<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;
use Webiny\Component\Config\ConfigObject;

/**
 * Class Filesystem
 *
 * This class handles the backup storage to a filesystem location.
 *
 * @package Webiny\BackupService\Lib
 */
class Filesystem
{

    /**
     * @var string
     */
    private $storagePath;

    /**
     * @param string $storagePath Path on the filesystem where the backups will be stored.
     */
    public function __construct($storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/').DIRECTORY_SEPARATOR;

        if(!is_dir($this->storagePath)){
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Store the give backup
     *
     * @param string $sourceFile      Path to the source file.
     * @param string $destinationFile Name of the file on S3.
     */
    public function upload($sourceFile, $destinationFile)
    {
        $destination = $this->storagePath.rtrim($destinationFile, '/');
        Service::$log->msg('Filesystem: Copying file to the backup storage location: ' . $sourceFile);
        copy($sourceFile, $destination);
        Service::$log->msg('Filesystem: Copy ended: '.$destination);
    }

    /**
     * Moves around the current backups on the filesystem and does some cleanup before new backups are created.
     */
    public function moveOldBackup()
    {
        Service::$log->msg('Filesystem: moving old backups');
        // remove the 2-day file
        if(file_exists($this->storagePath.'backup-2days-old')){
            unlink($this->storagePath.'backup-2days-old');
        }

        // copy current 1-day file to 2-day
        $sourceFile = $this->storagePath . 'backup-1day-old';
        $destinationFile = $this->storagePath . 'backup-2days-old';
        if (file_exists($sourceFile)) {
            copy($sourceFile, $destinationFile);
        }

        // remove the old 1-day file
        $destinationFile = $this->storagePath. 'backup-1day-old';
        if (file_exists($destinationFile)) {
            unlink($destinationFile);
        }

        Service::$log->msg('Filesystem: moving old backups done');
    }

    /**
     * Deletes a given backup from the filesystem.
     *
     * @param string $backupName Backup filename.
     */
    public function deleteBackup($backupName)
    {
        Service::$log->msg('Filesystem: deleting backup ' . $backupName);
        $destinationFile = $this->storagePath . $backupName;

        if (file_exists($destinationFile)) {
            unlink($destinationFile);
        }

        Service::$log->msg('Filesystem: backup deleted ' . $backupName);
    }

    /**
     * Makes a copy of the latest backup archive.
     * This is used to create the weekly, monthly and yearly snapshots.
     *
     * @param string $destination Filename.
     */
    public function copyLatestBackup($destination)
    {
        Service::$log->msg('Filesystem: copying latest backup into ' . $destination);
        $destinationFile = $this->storagePath . $destination;
        if (file_exists($destinationFile)) {
            unlink($destinationFile);
        }

        $sourceFile = $this->storagePath . 'backup-1day-old';
        $destinationFile = $this->storagePath . $destination;
        copy($sourceFile, $destinationFile);
        Service::$log->msg('Filesystem: copying latest backup into ' . $destination . ' done');
    }
}