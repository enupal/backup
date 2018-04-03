<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\models;

use craft\base\Model;
use enupal\backup\validators\BackupFilesValidator;
use enupal\backup\validators\AssetSourceValidator;
use enupal\backup\validators\DropboxValidator;
use enupal\backup\validators\AmazonValidator;
use enupal\backup\validators\FtpValidator;
use enupal\backup\validators\SoftlayerValidator;
use enupal\backup\validators\NotificationValidator;
use enupal\backup\validators\RecipientsValidator;

class Settings extends Model
{
    // General
    public $pluginNameOverride = '';
    public $backupsAmount = 5;
    public $deleteLocalBackupAfterUpload = 0;
    // Database by default
    public $enableDatabase = 1;
    public $excludeData = 'assetindexdata, assettransformindex, cache, sessions, templatecaches, templatecachecriteria, templatecacheelements';
    // Templates
    public $enableTemplates = 0;
    public $excludeTemplates = 'cpresources,';
    // Logs
    public $enableLogs = 0;
    public $excludeLogs = 'enupalbackup,';
    // Local Volumes
    public $enableLocalVolumes = 0;
    public $volumes = '';
    // Dropbox 	Api
    public $enableDropbox = 0;
    public $dropboxToken = '';
    public $dropboxPath = '/enupalbackup/';
    // Amazon S3 Api
    public $enableAmazon = 0;
    public $amazonKey = '';
    public $amazonSecret = '';
    public $amazonBucket = '';
    public $amazonRegion = '';
    public $amazonPath = '/enupalbackup/';
    public $amazonUseMultiPartUpload = 0;
    // FTP or SFTP
    public $enableFtp = 0;
    public $ftpType = 'ftp';
    public $ftpHost = '';
    public $ftpUser = '';
    public $ftpPassword = '';
    public $ftpPath = 'enupalbackup/';
    // Softlayer Object Storage
    public $enableSos = 0;
    public $sosUser = '';
    public $sosSecret = '';
    public $sosHost = '';
    public $sosContainer = '';
    public $sosPath = '/enupalbackup/';
    // Advanced
    public $enablePathToTar = 0;
    public $pathToTar = '';
    public $enablePathToPhp = '';
    public $pathToPhp = '';
    public $enablePathToMysqldump = '';
    public $pathToMysqldump = '';
    public $enablePathToOpenssl = '';
    public $pathToOpenssl = '';
    public $enablePathToPgdump = '';
    public $pathToPgdump = '';
    // Webhook
    public $enableWebhook = 0;
    public $webhookSecretKey = '';
    // security
    public $enableOpenssl = 0;
    public $opensslPassword = '';
    // notification
    public $enableNotification = '';
    public $notificationRecipients = '';
    public $notificationSenderName = '';
    public $notificationSenderEmail = '';
    public $notificationReplyToEmail = '';

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['backupsAmount', 'integer', 'min' => 1, 'on' => 'general'],
            [
                ['enableDatabase', 'enableTemplates', 'enableLocalVolumes', 'enableLogs'],
                BackupFilesValidator::class, 'on' => 'backupFiles'
            ],
            [
                ['enableLocalVolumes'],
                AssetSourceValidator::class, 'on' => 'backupFiles'
            ],
            [
                ['dropboxToken'],
                DropboxValidator::class, 'on' => 'dropbox'
            ],
            [
                ['enableAmazon'],
                AmazonValidator::class, 'on' => 'amazon'
            ],
            [
                ['enableFtp'],
                FtpValidator::class, 'on' => 'ftp'
            ],
            [
                ['enableSos'],
                SoftlayerValidator::class, 'on' => 'sos'
            ],
            [
                ['enableNotification'],
                NotificationValidator::class, 'on' => 'notification'
            ],
            [
                ['notificationSenderEmail', 'notificationReplyToEmail'],
                'email', 'on' => 'notification', 'when' => function($model) {
                return $model->enableNotification;
            }
            ],
            [
                ['notificationRecipients'],
                RecipientsValidator::class, 'on' => 'notification', 'when' => function($model) {
                return $model->enableNotification;
            }
            ],
            [
                ['opensslPassword'],
                'required', 'on' => 'encrypt', 'when' => function($model) {
                return $model->enableOpenssl;
            }
            ],
            [
                ['webhookSecretKey'],
                'required', 'on' => 'schedule', 'when' => function($model) {
                return $model->enableWebhook;
            }
            ],
        ];
    }
}