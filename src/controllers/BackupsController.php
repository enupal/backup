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
use yii\base\ErrorException;
use craft\helpers\FileHelper;
use craft\errors\ShellCommandException;
use mikehaertl\shellcommand\Command as ShellCommand;
use ZipArchive;

use enupal\backup\jobs\CreateBackup;
use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup;

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
				case 'all':

					$zipPath = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.$backup->backupId.'.zip';

					if (is_file($zipPath))
					{
						try
						{
							FileHelper::removeFile($zipPath);
						}
						catch (ErrorException $e)
						{
							Backup::error("Unable to delete the file \"{$zipPath}\": ".$e->getMessage());
						}
					}

					$zip = new ZipArchive();

					if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
						throw new Exception('Cannot create zip at '.$zipPath);
					}

					if ($backup->getDatabaseFile())
					{
						$filename = pathinfo($backup->getDatabaseFile(), PATHINFO_BASENAME);

						$zip->addFile($backup->getDatabaseFile(), $filename);
					}

					if ($backup->getTemplateFile())
					{
						$filename = pathinfo($backup->getTemplateFile(), PATHINFO_BASENAME);

						$zip->addFile($backup->getTemplateFile(), $filename);
					}

					if ($backup->getAssetFile())
					{
						$filename = pathinfo($backup->getAssetFile(), PATHINFO_BASENAME);

						$zip->addFile($backup->getAssetFile(), $filename);
					}

					if ($backup->getLogFile())
					{
						$filename = pathinfo($backup->getLogFile(), PATHINFO_BASENAME);

						$zip->addFile($backup->getLogFile(), $filename);
					}

					$zip->close();

					$filePath = $zipPath;
					break;
				case 'database':
					$filePath = $backup->getDatabaseFile();
					break;
				case 'template':
					$filePath = $backup->getTemplateFile();
					break;
				case 'logs':
					$filePath = $backup->getLogFile();
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
		$this->requirePostRequest();

		$response = Backup::$app->backups->executeEnupalBackup();

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

		if (!is_file($backup->getLogFile()))
		{
			$backup->logFileName = null;
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

		$backupId = $request->getRequiredBodyParam('id');
		$backup   = Backup::$app->backups->getBackupById($backupId);

		// @TODO - handle errors
		$success = Backup::$app->backups->deleteBackup($backup);

		if($success)
		{
			Craft::$app->getSession()->setNotice(Backup::t('Backup deleted.'));
		}
		else
		{
			Craft::$app->getSession()->setNotice(Backup::t('Couldn’t delete backup.'));
		}

		return $this->redirectToPostedUrl($backup);
	}
}
