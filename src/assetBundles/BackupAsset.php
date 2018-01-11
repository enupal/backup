<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BackupAsset extends AssetBundle
{
	public function init()
	{
		// define the path that your publishable resources live
		$this->sourcePath = '@enupal/backup/resources/';

		// define the dependencies
		$this->depends = [
			CpAsset::class,
		];

		// define the dependencies
		// define the relative path to CSS/JS files that should be registered with the page
		// when this asset bundle is registered
		$this->js = [
			'js/enupalbackup.js',
			'js/bootstrap.min.js',
		];

		$this->css = [
			'css/enupalbackup.css',
			'css/bootstrap-iso.css'
		];

		parent::init();
	}
}