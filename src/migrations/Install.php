<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\migrations;

use craft\db\Migration;

/**
 * Installation Migration
 */
class Install extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		$this->createTables();
		$this->addForeignKeys();

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTableIfExists('{{%enupalbackup_backups}}');

		return true;
	}

	/**
	 * Creates the tables.
	 *
	 * @return void
	 */
	protected function createTables()
	{
		$this->createTable('{{%enupalbackup_backups}}', [
			'id'               => $this->primaryKey(),
			'backupId'         => $this->string(),
			'time'             => $this->string(),
			'databaseFileName' => $this->string(),
			'databaseSize'     => $this->string(),
			'assetFileName'    => $this->string(),
			'assetSize'        => $this->string(),
			'templateFileName' => $this->string(),
			'templateSize'     => $this->string(),
			'logFileName'      => $this->string(),
			'logSize'          => $this->string(),
			'backupStatusId'   => $this->integer(),
			'aws'              => $this->boolean(),
			'dropbox'          => $this->boolean(),
			'rsync'            => $this->boolean(),
			'ftp'              => $this->boolean(),
			'softlayer'        => $this->boolean(),
			'isEncrypted'      => $this->boolean(),
			'logMessage'       => $this->text(),
			//
			'dateCreated'   => $this->dateTime()->notNull(),
			'dateUpdated'   => $this->dateTime()->notNull(),
			'uid'           => $this->uid(),
		]);
	}

	/**
	 * Adds the foreign keys.
	 *
	 * @return void
	 */
	protected function addForeignKeys()
	{
		$this->addForeignKey(
			$this->db->getForeignKeyName(
				'{{%enupalbackup_backups}}', 'id'
			),
			'{{%enupalbackup_backups}}', 'id',
			'{{%elements}}', 'id', 'CASCADE', null
		);
	}
}