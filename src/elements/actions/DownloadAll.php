<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\helpers\Json;

/**
 * Allows download all files from the index element page
 *
 */
class DownloadAll extends ElementAction
{
	// Properties
	// =========================================================================

	/**
	 * @var string|null The trigger label
	 */
	public $label;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		if ($this->label === null) {
			$this->label = Craft::t('app', 'Download All');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getTriggerLabel(): string
	{
		return $this->label;
	}

	/**
	 * @inheritdoc
	 */
	public function getTriggerHtml()
	{
		$type = Json::encode(static::class);
		$csrf = "".Craft::$app->request->getCsrfToken();
		/**/
		$js = <<<EOD
(function()
{
	var trigger = new Craft.ElementActionTrigger({
		type: {$type},
		batch: false,
		validateSelection: function(\$selectedItems)
		{
			return \$selectedItems.find('.element').data('status') == 'green';
		},
		activate: function(\$selectedItems)
		{
			var \$element = \$selectedItems.find('.element:first');

			var postData = {
				backupId: \$element.data('id'),
				type: 'all',
				CRAFT_CSRF_TOKEN: "{$csrf}"
			};

			// Loads the field settings template file, as well as all the resources that come with it

			Craft.postActionRequest('enupal-backup/backups/download', postData, $.proxy(function(response, textStatus)
			{
				if (textStatus === 'success')
				{
					var url = Craft.getActionUrl('enupal-backup/backups/download-backup-file', {'backupFilePath': response.backupFile});
					$.fileDownload(url)
					.done(function () { Craft.cp.displayNotice(Craft.t('enupal-backup','Download will start in 5 seconds')); })
					.fail(function () { Craft.cp.displayError(Craft.t('enupal-backup','Something went wrong with the download: '+url)); });

				}
				else
				{
					//this.destroy();
				}
			}, this));
		}
	});
})();
EOD;

		Craft::$app->getView()->registerAssetBundle('enupal\\backup\\assetbundles\\DownloadFileAsset');

		Craft::$app->getView()->registerJs($js);
	}
}
