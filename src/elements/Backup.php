<?php
namespace enupal\backup\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use yii\base\ErrorHandler;
use craft\db\Query;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;
use craft\elements\actions\Delete;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;

use enupal\backup\elements\db\BackupQuery;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup as BackupPlugin;

/**
 * Backup represents a entry element.
 */
class Backup extends Element
{
	// Properties
	// =========================================================================

	// General - Properties
	// =========================================================================
	public $id;
	public $backupId;
	public $time;
	public $databaseFileName;
	public $databaseSize;
	public $assetFileName;
	public $assetSize;
	public $templateFileName;
	public $templateSize;
	public $pluginFileName;
	public $pluginSize;
	public $status = BackupStatus::RUNNING;
	public $aws = 0;
	public $dropbox = 0;
	public $rsync = 0;
	public $ftp = 0;
	public $softlayer = 0;
	public $logMessage;

	/**
	 * Returns the field context this element's content uses.
	 *
	 * @access protected
	 * @return string
	 */
	public function getFieldContext(): string
	{
		return 'enupalBackup:' . $this->id;
	}

	/**
	 * Returns the element type name.
	 *
	 * @return string
	 */
	public static function displayName(): string
	{
		return BackupPlugin::t('Backups');
	}

	/**
	 * @inheritdoc
	 */
	public static function refHandle()
	{
		return 'backups';
	}

	/**
	 * @inheritdoc
	 */
	public static function hasContent(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasTitles(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function isLocalized(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasStatuses(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::cpUrl(
			'enupal-backup/backup/view/'.$this->id
		);
	}

	/**
	 * Use the name as the string representation.
	 *
	 * @return string
	 */
	/** @noinspection PhpInconsistentReturnPointsInspection */
	public function __toString()
	{
		try
		{
			// @todo - For some reason the Title returns null possible Craft3 bug
			return $this->dateCreated->format(DateTime::RFC1123);
		} catch (\Exception $e) {
			ErrorHandler::convertExceptionToError($e);
		}
	}

	/**
	 * @inheritdoc
	 *
	 * @return FormQuery The newly created [[FormQuery]] instance.
	 */
	public static function find(): ElementQueryInterface
	{
		return new BackupQuery(get_called_class());
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSources(string $context = null): array
	{
		$sources = [
			[
			'key'   => '*',
			'label' => BackupPlugin::t('All Backups'),
			]
		];

		$statuses = BackupStatus::getConstants();

		$colors = [
			BackupStatus::FINISHED => 'green',
			BackupStatus::RUNNING  => 'blue',
			BackupStatus::ERROR  => 'red',
		];

		$sources[] = ['heading' => BackupPlugin::t("Backup Status")];

		foreach ($statuses as $code => $status)
		{
			if ($status != BackupStatus::STARTED)
			{
				$key = 'status:' . $status;
				$sources[] = [
					'status'   => $colors[$status],
					'key'      => $key,
					'label'    => ucwords(strtolower($code)),
					'criteria' => ['statusId' => $status]
				];
			}
		}

		return $sources;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineActions(string $source = null): array
	{
		$actions = [];

		// Delete
		$actions[] = Craft::$app->getElements()->createAction([
			'type' => Delete::class,
			'confirmationMessage' => BackupPlugin::t('Are you sure you want to delete the selected backups?'),
			'successMessage' => BackupPlugin::t('Backups deleted.'),
		]);

		return $actions;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSearchableAttributes(): array
	{
		return ['backupId', 'enupalbackup_backups.status', 'title'];
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSortOptions(): array
	{
		$attributes = [
			'elements.dateCreated'  => BackupPlugin::t('Date Created')
		];

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineTableAttributes(): array
	{
		$attributes['dateCreated'] = ['label' => BackupPlugin::t('Backup Date')];
		$attributes['status']      = ['label' => BackupPlugin::t('Status')];
		$attributes['download']    = ['label' => BackupPlugin::t('Data')];
		$attributes['actions']     = ['label' => BackupPlugin::t('Actions')];

		return $attributes;
	}

	protected static function defineDefaultTableAttributes(string $source): array
	{
		$attributes = ['dateCreated', 'status', 'download', 'actions'];

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected function tableAttributeHtml(string $attribute): string
	{
		switch ($attribute)
		{
			case 'download':
			{
				return 'Download links ;D';
			}
			case 'status':
			{
				$message = $this->status == BackupStatus::STARTED ?
					BackupPlugin::t('Started') :
					BackupPlugin::t('Not defined');

				if ($this->status == BackupStatus::FINISHED)
				{
					$message = '<i class="fa fa-check-square-o" aria-hidden="true"></i> Finished';
				}
				else if ($this->status == BackupStatus::RUNNING)
				{
					$message = '<i class="fa fa-circle-o-notch fa-spin fa fa-fw"></i><span class="sr-only">Loading...</span> Running';
				}
				else if ($this->status == BackupStatus::ERROR)
				{
					$message = '<i class="fa fa-times" aria-hidden="true"></i> Error';
				}
				return $message;
			}
			case 'actions':
			{
				return 'Links ;D';
			}
			case 'dateCreated':
			{
				return "Tests";
			}
		}

		return parent::tableAttributeHtml($attribute);
	}

	public function getDatabaseFile()
	{
		$base = BackupPlugin::$app->backups->getDatabasePath();

		return $base.$this->databaseFileName;
	}

	public function getTemplateFile()
	{
		$base = BackupPlugin::$app->backups->getTemplatesPath();

		return $base.$this->templateFileName;
	}

	public function getPluginFile()
	{
		$base = BackupPlugin::$app->backups->getPluginsPath();

		return $base.$this->pluginFileName;
	}

	public function getAssetFile()
	{
		$base = BackupPlugin::$app->backups->getAssetsPath();

		return $base.$this->assetFileName;
	}

	/**
	 * @inheritdoc
	 * @throws Exception if reasons
	 */
	public function afterSave(bool $isNew)
	{
		// Get the Backup record
		if (!$isNew)
		{
			$record = BackupRecord::findOne($this->id);

			if (!$record)
			{
				throw new Exception('Invalid Backup ID: '.$this->id);
			}
		} else
		{
			$record = new BackupRecord();
			$record->id = $this->id;
		}

		$record->backupId         = $this->backupId;
		$record->time             = $this->time;
		$record->databaseFileName = $this->databaseFileName;
		$record->databaseSize     = $this->databaseSize;
		$record->assetFileName    = $this->assetFileName;
		$record->assetSize        = $this->assetSize;
		$record->templateFileName = $this->templateFileName;
		$record->templateSize     = $this->templateSize;
		$record->pluginFileName   = $this->pluginFileName;
		$record->pluginSize       = $this->pluginSize;
		$record->status           = $this->status;
		$record->aws              = $this->aws;
		$record->dropbox          = $this->dropbox;
		$record->rsync            = $this->rsync;
		$record->ftp              = $this->ftp;
		$record->softlayer        = $this->softlayer;
		$record->logMessage       = $this->logMessage;

		$record->save(false);

		parent::afterSave($isNew);
	}
}