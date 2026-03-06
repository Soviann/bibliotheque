<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\DTO\ImportExcelResult;
use App\DTO\ImportResult;
use App\DTO\ParsedIntegerValue;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Service d'import des données depuis un fichier Excel de suivi.
 */
class ImportExcelService
{
    private const array SHEET_TYPE_MAP = [
        'BD' => ComicType::BD,
        'Comics' => ComicType::COMICS,
        'Livre' => ComicType::LIVRE,
        'Mangas' => ComicType::MANGA,
    ];

    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Importe les données depuis un fichier Excel.
     */
    public function import(string $filePath, bool $dryRun): ImportExcelResult
    {
        $spreadsheet = IOFactory::load($filePath);

        $sheetDetails = [];
        $totalCreated = 0;
        $totalTomes = 0;
        $totalUpdated = 0;

        foreach (self::SHEET_TYPE_MAP as $sheetName => $comicType) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (!$sheet instanceof Worksheet) {
                continue;
            }

            /** @var array<int, array<int, mixed>> $data */
            $data = $sheet->toArray();
            $created = 0;
            $tomesCount = 0;
            $updated = 0;
            $counter = \count($data);

            for ($i = 1; $i < $counter; ++$i) {
                $row = $data[$i];
                $title = \is_scalar($row[0]) ? \trim((string) $row[0]) : '';

                if ('' === $title) {
                    continue;
                }

                $result = $this->importRow($row, $comicType);

                if (null !== $result) {
                    if (!$dryRun) {
                        $this->entityManager->persist($result->series);
                    }

                    if ($result->isUpdate) {
                        ++$updated;
                    } else {
                        ++$created;
                    }
                    $tomesCount += $result->tomesCount;
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $sheetDetails[$sheetName] = ['created' => $created, 'tomes' => $tomesCount, 'updated' => $updated];
            $totalCreated += $created;
            $totalTomes += $tomesCount;
            $totalUpdated += $updated;
        }

        return new ImportExcelResult(
            sheetDetails: $sheetDetails,
            totalCreated: $totalCreated,
            totalTomes: $totalTomes,
            totalUpdated: $totalUpdated,
        );
    }

    /**
     * Normalise un titre contenant un article entre parenthèses en fin de chaîne.
     *
     * Exemples : "age d'ombre (l')" → "l'age d'ombre", "monde perdu (le)" → "le monde perdu"
     */
    public static function normalizeTitle(string $title): string
    {
        if (1 === \preg_match('/^(.+?)\s+\((l\'|le|la|les)\)$/iu', $title, $matches)) {
            $article = $matches[2];
            $rest = $matches[1];

            $separator = \str_ends_with($article, "'") ? '' : ' ';

            return $article.$separator.$rest;
        }

        return $title;
    }

    /**
     * Importe une ligne du fichier Excel.
     *
     * @param array<int, mixed> $row
     */
    private function importRow(array $row, ComicType $comicType): ?ImportResult
    {
        $title = \is_scalar($row[0]) ? \trim((string) $row[0]) : '';

        if ('' === $title) {
            return null;
        }

        $title = self::normalizeTitle($title);

        $statusValue = isset($row[1]) && \is_string($row[1]) ? $row[1] : null;
        $lastBought = $this->parseIntegerValue($row[2] ?? null);
        $currentIssue = $this->parseIntegerValue($row[3] ?? null);
        $publishedCount = $this->parseIntegerValue($row[4] ?? null);
        $lastDownloaded = $this->parseIntegerValue($row[5] ?? null);
        $onNas = $this->determineOnNas($row[6] ?? null);

        $latestPublishedIssue = $publishedCount->value;
        $latestPublishedIssueComplete = $publishedCount->isComplete;

        if ($publishedCount->isComplete && null === $publishedCount->value) {
            $latestPublishedIssue = \max(
                $currentIssue->value ?? 0,
                $lastBought->value ?? 0,
                $lastDownloaded->value ?? 0
            );
            if (0 === $latestPublishedIssue) {
                $latestPublishedIssue = null;
            }
        }

        $existing = $this->comicSeriesRepository->findOneBy(['title' => $title, 'type' => $comicType]);
        $isUpdate = null !== $existing;
        $comic = $existing ?? new ComicSeries();

        if (!$isUpdate) {
            $comic->setTitle($title);
            $comic->setType($comicType);
        }

        $comic->setLatestPublishedIssue($latestPublishedIssue);
        $comic->setLatestPublishedIssueComplete($latestPublishedIssueComplete);
        $comic->setStatus($this->determineStatus($statusValue));

        $tomesCount = $this->syncTomes(
            $comic,
            $currentIssue->value,
            $currentIssue->isComplete,
            $lastBought->value,
            $lastBought->isComplete,
            $lastDownloaded->value,
            $lastDownloaded->isComplete,
            $onNas,
            $latestPublishedIssue
        );

        return new ImportResult(isUpdate: $isUpdate, series: $comic, tomesCount: $tomesCount);
    }

    /**
     * Synchronise les tomes pour une série (crée les manquants, met à jour les existants).
     */
    private function syncTomes(
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

        $existingTomes = [];
        foreach ($comic->getTomes() as $tome) {
            $existingTomes[$tome->getNumber()] = $tome;
        }

        $newTomesCount = 0;

        for ($number = 1; $number <= $maxTomeNumber; ++$number) {
            $isBought = $lastBoughtComplete
                || (null !== $lastBoughtValue && $number <= $lastBoughtValue);
            $isDownloaded = $lastDownloadedComplete
                || (null !== $lastDownloadedValue && $number <= $lastDownloadedValue);

            if (isset($existingTomes[$number])) {
                $existingTomes[$number]->setBought($isBought);
                $existingTomes[$number]->setDownloaded($isDownloaded);
                $existingTomes[$number]->setOnNas($onNas);
            } else {
                $tome = new Tome();
                $tome->setBought($isBought);
                $tome->setDownloaded($isDownloaded);
                $tome->setNumber($number);
                $tome->setOnNas($onNas);
                $comic->addTome($tome);
                ++$newTomesCount;
            }
        }

        return $newTomesCount;
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

        if ($currentIssueComplete) {
            if (null !== $latestPublishedIssue) {
                $candidates[] = $latestPublishedIssue;
            }
        } elseif (null !== $currentIssueValue) {
            $candidates[] = $currentIssueValue;
        }

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
     */
    private function parseIntegerValue(mixed $value): ParsedIntegerValue
    {
        if (null === $value) {
            return new ParsedIntegerValue(isComplete: false, value: null);
        }

        $value = \is_scalar($value) ? \trim((string) $value) : '';

        if ('' === $value) {
            return new ParsedIntegerValue(isComplete: false, value: null);
        }

        if ('fini' === \mb_strtolower($value)) {
            return new ParsedIntegerValue(isComplete: true, value: null);
        }

        if (\str_contains($value, ',')) {
            $parts = \explode(',', $value);
            $maxVal = 0;
            foreach ($parts as $part) {
                $intVal = (int) \trim($part);
                if ($intVal > $maxVal) {
                    $maxVal = $intVal;
                }
            }

            return new ParsedIntegerValue(isComplete: false, value: $maxVal > 0 ? $maxVal : null);
        }

        $intValue = (int) $value;

        return new ParsedIntegerValue(isComplete: false, value: $intValue > 0 ? $intValue : null);
    }
}
