<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

class Log
{
    private $log = '';
    private $logFolder;
    private $cli;

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

    public function msg($msg)
    {
        $this->log .= date('Y-m-d H:i:s') . ' ' . $msg . "\n";
        $this->cli->line($msg);
    }

    public function writeLog()
    {
        $this->msg('Log end');
        file_put_contents($this->logFolder . 'log-' . date('Y-m-d_H-i-s') . '.log', $this->log);
    }
}