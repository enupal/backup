<?php

namespace enupal\backup\migrations;

use craft\db\Migration;
use enupal\backup\Backup;


/**
 * m180713_000000_check_pending_backups migration.
 */
class m180713_000000_check_pending_backups extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $pendingBackups = Backup::$app->backups->getPendingBackups();

        foreach ($pendingBackups as $key => $backup) {
            $result = Backup::$app->backups->updateBackupOnComplete($backup);
        }

        Backup::$app->backups->checkBackupsAmount();
        Backup::$app->backups->deleteConfigFile();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180713_000000_check_pending_backups cannot be reverted.\n";

        return false;
    }
}
