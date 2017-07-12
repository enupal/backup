<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;

use enupal\backup\Backup;
use enupal\backup\models\Settings as SettingsModel;
use yii\db\Query;

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
		$settings = Backup::$app->sliders->getSettings();

		//Craft::$app->getSecurity()->hashData($password);

		if (isset($postSettings['pluginNameOverride']))
		{
			$settings['pluginNameOverride'] = $postSettings['pluginNameOverride'];
		}

		$settings = json_encode($settings);

		$affectedRows = Craft::$app->getDb()->createCommand()->update('plugins', [
			'settings' => $settings
			],
			[
			'handle' => 'enupalbackup'
			]
		)->execute();

		return (bool) $affectedRows;
	}

	public function getSettings()
	{
		$result = (new Query())
			->select('settings')
			->from(['{{%plugins}}'])
			->where(['handle' => 'enupal-backup'])
			->one();

		$arraySettings = json_decode($result['settings'], true);

		$settings = new SettingsModel($arraySettings);

		/*
		if ($settings->dropboxToken)
		{
			$settings->dropboxToken = Craft::$app->getSecurity()->hashData($settings->dropboxToken);
		}
		*/

		return $settings;
	}
}
