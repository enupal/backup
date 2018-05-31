<?php

namespace enupal\backup\migrations;

use craft\db\Migration;

/**
 * m180531_000000_config_files migration.
 */
class m180531_000000_config_files extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%enupalbackup_backups}}';

        if (!$this->db->columnExists($table, 'configFileName')) {

            $this->addColumn($table, 'configFileName', $this->text()->after('templateSize'));
        }

        if (!$this->db->columnExists($table, 'configSize')) {

            $this->addColumn($table, 'configSize', $this->string()->after('templateSize'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180531_000000_config_files cannot be reverted.\n";

        return false;
    }
}
