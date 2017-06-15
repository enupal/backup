<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;

use enupal\backup\Backup;

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
}
