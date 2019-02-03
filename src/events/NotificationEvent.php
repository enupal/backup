<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal LLC
 */

namespace enupal\backup\events;


use enupal\backup\elements\Backup;
use yii\base\Event;
use craft\mail\Message;

/**
 * NotificationEvent class.
 */
class NotificationEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Message
     */
    public $message;

    /**
     * @var Backup
     */
    public $backup;

}
