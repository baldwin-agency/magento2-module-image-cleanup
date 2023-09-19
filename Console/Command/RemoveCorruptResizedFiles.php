<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console\Command;

use Baldwin\ImageCleanup\Console\UserInteraction;
use Baldwin\ImageCleanup\Deleter\MediaDeleter;
use Baldwin\ImageCleanup\Finder\CorruptResizedFilesFinder;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCorruptResizedFiles extends ConsoleCommand
{
    private $userInteraction;
    private $mediaDeleter;
    private $corruptResizedFilesFinder;

    public function __construct(
        UserInteraction $userInteraction,
        MediaDeleter $mediaDeleter,
        CorruptResizedFilesFinder $corruptResizedFilesFinder
    ) {
        $this->userInteraction = $userInteraction;
        $this->mediaDeleter = $mediaDeleter;
        $this->corruptResizedFilesFinder = $corruptResizedFilesFinder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('catalog:images:remove-corrupt-resized-files');
        $this->setDescription(
            'Remove corrupt resized image files from the filesystem. ' .
            'Magento will re-generate them again afterwards'
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
        $files = $this->corruptResizedFilesFinder->find();

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
