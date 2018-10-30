<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use craft\records\Element;

/**
 * Class Backup record.
 *
 * @property int    $id
 * @property string $backupId
 * @property string $time
 * @property string $databaseFileName
 * @property string $slides
 * @property string $assetFileName
 * @property string $assetSize
 * @property string $templateFileName
 * @property string $templateSize
 * @property string $webFileName
 * @property string $webSize
 * @property string $configFileName
 * @property string $configSize
 * @property string $logFileName
 * @property string $logSize
 * @property string $databaseSize
 * @property integer $backupStatusId
 * @property string $logMessage
 * @property bool $aws
 * @property bool $dropbox
 * @property bool $rsync
 * @property bool $ftp
 * @property bool $softlayer
 * @property bool $googleDrive
 * @property bool $isEncrypted
 */
class Backup extends ActiveRecord
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%enupalbackup_backups}}';
    }

    /**
     * Returns the entry’s element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}