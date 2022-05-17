<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class BackupQuery extends ElementQuery
{

    // General - Properties
    // =========================================================================
    public mixed $id;
    public mixed $dateCreated;
    public $backupId;
    public $backupStatusId;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function backupId($value)
    {
        $this->backupId = $value;
    }

    /**
     * @inheritdoc
     */
    public function getBackupId()
    {
        return $this->backupId;
    }


    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'enupalbackup_backups.dateCreated';
        }

        parent::__construct($elementType, $config);
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('enupalbackup_backups');

        $this->query->/** @scrutinizer ignore-call */ select([
            'enupalbackup_backups.backupId',
            'enupalbackup_backups.time',
            'enupalbackup_backups.databaseFileName',
            'enupalbackup_backups.databaseSize',
            'enupalbackup_backups.assetFileName',
            'enupalbackup_backups.assetSize',
            'enupalbackup_backups.templateFileName',
            'enupalbackup_backups.templateSize',
            'enupalbackup_backups.webFileName',
            'enupalbackup_backups.webSize',
            'enupalbackup_backups.configFileName',
            'enupalbackup_backups.configSize',
            'enupalbackup_backups.logFileName',
            'enupalbackup_backups.logSize',
            'enupalbackup_backups.backupStatusId',
            'enupalbackup_backups.aws',
            'enupalbackup_backups.dropbox',
            'enupalbackup_backups.rsync',
            'enupalbackup_backups.ftp',
            'enupalbackup_backups.softlayer',
            'enupalbackup_backups.googleDrive',
            'enupalbackup_backups.logMessage',
            'enupalbackup_backups.isEncrypted',
        ]);

        if ($this->backupId) {
            $this->subQuery->/** @scrutinizer ignore-call */ andWhere(Db::parseParam(
                'enupalbackup_backups.backupId', $this->backupId)
            );
        }

        if ($this->backupStatusId) {
            $this->subQuery->andWhere(Db::parseParam(
                'enupalbackup_backups.backupStatusId', $this->backupStatusId)
            );
        }

        if ($this->orderBy !== null && empty($this->orderBy) && !$this->structureId && !$this->fixedOrder) {
            $this->orderBy = 'elements.dateCreated desc';
        }

        return parent::beforePrepare();
    }
}
