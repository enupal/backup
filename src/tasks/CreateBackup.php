<?php

namespace enupal\backup\tasks;

use enupal\backup\Backup;
use craft\base\Task;
use Craft;

/**
 * CrateBackup task
 */
class CrateBackup extends Task
{

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
			$result = Backup::$app->backups->enupalBackup();

		} catch (\Throwable $e)
		{
			Backup::error('Could not create Enupal Backup: '.$e->getMessage());
		}

		return $result;
	}
}