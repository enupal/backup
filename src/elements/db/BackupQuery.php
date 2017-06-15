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
			'enupalbackup_backups.dateCreated'
		]);

		if ($this->dateCreated) {
			$this->subQuery->andWhere(Db::parseParam(
				'enupalbackup_backups.dateCreated', $this->dateCreated)
			);
		}

		return parent::beforePrepare();
	}
}
