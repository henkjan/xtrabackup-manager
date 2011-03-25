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


	require('includes/init.php');
/*
	// Defined options, used for helptext only.
	$optDef = Array(
					'h' => 'Help - Display this help text.',
					'l' => 'List the hosts in XBM.',
					'v' => 'List the backup volumes in XBM.'//,
					//'s' => 'Lists all scheduled backups.'
				);
	ksort($optDef);

	// Define options and get them from cmdline
	$shortOpts = 'hlvs';
	$options = getOpt($shortOpts);
*/


	print("\nxbm-flush.php -- ".XBM_RELEASE_VERSION."\n\n");

	print("Flushing backup schedule to crontab for ".$config['SYSTEM']['user']." user...\n\n");
	$cronFlusher = new cronFlusher();
	
	//$cronFlusher->flushSchedule();
	if( ! $cronFlusher->flushSchedule() ) {
		print("Encoutnered a problem while flushing: ".$cronFlusher->error."\n\n");
		die();
	}

	print("Completed OK!\n\n");




?>
