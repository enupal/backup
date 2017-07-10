<?php
namespace enupal\backup;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use phpbu\App\Configuration;
use phpbu\App\Runner;

use enupal\backup\models\Settings;

class Backup extends \craft\base\Plugin
{
	/**
	 * Enable use of Backup::$app-> in place of Craft::$app->
	 *
	 * @var [type]
	 */
	public static $app;

	public $hasCpSection = true;

	public function init()
	{
		parent::init();
		self::$app = $this->get('app');

		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
				$event->rules = array_merge($event->rules, $this->getCpUrlRules());
			}
		);
	}

	protected function afterInstall()
	{
		#self::$app->backups->installDefaultValues();
	}

	protected function createSettingsModel()
	{
		return new Settings();
	}

	public function getCpNavItem()
	{
		$parent = parent::getCpNavItem();
		return array_merge($parent,[
			'subnav' => [
				'backups' => [
					"label" => Backup::t("Backups"),
					"url"   => 'enupalbackup/backups'
				],
				'settings' =>[
					"label" => Backup::t("Settings"),
					"url" => 'enupalbackup/settings'
				]
			]
		]);
	}

	/**
	 * @param string $message
	 * @param array  $params
	 *
	 * @return string
	 */
	public static function t($message, array $params = [])
	{
		return Craft::t('enupal-backup', $message, $params);
	}

	public static function log($message, $type = 'info')
	{
		Craft::$type(self::t($message), __METHOD__);
	}

	public static function info($message)
	{
		Craft::info(self::t($message), __METHOD__);
	}

	public static function error($message)
	{
		Craft::error(self::t($message), __METHOD__);
	}

	/**
	 * @return array
	 */
	private function getCpUrlRules()
	{
		return [
			'enupalbackup/run' =>
			'enupalbackup/backups/run',

			'enupalbackup' =>
			'enupalbackup/backups/index',

			'enupalbackup/backup/new' =>
			'enupalbackup/backups/edit-backup',

			'enupalbackup/backup/edit/<backupId:\d+>' =>
			'enupalbackup/backups/edit-backup',
		];
	}
}

