<?php
namespace enupal\backup\services;

use Craft;
use yii\base\Component;
use yii\db\Query;
use enupal\backup\Backup;
use enupal\backup\elements\Backup as BackupElement;
use enupal\backup\records\Backup as BackupRecord;
use phpbu\App\Cmd;
use enupal\backup\models\Settings;

class Backups extends Component
{
	protected $backupRecord;


	public function test()
	{
		(new Cmd())->run([
			'--configuration=PATH-TO-MY-CONFIG',
		]);
	}

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
		$isNewSlider  = true;

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
}
