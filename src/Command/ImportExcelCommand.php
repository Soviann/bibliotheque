<?php

namespace App\Command;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!\file_exists($filePath)) {
            $io->error(\sprintf('Le fichier "%s" n\'existe pas.', $filePath));

            return Command::FAILURE;
        }

        $io->title('Import des données depuis Excel');

        $spreadsheet = IOFactory::load($filePath);
        $totalImported = 0;

        foreach (self::SHEET_TYPE_MAP as $sheetName => $comicType) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                $io->warning(\sprintf('Onglet "%s" non trouvé, ignoré.', $sheetName));
                continue;
            }

            $io->section(\sprintf('Import de l\'onglet "%s"', $sheetName));

            $data = $sheet->toArray();
            $imported = 0;

            // Ignorer la ligne d'en-tête
            for ($i = 1; $i < \count($data); $i++) {
                $row = $data[$i];
                $title = \trim((string) ($row[0] ?? ''));

                if ($title === '') {
                    continue;
                }

                $comic = new ComicSeries();
                $comic->setTitle($title);
                $comic->setType($comicType);
                $comic->setStatus($this->determineStatus($row[1] ?? null));

                [$lastBoughtValue, $lastBoughtComplete] = $this->parseIntegerValue($row[2] ?? null);
                $comic->setLastBought($lastBoughtValue);
                $comic->setLastBoughtComplete($lastBoughtComplete);

                [$currentIssueValue, $currentIssueComplete] = $this->parseIntegerValue($row[3] ?? null);
                $comic->setCurrentIssue($currentIssueValue);
                $comic->setCurrentIssueComplete($currentIssueComplete);

                [$publishedCountValue, $publishedCountComplete] = $this->parseIntegerValue($row[4] ?? null);
                $comic->setPublishedCount($publishedCountValue);
                $comic->setPublishedCountComplete($publishedCountComplete);

                [$lastDownloadedValue, $lastDownloadedComplete] = $this->parseIntegerValue($row[5] ?? null);
                $comic->setLastDownloaded($lastDownloadedValue);
                $comic->setLastDownloadedComplete($lastDownloadedComplete);

                $comic->setOnNas($this->determineOnNas($row[6] ?? null));
                $comic->setIsWishlist(false);

                $this->entityManager->persist($comic);
                $imported++;
            }

            $this->entityManager->flush();
            $io->success(\sprintf('%d entrées importées depuis "%s".', $imported, $sheetName));
            $totalImported += $imported;
        }

        $io->success(\sprintf('Import terminé. Total : %d entrées.', $totalImported));

        return Command::SUCCESS;
    }

    private function determineStatus(?string $value): ComicStatus
    {
        if ($value === null) {
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

    private function determineOnNas(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = \mb_strtolower(\trim($value));

        return $value !== '' && $value !== 'non';
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function parseIntegerValue(mixed $value): array
    {
        if ($value === null) {
            return [null, false];
        }

        $value = \trim((string) $value);

        if ($value === '') {
            return [null, false];
        }

        if (\mb_strtolower($value) === 'fini') {
            return [null, true];
        }

        $intValue = (int) $value;

        return $intValue > 0 ? [$intValue, false] : [null, false];
    }
}
