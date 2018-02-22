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
     * @return string
     */
    public function getSettings()
    {
        return Backup::$app->settings->getSettings();
    }

    /**
     * @return string
     */
    public function getSizeFormatted($size)
    {
        return Backup::$app->backups->getSizeFormatted($size);
    }

    /**
     * @return string
     */
    public function getAllPlugins()
    {
        return Backup::$app->settings->getAllPlugins();
    }

    /**
     * @return string
     */
    public function getAllLocalVolumes()
    {
        return Backup::$app->settings->getAllLocalVolumes();
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return Backup::$app->backups->getRandomStr();
    }
}

