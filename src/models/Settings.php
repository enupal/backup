<?php
namespace enupal\backup\models;

class Settings extends \craft\base\Model
{
	public $pluginNameOverride = '';
	// Plugins
	public $enablePlugins  = 0;
	public $plugins = '';
	// Templates
	public $enableTemplates  = 0;
	public $excludeTemplates = 'cpresources,';
	// Local Volumes
	public $enableLocalVolumes = 0;
	public $volumes            = '';
	// Dropbox 	Api
	public $dopboxToken = '';
	public $dopboxPath  = '';
	// Amazon S3 Api
	public $amazonKey    = '';
	public $amazonSecret = '';
	public $amazonBucket = '';
	public $amazonRegion = '';
	public $amazonPath   = '';
	// FTP or SFTP
	public $ftpType = 'ftp';
	public $ftpHost = '';
	public $ftpUser = '';
	public $ftpPassword = '';
	public $ftpPath = '';
}