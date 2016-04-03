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
     * @var Log
     */
    public static $log;


    /**
     * @param string $pathToConfig Path to the backup yaml config.
     *
     * @throws \Exception
     */
    public function __construct($pathToConfig)
    {
        $this->startTime = $this->getTime();

        $this->config = \Webiny\Component\Config\Config::getInstance()->yaml($pathToConfig);
        $this->backupConfig = $this->config->BackupService;

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
     * Runs the backup process.
     */
    public function createBackup()
    {
        try {
            // compressor instance
            $compressor = new Compress('backup-' . date('Y-m-d_H-i-s'), $this->backupConfig->TempPath);

            // first we need to export all the databases
            $backupMongo = new BackupMongo($this->backupConfig->get('MongoDatabases', [], true),
                $this->backupConfig->TempPath);
            $compressor->addSources($backupMongo->exportDatabases());

            // add folders to the compressor
            $compressor->addSources($this->backupConfig->get('Folders', [], true));

            // compress all the folders and database exports in one archive
            $backupArchive = $compressor->compress();

            // encrypt the archive
            $encryption = new Encrypt($backupArchive, $this->backupConfig->Passphrase, $this->backupConfig->TempPath);
            $encArchive = $encryption->encrypt();

            $s3 = new Lib\S3($this->backupConfig->get('S3'));

            // move the old 1-day backup to 2-day backup
            $s3->moveOldBackup();

            // create new 1 day backup
            $s3->upload($encArchive, 'backup-1day-old');

            // check if we need 7-day backup and 30-day backup
            $frequencies = $this->backupConfig->get('Frequency', [], true);

            // week
            // we always do weekly backups on sunday
            if (in_array('Week', $frequencies) && date('w') == '0') {
                $s3->deleteBackup('backup-week');
                $s3->copyLatestBackup('backup-week');
            }

            // month
            // we always do monthly backups on the last day of the month
            if (in_array('Month', $frequencies) && date('d') == date('t')) {
                $s3->deleteBackup('backup-month');
                $s3->copyLatestBackup('backup-month');
            }

            // year
            // we always do monthly backups on the last day of the year
            if (in_array('Year', $frequencies) && date('d.m') == '31.12') {
                $s3->deleteBackup('backup-year');
                $s3->copyLatestBackup('backup-year');
            }

            // do the cleanup
            Cleanup::doCleanup();

            // end log
            self::$log->msg('Backup ended');
            self::$log->msg('Execution time: ' . $this->displayTime($this->getTime() - $this->startTime));
            self::$log->writeLog();
        } catch (\Exception $e) {
            self::$log->msg('ERROR: ' . $e->getMessage());
            self::$log->writeLog();
        }
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

    /**
     * @param int $seconds Displays time in a human readable format.
     *
     * @return string
     */
    private function displayTime($seconds)
    {
        $seconds = round($seconds);
        if ($seconds < 60) {
            return $seconds . ' sec';
        }
        $minutes = round($seconds / 60);
        $seconds = $seconds % 60;
        return $minutes . ' min ' . $seconds . ' sec';
    }

}