<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\web\assets;

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
            'css/font-awesome/css/all.min.css'
        ];

        parent::init();
    }
}