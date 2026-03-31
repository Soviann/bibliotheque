<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\DTO\ImportResult;
use App\DTO\ParsedIntegerValue;
use App\DTO\RowImportResult;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service d'import unifié : tracking + métadonnées depuis un fichier Excel unique.
 *
 * Feuille unique "Import" avec colonnes :
 * 0: Type, 1: Titre, 2: Achète?, 3: Dernier acheté, 4: Lu, 5: Parution,
 * 6: Dernier DL, 7: Sur NAS?, 8: Parution terminée,
 * 9: ISBN, 10: Auteur, 11: Couverture, 12: Éditeur, 13: Catégories, 14: Description
 */
final readonly class ImportService
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
     * Correspondance valeur de la colonne Type → ComicType.
     */
    private const array TYPE_VALUE_MAP = [
        'BD' => ComicType::BD,
        'Comics' => ComicType::COMICS,
        'Livre' => ComicType::LIVRE,
        'Manga' => ComicType::MANGA,
    ];

    public function __construct(
        private AuthorRepository $authorRepository,
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Importe les données depuis un fichier Excel unifié (feuille unique "Import").
     */
    public function import(string $filePath, bool $dryRun): ImportResult
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);

        /** @var array<int, array<int, mixed>> $data */
        $data = $sheet->toArray();

        $typeDetails = [];
        $totalCreated = 0;
        $totalEnriched = 0;
        $totalTomes = 0;
        $totalUpdated = 0;

        $counter = \count($data);

        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $typeString = \is_scalar($row[0]) ? \trim((string) $row[0]) : '';
            $title = \is_scalar($row[1]) ? \trim((string) $row[1]) : '';

            if ('' === $title || '' === $typeString) {
                continue;
            }

            $comicType = self::TYPE_VALUE_MAP[$typeString] ?? null;

            if (!$comicType instanceof ComicType) {
                continue;
            }

            $result = $this->importRow($row, $comicType);

            if ($result instanceof RowImportResult) {
                if (!$dryRun) {
                    $this->entityManager->persist($result->series);
                }

                if (!isset($typeDetails[$typeString])) {
                    $typeDetails[$typeString] = ['created' => 0, 'enriched' => 0, 'tomes' => 0, 'updated' => 0];
                }

                if ($result->isUpdate) {
                    ++$typeDetails[$typeString]['updated'];
                } else {
                    ++$typeDetails[$typeString]['created'];
                }
                if ($result->metadataApplied) {
                    ++$typeDetails[$typeString]['enriched'];
                }
                $typeDetails[$typeString]['tomes'] += $result->tomesCount;

                $totalCreated += $result->isUpdate ? 0 : 1;
                $totalUpdated += $result->isUpdate ? 1 : 0;
                if ($result->metadataApplied) {
                    ++$totalEnriched;
                }
                $totalTomes += $result->tomesCount;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return new ImportResult(
            typeDetails: $typeDetails,
            totalCreated: $totalCreated,
            totalEnriched: $totalEnriched,
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
    private function importRow(array $row, ComicType $comicType): ?RowImportResult
    {
        $title = \is_scalar($row[1]) ? \trim((string) $row[1]) : '';

        if ('' === $title) {
            return null;
        }

        $title = self::normalizeTitle($title);

        $statusValue = isset($row[2]) && \is_string($row[2]) ? $row[2] : null;
        $lastBought = $this->parseIntegerValue($row[3] ?? null);
        $currentIssue = $this->parseIntegerValue($row[4] ?? null);
        $publishedCount = $this->parseIntegerValue($row[5] ?? null);
        $lastOnNas = $this->parseIntegerValue($row[6] ?? null);
        $notInterestedBuy = $this->isNonValue($statusValue);
        $notInterestedNas = $this->isNonValue($row[7] ?? null);
        $onNas = $this->determineOnNas($row[7] ?? null);
        $onNasFini = $this->isFiniValue($row[7] ?? null);
        $publicationFinished = $this->isOuiValue($row[8] ?? null);
        $statusFini = $this->isFiniValue($row[2] ?? null);

        $latestPublishedIssue = $publishedCount->value;
        $latestPublishedIssueComplete = $publicationFinished
            || $publishedCount->isComplete
            || $lastBought->isComplete
            || $currentIssue->isComplete
            || $lastOnNas->isComplete
            || $onNasFini
            || $statusFini;

        $defaultTomeBought = $lastBought->isComplete || $statusFini;
        $defaultTomeOnNas = $lastOnNas->isComplete || $onNasFini;
        $defaultTomeRead = $currentIssue->isComplete;

        if ($latestPublishedIssueComplete && null === $publishedCount->value) {
            $latestPublishedIssue = \max(
                $currentIssue->value ?? 0,
                $lastBought->value ?? 0,
                $lastOnNas->value ?? 0
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
        $comic->setDefaultTomeOnNas($defaultTomeOnNas);
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
            $lastOnNas->value,
            $lastOnNas->isComplete,
            $onNas,
            $latestPublishedIssue,
            $publishedCount->hsCount,
            $lastBought->specificValues,
            $lastOnNas->specificValues,
        );

        $metadataApplied = $this->enrichRow($comic, $row);

        return new RowImportResult(isUpdate: $isUpdate, metadataApplied: $metadataApplied, series: $comic, tomesCount: $tomesCount);
    }

    /**
     * Enrichit une série avec les métadonnées des colonnes 9-14.
     *
     * @param array<int, mixed> $row
     */
    private function enrichRow(ComicSeries $comic, array $row): bool
    {
        $isbnField = isset($row[9]) && \is_scalar($row[9]) ? \trim((string) $row[9]) : '';
        $authorField = isset($row[10]) && \is_scalar($row[10]) ? \trim((string) $row[10]) : '';
        $coverUrl = isset($row[11]) && \is_scalar($row[11]) ? \trim((string) $row[11]) : '';
        $publisher = isset($row[12]) && \is_scalar($row[12]) ? \trim((string) $row[12]) : '';
        $categories = isset($row[13]) && \is_scalar($row[13]) ? \trim((string) $row[13]) : '';
        $description = isset($row[14]) && \is_scalar($row[14]) ? \trim((string) $row[14]) : '';

        if ('' === $isbnField && '' === $authorField && '' === $coverUrl && '' === $publisher && '' === $description) {
            return false;
        }

        if ('' !== $coverUrl && !\str_starts_with($coverUrl, 'file://') && null === $comic->getCoverUrl()) {
            $comic->setCoverUrl($coverUrl);
        }

        if ('' !== $description && null === $comic->getDescription()) {
            $comic->setDescription($description);
        }

        if ('' !== $publisher && null === $comic->getPublisher()) {
            $comic->setPublisher($publisher);
        }

        if ('' !== $authorField && $comic->getAuthors()->isEmpty()) {
            $authorNames = $this->collectAuthorNames($authorField);
            if ([] !== $authorNames) {
                $authors = $this->authorRepository->findOrCreateMultiple($authorNames);
                foreach ($authors as $author) {
                    $comic->addAuthor($author);
                }
            }
        }

        if ('' !== $isbnField) {
            $this->applyTomeIsbns($comic, $isbnField);
        }

        if ('' !== $categories && ComicType::LIVRE === $comic->getType()) {
            $comic->setType($this->determineType($categories));
        }

        return true;
    }

    /**
     * Synchronise les tomes pour une série (crée les manquants, met à jour les existants).
     *
     * @param list<int>|null $specificBoughtValues Tomes spécifiques achetés (format CSV)
     * @param list<int>|null $specificOnNasValues  Tomes spécifiques sur le NAS (format CSV)
     */
    private function syncTomes(
        ComicSeries $comic,
        ?int $currentIssueValue,
        bool $currentIssueComplete,
        ?int $lastBoughtValue,
        bool $lastBoughtComplete,
        ?int $lastOnNasValue,
        bool $lastOnNasComplete,
        bool $onNas,
        ?int $latestPublishedIssue,
        ?int $hsCount = null,
        ?array $specificBoughtValues = null,
        ?array $specificOnNasValues = null,
    ): int {
        $maxTomeNumber = $this->determineMaxTomeNumber(
            $currentIssueValue,
            $currentIssueComplete,
            $lastBoughtValue,
            $lastOnNasValue,
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
                $isOnNas = $lastOnNasComplete
                    || (null !== $specificOnNasValues && \in_array($number, $specificOnNasValues, true))
                    || (null === $specificOnNasValues && null !== $lastOnNasValue && $number <= $lastOnNasValue);

                if (isset($existingRegular[$number])) {
                    $existingRegular[$number]->setBought($isBought);
                    $existingRegular[$number]->setOnNas($onNas || $isOnNas);
                } else {
                    $tome = new Tome();
                    $tome->setBought($isBought);
                    $tome->setNumber($number);
                    $tome->setOnNas($onNas || $isOnNas);
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
        ?int $lastOnNasValue,
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
        if (null !== $lastOnNasValue) {
            $candidates[] = $lastOnNasValue;
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

    /**
     * Collecte les noms d'auteurs uniques depuis un champ texte.
     *
     * @return string[]
     */
    private function collectAuthorNames(string $authorField): array
    {
        $names = [];
        foreach (\explode(',', $authorField) as $name) {
            $name = \trim($name);
            if ('' !== $name && !\in_array(\mb_strtolower($name), \array_map(mb_strtolower(...), $names), true)) {
                $names[] = $name;
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

    /**
     * Applique les ISBN aux tomes correspondants.
     *
     * Format : "ISBN1:T1,ISBN2:T8,..." ou simple ISBN (appliqué au tome 1).
     */
    private function applyTomeIsbns(ComicSeries $comic, string $isbnField): void
    {
        $tomeIsbns = $this->parseTomeIsbns($isbnField);

        foreach ($comic->getTomes() as $tome) {
            if ($tome->isHorsSerie() || null !== $tome->getIsbn()) {
                continue;
            }

            $number = $tome->getNumber();
            if (isset($tomeIsbns[$number])) {
                $tome->setIsbn($tomeIsbns[$number]);
            }
        }
    }

    /**
     * Parse le champ ISBN multi-valeurs.
     *
     * @return array<int, string> numéro de tome → ISBN
     */
    private function parseTomeIsbns(string $isbnField): array
    {
        // Format "ISBN1:T1,ISBN2:T8,..."
        if (\str_contains($isbnField, ':T')) {
            $result = [];
            foreach (\explode(',', $isbnField) as $part) {
                $part = \trim($part);
                if (1 === \preg_match('/^(.+):T(\d+)$/', $part, $matches)) {
                    $isbn = $this->cleanIsbn($matches[1]);
                    if (null !== $isbn) {
                        $result[(int) $matches[2]] = $isbn;
                    }
                }
            }

            return $result;
        }

        // Simple ISBN → apply to tome 1
        $isbn = $this->cleanIsbn($isbnField);

        return null !== $isbn ? [1 => $isbn] : [];
    }
}
