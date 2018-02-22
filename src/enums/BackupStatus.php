<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\enums;

/**
 * Current status of a Backup
 */
abstract class BackupStatus extends BaseEnum
{
    // Constants
    // =========================================================================

    const FINISHED = 2;
    const STARTED = 0;
    const RUNNING = 1;
    const ERROR = 3;
}
