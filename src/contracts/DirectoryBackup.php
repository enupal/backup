<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\contracts;

use enupal\backup\Backup;

class DirectoryBackup extends BackupType
{
    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $pathToTar;

    /**
     * @var string
     */
    public $exclude;

    public function __construct()
    {
        $this->settings = Backup::$app->settings->getSettings();
        $this->pathToTar = Backup::$app->backups->getPathToTar();
    }

    /**
     * @inheritdoc
     */
    public function getBackup()
    {
        $backup = [
            'name' => $this->name,
            'source' => [
                'type' => 'tar',
                'options' => [
                    'path' => $this->path,
                    'forceLocal' => true,
                    'ignoreFailedRead' => true
                ]
            ],
            'target' => [
                'dirname' => $this->dirName,
                'filename' => $this->fileName
            ]
        ];

        if ($this->exclude) {
            $backup['source']['options']['exclude'] = $this->exclude;
        }

        if ($this->pathToTar) {
            $backup['source']['options']['pathToTar'] = $this->pathToTar;
        }

        if ($this->syncs) {
            $backup['syncs'] = $this->syncs;
        }

        if ($this->encrypt) {
            $backup['crypt'] = $this->encrypt;
        }

        // Compress on linux or if tar path is setup
        if (Backup::$app->backups->applyCompress()) {
            $backup['target']['compress'] = 'bzip2';
        }

        return $backup;
    }
}