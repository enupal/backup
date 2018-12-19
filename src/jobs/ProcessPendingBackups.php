<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\jobs;

use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use craft\queue\BaseJob;

use yii\queue\RetryableJobInterface;
use Craft;

/**
 * ProcessPendingBackups job
 */
class ProcessPendingBackups extends BaseJob implements RetryableJobInterface
{
    /**
     * Backups to check if they are finished
     *
     * @var BackupElement[]
     */
    public $pendingBackups;

    /**
     * Returns the default description for this job.
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return Backup::t('Checking pending backups');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $this->pendingBackups = $this->pendingBackups ?? Backup::$app->backups->getPendingBackups();
        $totalSteps = count($this->pendingBackups);

        $settings = Backup::$app->settings->getSettings();
        $step = 1;

        try {
            foreach ($this->pendingBackups as $key => $backup) {
                $result = Backup::$app->backups->updateBackupOnComplete($backup);

                if ($result && $settings->enableNotification) {
                    Backup::$app->backups->sendNotification($backup);
                }

                $this->setProgress($queue, $step / $totalSteps);
                $step++;
                Backup::info("Enupal Backup: ".$backup->backupId." Status:".$backup->backupStatusId);
            }

            Backup::$app->backups->checkBackupsAmount();
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTtr()
    {
        $settings = Backup::$app->settings->getSettings();
        $maxExecutionTime = $settings->maxExecutionTime ?? 3600;

        return $maxExecutionTime;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error)
    {
        return ($attempt < 5) && ($error instanceof \Exception);
    }
}