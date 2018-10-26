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
use craft\helpers\UrlHelper;
use yii\base\Component;
use craft\volumes\Local;
use Google_Client;
use Google_Service_Drive;

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

    /**
     * @return \enupal\backup\models\Settings|null
     */
    public function getSettings()
    {
        $backupPlugin = $this->getPlugin();

        return $backupPlugin->getSettings();
    }

    /**
     * @return array|bool|mixed
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @return \craft\base\PluginInterface|null
     */
    public function getPlugin()
    {
        return Craft::$app->getPlugins()->getPlugin('enupal-backup');
    }

    /**
     * @return bool
     */
    public function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @return Google_Client|null
     */
    public function createAccessClient()
    {
        $settings = $this->getSettings();
        $client = null;

        if ($settings->googleDriveClientId && $settings->googleDriveClientSecret){
            $client = new Google_Client();
            $client->setApplicationName('Enupal Backup');
            $client->setScopes(Google_Service_Drive::DRIVE);
            $client->setClientId($settings->googleDriveClientId);
            $client->setClientSecret($settings->googleDriveClientSecret);
            $client->setAccessType('offline');
        }

        return $client;
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function getGoogleDriveRedirectUrl()
    {
        return UrlHelper::siteUrl("enupal-backup/google-drive/auth");
    }
}
