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
            if ($number > 0 && $number < 1000) {
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
    public function parseUnreadSeries(array $listing, array $filesByDir): array
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
            );
        }

        return $series;
    }

    /**
     * Parse les séries entièrement lues (_lus/).
     *
     * @param list<string>                $listing    Contenu du répertoire _lus/
     * @param array<string, list<string>> $filesByDir Fichiers par sous-répertoire
     *
     * @return list<NasSeriesData>
     */
    public function parseReadSeries(array $listing, array $filesByDir): array
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
    public function parseInProgressSeries(array $listing, array $filesByDir): array
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
            );
        }

        return $series;
    }

    /**
     * Fusionne les séries en double (même titre dans plusieurs dossiers).
     *
     * Combine les informations : lastDownloaded = max, readUpTo = max,
     * isComplete/readComplete = OR logique.
     *
     * @param list<NasSeriesData> $series
     *
     * @return list<NasSeriesData>
     */
    public function mergeDuplicateSeries(array $series): array
    {
        /** @var array<string, NasSeriesData> $byTitle */
        $byTitle = [];

        foreach ($series as $s) {
            $key = \mb_strtolower($s->title);

            if (!isset($byTitle[$key])) {
                $byTitle[$key] = $s;

                continue;
            }

            $existing = $byTitle[$key];

            $byTitle[$key] = new NasSeriesData(
                isComplete: $existing->isComplete || $s->isComplete,
                lastDownloaded: \max($existing->lastDownloaded ?? 0, $s->lastDownloaded ?? 0) ?: null,
                readComplete: $existing->readComplete || $s->readComplete,
                readUpTo: \max($existing->readUpTo ?? 0, $s->readUpTo ?? 0) ?: null,
                title: $existing->title,
            );
        }

        return \array_values($byTitle);
    }

    private function isIgnoredEntry(string $entry): bool
    {
        return \in_array($entry, self::IGNORED_ENTRIES, true);
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
     * Extrait tous les numéros de tomes des fichiers.
     *
     * @param list<string> $files
     *
     * @return list<int>
     */
    private function extractAllTomeNumbers(array $files): array
    {
        $numbers = [];

        foreach ($files as $file) {
            $number = $this->extractTomeNumber($file);
            if (null !== $number) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }
}
