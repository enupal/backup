<?php

namespace enupal\backup\tasks;

use enupal\backup\Backup;
use craft\base\Task;
use Craft;

use enupal\backup\enums\BackupStatus;

/**
 * CreateBackup task
 */
class CreateBackup extends Task
{
	/**
	 * @var BackupElement|null
	 */
	private $_backup;

	/**
	 * Returns the default description for this task.
	 *
	 * @return string
	 */
	protected function defaultDescription(): string
	{
		return Backup::t('Creating backup');
	}

	/**
	 * Gets the total number of steps for this task.
	 *
	 * @return int
	 */
	public function getTotalSteps(): int
	{
		$this->_backup = Backup::$app->backups->initializeBackup();
		// one step
		return 1;
	}

	/**
	 * Runs a task step.
	 *
	 * @param int $step
	 *
	 * @return bool
	 */
	public function runStep(int $step)
	{
		$result = false;

		try
		{
			if ($this->_backup->id)
			{
				$result = Backup::$app->backups->enupalBackup($this->_backup);
			}
			else
			{
				$error = '01 - Unable to execute the Enupal Backup: '.json_encode($this->_backup->getErrors());
				$this->_backup->status = BackupStatus::ERROR;
				$this->_backup->logMessage = $error;

				Backup::$app->backups->saveBackup($this->_backup);

				Backup::error($error);
			}

		} catch (\Throwable $e)
		{
			$error = '02 - Could not create Enupal Backup: '.$e->getMessage();
			$this->_backup->status = BackupStatus::ERROR;
			$this->_backup->logMessage = $error;

			Backup::$app->backups->saveBackup($this->_backup);

			Backup::error($error);
		}

		return $result;
	}
}