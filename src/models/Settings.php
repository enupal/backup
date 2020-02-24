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
     * @var bool
     */
    public $useCurl = 0;

    /**
     * @var bool
     */
    public $runJobInBackground = 0;

    /**
     * @var int
     */
    public $compressWithBz2 = 1;

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
    public $deleteLocalBackupAfterUpload = false;

    // Database by default
    /**
     * @var bool
     */
    public $enableDatabase = true;

    /**
     * @var string
     */
    public $excludeData = 'assetindexdata, assettransformindex, cache, sessions, templatecaches, templatecachecriteria, templatecacheelements';

    // Templates
    /**
     * @var bool
     */
    public $enableTemplates = false;

    /**
     * @var bool
     */
    public $excludeTemplates = '';

    // Config FIles
    /**
     * @var bool
     */
    public $enableConfigFiles = false;

    /**
     * @var bool
     */
    public $excludeConfigFiles = 'cpresources,';
    // Logs
    /**
     * @var bool
     */
    public $enableLogs = false;

    /**
     * @var string
     */
    public $excludeLogs = 'enupalbackup,';
    // Web folder
    /**
     * @var bool
     */
    public $enableWebFolder = false;

    /**
     * @var string
     */
    public $excludeWebFolder = 'cpresources,';

    // Local Volumes
    /**
     * @var bool
     */
    public $enableLocalVolumes = false;

    /**
     * @var string|array
     */
    public $volumes;

    // Dropbox 	Api
    /**
     * @var bool
     */
    public $enableDropbox = false;
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

    // Google Drive Api
    /**
     * @var bool
     */
    public $enableGoogleDrive = false;

    /**
     * @var string
     */
    public $googleDriveClientId;

    /**
     * @var string
     */
    public $googleDriveClientSecret;

    /**
     * @var string
     */
    public $googleDriveFolder = "enupalbackup/";

    // Amazon S3 Api

    /**
     * @var bool
     */
    public $enableAmazon = false;

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
    public $amazonUseMultiPartUpload = false;

    // FTP or SFTP
    /**
     * @var bool
     */
    public $enableFtp = false;

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
    public $ftpPort;

    /**
     * @var string
     */
    public $ftpPath = 'enupalbackup/';

    // Softlayer Object Storage
    /**
     * @var bool
     */
    public $enableSos = false;

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
    public $enablePathToTar = false;

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
    public $enablePathToMysqldump = false;

    /**
     * @var string
     */
    public $pathToMysqldump;

    /**
     * @var bool
     */
    public $enablePathToOpenssl = false;

    /**
     * @var string
     */
    public $pathToOpenssl;

    /**
     * @var bool
     */
    public $enablePathToPgdump = false;

    /**
     * @var string
     */
    public $pathToPgdump;

    // Webhook
    /**
     * @var bool
     */
    public $enableWebhook = false;

    /**
     * @var string
     */
    public $webhookSecretKey;
    // security

    /**
     * @var bool
     */
    public $enableOpenssl = false;

    /**
     * @var string
     */
    public $opensslPassword;

    // notification
    /**
     * @var bool
     */
    public $enableNotification = false;

    /**
     * @var string
     */
    public $emailTemplateOverride;

    /**
     * @var string
     */
    public $notificationSubject;

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
     * @var integer
     */
    public $maxExecutionTime = 3600;

    /**
     * @var string
     */
    public $primarySiteUrl;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['backupsAmount', 'integer', 'min' => 1, 'on' => 'general'],
            ['primarySiteUrl', 'required', 'on' => 'general'],
            ['primarySiteUrl', 'url', 'on' => 'general'],
            [['backupsAmount'], 'required', 'on' => 'general'],
            [
                ['enableDatabase', 'enableTemplates', 'enableLocalVolumes', 'enableLogs'],
                BackupFilesValidator::class, 'on' => 'backupFiles'
            ],
            [
                ['googleDriveClientId', 'googleDriveClientSecret'],
                'required', 'on' => 'googledrive'
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