<?php

namespace enupal\backup\migrations;

use craft\db\Migration;

/**
 * m181030_000000_add_googleDrive_column migration.
 */
class m181030_000000_add_googleDrive_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%enupalbackup_backups}}';

        if (!$this->db->columnExists($table, 'googleDrive')) {
            $this->addColumn($table, 'googleDrive', $this->boolean()->after('softlayer'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181030_000000_add_googleDrive_column cannot be reverted.\n";

        return false;
    }
}
