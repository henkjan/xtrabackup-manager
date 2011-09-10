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


			$sql = "SELECT sb.*, bs.strategy_code, bs.strategy_name FROM scheduled_backups sb JOIN backup_strategies bs ON sb.backup_strategy_id=bs.backup_strategy_id WHERE scheduled_backup_id=".$this->id;


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
		function getXtraBackupBinary() {

			$info = $this->getInfo();

			$mysqlTypeGetter = new mysqlTypeGetter();
			$mysqlTypeGetter->setLogStream($this->log);

			$mysqlType = $mysqlTypeGetter->getById($info['mysql_type_id']);

			$mysqlTypeInfo = $mysqlType->getInfo();

			return $mysqlTypeInfo['xtrabackup_binary'];
		}



		// Return the valid seed of this scheduledBackup or false otherwise
		// This should be replaced by use of snapshotGroup->getSeed()
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

		// Get an array of the snapshot groups for the scheduledBackup
		function getSnapshotGroupsNewestToOldest() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getSnapshotGroupsNewestToOldest: '."Error: The ID for this object is not an integer.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT DISTINCT snapshot_group_num FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND status='COMPLETED' ORDER BY snapshot_group_num DESC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getSnapshotGroupsNewestToOldest: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$groups = Array();
			while($row = $res->fetch_array() ) {
				$groups[] = new backupSnapshotGroup($this->id, $row['snapshot_group_num']);
			}

			// If there are no groups in the DB, manually inject the initial group number 1...
			if(sizeOf($groups) == 0 ) {
				$groups[] = new backupSnapshotGroup($this->id, 1);
			}


			return $groups;

		}


		// Check to see if there is a running backup entry already for this scheduled backup
		function isRunning() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->isRunning: '."Error: The ID for this object is not an integer.");
			}   
			

			$backupGetter = new runningBackupGetter();
			$backupGetter->setLogStream($this->log);

			$this->runningBackups = $backupGetter->getByScheduledBackup($this);
	
			if( sizeOf($this->runningBackups) == 0 ) {
				return false;
			} elseif( sizeOf($this->runningBackups) > 0 ) {
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


		// Get the most recently completed materialized snapshot
		function getMostRecentCompletedMaterializedSnapshot() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getMostRecentCompletedMaterializedSnapshot: '."Error: The ID for this object is not an integer.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT materialized_snapshot_id FROM materialized_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." ORDER BY creation_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedMaterializedSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows < 1 ) {
				return false;
			}

			$row = $res->fetch_array();

			$snapshotGetter = new materializedSnapshotGetter();
			$snapshot = $snapshotGetter->getById($row['materialized_snapshot_id']);

			return $snapshot;
		}


		// Get an array list of the scheduledBackup parameters
		function getParameters() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getParameters: '."Error: The ID for this object is not an integer.");
			}

			$dbGetter = new dbConnectionGetter($config);

			$conn = $dbGetter->getConnection($this->log);

			$sql = "SELECT bsp.param_name, sbp.param_value FROM 
						scheduled_backups sb JOIN backup_strategy_params bsp 
							ON sb.backup_strategy_id = bsp.backup_strategy_id 
						JOIN scheduled_backup_params sbp
							ON sbp.scheduled_backup_id = sb.scheduled_backup_id AND 
								bsp.backup_strategy_param_id = sbp.backup_strategy_param_id
					WHERE
						sb.scheduled_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getParameters: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$params = Array();
			while( $row = $res->fetch_array() ) {
				$params[$row['param_name']] = $row['param_value'];
			}


			return $params;

		}

		// Validate a scheduledBackup name
		public static function validateName($name) {

			if(!isSet($name) ) {
				throw new InputException("Error: Expected a Scheduled Backup name as input, but did not get one.");
			}

			if(strlen($name) < 1 || strlen($name) > 128 ) {
				throw new InputException("Error: Scheduled Backup name must be between 1 and 128 characters in length.");
			}

			return;
		}

		// Validate a cronExpression
		public static function validateCronExpression($cron) {

			// Check that we got a value
			if(!isSet($cron) ) {
				throw new InputException("Error: Expected a cron expression as a parameter, but did not get one.");
			}

			// Build the cron validator regexp
			// Adapted from code by Jordi Salvat i Alabart - with thanks to www.salir.com

			$numbers= array(
				'min'=>'[0-5]?\d',
				'hour'=>'[01]?\d|2[0-3]',
				'day'=>'0?[1-9]|[12]\d|3[01]',
				'month'=>'[1-9]|1[012]',
				'dow'=>'[0-7]'
			);

			foreach($numbers as $field=>$number) {
				$range= "($number)(-($number)(\/\d+)?)?";
				$field_re[$field]= "\*(\/\d+)?|$range(,$range)*";
			}

			$field_re['month'].='|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';
			$field_re['dow'].='|mon|tue|wed|thu|fri|sat|sun';

			$fields_re= '('.join(')\s+(', $field_re).')';

			$replacements= '@reboot|@yearly|@annually|@monthly|@weekly|@daily|@midnight|@hourly';

			$regExp = '^('.
					"$fields_re".
					"|($replacements)".
				')$';

			// Validate the cron
			if(preg_match("/$regExp/", $cron) != 1) {
				throw new InputException("Error: The cron expression given is invalid.");
			}

			return;

		}

		// Validate a datadir path
		public static function validateDatadirPath($path) {

			if(!isSet($path) ) {
				throw new InputException("Error: Expected a datadir as a parameter, but did not get one.");
			}

			if(strlen($path) < 1 || strlen($path) > 1024 ) {
				throw new InputException("Error: Datadir must be between 1 and 1024 characters in length.");
			}

			return;

		}

		// Validate a mysql user
		public static function validateMysqlUser($user) {

			if(!isSet($user) ) {
				throw new InputException("Error: Expected a MySQL user as a parameter, but did not get one.");
			}

			if(strlen($user) < 1 || strlen($user) > 16) {
				throw new InputException("Error: MySQL user name must be between 1 and 16 characters in length.");
			}

			return;
		}

		// Validate a mysql password
		public static function validateMysqlPass($pass) {

			if(!isSet($pass)) {
				throw new InputException("Error: Expected a MySQL password as a parameter, but did not get one.");
			}

			if(strlen($pass) < 1 || strlen($pass) > 256 ) {
				throw new InputException("Error: MySQL password must be between 1 and 256 characters in length.");
			}

			return;
		}

		// Validate a backup user
		public static function validateBackupUser($user) {

            if(!isSet($user)) {
                throw new InputException("Error: Expected a Backup User as a parameter, but did not get one.");
            }

            if(strlen($user) < 1 || strlen($user) > 256 ) {
                throw new InputException("Error: Backup User must be between 1 and 256 characters in length.");
            }

            return;

		}

	} // Class: scheduledBackup

?>
