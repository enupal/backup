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
    /**
     * @var string
     */
    public $pluginNameOverride;

    /**
     * @var integer
     */
    public $backupsAmount = 5;

    /**
     * @var bool
     */
    public $deleteLocalBackupAfterUpload = 0;

    // Database by default
    /**
     * @var bool
     */
    public $enableDatabase = 1;

    /**
     * @var string
     */
    public $excludeData = 'assetindexdata, assettransformindex, cache, sessions, templatecaches, templatecachecriteria, templatecacheelements';

    // Templates
    /**
     * @var bool
     */
    public $enableTemplates = 0;

    /**
     * @var bool
     */
    public $excludeTemplates = 'cpresources,';
    // Logs
    /**
     * @var bool
     */
    public $enableLogs = 0;

    /**
     * @var string
     */
    public $excludeLogs = 'enupalbackup,';

    // Local Volumes
    /**
     * @var bool
     */
    public $enableLocalVolumes = 0;

    /**
     * @var string
     */
    public $volumes;

    // Dropbox 	Api
    /**
     * @var bool
     */
    public $enableDropbox = 0;
    /**
     * @var bool
     */

    /**
     * @var string
     */
    public $dropboxToken;

    /**
     * @var string
     */
    public $dropboxPath = '/enupalbackup/';
    // Amazon S3 Api

    /**
     * @var bool
     */
    public $enableAmazon = 0;

    /**
     * @var string
     */
    public $amazonKey;

    /**
     * @var string
     */
    public $amazonSecret;

    /**
     * @var string
     */
    public $amazonBucket;

    /**
     * @var string
     */
    public $amazonRegion;

    /**
     * @var string
     */
    public $amazonPath = '/enupalbackup/';

    /**
     * @var bool
     */
    public $amazonUseMultiPartUpload = 0;

    // FTP or SFTP
    /**
     * @var bool
     */
    public $enableFtp = 0;

    /**
     * @var string
     */
    public $ftpType = 'ftp';

    /**
     * @var string
     */
    public $ftpHost;

    /**
     * @var string
     */
    public $ftpUser;

    /**
     * @var string
     */
    public $ftpPassword;

    /**
     * @var string
     */
    public $ftpPath = 'enupalbackup/';

    // Softlayer Object Storage
    /**
     * @var bool
     */
    public $enableSos = 0;

    /**
     * @var string
     */
    public $sosUser;

    /**
     * @var string
     */
    public $sosSecret;

    /**
     * @var string
     */
    public $sosHost;

    /**
     * @var string
     */
    public $sosContainer;

    /**
     * @var string
     */
    public $sosPath = '/enupalbackup/';

    // Advanced
    /**
     * @var bool
     */
    public $enablePathToTar = 0;

    /**
     * @var string
     */
    public $pathToTar;

    /**
     * @var string
     */
    public $enablePathToPhp;

    /**
     * @var string
     */
    public $pathToPhp;

    /**
     * @var bool
     */
    public $enablePathToMysqldump = 0;

    /**
     * @var string
     */
    public $pathToMysqldump;

    /**
     * @var bool
     */
    public $enablePathToOpenssl = 0;

    /**
     * @var string
     */
    public $pathToOpenssl;

    /**
     * @var bool
     */
    public $enablePathToPgdump = 0;

    /**
     * @var string
     */
    public $pathToPgdump;

    // Webhook
    /**
     * @var bool
     */
    public $enableWebhook = 0;

    /**
     * @var string
     */
    public $webhookSecretKey;
    // security

    /**
     * @var bool
     */
    public $enableOpenssl = 0;

    /**
     * @var string
     */
    public $opensslPassword;

    // notification
    /**
     * @var bool
     */
    public $enableNotification = 0;

    /**
     * @var string
     */
    public $notificationRecipients;

    /**
     * @var string
     */
    public $notificationSenderName;

    /**
     * @var string
     */
    public $notificationSenderEmail;

    /**
     * @var string
     */
    public $notificationReplyToEmail;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['backupsAmount', 'integer', 'min' => 1, 'on' => 'general'],
            ['backupsAmount', 'required', 'on' => 'general'],
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