<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console\Command;

use Baldwin\ImageCleanup\Console\ProgressIndicator;
use Baldwin\ImageCleanup\Console\UserInteraction;
use Baldwin\ImageCleanup\Deleter\MediaDeleter;
use Baldwin\ImageCleanup\Finder\UnusedFilesFinder;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveUnusedImageFiles extends ConsoleCommand
{
    private $userInteraction;
    private $progressIndicator;
    private $mediaDeleter;
    private $unusedFilesFinder;

    public function __construct(
        UserInteraction $userInteraction,
        ProgressIndicator $progressIndicator,
        MediaDeleter $mediaDeleter,
        UnusedFilesFinder $unusedFilesFinder
    ) {
        $this->userInteraction = $userInteraction;
        $this->progressIndicator = $progressIndicator;
        $this->mediaDeleter = $mediaDeleter;
        $this->unusedFilesFinder = $unusedFilesFinder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('catalog:images:remove-unused-files');
        $this->setDescription(
            'Remove unused product image files from the filesystem. ' .
            'We compare the data that\'s in the database with the files on disk and remove the ones that don\'t match'
        );
        $this->addOption(
            UserInteraction::CONSOLE_OPTION_TO_SKIP_GENERATING_STATS,
            null,
            InputOption::VALUE_NONE,
            'Skip calculating and outputting stats (filesizes, number of files, ...), ' .
            'this can speed up the command in case it runs slowly.'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressIndicator->init($output);

        $files = $this->unusedFilesFinder->find();

        $accepted = $this->userInteraction->showPathsToDeleteAndAskForConfirmation($files, $input, $output);
        if ($accepted) {
            $this->mediaDeleter->deletePaths($files);
            $deletedPaths = $this->mediaDeleter->getDeletedPaths();
            $skippedPaths = $this->mediaDeleter->getSkippedPaths();
            $numberOfFilesRemoved = $this->mediaDeleter->getNumberOfFilesDeleted();
            $bytesRemoved = $this->mediaDeleter->getBytesDeleted();

            $this->userInteraction->showFinalInfo(
                $deletedPaths,
                $skippedPaths,
                $numberOfFilesRemoved,
                $bytesRemoved,
                $output
            );
        }

        return Cli::RETURN_SUCCESS;
    }
}
