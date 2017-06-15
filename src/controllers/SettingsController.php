<?php
namespace enupal\slider\controllers;

use Craft;
use craft\web\Controller as BaseController;
use craft\helpers\UrlHelper;
use yii\web\NotFoundHttpException;
use yii\db\Query;
use craft\helpers\ArrayHelper;
use craft\elements\Asset;

use enupal\slider\models\Settings;
use enupal\slider\Slider;
use enupal\slider\elements\Slider as SliderElement;

class SettingsController extends BaseController
{
	/**
	 * Save Plugin Settings
	 *
	 * @return void
	 */
	public function actionSaveSettings()
	{
		$this->requirePostRequest();
		$request  = Craft::$app->getRequest();
		$settings = $request->getBodyParam('settings');

		if (Slider::$app->settings->saveSettings($settings))
		{
			Craft::$app->getSession()->setNotice(Slider::t('Settings saved.'));

			return $this->redirectToPostedUrl();
		}
		else
		{
			Craft::$app->getSession()->setError(Slider::t('Couldn’t save settings.'));

			// Send the settings back to the template
			craft()->urlManager->setRouteVariables(array(
				'settings' => $settings
			));
		}
	}

	/**
	 * Save a slider
	 */
	public function actionSaveSlider()
	{
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();
		$slider  = new SliderElement;

		// @todo - save as new
		/*if ($request->getBodyParam('saveAsNew'))
		{
			@todo save as new feature
			$slider->saveAsNew = true;
			$duplicateSlider = Slider::$app()->sliders->createNewSlider(
				$request->getBodyParam('name'),
				$request->getBodyParam('handle')
			);

			if ($duplicateSlider)
			{
				$slider->id = $duplicateSlider->id;
			}
			else
			{
				throw new Exception(Craft::t('Error creating Form'));
			}
		}*/

		$sliderId = $request->getBodyParam('sliderId');
		$isNew    = true;

		if ($sliderId)
		{
			$slider = Slider::$app->sliders->getSliderById($sliderId);

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
		$slider->slides         = $request->getBodyParam('slides');
		$slider->mode           = $request->getBodyParam('mode');
		$slider->speed          = $request->getBodyParam('speed');
		$slider->slideMargin    = $request->getBodyParam('slideMargin');
		$slider->randomStart    = $request->getBodyParam('randomStart');
		$slider->slideSelector  = $request->getBodyParam('slideSelector');
		$slider->infiniteLoop   = $request->getBodyParam('infiniteLoop');
		$slider->captions       = $request->getBodyParam('captions');
		$slider->ticker         = $request->getBodyParam('ticker');
		$slider->tickerHover    = $request->getBodyParam('tickerHover');
		$slider->adaptiveHeight = $request->getBodyParam('adaptiveHeight');
		$slider->video          = $request->getBodyParam('video');
		$slider->responsive     = $request->getBodyParam('responsive');
		$slider->useCss         = $request->getBodyParam('useCss');
		$slider->easing         = $request->getBodyParam('easing');
		$slider->preloadImages  = $request->getBodyParam('preloadImages');
		$slider->touchEnabled   = $request->getBodyParam('touchEnabled');
		$slider->swipeThreshold = $request->getBodyParam('swipeThreshold');
		$slider->adaptiveHeightSpeed  = $request->getBodyParam('adaptiveHeightSpeed');
		$slider->preventDefaultSwipeX = $request->getBodyParam('preventDefaultSwipeX');
		$slider->preventDefaultSwipeX = $request->getBodyParam('preventDefaultSwipeX');
		//Pager
		$slider->pager                = $request->getBodyParam('pager');
		$slider->pagerType            = $request->getBodyParam('pagerType');
		$slider->pagerShortSeparator  = $request->getBodyParam('pagerShortSeparator');
		$slider->pagerSelector        = $request->getBodyParam('pagerSelector');
		$slider->thumbnailPager       = $request->getBodyParam('thumbnailPager');
		//Controls
		$slider->controls             = $request->getBodyParam('controls');
		$slider->nextText             = $request->getBodyParam('nextText');
		$slider->prevText             = $request->getBodyParam('prevText');
		$slider->nextSelector         = $request->getBodyParam('nextSelector');
		$slider->prevSelector         = $request->getBodyParam('prevSelector');
		$slider->autoControls         = $request->getBodyParam('autoControls');
		$slider->startText            = $request->getBodyParam('startText');
		$slider->stopText             = $request->getBodyParam('stopText');
		$slider->autoControlsCombine  = $request->getBodyParam('autoControlsCombine');
		$slider->autoControlsSelector = $request->getBodyParam('autoControlsSelector');
		$slider->keyboardEnabled      = $request->getBodyParam('keyboardEnabled');
		//Auto
		$slider->auto                 = $request->getBodyParam('auto');
		$slider->stopAutoOnClick      = $request->getBodyParam('stopAutoOnClick');
		$slider->pause                = $request->getBodyParam('pause');
		$slider->autoStart            = $request->getBodyParam('autoStart');
		$slider->autoDirection        = $request->getBodyParam('autoDirection');
		$slider->autoHover            = $request->getBodyParam('autoHover');
		$slider->autoDelay            = $request->getBodyParam('autoDelay');
		//Carousel
		$slider->minSlides            = $request->getBodyParam('minSlides');
		$slider->maxSlides            = $request->getBodyParam('maxSlides');
		$slider->moveSlides           = $request->getBodyParam('moveSlides');
		$slider->slideWidth           = $request->getBodyParam('slideWidth');
		$slider->shrinkItems          = $request->getBodyParam('shrinkItems');

		// Save it
		if (!Slider::$app->sliders->saveSlider($slider))
		{
			Craft::$app->getSession()->setError(Slider::t('Couldn’t save slider.'));

			Craft::$app->getUrlManager()->setRouteParams([
					'slider'               => $slider
				]
			);

			return null;
		}

		//lets update the subfolder
		if (!$isNew && $oldHandle != $newHandle)
		{
			if (!Slider::$app->sliders->updateSubfolder($slider, $oldHandle))
			{
				Slider::log("Unable to rename subfolder {$oldHandle} to {$slider->handle}", 'error');
			}
		}

		Craft::$app->getSession()->setNotice(Slider::t('Slider saved.'));

		#$_POST['redirect'] = str_replace('{id}', $form->id, $_POST['redirect']);

		return $this->redirectToPostedUrl($slider);
	}
}
