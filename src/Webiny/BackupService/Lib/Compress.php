<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

class Compress
{
    private $cmd = 'tar -czf';
    private $extension = '.tar.gz';


    private $sources;
    private $destination;

    public function __construct($archiveName, $tempFolder)
    {
        // check that temp folder exits
        $backupFolder = $tempFolder . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0755, true);
        }

        $this->destination = $backupFolder . $archiveName . $this->extension;
    }

    public function addSources(array $sources)
    {
        foreach ($sources as $s) {
            if (!file_exists($s)) {
                throw new \Exception(sprintf('Unable to compress %s because the destination doesn\'t exist.', $s));
            }
            $this->sources[] = $s;
        }
    }

    public function compress()
    {
        Service::$log->msg('Compression started');

        $cmd = $this->cmd . ' ' . $this->destination . ' ' . implode(' ', $this->sources);

        Service::$log->msg('Compression command: ' . $cmd);
        system($cmd);
        Service::$log->msg('Compression ended');

        Cleanup::addToQueue($this->destination, Cleanup::TYPE_FILE);

        return $this->destination;
    }
}