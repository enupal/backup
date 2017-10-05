<?php
namespace enupal\backup\validators;

use yii\validators\Validator;
use enupal\backup\Backup;

use Craft;

class AmazonValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * Amazon validation
	 */
	public function validateAttribute($object, $attribute)
	{
		if ($object->enableAmazon && !$object->amazonKey)
		{
			$this->addError($object, $attribute, Backup::t('Amazon Key cannot be blank'));
		}

		if ($object->enableAmazon && !$object->amazonSecret)
		{
			$this->addError($object, $attribute, Backup::t('Amazon Secret cannot be blank'));
		}

		if ($object->enableAmazon && !$object->amazonBucket)
		{
			$this->addError($object, $attribute, Backup::t('Amazon Bucket cannot be blank'));
		}

		if ($object->enableAmazon && !$object->amazonRegion)
		{
			$this->addError($object, $attribute, Backup::t('Amazon Region cannot be blank'));
		}
	}
}
