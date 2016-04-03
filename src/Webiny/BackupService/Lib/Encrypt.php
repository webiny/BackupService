<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

class Encrypt
{

    private $source;
    private $destination;
    private $passphrase;

    public function __construct($source, $archiveName, $passphrase, $tempFolder)
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
        $this->destination = $backupFolder.$archiveName;
        $this->passphrase = $passphrase;
    }

    public function encrypt()
    {
        Service::$log->msg('Encryption started');

        $cmd = 'echo "' . $this->passphrase . '" | gpg --batch --passphrase-fd 0 -c '.$this->source;

        Service::$log->msg('Encryption command: '.$cmd);

        system($cmd);

        Service::$log->msg('Encryption ended');

        $archive = $this->source.'.gpg';

        Cleanup::addToQueue($archive, Cleanup::TYPE_FILE);

        return $archive;
    }

}