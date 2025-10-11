<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console\Command;

use Baldwin\ImageCleanup\Console\UserInteraction;
use Baldwin\ImageCleanup\Deleter\MediaDeleter;
use Baldwin\ImageCleanup\Finder\UnusedCacheHashDirectoriesFinder;
use Baldwin\ImageCleanup\Service\HyvaThemeFallbackStoreThemeResolver;
use Magento\Framework\App\Area as AppArea;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveUnusedCacheHashDirectories extends ConsoleCommand
{
    private $appState;
    private $userInteraction;
    private $mediaDeleter;
    private $unusedCacheHashDirFinder;
    private $hyvaThemeFallbackStoreThemeResolver;

    public function __construct(
        AppState $appState,
        UserInteraction $userInteraction,
        MediaDeleter $mediaDeleter,
        UnusedCacheHashDirectoriesFinder $unusedCacheHashDirFinder,
        HyvaThemeFallbackStoreThemeResolver $hyvaThemeFallbackStoreThemeResolver
    ) {
        $this->appState = $appState;
        $this->userInteraction = $userInteraction;
        $this->mediaDeleter = $mediaDeleter;
        $this->unusedCacheHashDirFinder = $unusedCacheHashDirFinder;
        $this->hyvaThemeFallbackStoreThemeResolver = $hyvaThemeFallbackStoreThemeResolver;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('catalog:images:remove-unused-hash-directories');
        $this->setDescription(
            'Remove unused resized hash directories (like: pub/media/catalog/product/cache/xxyyzz). '
            . 'These directories can be a leftover from older Magento versions, or from image definitions that got '
            . 'removed from the etc/view.xml file of a custom theme for example.'
        );
        $this->addOption(
            UserInteraction::CONSOLE_OPTION_TO_SKIP_GENERATING_STATS,
            null,
            InputOption::VALUE_NONE,
            'Skip calculating and outputting stats (filesizes, number of files, ...), '
            . 'this can speed up the command in case it runs slowly.'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // needed to avoid 'Area code is not set'
        // mimicking same area as core magento (global) from the catalog:images:resize command
        $this->appState->setAreaCode(AppArea::AREA_GLOBAL);

        $this->hyvaThemeFallbackStoreThemeResolver->setIsActive(true);

        $directories = $this->unusedCacheHashDirFinder->find();

        $this->hyvaThemeFallbackStoreThemeResolver->setIsActive(false);

        $accepted = $this->userInteraction->showPathsToDeleteAndAskForConfirmation($directories, $input, $output);
        if ($accepted) {
            $this->mediaDeleter->deletePaths($directories);
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
