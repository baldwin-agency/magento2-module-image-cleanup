<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Finder;

use Baldwin\ImageCleanup\Console\ProgressIndicator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;

class CorruptResizedFilesFinder
{
    private $progressIndicator;
    private $filesystem;

    public function __construct(
        ProgressIndicator $progressIndicator,
        Filesystem $filesystem
    ) {
        $this->progressIndicator = $progressIndicator;
        $this->filesystem = $filesystem;
    }

    /**
     * @return array<string>
     */
    public function find(): array
    {
        $this->progressIndicator->start('Searching for corrupt files');

        $corruptFiles = $this->getCorruptFilesOnDisk();

        $this->progressIndicator->stop();

        return $corruptFiles;
    }

    /**
     * @return array<string>
     */
    private function getCorruptFilesOnDisk(): array
    {
        $corruptFiles = [];

        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $resizedImageDirectories = $this->getResizedImageDirectories($mediaDirectory);
        $this->progressIndicator->setMax(count($resizedImageDirectories));

        foreach ($resizedImageDirectories as $resizedImageDir) {
            $fileIterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $mediaDirectory->getAbsolutePath($resizedImageDir),
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            /** @var \SplFileInfo $file */
            foreach ($fileIterator as $file) {
                if ($file->isFile() && $this->isCorruptFile($file)) {
                    $corruptFiles[] = $file->getRealPath();
                }
            }

            $this->progressIndicator->advance();
        }

        return $corruptFiles;
    }

    /**
     * @return array<string>
     */
    private function getResizedImageDirectories(ReadInterface $mediaDirectory): array
    {
        // find all cached hash subdirectories,
        // so the ones starting with a single letter in their directory name
        $resizedImageDirectories = [];

        /** @var array<string> */
        $hashDirectories = $mediaDirectory->read('catalog/product/cache');
        foreach ($hashDirectories as $hashDir) {
            if ($mediaDirectory->isDirectory($hashDir)) {
                $resizedImageDirectories[] = $mediaDirectory->read($hashDir);
            }
        }
        $resizedImageDirectories = array_merge([], ...$resizedImageDirectories);

        // only allow directories that have one character as lowest path
        $resizedImageDirectories = array_filter(
            $resizedImageDirectories,
            function (string $path) use ($mediaDirectory) {
                return $mediaDirectory->isDirectory($path) && preg_match('#/.{1}$#', $path) === 1;
            }
        );

        return $resizedImageDirectories;
    }

    private function isCorruptFile(\SplFileInfo $file): bool
    {
        $size = $file->getSize();

        // no idea if a false value means corrupt, but let's choose not to for now
        if ($size === false) {
            return false;
        }

        // check if filesize is 0, if 0, file is corrupt
        if ($size === 0) {
            return true;
        }

        // if imagesize can't be determined, we can assume the image is corrupt
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.DiscouragedWithAlternative
        $imageSizeResult = getimagesize($file->getRealPath());
        if ($imageSizeResult === false) {
            return true;
        }

        return false;
    }
}
