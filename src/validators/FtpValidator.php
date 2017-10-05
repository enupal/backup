<?php
namespace enupal\backup\validators;

use yii\validators\Validator;
use enupal\backup\Backup;

use Craft;

class FtpValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * Ftp validation
	 */
	public function validateAttribute($object, $attribute)
	{
		if ($object->enableFtp && !$object->ftpType)
		{
			$this->addError($object, $attribute, Backup::t('Ftp Type cannot be blank'));
		}

		if ($object->enableFtp && !$object->ftpHost)
		{
			$this->addError($object, $attribute, Backup::t('Ftp Host cannot be blank'));
		}

		if ($object->enableFtp && !$object->ftpUser)
		{
			$this->addError($object, $attribute, Backup::t('Ftp User cannot be blank'));
		}

		if ($object->enableFtp && !$object->ftpPassword)
		{
			$this->addError($object, $attribute, Backup::t('Ftp Password cannot be blank'));
		}
	}
}
