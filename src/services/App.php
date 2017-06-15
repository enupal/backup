<?php
namespace enupal\backup\services;

use Craft;
use craft\base\Component;
use enupal\backup\Backup;

class App extends Component
{
	public $backups;
	public $settings;

	public function init()
	{
		$this->backups = new Backups();
		$this->settings = new Settings();
	}
}