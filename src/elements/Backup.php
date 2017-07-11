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

use enupal\backup\elements\db\BackupQuery;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\Backup;

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
		return Backup::t('Backups');
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
		return true;
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
			'enupal-backup/backup/edit/'.$this->id
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
			return $this->dateCreated;
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
			'label' => Backup::t('All Backups'),
			]
		];

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
			'confirmationMessage' => Backup::t('Are you sure you want to delete the selected backups?'),
			'successMessage' => Backup::t('Backups deleted.'),
		]);

		return $actions;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSearchableAttributes(): array
	{
		return ['dateCreated'];
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSortOptions(): array
	{
		$attributes = [
			'elements.dateCreated'      => Backup::t('Date Created')
		];

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineTableAttributes(): array
	{
		$attributes['dateCreated'] = ['label' => Backup::t('Backup Date')];
		$attributes['download']    = ['label' => Backup::t('Data')];
		$attributes['actions']     = ['label' => Backup::t('Actions')];

		return $attributes;
	}

	protected static function defineDefaultTableAttributes(string $source): array
	{
		$attributes = ['dateCreated', 'download', 'actions'];

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
			case 'actions':
			{
				return 'Links ;D';
			}
		}

		return parent::tableAttributeHtml($attribute);
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

		$record->save(false);

		parent::afterSave($isNew);
	}
}