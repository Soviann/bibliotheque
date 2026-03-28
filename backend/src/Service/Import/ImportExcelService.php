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
final readonly class ImportExcelService
{
    private const array SHEET_TYPE_MAP = [
        'BD' => ComicType::BD,
        'Comics' => ComicType::COMICS,
        'Livre' => ComicType::LIVRE,
        'Mangas' => ComicType::MANGA,
    ];

    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
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

                if ($result instanceof ImportResult) {
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
        $notInterestedBuy = $this->isNonValue($statusValue);
        $notInterestedNas = $this->isNonValue($row[6] ?? null);
        $onNas = $this->determineOnNas($row[6] ?? null);
        $onNasFini = $this->isFiniValue($row[6] ?? null);
        $publicationFinished = $this->isOuiValue($row[7] ?? null);
        $statusFini = $this->isFiniValue($row[1] ?? null);

        $latestPublishedIssue = $publishedCount->value;
        $latestPublishedIssueComplete = $publicationFinished
            || $publishedCount->isComplete
            || $lastBought->isComplete
            || $currentIssue->isComplete
            || $lastDownloaded->isComplete
            || $onNasFini
            || $statusFini;

        $defaultTomeBought = $lastBought->isComplete || $statusFini;
        $defaultTomeDownloaded = $lastDownloaded->isComplete || $onNasFini;
        $defaultTomeRead = $currentIssue->isComplete;

        if ($latestPublishedIssueComplete && null === $publishedCount->value) {
            $latestPublishedIssue = \max(
                $currentIssue->value ?? 0,
                $lastBought->value ?? 0,
                $lastDownloaded->value ?? 0
            );
            if (0 === $latestPublishedIssue) {
                $latestPublishedIssue = null;
            }
        }

        $existing = $this->comicSeriesRepository->findOneByFuzzyTitle($title, $comicType);
        $isUpdate = $existing instanceof ComicSeries;
        $comic = $existing ?? new ComicSeries();

        if (!$isUpdate) {
            $comic->setTitle($title);
            $comic->setType($comicType);
        }

        $comic->setDefaultTomeBought($defaultTomeBought);
        $comic->setDefaultTomeDownloaded($defaultTomeDownloaded);
        $comic->setDefaultTomeRead($defaultTomeRead);
        $comic->setLatestPublishedIssue($latestPublishedIssue);
        $comic->setLatestPublishedIssueComplete($latestPublishedIssueComplete);
        $comic->setNotInterestedBuy($notInterestedBuy);
        $comic->setNotInterestedNas($notInterestedNas);
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
            $latestPublishedIssue,
            $publishedCount->hsCount,
            $lastBought->specificValues,
            $lastDownloaded->specificValues,
        );

        return new ImportResult(isUpdate: $isUpdate, series: $comic, tomesCount: $tomesCount);
    }

    /**
     * Synchronise les tomes pour une série (crée les manquants, met à jour les existants).
     */
    /**
     * @param list<int>|null $specificBoughtValues     Tomes spécifiques achetés (format CSV)
     * @param list<int>|null $specificDownloadedValues Tomes spécifiques téléchargés (format CSV)
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
        ?int $hsCount = null,
        ?array $specificBoughtValues = null,
        ?array $specificDownloadedValues = null,
    ): int {
        $maxTomeNumber = $this->determineMaxTomeNumber(
            $currentIssueValue,
            $currentIssueComplete,
            $lastBoughtValue,
            $lastDownloadedValue,
            $latestPublishedIssue
        );

        $newTomesCount = 0;

        if (null !== $maxTomeNumber && $maxTomeNumber > 0) {
            $existingRegular = [];
            foreach ($comic->getTomes() as $tome) {
                if (!$tome->isHorsSerie()) {
                    $existingRegular[$tome->getNumber()] = $tome;
                }
            }

            for ($number = 1; $number <= $maxTomeNumber; ++$number) {
                $isBought = $lastBoughtComplete
                    || (null !== $specificBoughtValues && \in_array($number, $specificBoughtValues, true))
                    || (null === $specificBoughtValues && null !== $lastBoughtValue && $number <= $lastBoughtValue);
                $isDownloaded = $lastDownloadedComplete
                    || (null !== $specificDownloadedValues && \in_array($number, $specificDownloadedValues, true))
                    || (null === $specificDownloadedValues && null !== $lastDownloadedValue && $number <= $lastDownloadedValue);

                if (isset($existingRegular[$number])) {
                    $existingRegular[$number]->setBought($isBought);
                    $existingRegular[$number]->setDownloaded($isDownloaded);
                    $existingRegular[$number]->setOnNas($onNas);
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
        }

        // Tomes hors-série
        if (null !== $hsCount && $hsCount > 0) {
            $existingHs = [];
            foreach ($comic->getTomes() as $tome) {
                if ($tome->isHorsSerie()) {
                    $existingHs[$tome->getNumber()] = $tome;
                }
            }

            for ($number = 1; $number <= $hsCount; ++$number) {
                if (!isset($existingHs[$number])) {
                    $tome = new Tome();
                    $tome->setIsHorsSerie(true);
                    $tome->setNumber($number);
                    $tome->setOnNas($onNas);
                    $comic->addTome($tome);
                    ++$newTomesCount;
                }
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
        ?int $lastDownloadedValue,
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

        if (null !== $lastBoughtValue) {
            $candidates[] = $lastBoughtValue;
        }
        if (null !== $lastDownloadedValue) {
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
            'fini' => ComicStatus::FINISHED,
            'non', 'oui', '' => ComicStatus::BUYING,
            default => ComicStatus::BUYING,
        };
    }

    /**
     * Vérifie si une valeur est "fini".
     */
    /**
     * Normalise une valeur mixte en chaîne minuscule trimée.
     */
    private function normalizeValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return \is_scalar($value) ? \mb_strtolower(\trim((string) $value)) : '';
    }

    private function isFiniValue(mixed $value): bool
    {
        return 'fini' === $this->normalizeValue($value);
    }

    private function determineOnNas(mixed $value): bool
    {
        $normalized = $this->normalizeValue($value);

        return '' !== $normalized && 'non' !== $normalized;
    }

    /**
     * Vérifie si une valeur est "non".
     */
    private function isNonValue(mixed $value): bool
    {
        return 'non' === $this->normalizeValue($value);
    }

    /**
     * Vérifie si une valeur est "oui".
     */
    private function isOuiValue(mixed $value): bool
    {
        return 'oui' === $this->normalizeValue($value);
    }

    /**
     * Parse une valeur qui peut être un entier ou "fini".
     */
    private function parseIntegerValue(mixed $value): ParsedIntegerValue
    {
        if (null === $value) {
            return new ParsedIntegerValue(hsCount: null, isComplete: false, specificValues: null, value: null);
        }

        $value = \is_scalar($value) ? \trim((string) $value) : '';

        if ('' === $value) {
            return new ParsedIntegerValue(hsCount: null, isComplete: false, specificValues: null, value: null);
        }

        $lowerValue = \mb_strtolower($value);

        if ('fini' === $lowerValue) {
            return new ParsedIntegerValue(hsCount: null, isComplete: true, specificValues: null, value: null);
        }

        // Format "fini N+MHS" ou "fini N+HS" : parution terminée avec hors-série
        if (1 === \preg_match('/^fini\s+(\d+)\+(\d*)HS$/i', $value, $finiHsMatches)) {
            $intValue = (int) $finiHsMatches[1];
            $hsCount = '' === $finiHsMatches[2] ? 1 : (int) $finiHsMatches[2];

            return new ParsedIntegerValue(
                hsCount: $hsCount > 0 ? $hsCount : null,
                isComplete: true,
                specificValues: null,
                value: $intValue > 0 ? $intValue : null,
            );
        }

        // Format "fini N" : parution terminée avec nombre de tomes
        if (1 === \preg_match('/^fini\s+(\d+)$/i', $value, $finiMatches)) {
            $intValue = (int) $finiMatches[1];

            return new ParsedIntegerValue(hsCount: null, isComplete: true, specificValues: null, value: $intValue > 0 ? $intValue : null);
        }

        // Format "N+MHS" ou "N+HS" : tomes réguliers + hors-série
        if (1 === \preg_match('/^(\d+)\+(\d*)HS$/i', $value, $hsMatches)) {
            $intValue = (int) $hsMatches[1];
            $hsCount = '' === $hsMatches[2] ? 1 : (int) $hsMatches[2];

            return new ParsedIntegerValue(
                hsCount: $hsCount > 0 ? $hsCount : null,
                isComplete: false,
                specificValues: null,
                value: $intValue > 0 ? $intValue : null,
            );
        }

        // Format CSV "2, 5, 8" : liste de tomes spécifiques
        if (\str_contains($value, ',')) {
            $parts = \explode(',', $value);
            $specificValues = [];
            foreach ($parts as $part) {
                $intVal = (int) \trim($part);
                if ($intVal > 0) {
                    $specificValues[] = $intVal;
                }
            }

            \sort($specificValues);
            $maxVal = \count($specificValues) > 0 ? \max($specificValues) : 0;

            return new ParsedIntegerValue(
                hsCount: null,
                isComplete: false,
                specificValues: \count($specificValues) > 0 ? $specificValues : null,
                value: $maxVal > 0 ? $maxVal : null,
            );
        }

        $intValue = (int) $value;

        return new ParsedIntegerValue(hsCount: null, isComplete: false, specificValues: null, value: $intValue > 0 ? $intValue : null);
    }
}
