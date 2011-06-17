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


	class continuousIncrementalBackupTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->infologVerbose = true;
		}

		// Set the logStream for general / debug xbm output
		function setLogStream($log) {
			$this->log = $log;
		}

		// Set the logStream for informational output
		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Set whether or not the info log logStream should write to stdout
		function setInfoLogVerbose($bool) {
			$this->infologVerbose = $bool;
		}

		// Set the time thie backup was launched
		function setLaunchTime($launchTime) {
			$this->launchTime = $launchTime;
		}

		function validateParams($params) {

			// max_snapshots must be set
			if(!isSet($params['max_snapshots'])) {
				throw new Exception('continuousIncrementalBackupTaker->validateParams: '."Error: max_snapshots must be configured when using continuous incremental backups.");
			}

			// max_snapshots must be numeric
			if(!is_numeric($params['max_snapshots'])) {
				throw new Exception('continuousIncrementalBackupTaker->validateParams: '."Error: max_snapshots must be numeric.");
			}

			// max_snapshots must be >=1
			if($params['max_snapshots'] < 1) {
				throw new Exception('continuousIncrementalBackupTaker->validateParams: '."Error: max_snapshots must be greater than or equal to 1.");
			}

			return true;

		}

		// The main functin of this class - take the snapshot for a scheduled backup
		// Takes a scheduledBackup object as a param
		function takeScheduledBackupSnapshot ( $scheduledBackup = false ) {

			global $config;

			// Quick input validation...
			if($scheduledBackup === false ) {
				throw new Exception('continuousIncrementalBackupTaker->takeScheduledBackupSnapshot: '."Error: Expected a scheduledBackup object to be passed to this function and did not get one.");
			}

			// First fetch info to know what we're backing up

			// Validate the parameters of this backup, before we proceed.
			$params = $scheduledBackup->getParameters();
			$this->validateParams($params);

			// Get info on the backup
			$sbInfo = $scheduledBackup->getInfo();

			// Get the host of the backup
			$sbHost = $scheduledBackup->getHost();

			$hostInfo = $sbHost->getInfo();

			// Setup to write to host log
			if(!is_object($this->infolog)) {
				$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', $this->infologVerbose, $config['LOGS']['level']);
				$this->setInfoLogStream($infolog);
			}


			$backupTaker = new genericBackupTaker();
			$backupTaker->setInfoLogStream($this->infolog);


			$sbGroups = $scheduledBackup->getSnapshotGroupsNewestToOldest();

			// There should only be one group...
			if(sizeOf($sbGroups) > 1) {
				throw new Exception('continuousIncrementalBackupTaker->takeScheduledBackupSnapshot: '."Error: Found more than one snapshot group for a backup using continuous incremental strategy.");
			}

			// Find if there is a seed..
			$seedSnap = $sbGroups[0]->getSeed();

			// If this group has a seed snapshot, then take incremental...
			if($seedSnap) {
				$backupTaker->takeIncrementalBackupSnapshot($scheduledBackup, $sbGroups[0], $seedSnap );
			// Otherwise take a FULL backup
			} else {
				$backupTaker->takeFullBackupSnapshot($scheduledBackup, $sbGroups[0]);
			}

			return true;

		} // end takeScheduledBackupSnapshot


		// Check for COMPLETED backup snapshots under the scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy( $scheduledBackup = false) {

			global $config;

			$this->infolog->write("Checking to see if any snapshots need to be merged into the seed backup.", XBM_LOG_INFO);

			if(!is_object($scheduledBackup)) {
				throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: This function requires a scheduledBackup object as a parameter.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$scheduledBackup->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Get info for this scheduledBackup
			$info = $scheduledBackup->getInfo();

			// Get the params/options for this scheduledBackup
			$params = $scheduledBackup->getParameters();
			// Validate them
			$this->validateParams($params);

			// Build service objects for later use
			$snapshotGetter = new backupSnapshotGetter();
			$snapshotMerger = new backupSnapshotMerger();

			// Check to see if the number of rows we have is more than the number of snapshots we should have at a max
			while( $res->num_rows > $params['max_snapshots'] ) {

				// Grab the first row - it is the SEED
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$scheduledBackup->id);
				}

				$seedSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				// Grab the second row - it is the DELTA to be collapsed.
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$scheduledBackup->id);
				}

				$deltaSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				$this->infolog->write("Merging deltas in Backup Snapshot ID #".$deltaSnapshot->id." with Backup Snapshot ID #".$seedSnapshot->id.".", XBM_LOG_INFO);

				// Merge them together
				$snapshotMerger->mergeSnapshots($seedSnapshot, $deltaSnapshot);

				// Check to see what merge work is needed now.
				$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$scheduledBackup->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

				if( ! ($res = $conn->query($sql) ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
				}

			}

			return true;

		}


	}

?>
