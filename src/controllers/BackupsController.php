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
	 * Redirect to backups index page
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
		// let's add the job if it's linux we can run it in background
		Craft::$app->queue->push(new CreateBackup());
		// We have a webhook so don't wait
		$success = false;

		if (substr(php_uname(), 0, 7) != "Windows")
		{
			// listen by console
			// @todo we may need to add a settign to save the php path
			$shellCommand = new ShellCommand();
			// this is ok?
			$craftPath = CRAFT_BASE_PATH;

			$command = 'cd'.
					' '.$craftPath.
					' && php craft'.
					' queue/run';
			//> /dev/null &
			$command .= ' > /dev/null 2&1 &';
			$shellCommand->setCommand($command);

			// If we don't have proc_open, maybe we've got exec
			//@todo requiere this in the docs
			$shellCommand->useExec = true;

			$success = $shellCommand->execute();

			// windows does not work
			//$command .= ' 1>> NUL 2>&1';
			//$success = pclose(popen("start /B ". $command, "w"));
		}

		/*
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
		*/
		Craft::dd('Runing: ');
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

		$logPath = Backup::$app->backups->getLogPath($backup->backupId);
		$log     = file_get_contents($logPath);
		$variables['log'] = $log;

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
