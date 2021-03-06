<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\contracts;

use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\Backup;
use Craft;

use craft\helpers\UrlHelper;

class BackupConfig
{
    /**
     * @var string
     */
    private $backupElement;

    /**
     * @var string
     */
    public $config = [];

    /**
     * BackupConfig constructor.
     *
     * @param BackupElement $backup
     *
     * @throws \yii\base\Exception
     */
    public function __construct(BackupElement $backup)
    {
        $this->backupElement = $backup;

        $logPath = Backup::$app->backups->getLogPath($backup->backupId);

        $baseUrl = 'enupal-backup/finished?backupId='.$backup->backupId;

        $webhookUrl = UrlHelper::siteUrl($baseUrl);
        $settings = Backup::$app->settings->getSettings();

        if (Craft::$app->getRequest()->isConsoleRequest){
            $webhookUrl = $settings->primarySiteUrl.'/'.$baseUrl;
        }

        $requestMethod = 'file_get_contents';
        if ($settings->useCurl){
            $requestMethod = 'curl';
        }

        $this->config = [
            'verbose' => true,
            'logging' => [
                [
                    'type' => 'json',
                    'target' => $logPath
                ],
                [
                    'type' => 'webhook',
                    'options' => [
                        'uri' => $webhookUrl,
                        'requestMethod' => $requestMethod
                    ]
                ]
            ],
            'backups' => []
        ];
    }

    /**
     * Add a backup
     *
     * @param BackupType $backup
     */
    public function addBackup(BackupType $backup)
    {
        $this->config['backups'][] = $backup->getBackup();
    }

    /**
     * Returns the backup config
     *
     * @param bool $asJson
     *
     * @return array|string []
     */
    public function getConfig($asJson = false)
    {
        return $asJson ? json_encode($this->config) : $this->config;
    }

}