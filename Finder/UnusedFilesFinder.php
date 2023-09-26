<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Finder;

use Baldwin\ImageCleanup\Config\ConfigReader;
use Baldwin\ImageCleanup\Console\ProgressIndicator;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as GalleryResourceModel;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Io\File as FileIo;

class UnusedFilesFinder
{
    private $progressIndicator;
    private $resource;
    private $filesystem;
    private $fileIo;
    private $configReader;

    /** @var array<string, array<string>> */
    private $fileMapping = [];

    public function __construct(
        ProgressIndicator $progressIndicator,
        ResourceConnection $resource,
        Filesystem $filesystem,
        FileIo $fileIo,
        ConfigReader $configReader
    ) {
        $this->progressIndicator = $progressIndicator;
        $this->resource = $resource;
        $this->filesystem = $filesystem;
        $this->fileIo = $fileIo;
        $this->configReader = $configReader;
    }

    /**
     * @return array<string>
     */
    public function find(): array
    {
        $this->progressIndicator->start('Searching for unused files');

        $filesInDb = $this->getFilenamesFromDb();
        $filesInDb = $this->extendWithAlternatives($filesInDb);

        $this->progressIndicator->advance();

        $filesOnDisk = $this->getFilesOnDisk();

        $unusedFiles = array_diff($filesOnDisk, $filesInDb);
        $unusedFiles = $this->getUnusedFilesAbsolutePaths($unusedFiles);

        $this->progressIndicator->stop();

        return $unusedFiles;
    }

    /**
     * @return array<string>
     */
    private function getFilenamesFromDb(): array
    {
        // we don't check here if the gallery entries are assigned to an existing entity
        // we have the catalog:images:remove-obsolete-db-entries command to get rid of those obsolete entries first

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(GalleryResourceModel::GALLERY_TABLE))
            ->reset(Select::COLUMNS)
            ->columns(new \Zend_Db_Expr('DISTINCT value'))
            // we only search for filenames that start with /x/,
            // because we will always assume this sort of filepath later when we compare with files on the disk
            ->where("value LIKE '/_/%'")
        ;

        /** @var array<string> */
        $filenames = $connection->fetchCol($select);

        $filenames = array_map(
            function (string $file) {
                return ltrim($file, '/');
            },
            $filenames
        );

        return $filenames;
    }

    /**
     * @return array<string>
     */
    private function getFilesOnDisk(): array
    {
        $filesOnDisk = [];

        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $mediaDirectoryPath = $mediaDirectory->getAbsolutePath();

        $productImageDirectories = $this->getProductImageDirectories($mediaDirectory);
        $this->progressIndicator->setMax(count($productImageDirectories));

        foreach ($productImageDirectories as $productImageDir) {
            $fileIterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $mediaDirectory->getAbsolutePath($productImageDir),
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            /** @var \SplFileInfo $file */
            foreach ($fileIterator as $file) {
                if ($file->isFile()) {
                    $absPath = $file->getRealPath();
                    $filenameWithoutExtension = $file->getBasename('.' . $file->getExtension());
                    $filename = $this->normalizeFilename($absPath, $mediaDirectoryPath);
                    if ($filename !== null) {
                        // keep track of mapping between relative filename and absolute paths,
                        // because a single relative filename could match multiple absolute paths
                        if (!array_key_exists($filename, $this->fileMapping)) {
                            $this->fileMapping[$filename] = [];
                        }
                        $this->fileMapping[$filename][] = $absPath;

                        $filesOnDisk[] = $filename;
                    }
                }
            }

            $this->progressIndicator->advance();
        }

        return array_unique($filesOnDisk);
    }

    /**
     * @return array<string>
     */
    private function getProductImageDirectories(ReadInterface $mediaDirectory): array
    {
        // find all cached hash subdirectories,
        // so the ones starting with a single letter in their directory name
        $subHashDirectories = [];

        /** @var array<string> */
        $hashDirectories = $mediaDirectory->read('catalog/product/cache');
        foreach ($hashDirectories as $hashDir) {
            if ($mediaDirectory->isDirectory($hashDir)) {
                $subHashDirectories[] = $mediaDirectory->read($hashDir);
            }
        }

        /** @var array<string> */
        $productImageDirectories = array_merge(
            $mediaDirectory->read('catalog/product'),
            ...$subHashDirectories
        );

        // only allow directories that have one character as lowest path
        $productImageDirectories = array_filter(
            $productImageDirectories,
            function (string $path) use ($mediaDirectory) {
                return $mediaDirectory->isDirectory($path) && preg_match('#/.{1}$#', $path) === 1;
            }
        );

        return $productImageDirectories;
    }

    private function normalizeFilename(string $filename, string $mediaDirectoryPath): ?string
    {
        // remove media directory path from filename
        $regex = '#^' . preg_quote($mediaDirectoryPath) . '#';
        $filename = preg_replace($regex, '', $filename);

        if ($filename !== null) {
            // reduce filename path even further so we end up with
            // only the part from the directory with one single character
            $pathParts = explode('/', $filename);
            foreach ($pathParts as $index => $part) {
                if (strlen($part) === 1) {
                    $filename = implode('/', array_splice($pathParts, $index));

                    return $filename;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string> $files
     *
     * @return array<string>
     */
    private function extendWithAlternatives(array $files): array
    {
        $alternativeExtensions = $this->configReader->getAlternativeExtensions();

        $extraFiles = [];
        array_walk($files, function (string $filename) use ($alternativeExtensions, &$extraFiles) {
            /** @var array<string> */
            $pathInfo = $this->fileIo->getPathInfo($filename);

            $fileWithoutExtension = $pathInfo['dirname'] . '/' . $pathInfo['filename'];

            foreach ($alternativeExtensions as $ext) {
                $extraFiles[] = $fileWithoutExtension . '.' . $ext;
                $extraFiles[] = $filename . '.' . $ext;
            }
        });

        return array_unique(array_merge($files, $extraFiles));
    }

    /**
     * @param array<string> $paths
     *
     * @return array<string>
     */
    private function getUnusedFilesAbsolutePaths(array $paths): array
    {
        $result = [];

        foreach ($paths as $path) {
            $result[] = $this->fileMapping[$path];
        }

        return array_merge([], ...$result);
    }
}
