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
	private $_contentRows;
	private $_newFormat;
	private $_contentTable;

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

			return true;
		}
		catch (\Throwable $e)
		{
			Craft::$app->getErrorHandler()->logException($e);
			 return 'An exception was thrown while trying to generate the Enupal Backup: '.$e->getMessage();
		}
		$contentRow = $this->_contentRows[$step];

		//Call the update process
		$response = sproutForms()->entries->updateTitleFormat($contentRow, $this->_newFormat, $this->_contentTable);

		if (!$response)
		{
			SproutFormsPlugin::log('SproutForms has failed to update the title format for ' . $this->_contentTable . ' Id:' . $contentId, LogLevel::Error);
		}


	}
}