<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

/**
 * Class Encrypt
 *
 * Once the backup archive is created, this class creates an encrypted copy.
 *
 * @package Webiny\BackupService\Lib
 */
class Encrypt
{

    /**
     * @var string Path to the raw archive.
     */
    private $source;

    /**
     * @var string Passphrase that will be used to encrypt the archive.
     */
    private $passphrase;


    /**
     * @param string $source     Path to the raw archive.
     * @param string $passphrase Passphrase that will be used to encrypt the archive.
     * @param string $tempFolder Path to the temp folder.
     *
     * @throws \Exception
     */
    public function __construct($source, $passphrase, $tempFolder)
    {
        if (!is_file($source)) {
            throw new \Exception(sprintf('Source file "%s" doesn\'t exist.', $source));
        }

        if (empty($passphrase)) {
            throw new \Exception(sprintf('Passphrase cannot be empty.'));
        }

        // check that temp folder exits
        $backupFolder = $tempFolder . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0755, true);
        }

        $this->source = $source;
        $this->passphrase = $passphrase;
    }

    /**
     * Does the archive encryption.
     * @return string Path to the encrypted archive.
     * @throws \Exception
     */
    public function encrypt()
    {
        Service::$log->msg('Encryption started');

        $cmd = 'echo "' . $this->passphrase . '" | gpg --batch --passphrase-fd 0 -c ' . $this->source;

        Service::$log->msg('Encryption command: ' . $cmd);

        system($cmd);

        Service::$log->msg('Encryption ended');

        $archive = $this->source . '.gpg';

        Cleanup::addToQueue($archive, Cleanup::TYPE_FILE);

        return $archive;
    }

}