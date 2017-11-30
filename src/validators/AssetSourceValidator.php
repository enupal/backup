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

class AssetSourceValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * At least one source
	 */
	public function validateAttribute($object, $attribute)
	{
		$volumes = $object->enableLocalVolumes;

		if ($volumes && !$object->volumes)
		{
			$this->addError($object, $attribute, Backup::t('Please select at least one Volume'));
		}
	}
}
