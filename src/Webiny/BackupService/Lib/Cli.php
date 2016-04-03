<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

/**
 * Class Cli
 * @package Webiny\BackupService\Lib
 */
class Cli
{
    /**
     * Print a message and end with a new line.
     *
     * @param string $msg
     */
    public function line($msg = '')
    {
        \cli\line($msg);
    }

}
