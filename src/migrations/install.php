<?php
namespace enupal\backup\migrations;

use Craft;
use craft\db\Connection;
use craft\db\Migration;
use craft\elements\User;
use craft\helpers\StringHelper;

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
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTable('{{%enupalbackup_backups}}');
	}

	/**
	 * Creates the tables.
	 *
	 * @return void
	 */
	protected function createTables()
	{
		$this->createTable('{{%enupalbackup_backups}}', [
			'id'                   => $this->primaryKey(),
			//
			'dateCreated'          => $this->dateTime()->notNull(),
			'dateUpdated'          => $this->dateTime()->notNull(),
			'uid'                  => $this->uid(),
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