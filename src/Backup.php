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
use craft\services\SystemMessages;
use craft\events\RegisterEmailMessagesEvent;

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
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public $schemaVersion = '1.1.5';

    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

        $settings = Backup::$app->settings->getDbSettings();

        if (isset($settings['pluginNameOverride']) && $settings['pluginNameOverride']){
            $this->name = $settings['pluginNameOverride'];
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

        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                array_push($event->messages,
                    [
                        'key' => 'enupal_backup_notification',
                        'subject' => 'Backup process completed',
                        'body' => 'We are happy to inform you that the backup process has been completed. Backup Id: {{backup.backupId}}'
                    ]
                );
            }
        );
    }

    /**
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    protected function afterInstall()
    {
        self::$app->backups->installDefaultValues();
    }

    /**
     * Performs actions before the plugin is Uninstalled.
     *
     * @return bool Whether the plugin should be Uninstalled
     * @throws \Throwable
     */
    protected function beforeUninstall(): bool
    {
        $backups = self::$app->backups->getAllBackups();

        foreach ($backups as $key => $backup) {
            Craft::$app->elements->deleteElementById($backup->id);
        }

        return true;
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();
        return array_merge($parent, [
            'subnav' => [
                'backups' => [
                    "label" => Backup::t("Backups"),
                    "url" => 'enupal-backup/backups'
                ],
                'settings' => [
                    "label" => Backup::t("Settings"),
                    "url" => 'enupal-backup/settings'
                ]
            ]
        ]);
    }

    /**
     * Settings HTML
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml()
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
                'enupal-backup/webhook/schedule'
        ];
    }
}

