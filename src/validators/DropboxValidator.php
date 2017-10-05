<?php
namespace enupal\backup\validators;

use yii\validators\Validator;
use enupal\backup\Backup;

use Craft;

class DropboxValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * At least one need to be enable
	 */
	public function validateAttribute($object, $attribute)
	{
		if ($object->enableDropbox && !$object->dropboxToken)
		{
			$this->addError($object, $attribute, Backup::t('Secrect Key cannot be blank'));
		}
	}
}
