<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

class Cleanup
{
    const TYPE_DIR = 'dir';
    const TYPE_FILE = 'file';

    private static $cleanupQueue;
    private static $tempFolder;

    public static function setTempFolder($tempFolder)
    {
        self::$tempFolder = $tempFolder;
    }

    public static function addToQueue($path, $type)
    {
        if ($type != self::TYPE_DIR && $type != self::TYPE_FILE) {
            throw new \Exception(sprintf('Invalid $type parameter "%s"', $type));
        }

        self::$cleanupQueue[] = ['path' => $path, 'type' => $type];
    }

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
                system('rm -rf '.$cq['path']);
            }

            if ($cq['type'] == self::TYPE_FILE) {
                system('rm '.$cq['path']);
            }
        }

        Service::$log->msg('Cleanup ended');
    }

}