<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller as BaseController;

use enupal\backup\Backup;

class WebhookController extends BaseController
{
    protected array|int|bool $allowAnonymous = true;

    // Disable CSRF validation for the entire controller
    public $enableCsrfValidation = false;

    /**
     * WebHook to listen when a backup process finish up
     *
     * @return mixed
     * @throws \Throwable
     */
    public function actionFinished()
    {
        $backupId = Craft::$app->request->getParam('backupId');
        $backup = Backup::$app->backups->getBackupByBackupId($backupId);
        Craft::info("Request to finish backup: ".$backupId, __METHOD__);

        if ($backup) {
            // we could check just this backup but let's check all pending backups
            Backup::$app->backups->processPendingBackups();
            Backup::$app->backups->deleteConfigFile();
        } else {
            Craft::error("Unable to finish the webhook backup with id: ".$backupId. __METHOD__);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * WebHook to listen when a cronjob call EnupalBackup process
     *
     * @return \yii\web\Response
     * @throws \Throwable
     */
    public function actionSchedule()
    {
        $key = Craft::$app->request->getParam('key');
        $settings = Backup::$app->settings->getSettings();
        $response = [
            'success' => false
        ];

        if ($settings->enableWebhook) {
            if ($key == $settings->webhookSecretKey && $settings->webhookSecretKey) {
                $response = Backup::$app->backups->executeEnupalBackup();
                Backup::$app->backups->processPendingBackups();
            } else {
                Craft::error("Wrong webhook key: ".$key, __METHOD__);
            }
        } else {
            Craft::error("Webhook is disabled", __METHOD__);
        }

        return $this->asJson($response);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\base\Exception
     */
    public function actionGoogleDriveAuth()
    {
        $key = Craft::$app->request->getParam('code');
        $response = [
            'success' => false
        ];

        if ($key) {
            $client = Backup::$app->settings->createAccessClient();
            $accessToken = $client->fetchAccessTokenWithAuthCode($key);

            if (!isset($accessToken['error_description'])){
                $accessFile = Backup::$app->backups->getGoogleDriveAccessPath();

                // Store the credentials to disk.
                $basePath = Backup::$app->backups->getBasePath();
                if (!file_exists($basePath)) {
                    mkdir($basePath, 0777, true);
                }

                file_put_contents($accessFile, json_encode($accessToken));

                Craft::$app->getSession()->setNotice(Backup::t('Google Drive successfully added'));

            }else{
                Craft::$app->getSession()->setError(Backup::t('Couldn’t save google drive access token: '.$accessToken['error_description']));
            }
        } else {
            Craft::$app->getSession()->setError(Backup::t('Empty code response from google drive'));
        }

        Craft::$app->getView()->setTemplateMode('cp');

        return $this->renderTemplate('enupal-backup/settings/googledrive');
    }
}
