<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;
use yii\db\Query;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\models\Settings;
use enupal\backup\enums\BackupStatus;

use craft\helpers\FileHelper;
use craft\errors\ShellCommandException;
use craft\volumes\Local;
use craft\helpers\App as CraftApp;
use mikehaertl\shellcommand\Command as ShellCommand;
use yii\base\Exception;
use craft\helpers\Path;
use craft\helpers\UrlHelper;

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
	 * @param int $id
	 * @param int $siteId
	 *
	 * @return null|BackupElement
	 */
	public function getBackupById(int $id, int $siteId = null)
	{
		$query = BackupElement::find();
		$query->id($id);
		$query->siteId($siteId);

		return $query->one();
	}

	/**
	 * Returns a Backup model if one is found in the database by backupId
	 *
	 * @param string $backupId
	 * @param int $siteId
	 *
	 * @return null|BackupElement
	 */
	public function getBackupByBackupId(string $backupId, int $siteId = null)
	{
		$query = BackupElement::find();
		$query->backupId($backupId);
		$query->siteId($siteId);

		return $query->one();
	}

	/**
	 * Returns all the Pending backups
	 *
	 * @return null|BackupElement[]
	 */
	public function getPendingBackups()
	{
		$query = BackupElement::find();
		$query->backupStatusId = BackupStatus::RUNNING;

		return $query->all();
	}

	/**
	 * @param $backup BackupElement
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
	public function enupalBackup(BackupElement $backup)
	{
		// This may make take a while so..
		#CraftApp::maxPowerCaptain();
		$settings   = Backup::$app->settings->getSettings();
		$phpbuPath  = Craft::getAlias('@enupal/backup/resources');
		$configFile = Backup::$app->backups->getConfigJson($backup);
		// update the the backup to running
		$backup->backupStatusId = BackupStatus::RUNNING;

		if (!$this->saveBackup($backup))
		{
			return false;
		}

		if (!is_file($configFile))
		{
			throw new Exception("Could not create the Enupal Backup: the config file doesn't exist: ".$configFile);
		}

		// Create the shell command
		$shellCommand = new ShellCommand();
		$command = 'cd'.
				' '.$phpbuPath;

		$phpPath = $this->getPhpPath();

		$command .= ' && '.$phpPath.' phpbu5.phar';
		$command .= ' --configuration='.$configFile;
				//' --debug';

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

		// moved to the webhook
		#$this->updateBackupOnComplete($backup);

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

	/**
	 * This function creates a default backup and generates the id
	 * @return BackupElement
	*/
	public function initializeBackup()
	{
		$info      = Craft::$app->getInfo();
		$systemName = FileHelper::sanitizeFilename(
			$info->name,
			[
				'asciiOnly' => true,
				'separator' => '_'
			]
		);
		$siteName  = $systemName ?? 'backup';
		$randomStr = $this->getRandomStr();
		$date      = date('YmdHis');

		$backupId         = strtolower($siteName.'_'.$date.'_'.$randomStr);
		$backup           = new BackupElement();
		$backup->backupId = $backupId;
		$backup->backupStatusId   = BackupStatus::STARTED;

		if (!$this->saveBackup($backup))
		{
			return $backup;
		}

		return $backup;
	}

	public function getSizeFormatted($size)
	{
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor(log($size, 1024)) : 0;
		return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
	}

	/**
	 * Check if the log file has content, if so the backup is finished
	*/
	public function updateBackupOnComplete(BackupElement $backup)
	{
		// If the log have infomartion the backup is finished
		$logPath = $this->getLogPath($backup->backupId);
		$log     = file_get_contents($logPath);

		if ($log)
		{
			// Save the log
			$backup->logMessage = $log;

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

			$backupLog = json_decode($log, true);
			// Backup succesfully
			$backup->backupStatusId = BackupStatus::FINISHED;
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

					if (isset($error['message']))
					{
						// Dropbox
						if (strpos(strtolower($error['message']), 'amazon') !== false)
						{
							$backup->amazon = 0;
						}
					}
				}
			}

			return $this->saveBackup($backup);
		}

		return false;
	}

	/**
	 * @param $backup BackupElement
	 * Generetates the config file and create the backup element entry
	 *
	*/
	private function getConfigJson(BackupElement $backup)
	{
		$logPath  = $this->getLogPath($backup->backupId);
		$settings = Backup::$app->settings->getSettings();
		// @todo add security steps for webhook
		// verbose shows user and password
		$config  = [
			'verbose' => false,
			'logging' => [
				[
					'type'   => 'json',
					'target' => $logPath
				],
				[
					'type'    => 'webhook',
					'options' => [
						'uri'      => UrlHelper::siteUrl('enupal-backup/finished?backupId='.$backup->backupId)
					]
				]
			],
			'backups' => []
		];

		$backupId       = $backup->backupId;
		$compress       = $this->getCompressType();
		$syncs          = $this->getSyncs($backupId);
		$dbFileName     = 'database-'.$backupId.'.sql';
		$assetName      = 'assets-'.$backupId.$compress;
		$templateName   = 'templates-'.$backupId.$compress;
		$pluginName     = 'plugins-'.$backupId.$compress;
		$pathToTar      = $this->getPathToTar();
		$backups        = [];

		// let's create the Backup Element
		$backup->databaseFileName = $dbFileName;
		$backup->assetFileName    = $assetName;
		$backup->templateFileName = $templateName;
		$backup->pluginFileName   = $pluginName;

		if (!$this->saveBackup($backup))
		{
			throw new Exception('Unable to create the element record for the Backup: '.$backupId.
				' Errors: '.json_encode($backup->getErrors()));
		}

		// DATABASE
		$dbConfig = Craft::$app->getConfig()->getDb();

		if ($dbConfig->driver == 'mysql')
		{
			$databaseBackup = [
				'name'   => 'Database',
				'source' => [
					'type'   => 'mysqldump',
					'options'       => [
						'host'          => $dbConfig->server,
						'databases'     => $dbConfig->database,
						'user'          => $dbConfig->user,
						'password'      => $dbConfig->password,
						'port'          => $dbConfig->port
						//'ignoreTable'   => 'tableFoo,tableBar',
						//'structureOnly' => 'logTable1,logTable2'
					]
				],
				'target' => [
					'dirname' => $this->getDatabasePath(),
					'filename' => $dbFileName
				]
			];

			if ($settings->enablePathToMysqldump && $settings->pathToMysqldump)
			{
				$databaseBackup['source']['options']['pathToMysqldump'] = $settings->pathToMysqldump;
			}

			if ($syncs)
			{
				$databaseBackup['syncs'] = $syncs;
			}

			$backups[] = $databaseBackup;
		}
		// END DATABASE

		// ASSETS
		$assets = [];

		if ($settings->enableLocalVolumes)
		{
			if (is_array($settings->volumes))
			{
				foreach ($settings->volumes as $volumeId)
				{
					$volume   = Craft::$app->getVolumes()->getVolumeById($volumeId);
					$assets[] = $volume;
				}
			}
			else
			{
				// get all the local volumes (*)
				$assets = Backup::$app->settings->getAllLocalVolumesObjects();
			}
		}
		// Adding the assets
		foreach ($assets as $key => $asset)
		{
			// Supports local volumes for now.
			if (get_class($asset) == Local::class)
			{
				// Check if the path exists
				// @todo - research and test this looks to easy :D
				if (is_dir($asset->path))
				{
					$assetBackup = [
						'name'   => 'Asset'.$asset->id,
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
						$assetBackup['source']['options']['pathToTar'] = $pathToTar;
					}

					if ($syncs)
					{
						$assetBackup['syncs'] = $syncs;
					}

					$backups[] = $assetBackup;
				}
				else
				{
					Backup::error('Skipped the volume: '.$asset->id.' because the path does not exists');
				}
			}
		}

		// TEMPLATES
		if ($settings->enableTemplates)
		{
			$baseTemplatePath = Craft::$app->getPath()->getSiteTemplatesPath();
			//@todo - add exclude templates
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
				$templateBackup['source']['options']['pathToTar'] = $pathToTar;
			}

			if ($syncs)
			{
				$templateBackup['syncs'] = $syncs;
			}

			$backups[] = $templateBackup;
		}

		$config['backups'] = $backups;

		$configFile = $this->getConfigPath();

		if (!file_exists($this->getBasePath()))
		{
			mkdir($this->getBasePath(), 0777, true);
		}

		file_put_contents($configFile, json_encode($config));

		return $configFile;
	}

	/**
	 * Performs a review to check the backups amount allowed
	 * @todo should we move this to a job?
	*/
	public function checkBackupsAmount()
	{
		// Amount of backups to keep
		$settings  = Backup::$app->settings->getSettings();
		// @todo we need to delete the ERROR backup's?
		$condition = 'backupStatusId =:finished';
		$params    = [
			':finished' => BackupStatus::FINISHED
		];

		try
		{
			$count = BackupElement::find()->where($condition, $params)->count();

			$totalToDelete = 0;

			if ($count > $settings['backupsAmount'])
			{
				$totalToDelete = $count - $settings['backupsAmount'];

				if ($totalToDelete)
				{
					$backups = BackupElement::find()
						->where($condition, $params)
						->limit($totalToDelete)
						->orderBy(['enupalbackup_backups.dateCreated' => SORT_ASC])
						->all();

					foreach ($backups as $key => $backup)
					{
						$response = Craft::$app->elements->deleteElementById($backup->id);

						if ($response)
						{
							Backup::info('EnupalBackup has deleted the backup Id: '.$backup->backupId);
						}
						else
						{
							Backup::error('EnupalBackup has failed to delete the backup Id: '.$backup->backupId);
						}

					}
				}
			}

		} catch (\Throwable $e)
		{
			$error = 'Enupal Backup Could not execute the checkBackupsAmount function: '.$e->getMessage().' --Trace: '.json_encode($e->getTrace());

			Backup::error($error);
			return false;
		}

		return true;
	}

	private function getSyncs($backupId)
	{
		$syncs = [];
		$settings = Backup::$app->settings->getSettings();
		// DROPBOX
		// @todo - Test with just one backup - triggers an error start with /
		if ($settings->enableDropbox)
		{
			$dropbox = [
				'type' => 'dropbox',
				'options' => [
					'token' => $settings->dropboxToken,
					'path'  => trim($settings->dropboxPath.$backupId)
				]
			];

			$syncs[] = $dropbox;
		}
		// AMAZON S3
		if ($settings->enableAmazon)
		{
			$amazon = [
				'type' => 'amazons3',
				'options' => [
					'key'    => $settings->amazonKey,
					'secret' => $settings->amazonSecret,
					'bucket' => $settings->amazonBucket,
					'region' => $settings->amazonRegion,
					'path'   => trim($settings->amazonPath.'/'.$backupId),
					'useMultiPartUpload' => $settings->amazonUseMultiPartUpload
				]
			];

			$syncs[] = $amazon;
		}

		return $syncs;
	}

	private function getCompressType()
	{
		// @todo - add setting to change this bz2 or something else
		return '.tar';
	}

	private function getPathToTar()
	{
		$settings  = Backup::$app->settings->getSettings();
		$pathToTar = null;

		if ($settings->enablePathToTar)
		{
			$pathToTar = $settings->pathToTar;
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
	public function getTempDatabasePath()
	{
		return Craft::$app->getPath()->getStoragePath().DIRECTORY_SEPARATOR.'enupalbackuptemp'.DIRECTORY_SEPARATOR;
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

	public function getLogPath($backupId)
	{
		$base = Craft::$app->getPath()->getLogPath().DIRECTORY_SEPARATOR.'enupalbackup'.DIRECTORY_SEPARATOR;

		return $base.$backupId.'.log';
	}

	public function getConfigPath()
	{
		$base = $this->getBasePath();
		$configFile = $base.'config.json';

		return $configFile;
	}

	public function getPhpPath()
	{
		$settings = Backup::$app->settings->getSettings();
		$phpPath  = 'php';

		if ($settings->enablePathToPhp && $settings->pathToPhp)
		{
			$phpPath = $settings->pathToPhp;
		}

		return $phpPath;
	}

	public function getColorStatuses()
	{
		$colors = [
			BackupStatus::STARTED  => 'white',
			BackupStatus::FINISHED => 'green',
			BackupStatus::RUNNING  => 'blue',
			BackupStatus::ERROR    => 'red',
		];

		return $colors;
	}

}
