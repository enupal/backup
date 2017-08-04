<?php
namespace enupal\backup\enums;

/**
 * Current status of a Backup
 */
abstract class BackupStatus extends BaseEnum
{
	// Constants
	// =========================================================================

	const STARTED  = 0;
	const RUNNING  = 1;
	const FINISHED = 2;
	const ERROR    = 3;
}
