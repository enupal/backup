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
use Craft;

use enupal\backup\enums\BackupStatus;

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

	public function execute($queue)
	{
		$result = false;
		$totalSteps = 2;
		$this->_backup = Backup::$app->backups->initializeBackup();

		$step = 1;
		$this->setProgress($queue, $step / $totalSteps);

		try
		{
			if ($this->_backup->id)
			{
				$result = Backup::$app->backups->enupalBackup($this->_backup);
				$step = 2;
				$this->setProgress($queue, $step / $totalSteps);
			}
			else
			{
				$error = '01 - Unable to execute the Enupal Backup: '.json_encode($this->_backup->getErrors());
				$this->_backup->backupStatusId = BackupStatus::ERROR;
				$this->_backup->logMessage = $error;

				Backup::$app->backups->saveBackup($this->_backup);
				Backup::$app->backups->checkBackupsAmount();

				Backup::error($error);
			}

		} catch (\Throwable $e)
		{
			$error = '02 - Could not create Enupal Backup: '.$e->getMessage().' --Trace: '.$e->getTraceAsString();
			$this->_backup->backupStatusId = BackupStatus::ERROR;
			$this->_backup->logMessage = $error;

			Backup::$app->backups->saveBackup($this->_backup);
			Backup::$app->backups->checkBackupsAmount();

			Backup::error($error);
		}
		// let's dont return false if the backup fails we'll know it
		return true;
	}
}