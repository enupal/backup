<?php

namespace enupal\backup\migrations;

use craft\db\Migration;
use Craft;
use craft\db\Query;

/**
 * m180709_000000_primary_site_url migration.
 */
class m180709_000000_primary_site_url extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%plugins}}';

        $backup = (new Query())
            ->select(['id', 'settings'])
            ->from([$table])
            ->where(['handle' => 'enupal-backup'])
            ->one();

        $primarySite = (new Query())
            ->select(['baseUrl'])
            ->from(['{{%sites}}'])
            ->where(['primary' => 1])
            ->one();

        $primarySiteUrl = Craft::getAlias($primarySite['baseUrl']);

        $settings = json_decode($backup['settings'], true);

        $settings['primarySiteUrl'] = $primarySiteUrl;

        $settingsAsJson = json_encode($settings);

        $this->update($table, ['settings' => $settingsAsJson], ['id' => $backup['id']], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180709_000000_primary_site_url cannot be reverted.\n";

        return false;
    }
}
