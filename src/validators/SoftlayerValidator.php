<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\validators;

use yii\validators\Validator;
use enupal\backup\Backup;

use Craft;

class SoftlayerValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * Softlayer validation
	 */
	public function validateAttribute($object, $attribute)
	{
		if ($object->enableSos && !$object->sosUser)
		{
			$this->addError($object, $attribute, Backup::t('User cannot be blank'));
		}

		if ($object->enableSos && !$object->sosSecret)
		{
			$this->addError($object, $attribute, Backup::t('Secret cannot be blank'));
		}

		if ($object->enableSos && !$object->sosHost)
		{
			$this->addError($object, $attribute, Backup::t('Host cannot be blank'));
		}

		if ($object->enableSos && !$object->sosContainer)
		{
			$this->addError($object, $attribute, Backup::t('Container cannot be blank'));
		}

		if ($object->enableSos && !$object->sosPath)
		{
			$this->addError($object, $attribute, Backup::t('Path cannot be blank'));
		}
	}
}
