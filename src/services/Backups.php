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
use craft\mail\Message;
use enupal\backup\events\NotificationEvent;
use yii\base\Component;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\models\Settings as SettingsModel;
use enupal\backup\enums\BackupStatus;
use enupal\backup\jobs\CreateBackup;
use enupal\backup\contracts\BackupConfig;
use enupal\backup\contracts\DatabaseBackup;
use enupal\backup\contracts\DirectoryBackup;

use craft\helpers\FileHelper;
use craft\errors\ShellCommandException;
use craft\volumes\Local;
use craft\helpers\App as CraftApp;
use mikehaertl\shellcommand\Command as ShellCommand;
use yii\base\Exception;
use craft\models\MailSettings;
use craft\helpers\MailerHelper;

class Backups extends Component
{
    protected $backupRecord;

    /**
     * @event NotificationEvent The event that is triggered before a notification is send
     *
     * Plugins can get notified before a notification email is send
     *
     * ```php
     * use enupal\backup\events\NotificationEvent;
     * use enupal\backup\services\Backups;
     * use yii\base\Event;
     *
     * Event::on(Backups::class, Backups::EVENT_BEFORE_SEND_NOTIFICATION_EMAIL, function(NotificationEvent $e) {
     *      $message = $e->message;
     *     // Do something
     * });
     * ```
     */
    const EVENT_BEFORE_SEND_NOTIFICATION_EMAIL = 'beforeSendNotificationEmail';

    // Bz2 extension file
    const BZ2 = '.bz2';

    public function init()
    {
        if (is_null($this->backupRecord)) {
            $this->backupRecord = new BackupRecord();
        }

        parent::init();
    }

    /**
     * Execute Enupal Backup Job from the service layer
     *
     * @return array
     */
    public function executeEnupalBackup()
    {
        $success = false;
        $response = [
            'success' => $success,
            'message' => 'queued'
        ];

        $queue = Craft::$app->getQueue();

        // Add our CreateBackup job to the queue
        $queue->push(new CreateBackup());

        // Let's try to call queue/run in background
        $queue = Craft::$app->getQueue();
        // Run the queue
        $queue->run();

        return $response;
    }

    /**
     * Returns a Backup model if one is found in the database by id
     *
     * @param int      $id
     * @param int|null $siteId
     *
     * @return array|BackupElement|null
     */
    public function getBackupById(int $id, int $siteId = null)
    {
        if (!$id) {
            return null;
        }

        $query = BackupElement::find();
        $query->id($id);
        $query->siteId($siteId);

        return $query->one();
    }

    /**
     * Returns a Backup model if one is found in the database by backupId
     *
     * @param string   $backupId
     * @param int|null $siteId
     *
     * @return array|BackupElement|null
     */
    public function getBackupByBackupId(string $backupId, int $siteId = null)
    {
        $query = BackupElement::find();
        $query->backupId($backupId);
        $query->siteId($siteId);

        return $query->one();
    }

    /**
     * Returns all Backups
     *
     * @return array|BackupElement[]|null
     */
    public function getAllBackups()
    {
        $query = BackupElement::find();

        return $query->all();
    }

    /**
     * Returns all the Pending backups
     * s
     * @return array|BackupElement[]|null
     */
    public function getPendingBackups()
    {
        $query = BackupElement::find();
        $query->backupStatusId = BackupStatus::RUNNING;

        return $query->all();
    }

    /**
     * @param $backup BackupElement
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function saveBackup(BackupElement $backup)
    {
        if ($backup->id) {
            $backupRecord = BackupRecord::findOne($backup->id);

            if (!$backupRecord) {
                throw new Exception(Backup::t('No Backup exists with the ID “{id}”', ['id' => $backup->id]));
            }
        }

        $backup->validate();

        if ($backup->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->db->beginTransaction();

        try {
            if (Craft::$app->elements->saveElement($backup)) {
                $transaction->commit();
            }
        } catch (\Exception $e) {
            $transaction->rollback();

            throw $e;
        }

        return true;
    }

    /**
     * Performs a Enupal Backup operation.
     *
     * @param BackupElement $backup
     *
     * @return boolean
     * @throws Exception
     * @throws ShellCommandException in case of failure
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function enupalBackup(BackupElement $backup)
    {
        // This may make take a while so..
        CraftApp::maxPowerCaptain();

        $phpbuPath = Craft::getAlias('@enupal/backup/resources');
        $configFile = Backup::$app->backups->getConfigJson($backup);
        // update the the backup to running
        $backup->backupStatusId = BackupStatus::RUNNING;

        if (!$this->saveBackup($backup)) {
            return false;
        }

        if (!is_file($configFile)) {
            throw new Exception("Could not create the Enupal Backup: the config file doesn't exist: ".$configFile);
        }

        // Create the shell command
        $shellCommand = new ShellCommand();
        $command = 'cd'.
            ' '.$phpbuPath;

        $phpPath = $this->getPhpPath();

        $command .= ' && '.$phpPath.' phpbu.phar';
        $command .= ' --configuration='.$configFile;
        $command .= ' --debug';

        $shellCommand->setCommand($command);

        // We have better error messages with exec
        if (function_exists('exec')) {
            $shellCommand->useExec = true;
        }

        $success = $shellCommand->execute();

        if (!$success) {
            throw ShellCommandException::createFromCommand($shellCommand);
        }

        return $success;
    }

    /**
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function installDefaultValues()
    {
        $model = new SettingsModel();
        $settings = $model->getAttributes();

        $primarySite = (new Query())
            ->select(['baseUrl'])
            ->from(['{{%sites}}'])
            ->where(['primary' => 1])
            ->one();

        $primarySiteUrl = Craft::getAlias($primarySite['baseUrl']);

        $settings['primarySiteUrl'] = $primarySiteUrl;

        $settings = json_encode($settings);

        Craft::$app->getDb()->createCommand()->update('{{%plugins}}', [
            'settings' => $settings
        ],
            [
                'handle' => 'enupal-backup'
            ]
        )->execute();
    }

    /**
     * This function creates a default backup and generates the id
     *
     * @return BackupElement
     * @throws \Exception
     * @throws \yii\web\ServerErrorHttpException
     * @throws \Throwable
     */
    public function initializeBackup()
    {
        $info = Craft::$app->getInfo();
        $systemName = FileHelper::sanitizeFilename(
            $info->name,
            [
                'asciiOnly' => true,
                'separator' => '_'
            ]
        );
        $siteName = $systemName ?? 'backup';
        $randomStr = $this->getRandomStr();
        $date = date('YmdHis');

        $backupId = strtolower($siteName.'_'.$date.'_'.$randomStr);
        $backup = new BackupElement();
        $backup->backupId = $backupId;
        $backup->backupStatusId = BackupStatus::STARTED;
        $this->saveBackup($backup);

        // Return the backup if have errors check $backup->getErrors();
        return $backup;
    }

    public function getSizeFormatted($size)
    {
        /*
         * When is php 32bit on Windows for files larger thant 2GB returns negative number
        */
        if ($size < 0) {
            return '> 2 GB';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',').' '.$units[$power];
    }

    /**
     * Check if the log file has content, if so the backup is finished
     *
     * @param BackupElement $backup
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function updateBackupOnComplete(BackupElement $backup)
    {
        // If the log have infomartion the backup is finished
        $logPath = $this->getLogPath($backup->backupId);
        $log = file_exists($logPath) ? file_get_contents($logPath) : null;
        $settings = Backup::$app->settings->getSettings();

        if ($log) {
            // Save the log
            $backup->logMessage = $log;

            // let's update the filenames
            if (is_file($backup->getDatabaseFile())) {
                $backup->databaseSize = filesize($backup->getDatabaseFile());
            }

            if (is_file($backup->getTemplateFile())) {
                $backup->templateSize = filesize($backup->getTemplateFile());
            }

            if (is_file($backup->getWebFile())) {
                $backup->webSize = filesize($backup->getWebFile());
            }

            if (is_file($backup->getLogFile())) {
                $backup->logSize = filesize($backup->getLogFile());
            }
            // asset files
            $assetFiles = [];
            $assetFileSizes = 0;
            $backup->getAssetFiles($assetFiles);
            foreach ($assetFiles as $assetFile) {
                if (is_file($assetFile)) {
                    $assetFileSizes += filesize($assetFile);
                }
            }

            if ($assetFileSizes) {
                $backup->assetSize = $assetFileSizes;
            }
            // config files
            $configFiles = [];
            $configFileSizes = 0;
            $backup->getConfigFiles($configFiles);
            foreach ($configFiles as $configFile) {
                if (is_file($configFile)) {
                    $configFileSizes += filesize($configFile);
                }
            }

            if ($configFileSizes) {
                $backup->configSize = $configFileSizes;
            }

            $backupLog = json_decode($log, true);
            // Backup succesfully
            $backup->backupStatusId = BackupStatus::FINISHED;
            /*
             * We could validate by each backup but let's keep globally for now
            */
            $backup->dropbox = $settings->enableDropbox;
            $backup->aws = $settings->enableAmazon;
            $backup->ftp = $settings->enableFtp;
            $backup->softlayer = $settings->enableSos;

            if (isset($backupLog['timestamp'])) {
                $backup->time = $backupLog['timestamp'];
            }

            // Try to figure out if any sync fails
            if (isset($backupLog['errors']) && $backupLog['errors']) {
                foreach ($backupLog['errors'] as $error) {
                    if (isset($error['msg'])) {
                        // Dropbox
                        if (strpos(strtolower($error['msg']), 'dropbox') !== false) {
                            $backup->dropbox = false;
                        }
                    }

                    if (isset($error['file'])) {
                        // Dropbox
                        if (strpos(strtolower($error['file']), 'dropbox') !== false) {
                            $backup->dropbox = false;
                        }
                    }

                    if (isset($error['message'])) {
                        // Amazon
                        if (strpos(strtolower($error['message']), 'amazon') !== false) {
                            $backup->aws = false;
                        }
                    }

                    if (isset($error['file'])) {
                        // FTP
                        if (strpos(strtolower($error['file']), 'ftp') !== false) {
                            $backup->ftp = false;
                        }
                    }

                    if (isset($error['file'])) {
                        // SOFTLAYER
                        if (strpos(strtolower($error['file']), 'softlayer') !== false) {
                            $backup->softlayer = false;
                        }
                    }
                }
            }

            return $this->saveBackup($backup);
        }

        return false;
    }


    /**
     * Enupal Backup send notification service
     *
     * @param BackupElement $backup
     * @return bool
     * @throws Exception
     * @throws \craft\web\twig\TemplateLoaderException
     */
    public function sendNotification(BackupElement $backup)
    {
        $settings = Backup::$app->settings->getSettings();
        $variables = [];
        $view = Craft::$app->getView();
        $message = new Message();
        $message->setFrom([$settings->notificationSenderEmail => $settings->notificationSenderName]);
        $variables['backup'] = $backup;
        $subject = $view->renderString($settings->notificationSubject, $variables);
        $textBody = $view->renderString("We are happy to inform you that the backup process has been completed. Backup Id: {{backup.backupId}}", $variables);

        $originalPath = $view->getTemplatesPath();

        $template = 'email';
        $templateOverride = null;
        $extensions = ['.html', '.twig'];

        if ($settings->emailTemplateOverride){
            // let's check if the file exists
            $overridePath = $originalPath.DIRECTORY_SEPARATOR.$settings->emailTemplateOverride;
            foreach ($extensions as $extension) {
                if (file_exists($overridePath.$extension)){
                    $templateOverride = $settings->emailTemplateOverride;
                    $template = $templateOverride;
                }
            }
        }

        if (is_null($templateOverride)){
            $defaultTemplate = Craft::getAlias('@enupal/backup/templates/_notification/');
            $view->setTemplatesPath($defaultTemplate);
        }

        $htmlBody = $view->renderTemplate($template, $variables);

        $view->setTemplatesPath($originalPath);

        $message->setSubject($subject);
        $message->setHtmlBody($htmlBody);
        $message->setTextBody($textBody);
        $message->setReplyTo($settings->notificationReplyToEmail);
        // to emails
        $emails =  array_map('trim', explode(',', $settings->notificationRecipients));
        $message->setTo($emails);

        $mailer = Craft::$app->getMailer();

        $event = new NotificationEvent([
            'message' => $message,
        ]);

        $this->trigger(self::EVENT_BEFORE_SEND_NOTIFICATION_EMAIL, $event);

        try {
            $result = $mailer->send($message);
        } catch (\Throwable $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $result = false;
        }

        if (!$result) {
            Craft::error('Unable to send notification email', __METHOD__);
        }

        Craft::info('Notification email sent successfully', __METHOD__);

        return $result;
    }

    /**
     * Generates the config file and create the backup element entry
     *
     * @param $backup BackupElement
     *
     * @return string
     * @throws Exception
     * @throws \Exception
     * @throws \Throwable
     */
    private function getConfigJson(BackupElement $backup)
    {
        $settings = Backup::$app->settings->getSettings();

        $config = new BackupConfig($backup);

        $backupId = $backup->backupId;
        $compress = $this->getCompressType();
        $syncs = $this->getSyncs($backupId);
        $encrypt = $this->getEncrypt();
        $dbFileName = 'database-'.$backupId.'.sql';
        $templateName = 'templates-'.$backupId.$compress;
        $logName = 'logs-'.$backupId.$compress;
        $webFolderName = 'web-'.$backupId.$compress;

        // let's create the Backup Element
        $backup->databaseFileName = $dbFileName;
        $backup->templateFileName = $settings->enableTemplates ? $templateName : null;
        $backup->webFileName = $settings->enableWebFolder ? $webFolderName : null;
        $backup->logFileName = $settings->enableLogs ? $logName : null;

        // Add compression if available
        if (!Backup::$app->settings->isWindows() && $settings->compressWithBz2) {
            // compress database just work on linux
            $backup->databaseFileName .= self::BZ2;
        }

        if ($this->applyCompress($settings)) {
            $backup->webFileName = $backup->webFileName ? $backup->webFileName.self::BZ2 : null;
            $backup->templateFileName = $backup->templateFileName ? $backup->templateFileName.self::BZ2 : null;
            $backup->logFileName = $backup->logFileName ? $backup->logFileName.self::BZ2 : null;
        }

        // Add encrypt extension if enabled
        $backup->databaseFileName = $this->getEncryptFileName($encrypt, $backup->databaseFileName);
        $backup->templateFileName = $this->getEncryptFileName($encrypt, $backup->templateFileName);
        $backup->webFileName = $this->getEncryptFileName($encrypt, $backup->webFileName);
        $backup->logFileName = $this->getEncryptFileName($encrypt, $backup->logFileName);

        if (!empty($encrypt)) {
            $backup->isEncrypted = true;
        }

        if (!$this->saveBackup($backup)) {
            throw new Exception('Unable to create the element record for the Backup: '.$backupId.
                ' Errors: '.json_encode($backup->getErrors()));
        }

        $this->getDatabaseConfigFormat($config, $settings, $dbFileName, $syncs, $encrypt);
        $this->getAssetsConfigFormat($backup, $config, $settings, $syncs, $encrypt);
        $this->getConfigFilesFormat($backup, $config, $settings, $syncs, $encrypt);
        $this->getTemplatesConfigFormat($config, $settings, $templateName, $syncs, $encrypt);
        $this->getWebConfigFormat($config, $settings, $webFolderName, $syncs, $encrypt);
        $this->getLogsConfigFormat($config, $settings, $logName, $syncs, $encrypt);

        $configFile = $this->getConfigPath();

        if (!file_exists($this->getBasePath())) {
            mkdir($this->getBasePath(), 0777, true);
        }

        file_put_contents($configFile, $config->getConfig(true));

        return $configFile;
    }

    /**
     * @param $backup BackupElement
     * @param $config BackupConfig
     * @param $settings
     * @param $syncs
     * @param $encrypt
     * @throws Exception
     */
    private function getConfigFilesFormat($backup, $config, $settings, $syncs, $encrypt)
    {
        // Config Files
        $configFiles = [];

        if ($settings->enableConfigFiles) {
            $configFiles[] = ["key" => "translations", "path" => Craft::$app->getPath()->getSiteTranslationsPath()];
            $configFiles[] = ["key" => "configFolder", "path" => Craft::$app->getPath()->getConfigPath()];
            // Lets copy the composer file  to a temp folder for security reasons
            $tempConfigFolder = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.'enupal-backup-composer'.DIRECTORY_SEPARATOR;
            $tempConfigFile = $tempConfigFolder. 'composer.json';
            $composerFile = CRAFT_BASE_PATH.DIRECTORY_SEPARATOR.'composer.json';
            if (!file_exists($tempConfigFile)) {
                mkdir(dirname($tempConfigFile), 0777, true);
            }
            copy($composerFile, $tempConfigFile);

            $configFiles[] = ["key" => "composerFile", "path" => $tempConfigFolder];
        }
        // Adding the assets
        $configFileNames = [];
        foreach ($configFiles as $key => $configFile) {
            // Check if the path exists
            if (is_dir($configFile['path']) || is_file($configFile['path'])) {
                // So we need store assets files as json could be more than one
                $configName = 'config-'.$configFile['key'].'-'.$backup->backupId.$this->getCompressType();
                $configFileName = $configName;

                if ($this->applyCompress($settings)) {
                    $configFileName = $configFileName ? $configFileName.self::BZ2 : null;
                }

                $configFileName = $this->getEncryptFileName($encrypt, $configFileName);

                $configFilesBackup = new DirectoryBackup();
                $configFilesBackup->name = 'Config'.$key;
                $configFilesBackup->path = $configFile['path'];
                $configFilesBackup->fileName = $configName;
                $configFilesBackup->dirName = $this->getConfigFilesPath();
                $configFilesBackup->syncs = $syncs;
                $configFilesBackup->encrypt = $encrypt;

                $config->addBackup($configFilesBackup);
                $configFileNames[] = $configFileName;
            } else {
                Backup::error('Skipped the config file: '.$configFile['path'].' because the path does not exists');
            }
        }

        $backup->configFileName = json_encode($configFileNames);
    }

    /**
     * @param $backup BackupElement
     * @param $config BackupConfig
     * @param $settings
     * @param $syncs
     * @param $encrypt
     * @throws Exception
     */
    private function getAssetsConfigFormat($backup, $config, $settings, $syncs, $encrypt)
    {
        // ASSETS
        $assets = [];

        if ($settings->enableLocalVolumes) {
            if (is_array($settings->volumes)) {
                foreach ($settings->volumes as $volumeId) {
                    $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
                    $assets[] = $volume;
                }
            } else {
                // get all the local volumes (*)
                $assets = Backup::$app->settings->getAllLocalVolumesObjects();
            }
        }
        // Adding the assets
        $assetFileNames = [];
        foreach ($assets as $key => $asset) {
            // Supports local volumes for now.
            if (get_class($asset) == Local::class) {
                // Check if the path exists
                if (is_dir($asset->getRootPath())) {
                    // So we need store assets files as json could be more than one
                    $assetName = 'assets-'.$asset->handle.'-'.$backup->backupId.$this->getCompressType();
                    $assetFileName = $assetName;

                    if ($this->applyCompress($settings)) {
                        $assetFileName = $assetFileName ? $assetFileName.self::BZ2 : null;
                    }

                    $assetFileName = $this->getEncryptFileName($encrypt, $assetFileName);

                    $assetBackup = new DirectoryBackup();
                    $assetBackup->name = 'Asset'.$asset->id;
                    $assetBackup->path = $asset->getRootPath();
                    $assetBackup->fileName = $assetName;
                    $assetBackup->dirName = $this->getAssetsPath();
                    $assetBackup->syncs = $syncs;
                    $assetBackup->encrypt = $encrypt;

                    $config->addBackup($assetBackup);
                    $assetFileNames[] = $assetFileName;
                } else {
                    Backup::error('Skipped the volume: '.$asset->id.' because the path does not exists');
                }
            }
        }

        $backup->assetFileName = json_encode($assetFileNames);
    }


    /**
     * @param $config BackupConfig
     * @param $settings
     * @param $logName
     * @param $syncs
     * @param $encrypt
     * @throws Exception
     */
    private function getLogsConfigFormat($config, $settings, $logName, $syncs, $encrypt)
    {
        // LOGS
        if ($settings->enableLogs) {
            $baseLogPath = Craft::$app->getPath()->getLogPath();

            $logBackup = new DirectoryBackup();
            $logBackup->name = 'Logs';
            $logBackup->path = $baseLogPath;
            $logBackup->fileName = $logName;
            $logBackup->dirName = $this->getLogsPath();
            $logBackup->syncs = $syncs;
            $logBackup->encrypt = $encrypt;
            $logBackup->exclude = $settings->excludeLogs;

            $config->addBackup($logBackup);
        }
    }

    /**
     * @param $config BackupConfig
     * @param $settings
     * @param $templateName
     * @param $syncs
     * @param $encrypt
     * @throws Exception
     */
    private function getTemplatesConfigFormat($config, $settings, $templateName, $syncs, $encrypt)
    {
        // TEMPLATES
        if ($settings->enableTemplates) {
            $baseTemplatePath = Craft::$app->getPath()->getSiteTemplatesPath();
            $templateBackup = new DirectoryBackup();
            $templateBackup->name = 'Templates';
            $templateBackup->path = $baseTemplatePath;
            $templateBackup->fileName = $templateName;
            $templateBackup->dirName = $this->getTemplatesPath();
            $templateBackup->syncs = $syncs;
            $templateBackup->encrypt = $encrypt;

            if ($settings->excludeTemplates){
                $templateBackup->exclude = $settings->excludeTemplates;
            }

            $config->addBackup($templateBackup);
        }
    }

    /**
     * @param $config BackupConfig
     * @param $settings
     * @param $templateName
     * @param $syncs
     * @param $encrypt
     * @throws Exception
     */
    private function getWebConfigFormat($config, $settings, $webFolderName, $syncs, $encrypt)
    {
        // TEMPLATES
        if ($settings->enableWebFolder) {
            $baseWebPath =  Craft::getAlias('@webroot');
            $baseWebPath = FileHelper::normalizePath($baseWebPath);
            $webFolderBackup = new DirectoryBackup();
            $webFolderBackup->name = 'Web Folder';
            $webFolderBackup->path = $baseWebPath;
            $webFolderBackup->fileName = $webFolderName;
            $webFolderBackup->dirName = $this->getWebFolderPath();
            $webFolderBackup->syncs = $syncs;
            $webFolderBackup->encrypt = $encrypt;

            if ($settings->excludeWebFolder){
                $webFolderBackup->exclude = $settings->excludeWebFolder;
            }

            $config->addBackup($webFolderBackup);
        }
    }

    /**
     * @param $config BackupConfig
     * @param $settings
     * @param $dbFileName
     * @param $syncs
     * @param $encrypt
     */
    private function getDatabaseConfigFormat($config, $settings, $dbFileName, $syncs, $encrypt)
    {
        if ($settings->enableDatabase) {
            $databaseBackup = new DatabaseBackup();
            $databaseBackup->name = 'Database';
            $databaseBackup->fileName = $dbFileName;
            $databaseBackup->syncs = $syncs;
            $databaseBackup->encrypt = $encrypt;

            $config->addBackup($databaseBackup);
        } // DATABASE
    }

    /**
     * Delete config file for security reasons
     */
    public function deleteConfigFile()
    {
        $file = $this->getConfigPath();

        if (file_exists($file)) {
            unlink($file);
        } else {
            // File not found.
            Backup::error(Backup::t('Unable to delete the config file'));
        }
    }

    /**
     * Removes a backup and related files
     *
     * @param BackupElement $backup
     *
     * @return boolean
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function deleteBackup(BackupElement $backup)
    {
        $transaction = Craft::$app->db->beginTransaction();

        try {
            // Delete the Element and Backup
            $success = Craft::$app->elements->deleteElementById($backup->id);

            if (!$success) {
                $transaction->rollback();
                Backup::error("Couldn’t delete Backup on deletebackup service.");

                return false;
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();

            throw $e;
        }

        return true;
    }

    /**
     * Performs a review to check the backups amount allowed
     *
     * @todo should we move this to a job?
     */
    public function checkBackupsAmount()
    {
        // Amount of backups to keep
        $settings = Backup::$app->settings->getSettings();

        try {
            $count = BackupElement::find()->where(['backupStatusId' => BackupStatus::FINISHED])->count();

            if ($count > $settings['backupsAmount']) {
                $totalToDelete = $count - $settings['backupsAmount'];

                if ($totalToDelete) {
                    $backups = BackupElement::find()
                        ->where(['backupStatusId' => BackupStatus::FINISHED])
                        ->limit($totalToDelete)
                        ->orderBy(['enupalbackup_backups.dateCreated' => SORT_ASC])
                        ->all();

                    foreach ($backups as $key => $backup) {
                        $response = Craft::$app->elements->deleteElementById($backup->id);

                        if ($response) {
                            Backup::info('EnupalBackup has deleted the backup Id: '.$backup->backupId);
                        } else {
                            Backup::error('EnupalBackup has failed to delete the backup Id: '.$backup->backupId);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $error = 'Enupal Backup Could not execute the checkBackupsAmount function: '.$e->getMessage().' --Trace: '.json_encode($e->getTrace());

            Backup::error($error);
            return false;
        }

        return true;
    }

    /**
     * @param $encrypt
     * @param $fileName
     *
     * @return string
     */
    private function getEncryptFileName($encrypt, $fileName)
    {
        $enc = $encrypt ? '.enc' : '';

        if ($fileName) {
            $fileName .= $enc;
        }

        return $fileName;
    }

    /**
     * @return array
     */
    private function getEncrypt()
    {
        $encrypt = [];
        $settings = Backup::$app->settings->getSettings();

        if ($settings->enableOpenssl) {
            $encrypt = [
                'type' => 'openssl',
                'options' => [
                    'password' => $settings->opensslPassword,
                    'algorithm' => 'aes-256-cbc'
                ]
            ];

            if ($settings->enablePathToOpenssl && $settings->pathToOpenssl) {
                $encrypt['options']['pathToOpenSSL'] = $settings->pathToOpenssl;
            }
        }

        return $encrypt;
    }

    /**
     * @param $backupId
     *
     * @return array
     */
    private function getSyncs($backupId)
    {
        $syncs = [];
        $settings = Backup::$app->settings->getSettings();
        // DROPBOX
        if ($settings->enableDropbox) {
            $dropbox = [
                'type' => 'dropbox',
                'options' => [
                    'token' => $settings->dropboxToken,
                    'path' => trim($settings->dropboxPath.$backupId)
                ]
            ];

            $syncs[] = $dropbox;
        }
        // AMAZON S3
        if ($settings->enableAmazon) {
            $amazon = [
                'type' => 'amazons3',
                'options' => [
                    'key' => $settings->amazonKey,
                    'secret' => $settings->amazonSecret,
                    'bucket' => $settings->amazonBucket,
                    'region' => $settings->amazonRegion,
                    'path' => trim($settings->amazonPath.$backupId),
                    'useMultiPartUpload' => $settings->amazonUseMultiPartUpload
                ]
            ];

            $syncs[] = $amazon;
        }

        // FTP
        if ($settings->enableFtp) {
            $ftp = [
                'type' => $settings->ftpType,
                'options' => [
                    'host' => $settings->ftpHost,
                    'user' => $settings->ftpUser,
                    'password' => $settings->ftpPassword,
                    'path' => trim($settings->ftpPath.'/'.$backupId)
                ]
            ];

            $syncs[] = $ftp;
        }

        // SOFTLAYER
        if ($settings->enableSos) {
            $softlayer = [
                'type' => 'softlayer',
                'options' => [
                    'host' => $settings->sosHost,
                    'user' => $settings->sosUser,
                    'secret' => $settings->sosSecret,
                    'container' => $settings->sosContainer,
                    'path' => trim($settings->sosPath.'/'.$backupId)
                ]
            ];

            $syncs[] = $softlayer;
        }

        return $syncs;
    }

    /**
     * @return string
     */
    private function getCompressType()
    {
        $compress = '.tar';

        return $compress;
    }

    /**
     * @param $settings
     * @return bool
     */
    public function applyCompress($settings)
    {
        // $settings = Backup::$app->settings->getSettings();
        // Removes  || ($settings->enablePathToTar && $settings->pathToTar because is generating problems in windows lets default to tar
        if (!Backup::$app->settings->isWindows() && $settings->compressWithBz2) {
            return true;
        }

        return false;
    }

    /**
     * @return null|string
     */
    public function getPathToTar()
    {
        $settings = Backup::$app->settings->getSettings();
        $pathToTar = null;

        if ($settings->enablePathToTar) {
            $pathToTar = $settings->pathToTar;
        }

        return $pathToTar;
    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int    $length   How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     *
     * @return string
     * @throws \Exception
     */
    public function getRandomStr($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;

        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getBasePath()
    {
        return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getTempDatabasePath()
    {
        return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackuptemp'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getAssetsPath()
    {
        return $this->getBasePath().'assets'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getConfigFilesPath()
    {
        return $this->getBasePath().'config'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getTemplatesPath()
    {
        return $this->getBasePath().'templates'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getWebFolderPath()
    {
        return $this->getBasePath().'web'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getLogsPath()
    {
        return $this->getBasePath().'logs'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDatabasePath()
    {
        return $this->getBasePath().'databases'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getPluginsPath()
    {
        return $this->getBasePath().'plugins'.DIRECTORY_SEPARATOR;
    }

    /**
     * @param $backupId
     *
     * @return string
     */
    public function getLogPath($backupId)
    {
        $base = Craft::$app->getPath()->getLogPath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;

        return $base.$backupId.'.log';
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getConfigPath()
    {
        $base = $this->getBasePath();
        $configFile = $base.'config.json';

        return $configFile;
    }

    /**
     * @return string
     */
    public function getPhpPath()
    {
        $settings = Backup::$app->settings->getSettings();
        $phpPath = 'php';

        if ($settings->enablePathToPhp && $settings->pathToPhp) {
            $phpPath = $settings->pathToPhp;
        }

        return $phpPath;
    }

    /**
     * @return array
     */
    public function getColorStatuses()
    {
        $colors = [
            BackupStatus::STARTED => 'white',
            BackupStatus::FINISHED => 'green',
            BackupStatus::RUNNING => 'blue',
            BackupStatus::ERROR => 'red',
        ];

        return $colors;
    }

}
