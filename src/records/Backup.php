<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\records;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use craft\records\Element;

/**
 * Class Backup record.
 *
 * @property int         $id
 * @property int         $groupId
 * @property string      $name
 * @property string      $handle
 * @property string      $slides
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