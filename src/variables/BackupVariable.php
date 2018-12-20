<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\variables;

use Craft;
use enupal\backup\Backup;

/**
 * EnupalBackup provides an API for accessing information about sliders. It is accessible from templates via `craft.enupalbackup`.
 *
 */
class BackupVariable
{

    /**
     * @return string
     */
    public function getName()
    {
        $plugin = Craft::$app->plugins->getPlugin('enupal-backup');

        return $plugin->getName();
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        $plugin = Craft::$app->plugins->getPlugin('enupal-backup');

        return $plugin->getVersion();
    }

    /**
     * @return mixed
     */
    public function getFtpTypes()
    {
        $options = [
            'ftp' => 'FTP',
            'sftp' => 'SFTP'
        ];

        return $options;
    }
    
    /**
     * @return \enupal\backup\models\Settings|null
     */
    public function getSettings()
    {
        return Backup::$app->settings->getSettings();
    }

    /**
     * @param $size
     *
     * @return string
     */
    public function getSizeFormatted($size)
    {
        return Backup::$app->backups->getSizeFormatted($size);
    }

    /**
     * @return array
     */
    public function getAllPlugins()
    {
        return Backup::$app->settings->getAllPlugins();
    }

    /**
     * @return array
     */
    public function getAllLocalVolumes()
    {
        return Backup::$app->settings->getAllLocalVolumes();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getSecretKey()
    {
        return Backup::$app->backups->getRandomStr();
    }

    /**
     * @return \Google_Client|null
     * @throws \yii\base\Exception
     */
    public function createAccessClient()
    {
        return Backup::$app->settings->createAccessClient();
    }

    /**
     * @return bool
     * @throws \yii\base\Exception
     */
    public function hasAccessFile()
    {
        return Backup::$app->settings->hasAccessFile();
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function getGoogleDriveRedirectUrl()
    {
        return Backup::$app->settings->getGoogleDriveRedirectUrl();
    }

    /**
     * @return array
     * @throws \yii\base\Exception
     */
    public function getRootFolderOptions()
    {
        return Backup::$app->backups->getGoogleDriveRootFolders();
    }

    /**
     * Process pending backups
     */
    public function processPendingBackups()
    {
        Backup::$app->backups->processPendingBackups();
    }
}

