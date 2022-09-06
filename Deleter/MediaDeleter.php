<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Deleter;

use Baldwin\ImageCleanup\Logger\Logger;
use Baldwin\ImageCleanup\Stats\FileStatsCalculator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DriverInterface as FilesystemDriverInterface;

class MediaDeleter
{
    private $fileStatsCalculator;
    private $filesystem;
    private $filesystemDriver;
    private $logger;

    /** @var array<string> */
    private $deletedPaths = [];
    /** @var array<string> */
    private $skippedPaths = [];

    public function __construct(
        FileStatsCalculator $fileStatsCalculator,
        Filesystem $filesystem,
        FilesystemDriverInterface $filesystemDriver,
        Logger $logger
    ) {
        $this->fileStatsCalculator = $fileStatsCalculator;
        $this->filesystem = $filesystem;
        $this->filesystemDriver = $filesystemDriver;
        $this->logger = $logger;
    }

    /**
     * @param array<string> $paths
     */
    public function deletePaths(array $paths): void
    {
        $this->resetState();

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDirectoryPath = $this->filesystemDriver->getRealPath($mediaDirectory->getAbsolutePath());
        if (!is_string($mediaDirectoryPath)) {
            throw new FileSystemException(
                __('Can\'t find media directory path: "%1"', $mediaDirectory->getAbsolutePath())
            );
        }

        foreach ($paths as $path) {
            $regex = '#^' . preg_quote($mediaDirectoryPath) . '#';
            if (preg_match($regex, $path) !== 1) {
                $this->skippedPaths[] = $path;
            } else {
                $relativePath = preg_replace($regex, '', $path);

                $this->fileStatsCalculator->calculateStatsForPath($path);
                $this->deletedPaths[] = $path;
                $mediaDirectory->delete($relativePath);

                $this->logger->logPathRemoval($path);
            }
        }
    }

    private function resetState(): void
    {
        $this->skippedPaths = [];
        $this->deletedPaths = [];

        $this->fileStatsCalculator->resetStats();
    }

    /**
     * @return array<string>
     */
    public function getDeletedPaths(): array
    {
        return $this->deletedPaths;
    }

    /**
     * @return array<string>
     */
    public function getSkippedPaths(): array
    {
        return $this->skippedPaths;
    }

    public function getNumberOfFilesDeleted(): int
    {
        return $this->fileStatsCalculator->getNumberOfFiles();
    }

    public function getBytesDeleted(): int
    {
        return $this->fileStatsCalculator->getTotalSizeInBytes();
    }
}
