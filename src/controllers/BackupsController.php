<?php
namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;
use craft\helpers\UrlHelper;
use yii\web\NotFoundHttpException;
use yii\db\Query;
use craft\helpers\ArrayHelper;
use craft\elements\Asset;
use craft\helpers\Json;
use craft\helpers\Template as TemplateHelper;
use yii\base\Exception;

use enupal\backup\tasks\CreateBackup;
use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup;

use mikehaertl\shellcommand\Command as ShellCommand;
use craft\errors\ShellCommandException;

class BackupsController extends BaseController
{
	/*
	 * Redirect to sliders index page
	*/
	public function actionIndex()
	{
		return $this->renderTemplate('enupal-backup/backups/index');
	}

	/*
	 * Download backup
	*/
	public function actionDownload()
	{
		$this->requirePostRequest();
		$backupId = Craft::$app->getRequest()->getRequiredBodyParam('backupId');
		$type     = Craft::$app->getRequest()->getRequiredBodyParam('type');
		$backup   = Backup::$app->backups->getBackupById($backupId);

		if ($backup && $type)
		{
			$filePath = null;

			switch ($type)
			{
				case 'database':
					$filePath = $backup->getDatabaseFile();
					break;
				case 'template':
					$filePath = $backup->getTemplateFile();
					break;
				case 'plugin':
					$filePath = $backup->getPluginFile();
					break;
				case 'asset':
					$filePath = $backup->getAssetFile();
					break;
			}

			if (!is_file($filePath))
			{
				throw new NotFoundHttpException(Backup::t('Invalid backup name: {filename}', [
					'filename' => $filePath
				]));
			}
		}
		else
		{
			throw new NotFoundHttpException(Backup::t('Invalid backup parameters'));
		}

		return Craft::$app->getResponse()->sendFile($filePath);
	}

	public function actionRun()
	{
		#$result = Backup::$app->backups->enupalBackup();
		#$tasksService = Craft::$app->getTasks();

		#$tasksService->queueTask([
		#	'type' => CreateBackup::class
		#]);

		##$tasksService->runPendingTasks();

		// NEW METHOD using webhook
		try
		{
			$backup = Backup::$app->backups->initializeBackup();
			// try to finish up
			if ($backup->id)
			{
				$result = Backup::$app->backups->enupalBackup($backup);
			}
		}
		catch (\Throwable $e)
		{
			$error = '02 - Could not create Enupal Backup: '.$e->getMessage().' --Trace: '.json_encode($e->getTrace());
			$backup->status = BackupStatus::ERROR;
			$backup->logMessage = $error;

			Backup::$app->backups->saveBackup($backup);

			Backup::error($error);
		}

		Craft::dd('Runing'.$backup->id);
	}

	/**
	 * View a Backup.
	 *
	 * @param int|null  $backupId The backup's ID
	 *
	 * @throws HttpException
	 * @throws Exception
	 */
	public function actionViewBackup(int $backupId = null)
	{
		// Get the Backup
		$backup = Backup::$app->backups->getBackupById($backupId);

		if (!$backup)
		{
			throw new NotFoundHttpException(Backup::t('Backup not found'));
		}

		if ($backup->status == BackupStatus::RUNNING)
		{
			Backup::$app->backups->updateBackupOnComplete($backup);
		}

		if (!is_file($backup->getDatabaseFile()))
		{
			$backup->databaseFileName = null;
		}

		if (!is_file($backup->getTemplateFile()))
		{
			$backup->templateFileName = null;
		}

		if (!is_file($backup->getPluginFile()))
		{
			$backup->pluginFileName = null;
		}

		if (!is_file($backup->getAssetFile()))
		{
			$backup->assetFileName = null;
		}

		$variables['backup'] = $backup;

		return $this->renderTemplate('enupal-backup/backups/_viewBackup', $variables);
	}

	/**
	 * Delete a slider.
	 *
	 * @return void
	 */
	public function actionDeleteBackup()
	{
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();

		$sliderId = $request->getRequiredBodyParam('id');
		$slider   = Backup::$app->backups->getBackupById($sliderId);

		// @TODO - handle errors
		$success = Backup::$app->backups->deleteBackup($slider);

		return $this->redirectToPostedUrl($form);
	}
}
