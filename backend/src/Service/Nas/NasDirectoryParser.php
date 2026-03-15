<?php

declare(strict_types=1);

namespace App\Service\Nas;

use App\DTO\NasSeriesData;

/**
 * Parse les listings de répertoires du NAS pour extraire les séries et tomes.
 */
final class NasDirectoryParser
{
    private const array IGNORED_ENTRIES = ['@eaDir', '#recycle', '_lus'];

    /**
     * Seuil de distance de Levenshtein (ratio max par rapport à la longueur).
     * 0.2 = 20% de différence tolérée.
     */
    private const float FUZZY_THRESHOLD = 0.2;

    /**
     * Articles à supprimer lors de la normalisation.
     */
    /**
     * Articles à supprimer lors de la normalisation.
     */
    private const array ARTICLES = ['de', 'des', 'du', 'l', 'la', 'le', 'les', 'the'];

    /**
     * Extensions de fichiers BD/manga/livre.
     */
    private const string COMIC_EXTENSIONS_PATTERN = '/\.(?:cbr|cbz|pdf|zip|rar|epub)$/i';

    /**
     * Extrait le numéro de tome d'un nom de fichier/dossier.
     *
     * Formats supportés :
     * - "Série 01 - Titre.cbr"
     * - "Série - T01 - Titre.cbr"
     * - "bd_fr_serie_t10_titre.cbr"
     * - "BDFR - SERIE - 01 - Titre.cbz"
     * - "04 - Vilyana.pdf"
     * - "Nausicaa 01 (Source)"
     * - "Série (T01-06)" → retourne 6 (max de la plage)
     * - "Série - Tome 1 à 4" → retourne 4
     */
    public function extractTomeNumber(string $filename): ?int
    {
        // Ignorer les one-shots
        if (1 === \preg_match('/one[- ]?shot/i', $filename)) {
            return null;
        }

        // Format plage "Tome X à Y" ou "(TX-Y)"
        if (1 === \preg_match('/(?:Tome\s+\d+\s*à\s*|T\d+-\s*)(\d+)/i', $filename, $matches)) {
            return (int) $matches[1];
        }

        // Format "tNN_" (underscored, comme bd_fr_serie_t10_titre)
        if (1 === \preg_match('/[_.]t(\d+)[_.]/', $filename, $matches)) {
            return (int) $matches[1];
        }

        // Format "- TNN -" ou "- NN -" ou " NN " avec séparateurs
        if (1 === \preg_match('/(?:^|\s|-)(?:T)?0*(\d+)(?:\s|[-.]|$)/i', $filename, $matches)) {
            $number = (int) $matches[1];

            // Éviter les faux positifs (années, etc.)
            if ($number >= 0 && $number < 1000) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Parse le nom d'un répertoire de série pour en extraire le titre et le statut complet.
     *
     * @return array{title: string, isComplete: bool}
     */
    public function parseSeriesDirectory(string $dirName): array
    {
        $isComplete = false;

        // Détecter "(complet)" en fin de chaîne (insensible à la casse)
        if (1 === \preg_match('/\s*\(complet\)\s*$/i', $dirName)) {
            $isComplete = true;
            $dirName = (string) \preg_replace('/\s*\(complet\)\s*$/i', '', $dirName);
        }

        // Retirer "(incomplet)" en fin de chaîne
        $dirName = (string) \preg_replace('/\s*\(incomplet\)\s*$/i', '', $dirName);

        // Retirer les plages de tomes : "(T01-06)", "- Tome 1 à 4"
        $dirName = (string) \preg_replace('/\s*\(T\d+-\d+\)\s*$/i', '', $dirName);
        $dirName = (string) \preg_replace('/\s*-\s*Tome\s+\d+\s*à\s*\d+\s*$/i', '', $dirName);

        return [
            'isComplete' => $isComplete,
            'title' => \trim($dirName),
        ];
    }

    /**
     * Parse les séries non lues (/volume1/lecture/{type}/).
     *
     * @param list<string>                $listing    Contenu du répertoire
     * @param array<string, list<string>> $filesByDir Fichiers par sous-répertoire
     *
     * @return list<NasSeriesData>
     */
    public function parseUnreadSeries(array $listing, array $filesByDir, ?string $publisher = null): array
    {
        $series = [];

        foreach ($listing as $entry) {
            if ($this->isIgnoredEntry($entry)) {
                continue;
            }

            $parsed = $this->parseSeriesDirectory($entry);
            $lastDownloaded = $this->getMaxTomeFromFiles($filesByDir[$entry] ?? []);

            // Si pas de fichiers, essayer d'extraire depuis le nom du dossier lui-même
            if (null === $lastDownloaded) {
                $lastDownloaded = $this->extractTomeNumber($entry);
            }

            $series[] = new NasSeriesData(
                isComplete: $parsed['isComplete'],
                lastDownloaded: $lastDownloaded,
                readComplete: false,
                readUpTo: null,
                title: $parsed['title'],
                publisher: $publisher,
            );
        }

        return $series;
    }

    /**
     * Parse les séries lues (_lus/).
     *
     * @param list<string>                $listing    Contenu du répertoire _lus/
     * @param array<string, list<string>> $filesByDir Fichiers par sous-répertoire
     *
     * @return list<NasSeriesData>
     */
    public function parseReadSeries(array $listing, array $filesByDir, ?string $publisher = null): array
    {
        $series = [];

        foreach ($listing as $entry) {
            if ($this->isIgnoredEntry($entry)) {
                continue;
            }

            $parsed = $this->parseSeriesDirectory($entry);
            $lastDownloaded = $this->getMaxTomeFromFiles($filesByDir[$entry] ?? []);

            // _lus = tomes lus, pas nécessairement série terminée
            // readComplete seulement si marqué (complet)
            $series[] = new NasSeriesData(
                isComplete: $parsed['isComplete'],
                lastDownloaded: $lastDownloaded,
                readComplete: $parsed['isComplete'],
                readUpTo: $parsed['isComplete'] ? null : $lastDownloaded,
                title: $parsed['title'],
                publisher: $publisher,
            );
        }

        return $series;
    }

    /**
     * Parse les séries en cours de lecture (/volume1/lecture en cours/{type}/).
     * Si la liste commence au T10, les T1-T9 sont considérés lus.
     *
     * @param list<string>                $listing    Contenu du répertoire
     * @param array<string, list<string>> $filesByDir Fichiers par sous-répertoire
     *
     * @return list<NasSeriesData>
     */
    public function parseInProgressSeries(array $listing, array $filesByDir, ?string $publisher = null): array
    {
        $series = [];

        foreach ($listing as $entry) {
            if ($this->isIgnoredEntry($entry)) {
                continue;
            }

            $parsed = $this->parseSeriesDirectory($entry);
            $files = $filesByDir[$entry] ?? [];
            $tomeNumbers = $this->extractAllTomeNumbers($files);

            $lastDownloaded = [] !== $tomeNumbers ? \max($tomeNumbers) : null;
            $minTome = [] !== $tomeNumbers ? \min($tomeNumbers) : null;

            // Si le premier tome n'est pas le T1, les précédents ont été lus
            $readUpTo = (null !== $minTome && $minTome > 1) ? $minTome - 1 : null;

            $series[] = new NasSeriesData(
                isComplete: $parsed['isComplete'],
                lastDownloaded: $lastDownloaded,
                readComplete: false,
                readUpTo: $readUpTo,
                title: $parsed['title'],
                publisher: $publisher,
            );
        }

        return $series;
    }

    /**
     * Sépare les fichiers isolés des répertoires dans un listing.
     *
     * Les fichiers avec extension BD (.cbr, .cbz, .pdf…) sont rattachés
     * au dossier correspondant (par préfixe normalisé). S'il n'existe pas,
     * un dossier synthétique est créé à partir du nom de série extrait.
     *
     * @param list<string> $listing
     *
     * @return array{directories: list<string>, looseFiles: array<string, list<string>>}
     */
    public function groupLooseFiles(array $listing): array
    {
        $directories = [];
        $files = [];

        foreach ($listing as $entry) {
            if ($this->isIgnoredEntry($entry) || $this->isInfoFile($entry)) {
                continue;
            }

            if ($this->isComicFile($entry)) {
                $files[] = $entry;
            } else {
                $directories[] = $entry;
            }
        }

        /** @var array<string, list<string>> $looseFiles */
        $looseFiles = [];

        // Index normalisé des dossiers existants
        /** @var array<string, string> $normalizedDirMap clé normalisée → nom original */
        $normalizedDirMap = [];
        foreach ($directories as $dir) {
            $normalizedDirMap[$this->normalizeTitle($dir)] = $dir;
        }

        foreach ($files as $file) {
            $seriesName = $this->extractSeriesNameFromFile($file);
            $normalizedSeries = $this->normalizeTitle($seriesName);

            // Chercher un dossier existant dont le titre normalisé correspond
            $matchedDir = $normalizedDirMap[$normalizedSeries] ?? null;

            if (null === $matchedDir) {
                // Chercher par préfixe ou fuzzy
                foreach ($normalizedDirMap as $normalizedDir => $dirName) {
                    if (\str_starts_with($normalizedSeries, $normalizedDir) || $this->isFuzzyMatch($normalizedSeries, $normalizedDir)) {
                        $matchedDir = $dirName;

                        break;
                    }
                }
            }

            if (null === $matchedDir) {
                // Créer un dossier synthétique
                $matchedDir = $seriesName;
                $directories[] = $matchedDir;
                $normalizedDirMap[$normalizedSeries] = $matchedDir;
            }

            $looseFiles[$matchedDir][] = $file;
        }

        return [
            'directories' => $directories,
            'looseFiles' => $looseFiles,
        ];
    }

    /**
     * Normalise un titre pour la comparaison fuzzy.
     *
     * Lowercase, translitération accents, & → et, suppression articles et ponctuation.
     */
    public function normalizeTitle(string $title): string
    {
        // Lowercase
        $normalized = \mb_strtolower($title);

        // Translitération accents (é → e, etc.)
        $normalized = \transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $normalized) ?: $normalized;

        // & → et
        $normalized = \str_replace('&', 'et', $normalized);

        // Supprimer la ponctuation (garder lettres, chiffres, espaces)
        $normalized = (string) \preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        // Supprimer les articles (mots entiers)
        $articlesPattern = '/\b('.\implode('|', self::ARTICLES).')\b/u';
        $normalized = (string) \preg_replace($articlesPattern, '', $normalized);

        // Collapse espaces multiples
        $normalized = (string) \preg_replace('/\s+/', ' ', $normalized);

        return \trim($normalized);
    }

    /**
     * Fusionne les séries en double (même titre ou titre similaire).
     *
     * Passe 1 : regroupement par titre normalisé exact.
     * Passe 2 : Levenshtein sur les titres normalisés (seuil 20%).
     *
     * @param list<NasSeriesData> $series
     *
     * @return list<NasSeriesData>
     */
    public function mergeDuplicateSeries(array $series): array
    {
        /** @var array<string, NasSeriesData> $byNormalized */
        $byNormalized = [];

        // Passe 1 : regroupement exact par titre normalisé
        foreach ($series as $s) {
            $key = $this->normalizeTitle($s->title);

            if (!isset($byNormalized[$key])) {
                $byNormalized[$key] = $s;

                continue;
            }

            $byNormalized[$key] = $this->mergeTwo($byNormalized[$key], $s);
        }

        // Passe 2 : Levenshtein pour les titres proches
        $keys = \array_keys($byNormalized);
        $merged = [];

        foreach ($keys as $key) {
            if (!isset($byNormalized[$key])) {
                continue;
            }

            $current = $byNormalized[$key];

            foreach ($keys as $otherKey) {
                if ($key === $otherKey || !isset($byNormalized[$otherKey])) {
                    continue;
                }

                if ($this->isFuzzyMatch($key, $otherKey)) {
                    $current = $this->mergeTwo($current, $byNormalized[$otherKey]);
                    unset($byNormalized[$otherKey]);
                }
            }

            $merged[] = $current;
            unset($byNormalized[$key]);
        }

        return $merged;
    }

    /**
     * Nettoie un nom de fichier : supprime "GetComics.INFO" et les extensions.
     */
    private function cleanFilename(string $filename): string
    {
        // Supprimer "GetComics.INFO" (et variantes de casse)
        $filename = (string) \preg_replace('/\(?GetComics\.INFO\)?/i', '', $filename);

        // Supprimer l'extension
        $filename = (string) \preg_replace('/\.\w{2,4}$/', '', $filename);

        return \trim($filename);
    }

    /**
     * Extrait tous les numéros de tomes des fichiers (après nettoyage).
     *
     * @param list<string> $files
     *
     * @return list<int>
     */
    private function extractAllTomeNumbers(array $files): array
    {
        $numbers = [];

        foreach ($files as $file) {
            if ($this->isInfoFile($file)) {
                continue;
            }

            $cleaned = $this->cleanFilename($file);
            $number = $this->extractTomeNumber($cleaned);

            if (null !== $number) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * Retourne le numéro de tome max trouvé parmi les fichiers.
     *
     * @param list<string> $files
     */
    private function getMaxTomeFromFiles(array $files): ?int
    {
        $numbers = $this->extractAllTomeNumbers($files);

        return [] !== $numbers ? \max($numbers) : null;
    }

    /**
     * Extrait le nom de série depuis un nom de fichier.
     *
     * "Aquablue - T12 retour aux sources.cbr" → "Aquablue"
     * "Chaos team T00 - La vengeance du Beret Vert.cbr" → "Chaos team"
     * "Chaos team 1.2.cbr" → "Chaos team"
     * "Batman 01 - Year One.cbz" → "Batman"
     */
    private function extractSeriesNameFromFile(string $filename): string
    {
        $cleaned = $this->cleanFilename($filename);

        // " - T12 reste" ou " - 01 reste" (tiret + T/numéro)
        $name = (string) \preg_replace('/\s*[-–]\s*(?:T\d|\d).*$/i', '', $cleaned);

        // " T00 reste" (espace + T + chiffres, sans tiret)
        $name = (string) \preg_replace('/\s+T\d+\b.*$/i', '', $name);

        // " 01 - reste" (espace + chiffres + tiret + reste)
        $name = (string) \preg_replace('/\s+\d+\s*[-–].*$/', '', $name);

        // Numéro final : " 01" ou " 1.2"
        $name = (string) \preg_replace('/\s+\d+(?:\.\d+)?\s*$/', '', $name);

        return \trim($name);
    }

    /**
     * Vérifie si une entrée est un fichier BD (par extension).
     */
    private function isComicFile(string $entry): bool
    {
        return 1 === \preg_match(self::COMIC_EXTENSIONS_PATTERN, $entry);
    }

    private function isIgnoredEntry(string $entry): bool
    {
        return \in_array($entry, self::IGNORED_ENTRIES, true);
    }

    /**
     * Vérifie si un fichier est un .info à ignorer.
     */
    private function isInfoFile(string $filename): bool
    {
        return 1 === \preg_match('/\.info$/i', $filename);
    }

    /**
     * Vérifie si deux titres normalisés sont suffisamment proches (Levenshtein).
     */
    private function isFuzzyMatch(string $normalizedA, string $normalizedB): bool
    {
        $maxLen = \max(\strlen($normalizedA), \strlen($normalizedB));

        if (0 === $maxLen) {
            return true;
        }

        $distance = \levenshtein($normalizedA, $normalizedB);

        return $distance / $maxLen <= self::FUZZY_THRESHOLD;
    }

    /**
     * Fusionne deux NasSeriesData en une seule.
     */
    /**
     * Retourne le max de deux valeurs nullable (null si les deux sont null).
     */
    private function maxNullable(?int $a, ?int $b): ?int
    {
        if (null === $a) {
            return $b;
        }

        if (null === $b) {
            return $a;
        }

        return \max($a, $b);
    }

    private function mergeTwo(NasSeriesData $a, NasSeriesData $b): NasSeriesData
    {
        return new NasSeriesData(
            isComplete: $a->isComplete || $b->isComplete,
            lastDownloaded: $this->maxNullable($a->lastDownloaded, $b->lastDownloaded),
            readComplete: $a->readComplete || $b->readComplete,
            readUpTo: $this->maxNullable($a->readUpTo, $b->readUpTo),
            title: $a->title,
            publisher: $a->publisher ?? $b->publisher,
        );
    }
}
