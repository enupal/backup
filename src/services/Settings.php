<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;

use enupal\backup\Backup;
use enupal\backup\models\Settings as SettingsModel;
use yii\db\Query;
use craft\volumes\Local;

class Settings extends Component
{

	/**
	 * Saves Settings
	 *
	 * @param array $postSettings
	 *
	 * @return bool
	 */
	public function saveSettings($postSettings): bool
	{
		$backupPlugin = $this->getPlugin();

		//Craft::$app->getSecurity()->hashData($password);

		$success = Craft::$app->getPlugins()->savePluginSettings($backupPlugin, $postSettings);

		return $success;
	}

	public function getSettings()
	{
		$backupPlugin = $this->getPlugin();

		return $backupPlugin->getSettings();
	}

	public function getAllPlugins()
	{
		$plugins  = Craft::$app->getPlugins()->getAllPlugins();
		$response = [];

		foreach ($plugins as $key => $plugin)
		{
			$response[] = [
				'value' => $plugin->getHandle(),
				'label' => $plugin->name
			];
		}

		return $response;
	}

	public function getAllLocalVolumes()
	{
		$volumes  = Craft::$app->getVolumes()->getAllVolumes();
		$response = [];

		foreach ($volumes as $key => $volume)
		{
			if (get_class($volume) == Local::class)
			{
				$response[] = [
					'value' => $volume->id,
					'label' => $volume->name
				];
			}
		}

		return $response;
	}

	public function getAllLocalVolumesObjects()
	{
		$volumes  = Craft::$app->getVolumes()->getAllVolumes();
		$response = [];

		foreach ($volumes as $key => $volume)
		{
			if (get_class($volume) == Local::class)
			{
				$response[] = $volume;
			}
		}

		return $response;
	}

	public function getPlugin()
	{
		return Craft::$app->getPlugins()->getPlugin('enupal-backup');
	}
}
