<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

/**
 * Class Compress
 *
 * This class gets the list of files and folders and creates an archive of them.
 *
 * @package Webiny\BackupService\Lib
 */
class Compress
{
    /**
     * @var string Compression command we will use.
     */
    private $cmd = 'tar -czf';

    /**
     * @var string File extension that will be placed on the archive output filename.
     */
    private $extension = '.tar.gz';

    /**
     * @var array List of files and folders that will be added to the archive.
     */
    private $sources;

    /**
     * @var string A destination where the archive will be placed.
     */
    private $destination;


    /**
     * @param string $archiveName Archive filename.
     * @param string $tempFolder Path to the temp folder.
     */
    public function __construct($archiveName, $tempFolder)
    {
        // check that temp folder exits
        $backupFolder = $tempFolder . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0755, true);
        }

        $this->destination = $backupFolder . $archiveName . $this->extension;
    }

    /**
     * Add one or more file or folders to the archive.
     *
     * @param array $sources
     *
     * @throws \Exception
     */
    public function addSources(array $sources)
    {
        foreach ($sources as $s) {
            if (!file_exists($s)) {
                throw new \Exception(sprintf('Unable to compress %s because the destination doesn\'t exist.', $s));
            }
            $this->sources[] = $s;
        }
    }

    /**
     * Compress all the defined $sources into a single archive.
     *
     * @return string Path to the archive.
     * @throws \Exception
     */
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