<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\NasSeriesData;
use App\Service\Nas\NasDirectoryParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scanne le NAS via SSH et génère un fichier Excel compatible avec l'import.
 */
#[AsCommand(
    name: 'app:scan-nas',
    description: 'Scanne les fichiers du NAS et génère un fichier Excel d\'import',
)]
final class ScanNasCommand extends Command
{
    /**
     * Correspondance type → nom d'onglet Excel (doit correspondre à ImportExcelService::SHEET_TYPE_MAP).
     */
    private const array TYPE_SHEET_MAP = [
        'BD' => 'BD',
        'Comics' => 'Comics',
        'Livres' => 'Livre',
        'Mangas' => 'Mangas',
    ];

    /**
     * Sous-dossiers Comics à parcourir.
     */
    private const array COMICS_SUBDIRS = ['Autres', 'DC Comics', 'Marvel comics'];

    private const string DEFAULT_OUTPUT = 'var/nas-import.xlsx';

    private ?SSH2 $ssh = null;

    public function __construct(
        private readonly NasDirectoryParser $parser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Chemin du fichier Excel de sortie', self::DEFAULT_OUTPUT)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scan des fichiers du NAS');

        /** @var string $host */
        $host = $_ENV['NAS_HOST'] ?? '';
        /** @var string $port */
        $port = $_ENV['NAS_PORT'] ?? '22';
        /** @var string $username */
        $username = $_ENV['NAS_USERNAME'] ?? '';
        /** @var string $password */
        $password = $_ENV['NAS_PASSWORD'] ?? '';

        if ('' === $host || '' === $username || '' === $password) {
            $io->error('Variables d\'environnement NAS_HOST, NAS_USERNAME et NAS_PASSWORD requises dans .env.local');

            return Command::FAILURE;
        }

        $io->text(\sprintf('Connexion à %s:%s...', $host, $port));

        try {
            $this->ssh = new SSH2($host, (int) $port, 30);

            if (!$this->ssh->login($username, $password)) {
                $io->error('Échec de l\'authentification SSH');

                return Command::FAILURE;
            }
        } catch (\RuntimeException $e) {
            $io->error(\sprintf('Erreur de connexion SSH : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->text('Connecté.');

        /** @var string $outputPath */
        $outputPath = $input->getOption('output');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $totalSeries = 0;

        foreach (self::TYPE_SHEET_MAP as $nasDir => $sheetName) {
            $io->section("Scan : {$nasDir}");

            $allSeries = $this->scanType($nasDir, $io);

            if ([] === $allSeries) {
                $io->warning("Aucune série trouvée pour {$nasDir}");

                continue;
            }

            // Trier par titre
            \usort($allSeries, static fn (NasSeriesData $a, NasSeriesData $b) => \strcasecmp($a->title, $b->title));

            // Fusionner les séries en double (même série dans plusieurs dossiers)
            $allSeries = $this->parser->mergeDuplicateSeries($allSeries);

            $this->writeSheet($spreadsheet, $sheetName, $allSeries);

            $count = \count($allSeries);
            $totalSeries += $count;
            $io->success(\sprintf('%d séries trouvées pour %s', $count, $nasDir));
        }

        if (0 === $totalSeries) {
            $io->error('Aucune série trouvée sur le NAS');

            return Command::FAILURE;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $io->success(\sprintf('Fichier Excel généré : %s (%d séries au total)', $outputPath, $totalSeries));

        return Command::SUCCESS;
    }

    /**
     * Scanne un type (BD, Comics, Livres, Mangas) et retourne toutes les séries.
     *
     * @return list<NasSeriesData>
     */
    private function scanType(string $type, SymfonyStyle $io): array
    {
        $basePath = '/volume1/lecture';
        $inProgressPath = '/volume1/lecture en cours';
        $allSeries = [];

        if ('Comics' === $type) {
            foreach (self::COMICS_SUBDIRS as $subdir) {
                $path = "{$basePath}/Comics/{$subdir}";
                $listing = $this->sshLs($path);
                $filesByDir = $this->fetchFilesByDir($listing, $path);
                $allSeries = \array_merge($allSeries, $this->parser->parseUnreadSeries($listing, $filesByDir));
            }

            $lusPath = "{$basePath}/Comics/_lus";
            $lusListing = $this->sshLs($lusPath);
            $lusFiles = $this->fetchFilesByDir($lusListing, $lusPath);
            $allSeries = \array_merge($allSeries, $this->parser->parseReadSeries($lusListing, $lusFiles));

            $inProgressComicsPath = "{$inProgressPath}/Comics";
            $inProgressListing = $this->sshLs($inProgressComicsPath);
            $inProgressFiles = $this->fetchFilesByDir($inProgressListing, $inProgressComicsPath);
            $allSeries = \array_merge($allSeries, $this->parser->parseInProgressSeries($inProgressListing, $inProgressFiles));
        } elseif ('Livres' === $type) {
            $io->note('Livres : structure hétérogène, import manuel recommandé');

            return [];
        } else {
            $path = "{$basePath}/{$type}";
            $listing = $this->sshLs($path);
            $filesByDir = $this->fetchFilesByDir($listing, $path);
            $allSeries = \array_merge($allSeries, $this->parser->parseUnreadSeries($listing, $filesByDir));

            $lusPath = "{$path}/_lus";
            $lusListing = $this->sshLs($lusPath);
            $lusFiles = $this->fetchFilesByDir($lusListing, $lusPath);
            $allSeries = \array_merge($allSeries, $this->parser->parseReadSeries($lusListing, $lusFiles));

            $inProgressTypePath = "{$inProgressPath}/{$type}";
            $inProgressListing = $this->sshLs($inProgressTypePath);
            $inProgressFiles = $this->fetchFilesByDir($inProgressListing, $inProgressTypePath);
            $allSeries = \array_merge($allSeries, $this->parser->parseInProgressSeries($inProgressListing, $inProgressFiles));
        }

        return $allSeries;
    }

    /**
     * Récupère les fichiers de chaque sous-répertoire via SSH.
     *
     * @param list<string> $listing
     *
     * @return array<string, list<string>>
     */
    private function fetchFilesByDir(array $listing, string $basePath): array
    {
        $filesByDir = [];

        foreach ($listing as $entry) {
            if (\in_array($entry, ['@eaDir', '#recycle', '_lus'], true)) {
                continue;
            }

            $files = $this->sshLs("{$basePath}/{$entry}");

            if ([] !== $files) {
                $filesByDir[$entry] = $files;
            }
        }

        return $filesByDir;
    }

    /**
     * Liste un répertoire distant via SSH (connexion persistante phpseclib).
     *
     * @return list<string>
     */
    private function sshLs(string $remotePath): array
    {
        if (null === $this->ssh) {
            return [];
        }

        $command = \sprintf('ls %s 2>/dev/null', \escapeshellarg($remotePath));

        // phpseclib SSH2::exec() — exécution sécurisée via connexion persistante
        $result = $this->ssh->exec($command);

        if (!\is_string($result)) {
            return [];
        }

        $result = \trim($result);

        if ('' === $result) {
            return [];
        }

        return \explode("\n", $result);
    }

    /**
     * Écrit les données d'un type dans un onglet Excel.
     *
     * @param list<NasSeriesData> $seriesList
     */
    private function writeSheet(Spreadsheet $spreadsheet, string $sheetName, array $seriesList): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);

        $headers = ['Titre', 'Statut', 'Dernier acheté', 'Lu jusqu\'à', 'Nombre publié', 'Dernier téléchargé', 'Sur NAS', 'Parution terminée'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($seriesList as $series) {
            $sheet->setCellValue([1, $row], $series->title);

            if ($series->readComplete) {
                $sheet->setCellValue([4, $row], 'fini');
            } elseif (null !== $series->readUpTo) {
                $sheet->setCellValue([4, $row], $series->readUpTo);
            }

            if ($series->isComplete && null !== $series->lastDownloaded) {
                $sheet->setCellValue([5, $row], $series->lastDownloaded);
            }

            if (null !== $series->lastDownloaded) {
                $sheet->setCellValue([6, $row], $series->lastDownloaded);
            }

            $sheet->setCellValue([7, $row], 'oui');

            if ($series->isComplete) {
                $sheet->setCellValue([8, $row], 'oui');
            }

            ++$row;
        }
    }
}
