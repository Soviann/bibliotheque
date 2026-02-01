<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
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
 * Commande d'import des données depuis un fichier Excel.
 *
 * Structure du fichier attendue (colonnes) :
 * - 0: Titre
 * - 1: Buy? (statut d'achat : oui/non/fini)
 * - 2: Last bought (dernier numéro acheté ou "fini")
 * - 3: Current (dernier numéro possédé ou "fini")
 * - 4: Parution (dernier numéro paru ou "fini")
 * - 5: Last dled (dernier numéro téléchargé ou "fini")
 * - 6: On NAS? (présence sur le NAS)
 */
#[AsCommand(
    name: 'app:import-excel',
    description: 'Importe les données depuis un fichier Excel',
)]
class ImportExcelCommand extends Command
{
    private const SHEET_TYPE_MAP = [
        'BD' => ComicType::BD,
        'Comics' => ComicType::COMICS,
        'Livre' => ComicType::LIVRE,
        'Mangas' => ComicType::MANGA,
    ];

    public function __construct(
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

        $io->title('Import des données depuis Excel');

        if ($dryRun) {
            $io->warning('Mode simulation activé (--dry-run). Aucune donnée ne sera persistée.');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (ReaderException $e) {
            $io->error(\sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $totalImported = 0;
        $totalTomes = 0;

        foreach (self::SHEET_TYPE_MAP as $sheetName => $comicType) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (null === $sheet) {
                $io->warning(\sprintf('Onglet "%s" non trouvé, ignoré.', $sheetName));
                continue;
            }

            $io->section(\sprintf('Import de l\'onglet "%s"', $sheetName));

            /** @var array<int, array<int, mixed>> $data */
            $data = $sheet->toArray();
            $imported = 0;
            $tomesCreated = 0;

            // Ignorer la ligne d'en-tête
            for ($i = 1; $i < \count($data); ++$i) {
                $row = $data[$i];
                $title = \is_scalar($row[0]) ? \trim((string) $row[0]) : '';

                if ('' === $title) {
                    continue;
                }

                $result = $this->importRow($row, $comicType, $io);

                if (null !== $result) {
                    if (!$dryRun) {
                        $this->entityManager->persist($result['series']);
                    }
                    ++$imported;
                    $tomesCreated += $result['tomesCount'];
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $io->success(\sprintf(
                '%d séries importées avec %d tomes depuis "%s".',
                $imported,
                $tomesCreated,
                $sheetName
            ));
            $totalImported += $imported;
            $totalTomes += $tomesCreated;
        }

        $io->success(\sprintf(
            'Import terminé. Total : %d séries, %d tomes.',
            $totalImported,
            $totalTomes
        ));

        return Command::SUCCESS;
    }

    /**
     * Importe une ligne du fichier Excel.
     *
     * @param array<int, mixed> $row
     *
     * @return array{series: ComicSeries, tomesCount: int}|null
     */
    private function importRow(array $row, ComicType $comicType, SymfonyStyle $io): ?array
    {
        $title = \is_scalar($row[0]) ? \trim((string) $row[0]) : '';

        if ('' === $title) {
            return null;
        }

        // Création de la série
        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setType($comicType);
        $statusValue = isset($row[1]) && \is_string($row[1]) ? $row[1] : null;
        $comic->setStatus($this->determineStatus($statusValue));
        $comic->setIsWishlist(false);

        // Extraction des valeurs numériques
        [$lastBoughtValue, $lastBoughtComplete] = $this->parseIntegerValue($row[2] ?? null);
        [$currentIssueValue, $currentIssueComplete] = $this->parseIntegerValue($row[3] ?? null);
        [$publishedCountValue, $publishedCountComplete] = $this->parseIntegerValue($row[4] ?? null);
        [$lastDownloadedValue, $lastDownloadedComplete] = $this->parseIntegerValue($row[5] ?? null);
        $onNas = $this->determineOnNas($row[6] ?? null);

        // Détermination du dernier tome paru
        $latestPublishedIssue = $publishedCountValue;
        $latestPublishedIssueComplete = $publishedCountComplete;

        // Si "fini" pour parution, on prend le max des autres valeurs
        if ($publishedCountComplete && null === $publishedCountValue) {
            $latestPublishedIssue = \max(
                $currentIssueValue ?? 0,
                $lastBoughtValue ?? 0,
                $lastDownloadedValue ?? 0
            );
            if (0 === $latestPublishedIssue) {
                $latestPublishedIssue = null;
            }
        }

        $comic->setLatestPublishedIssue($latestPublishedIssue);
        $comic->setLatestPublishedIssueComplete($latestPublishedIssueComplete);

        // Création des tomes
        $tomesCount = $this->createTomes(
            $comic,
            $currentIssueValue,
            $currentIssueComplete,
            $lastBoughtValue,
            $lastBoughtComplete,
            $lastDownloadedValue,
            $lastDownloadedComplete,
            $onNas,
            $latestPublishedIssue
        );

        return [
            'series' => $comic,
            'tomesCount' => $tomesCount,
        ];
    }

    /**
     * Crée les tomes pour une série.
     */
    private function createTomes(
        ComicSeries $comic,
        ?int $currentIssueValue,
        bool $currentIssueComplete,
        ?int $lastBoughtValue,
        bool $lastBoughtComplete,
        ?int $lastDownloadedValue,
        bool $lastDownloadedComplete,
        bool $onNas,
        ?int $latestPublishedIssue,
    ): int {
        // Déterminer le nombre de tomes à créer
        $maxTomeNumber = $this->determineMaxTomeNumber(
            $currentIssueValue,
            $currentIssueComplete,
            $lastBoughtValue,
            $lastBoughtComplete,
            $lastDownloadedValue,
            $lastDownloadedComplete,
            $latestPublishedIssue
        );

        if (null === $maxTomeNumber || $maxTomeNumber <= 0) {
            return 0;
        }

        // Créer les tomes de 1 à maxTomeNumber
        for ($number = 1; $number <= $maxTomeNumber; ++$number) {
            $tome = new Tome();
            $tome->setNumber($number);

            // Marquer comme acheté si <= lastBought ou si lastBoughtComplete
            $isBought = $lastBoughtComplete
                || (null !== $lastBoughtValue && $number <= $lastBoughtValue);
            $tome->setBought($isBought);

            // Marquer comme téléchargé si <= lastDownloaded ou si lastDownloadedComplete
            $isDownloaded = $lastDownloadedComplete
                || (null !== $lastDownloadedValue && $number <= $lastDownloadedValue);
            $tome->setDownloaded($isDownloaded);

            // Marquer comme sur NAS si la série est sur NAS
            $tome->setOnNas($onNas);

            $comic->addTome($tome);
        }

        return $maxTomeNumber;
    }

    /**
     * Détermine le nombre maximum de tomes à créer.
     */
    private function determineMaxTomeNumber(
        ?int $currentIssueValue,
        bool $currentIssueComplete,
        ?int $lastBoughtValue,
        bool $lastBoughtComplete,
        ?int $lastDownloadedValue,
        bool $lastDownloadedComplete,
        ?int $latestPublishedIssue,
    ): ?int {
        $candidates = [];

        // Si currentIssue est "fini", utiliser latestPublishedIssue
        if ($currentIssueComplete) {
            if (null !== $latestPublishedIssue) {
                $candidates[] = $latestPublishedIssue;
            }
        } elseif (null !== $currentIssueValue) {
            $candidates[] = $currentIssueValue;
        }

        // Ajouter les autres valeurs comme candidates
        if (!$lastBoughtComplete && null !== $lastBoughtValue) {
            $candidates[] = $lastBoughtValue;
        }
        if (!$lastDownloadedComplete && null !== $lastDownloadedValue) {
            $candidates[] = $lastDownloadedValue;
        }

        if ([] === $candidates) {
            return null;
        }

        return \max($candidates);
    }

    private function determineStatus(?string $value): ComicStatus
    {
        if (null === $value) {
            return ComicStatus::BUYING;
        }

        $value = \mb_strtolower(\trim($value));

        return match ($value) {
            'non' => ComicStatus::STOPPED,
            'fini' => ComicStatus::FINISHED,
            'oui', '' => ComicStatus::BUYING,
            default => ComicStatus::BUYING,
        };
    }

    private function determineOnNas(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        $value = \is_scalar($value) ? \mb_strtolower(\trim((string) $value)) : '';

        return '' !== $value && 'non' !== $value;
    }

    /**
     * Parse une valeur qui peut être un entier ou "fini".
     *
     * @return array{0: ?int, 1: bool}
     */
    private function parseIntegerValue(mixed $value): array
    {
        if (null === $value) {
            return [null, false];
        }

        $value = \is_scalar($value) ? \trim((string) $value) : '';

        if ('' === $value) {
            return [null, false];
        }

        if ('fini' === \mb_strtolower($value)) {
            return [null, true];
        }

        // Gérer les valeurs comme "3, 4" en prenant le max
        if (\str_contains($value, ',')) {
            $parts = \explode(',', $value);
            $maxVal = 0;
            foreach ($parts as $part) {
                $intVal = (int) \trim($part);
                if ($intVal > $maxVal) {
                    $maxVal = $intVal;
                }
            }

            return $maxVal > 0 ? [$maxVal, false] : [null, false];
        }

        $intValue = (int) $value;

        return $intValue > 0 ? [$intValue, false] : [null, false];
    }
}
