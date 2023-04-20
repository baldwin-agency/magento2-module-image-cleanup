<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console\Command;

use Baldwin\ImageCleanup\Console\UserInteraction;
use Baldwin\ImageCleanup\Deleter\DatabaseGalleryDeleter;
use Baldwin\ImageCleanup\Finder\ObsoleteDatabaseEntriesFinder;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as GalleryResourceModel;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveObsoleteDatabaseEntries extends ConsoleCommand
{
    private $userInteraction;
    private $dbGalleryDeleter;
    private $obsoleteDbEntriesFinder;

    public function __construct(
        UserInteraction $userInteraction,
        DatabaseGalleryDeleter $dbGalleryDeleter,
        ObsoleteDatabaseEntriesFinder $obsoleteDbEntriesFinder
    ) {
        $this->userInteraction = $userInteraction;
        $this->dbGalleryDeleter = $dbGalleryDeleter;
        $this->obsoleteDbEntriesFinder = $obsoleteDbEntriesFinder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('catalog:images:remove-obsolete-db-entries');
        $this->setDescription(
            sprintf(
                'Removes values from the %s db table which are no longer needed.',
                GalleryResourceModel::GALLERY_TABLE
            )
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entries = $this->obsoleteDbEntriesFinder->find();

        $accepted = $this->userInteraction->showDbValuesToDeleteAndAskForConfirmation(
            array_map('strval', $entries),
            GalleryResourceModel::GALLERY_TABLE,
            $input,
            $output
        );
        if ($accepted) {
            $this->dbGalleryDeleter->deleteGalleryValues($entries);
            $deletedValues = $this->dbGalleryDeleter->getDeletedValues();
            $numberOfValuesDeleted = $this->dbGalleryDeleter->getNumberOfValuesDeleted();

            $this->userInteraction->showFinalDbInfo(
                $deletedValues,
                $numberOfValuesDeleted,
                GalleryResourceModel::GALLERY_TABLE,
                $output
            );
        }

        return Cli::RETURN_SUCCESS;
    }
}
