<?php
namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;

use enupal\backup\Backup;

class WebhookController extends BaseController
{
	public $allowAnonymous = array('finished');

	/**
	 * Webhook to listen when a backup process finish up
	 *
	*/
	public function actionFinished()
	{
		$pendingBackups = Backup::$app->backups->getPendingBackups();

		Backup::error("ENUPALBACKUP: ".json_encode($_POST));

		foreach ($pendingBackups as $key => $pendingBackup)
		{
			Backup::$app->backups->updateBackupOnComplete($pendingBackup);
		}

		Backup::$app->backups->checkBackupsAmount();

		return $this->asJson(['success'=>true]);
	}
}
