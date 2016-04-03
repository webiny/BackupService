<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\BackupService\Lib;

use Webiny\BackupService\Service;

/**
 * Class BackupMongo
 *
 * This class creates the MongoDb database dumps.
 *
 * @package Webiny\BackupService\Lib
 */
class BackupMongo
{

    /**
     * @var string Path to the temp folder.
     */
    private $tempFolder;

    /**
     * @var array List of databases that need to be exported.
     */
    private $databases;


    /**
     * @param array  $databases  List of databases that need to be exported.
     * @param string $tempFolder Path to the temp folder.
     */
    public function __construct(array $databases, $tempFolder)
    {
        # check the temp folder location
        $this->tempFolder = $tempFolder . DIRECTORY_SEPARATOR . 'mongo-exports' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->tempFolder)) {
            mkdir($this->tempFolder, 0755, true);
        }

        $this->databases = $databases;
    }

    /**
     * Loops over the list of databases and calls createExport method.
     *
     * @return array List of database export directories.
     * @throws \Exception
     */
    public function exportDatabases()
    {
        $exports = [];
        foreach ($this->databases as $db) {
            $exports[] = $this->createExport($db);
        }

        return $exports;
    }

    /**
     * Private method that does the actual database export.
     *
     * @param array $db Database entry configuration.
     *
     * @return string Path to the created database export.
     * @throws \Exception
     */
    private function createExport($db)
    {
        Service::$log->msg(sprintf('Mongodb: exporting "%s" database.', $db['Database']));

        $cmd = 'mongodump';

        if (!isset($db['Database'])) {
            throw new \Exception(sprintf('Missing "Database" parameter for one of the MongoDatabases'));
        }

        $cmd .= ' --db ' . $db['Database'];

        if (!isset($db['Host'])) {
            throw new \Exception(sprintf('Missing "Host" parameter for "%s" database', $db['Database']));
        }

        $host = explode(':', $db['Host']);

        $cmd .= ' --host ' . $host[0];

        if (isset($host[1])) {
            $cmd .= ' --port ' . $host[1];
        }

        if (isset($db['Username']) && !empty($db['Username'])) {
            $cmd .= ' --username ' . $db['Username'];
        }

        if (isset($db['Password']) && !empty($db['Password'])) {
            $cmd .= ' --password ' . $db['Password'];
        }

        // create a folder for this database export
        $folderName = $this->tempFolder . $db['Database'] . '-' . date('Y-m-d');
        $cmd .= ' --out ' . $folderName;

        Service::$log->msg(sprintf('Mongodb: export command ' . $cmd));

        system($cmd);

        Service::$log->msg(sprintf('Mongodb: database export done'));

        Cleanup::addToQueue($folderName, Cleanup::TYPE_DIR);

        return $folderName;
    }
}