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

class BackupFilesValidator extends Validator
{
    public $skipOnEmpty = false;

    /**
     * At least one need to be enable
     *
     * @param $object
     * @param $attribute
     */
    public function validateAttribute($object, $attribute)
    {
        $templates = $object->enableTemplates;
        $volumes = $object->enableLocalVolumes;
        $database = $object->enableDatabase;
        $log = $object->enableLogs;

        if (!$templates && !$volumes && !$database && !$log) {
            $this->addError($object, $attribute, Backup::t('At least one file needs to be backed up'));
        }
    }
}
