<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

/**
 * Class Log
 *
 * Simple log class.
 *
 * @package Webiny\BackupService\Lib
 */
class Log
{
    /**
     * @var string Log message buffer.
     */
    private $log = '';

    /**
     * @var string Where the log is placed.
     */
    private $logFolder;

    /**
     * @var Cli Cli instance so we output all the log messages to the terminal.
     */
    private $cli;


    /**
     * @param string $tempFolder Path to the temp folder.
     */
    public function __construct($tempFolder)
    {
        $this->cli = new Cli();
        $this->msg('New log started at ' . date('Y-m-d H:i:s'));

        // check that log folder exits
        $this->logFolder = $tempFolder . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->logFolder)) {
            mkdir($this->logFolder, 0755, true);
        }
    }

    /**
     * Log a message.
     *
     * @param string $msg Message.
     */
    public function msg($msg)
    {
        $this->log .= date('Y-m-d H:i:s') . ' ' . $msg . "\n";
        $this->cli->line($msg);
    }

    /**
     * Writes the log buffer to disk.
     */
    public function writeLog()
    {
        $this->msg('Log end');
        file_put_contents($this->logFolder . 'log-' . date('Y-m-d_H-i-s') . '.log', $this->log);
    }
}