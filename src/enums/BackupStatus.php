<?php
namespace enupal\backup\enums;

/**
 * Current status of a Backup
 */
abstract class BackupStatus extends BaseEnum
{
	// Constants
	// =========================================================================

	const Running  = 0;
	const Finished = 1;
}
