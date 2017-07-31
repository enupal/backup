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
use yii\base\Exception;

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
		$info      = Craft::$app->getInfo();
		$siteName  = $info->name ?? '';
		$randomStr = $this->getRandomStr();
		$date      = date('Y-m-d-His');

		$backupId   = $date.'_'.$siteName.'_'.$randomStr;
		$backup     = new BackupElement();
		$base       = Craft::getAlias('@enupal/backup/');
		$phpbuPath  = Craft::getAlias('@enupal/backup/resources');
		$configFile = Backup::$app->backups->getConfigJson($backupId, $backup);

		if (!is_file($configFile))
		{
			throw new Exception("Could not create the Enupal Backup: the config file doesn't exist: ".$configFile);
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

		$this->updateBackupOnComplete($backup);

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

	public function getSizeFormatted($size)
	{
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor(log($size, 1024)) : 0;
		return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
	}

	private function updateBackupOnComplete(BackupElement $backup)
	{
		// let's update the filenames
		if (!is_file($backup->getDatabaseFile()))
		{
			$backup->databaseFileName = null;
		}
		else
		{
			$backup->databaseSize = filesize($backup->getDatabaseFile());
		}

		if (!is_file($backup->getTemplateFile()))
		{
			$backup->templateFileName = null;
		}
		else
		{
			$backup->templateSize = filesize($backup->getTemplateFile());
		}

		if (!is_file($backup->getPluginFile()))
		{
			$backup->pluginFileName = null;
		}
		else
		{
			$backup->pluginSize = filesize($backup->getPluginFile());
		}

		if (!is_file($backup->getAssetFile()))
		{
			$backup->assetFileName = null;
		}
		else
		{
			$backup->assetSize = filesize($backup->getAssetFile());
		}

		$logPath = $this->getLogPath();
		$log     = file_get_contents($logPath);
		// Save the log
		$backup->logMessage = $log;

		$backupLog = json_decode($log, true);
		// Backup succesfully
		$backup->status = 1;
		// @todo depending of the settings
		$backup->dropbox = 1;
		$backup->aws = 1;
		$backup->rsync = 1;
		$backup->ftp = 1;
		$backup->softlayer = 1;

		if (isset($backupLog['timestamp']))
		{
			$backup->time = $backupLog['timestamp'];
		}

		// Try to figure out if any sync fails
		if (isset($backupLog['errors']) && $backupLog['errors'])
		{
			foreach ($backupLog['errors'] as $error)
			{
				if (isset($error['msg']))
				{
					// Dropbox
					if (strpos(strtolower($error['msg']), 'dropbox') !== false)
					{
						$backup->dropbox = 0;
					}
				}
			}
		}

		$this->saveBackup($backup);
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
			throw new Exception('Unable to create the element record for the Backup: '.$backupId.
				' Errors: '.json_encode($backup->getErrors()));
		}

		// @todo - add the assets from settings
		$testAsset = Craft::$app->getVolumes()->getVolumeById(36);
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
					$assetBackup['syncs'] = $syncs;
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

	/**
	 * Generate a random string, using a cryptographically secure
	 * pseudorandom number generator (random_int)
	 *
	 * For PHP 7, random_int is a PHP core function
	 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
	 *
	 * @param int $length      How many characters do we want?
	 * @param string $keyspace A string of all possible characters
	 *                         to select from
	 * @return string
	 */
	private function getRandomStr($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
		$str = '';
		$max = mb_strlen($keyspace, '8bit') - 1;

		for ($i = 0; $i < $length; ++$i)
		{
				$str .= $keyspace[random_int(0, $max)];
		}
		return $str;
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

	public function getDatabasePath()
	{
		return $this->getBasePath().'databases'.DIRECTORY_SEPARATOR;
	}

	public function getPluginsPath()
	{
		return $this->getBasePath().'plugins'.DIRECTORY_SEPARATOR;
	}

	public function getLogPath()
	{
		return Craft::getAlias('@enupal/backup/backup/enupalbackup.log');
	}

}
