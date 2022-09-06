<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Stats;

use Magento\Framework\Filesystem\DriverInterface as FilesystemDriverInterface;

class FileStatsCalculator
{
    private $filesystemDriver;

    /** @var bool */
    private $skipCalculation = false;
    /** @var int */
    private $numberOfFiles = 0;
    /** @var int */
    private $totalSizeInBytes = 0;

    public function __construct(
        FilesystemDriverInterface $filesystemDriver
    ) {
        $this->filesystemDriver = $filesystemDriver;
    }

    public function resetStats(): void
    {
        $this->numberOfFiles = 0;
        $this->totalSizeInBytes = 0;
    }

    public function calculateStatsForPath(string $path): void
    {
        if ($this->skipCalculation === true) {
            return;
        }

        if ($this->filesystemDriver->isDirectory($path)) {
            $fileIterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($fileIterator as $file) {
                if ($file->isFile()) {
                    ++$this->numberOfFiles;
                    $this->totalSizeInBytes += $file->getSize();
                }
            }
        } elseif ($this->filesystemDriver->isFile($path)) {
            $file = new \SplFileInfo($path);

            ++$this->numberOfFiles;
            $this->totalSizeInBytes += $file->getSize();
        }
    }

    /**
     * @param array<string> $paths
     */
    public function calculateStatsForPaths(array $paths): void
    {
        if ($this->skipCalculation === true) {
            return;
        }

        foreach ($paths as $path) {
            $this->calculateStatsForPath($path);
        }
    }

    public function setSkipCalculation(bool $skip): void
    {
        $this->skipCalculation = $skip;
    }

    public function canCalculate(): bool
    {
        return !$this->skipCalculation;
    }

    public function getNumberOfFiles(): int
    {
        return $this->numberOfFiles;
    }

    public function getTotalSizeInBytes(): int
    {
        return $this->totalSizeInBytes;
    }
}
