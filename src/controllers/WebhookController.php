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

use enupal\backup\Backup;

class WebhookController extends BaseController
{
    protected $allowAnonymous = ['actionFinished', 'actionSchedule'];

    /**
     * Webhook to listen when a backup process finish up
     *
     * @return \yii\web\Response
     */
    public function actionFinished()
    {
        $backupId = Craft::$app->request->getParam('backupId');
        $backup = Backup::$app->backups->getBackupByBackupId($backupId);
        $settings = Backup::$app->settings->getSettings();
        Backup::info("Request to finish backup: ".$backupId);

        if ($backup) {
            // we could check just this backup but let's check all pending backups
            $pendingBackups = Backup::$app->backups->getPendingBackups();

            foreach ($pendingBackups as $key => $backup) {
                $result = Backup::$app->backups->updateBackupOnComplete($backup);
                // let's send a notification
                if ($result && $settings->enableNotification) {
                    Backup::$app->backups->sendNotification($backup);
                }

                Backup::info("EnupalBackup: ".$backup->backupId." Status:".$backup->backupStatusId." (webhook)");
            }

            Backup::$app->backups->checkBackupsAmount();
            Backup::$app->backups->deleteConfigFile();
        } else {
            Backup::error("Unable to finish the webhook backup with id: ".$backupId);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Webhook to listen when a cronjob call EnupalBackup process
     *
     * @return \yii\web\Response
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
            } else {
                Backup::error("Wrong webhook key: ".$key);
            }
        } else {
            Backup::error("Webhook is disabled");
        }

        return $this->asJson($response);
    }
}
