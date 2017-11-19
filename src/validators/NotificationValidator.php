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

class NotificationValidator extends Validator
{
	public $skipOnEmpty = false;

	/**
	 * Email notification validation
	 */
	public function validateAttribute($object, $attribute)
	{
		if ($object->enableNotification && !$object->notificationRecipients)
		{
			$this->addError($object, $attribute, Backup::t('Recipients cannot be blank'));
		}

		if ($object->enableNotification && !$object->notificationSenderName)
		{
			$this->addError($object, $attribute, Backup::t('Sender Name cannot be blank'));
		}

		if ($object->enableNotification && !$object->notificationSenderEmail)
		{
			$this->addError($object, $attribute, Backup::t('Sender Email cannot be blank'));
		}

		if ($object->enableNotification && !$object->notificationReplyToEmail)
		{
			$this->addError($object, $attribute, Backup::t('Reply Email cannot be blank'));
		}
	}
}
