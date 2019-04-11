<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\jobs;

use enupal\backup\Backup;
use craft\queue\BaseJob;
use enupal\backup\elements\Backup as BackupElement;

use enupal\backup\enums\BackupStatus;
use Craft;

/**
 * CreateBackup job
 */
class CreateBackup extends BaseJob
{
    /**
     * @var BackupElement|null
     */
    private $_backup;

    /**
     * Returns the default description for this job.
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return Backup::t('Creating backup');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $totalSteps = 2;
        $this->_backup = Backup::$app->backups->initializeBackup();

        $step = 1;
        $this->setProgress($queue, $step / $totalSteps);

        try {
            if ($this->_backup->id) {
                Backup::$app->backups->enupalBackup($this->_backup);
                $step = 2;
                $this->setProgress($queue, $step / $totalSteps);
            } else {
                $error = '01 - Unable to execute the Enupal Backup: '.json_encode($this->_backup->getErrors());
                $this->updateBackupToError($error);
            }
        } catch (\Throwable $e) {
            $error = '02 - Could not create Enupal Backup: '.$e->getMessage().' --Trace: '.$e->getTraceAsString();
            $this->updateBackupToError($error);
        }
        // let's dont return false if the backup fails we'll know it
        return true;
    }


    /**
     * @param $error
     * @return bool
     * @throws \Throwable
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    private function updateBackupToError($error)
    {
        if (!$this->_backup) {
            return false;
        }

        $settings = Backup::$app->settings->getSettings();
        $this->_backup->backupStatusId = BackupStatus::ERROR;
        $this->_backup->logMessage = $error;

        Backup::$app->backups->saveBackup($this->_backup);
        Backup::$app->backups->checkBackupsAmount();

        Craft::error($error, __METHOD__);

        if ($settings->enableNotification) {
            Backup::$app->backups->sendNotification($this->_backup);
        }

        return true;
    }
}