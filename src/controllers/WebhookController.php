<?php
namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;

use enupal\backup\Backup;

class WebhookController extends BaseController
{
	protected $allowAnonymous = array('actionFinished');

	/**
	 * Webhook to listen when a backup process finish up
	 * @param $backupId
	 *
	*/
	public function actionFinished()
	{
		$backupId = Craft::$app->request->getParam('backupId');
		$backup   = Backup::$app->backups->getBackupByBackupId($backupId);

		if ($backup)
		{
			// we could check just this backup but let's check all pending backups
			$pendingBackups = Backup::$app->backups->getPendingBackups();

			foreach ($pendingBackups as $key => $pendingBackup)
			{
				Backup::$app->backups->updateBackupOnComplete($pendingBackup);
				Backup::info("EnupalBackup webhook has updated: ".$backupId);
			}

			Backup::$app->backups->checkBackupsAmount();
			Backup::$app->backups->deleteConfigFile();
		}
		else
		{
			Backup::error("Unable to finish the webhook backup for: ".$backupId);
		}

		return $this->asJson(['success'=>true]);
	}
}
