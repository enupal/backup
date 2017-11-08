<?php
namespace enupal\backup\contracts;

use enupal\backup\Backup;

abstract class BackupType
{
	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var []
	 */
	public $syncs;

	/**
	 * @var []
	 */
	public $encrypt;

	/**
	 * @var []
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
	 * @return []
	 */
	abstract public function getBackup();
}