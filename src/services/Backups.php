<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;
use yii\db\Query;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\models\Settings;

use craft\errors\ShellCommandException;
use craft\volumes\Local;
use craft\helpers\App as CraftApp;
use mikehaertl\shellcommand\Command as ShellCommand;

class Backups extends Component
{
	protected $backupRecord;

	/**
	 * Constructor
	 *
	 * @param object $backupRecord
	 */
	public function __construct($backupRecord = null)
	{
		$this->backupRecord = $backupRecord;

		if (is_null($this->backupRecord))
		{
			$this->backupRecord = new BackupRecord();
		}
	}

	/**
	 * Returns a Backup model if one is found in the database by id
	 *
	 * @param int $backupId
	 * @param int $siteId
	 *
	 * @return null|BackupElement
	 */
	public function getBackupById(int $backupId, int $siteId = null)
	{
		$query = BackupElement::find();
		$query->id($backupId);
		$query->siteId($siteId);
		// @todo - research next function
		#$query->enabledForSite(false);

		return $query->one();
	}

	/**
	 * @param BackupElement $backup
	 *
	 * @throws \Exception
	 * @return bool
	 */
	public function saveBackup(BackupElement $backup)
	{
		if ($backup->id)
		{
			$backupRecord = BackupRecord::findOne($backup->id);

			if (!$backupRecord)
			{
				throw new Exception(Backup::t('No Backup exists with the ID “{id}”', ['id' => $backup->id]));
			}
		}

		$backup->validate();

		if ($backup->hasErrors())
		{
			return false;
		}

		$transaction = Craft::$app->db->beginTransaction();

		try
		{
			if (Craft::$app->elements->saveElement($backup))
			{
				$transaction->commit();
			}
		}
		catch (\Exception $e)
		{
			$transaction->rollback();

			throw $e;
		}

		return true;
	}

	/**
	 * Performs a Enupal Backup operation.
	 *
	 * @return boolean
	 * @throws Exception
	 * @throws ShellCommandException in case of failure
	 */
	public function enupalBackup()
	{
		// This may make take a while so..
		CraftApp::maxPowerCaptain();

		$backupId   = date('Ymd-H_i_s');
		$backup     = new BackupElement();
		$base       = Craft::getAlias('@enupal/backup/');
		$phpbuPath  = Craft::getAlias('@enupal/backup/resources');
		$configFile = Backup::$app->backups->getConfigJson($backupId, $backup);

		if (!is_file($configFile))
		{
			throw new Exception("Could not create the Enupal Backup: the config file doesn't exist.");
		}

		$configFile = $base.'backup/config.json';

		// Create the shell command
		$shellCommand = new ShellCommand();
		$command = 'cd'.
				' '.$phpbuPath.
				' && php phpbu.phar'.
				' --configuration='.$configFile.
				' --debug';

		$shellCommand->setCommand($command);

		// If we don't have proc_open, maybe we've got exec
		if (!function_exists('proc_open') && function_exists('exec'))
		{
			$shellCommand->useExec = true;
		}

		$success = $shellCommand->execute();

		if (!$success)
		{
			throw ShellCommandException::createFromCommand($shellCommand);
		}



		return $success;
	}

	public function installDefaultValues()
	{
		$model    = new Settings();
		$settings = $model->getAttributes();

		$settings = json_encode($settings);
		$affectedRows = Craft::$app->getDb()->createCommand()->update('plugins', [
			'settings' => $settings
			],
			[
			'handle' => 'enupal-backup'
			]
		)->execute();
	}

	public function getSizeFormatted($path)
	{
		$size = filesize($path);
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor(log($size, 1024)) : 0;
		return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
	}

	/**
	 * Generetates the config file and create the backup element entry
	 *
	*/
	private function getConfigJson($backupId, BackupElement $backup)
	{
		$logPath = $this->getLogPath();

		$config  = [
			'verbose' => true,
			'logging' => [
				[
					'type'   => 'json',
					'target' => $logPath
				]
			],
			'backups' => []
		];

		$compress       = $this->getCompressType();
		$syncs          = $this->getSyncs($backupId);
		$dbName         = 'backup-db-'.$backupId.$compress;
		$assetName      = 'backup-assets-'.$backupId.$compress;
		$templateName   = 'backup-templates-'.$backupId.$compress;
		$pluginName     = 'backup-plugins-'.$backupId.$compress;
		$pathToTar      = $this->getPathToTar();
		$assetsCleanups = $this->getAssetsCleanup();

		// let's create the Backup Element
		$backup->backupId         = $backupId;
		$backup->databaseFileName = $dbName;
		$backup->assetFileName    = $assetName;
		$backup->templateFileName = $templateName;
		$backup->pluginFileName   = $pluginName;

		if (!$this->saveBackup($backup))
		{
			Backup::error('Unable to create the element record for the Backup: '.$backupId.
				' Errors: '.json_encode($backup->getErrors()));

			return null;
		}

		// @todo - add the assets from settings
		$testAsset = Craft::$app->getVolumes()->getVolumeById(33);
		$assets[]  = $testAsset;
		$backups = [];
		// Adding the assets
		foreach ($assets as $key => $asset)
		{
			// Supports local volumes for now.
			if (get_class($asset) == Local::class)
			{
				// @todo - validate if the $asset->path exists
				$assetBackup = [
					'name'   => 'Asset:'.$asset->id,
					'source' => [
						'type' => 'tar',
						'options' => [
							"path" => $asset->path,
							"forceLocal" => true
						]
					],
					'target' => [
						'dirname' => $this->getAssetsPath(),
						'filename' => $assetName
					]
				];

				if ($pathToTar)
				{
					$assetBackup['source']['options']['pathToTar'] = "C:\\cygwin64\\bin";
				}

				if ($syncs)
				{
					#$assetBackup['syncs'] = $syncs;
				}

				if ($assetsCleanups)
				{
					$assetBackup['cleanup'] = $assetsCleanups;
				}

				$backups[] = $assetBackup;
			}
		}

		// @todo - Adding template backups
		if (true)
		{
			$baseTemplatePath = Craft::$app->getPath()->getSiteTemplatesPath();

			$templateBackup = [
				'name'   => 'Templates',
				'source' => [
					'type' => 'tar',
					'options' => [
						"path" => $baseTemplatePath,
						"forceLocal" => true
					]
				],
				'target' => [
					'dirname' => $this->getTemplatesPath(),
					'filename' => $templateName
				]
			];

			if ($pathToTar)
			{
				$templateBackup['source']['options']['pathToTar'] = "C:\\cygwin64\\bin";
			}

			if ($syncs)
			{
				$templateBackup['syncs'] = $syncs;
			}

			if ($assetsCleanups)
			{
				$templateBackup['cleanup'] = $assetsCleanups;
			}

			$backups[] = $templateBackup;
		}

		$config['backups'] = $backups;
		$base = Craft::getAlias('@enupal/backup/');
		$configFile = $base.'backup'.DIRECTORY_SEPARATOR.'config.json';

		file_put_contents($configFile, json_encode($config));

		return $logPath;
	}

	private function getSyncs($backupId)
	{
		$syncs = [];
		// @todo validate dropbox
		if (true)
		{
			$dropbox = [
				'type' => 'dropbox',
				'options' => [
					'token' => 'WpYFCk46C4QAAAAAAAAHmTbUVAvCFnBzf7Vqm3imO4ANZxazrF8YG0COqlh--tLa',
					'path' => '/enupalbackup/'.$backupId
				]
			];

			$syncs[] = $dropbox;
		}

		return $syncs;
	}

	private function getAssetsCleanup()
	{
		// @todo - cleanups Capacity- Outdated - quantity
		$cleanup = [];

		if (true)
		{
			$cleanup = [
				'type' => 'capacity',
				'options' => [
					'size' => '30M'
				]
			];
		}

		return $cleanup;
	}

	private function getCompressType()
	{
		// @todo - add setting to change this bz2 or something else
		return '.tar';
	}

	private function getPathToTar()
	{
		// @todo - add path to tar
		$pathToTar = null;

		if (true)
		{
			$pathToTar = "C:\\cygwin64\\bin";
		}

		return $pathToTar;
	}

	public function getBasePath()
	{
		return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;
	}

	public function getAssetsPath()
	{
		return $this->getBasePath().'assets'.DIRECTORY_SEPARATOR;
	}

	public function getTemplatesPath()
	{
		return $this->getBasePath().'templates'.DIRECTORY_SEPARATOR;
	}

	public function getDbPath()
	{
		return $this->getBasePath().'databases'.DIRECTORY_SEPARATOR;
	}

	public function getLogPath()
	{
		return Craft::getAlias('@enupal/backup/backup/enupalbackup.log');
	}

}
