<?php
namespace enupal\backup\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class FontAwesomeAsset extends AssetBundle
{
	public function init()
	{
		// define the path that your publishable resources live
		$this->sourcePath = '@enupal/backup/resources/';

		// define the dependencies
		// define the relative path to CSS/JS files that should be registered with the page
		// when this asset bundle is registered

		$this->css = [
			'css/font/css/font-awesome.min.css'
		];

		parent::init();
	}
}