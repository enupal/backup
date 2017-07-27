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
		$settings            = $this->getSettings();
		$this->_contentRows  = $settings->contentRows;
		$this->_newFormat    = $settings->newFormat;
		$this->_contentTable = $settings->contentTable;

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
		try
		{
			$base = Craft::getAlias('@enupal/backup/');
			App::maxPowerCaptain();
			Backup::$app->backups->getConfigJson();

			$cmd = new Cmd();
			$configFile = $base.'backup/config.json';

			$cmd->run([
					'--configuration='.$configFile
					//'--debug'
			]);

			return true;
		}
		catch (\Throwable $e)
		{
			Craft::$app->getErrorHandler()->logException($e);
			 return 'An exception was thrown while trying to generate the Enupal Backup: '.$e->getMessage();
		}

	}
}