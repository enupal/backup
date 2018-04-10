<?php
/**
 * EnupalBackup plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2017 Enupal
 */

namespace enupal\backup\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use yii\base\ErrorHandler;
use craft\helpers\UrlHelper;
use craft\elements\actions\Delete;

use enupal\backup\elements\db\BackupQuery;
use enupal\backup\records\Backup as BackupRecord;
use enupal\backup\enums\BackupStatus;
use enupal\backup\Backup as BackupPlugin;

/**
 * Backup represents a entry element.
 */
class Backup extends Element
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $backupId;

    /**
     * @var string
     */
    public $time;

    /**
     * @var string
     */
    public $databaseFileName;

    /**
     * @var string
     */
    public $databaseSize;

    /**
     * @var string
     */
    public $assetFileName;

    /**
     * @var string
     */
    public $assetSize;

    /**
     * @var string
     */
    public $templateFileName;

    /**
     * @var string
     */
    public $templateSize;

    /**
     * @var string
     */
    public $logFileName;

    /**
     * @var string
     */
    public $logSize;

    /**
     * @var integer
     */
    public $backupStatusId = BackupStatus::RUNNING;

    /**
     * @var bool
     */
    public $aws = 0;

    /**
     * @var bool
     */
    public $dropbox = 0;

    /**
     * @var bool
     */
    public $rsync = 0;

    /**
     * @var bool
     */
    public $ftp = 0;

    /**
     * @var bool
     */
    public $softlayer = 0;

    /**
     * @var bool
     */
    public $isEncrypted = 0;

    /**
     * @var string
     */
    public $logMessage;

    /**
     * Returns the field context this element's content uses.
     *
     * @access protected
     * @return string
     */
    public function getFieldContext(): string
    {
        return 'enupalBackup:'.$this->id;
    }

    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return BackupPlugin::t('Backups');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'backups';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'enupal-backup/backup/view/'.$this->id
        );
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function __toString()
    {
        return $this->backupId;
    }

    /**
     * @inheritdoc
     *
     * @return BackupQuery The newly created [[BackupQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new BackupQuery(get_called_class());
    }

    /**
     *
     * @return string|null
     */
    public function getStatus()
    {
        $statusId = $this->backupStatusId;

        $colors = BackupPlugin::$app->backups->getColorStatuses();

        return $colors[$statusId];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => BackupPlugin::t('All Backups'),
            ]
        ];

        $statuses = BackupStatus::getConstants();

        $colors = BackupPlugin::$app->backups->getColorStatuses();

        $sources[] = ['heading' => BackupPlugin::t("Backup Status")];

        foreach ($statuses as $code => $status) {
            if ($status != BackupStatus::STARTED) {
                $key = 'backupStatusId:'.$status;
                $sources[] = [
                    'status' => $colors[$status],
                    'key' => $key,
                    'label' => ucwords(strtolower($code)),
                    'criteria' => ['backupStatusId' => $status]
                ];
            }
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Delete
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => BackupPlugin::t('Are you sure you want to delete the selected backups?'),
            'successMessage' => BackupPlugin::t('Backups deleted.'),
        ]);

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['backupId'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        $attributes = [
            'elements.dateCreated' => BackupPlugin::t('Date Created')
        ];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [];
        $attributes['backupId'] = ['label' => BackupPlugin::t('Backup Id')];
        $attributes['size'] = ['label' => BackupPlugin::t('Size')];
        $attributes['dateCreated'] = ['label' => BackupPlugin::t('Date')];
        $attributes['status'] = ['label' => BackupPlugin::t('Status')];

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = ['backupId', 'size', 'dateCreated', 'status'];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'size':
                {
                    return $this->getTotalSize();
                }
            case 'status':
                {
                    $message = $this->backupStatusId == BackupStatus::STARTED ?
                        BackupPlugin::t('Started') :
                        BackupPlugin::t('Not defined');

                    $encryted = '&nbsp;<i class="fa fa-lock" aria-hidden="true"></i>';

                    if ($this->backupStatusId == BackupStatus::FINISHED) {
                        $message = '<i class="fa fa-check-square-o" aria-hidden="true"></i>';
                    } else if ($this->backupStatusId == BackupStatus::RUNNING) {
                        $message = '<i class="fa fa-circle-o-notch fa-spin fa fa-fw"></i><span class="sr-only">Loading...</span>';
                    } else if ($this->backupStatusId == BackupStatus::ERROR) {
                        $message = '<i class="fa fa-times" aria-hidden="true"></i>';
                    }

                    if ($this->isEncrypted) {
                        $message .= $encryted;
                    }

                    return $message;
                }
            case 'dateCreated':
                {
                    return $this->dateCreated->format("Y-m-d H:i");
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    public function getDatabaseFile()
    {
        $base = BackupPlugin::$app->backups->getDatabasePath();

        if (!$this->databaseFileName) {
            return null;
        }

        return $base.$this->databaseFileName;
    }

    public function getTemplateFile()
    {
        $base = BackupPlugin::$app->backups->getTemplatesPath();

        if (!$this->templateFileName) {
            return null;
        }

        return $base.$this->templateFileName;
    }

    public function getLogFile()
    {
        $base = BackupPlugin::$app->backups->getLogsPath();

        if (!$this->logFileName) {
            return null;
        }

        return $base.$this->logFileName;
    }

    public function getAssetFile()
    {
        $base = BackupPlugin::$app->backups->getAssetsPath();

        if (!$this->assetFileName) {
            return null;
        }

        return $base.$this->assetFileName;
    }


    public function getTotalSize()
    {
        $total = 0;

        if ($this->assetSize) {
            $total += $this->assetSize;
        }

        if ($this->templateSize) {
            $total += $this->templateSize;
        }

        if ($this->databaseSize) {
            $total += $this->databaseSize;
        }

        if ($this->logSize) {
            $total += $this->logSize;
        }

        if ($total == 0) {
            return "";
        }

        return BackupPlugin::$app->backups->getSizeFormatted($total);
    }

    /**
     * @inheritdoc
     * @throws \Exception if reasons
     */
    public function afterSave(bool $isNew)
    {
        // Get the Backup record
        if (!$isNew) {
            $record = BackupRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid Backup ID: '.$this->id);
            }
        } else {
            $record = new BackupRecord();
            $record->id = $this->id;
        }

        $record->backupId = $this->backupId;
        $record->time = $this->time;
        $record->databaseFileName = $this->databaseFileName;
        $record->databaseSize = $this->databaseSize;
        $record->assetFileName = $this->assetFileName;
        $record->assetSize = $this->assetSize;
        $record->templateFileName = $this->templateFileName;
        $record->templateSize = $this->templateSize;
        $record->logFileName = $this->logFileName;
        $record->logSize = $this->logSize;
        $record->backupStatusId = $this->backupStatusId;
        $record->aws = $this->aws;
        $record->dropbox = $this->dropbox;
        $record->rsync = $this->rsync;
        $record->ftp = $this->ftp;
        $record->softlayer = $this->softlayer;
        $record->isEncrypted = $this->isEncrypted;
        $record->logMessage = $this->logMessage;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        // Let's delete all the info
        $files = [];
        $files[] = $this->getDatabaseFile();
        $files[] = $this->getTemplateFile();
        $files[] = $this->getAssetFile();
        $files[] = $this->getLogFile();
        $files[] = BackupPlugin::$app->backups->getLogPath($this->backupId);

        foreach ($files as $file) {
            if ($file) {
                if (file_exists($file)) {
                    unlink($file);
                } else {
                    // File not found.
                    BackupPlugin::error(BackupPlugin::t('Unable to delete the file: '.$file));
                }
            }
        }

        return true;
    }

    public function getStatusName()
    {
        $statuses = BackupStatus::getConstants();

        $statuses = array_flip($statuses);

        return ucwords(strtolower($statuses[$this->backupStatusId]));
    }
}