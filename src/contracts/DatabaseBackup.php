<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\contracts;

use Craft;

use craft\helpers\Db;
use enupal\backup\Backup;

class DatabaseBackup extends BackupType
{
    /**
     * @var string
     */
    public $dbType;

    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $user;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $port;

    /**
     * @var string
     */
    public $ignoreTables;

    /**
     * @var string
     */
    public $database;

    public function __construct()
    {
        $this->settings = Backup::$app->settings->getSettings();
        $dbConfig = Craft::$app->getConfig()->getDb();
        $parsed = Db::parseDsn($dbConfig->dsn);
        $database = $parsed['dbname'] ?? '';
        $driver = $parsed['driver'] ?? '';

        $username = !empty($parsed['user']) ? $parsed['user'] : $dbConfig->user;
        $password = !empty($parsed['password']) ? $parsed['password'] : $dbConfig->password;

        $this->dbType = $driver == 'mysql' ? 'mysqldump' : 'pgdump';
        $dbSchema = Craft::$app->getDb()->getSchema();

        $excludeTables = explode(",", $this->settings->excludeData);
        $this->ignoreTables = '';

        foreach ($excludeTables as $key => $excludeTable) {
            $excludeTable = $database.'.'.$dbSchema->getRawTableName('{{%'.trim($excludeTable).'}}');

            if ($key == 0) {
                $this->ignoreTables .= $excludeTable;
            } else {
                $this->ignoreTables .= ','.$excludeTable;
            }
        }

        $this->database = $database;
        $this->host = $parsed['host'] ?? '';
        $this->user = $username;
        $this->password = $password;
        $this->port = $parsed['port'] ?? '';
        $this->dirName = Backup::$app->backups->getDatabasePath();
    }

    /**
     * @inheritdoc
     */
    public function getBackup()
    {
        $databaseBackup = [
            'name' => $this->name,
            'source' => [
                'type' => $this->dbType,
                'options' => [
                    'host' => $this->host,
                    'user' => $this->user,
                    'password' => $this->password,
                    'port' => $this->port
                ]
            ],
            'target' => [
                'dirname' => $this->dirName,
                'filename' => $this->fileName
            ]
        ];

        if ($this->dbType == 'mysqldump') {
            $databaseBackup['source']['options']['structureOnly'] = $this->ignoreTables;
            $databaseBackup['source']['options']['databases'] = $this->database;

            if ($this->settings->enablePathToMysqldump && $this->settings->pathToMysqldump) {
                $databaseBackup['source']['options']['pathToMysqldump'] = $this->settings->pathToMysqldump;
            }
        }

        if ($this->dbType == 'pgdump') {
            $databaseBackup['source']['options']['excludeTableData'] = $this->ignoreTables;
            $databaseBackup['source']['options']['database'] = $this->database;

            if ($this->settings->enablePathToPgdump && $this->settings->pathToPgdump) {
                $databaseBackup['source']['options']['pathToPgdump'] = $this->settings->pathToPgdump;
            }
        }

        if ($this->syncs) {
            $databaseBackup['syncs'] = $this->syncs;
        }

        if ($this->encrypt) {
            $databaseBackup['crypt'] = $this->encrypt;
        }

        $settings = Backup::$app->settings->getSettings();

        // Compress on linux
        if (!Backup::$app->settings->isWindows() && $settings->compressWithBz2) {
            $databaseBackup['target']['compress'] = 'bzip2';
        }

        return $databaseBackup;
    }
}