<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Logger;

use Psr\Log\LoggerInterface;

class Logger
{
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function logPathRemoval(string $path): void
    {
        $this->logger->info(sprintf('Removed path "%s"', $path));
    }

    public function logDbRowRemoval(string $rowRepresentation, string $dbTable): void
    {
        $this->logger->info(sprintf('Removed db value "%s" from table %s', $rowRepresentation, $dbTable));
    }

    public function logNoActionTaken(): void
    {
        $this->logger->info('Nothing found to cleanup, all is good!');
    }

    public function logFinalSummary(int $numberOfFilesRemoved, string $formattedBytesRemoved): void
    {
        $this->logger->info(sprintf(
            '-- Summary: removed %d files in total which cleared up %s of diskspace',
            $numberOfFilesRemoved,
            $formattedBytesRemoved
        ));
    }

    public function logFinalDbSummary(int $numberOfDbRowsRemoved, string $dbTable): void
    {
        $this->logger->info(sprintf(
            '-- Summary: removed %d rows in the %s database table',
            $numberOfDbRowsRemoved,
            $dbTable
        ));
    }
}
