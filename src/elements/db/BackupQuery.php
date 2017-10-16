<?php
namespace enupal\backup\elements\db;

use Craft;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;

use enupal\backup\Backup;

class BackupQuery extends ElementQuery
{

	// General - Properties
	// =========================================================================
	public $id;
	public $dateCreated;
	public $backupId;
	public $backupStatusId;

	/**
	 * @inheritdoc
	 */
	public function __set($name, $value)
	{
		parent::__set($name, $value);
	}

	/**
	 * @inheritdoc
	 */
	public function backupId($value)
	{
		$this->backupId = $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getBackupId()
	{
		return $this->backupId;
	}


	/**
	 * @inheritdoc
	 */
	public function __construct($elementType, array $config = [])
	{
		// Default orderBy
		if (!isset($config['orderBy'])) {
			$config['orderBy'] = 'enupalbackup_backups.dateCreated';
		}

		parent::__construct($elementType, $config);
	}


	// Protected Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	protected function beforePrepare(): bool
	{
		$this->joinElementTable('enupalbackup_backups');

		$this->query->select([
			'enupalbackup_backups.backupId',
			'enupalbackup_backups.time',
			'enupalbackup_backups.databaseFileName',
			'enupalbackup_backups.databaseSize',
			'enupalbackup_backups.assetFileName',
			'enupalbackup_backups.assetSize',
			'enupalbackup_backups.templateFileName',
			'enupalbackup_backups.templateSize',
			'enupalbackup_backups.logFileName',
			'enupalbackup_backups.logSize',
			'enupalbackup_backups.backupStatusId',
			'enupalbackup_backups.aws',
			'enupalbackup_backups.dropbox',
			'enupalbackup_backups.rsync',
			'enupalbackup_backups.ftp',
			'enupalbackup_backups.softlayer',
			'enupalbackup_backups.logMessage',
			'enupalbackup_backups.isEncrypted',
		]);

		if ($this->backupId) {
			$this->subQuery->andWhere(Db::parseParam(
				'enupalbackup_backups.backupId', $this->backupId)
			);
		}

		if ($this->backupStatusId) {
			$this->subQuery->andWhere(Db::parseParam(
				'enupalbackup_backups.backupStatusId', $this->backupStatusId)
			);
		}

		if ($this->orderBy !== null && empty($this->orderBy) && !$this->structureId && !$this->fixedOrder)
		{
			$this->orderBy = 'elements.dateCreated desc';
		}

		return parent::beforePrepare();
	}
}
