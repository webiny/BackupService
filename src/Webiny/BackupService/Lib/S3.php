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
 * Class S3
 *
 * This class handles the S3 bucket storage.
 *
 * @package Webiny\BackupService\Lib
 */
class S3
{

    /**
     * @var \Webiny\Component\Amazon\S3
     */
    private $s3Instance;

    /**
     * @var ConfigObject S3 configuration.
     */
    private $s3Config;


    /**
     * @param ConfigObject $s3Config S3 configuration.
     */
    public function __construct(ConfigObject $s3Config)
    {
        $this->s3Instance = new \Webiny\Component\Amazon\S3($s3Config->AccessId, $s3Config->AccessKey,
            $s3Config->Region, $s3Config->get('Endpoint', null));
        $this->s3Config = $s3Config;
    }

    /**
     * Upload the give file to S3.
     *
     * @param string $sourceFile      Path to the source file.
     * @param string $destinationFile Name of the file on S3.
     */
    public function upload($sourceFile, $destinationFile)
    {
        Service::$log->msg('Upload to S3 started: ' . $sourceFile);
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/' . $destinationFile;
        $this->s3Instance->multipartUpload($this->s3Config->Bucket, $destinationFile, $sourceFile, 3);
        Service::$log->msg('Upload ended');
    }

    /**
     * Moves around the current backups on S3 and does some cleanup before new backups are created.
     */
    public function moveOldBackup()
    {
        Service::$log->msg('S3: moving old backups');
        // remove the 2-day file
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/backup-2days-old';
        if ($this->s3Instance->doesObjectExist($this->s3Config->Bucket, $destinationFile)) {
            $this->s3Instance->deleteObject($this->s3Config->Bucket, $destinationFile);
        }

        // copy current 1-day file to 2-day
        $sourceFile = rtrim($this->s3Config->RemotePath, '/') . '/backup-1day-old';
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/backup-2days-old';
        if ($this->s3Instance->doesObjectExist($this->s3Config->Bucket, $sourceFile)) {
            $this->s3Instance->copyObject($this->s3Config->Bucket, $sourceFile, $this->s3Config->Bucket,
                $destinationFile);
        }

        // remove the old 1-day file
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/backup-1day-old';
        if ($this->s3Instance->doesObjectExist($this->s3Config->Bucket, $destinationFile)) {
            $this->s3Instance->deleteObject($this->s3Config->Bucket, $destinationFile);
        }

        Service::$log->msg('S3: moving old backups done');
    }

    /**
     * Deletes a given backup from the S3 bucket.
     *
     * @param string $backupName Backup filename on S3.
     */
    public function deleteBackup($backupName)
    {
        Service::$log->msg('S3: deleting backup ' . $backupName);
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/' . $backupName;

        if ($this->s3Instance->doesObjectExist($this->s3Config->Bucket, $destinationFile)) {
            $this->s3Instance->deleteObject($this->s3Config->Bucket, $destinationFile);
        }

        Service::$log->msg('S3: backup deleted ' . $backupName);
    }

    /**
     * Makes a copy of the latest backup archive.
     * This is used to create the weekly, monthly and yearly snapshots, using the latest snapshot that is already on S3,
     * without the need to re-upload the same file again.
     *
     * @param string $destination Filename.
     */
    public function copyLatestBackup($destination)
    {
        Service::$log->msg('S3: copying latest backup into ' . $destination);
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/' . $destination;
        if ($this->s3Instance->doesObjectExist($this->s3Config->Bucket, $destinationFile)) {
            $this->s3Instance->deleteObject($this->s3Config->Bucket, $destinationFile);
        }

        $sourceFile = rtrim($this->s3Config->RemotePath, '/') . '/backup-1day-old';
        $destinationFile = rtrim($this->s3Config->RemotePath, '/') . '/' . $destination;
        $this->s3Instance->copyObject($this->s3Config->Bucket, $sourceFile, $this->s3Config->Bucket, $destinationFile);
        Service::$log->msg('S3: copying latest backup into ' . $destination . ' done');
    }
}