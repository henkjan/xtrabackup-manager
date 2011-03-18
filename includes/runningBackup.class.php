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


	class runningBackup {


		function __construct($id = false) {
			$this->id = $id;
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Atempt to initialize a running backup
		function init($host, $scheduledBackup) {

			// create a port finder object
			// portFound = false
			// while portFound = false
			// get available port number
			// attempt to create running backup entry with that port number
			// if success, then portFound = true
			// end while

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'runningBackup->init: '.$dbGetter->error;
				return false;
			}


			$portFinder = new portFinder();

			$attempts = 0;

			if($this->infolog !== false) 
				$this->infolog->write("Attempting to find available ports for use...", XBM_LOG_INFO);

			while( $attempts < 5 ) {

				$attempts++;

				if( ! $portFinder->findAvailablePort() ) {
					$this->error = 'runningBackup->init: '.$portFinder->error;
					return false;
				}


				// If we didn't get a port for some reason, try again
				if( $portFinder->availablePort === false ) {

					if($this->infolog !== false) 
						$this->infolog->write("Attempted to acquire an available port with portFinder, unsuccessfully. Sleeping ".XBM_SLEEP_SECS." secs before trying again...", XBM_LOG_INFO);

					sleep(XBM_SLEEP_SECS);
					continue;
				}


				$sql = "INSERT INTO running_backups (host_id, scheduled_backup_id, port) VALUES (".$host->id.", ".$scheduledBackup->id.", ".$portFinder->availablePort.")";

				if( ! $conn->query($sql) ) {


					// This is hacky as it relies on the order of the keys, but basically...
					// If we get a dupe key error on the port field, we'll try again, otherwise, consider it fatal.
					if($conn->errno == 1063 && stristr($conn->error, 'key 2')) {
						// If log enabled - write info to it...
						if($this->infolog !== false) 
							$this->infolog->write("Attempted to lock port ".$portFinder->availablePort." by creating runningBackup, but somebody snatched it. Sleeping ".XBM_SLEEP_SECS." secs before retry...", XBM_LOG_INFO);

						sleep(XBM_SLEEP_SECS);
						continue;

					} else {
						$this->error = 'runningBackuup->init: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
						return false;
					}
				}

				if($this->infolog !== false) {
					$this->infolog->write("Got lock on port ".$portFinder->availablePort.".", XBM_LOG_INFO);
				}

				$this->id = $conn->insert_id;
				return true;

			}

			if($this->infolog !== false ) {
				$this->infolog->write("Was unable to allocate a port for the backup after $attempts attempts. Giving up!", XBM_LOG_ERROR);
			}

			$this->error = 'runningBackup->init: '."Error: Was unable to allocate a port for the backup after $attempts attempts. Gave up!";
			return false;

		}


		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'runningBackup->getInfo: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'runningBackup->getInfo: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT * FROM running_backups WHERE running_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'runningBackup->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}
	
			$info = $res->fetch_array();

			return $info;

		}


		// Clean up the running backup entry
		function finish() {

			global $config;

			if(!is_numeric($this->id) ) {
				$this->error = 'runningBackup->finish: '."Error: The ID for this object is not an integer.";
				return false;
			}

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'runningBackup->finish: '.$dbGetter->error;
				return false;
			}

			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'runningBackup->finish: '.$this->error;
				return false;
			}


			// If we have a staging tmpdir -- try to remove it
			if( isSet($this->remoteTempDir) && is_object($this->remoteTempDir) ) {

				if( ! $this->remoteTempDir->destroy() ) {
					$this->error = 'runningBackup->finish: '.$this->remoteTempDir->error;
					return false;
				}

			}

			$sql = "DELETE FROM running_backups WHERE running_backup_id=".$this->id;

			if( ! $conn->query($sql) ) {
				$this->error = 'runningBackup->finish: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$this->infolog->write("Released lock on port ".$info['port'].".", XBM_LOG_INFO);

			return true;

		}


		// Set the staging tmpdir in the running backup object
		function getStagingTmpdir() {

			global $config;

			if(!is_numeric($this->id) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '."Error: The ID for this object is not an integer.";
				return false;
			}

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$dbGetter->error;
				return false;
			}


			if( ! ( $info = $this->getInfo() ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$this->error;
				return false;
			}

			// Collect the info we need to connect to the remote host 
			$backupGetter = new scheduledBackupGetter();

			if( ! ( $scheduledBackup = $backupGetter->getById($info['scheduled_backup_id']) ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$backupGetter->error;
				return false;
			}

			if( ! ( $sbInfo = $scheduledBackup->getInfo() ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$scheduledBackup->error;
				return false;
			}

			if( ! ( $host = $scheduledBackup->getHost() ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$scheduledBackup->error;
				return false;
			}

			if( ! ( $hostInfo = $host->getInfo() ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$host->error;
				return false;
			}

			$this->remoteTempDir = new remoteTempDir();

			if( ! ( $tempDir = $this->remoteTempDir->init($hostInfo['hostname'], $sbInfo['backup_user'], $hostInfo['staging_path'], 'xbm-') ) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '.$this->remoteTempDir->error;
				return false;
			}

			// Put the path into the DB

			$sql = "UPDATE running_backups SET staging_tmpdir='".$conn->real_escape_string($tempDir)."' WHERE running_backup_id=".$this->id;

			if( ! $conn->query($sql) ) {
				$this->error = 'runningBackup->getStagingTmpdir: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			return $tempDir;

		}

	}

?>
