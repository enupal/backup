<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\contracts;

use Craft;

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
		$this->dbType   = $dbConfig->driver == 'mysql' ? 'mysqldump' : 'pgdump';
		$dbSchema = Craft::$app->getDb()->getSchema();

		$excludeTables = explode(",", $this->settings->excludeData);
		$this->ignoreTables  = '';

		foreach ($excludeTables as $key => $excludeTable)
		{
			$excludeTable = $dbConfig->database.'.'.$dbSchema->getRawTableName('{{%'.trim($excludeTable).'}}');

			if ($key == 0)
			{
				$this->ignoreTables .= $excludeTable;
			}
			else
			{
				$this->ignoreTables .= ','.$excludeTable;
			}
		}

		$this->database = $dbConfig->database;
		$this->host     = $dbConfig->server;
		$this->user     = $dbConfig->user;
		$this->password = $dbConfig->password;
		$this->port     = $dbConfig->port;
		$this->dirName = Backup::$app->backups->getDatabasePath();
	}

	public function getBackup()
	{
		$databaseBackup = [
			'name'   => $this->name,
			'source' => [
				'type'    => $this->dbType,
				'options' => [
					'host'     => $this->host,
					'user'     => $this->user,
					'password' => $this->password,
					'port'     => $this->port
				]
			],
			'target' => [
				'dirname'  => $this->dirName,
				'filename' => $this->fileName
			]
		];

		if ($this->dbType == 'mysqldump')
		{
			$databaseBackup['source']['options']['structureOnly'] = $this->ignoreTables;
			$databaseBackup['source']['options']['databases'] = $this->database;

			if ($this->settings->enablePathToMysqldump && $this->settings->pathToMysqldump)
			{
				$databaseBackup['source']['options']['pathToMysqldump'] = $this->settings->pathToMysqldump;
			}
		}

		if ($this->dbType == 'pgdump')
		{
			$databaseBackup['source']['options']['excludeTableData'] = $this->ignoreTables;
			$databaseBackup['source']['options']['database']         = $this->database;

			if ($this->settings->enablePathToPgdump && $this->settings->pathToPgdump)
			{
				$databaseBackup['source']['options']['pathToPgdump'] = $this->settings->enablePathToPgdump;
			}
		}

		if ($this->syncs)
		{
			$databaseBackup['syncs'] = $this->syncs;
		}

		if ($this->encrypt)
		{
			$databaseBackup['crypt'] = $this->encrypt;
		}

		// Compress on linux
		if (!Backup::$app->settings->isWindows())
		{
			$databaseBackup['target']['compress'] = 'bzip2';
		}

		return $databaseBackup;
	}
}