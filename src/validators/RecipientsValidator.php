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

class RecipientsValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function validateAttribute($object, $attribute)
    {
        $value = $object->$attribute;

        if ($emails = explode(',', $value)) {
            foreach ($emails as $email) {
                if ($email) {
                    $this->validateRecipient($object, $attribute, $email);
                }
            }
        }
    }

    /**
     * Custom validator for email distribution list
     *
     * @param string $attribute
     *
     * @return boolean
     */
    private function validateRecipient($object, $attribute, $email): bool
    {
        $email = trim($email);

        // Allow twig syntax
        if (preg_match('/^{{?(.*?)}}?$/', $email)) {
            return true;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($object, $attribute, Backup::t('Please make sure all emails are valid.'));

            return false;
        }

        return true;
    }
}
