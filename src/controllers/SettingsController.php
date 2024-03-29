<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;
use enupal\backup\Backup;
use yii\web\ForbiddenHttpException;

class SettingsController extends BaseController
{
    /**
     * Save Plugin Settings
     *
     * @return null|\yii\web\Response
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveSettings()
    {
        // Make sure admin changes are allowed
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException('Administrative changes are disallowed in this environment.');
        }

        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $settings = $request->getBodyParam('settings');
        $scenario = $request->getBodyParam('backupScenario');

        if (!Backup::$app->settings->saveSettings($settings, $scenario)) {
            Craft::$app->getSession()->setError(Backup::t('Couldn’t save settings.'));

            // Send the settings back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Backup::t('Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
