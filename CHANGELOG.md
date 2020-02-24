# Enupal Backup Changelog

## 1.3.6 - 2020.02.24
### Added
- Added the `runJobInBackground` setting

## 1.3.5 - 2019.09.20
### Fixed
- Fixed failed enupal backup job tasks

## 1.3.4 - 2019.08.26
### Fixed
- Fixed stalled enupal-backup tasks

## 1.3.3 - 2019.08.07
### Improved
- Improved stalling "running" backups

## 1.3.2 - 2019.08.07
### Improved
- Improved "running" backups

## 1.3.1 - 2019.07.11
### Added
- Added support for Craft 3.2

## 1.3.0 - 2019.07.07
### Added
- Added useCurl setting
- Added support for project config and environmental variables

### Improved
- Improved log behavior

## 1.2.12 - 2019.05.04
### Added
- Added Port to FTP settings

## 1.2.11 - 2019.04.16
### Improved
- Improved Enupal Backup log messages

## 1.2.10 - 2019.04.11
### Improved
- Improved Enupal Backup process and log messages

## 1.2.8 - 2019.04.08
### Improved
- Improved Enupal Backup process on linux servers

## 1.2.7 - 2019.04.07
### Improved
- Improved Enupal Backup process

## 1.2.6 - 2019.02.02
### Added
- Added `$backup` to the Notification email event. [More info](https://enupal.com/craft-plugins/enupal-backup/docs/development/events)

## 1.2.5 - 2019.01.22
### Fixed
- Fixed installation error on Craft 3.1

## 1.2.4 - 2018.12.22
### Fixed
- Fixed `Column not found: 1054 Unknown column 'settings'` error on Craft 3.1

## 1.2.3 - 2018.12.20
### Fixed
- Fixed error when installing or updating the plugin

## 1.2.2 - 2018.12.18
### Added
- Added the `Backup::$app->backups->processPendingBackups()` method. Called on Webhook calls and control panel requests

## 1.2.1 - 2018.11.06
### Improved
- Improved Enupal Backup process

## 1.2.0 - 2018.10.30
### Added
- Added support to Google Drive

### Improved
- Improved sync error messages on view backup page

## 1.1.12 - 2018.10.23
### Added
- Added compress with bzip2 setting to database

## 1.1.11 - 2018.09.25
### Improved
- Improved code conventions and fixed bug where missing param.

## 1.1.10 - 2018.09.25
### Improved
- Improved code conventions.

## 1.1.9 - 2018.09.25
### Added
- Added apply compress setting

## 1.1.8 - 2018.08.16
### Fixed
- Fixed issue with Craft CMS 3.0.20

## 1.1.7 - 2018.07.13
### Added
- Added template overrides to notifications
- Added  `beforeSendNotificationEmail` event
- Added migration to finish all pending backups that stuck in progress for versions less than 1.1.5

## 1.1.6 - 2018.07.09
### Added
- Added primarySiteUrl setting to avoid problems in console commands
### Fixed
- Fixed bug when adding PgDump Path
- Fixed bug where finished webhook was not working on console commands

## 1.1.5 - 2018.07.02
### Added
- Added support to backup the Web Root directory.

## 1.1.4 - 2018.06.01
### Improved
- Improved readme file

## 1.1.3 - 2018.06.01
### Fixed
- Fixed scenario where Config Files download button should not be displayed

## 1.1.2 - 2018.05.31
### Added
- Added Config Files as option to backup
- Added Max Execution setting

### Fixed
- Fixed bug where if added more that one asset just taken the last one
- Fixed bug where if the Asset volume was using the `@webroot` alias in the path the asset was ignored

## 1.0.13 - 2018.05.03
### Improved
- Improved Dropbox validation and instructions.

## 1.0.12 - 2018.04.27
### Fixed
- Fixed bug related to reflection class not found in some scenarios. 

## 1.0.11 - 2018.04.10
### Improved
- Improved general performance

## 1.0.10 - 2018.04.09
### Improved
- Improved Amazon S3 upload files

## 1.0.9 - 2018.04.03
### Added
- Added Plugin name override setting

### Improved
- Improved code inspections

## 1.0.8 - 2018.02.22
### Added
- Added PSR2 support
- Added refresh elements index after backup

### Improved
- Improved webhook example

## 1.0.7 - 2018.01.16
### Improved
- Improved code conventions

### Updated
- Updated phpbu to 5.0.9

## 1.0.6 - 2018.01.11
### Fixed
- Fixed bug on error modal window
### Updated
- Updated sidebar to craftcms styles

## 1.0.5 - 2017.12.13
### Added
- Added require Craft cms rc1 or higher
### Improved
- Improved UI on view backup

## 1.0.4 - 2017.12.12
### Improved
- Improved install migration

## 1.0.3 - 2017.12.05
### Improved
- Improved code conventions

## 1.0.2 - 2017.12.05
### Improved
- Improved delete logs backup file after delete element

## 1.0.1 - 2017.12.03
### Fixed
- Fixed bug with `tablePrefix` query

## 1.0.0 - 2017.12.03
### Added
- Initial release