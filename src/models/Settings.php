<?php
namespace enupal\backup\models;

class Settings extends \craft\base\Model
{
	public $pluginNameOverride = '';
	public $dopboxApi = '';
	public $awsApi = '';
	public $resyncApi = '';
	public $sftpApi = '';
	public $ftpApi = '';
	public $softlayerApi = '';
	//@todo - schedule options
}