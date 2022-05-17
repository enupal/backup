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

use enupal\backup\enums\BackupStatus;
use yii\queue\RetryableJobInterface;
use Craft;

/**
 * ProcessPendingBackups job
 */
class ProcessPendingBackups extends BaseJob
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
    protected function defaultDescription(): ?string
    {
        return Backup::t('Checking pending backups');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $this->pendingBackups = $this->pendingBackups ?? Backup::$app->backups->getPendingBackups();
        $totalSteps = count($this->pendingBackups);

        $settings = Backup::$app->settings->getSettings();
        $step = 1;

        try {
            foreach ($this->pendingBackups as $key => $backup) {
                Backup::$app->backups->updateBackupOnComplete($backup);

                if (($backup->backupStatusId == BackupStatus::FINISHED ||
                    $backup->backupStatusId == BackupStatus::ERROR) &&
                    $settings->enableNotification) {
                    Backup::$app->backups->sendNotification($backup);
                }

                $this->setProgress($queue, $step / $totalSteps);
                $step++;
                Craft::info("Enupal Backup: ".$backup->backupId." Status:".$backup->backupStatusId, __METHOD__);
            }

            Backup::$app->backups->checkBackupsAmount();
        } catch (\Exception $e) {
            Craft::error('Error on pending backups: '.$e->getMessage());
        }

        return true;
    }
}