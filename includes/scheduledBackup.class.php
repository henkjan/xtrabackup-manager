<?php
/*

Copyright 2011 Marin Software

This file is part of Xtrabackup Manager.

Xtrabackup Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
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
			$this->id = $id;
			$this->active = NULL;
			$this->hasValidSeed = NULL;
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
				$this->error = 'scheduledBackup->getInfo: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter($config);


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackup->getInfo: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT * FROM scheduled_backups WHERE scheduled_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackup->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Sets this->active to true or false based on logic.
		function pollActive() {

			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'scheduledBackup->pollActive: '.$this->error;
				return false;
			}

			// Check first if this scheduled backup is active
			if($info['active'] != 'Y') {

				$this->inactive_reason = 'The Scheduled Backup is not active.';
				$this->active = false;
				return true;
			} else {

				// Then check to make sure that the host is active
				if( ! ( $host = $this->getHost() ) ) {
					$this->error = 'scheduledBackup->pollActive: '.$this->error;
					return false;
				}

				// Poll host being active...
				if( ! $host->pollActive() ) {
					$this->error = 'scheduledBackup->pollActive: '.$host->error;
					return false;
				} else {
					// If it's not active..
					if( $host->active == false ) {
						$this->active = false;
						$this->inactive_reason = 'The Host is not active.';
						return true;
					} else {
					// Otherwise..
						$this->active = true;
						$this->inactive_reason = NULL;
						return true;
					}

				}

			}
			
		}


		// Get the host object that this scheduledBackup is for	
		function getHost() {

			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'scheduledBackup->getHost: '.$this->error;
				return false;
			}

			$hostGetter = new hostGetter();
			if( ! ( $host = $hostGetter->getById($info['host_id']) ) ) {
				$this->error = 'scheduledBackup->getHost: '.$this->error;
				return false;
			}

			return $host;

		}


		// Get the volume object that this scheduledBackup is stored on
		function getVolume() {

			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'scheduledBackup->getVolume: '.$this->error;
				return false;
			}

			$volumeGetter = new volumeGetter();
			if( ! ( $volume = $volumeGetter->getById($info['backup_volume_id']) ) ) {
				$this->error = 'scheduledBackup->getVolume: '.$this->error;
				return false;
			}

			return $volume;

		}


		// Get the name of the command that should be used for xtrabackup
		// based on the configured mysql_type of this scheduledBackup
		function getXtrabackupBinary() {

			if( ! ($info = $this->getInfo() ) ) {
				$this->error = 'scheduledBackup->getXtrabackupBinary: '.$this->error;
				return false;
			}


			$mysqlTypeGetter = new mysqlTypeGetter();
			$mysqlTypeGetter->setLogStream($this->log);

			if( ! ( $mysqlType = $mysqlTypeGetter->getById($info['mysql_type_id']) ) ) {
				$this->error = 'scheduledBackup->getXtrabackupBinary: '.$mysqlTypeGetter->error;
				return false;
			}

			if( ! ( $mysqlTypeInfo = $mysqlType->getInfo() ) ) {
				$this->error = 'scheduledBackup->getXtrabackupBinary: '.$mysqlType->error;
				return false;
			}

			return $mysqlTypeInfo['xtrabackup_binary'];
		}



		// Find out if this scheduledBackup has a valid seed - put answer in hasValidSeed object var
		// Puts the the backupSnapshot of the seed into the object var seedBackupSnapshot
		function pollHasValidSeed() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'scheduledBackup->pollHasValidSeed: '."Error: The ID for this object is not an integer.";
				return false;
			}   

			$dbGetter = new dbConnectionGetter($config);

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackup->pollHasValidSeed: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND type='SEED' AND status='COMPLETED'";


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackup->pollHasValidSeed: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if( $res->num_rows > 1 ) {
				$this->error = 'scheduledBackup->pollHasValidSeed: '."Error: Found more than one valid seed for this backup. This should not happen.";
				return false;
			} elseif( $res->num_rows == 1 ) {
				$row = $res->fetch_array();
				$snapshotGetter = new backupSnapshotGetter();
				if( ! ( $this->seedBackupSnapshot = $snapshotGetter->getById($row['backup_snapshot_id']) ) ) {
					$this->error = 'scheduledBackup->pollHasValidSeed: '.$snapshotGetter->error;
					return false;
				}
				$this->hasValidSeed = true;
			} elseif( $res->num_rows == 0 ) {
				$this->hasValidSeed = false;
			}


			return true;

		}



		// Check to see if there is a running backup entry already for this scheduled backup
		function pollIsRunning() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'scheduledBackup->pollIsRunning: '."Error: The ID for this object is not an integer.";
				return false;
			}   
				
			$dbGetter = new dbConnectionGetter($config);

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackup->pollIsRunning: '.$dbGetter->error;
				return false;
			}	   
				

			$sql = "SELECT running_backup_id FROM running_backups WHERE scheduled_backup_id=".$this->id;
				
			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackup->pollIsRunning: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}	   

			if( $res->num_rows == 0 ) {
				$this->isRunning = false;
			} elseif( $res->num_rows > 0 ) {

				$this->isRunning = true;
				$this->runningBackups = Array();
				while($row = $res->fetch_array() ) {
					$this->runningBackups[] = new runningBackup($row['running_backup_id']);
				}

				$res->free();

			}

			$conn->close();
			return true;
			
		}


		// Get the most recently completed scheduled backup snapshot
		function getMostRecentCompletedBackupSnapshot() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: The ID for this object is not an integer.";
				return false;
			}

			$dbGetter = new dbConnectionGetter($config);

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackup->getMostRecentCompletedBackupSnapshot: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." ORDER BY snapshot_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if( $res->num_rows != 1 ) {
				$this->error = 'scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Could not find the most recent backup snapshot for Scheduled Backup ID ".$this->id;
				return false;
			}

			$row = $res->fetch_array();

			$snapshotGetter = new backupSnapshotGetter();
			if( ! ( $snapshot = $snapshotGetter->getById($row['backup_snapshot_id']) ) ) {
				$this->error = 'scheduledBackup->getMostRecentCompletedBackupSnapshot: '.$snapshotGetter->error;
				return false;
			}

			return $snapshot;
		}


		// Check for COMPLETED backup snapshots under this scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'scheduledBackup->applyRetentionPolicy: '."Error: The ID for this object is not an integer.";
				return false;
			}

			$dbGetter = new dbConnectionGetter($config);

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackup->applyRetentionPolicy: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackup->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			// Get info for this scheduledBackup
			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'scheduledBackup->applyRetentionPolicy: '.$this->error;
				return false;
			}

			// Build service objects for later use
			$snapshotGetter = new backupSnapshotGetter();
			$snapshotMerger = new backupSnapshotMerger();

			// Check to see if the number of rows we have is more than the number of snapshots we should have at a max
			while( $res->num_rows > $info['snapshots_retained'] ) { 


				// Grab the first row - it is the SEED
				if( ! ( $row = $res->fetch_array() ) ) {
					$this->error = 'scheduledBackup->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$this->id;
				}

				if( ! ( $seedSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] ) ) ) {
					$this->error = 'scheduledBackup->applyRetentionPolicy: '.$snapshotGetter->error;
					return false;
				}

				// Grab the second row - it is the DELTA to be collapsed.
				if( ! ( $row = $res->fetch_array() ) ) {
					$this->error = 'scheduledBackup->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$this->id;
				}
				
				if( ! ( $deltaSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] ) ) ) {
					$this->error = 'scheduledBackup->applyRetentionPolicy: '.$snapshotGetter->error;
					return false;
				}

				// Merge them together
				if( ! $snapshotMerger->merge($seedSnapshot, $deltaSnapshot) ) {
					$this->error = 'scheduledBackup->applyRetentionPolicy: '.$snapshotMerger->error;
					return false;
				}

				// Check to see what merge work is needed now.
				$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

				if( ! ($res = $conn->query($sql) ) ) {
			   		$this->error = 'scheduledBackup->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
					return false;
		   		}

			}

			return true;

		}


	} // Class: scheduledBackup

?>
