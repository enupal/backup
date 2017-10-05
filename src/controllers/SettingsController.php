<?php
namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;
use craft\helpers\UrlHelper;
use yii\web\NotFoundHttpException;
use yii\db\Query;
use craft\helpers\ArrayHelper;
use craft\elements\Asset;

use enupal\backup\models\Settings as SettingsModel;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;

class SettingsController extends BaseController
{
	/**
	 * Save Plugin Settings
	 *
	 * @return void
	 */
	public function actionSaveSettings()
	{
		$this->requirePostRequest();
		$request  = Craft::$app->getRequest();
		$settings = $request->getBodyParam('settings');
		$scenario = $request->getBodyParam('backupScenario');

		if (!Backup::$app->settings->saveSettings($settings, $scenario))
		{
			Craft::$app->getSession()->setError(Backup::t('Couldn’t save settings.'));

			// Send the settings back to the template
			Craft::$app->getUrlManager()->setRouteParams([
				'settings' => $settings
			]);

			return null;
		}

		Craft::$app->getSession()->setNotice(Backup::t('Settings saved.'));

		return $this->redirectToPostedUrl();
	}
}
