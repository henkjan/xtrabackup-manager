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

	class backupSnapshot {


		function __construct($id = NULL) {
			$this->id = $id;
			$this->log = false;
			$this->error = NULL;
			$this->scheduledBackup = NULL;
		}

		function setLogStream($log) {
			$this->log = $log;
		}


		function init($scheduledBackup, $type, $creation_method, $parentId = false) {

			global $config;

			if(!is_numeric($scheduledBackup->id) ) {
				$this->error = 'backupSnapshot->init: '."Error: Expected ScheduledBackup with a numeric ID and did not get one.";
				return false;
			}

			$this->scheduledBackup = $scheduledBackup;

			$dbGetter = new dbConnectionGetter($config);


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'backupSnapshot->init: '.$dbGetter->error;
				return false;
			}


			if( $parentId === false ) {

				$sql = "INSERT INTO backup_snapshots (scheduled_backup_id, type, creation_method) VALUES
						(".$scheduledBackup->id.", '".$conn->real_escape_string($type)."', '".$conn->real_escape_string($creation_method)."' )";

			} else {

				if(!is_numeric($parentId) ) {
					$this->error = 'backupSnapshot->init: '."Error: Expected numeric parent ScheduledBackup ID and did not get one.";
					return false;
				}

				$sql = "INSERT INTO backup_snapshots (scheduled_backup_id, type, creation_method, parent_snapshot_id) VALUES 
						(".$scheduledBackup->id.", '".$conn->real_escape_string($type)."', '".$conn->real_escape_string($creation_method)."',
							".$parentId.")";
			}


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'backupSnapshot->init: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$this->id = $conn->insert_id;

			// Get the path for this snapshot
			if( ! ($path = $this->getPath() ) ) {
				$this->error = 'backupSnapshot->init: '.$this->error;
				return false;
			}

			// Create the dir for the hostname/snapshotId
			if(!mkdir($path, 0700, true) ) {
				$this->error = 'backupSnapshot->init: '."Error: Could not create the directory for this snapshot at ".$path." .";
				return false;
			}

			return true;
		}


		// Return the scheduledBackup parent object for this snapshot.
		function getScheduledBackup() {

            if(!is_object($this->scheduledBackup) ) {
                if( ! ( $info = $this->getInfo() ) ) {
                    $this->error = 'backupSnapshot->getScheduledBackup: '.$this->error;
                    return false;
                }   
                
                $scheduledBackupGetter = new scheduledBackupGetter();
                if( ! ($this->scheduledBackup = $scheduledBackupGetter->getById($info['scheduled_backup_id']) ) ) {
                    $this->error = 'backupSnapshot->getScheduledBackup: '.$this->error;
                    return false;
                }   
            }   


			return $this->scheduledBackup;
	
		}


		// Check to see if the storage volume exists and returns a path for the snapshot.
		function getPath() {


			if( ! ( $scheduledBackup = $this->getScheduledBackup() ) ) {
				$this->error = 'backupSnapshot->getPath: '.$this->error;
				return false;
			}

			// Get the host and info about it
			if( ! ($host = $scheduledBackup->getHost() ) ) {
				$this->error = 'backupSnapshot->getPath: '.$scheduledBackup->error;
				return false;
			}

			if( ! ($hostInfo = $host->getInfo() ) ) {
				$this->error = 'backupSnapshot->getPath: '.$host->error;
				return false;
			}


			// Get the volume and info about it
			if( ! ($volume = $scheduledBackup->getVolume() ) ) {
				$this->error = 'backupSnapshot->getPath: '.$scheduledBackup->error;
				return false;
			}

			if( ! ($volumeInfo = $volume->getInfo() ) ) {
				$this->error = 'backupSnapshot->getPath: '.$volume->error;
				return false;
			}			

			// Check to see that the volume is a directory first
			if( !is_dir($volumeInfo['path']) ) {
				$this->error = 'backupSnapshot->getPath: '."Error: The storage volume at ".$volumeInfo['path']." is not a valid directory.";
				return false;
			}


			return $volumeInfo['path'].'/'.$hostInfo['hostname'].'/'.$this->id;


		}


		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'backupSnapshot->getInfo: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'backupSnapshot->getInfo: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT * FROM backup_snapshots WHERE backup_snapshot_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'backupSnapshot->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}
	
			$info = $res->fetch_array();

			return $info;

		}


		// Change the status of the backup snapshot
		function setStatus($status) {
			
			if(!is_numeric($this->id)) {
				$this->error = 'backupSnapshot->setStatus: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'backupSnapshot->setStatus: '.$dbGetter->error;
				return false;
			}


			$sql = "UPDATE backup_snapshots SET status='".$conn->real_escape_string($status)."' ";

			$sql .= " WHERE backup_snapshot_id=".$this->id;

			if( ! $conn->query($sql) ) {
				$this->error = 'backupSnapshot->setStatus: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			return true;

		}


		// Set the snapshot time of the backup snapshot - uses NOW() if unset.
		function setSnapshotTime($snapshotTime = false) {

            if(!is_numeric($this->id)) {
                $this->error = 'backupSnapshot->setSnapshotTime: '."Error: The ID for this object is not an integer.";
                return false;
            }


            $dbGetter = new dbConnectionGetter();


            if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
                $this->error = 'backupSnapshot->setSnapshotTime: '.$dbGetter->error;
                return false;
            }

			if($snapshotTime === false) {
				$snapshotTime = 'NOW()';
			}

            $sql = "UPDATE backup_snapshots SET snapshot_time='".$conn->real_escape_string($snapshotTime)."' ";

            $sql .= " WHERE backup_snapshot_id=".$this->id;

            if( ! $conn->query($sql) ) {
                $this->error = 'backupSnapshot->setSnapshotTime: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
                return false;
            }

            return true;

		}


		// Completely removes all files from the backup snapshot directory and the directory itself
		function deleteFiles() {

			// Get the path
			if( ! ( $path = $this->getPath() ) ) {
				$this->error = 'backupSnapshot->deleteFiles: '.$this->error;
				return false;
			}

			if( ( strlen($path) == 0 ) || $path == '/' ) {
				$this->error = 'backupSnapshot->deleteFiles: '."Error: Detected unsafe path for this snapshot to attempt to perform recursive delete on. Aborting.";
				return false;
			}

			if(!is_dir($path)) {
				return true;
			}

			$deleter = new recursiveDeleter();
			if( ! $deleter->delTree($path.'/') ) {
				$this->error = 'backupSnapshot->deleteFiles: '.$deleter->error;
				return false;
			}

			return true;
		} 


		// Get the log sequence number position for this backup snapshot
		function getLsn() {

			// Read the to_lsn value from the xtrabackup_checkpoints file in the backup dir

			if( ! ( $path = $this->getPath() ) ) {
				$this->error = 'backupSnapshot->getLsn: '.$this->error;
				return false;
			}
	
			if(!is_file($path.'/xtrabackup_checkpoints')) {
				$this->error = 'backupSnapshot->getLsn: '."Error: Could not find file ".$path."/xtrabackup_checkpoints for log sequence information.";
				return false;
			}

			if( ! ( $file = file_get_contents($path.'/xtrabackup_checkpoints') ) ) {
				$this->error = 'backupSnapshot->getLsn: '."Error: Could not read file ".$path."/xtrabackup_checkpoints for log sequence information.";
				return false;
			}

			if( preg_match('/to_lsn = ([0-9]+:[0-9]+|[0-9]+)/', $file, $matches) == 0 ) {
                $this->error = 'backupSnapshot->getLsn: '."Error: Could find log sequence information in file: ".$path."/xtrabackup_checkpoints";
                return false;
			}

			if( !isSet($matches[1]) || strlen($matches[1]) == 0 ) {
                $this->error = 'backupSnapshot->getLsn: '."Error: Could find log sequence information in file: ".$path."/xtrabackup_checkpoints";
                return false;
			}

			return $matches[1];

		}

		// Reassign any snapshot(s) whose parent snapshot is this snapshot to another snapshot - used when merging snapshots
		function assignChildrenNewParent($parentId) {

			global $config;

            if(!is_numeric($this->id)) {
                $this->error = 'backupSnapshot->assignChildrenNewParent: '."Error: The ID for this object is not an integer.";
                return false;
            }

			if(!is_numeric($parentId) ) {
				$this->error = 'backupSnapshot->assignChildrenNewParent: '."Error: Expected numeric value for new parent to assign to children of this snapshot, but did not get one.";
				return false;
			}

            $dbGetter = new dbConnectionGetter();

            if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
                $this->error = 'backupSnapshot->assignChildrenNewParent: '.$dbGetter->error;
                return false;
            }

            $sql = "UPDATE backup_snapshots SET parent_snapshot_id=".$parentId." WHERE parent_snapshot_id=".$this->id;


            if( ! $conn->query($sql) ) {
                $this->error = 'backupSnapshot->assignChildrenNewParent: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
                return false;
            }

            return true;

			
		}

	}

?>
