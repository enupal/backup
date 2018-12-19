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
use enupal\backup\models\Settings as SettingsModel;
use enupal\backup\Backup;

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
        $pluginSettings =  (new Query())
            ->select(['settings'])
            ->from(['{{%plugins}}'])
            ->where(['handle' => 'enupal-backup'])
            ->one();

        $settings = new SettingsModel();

        $settings->setAttributes(json_decode($pluginSettings['settings'], true), false);

        return $settings;
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
     * @throws \yii\base\Exception
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
            $client->setRedirectUri($this->getGoogleDriveRedirectUrl());
            $client->setAccessType('offline');
        }

        return $client;
    }

    /**
     * @return Google_Client|null
     * @throws \yii\base\Exception
     */
    public function getGoogleDriveClient()
    {
        $settings = $this->getSettings();
        $client = null;

        if ($settings->googleDriveClientId && $settings->googleDriveClientSecret){
            $client = $this->createAccessClient();

            // Load previously authorized token from a file, if it exists.
            $accessFile = Backup::$app->backups->getGoogleDriveAccessPath();
            if (file_exists($accessFile)) {
                $accessToken = json_decode(file_get_contents($accessFile), true);
                $client->setAccessToken($accessToken);
                $client = $this->checkRefreshToken($client, $accessFile, $accessToken);
            }
        }

        return $client;
    }

    /**
     * @param $client
     * @param $accessFile
     * @param $accessToken
     * @return mixed
     */
    public function checkRefreshToken($client, $accessFile, $accessToken)
    {
        if ($client->isAccessTokenExpired() && isset($accessToken['refresh_token'])){
            $client->refreshToken($accessToken['refresh_token']);
            $newAccessToken = $client->getAccessToken();

            file_put_contents($accessFile, json_encode($newAccessToken));
        }

        return $client;
    }

    /**
     * @return Google_Service_Drive
     * @throws \yii\base\Exception
     */
    public function getGoogleDriveService()
    {
        $client = $this->getGoogleDriveClient();
        $service = new Google_Service_Drive($client);

        return $service;
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function getGoogleDriveRedirectUrl()
    {
        return UrlHelper::siteUrl("enupal-backup/google-drive/auth");
    }

    /**
     * @return bool
     * @throws \yii\base\Exception
     */
    public function hasAccessFile()
    {
        $accessFile = Backup::$app->backups->getGoogleDriveAccessPath();

        if (file_exists($accessFile)){
            $accessValue = file_get_contents($accessFile);
            $result = json_decode($accessValue, true);
            if (isset($result['access_token'])){
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function getSecretGoolgeDriveFile()
    {
        $settings = $this->getSettings();
        $secretFile = Backup::$app->backups->getGoogleDriveSecretPath();

        $secret = [
            'installed' => [
                'client_id' => $settings->googleDriveClientId,
                'client_secret' => $settings->googleDriveClientSecret,
            ]
        ];

        $basePath = Backup::$app->backups->getBasePath();
        if (!file_exists($basePath)) {
            mkdir($basePath, 0777, true);
        }

        file_put_contents($secretFile, json_encode($secret));

        return $secretFile;
    }
}
