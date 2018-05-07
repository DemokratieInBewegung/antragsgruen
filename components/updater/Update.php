<?php

namespace app\components\updater;

class Update
{
    const TYPE_PATCH = 'patch';

    public $type;
    public $version;
    public $changelog;
    public $url;
    public $filesize;
    public $signature;

    private $updatedFiles = null;

    /**
     * UpdateInformation constructor.
     *
     * @param array $json
     * @throws \Exception
     */
    public function __construct($json)
    {
        $this->type      = $json['type'];
        $this->version   = $json['version'];
        $this->changelog = $json['changelog'];
        $this->url       = $json['url'];
        $this->filesize  = $json['filesize'];
        $this->signature = $json['signature'];

        if ($this->type !== static::TYPE_PATCH) {
            throw new \Exception("Only patch releases are supported");
        }
    }

    /**
     * @return string
     */
    private function getBasePath()
    {
        return __DIR__ . '/../../';
    }

    /**
     * @return string
     */
    private function getAbsolutePath()
    {
        $dir = $this->getBasePath() . 'runtime/updates/';
        if (!file_exists($dir)) {
            mkdir($dir, 0755);
        }
        $base = explode("/", $this->url);
        return $dir . $base[count($base) - 1];
    }

    /**
     * @param string $version
     * @return string
     * @throws \Exception
     */
    private function getBackupPath($version)
    {
        $dir = $this->getBasePath() . 'runtime/backups/' . $version . '/';
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception('Could not create backup directory');
            }
        }
        return $dir;
    }

    /**
     * return bool
     */
    public function isDownloaded()
    {
        if (!file_exists($this->getAbsolutePath())) {
            return false;
        }
        $content = file_get_contents($this->getAbsolutePath());
        return (base64_encode(sodium_crypto_generichash($content)) === $this->signature);
    }

    /**
     * @throws \Exception
     */
    public function download()
    {
        $curlc = curl_init($this->url);
        curl_setopt($curlc, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($curlc);
        $info = curl_getinfo($curlc);
        curl_close($curlc);

        if (!isset($info['http_code'])) {
            throw new \Exception('The update could not be loaded');
        }
        if ($info['http_code'] !== 200) {
            throw new \Exception('The update could not be loaded');
        }

        if (base64_encode(sodium_crypto_generichash($resp)) !== $this->signature) {
            throw new \Exception('The update file has the wrong checksum');
        }

        file_put_contents($this->getAbsolutePath(), $resp);
    }

    /**
     * @return UpdatedFiles
     * @throws \Exception
     */
    public function readUpdateJson()
    {
        if (!$this->updatedFiles) {
            if (!$this->isDownloaded()) {
                throw new \Exception("File is not yet downloaded");
            }

            $zipfile = new \ZipArchive();
            if ($zipfile->open($this->getAbsolutePath()) !== true) {
                throw new \Exception("Could not open the ZIP file");
            }

            $updateJson      = $zipfile->getFromName('update.json');
            $updateSignature = base64_decode($zipfile->getFromName('update.json.signature'));
            $publicKey       = base64_decode(file_get_contents(__DIR__ . '/../../config/update-public.key'));
            if (!sodium_crypto_sign_verify_detached($updateSignature, $updateJson, $publicKey)) {
                throw new \Exception("The signature of the update file is invalid");
            }

            $zipfile->close();

            $this->updatedFiles = new UpdatedFiles($updateJson);
        }

        return $this->updatedFiles;
    }

    /**
     * @param string $version
     * @throws \Exception
     */
    public function backupOldFiles($version)
    {
        $basepath = $this->getBackupPath($version);
        $filesObj = $this->readUpdateJson();
        $files    = array_merge(array_keys($filesObj->files_updated), $filesObj->files_deleted);
        foreach ($files as $file) {
            $fulldir = $basepath . (dirname($file) === '.' ? '' : dirname($file));
            if (!file_exists($fulldir) && !mkdir($fulldir, 0755, true)) {
                throw new \Exception('Could not create backup sub-directory: ' . $fulldir);
            }
            if (!file_exists($this->getBasePath() . $file)) {
                throw new \Exception('An expected file of the current version was not found: ' . $file);
            }
            if (!copy($this->getBasePath() . $file, $fulldir . DIRECTORY_SEPARATOR . basename($file))) {
                throw new \Exception('Could not back up file: ' . $file);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function verifyFileIntegrity()
    {
        $files = $this->readUpdateJson();

        $zipfile = new \ZipArchive();
        if ($zipfile->open($this->getAbsolutePath()) !== true) {
            throw new \Exception('Could not open the ZIP file');
        }

        $fileList = array_merge($files->files_added, $files->files_updated);
        foreach ($fileList as $file => $correctHash) {
            $content = $zipfile->getFromName($file);
            $zipHash = base64_encode(sodium_crypto_generichash($content));
            if ($zipHash !== $correctHash) {
                throw new \Exception('The files in the backup file seem to be corrupted: ' . $file);
            }
        }

        $fileList = array_merge($files->files_deleted, array_keys($files->files_updated));
        foreach ($fileList as $file) {
            if (!file_exists($this->getBasePath() . $file)) {
                throw new \Exception('File to be updated was not found: ' . $file);
            }
        }

        $zipfile->close();
    }

    /**
     * @returns \Exception
     */
    public function checkFilePermissions()
    {
        $fileObj = $this->readUpdateJson();
        $basepath = $this->getBasePath();

        foreach ($fileObj->files_deleted as $file) {
            if (!is_writable($basepath . $file)) {
                throw new \Exception('Cannot write file: ' . $file);
            }
        }

        foreach (array_keys($fileObj->files_updated) as $file) {
            if (!is_writable($basepath . $file)) {
                throw new \Exception('Cannot write file: ' . $file);
            }
        }

        foreach (array_keys($fileObj->files_added) as $file) {
            $file = dirname($file);
            if (!is_writable($basepath . $file)) {
                throw new \Exception('Cannot write to directory: ' . $file);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function performUpdate()
    {
        $filesObj = $this->readUpdateJson();

        $zipfile = new \ZipArchive();
        if ($zipfile->open($this->getAbsolutePath()) !== true) {
            throw new \Exception('Could not open the ZIP file');
        }

        $fileList = array_merge($filesObj->files_added, $filesObj->files_updated);
        foreach (array_keys($fileList) as $file) {
            $content = $zipfile->getFromName($file);
            if (file_put_contents($this->getBasePath() . $file, $content) === false) {
                throw new \Exception('The file could not be updated: ' . $file);
            }
        }

        foreach ($filesObj->files_deleted as $file) {
            if (!unlink($this->getBasePath() . $file)) {
                throw new \Exception('The file could not be deleted: ' . $file);
            }
        }

        $zipfile->close();
    }
}
