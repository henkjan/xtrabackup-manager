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


	class scheduledBackup {


		function __construct($id) {
			if(!is_numeric($id)) {
				throw new Exception('scheduledBackup->__construct'."Error: Expected a numeric ID for this object and did not get one.");
			}
			$this->id = $id;
			$this->active = NULL;
			$this->inactive_reason = '';
			$this->log = false;
			$this->isRunning = NULL;
			$this->runningBackups = Array();
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getInfo: '."Error: The ID for this object is not an integer.");
			}


			$dbGetter = new dbConnectionGetter($config);


			$conn = $dbGetter->getConnection($this->log);


			$sql = "SELECT * FROM scheduled_backups WHERE scheduled_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Sets this->active to true or false based on logic.
		function isActive() {

			$info = $this->getInfo();

			// Check first if this scheduled backup is active
			if($info['active'] != 'Y') {

				$this->inactive_reason = 'The Scheduled Backup is not active.';
				return false;
			} else {

				// Then check to make sure that the host is active
				$host = $this->getHost();

				// Poll host being active...
					// If it's not active..
				if( $host->isActive() == false ) {
					$this->inactive_reason = 'The Host is not active.';
					return false;
				} else {
				// Otherwise..
					$this->inactive_reason = NULL;
					return true;
				}


			}
			
		}


		// Get the host object that this scheduledBackup is for	
		function getHost() {

			$info = $this->getInfo();

			$hostGetter = new hostGetter();
			$host = $hostGetter->getById($info['host_id']);

			return $host;

		}


		// Get the volume object that this scheduledBackup is stored on
		function getVolume() {

			$info = $this->getInfo();

			$volumeGetter = new volumeGetter();
			$volume = $volumeGetter->getById($info['backup_volume_id']);

			return $volume;

		}


		// Get the name of the command that should be used for xtrabackup
		// based on the configured mysql_type of this scheduledBackup
		function getXtrabackupBinary() {

			$info = $this->getInfo();

			$mysqlTypeGetter = new mysqlTypeGetter();
			$mysqlTypeGetter->setLogStream($this->log);

			$mysqlType = $mysqlTypeGetter->getById($info['mysql_type_id']);

			$mysqlTypeInfo = $mysqlType->getInfo();

			return $mysqlTypeInfo['xtrabackup_binary'];
		}



		// Return the valid seed of this scheduledBackup or false otherwise
		function getSeed() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getSeed: '."Error: The ID for this object is not an integer.");
			}   

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND type='SEED' AND status='COMPLETED'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getSeed: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows > 1 ) {
				throw new Exception('scheduledBackup->getSeed: '."Error: Found more than one valid seed for this backup. This should not happen.");
			} elseif( $res->num_rows == 1 ) {
				$row = $res->fetch_array();
				$snapshotGetter = new backupSnapshotGetter();
				return $snapshotGetter->getById($row['backup_snapshot_id']);
			} elseif( $res->num_rows == 0 ) {
				return false;
			}

			throw new Exception('scheduledBackup->getSeed: '."Error: Failed to determine if there was a valid seed and return it. This should not happen.");

		}



		// Check to see if there is a running backup entry already for this scheduled backup
		function isRunning() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->isRunning: '."Error: The ID for this object is not an integer.");
			}   
				
			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);
				
			$sql = "SELECT running_backup_id FROM running_backups WHERE scheduled_backup_id=".$this->id;
				
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->isRunning: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}	   

			if( $res->num_rows == 0 ) {
				return false;
			} elseif( $res->num_rows > 0 ) {

				$this->runningBackups = Array();
				while($row = $res->fetch_array() ) {
					$this->runningBackups[] = new runningBackup($row['running_backup_id']);
				}

				$res->free();

				return true;
			}

			
		}


		// Get the most recently completed scheduled backup snapshot
		function getMostRecentCompletedBackupSnapshot() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: The ID for this object is not an integer.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." ORDER BY snapshot_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows != 1 ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Could not find the most recent backup snapshot for Scheduled Backup ID ".$this->id);
			}

			$row = $res->fetch_array();

			$snapshotGetter = new backupSnapshotGetter();
			$snapshot = $snapshotGetter->getById($row['backup_snapshot_id']);

			return $snapshot;
		}


		// Check for COMPLETED backup snapshots under this scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->applyRetentionPolicy: '."Error: The ID for this object is not an integer.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Get info for this scheduledBackup
			$info = $this->getInfo();

			// Build service objects for later use
			$snapshotGetter = new backupSnapshotGetter();
			$snapshotMerger = new backupSnapshotMerger();

			// Check to see if the number of rows we have is more than the number of snapshots we should have at a max
			while( $res->num_rows > $info['snapshots_retained'] ) { 


				// Grab the first row - it is the SEED
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('scheduledBackup->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$this->id);
				}

				$seedSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				// Grab the second row - it is the DELTA to be collapsed.
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('scheduledBackup->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$this->id);
				}
				
				$deltaSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				// Merge them together
				$snapshotMerger->mergeSnapshots($seedSnapshot, $deltaSnapshot);

				// Check to see what merge work is needed now.
				$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

				if( ! ($res = $conn->query($sql) ) ) {
			   		throw new Exception('scheduledBackup->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
		   		}

			}

			return true;

		}


	} // Class: scheduledBackup

?>
