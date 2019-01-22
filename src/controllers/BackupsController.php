<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;
use yii\web\NotFoundHttpException;
use yii\base\Exception;
use craft\helpers\FileHelper;
use ZipArchive;

use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup;

class BackupsController extends BaseController
{
    /**
     * Download backup
     *
     * @return \yii\web\Response|\yii\console\Response|static
     * @throws NotFoundHttpException
     * @throws \Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDownload()
    {
        $this->requirePostRequest();
        $backupId = Craft::$app->getRequest()->getRequiredBodyParam('backupId');
        $type = Craft::$app->getRequest()->getRequiredBodyParam('type');
        $backup = Backup::$app->backups->getBackupById($backupId);

        if ($backup && $type) {
            $filePath = null;

            switch ($type) {
                case 'all':

                    $zipPath = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.$backup->backupId.'.zip';

                    $zip = $this->getZip($zipPath);

                    if ($backup->getDatabaseFile()) {
                        $filename = pathinfo($backup->getDatabaseFile(), PATHINFO_BASENAME);

                        $zip->addFile($backup->getDatabaseFile(), $filename);
                    }

                    if ($backup->getTemplateFile()) {
                        $filename = pathinfo($backup->getTemplateFile(), PATHINFO_BASENAME);

                        $zip->addFile($backup->getTemplateFile(), $filename);
                    }

                    if ($backup->getWebFile()) {
                        $filename = pathinfo($backup->getWebFile(), PATHINFO_BASENAME);

                        $zip->addFile($backup->getWebFile(), $filename);
                    }

                    $assetFiles = [];
                    $backup->getAssetFiles($assetFiles);
                    foreach ($assetFiles as $assetFile) {
                        if (is_file($assetFile)) {
                            $filename = pathinfo($assetFile, PATHINFO_BASENAME);
                            $zip->addFile($assetFile, $filename);
                        }
                    }

                    $configFiles = [];
                    $backup->getConfigFiles($configFiles);
                    foreach ($configFiles as $configFile) {
                        if (is_file($configFile)) {
                            $filename = pathinfo($configFile, PATHINFO_BASENAME);
                            $zip->addFile($configFile, $filename);
                        }
                    }

                    if ($backup->getLogFile()) {
                        $filename = pathinfo($backup->getLogFile(), PATHINFO_BASENAME);

                        $zip->addFile($backup->getLogFile(), $filename);
                    }

                    $zip->close();

                    $filePath = $zipPath;
                    break;
                case 'database':
                    $filePath = $backup->getDatabaseFile();
                    break;
                case 'template':
                    $filePath = $backup->getTemplateFile();
                    break;
                case 'webFolder':
                    $filePath = $backup->getWebFile();
                    break;
                case 'logs':
                    $filePath = $backup->getLogFile();
                    break;
                case 'asset':
                    $assetFiles = [];
                    $backup->getAssetFiles($assetFiles);

                    $zipPath = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.'assets-'.$backup->backupId.'.zip';
                    $addAssetPath = false;

                    $zip = $this->getZip($zipPath);

                    foreach ($assetFiles as $assetFile) {
                        if (is_file($assetFile)) {
                            $filename = pathinfo($assetFile, PATHINFO_BASENAME);
                            $zip->addFile($assetFile, $filename);
                            $addAssetPath = true;
                        }
                    }

                    $zip->close();

                    if ($addAssetPath){
                        $filePath = $zipPath;
                    }
                    break;
                case 'config':
                    $configFiles = [];
                    $backup->getConfigFiles($configFiles);

                    $zipPath = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.'config-files-'.$backup->backupId.'.zip';
                    $addConfigPath = false;

                    $zip = $this->getZip($zipPath);

                    foreach ($configFiles as $configFile) {
                        if (is_file($configFile)) {
                            $filename = pathinfo($configFile, PATHINFO_BASENAME);
                            $zip->addFile($configFile, $filename);
                            $addConfigPath = true;
                        }
                    }

                    $zip->close();

                    if ($addConfigPath){
                        $filePath = $zipPath;
                    }
                    break;
            }

            if (!is_file($filePath)) {
                throw new NotFoundHttpException(Backup::t('Invalid backup name: {filename}', [
                    'filename' => $filePath
                ]));
            }
        } else {
            throw new NotFoundHttpException(Backup::t('Invalid backup parameters'));
        }

        // Ajax call from element index
        if (Craft::$app->request->getAcceptsJson()) {
            return $this->asJson([
                'backupFile' => $filePath
            ]);
        }

        return Craft::$app->getResponse()->sendFile($filePath);
    }

    /**
     * @return \yii\web\Response
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRun()
    {
        $this->requirePostRequest();

        Backup::$app->backups->executeEnupalBackup();

        Craft::$app->getSession()->setNotice(Backup::t('Enupal Backup: running'));

        return $this->redirectToPostedUrl();
    }

    /**
     * View a Backup.
     *
     * @param int|null $backupId The backup's ID
     *
     * @return \yii\web\Response
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function actionViewBackup(int $backupId)
    {
        // Get the Backup
        $backup = Backup::$app->backups->getBackupById($backupId);

        if (is_null($backup)) {
            throw new NotFoundHttpException(Backup::t('Backup not found'));
        }

        if ($backup->backupStatusId == BackupStatus::RUNNING) {
            Backup::$app->backups->updateBackupOnComplete($backup);
        }
        if (!is_file($backup->getDatabaseFile())) {
            $backup->databaseFileName = null;
        }

        if (!is_file($backup->getTemplateFile())) {
            $backup->templateFileName = null;
        }

        if (!is_file($backup->getWebFile())) {
            $backup->webFileName = null;
        }

        if (!is_file($backup->getLogFile())) {
            $backup->logFileName = null;
        }

        $assetFiles = [];
        $backup->getAssetFiles($assetFiles);

        $assetFileName = null;
        foreach ($assetFiles as $assetFile) {
            if (is_file($assetFile)) {
                // If we have a least one file we should allow download it.
                $assetFileName = "assets";
                break;
            }
        }

        $backup->assetFileName = $assetFileName;

        $configFiles = [];
        $backup->getConfigFiles($configFiles);

        $configFileName = null;
        foreach ($configFiles as $configFile) {
            if (is_file($configFile)) {
                // If we have a least one file we should allow download it.
                $configFileName = "config";
                break;
            }
        }

        $backup->configFileName = $configFileName;

        $variables = [];

        $variables['backup'] = $backup;

        $logPath = Backup::$app->backups->getLogPath($backup->backupId);

        if (is_file($logPath)) {
            $log = file_get_contents($logPath);
            $variables['log'] = $log;
        }

        $variables['syncErrors'] = $this->getSyncErrors($backup->logMessage);

        return $this->renderTemplate('enupal-backup/backups/_viewBackup', $variables);
    }

    /**
     * Delete a backup.
     *
     * @return \yii\web\Response
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteBackup()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $backupId = $request->getRequiredBodyParam('id');
        $backup = Backup::$app->backups->getBackupById($backupId);

        // @TODO - handle errors
        $success = Backup::$app->backups->deleteBackup($backup);

        if ($success) {
            Craft::$app->getSession()->setNotice(Backup::t('Backup deleted.'));
        } else {
            Craft::$app->getSession()->setNotice(Backup::t('Couldn’t delete backup.'));
        }

        return $this->redirectToPostedUrl($backup);
    }

    /**
     * @param $zipPath
     * @return ZipArchive
     * @throws \Exception
     */
    private function getZip($zipPath)
    {
        if (is_file($zipPath)) {
            try {
                FileHelper::unlink($zipPath);
            } catch (\Exception $e) {
                Backup::error("Unable to delete the file \"{$zipPath}\": ".$e->getMessage());
            }
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \Exception('Cannot create zip at '.$zipPath);
        }

        return $zip;
    }

    /**
     * @param $log
     * @return array
     */
    private function getSyncErrors($log)
    {
        $backupLog = json_decode($log, true);

        $errorsMessages = [
            'dropbox' => 'dropbox',
            'amazon' => 'amazon',
            'googleDrive' => 'googledrive',
            'ftp' => 'ftp',
            'softlayer' => 'softlayer',
        ];

        $syncErrors = [];

        // Try to figure out if any sync fails
        if (isset($backupLog['errors']) && $backupLog['errors']) {
            foreach ($backupLog['errors'] as $error) {
                foreach ($errorsMessages as $service => $errorMessage) {
                    $finalError = '';

                    if (isset($error['msg'])) {
                        if (strpos(strtolower($error['msg']), $errorMessage) !== false) {
                            $finalError .= $error['msg'];
                        }
                    }
                    if (isset($error['message'])) {
                        if (strpos(strtolower($error['message']), $errorMessage) !== false) {
                            $finalError .= $error['message'];
                        }
                    }
                    if (isset($error['file'])) {
                        if (strpos(strtolower($error['file']), $errorMessage) !== false) {
                            $finalError .= $error['file'] . ' * Line: '. $error['line'] ?? '';
                        }
                    }

                    if ($finalError){
                        $syncErrors[$service]['msg'] = $finalError;
                    }
                }
            }
        }

        return $syncErrors;
    }
}
