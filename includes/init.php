<?php
/*

Copyright 2011 Marin Software

This file is part of XtraBackup Manager.

XtraBackup Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

XtraBackup Manager is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with XtraBackup Manager.  If not, see <http://www.gnu.org/licenses/>.

*/
	
	// Setup global defines that do not depend on anything else
	define('XBM_RELEASE_VERSION', 'XtraBackup Manager v0.5 - Copyright 2011 Marin Software');

	// Log levels, lower is more verbose
	define('XBM_LOG_DEBUG', 0);
	define('XBM_LOG_INFO', 1);
	define('XBM_LOG_ERROR', 2);


	// Number of seconds we sleep between checking stuff
	// Usually to see if we can run the backup 
	define('XBM_SLEEP_SECS', 30);

	// Autodetect some things to use in config.php

	// Use gethostname() function if it exists, otherwise fallback to "hostname" shell cmd.
	if( function_exists('gethostname') ) {
		$XBM_AUTO_HOSTNAME = gethostname();
	} else {
		$XBM_AUTO_HOSTNAME = trim(`hostname`);
	}

	$XBM_AUTO_INSTALLDIR = dirname(dirname(__FILE__)) . '/';

	// Include config and class / function files
	require('config.php');
	require('db.classes.php');
	require('service.classes.php');
	require('host.class.php');
	require('backupVolume.class.php');
	require('scheduledBackup.class.php');
	require('logStream.class.php');
	require('backupRestorer.class.php');
	require('backupSnapshot.class.php');
	require('backupSnapshotTaker.class.php');
	require('runningBackup.class.php');
	require('mysqlType.class.php');
	require('configCSV.class.php');
	require('continuousIncrementalBackupTaker.class.php');
	require('rotatingBackupTaker.class.php');
	require('backupSnapshotGroup.class.php');
	require('genericBackupTaker.class.php');

	// Setup global defines that depend on other stuff
	define('XBM_MAIN_LOG', $config['LOGS']['logdir'].'/xbm.log');
?>
