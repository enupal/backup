<?php
namespace enupal\slider\models;

use craft\base\Model;

use enupal\slider\Slider;

class SliderGroup extends Model
{
	/**
	 * @var int|null ID
	 */
	public $id;

	/**
	 * @var string|null Name
	 */
	public $name;

	/**
	 * @var string
	 */
	public $dateCreated;

	/**
	 * @var string
	 */
	public $dateUpdated;

	/**
	 * @var string
	 */
	public $uid;

	/**
	 * Use the translated section name as the string representation.
	 *
	 * @return string
	 */
	function __toString()
	{
		return Slider::t($this->name);
	}
}