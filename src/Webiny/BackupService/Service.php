<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService;

use Webiny\BackupService\Lib\BackupMongo;
use Webiny\BackupService\Lib\Cleanup;
use Webiny\BackupService\Lib\Compress;
use Webiny\BackupService\Lib\Encrypt;
use Webiny\BackupService\Lib\Log;
use Webiny\Component\Amazon\S3;
use Webiny\Component\Config\ConfigObject;

/**
 * Class Service
 *
 *
 * @package Webiny\BackupService
 */
class Service
{
    /**
     * @var ConfigObject
     */
    private $config;

    /**
     * @var int Time when the backup started
     */
    private $startTime;

    /**
     * @var ConfigObject
     */
    private $backupConfig;

    /**
     * @var bool Should we write the log to the disk or not
     */
    private $storeLog = true;

    /**
     * @var Log
     */
    public static $log;

    /**
     * @var boolean Flag that defines if the whole backup process was successful.
     */
    private $isSuccessful = false;

    /**
     * @var array List of backup files that were created and synced.
     */
    private $backupsCreated;


    /**
     * @param string|array $config Either a config in form of an array, or a path to the yaml file.
     *
     * @throws \Exception
     */
    public function __construct($config)
    {
        $this->startTime = $this->getTime();

        if (is_array($config)) {
            $this->config = \Webiny\Component\Config\Config::getInstance()->php($config);
            $this->backupConfig = $this->config;
        } else {
            $this->config = \Webiny\Component\Config\Config::getInstance()->yaml($config);
            $this->backupConfig = $this->config->BackupService;
        }

        if (!$this->backupConfig->get('TempPath',
                false) || trim($this->backupConfig->TempPath) == '' || rtrim($this->backupConfig->TempPath, '/') == ''
        ) {
            throw new \Exception('Please set the TempPath in your configuration.');
        }

        $this->backupConfig->TempPath = rtrim($this->backupConfig->TempPath, DIRECTORY_SEPARATOR);

        // start the log
        self::$log = new Log($this->backupConfig->TempPath);

        // set the temp folder for cleanup service
        Cleanup::setTempFolder($this->backupConfig->TempPath);
    }

    /**
     * @param boolean $storeLog
     */
    public function setStoreLog($storeLog)
    {
        $this->storeLog = (boolean)$storeLog;
    }

    /**
     * Runs the backup process.
     */
    public function createBackup()
    {
        try {
            // compressor instance
            $compressor = new Compress('backup-' . date('Y-m-d_H-i-s'), $this->backupConfig->TempPath);

            // first we need to export all the databases
            $backupMongo = new BackupMongo($this->backupConfig->get('MongoDatabases', [], true), $this->backupConfig->TempPath);
            $compressor->addSources($backupMongo->exportDatabases());

            // add folders to the compressor
            $compressor->addSources($this->backupConfig->get('Folders', [], true));

            // compress all the folders and database exports in one archive
            $backupArchive = $compressor->compress();

            // encrypt the archive -> only if passpharse is set
            $encryptionStatus = false;
            if ($this->backupConfig->get('Encryption', false)) {
                $encryption = new Encrypt($backupArchive, $this->backupConfig->Encryption, $this->backupConfig->TempPath);
                $encArchive = $encryption->encrypt();
                $encryptionStatus = true;
            } else {
                $encArchive = $backupArchive;
            }

            $backupSize = filesize($encArchive);

            // check if we should backup to S3
            if ($this->backupConfig->get('S3', false)) {
                $s3 = new Lib\S3($this->backupConfig->get('S3'));

                // move the old 1-day backup to 2-day backup
                $oldBackup = $s3->moveOldBackup();
                $this->backupsCreated['s3']['48h'] = [
                    'filename'  => $oldBackup,
                    'encrypted' => $encryptionStatus
                ];
                

                // create new 1 day backup
                $uploadedFile = $s3->upload($encArchive, 'backup-1day-old');
                $this->backupsCreated['s3']['24h'] = [
                    'size'      => $backupSize,
                    'filename'  => $uploadedFile,
                    'encrypted' => $encryptionStatus
                ];

                // check if we need 7-day backup and 30-day backup
                $frequencies = $this->backupConfig->get('Frequency', [], true);

                // week
                // we always do weekly backups on sunday
                if (in_array('Week', $frequencies) && date('w') == '0') {
                    $s3->deleteBackup('backup-week');
                    $copiedFile = $s3->copyLatestBackup('backup-week');
                    $this->backupsCreated['s3']['weekly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }

                // month
                // we always do monthly backups on the last day of the month
                if (in_array('Month', $frequencies) && date('d') == date('t')) {
                    $s3->deleteBackup('backup-month');
                    $copiedFile = $s3->copyLatestBackup('backup-month');
                    $this->backupsCreated['s3']['monthly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }

                // year
                // we always do monthly backups on the last day of the year
                if (in_array('Year', $frequencies) && date('d.m') == '31.12') {
                    $s3->deleteBackup('backup-year');
                    $copiedFile = $s3->copyLatestBackup('backup-year');
                    $this->backupsCreated['s3']['yearly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }
            }

            // check if a file system backup is required
            if ($this->backupConfig->get('BackupStoragePath', false)) {
                $fsBackup = new Lib\Filesystem($this->backupConfig->get('BackupStoragePath'));

                // move the old 1-day backup to 2-day backup
                $oldBackup = $fsBackup->moveOldBackup();
                $this->backupsCreated['local']['48h'] = [
                    'filename'  => $oldBackup,
                    'encrypted' => $encryptionStatus
                ];

                // create new 1 day backup
                $uploadedFile = $fsBackup->upload($encArchive, 'backup-1day-old');
                $this->backupsCreated['local']['24h'] = [
                    'size'      => $backupSize,
                    'filename'  => $uploadedFile,
                    'encrypted' => $encryptionStatus
                ];

                // check if we need 7-day backup and 30-day backup
                $frequencies = $this->backupConfig->get('Frequency', [], true);

                // week
                // we always do weekly backups on sunday
                if (in_array('Week', $frequencies) && date('w') == '0') {
                    $fsBackup->deleteBackup('backup-week');
                    $copiedFile = $fsBackup->copyLatestBackup('backup-week');
                    $this->backupsCreated['local']['weekly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }

                // month
                // we always do monthly backups on the last day of the month
                if (in_array('Month', $frequencies) && date('d') == date('t')) {
                    $fsBackup->deleteBackup('backup-month');
                    $copiedFile = $fsBackup->copyLatestBackup('backup-month');
                    $this->backupsCreated['local']['monthly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }

                // year
                // we always do monthly backups on the last day of the year
                if (in_array('Year', $frequencies) && date('d.m') == '31.12') {
                    $fsBackup->deleteBackup('backup-year');
                    $copiedFile = $fsBackup->copyLatestBackup('backup-year');
                    $this->backupsCreated['local']['yearly'] = [
                        'size'      => $backupSize,
                        'filename'  => $copiedFile,
                        'encrypted' => $encryptionStatus
                    ];
                }
            }

            // do the cleanup
            Cleanup::doCleanup();

            // end log
            self::$log->msg('Backup ended');
            self::$log->msg('Execution time: ' . $this->getExecutionTime());

            // set success flag
            $this->isSuccessful = true;

            if ($this->storeLog) {
                self::$log->writeLog();
            } else {
                return self::$log->getLog();
            }

        } catch (\Exception $e) {
            // set success flag
            $this->isSuccessful = false;

            self::$log->msg('ERROR: ' . $e->getMessage());
            if ($this->storeLog) {
                self::$log->writeLog();
            } else {
                return self::$log->getLog();
            }
        }
    }

    /**
     * @return bool Was the backup process overall successful
     */
    public function isSuccessful()
    {
        return $this->isSuccessful;
    }

    /**
     * @return array Returns a list of backups that were created
     */
    public function getCreatedBackups()
    {
        return $this->backupsCreated;
    }

    /**
     * @param int $seconds Displays time in a human readable format.
     *
     * @return string
     */
    public function getExecutionTime()
    {
        $seconds = $this->getTime() - $this->startTime;

        $seconds = round($seconds);
        if ($seconds < 60) {
            return $seconds . ' sec';
        }
        $minutes = round($seconds / 60);
        $seconds = $seconds % 60;

        return $minutes . ' min ' . $seconds . ' sec';
    }

    /**
     * @return int Gets the current time.
     */
    private function getTime()
    {
        $timer = explode(' ', microtime());
        $timer = $timer[1] + $timer[0];

        return $timer;
    }

}
