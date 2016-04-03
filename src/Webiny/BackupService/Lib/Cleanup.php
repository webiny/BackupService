<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

/**
 * Class Cleanup
 *
 * The cleanup scripts aggregates a list of files or directories that need to be removed, once the backups was created.
 *
 * @package Webiny\BackupService\Lib
 */
class Cleanup
{

    const TYPE_DIR = 'dir';
    const TYPE_FILE = 'file';

    /**
     * @var array List of files and directories that will be removed after the backup is created and uploaded.
     */
    private static $cleanupQueue;

    /**
     * @var string Path to the temp folder. Note: the script can only remove fhe files from the temp folder.
     * All other folders are off-limit.
     */
    private static $tempFolder;


    /**
     * @param string $tempFolder Path to the temp folder
     */
    public static function setTempFolder($tempFolder)
    {
        self::$tempFolder = $tempFolder;
    }

    /**
     * Add a file or a folder to the cleanup queue.
     *
     * @param string $path Path that should be removed.
     * @param string $type Is it a file or a directory.
     *
     * @throws \Exception
     */
    public static function addToQueue($path, $type)
    {
        if ($type != self::TYPE_DIR && $type != self::TYPE_FILE) {
            throw new \Exception(sprintf('Invalid $type parameter "%s"', $type));
        }

        self::$cleanupQueue[] = ['path' => $path, 'type' => $type];
    }

    /**
     * Method that does the actual cleanup.
     *
     * @throws \Exception
     */
    public static function doCleanup()
    {
        Service::$log->msg('Cleanup started');
        foreach (self::$cleanupQueue as $cq) {
            Service::$log->msg(sprintf('Removing %s %s', $cq['type'], $cq['path']));

            if (strpos($cq['path'], self::$tempFolder) !== 0) {
                throw new \Exception(sprintf('Path "%s" is not within the defined temp folder location "%s".',
                    $cq['path'], self::$tempFolder));
            }

            if ($cq['type'] == self::TYPE_DIR) {
                system('rm -rf ' . $cq['path']);
            }

            if ($cq['type'] == self::TYPE_FILE) {
                system('rm ' . $cq['path']);
            }
        }

        Service::$log->msg('Cleanup ended');
    }

}