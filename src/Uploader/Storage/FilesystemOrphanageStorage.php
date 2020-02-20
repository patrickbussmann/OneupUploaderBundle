<?php

declare(strict_types=1);

namespace Oneup\UploaderBundle\Uploader\Storage;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\File\FilesystemFile;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FilesystemOrphanageStorage extends FilesystemStorage implements OrphanageStorageInterface
{
    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $type;

    public function __construct(StorageInterface $storage, SessionInterface $session, array $config, string $type)
    {
        parent::__construct($config['directory']);

        // We can just ignore the chunkstorage here, it's not needed to access the files
        $this->storage = $storage;
        $this->session = $session;
        $this->config = $config;
        $this->type = $type;
    }

    /**
     * @param FileInterface|File $file
     *
     * @return FileInterface|File
     */
    public function upload($file, string $name, string $path = null)
    {
        if (!$this->session->isStarted()) {
            throw new \RuntimeException('You need a running session in order to run the Orphanage.');
        }

        return parent::upload($file, $name, $this->getPath());
    }

    public function uploadFiles(array $files = null): array
    {
        try {
            if (null === $files) {
                $files = $this->getFiles();
            }
            $return = [];

            foreach ($files as $file) {
                $return[] = $this->storage->upload(new FilesystemFile(new File($file->getPathname())), ltrim(str_replace($this->getFindPath(), '', $file), '/'));
            }

            return $return;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFiles(): array
    {
        $finder = new Finder();

        try {
            $finder->in($this->getFindPath())->files();
        } catch (\InvalidArgumentException $e) {
            //catch non-existing directory exception.
            //This can happen if getFiles is called and no file has yet been uploaded

            //push empty array into the finder so we can emulate no files found
            $finder->append([]);
        }

        return iterator_to_array($finder);
    }

    protected function getPath(): string
    {
        return sprintf('%s/%s', $this->session->getId(), $this->type);
    }

    protected function getFindPath(): string
    {
        return sprintf('%s/%s', $this->config['directory'], $this->getPath());
    }
}