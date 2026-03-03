<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe des livres depuis un fichier Excel (format Livres.xlsx).
 *
 * Colonnes attendues : Code-barres, Titre, Auteur, Éditeur, Couverture, Catégories, Description
 * Les 4 dernières colonnes (Notes, Prêt, Couverture.http, MEMENTO_ID) sont ignorées.
 *
 * Regroupe automatiquement les tomes d'une même série (détection par « Tome X », « TX », etc.).
 * Enrichit les séries déjà présentes en base (auteurs, éditeur, couverture, description, ISBN).
 */
#[AsCommand(
    name: 'app:import-books',
    description: 'Importe des livres depuis un fichier Excel (format Livres.xlsx)',
)]
class ImportBooksCommand extends Command
{
    /**
     * Correspondance catégorie → ComicType (par ordre de priorité).
     */
    private const array CATEGORY_TYPE_MAP = [
        'BD' => ComicType::BD,
        'Comics' => ComicType::COMICS,
        'Manga' => ComicType::MANGA,
    ];

    /**
     * Patterns pour extraire le nom de série et le numéro de tome depuis un titre.
     *
     * @var string[]
     */
    private const array TOME_PATTERNS = [
        '/^(.+?)\s*[-–]\s*[Tt]ome\s+(\d+)/u',           // Title - Tome X
        '/^(.+?),\s*[Tt]ome\s+(\d+)/u',                  // Title, Tome X
        '/^(.+?)\s*[-–]\s*[Tt](\d+)\s/u',                // Title - T01 (suivi d'un espace)
        '/^(.+?)\s*[-–]\s*[Tt](\d+)$/u',                 // Title - T01 (fin de chaîne)
        '/^(.+?)\s+[Tt](\d+)\s*[-–:]/u',                 // Title T01 - subtitle
        '/^(.+?)\s+[Tt](\d+)$/u',                        // Title T01 (fin de chaîne)
        '/^(.+?),\s*[Tt](\d+)\s*[-–:]/u',                // Title, T01 - subtitle
        '/^(.+?)\s*[-–]\s*n[oº°]?\s*(\d+)/u',            // Title - nº37 / no9
    ];

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin vers le fichier Excel')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler l\'import sans persister')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $filePath */
        $filePath = $input->getArgument('file');
        $dryRun = $input->getOption('dry-run');

        if (!\file_exists($filePath)) {
            $io->error(\sprintf('Le fichier "%s" n\'existe pas.', $filePath));

            return Command::FAILURE;
        }

        $io->title('Import des livres depuis Excel');

        if ($dryRun) {
            $io->warning('Mode simulation activé (--dry-run). Aucune donnée ne sera persistée.');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (ReaderException $e) {
            $io->error(\sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $sheet = $spreadsheet->getActiveSheet();
        /** @var array<int, array<int, mixed>> $data */
        $data = $sheet->toArray();

        // Phase 1 : parser et grouper les lignes
        $groups = $this->groupRows($data);

        $io->section(\sprintf('%d groupes détectés (%d séries, %d one-shots)',
            \count($groups),
            \count(\array_filter($groups, static fn (array $g): bool => \count($g['rows']) > 1 || null !== $g['rows'][0]['tomeNumber'])),
            \count(\array_filter($groups, static fn (array $g): bool => 1 === \count($g['rows']) && null === $g['rows'][0]['tomeNumber']))
        ));

        // Phase 2 : importer chaque groupe
        $created = 0;
        $enriched = 0;

        foreach ($groups as $group) {
            $seriesName = $group['seriesName'];
            $rows = $group['rows'];

            // Chercher une série existante en base
            $existing = $this->comicSeriesRepository->findOneBy(['title' => $seriesName]);

            if (null !== $existing) {
                $this->enrichExisting($existing, $rows);

                if (!$dryRun) {
                    $this->entityManager->flush();
                }

                $tomeNumbers = \array_filter(\array_column($rows, 'tomeNumber'));
                $io->text(\sprintf('  [enrichi] %s%s',
                    $seriesName,
                    [] !== $tomeNumbers ? ' (tomes ' . \implode(', ', $tomeNumbers) . ')' : ''
                ));
                ++$enriched;

                continue;
            }

            $comic = $this->createSeries($seriesName, $rows);

            if (!$dryRun) {
                $this->entityManager->persist($comic);
                $this->entityManager->flush();
            }

            $io->text(\sprintf(
                '  [%s] %s — %s (%s)%s',
                $comic->getType()->getLabel(),
                $comic->getTitle(),
                $comic->getAuthorsAsString() ?: '—',
                $comic->getPublisher() ?? '—',
                $comic->getTomes()->count() > 1 ? \sprintf(' [%d tomes]', $comic->getTomes()->count()) : ''
            ));

            ++$created;
        }

        $io->success(\sprintf(
            'Import terminé. %d créés, %d enrichis.',
            $created,
            $enriched
        ));

        return Command::SUCCESS;
    }

    /**
     * Parse toutes les lignes et les regroupe par nom de série.
     *
     * @param array<int, array<int, mixed>> $data
     *
     * @return array<string, array{seriesName: string, rows: list<array{row: array<int, mixed>, tomeNumber: ?int, originalTitle: string}>}>
     */
    private function groupRows(array $data): array
    {
        /** @var array<string, array{seriesName: string, rows: list<array{row: array<int, mixed>, tomeNumber: ?int, originalTitle: string}>}> $groups */
        $groups = [];

        $counter = \count($data);
        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $title = \is_scalar($row[1]) ? \trim((string) $row[1]) : '';

            if ('' === $title) {
                continue;
            }

            [$seriesName, $tomeNumber] = $this->extractSeriesInfo($title);
            $key = \mb_strtolower($seriesName);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'rows' => [],
                    'seriesName' => $seriesName,
                ];
            }

            $groups[$key]['rows'][] = [
                'originalTitle' => $title,
                'row' => $row,
                'tomeNumber' => $tomeNumber,
            ];
        }

        return $groups;
    }

    /**
     * Extrait le nom de série et le numéro de tome depuis un titre.
     *
     * @return array{0: string, 1: ?int}
     */
    private function extractSeriesInfo(string $title): array
    {
        foreach (self::TOME_PATTERNS as $pattern) {
            if (1 === \preg_match($pattern, $title, $matches)) {
                $seriesName = \trim($matches[1]);
                // Nettoyer les caractères de ponctuation en fin de nom
                $seriesName = \rtrim($seriesName, ' -–:,');

                if ('' !== $seriesName) {
                    return [$seriesName, (int) $matches[2]];
                }
            }
        }

        return [$title, null];
    }

    /**
     * Crée une nouvelle série à partir d'un groupe de lignes.
     *
     * @param list<array{row: array<int, mixed>, tomeNumber: ?int, originalTitle: string}> $rows
     */
    private function createSeries(string $seriesName, array $rows): ComicSeries
    {
        // Prendre les métadonnées du premier row qui en a
        $firstRow = $rows[0]['row'];
        $authorNames = \is_scalar($firstRow[2]) ? \trim((string) $firstRow[2]) : '';
        $publisher = \is_scalar($firstRow[3]) ? \trim((string) $firstRow[3]) : null;
        $coverUrl = \is_scalar($firstRow[4]) ? \trim((string) $firstRow[4]) : null;
        $categories = \is_scalar($firstRow[5]) ? \trim((string) $firstRow[5]) : '';
        $description = \is_scalar($firstRow[6]) ? \trim((string) $firstRow[6]) : null;

        $isOneShot = 1 === \count($rows) && null === $rows[0]['tomeNumber'];

        $comic = new ComicSeries();
        $comic->setCoverUrl('' !== $coverUrl && !\str_starts_with((string) $coverUrl, 'file://') ? $coverUrl : null);
        $comic->setDescription('' !== $description ? $description : null);
        $comic->setIsOneShot($isOneShot);
        $comic->setPublisher('' !== $publisher ? $publisher : null);
        $comic->setStatus(ComicStatus::FINISHED);
        $comic->setTitle($seriesName);
        $comic->setType($this->determineType($categories));

        // Auteurs — fusionner de toutes les lignes du groupe
        $allAuthorNames = $this->collectAuthorNames($rows);
        if ([] !== $allAuthorNames) {
            $authors = $this->authorRepository->findOrCreateMultiple($allAuthorNames);
            foreach ($authors as $author) {
                $comic->addAuthor($author);
            }
        }

        if ($isOneShot) {
            $comic->setLatestPublishedIssue(1);
            $comic->setLatestPublishedIssueComplete(true);

            $isbn = $this->cleanIsbn($firstRow[0] ?? null);
            $tome = new Tome();
            $tome->setBought(true);
            $tome->setIsbn($isbn);
            $tome->setNumber(1);
            $comic->addTome($tome);
        } else {
            // Créer un tome par entrée du groupe
            $maxTome = 0;
            foreach ($rows as $entry) {
                $tomeNumber = $entry['tomeNumber'] ?? 1;
                $isbn = $this->cleanIsbn($entry['row'][0] ?? null);

                $tome = new Tome();
                $tome->setBought(true);
                $tome->setIsbn($isbn);
                $tome->setNumber($tomeNumber);
                $comic->addTome($tome);

                if ($tomeNumber > $maxTome) {
                    $maxTome = $tomeNumber;
                }
            }

            $comic->setLatestPublishedIssue($maxTome);
            $comic->setLatestPublishedIssueComplete(true);
        }

        return $comic;
    }

    /**
     * Enrichit une série existante avec les métadonnées du fichier Excel.
     *
     * @param list<array{row: array<int, mixed>, tomeNumber: ?int, originalTitle: string}> $rows
     */
    private function enrichExisting(ComicSeries $comic, array $rows): void
    {
        $firstRow = $rows[0]['row'];
        $publisher = \is_scalar($firstRow[3]) ? \trim((string) $firstRow[3]) : null;
        $coverUrl = \is_scalar($firstRow[4]) ? \trim((string) $firstRow[4]) : null;
        $description = \is_scalar($firstRow[6]) ? \trim((string) $firstRow[6]) : null;

        if (null === $comic->getCoverUrl() && '' !== $coverUrl && !\str_starts_with((string) $coverUrl, 'file://')) {
            $comic->setCoverUrl($coverUrl);
        }

        if (null === $comic->getDescription() && '' !== $description) {
            $comic->setDescription($description);
        }

        if (null === $comic->getPublisher() && '' !== $publisher) {
            $comic->setPublisher($publisher);
        }

        // Ajouter les auteurs manquants
        if ($comic->getAuthors()->isEmpty()) {
            $allAuthorNames = $this->collectAuthorNames($rows);
            if ([] !== $allAuthorNames) {
                $authors = $this->authorRepository->findOrCreateMultiple($allAuthorNames);
                foreach ($authors as $author) {
                    $comic->addAuthor($author);
                }
            }
        }

        // Ajouter l'ISBN aux tomes existants qui n'en ont pas
        $existingTomes = [];
        foreach ($comic->getTomes() as $tome) {
            $existingTomes[$tome->getNumber()] = $tome;
        }

        foreach ($rows as $entry) {
            $isbn = $this->cleanIsbn($entry['row'][0] ?? null);
            $tomeNumber = $entry['tomeNumber'] ?? 1;

            if (null !== $isbn && isset($existingTomes[$tomeNumber]) && null === $existingTomes[$tomeNumber]->getIsbn()) {
                $existingTomes[$tomeNumber]->setIsbn($isbn);
            }
        }
    }

    /**
     * Collecte les noms d'auteurs uniques depuis toutes les lignes d'un groupe.
     *
     * @param list<array{row: array<int, mixed>, tomeNumber: ?int, originalTitle: string}> $rows
     *
     * @return string[]
     */
    private function collectAuthorNames(array $rows): array
    {
        $names = [];
        foreach ($rows as $entry) {
            $authorField = \is_scalar($entry['row'][2]) ? \trim((string) $entry['row'][2]) : '';
            if ('' !== $authorField) {
                foreach (\explode(',', $authorField) as $name) {
                    $name = \trim($name);
                    if ('' !== $name && !\in_array(\mb_strtolower($name), \array_map('mb_strtolower', $names), true)) {
                        $names[] = $name;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Nettoie un ISBN (supprime le .0 ajouté par Excel).
     */
    private function cleanIsbn(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $isbn = \trim((string) $value);

        if ('' === $isbn) {
            return null;
        }

        // Supprimer le .0 ajouté par Excel pour les nombres
        if (\str_ends_with($isbn, '.0')) {
            $isbn = \substr($isbn, 0, -2);
        }

        return $isbn;
    }

    /**
     * Détermine le ComicType à partir des catégories.
     */
    private function determineType(string $categories): ComicType
    {
        $upper = \mb_strtoupper($categories);

        foreach (self::CATEGORY_TYPE_MAP as $keyword => $type) {
            if (\str_contains($upper, \mb_strtoupper($keyword))) {
                return $type;
            }
        }

        return ComicType::LIVRE;
    }
}
