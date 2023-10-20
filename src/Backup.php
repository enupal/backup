<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use enupal\backup\services\App;
use yii\base\Event;
use craft\web\twig\variables\CraftVariable;

use enupal\backup\variables\BackupVariable;
use enupal\backup\models\Settings;

class Backup extends Plugin
{
    /**
     * Enable use of Backup::$app-> in place of Craft::$app->
     *
     * @var App
     */
    public static $app;

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '2.0.0';

    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

        $settings = $this->getSettings();

        if ($settings->pluginNameOverride){
            $this->name = $settings->pluginNameOverride;
        }

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getCpUrlRules());
        }
        );

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getSiteUrlRules());
        }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('enupalbackup', BackupVariable::class);
            }
        );
    }

    /**
     * Performs actions before the plugin is Uninstalled.
     *
     * @return bool Whether the plugin should be Uninstalled
     * @throws \Throwable
     */
    protected function beforeUninstall(): void
    {
        $backups = self::$app->backups->getAllBackups();

        foreach ($backups as $key => $backup) {
            Craft::$app->elements->deleteElementById($backup->id);
        }
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    public function getCpNavItem(): ?array
    {
        $parent = parent::getCpNavItem();
        $current = array_merge($parent, [
            'subnav' => [
                'backups' => [
                    "label" => Backup::t("Backups"),
                    "url" => 'enupal-backup/backups'
                ]
            ]
        ]);

        if (Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $current['subnav']['settings'] = [
                "label" => Backup::t("Settings"),
                "url" => 'enupal-backup/settings'
            ];
        }

        return $current;
    }

    /**
     * Settings HTML
     *
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \yii\base\Exception
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('enupal-backup/settings/index');
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
            'enupal-backup/run' =>
                'enupal-backup/backups/run',

            'enupal-backup/backup/new' =>
                'enupal-backup/backups/edit-backup',

            'enupal-backup/backup/view/<backupId:\d+>' =>
                'enupal-backup/backups/view-backup',
        ];
    }

    /**
     * @return array
     */
    private function getSiteUrlRules()
    {
        return [
            'enupal-backup/finished' =>
                'enupal-backup/webhook/finished',

            'enupal-backup/schedule' =>
                'enupal-backup/webhook/schedule',

            'enupal-backup/google-drive/auth' =>
                'enupal-backup/webhook/google-drive-auth'
        ];
    }
}

