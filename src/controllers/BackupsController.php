<?php
namespace enupal\backup\controllers;

use Craft;
use craft\web\Controller as BaseController;
use craft\helpers\UrlHelper;
use yii\web\NotFoundHttpException;
use yii\db\Query;
use craft\helpers\ArrayHelper;
use craft\elements\Asset;
use craft\helpers\Json;
use craft\helpers\Template as TemplateHelper;

use enupal\slider\Backup;
use phpbu\App\Cmd;
use phpbu\App\Util\Cli;

class BackupsController extends BaseController
{
	/*
	 * Redirect to sliders index page
	*/
	public function actionIndex()
	{
		return $this->renderTemplate('enupal-backup/backups/index');
	}

	public function actionRun()
	{
		/*//'\"C:\\Program Files (x86)\\Git\\bin\"'*/
		// windows needed
		$base = Craft::getAlias('@enupal/backup/');
		$path = escapeshellarg('C:\\Program Files (x86)\\Git\\bin');
		#Craft::dd($path);
		//Cli::addCommandLocation('tar',$path);
		$cmd = new Cmd();
		$configFile = $base.'backup/config.json';
		$response = $cmd->run([
				'--configuration='.$configFile
				//'--debug'
		]);
		$logPath = $base.'backup/json.log';
		$str = file_get_contents($logPath);
		$json = json_decode($str, true);
		echo "AS";
		echo "---------------------";
		print_r($json);
		die();

		// create new archive
		/*$zipFile = new \PhpZip\ZipFile();
		$zipFile
				->addDir('C:/MAMP/htdocs/craft3.personal/web/', "enupalslider")
				->saveAsFile("enupalbackup__232232323")
				->close();*/
	}

	/**
	 * Save a slider
	 */
	public function actionSaveBackup()
	{
		$this->requirePostRequest();

		/*$request = Craft::$app->getRequest();
		$slider  = new SliderElement;

		$sliderId = $request->getBodyParam('sliderId');
		$isNew    = true;

		if ($sliderId)
		{
			$slider = Backup::$app->backups->getSliderById($sliderId);

			if ($slider)
			{
				$isNew = false;
			}
		}

		//$slider->groupId     = $request->getBodyParam('groupId');
		$oldHandle              = $slider->handle;
		$newHandle              = $request->getBodyParam('handle');
		$slider->name           = $request->getBodyParam('name');
		$slider->handle         = $newHandle;
		$slider                 = Backup::$app->backups->populateSliderFromPost($slider);

		// Save it
		if (!Backup::$app->backups->saveSlider($slider))
		{
			Craft::$app->getSession()->setError(Backup::t('Coubackupsave slider.'));

			Craft::$app->getUrlManager()->setRouteParams([
					'slider'               => $slider
				]
			);

			return null;
		}

		//lets update the subfolder
		if (!$isNew && $oldHandle != $newHandle)
		{
			if (!Backup::$app->backups->updateSubfolder($slider, $oldHandle))
			{
				Backup::log("Ubackupto rename subfolder {$oldHandle} to {$slider->handle}", 'error');
			}
		}

		Craft::$app->getSession()->setNotice(Backup::t('Slibackupved.'));

		#$_POST['redirect'] = str_replace('{id}', $form->id, $_POST['redirect']);

		return $this->redirectToPostedUrl($slider);
		*/
	}

	/**
	 * Edit a Backup.
	 *
	 * @param int|null  $slierId The backup's ID, if editing an existing slider.
	 *
	 * @throws HttpException
	 * @throws Exception
	 */
	public function actionEditBackup(int $backupId = null)
	{
		// Immediately create a new Form
		/*if ($sliderId === null)
		{
			$slider = Backup::$app->backups->createNewSlider();

			if ($slider->id)
			{
				$url = UrlHelper::cpUrl('enupalslider/slider/edit/' . $slider->id);
				return $this->redirect($url);
			}
			else
			{
				throw new Exception(Craft::t('Error creating Slider'));
			}
		}
		else
		{
			if ($sliderId !== null)
			{
				if ($slider === null)
				{
					$variables['groups']  = Backup::$app->backup->getAllSlidersGroups();
					$variables['groupId'] = "";

					// Get the Slider
					$slider = Backup::$app->backups->getSliderById($sliderId);

					if (!$slider)
					{
						throw new NotFoundHttpException(Backup::t('Slibackupt found'));
					}
				}
			}
		}

		$sources = Backup::$app->backups->getVolumeFolder($slider);

		$variables['sources']  = $sources;
		$variables['sliderId'] = $sliderId;
		$variables['slider']   = $slider;
		$variables['name']     = $slider->name;
		$variables['groupId']  = $slider->groupId;
		$variables['elementType'] = Asset::class;

		$variables['slidesElements']  = null;

		if ($slider->slides)
		{
			$slides = $slider->slides;
			if (is_string($slides))
			{
				$slides = json_decode($slider->slides);
			}

			$slidesElements = [];

			if (count($slides))
			{
				foreach ($slides as $key => $slideId)
				{
					$slide = Craft::$app->elements->getElementById($slideId);
					array_push($slidesElements, $slide);
				}

				$variables['slidesElements'] = $slidesElements;
			}
		}

		$variables['showPreviewBtn'] = false;
		// Enable Live Preview?
		if (!Craft::$app->getRequest()->isMobileBrowser(true))
		{

			//#title-field, #fields > div > div > .field
			$this->getView()->registerJs('Craft.LivePreview.init('.Json::encode([
					'fields' => '.field',
					'previewAction' => 'enupalslider/sliders/live-preview',
					'previewParams' => [
						'sliderId' => $slider->id
					]
				]).');');

			$variables['showPreviewBtn'] = true;
		}

		// Set the "Continue Editing" URL
		$variables['continueEditingUrl'] = 'enupalslider/slider/edit/{id}';

		$variables['settings'] = Craft::$app->plugins->getPlugin('enupalslider')->getSettings();

		return $this->renderTemplate('enupalslider/sliders/_editSlider', $variables);
		*/
	}

	/**
	 * Delete a slider.
	 *
	 * @return void
	 */
	public function actionDeleteBackup()
	{
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();

		$sliderId = $request->getRequiredBodyParam('id');
		$slider   = Backup::$app->backups->getBackupById($sliderId);

		// @TODO - handle errors
		$success = Backup::$app->backups->deleteBackup($slider);

		return $this->redirectToPostedUrl($form);
	}
}
