<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\services;

use Craft;
use yii\base\Component;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\models\Settings;
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

        // Add our CreateBackup job to the queue
        Craft::$app->queue->push(new CreateBackup());

        // if is Linux try to call queue/run in background
        if (!Backup::$app->settings->isWindows()) {
            // listen by console
            $shellCommand = new ShellCommand();
            $craftPath = CRAFT_BASE_PATH;
            $phpPath = Backup::$app->backups->getPhpPath();
            $command = 'cd'.
                ' '.$craftPath;
            $command .= ' && '.$phpPath.
                ' craft'.
                ' queue/run';
            // linux
            $command .= ' > /dev/null 2&1 &';
            // windows does not work
            //$command .= ' 1>> NUL 2>&1';
            $shellCommand->setCommand($command);

            // We have better error messages with exec
            if (function_exists('exec')) {
                $shellCommand->useExec = true;
            }

            $success = $shellCommand->execute();

            $response = [
                'success' => $success,
                'message' => 'running'
            ];
        }

        return $response;
    }

    /**
     * Returns a Backup model if one is found in the database by id
     *
     * @param int $id
     * @param int $siteId
     *
     * @return null|BackupElement
     */
    public function getBackupById(int $id, int $siteId = null)
    {
        $query = BackupElement::find();
        $query->id($id);
        $query->siteId($siteId);

        return $query->one();
    }

    /**
     * Returns a Backup model if one is found in the database by backupId
     *
     * @param string $backupId
     * @param int    $siteId
     *
     * @return null|BackupElement
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
     * @return null|BackupElement[]
     */
    public function getAllBackups()
    {
        $query = BackupElement::find();

        return $query->all();
    }

    /**
     * Returns all the Pending backups
     *
     * @return null|BackupElement[]
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
        $model = new Settings();
        $settings = $model->getAttributes();

        $settings = json_encode($settings);
        $affectedRows = Craft::$app->getDb()->createCommand()->update('{{%plugins}}', [
            'settings' => $settings
        ],
            [
                'handle' => 'enupal-backup'
            ]
        )->execute();

        $emailPath = Craft::getAlias('@enupal/backup/templates/_notification');
        $templateEmailPath = Craft::$app->path->getSiteTemplatesPath();
        $templateEmailPath = FileHelper::normalizePath($templateEmailPath."/_enupalBackupNotification");
        FileHelper::copyDirectory($emailPath, $templateEmailPath);
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

            if (is_file($backup->getLogFile())) {
                $backup->logSize = filesize($backup->getLogFile());
            }

            if (is_file($backup->getAssetFile())) {
                $backup->assetSize = filesize($backup->getAssetFile());
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
                            $backup->dropbox = 0;
                        }
                    }

                    if (isset($error['message'])) {
                        // Amazon
                        if (strpos(strtolower($error['message']), 'amazon') !== false) {
                            $backup->aws = 0;
                        }
                    }

                    if (isset($error['file'])) {
                        // FTP
                        if (strpos(strtolower($error['file']), 'ftp') !== false) {
                            $backup->ftp = 0;
                        }
                    }

                    if (isset($error['file'])) {
                        // SOFTLAYER
                        if (strpos(strtolower($error['file']), 'softlayer') !== false) {
                            $backup->softlayer = 0;
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
     * @param $backup BackupElement
     *
     * @return bool
     */
    public function sendNotification(BackupElement $backup)
    {
        $settings = new MailSettings();
        $backupSettings = Backup::$app->settings->getSettings();
        $templatePath = '_enupalBackupNotification/email';
        $emailSettings = Craft::$app->getSystemSettings()->getSettings('email');

        $settings->fromEmail = $backupSettings->notificationSenderEmail;
        $settings->fromName = $backupSettings->notificationSenderName;
        $settings->template = $templatePath;
        $settings->transportType = $emailSettings['transportType'];
        $settings->transportSettings = $emailSettings['transportSettings'];

        $mailer = MailerHelper::createMailer($settings);

        $emails = explode(",", $backupSettings->notificationRecipients);

        try {
            $emailSent = $mailer
                ->composeFromKey('enupal_backup_notification', ['backup' => $backup])
                ->setTo($emails)
                ->send();
        } catch (\Throwable $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $emailSent = false;
        }

        if ($emailSent) {
            Backup::info('Notification Email sent successfully!');
        } else {
            Backup::error('There was an error sending the Notification email');
        }

        return $emailSent;
    }

    /**
     * @param $backup BackupElement
     *                Generetates the config file and create the backup element entry
     *
     * @return string
     * @throws Exception
     * @throws \Exception
     * @throws \Throwable
     */
    private function getConfigJson(BackupElement $backup)
    {
        $logPath = $this->getLogPath($backup->backupId);
        $settings = Backup::$app->settings->getSettings();

        $config = new BackupConfig($backup);

        $backupId = $backup->backupId;
        $compress = $this->getCompressType();
        $syncs = $this->getSyncs($backupId);
        $encrypt = $this->getEncrypt();
        $dbFileName = 'database-'.$backupId.'.sql';
        $assetName = 'assets-'.$backupId.$compress;
        $templateName = 'templates-'.$backupId.$compress;
        $logName = 'logs-'.$backupId.$compress;
        $pathToTar = $this->getPathToTar();
        $backups = [];

        // let's create the Backup Element
        $backup->databaseFileName = $dbFileName;

        $backup->assetFileName = $settings->enableLocalVolumes ? $assetName : null;
        $backup->templateFileName = $settings->enableTemplates ? $templateName : null;
        $backup->logFileName = $settings->enableLogs ? $logName : null;

        // Add compression if available
        if (!Backup::$app->settings->isWindows()) {
            // compress database just work on linux
            $backup->databaseFileName .= '.bz2';
        }

        if ($this->applyCompress()) {
            $backup->assetFileName = $backup->assetFileName ? $backup->assetFileName.'.bz2' : null;
            $backup->templateFileName = $backup->templateFileName ? $backup->templateFileName.'.bz2' : null;
            $backup->logFileName = $backup->logFileName ? $backup->logFileName.'.bz2' : null;
        }

        // Add encrypt extension if enabled
        $backup->databaseFileName = $this->getEncryptFileName($encrypt, $backup->databaseFileName);
        $backup->templateFileName = $this->getEncryptFileName($encrypt, $backup->templateFileName);
        $backup->assetFileName = $this->getEncryptFileName($encrypt, $backup->assetFileName);
        $backup->logFileName = $this->getEncryptFileName($encrypt, $backup->logFileName);

        if ($encrypt) {
            $backup->isEncrypted = 1;
        }

        if (!$this->saveBackup($backup)) {
            throw new Exception('Unable to create the element record for the Backup: '.$backupId.
                ' Errors: '.json_encode($backup->getErrors()));
        }

        // DATABASE
        if ($settings->enableDatabase) {
            $databaseBackup = new DatabaseBackup();
            $databaseBackup->name = 'Database';
            $databaseBackup->fileName = $dbFileName;
            $databaseBackup->syncs = $syncs;
            $databaseBackup->encrypt = $encrypt;

            $config->addBackup($databaseBackup);
        }
        // END DATABASE

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
        foreach ($assets as $key => $asset) {
            // Supports local volumes for now.
            if (get_class($asset) == Local::class) {
                // Check if the path exists
                if (is_dir($asset->path)) {
                    $assetBackup = new DirectoryBackup();
                    $assetBackup->name = 'Asset'.$asset->id;
                    $assetBackup->path = $asset->path;
                    $assetBackup->fileName = $assetName;
                    $assetBackup->dirName = $this->getAssetsPath();
                    $assetBackup->syncs = $syncs;
                    $assetBackup->encrypt = $encrypt;

                    $config->addBackup($assetBackup);
                } else {
                    Backup::error('Skipped the volume: '.$asset->id.' because the path does not exists');
                }
            }
        }

        // TEMPLATES
        if ($settings->enableTemplates) {
            $baseTemplatePath = Craft::$app->getPath()->getSiteTemplatesPath();
            //@todo - add exclude templates
            $templateBackup = new DirectoryBackup();
            $templateBackup->name = 'Templates';
            $templateBackup->path = $baseTemplatePath;
            $templateBackup->fileName = $templateName;
            $templateBackup->dirName = $this->getTemplatesPath();
            $templateBackup->syncs = $syncs;
            $templateBackup->encrypt = $encrypt;

            $config->addBackup($templateBackup);
        }

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

        $configFile = $this->getConfigPath();

        if (!file_exists($this->getBasePath())) {
            mkdir($this->getBasePath(), 0777, true);
        }

        file_put_contents($configFile, $config->getConfig(true));

        return $configFile;
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

            $totalToDelete = 0;

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

    private function getEncryptFileName($encrypt, $fileName)
    {
        $enc = $encrypt ? '.enc' : '';

        if ($fileName) {
            $fileName .= $enc;
        }

        return $fileName;
    }

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

    private function getCompressType()
    {
        $compress = '.tar';

        return $compress;
    }

    public function applyCompress()
    {
        $settings = Backup::$app->settings->getSettings();

        if (!Backup::$app->settings->isWindows() || ($settings->enablePathToTar && $settings->pathToTar)) {
            return true;
        }

        return false;
    }

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

    public function getBasePath()
    {
        return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;
    }

    public function getTempDatabasePath()
    {
        return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackuptemp'.DIRECTORY_SEPARATOR;
    }

    public function getAssetsPath()
    {
        return $this->getBasePath().'assets'.DIRECTORY_SEPARATOR;
    }

    public function getTemplatesPath()
    {
        return $this->getBasePath().'templates'.DIRECTORY_SEPARATOR;
    }

    public function getLogsPath()
    {
        return $this->getBasePath().'logs'.DIRECTORY_SEPARATOR;
    }

    public function getDatabasePath()
    {
        return $this->getBasePath().'databases'.DIRECTORY_SEPARATOR;
    }

    public function getPluginsPath()
    {
        return $this->getBasePath().'plugins'.DIRECTORY_SEPARATOR;
    }

    public function getLogPath($backupId)
    {
        $base = Craft::$app->getPath()->getLogPath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;

        return $base.$backupId.'.log';
    }

    public function getConfigPath()
    {
        $base = $this->getBasePath();
        $configFile = $base.'config.json';

        return $configFile;
    }

    public function getPhpPath()
    {
        $settings = Backup::$app->settings->getSettings();
        $phpPath = 'php';

        if ($settings->enablePathToPhp && $settings->pathToPhp) {
            $phpPath = $settings->pathToPhp;
        }

        return $phpPath;
    }

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
