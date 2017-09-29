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

use enupal\backup\jobs\CreateBackup;
use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup;

use mikehaertl\shellcommand\Command as ShellCommand;
use craft\errors\ShellCommandException;

class BackupsController extends BaseController
{
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
		// let's add the job if it's linux we can run it in background
		Craft::$app->queue->push(new CreateBackup());
		// We have a webhook so don't wait
		$success = false;
		$response = [
			'success' => true,
			'message' => 'queued'
		];

		if (!Backup::$app->settings->isWindows())
		{
			// listen by console
			$shellCommand = new ShellCommand();
			$craftPath    = CRAFT_BASE_PATH;
			$phpPath      = Backup::$app->backups->getPhpPath();

			$command = $phpPath.
					' craft'.
					' queue/run';
			// linux
			$command .= ' > /dev/null 2&1 &';
			// windows does not work
			//$command .= ' 1>> NUL 2>&1';
			$shellCommand->setCommand($command);

			//@todo requiere this in the docs
			$shellCommand->useExec = true;

			$success = $shellCommand->execute();
			$response = [
				'success' => $success,
				'message' => 'running'
			];
		}

		return $this->asJson($response);
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

		if ($backup->backupStatusId == BackupStatus::RUNNING)
		{
			Backup::$app->backups->updateBackupOnComplete($backup);
			Backup::$app->backups->checkBackupsAmount();
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

		$logPath = Backup::$app->backups->getLogPath($backup->backupId);

		if (is_file($logPath))
		{
			$log  = file_get_contents($logPath);
			$variables['log'] = $log;
		}

		return $this->renderTemplate('enupal-backup/backups/_viewBackup', $variables);
	}

	/**
	 * Delete a backup.
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
