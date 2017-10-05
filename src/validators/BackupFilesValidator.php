<?php
namespace enupal\backup\validators;

use yii\validators\Validator;
use enupal\backup\Backup;

use Craft;

class BackupFilesValidator extends Validator
{
	public $skipOnEmpty = false;
	/**
	 * At least one need to be enable
	 */
	public function validateAttribute($object, $attribute)
	{
		$templates = $object->enableTemplates;
		$volumes   = $object->enableLocalVolumes;
		$database  = $object->enableDatabase;

		if (!$templates && !$volumes && !$database)
		{
			$this->addError($object, $attribute, Backup::t('At least one file needs to be backed up'));
		}
	}
}
