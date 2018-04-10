<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\contracts;

abstract class BackupType
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $syncs;

    /**
     * @var array
     */
    public $encrypt;

    /**
     * @var array
     */
    public $settings;

    /**
     * @var string
     */
    public $dirName;

    /**
     * @var string
     */
    public $fileName;

    /**
     * Returns the backup array
     *
     * @return array
     */
    abstract public function getBackup();
}