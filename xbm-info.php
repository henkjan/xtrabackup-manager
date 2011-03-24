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



	print("\nxbm-info.php -- ".XBM_RELEASE_VERSION."\n");

	/* -l Option -- List the hostnames */
	if( isSet($options['l']) && !isSet($options['h']) ) {
		print("\nListing Hosts:\n\n");
		$hostGetter = new hostGetter();

		if(  ( $hosts = $hostGetter->getAll() ) === false ) {
			print($hostGetter->error."\n");
			die;
		}

		if(sizeOf($hosts) == 0 ) {
			print("\tNo hosts found.\n");
		} else {
			foreach($hosts as $host) {
				if( ! ( $hostInfo = $host->getInfo() ) ) {
					print($host->error."\n");
					die;
				}
				print("Host ID: ".$hostInfo['host_id']."  Hostname: ".$hostInfo['hostname']."\n");
				print("Description: ".$hostInfo['description']."\n");
				print("Active: ".$hostInfo['active']."  Last Backup: ".$hostInfo['last_backup']."\n");
				print("\n");
			}
		}

		print("\n");
	}


	/* -v Option -- List the Backup volumes */
	if( isSet($options['v']) && !isSet($options['h']) ) {

		print("\nListing Backup Volumes:\n\n");
		$volumeGetter = new volumeGetter();
		if( ( $volumes = $volumeGetter->getAll() )  === false) {
			print($volumeGetter->error."\n");
			die;
		}

		if(sizeOf($volumes) == 0) {
			print("\tNo Backup Volumes found.\n");
		} else {
			foreach($volumes as $volume) {

				if( ! ( $volumeInfo = $volume->getInfo() ) ) {
					print($volume->error."\n");
					die;
				}

				print("Backup Volume ID: ".$volumeInfo['backup_volume_id']."  Name: ".$volumeInfo['name']."\n");
				print("Path: ".$volumeInfo['path']."\n");
				print("\n"); 
			}
		}
		print("\n");
	}




	if( isSet($options['h']) || sizeOf($options) == 0) {
		
		$msg = "\nParameters: \n";
		foreach($optDef as $opt => $desc) {
			$msg .= "  -".$opt."	".$desc."\n";
		}

		print($msg."\n\n");	
		die();

	}




?>
