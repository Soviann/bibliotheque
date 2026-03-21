<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\DTO\BookGroup;
use App\DTO\BookRow;
use App\DTO\ImportBooksResult;
use App\DTO\SeriesInfo;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service d'import des livres depuis un fichier Excel (format Livres.xlsx).
 */
final class ImportBooksService
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
        '/^(.+?)\s*[-–]\s*[Tt]ome\s+(\d+)/u',
        '/^(.+?),\s*[Tt]ome\s+(\d+)/u',
        '/^(.+?)\s*[-–]\s*[Tt](\d+)\s/u',
        '/^(.+?)\s*[-–]\s*[Tt](\d+)$/u',
        '/^(.+?)\s+[Tt](\d+)\s*[-–:]/u',
        '/^(.+?)\s+[Tt](\d+)$/u',
        '/^(.+?),\s*[Tt](\d+)\s*[-–:]/u',
        '/^(.+?)\s*[-–]\s*n[oº°]?\s*(\d+)/u',
    ];

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Importe les livres depuis un fichier Excel.
     */
    public function import(string $filePath, bool $dryRun): ImportBooksResult
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        /** @var array<int, array<int, mixed>> $data */
        $data = $sheet->toArray();

        $groups = $this->groupRows($data);

        $created = 0;
        $enriched = 0;

        foreach ($groups as $group) {
            $existing = $this->comicSeriesRepository->findOneByFuzzyTitleAnyType($group->seriesName);

            if (null !== $existing) {
                $this->enrichExisting($existing, $group->rows);

                if (!$dryRun) {
                    $this->entityManager->flush();
                }

                ++$enriched;

                continue;
            }

            $comic = $this->createSeries($group->seriesName, $group->rows);

            if (!$dryRun) {
                $this->entityManager->persist($comic);
                $this->entityManager->flush();
            }

            ++$created;
        }

        return new ImportBooksResult(
            created: $created,
            enriched: $enriched,
            groupCount: \count($groups),
        );
    }

    /**
     * Parse toutes les lignes et les regroupe par nom de série.
     *
     * @param array<int, array<int, mixed>> $data
     *
     * @return array<string, BookGroup>
     */
    private function groupRows(array $data): array
    {
        /** @var array<string, array{seriesName: string, rows: list<BookRow>}> $groupData */
        $groupData = [];

        $counter = \count($data);
        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $title = \is_scalar($row[1]) ? \trim((string) $row[1]) : '';

            if ('' === $title) {
                continue;
            }

            $info = $this->extractSeriesInfo($title);
            $key = \mb_strtolower($info->name);

            if (!isset($groupData[$key])) {
                $groupData[$key] = [
                    'rows' => [],
                    'seriesName' => $info->name,
                ];
            }

            $groupData[$key]['rows'][] = new BookRow(originalTitle: $title, row: $row, tomeNumber: $info->tomeNumber);
        }

        $groups = [];
        foreach ($groupData as $key => $data) {
            $groups[$key] = new BookGroup(rows: $data['rows'], seriesName: $data['seriesName']);
        }

        return $groups;
    }

    /**
     * Extrait le nom de série et le numéro de tome depuis un titre.
     */
    private function extractSeriesInfo(string $title): SeriesInfo
    {
        foreach (self::TOME_PATTERNS as $pattern) {
            if (1 === \preg_match($pattern, $title, $matches)) {
                $seriesName = \trim($matches[1]);
                $seriesName = \rtrim($seriesName, ' -–:,');

                if ('' !== $seriesName) {
                    return new SeriesInfo(name: $seriesName, tomeNumber: (int) $matches[2]);
                }
            }
        }

        return new SeriesInfo(name: $title, tomeNumber: null);
    }

    /**
     * Crée une nouvelle série à partir d'un groupe de lignes.
     *
     * @param list<BookRow> $rows
     */
    private function createSeries(string $seriesName, array $rows): ComicSeries
    {
        $firstRow = $rows[0]->row;
        $publisher = \is_scalar($firstRow[3]) ? \trim((string) $firstRow[3]) : null;
        $coverUrl = \is_scalar($firstRow[4]) ? \trim((string) $firstRow[4]) : null;
        $categories = \is_scalar($firstRow[5]) ? \trim((string) $firstRow[5]) : '';
        $description = \is_scalar($firstRow[6]) ? \trim((string) $firstRow[6]) : null;

        $isOneShot = 1 === \count($rows) && null === $rows[0]->tomeNumber;

        $comic = new ComicSeries();
        $comic->setCoverUrl('' !== $coverUrl && !\str_starts_with((string) $coverUrl, 'file://') ? $coverUrl : null);
        $comic->setDescription('' !== $description ? $description : null);
        $comic->setIsOneShot($isOneShot);
        $comic->setPublisher('' !== $publisher ? $publisher : null);
        $comic->setStatus(ComicStatus::FINISHED);
        $comic->setTitle($seriesName);
        $comic->setType($this->determineType($categories));

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
            $maxTome = 0;
            foreach ($rows as $entry) {
                $tomeNumber = $entry->tomeNumber ?? 1;
                $isbn = $this->cleanIsbn($entry->row[0] ?? null);

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
     * @param list<BookRow> $rows
     */
    private function enrichExisting(ComicSeries $comic, array $rows): void
    {
        $firstRow = $rows[0]->row;
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

        if ($comic->getAuthors()->isEmpty()) {
            $allAuthorNames = $this->collectAuthorNames($rows);
            if ([] !== $allAuthorNames) {
                $authors = $this->authorRepository->findOrCreateMultiple($allAuthorNames);
                foreach ($authors as $author) {
                    $comic->addAuthor($author);
                }
            }
        }

        $existingTomes = [];
        foreach ($comic->getTomes() as $tome) {
            $existingTomes[$tome->getNumber()] = $tome;
        }

        foreach ($rows as $entry) {
            $isbn = $this->cleanIsbn($entry->row[0] ?? null);
            $tomeNumber = $entry->tomeNumber ?? 1;

            if (isset($existingTomes[$tomeNumber])) {
                $existingTomes[$tomeNumber]->setBought(true);

                if (null !== $isbn && null === $existingTomes[$tomeNumber]->getIsbn()) {
                    $existingTomes[$tomeNumber]->setIsbn($isbn);
                }
            } else {
                $tome = new Tome();
                $tome->setBought(true);
                $tome->setIsbn($isbn);
                $tome->setNumber($tomeNumber);
                $comic->addTome($tome);
            }
        }
    }

    /**
     * Collecte les noms d'auteurs uniques depuis toutes les lignes d'un groupe.
     *
     * @param list<BookRow> $rows
     *
     * @return string[]
     */
    private function collectAuthorNames(array $rows): array
    {
        $names = [];
        foreach ($rows as $entry) {
            $authorField = \is_scalar($entry->row[2]) ? \trim((string) $entry->row[2]) : '';
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

        $isbn = \trim(\is_scalar($value) ? (string) $value : '');

        if ('' === $isbn) {
            return null;
        }

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
