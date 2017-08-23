<?php
namespace enupal\backup\enums;

/**
 * Current status of a Backup
 */
abstract class BackupStatus extends BaseEnum
{
	// Constants
	// =========================================================================

	const FINISHED = 2;
	const STARTED  = 0;
	const RUNNING  = 1;
	const ERROR    = 3;
}
