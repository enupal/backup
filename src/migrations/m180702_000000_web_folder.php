<?php

namespace enupal\backup\migrations;

use craft\db\Migration;

/**
 * m180702_000000_web_folder migration.
 */
class m180702_000000_web_folder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%enupalbackup_backups}}';

        if (!$this->db->columnExists($table, 'webFileName')) {
            $this->addColumn($table, 'webFileName', $this->text()->after('templateSize'));
        }

        if (!$this->db->columnExists($table, 'webSize')) {

            $this->addColumn($table, 'webSize', $this->string()->after('templateSize'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180702_000000_web_folder cannot be reverted.\n";

        return false;
    }
}
