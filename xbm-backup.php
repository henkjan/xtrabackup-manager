#!/usr/bin/php
<?php
/*

Copyright 2011 Marin Software

This file is part of Xtrabackup Manager.

Xtrabackup Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

Xtrabackup Manager is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Xtrabackup Manager.  If not, see <http://www.gnu.org/licenses/>.

*/

	/* xbm-backup - Wrapper script to launch backup events. */

	
	require('includes/init.php');

	// Defined options, used for helptext only.
	$optDef = Array(
					'h' => 'Help - Display this help text.',
					's' => 'The Scheduled Backup ID to launch/run.',
					'f' => 'Force the backup to run even if it is not flagged as active.',
					'q' => 'Run in quiet mode - no output except for fatal errors'
				);
	ksort($optDef);

	// Define options and get them from cmdline
	$shortOpts = 'hs:q';
	$options = getOpt($shortOpts);



	if(!isSet($options['q']))
		print("\nxbm-backup.php -- ".XBM_RELEASE_VERSION."\n");

	if(isSet($options['s']) && !is_numeric($options['s']) ) {
		print("\nThe -s option must be numeric.\n");
	}
	if(!isSet($options['s']) ) {
		print("\nThe -s options is required.\n");
	}

	// Catch the -h option first - print help text
	if( isSet($options['h']) || sizeOf($options) == 0 || 
		// or If S options set but not numeric
		(isSet($options['s']) && !is_numeric($options['s']) )  ||
		// or if s option not set
		(!isSet($options['s']) )
	) {
		
		$msg = "\nParameters: \n";
		foreach($optDef as $opt => $desc) {
			$msg .= "  -".$opt."	".$desc."\n";
		}

		print($msg."\n\n");	
		die();

	}

	if(!isSet($options['q']))	
		print("\n");

	// Setup logStream
	$log = new logStream(XBM_MAIN_LOG, false, $config['LOGS']['level']);


	// Fetch the scheduled backup
	$scheduledBackupGetter = new scheduledBackupGetter();
	$scheduledBackupGetter->setLogStream($log);
	
	if( ! ( $scheduledBackup = $scheduledBackupGetter->getById($options['s']) ) ) {
		print("xbm-backup.php: ".$scheduledBackupGetter->error."\n");
		die();
	}


	if( ! ( $scheduledBackupInfo = $scheduledBackup->getInfo() ) ) {
		print("xbm-backup.php: ".$scheduledBackup->error."\n");
		die();
	}

	// If we are not in FORCE mode - check if the scheduled backup is active
	if( ! isSet($options['f']) ) {

		if( ! $scheduledBackup->pollActive() ) {
			print("xbm-backup.php: ".$scheduledBackup->error."\n");
			die();
		}

		if( ! $scheduledBackup->active) {
			print("The specified backup is not active - Reason: ".$scheduledBackup->inactive_reason." -- exiting...\n");
			die();
		} 
			
	}


	// Proceed with the backup!

	/* we're going to create a new backup snapshot,  -- start logging informational stuff to logs/hosts/hostname.log
	create a new object.
	figure out if the new snapshot will be incremental or full
	setup the snapshot settings
	tell the snapshot to take
	final step of the snapshot will be to apply log
	after apply log we should check to see if we exceeded the max number of retained snaps
	then we should create a new snapshot that is a merge of the oldest incremental and full.
	*/

	$snapshotTaker = new backupSnapshotTaker();

	$snapshotTaker->setLogStream($log);

	// If we are in quiet mode, turn off the output to stdout
	if(isSet($options['q']) ) {
		$snapshotTaker->setInfoLogVerbose(false);
	}

	$snapshotTaker->setAlertEmailAddress($config['ALERTS']['email']);

	if( ! $snapshotTaker->takeScheduledBackupSnapshot($scheduledBackup) ) {
	//	print("xbm-backup.php: ".$snapshotTaker->error."\n");
		die();
	}

	
		
?>
