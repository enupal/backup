<?php

namespace enupal\backup\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180530_000000_assets_cleanup migration.
 */
class m180530_000000_assets_cleanup extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%enupalbackup_backups}}';
        $this->alterColumn($table, 'assetFileName', $this->text());

        $rows = (new Query())
            ->select(['id', 'assetFileName'])
            ->from([$table])
            ->all();

        foreach ($rows as $row) {
            $files = [];
            if ($row['assetFileName']){
                $files[] = $row['assetFileName'];
                $filesAsJson = json_encode($files);

                $this->update($table, ['assetFileName' => $filesAsJson], ['id' => $row['id']], [], false);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180530_000000_assets_cleanup cannot be reverted.\n";

        return false;
    }
}
