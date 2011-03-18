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


	class host {


		function __construct($id) {
			$this->id = $id;
			$this->active = NULL;
			$this->log = false;
		}

		// Set the logStream to write out to
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get info about this host
		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'host->getInfo: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter($config);


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'host->getInfo: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT * FROM hosts WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'host->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}
	
			$info = $res->fetch_array();
			if(empty($info['last_backup']))
				$info['last_backup'] = 'Never';

			return $info;

		}

		// Get scheduled backups
		function getScheduledBackups() {

			global $config;

			$dbGetter = new dbConnectionGetter($config);

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'host->getScheduledBackups: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'host->getScheduledBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$scheduledBackups = Array();
			while($row = $res->fetch_array() ) {
				$scheduledBackups[] = new scheduledBackup($row['scheduled_backup_id']);
			}

			return $scheduledBackups;

		}

		// Populate this->active with true/false depending on active status of host
		function pollActive() {
			if( ! ($info = $this->getInfo() ) ) {
				$this->error = 'host->pollActive: '.$this->error;
				return false;
			}

			if($info['active'] == 'Y') {
				$this->active = true;
			} else {
				$this->active = false;
			}

			return true;
		}


		// Get the running backups for this host
		function getRunningBackups() {

			global $config;

			if(!is_numeric($this->id)) {
				$this->error = 'host->getRunningBackups: '."Error: The ID for this object is not an integer.";
				return false;
			}


			$dbGetter = new dbConnectionGetter($config);


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'host->getRunningBackups: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT running_backup_id FROM running_backups WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'host->getRunningBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackups[] = new runningBackup($row['running_backup_id']);
			}

			return $runningBackups;

		}

	}

?>
