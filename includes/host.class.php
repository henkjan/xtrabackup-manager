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


	class host {


		function __construct($id) {
			if(!is_numeric($id) ) {
				throw new Exception('host->__construct: '."Error: The ID for this object is not an integer.");
			}
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
				throw new Exception('host->getInfo: '."Error: The ID for this object is not an integer.");
			}


			$dbGetter = new dbConnectionGetter($config);


			$conn = $dbGetter->getConnection($this->log);


			$sql = "SELECT * FROM hosts WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('host->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Get scheduled backups
		function getScheduledBackups() {

			global $config;

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);


			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('host->getScheduledBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$scheduledBackups = Array();
			while($row = $res->fetch_array() ) {
				$scheduledBackups[] = new scheduledBackup($row['scheduled_backup_id']);
			}

			return $scheduledBackups;

		}

		// Return true or false 
		function isActive() {

			$info = $this->getInfo();

			if($info['active'] == 'Y') {
				return true;
			} else {
				return false;
			}

		}


		// Get the running backups for this host
		function getRunningBackups() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('host->getRunningBackups: '."Error: The ID for this object is not an integer.");
			}


			$dbGetter = new dbConnectionGetter($config);


			$conn = $dbGetter->getConnection($this->log);


			$sql = "SELECT running_backup_id FROM running_backups WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('host->getRunningBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackups[] = new runningBackup($row['running_backup_id']);
			}

			return $runningBackups;

		}

	}

?>
