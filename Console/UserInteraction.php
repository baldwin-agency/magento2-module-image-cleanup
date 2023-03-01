<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console;

use Baldwin\ImageCleanup\Logger\Logger;
use Baldwin\ImageCleanup\Stats\FileStatsCalculator;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UserInteraction
{
    public const CONSOLE_OPTION_TO_SKIP_GENERATING_STATS = 'no-stats';

    private $fileStatsCalculator;
    private $logger;

    public function __construct(
        FileStatsCalculator $fileStatsCalculator,
        Logger $logger
    ) {
        $this->fileStatsCalculator = $fileStatsCalculator;
        $this->logger = $logger;
    }

    /**
     * @param array<string> $paths
     */
    public function showPathsToDeleteAndAskForConfirmation(
        array $paths,
        InputInterface $input,
        OutputInterface $output
    ): bool {
        $skipCalculationOption = (bool) $input->getOption(self::CONSOLE_OPTION_TO_SKIP_GENERATING_STATS);
        $this->fileStatsCalculator->setSkipCalculation($skipCalculationOption);

        $output->writeln('');

        if ($paths === []) {
            $output->writeln('<info>No paths found to cleanup, all is good!</info>');
            $this->logger->logNoActionTaken();

            return false;
        }

        if ($input->isInteractive()) {
            $this->displayPaths($paths, $output);
            $this->calculateAndDisplayStats($paths, $output);
        }

        return $this->askForConfirmation($input, $output);
    }

    /**
     * @param array<string> $deletedPaths
     * @param array<string> $skippedPaths
     */
    public function showFinalInfo(
        array $deletedPaths,
        array $skippedPaths,
        int $numberOfFilesRemoved,
        int $bytesRemoved,
        OutputInterface $output
    ): void {
        if ($skippedPaths !== []) {
            $output->writeln(sprintf("<error>Skipped these paths:\n- %s</error>", implode("\n- ", $skippedPaths)));
            $output->writeln('');
        }

        if ($deletedPaths !== []) {
            $output->writeln(sprintf("<info>Deleted these paths:\n- %s</info>", implode("\n- ", $deletedPaths)));
            $output->writeln('');

            if ($this->fileStatsCalculator->canCalculate()) {
                $output->writeln(sprintf(
                    '<info>Cleaned up %d files, was able to cleanup %s!</info>',
                    $numberOfFilesRemoved,
                    $this->formatBytes($bytesRemoved)
                ));
                $this->logger->logFinalSummary($numberOfFilesRemoved, $this->formatBytes($bytesRemoved));
            }
        }
    }

    /**
     * @param array<string> $paths
     */
    private function displayPaths(array $paths, OutputInterface $output): void
    {
        // MAYBE TODO: if the amount of paths is bigger than some number (100?)
        // then try to group the output in something like: "/pub/media/catalog/product/xxx/ (xx items)""
        $output->writeln('<question>We found the following paths to delete:</question>');

        foreach ($paths as $path) {
            $output->writeln(sprintf('<question>- %s</question>', $path));
        }
        $output->writeln('');
    }

    /**
     * @param array<string> $paths
     */
    private function calculateAndDisplayStats(array $paths, OutputInterface $output): void
    {
        if ($this->fileStatsCalculator->canCalculate()) {
            $this->fileStatsCalculator->resetStats();
            $this->fileStatsCalculator->calculateStatsForPaths($paths);

            $numberOfFiles = $this->fileStatsCalculator->getNumberOfFiles();
            $totalSizeInBytes = $this->fileStatsCalculator->getTotalSizeInBytes();

            $output->writeln(sprintf('Total files: <question>%d</question>', $numberOfFiles));
            $output->writeln(sprintf('Filesize: <question>%s</question>', $this->formatBytes($totalSizeInBytes)));
            $output->writeln('');
        }
    }

    // from: https://stackoverflow.com/a/2510459
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function askForConfirmation(InputInterface $input, OutputInterface $output): bool
    {
        $result = false;

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion('Continue with the deletion of these paths [y/N]? ', false);
            $questionHelper = new QuestionHelper();

            if ((bool) $questionHelper->ask($input, $output, $question)) {
                $result = true;
                $output->writeln('');
            }
        } else {
            $result = true;
        }

        return $result;
    }
}
