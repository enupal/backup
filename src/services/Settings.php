<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use craft\volumes\Local;

class Settings extends Component
{

    /**
     * Saves Settings
     *
     * @param string $scenario
     * @param array  $postSettings
     *
     * @return bool
     */
    public function saveSettings(array $postSettings, string $scenario = null): bool
    {
        $backupPlugin = $this->getPlugin();

        $backupPlugin->getSettings()->setAttributes($postSettings, false);

        if ($scenario) {
            $backupPlugin->getSettings()->setScenario($scenario);
        }

        // Validate them, now that it's a model
        if ($backupPlugin->getSettings()->validate() === false) {
            return false;
        }

        $success = Craft::$app->getPlugins()->savePluginSettings($backupPlugin, $postSettings);

        return $success;
    }

    public function getSettings()
    {
        $backupPlugin = $this->getPlugin();

        return $backupPlugin->getSettings();
    }

    public function getDbSettings()
    {
        $settings = (new Query())
            ->select('settings')
            ->from(['{{%plugins}}'])
            ->where(['handle' => 'enupal-backup'])
            ->one();

        $settings = json_decode($settings['settings'], true);

        return $settings;
    }

    public function getAllPlugins()
    {
        $plugins = Craft::$app->getPlugins()->getAllPlugins();
        $response = [];

        foreach ($plugins as $key => $plugin) {
            $response[] = [
                'value' => $plugin->getHandle(),
                'label' => $plugin->name
            ];
        }

        return $response;
    }

    public function getAllLocalVolumes()
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $response = [];

        foreach ($volumes as $key => $volume) {
            if (get_class($volume) == Local::class) {
                $response[] = [
                    'value' => $volume->id,
                    'label' => $volume->name
                ];
            }
        }

        return $response;
    }

    public function getAllLocalVolumesObjects()
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $response = [];

        foreach ($volumes as $key => $volume) {
            if (get_class($volume) == Local::class) {
                $response[] = $volume;
            }
        }

        return $response;
    }

    public function getPlugin()
    {
        return Craft::$app->getPlugins()->getPlugin('enupal-backup');
    }

    public function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }
}
