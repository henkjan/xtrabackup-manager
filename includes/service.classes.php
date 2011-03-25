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

	// Service class to get back hosts
	class hostGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}


		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all Host objects
		function getAll($activeOnly = false) {
			global $config;

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'hostGetter->getAll: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT host_id FROM hosts";

			if($activeOnly == true) {
				$sql .= " WHERE active = 'Y'";
			}

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'hostGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$hosts = Array();
			while($row = $res->fetch_array() ) {
				$host = new host($row['host_id']);
				$host->setLogStream($this->log);
				$hosts[] = $host;
			}

			return $hosts;

		}

		// Get host by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				$this->error = 'hostGetter->getById: '."Error: Expected a numeric ID as a parameter, but did not get one.";
				return false;
			}

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'hostGetter->getById: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT host_id FROM hosts WHERE host_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'hostGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if($res->num_rows != 1 ) {
				$this->error = 'hostGetter->getById: '."Error: Could not retrieve a Host with ID $id.";
				return false;
			}

			$host = new host($id);
			$host->setLogStream($this->log);

			return $host;
		}

	}


	// Service class to get back storage volumes
	class volumeGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}
		
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all Volume objects
		function getAll() {
			global $config;

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'volumeGetter->getAllVolumes: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_volume_id FROM backup_volumes";


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'volumeGetter->getAllVolumes: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$volumes = Array();
			while($row = $res->fetch_array() ) {
				$volume = new backupVolume($row['backup_volume_id']);
				$volume->setLogStream($this->log);
				$volumes[] = $volume;
			}

			return $volumes;

		}

		// Get volume by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				$this->error = 'volumeGetter->getById: '."Error: Expected a numeric ID as a parameter, but did not get one.";
				return false;
			}

			$dbGetter = new dbConnectionGetter();

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'volumeGetter->getById: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_volume_id FROM backup_volumes WHERE backup_volume_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'volumeGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if($res->num_rows != 1 ) {
				$this->error = 'volumeGetter->getById: '."Error: Could not retrieve a Volume with ID $id.";
				return false;
			}

			$volume = new backupVolume($id);
			$volume->setLogStream($this->log);

			return $volume;
		}

	}


	// Service class to get back scheduled backups
	class scheduledBackupGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all scheduledBackup objects
		function getAll() {

			global $config;

			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackupGetter->getAll: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT scheduled_backup_id FROM scheduled_backups";


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackupGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			$scheduledBackups = Array();
			while($row = $res->fetch_array() ) {
				$scheduledBackup = new scheduledBackup($row['scheduled_backup_id']);
				$scheduledBackup->setLogStream($this->log);
				$scheduledBackups[] = $scheduledBackup;
			}

			return $scheduledBackups;

		}

		// Get one scheduledBackup object by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				$this->error = 'scheduledBackupGetter->getById: '."Error: The ID for this object is not an integer.";
				return false;
			}

			$dbGetter = new dbConnectionGetter();

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'scheduledBackupGetter->getById: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE scheduled_backup_id=".$id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'scheduledBackupGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if($res->num_rows != 1) {
				$this->error = 'scheduledBackupGetter->getById: '."Error: Could not retrieve a Scheduled Backup with ID $id.";
				return false;
			}

			$scheduledBackup = new scheduledBackup($id);
			$scheduledBackup->setLogStream($this->log);

			return $scheduledBackup;

		}
	}


	class mysqlTypeGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getById($id) {

			if(!is_numeric($id) ) {
				$this->error = 'mysqlTypeGetter->getById: '."Error: The ID for this object is not an integer.";
				return false;
			}   
			
			$dbGetter = new dbConnectionGetter();

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'mysqlTypeGetter->getById: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT mysql_type_id FROM mysql_types WHERE mysql_type_id=".$id;


			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'mysqlTypeGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}

			if($res->num_rows != 1) {
				$this->error = 'mysqlTypeGetter->getById: '."Error: Could not retrieve a MySQL Type with ID $id.";
				return false;
			}

			$mysqlType = new mysqlType($id);
			$mysqlType->setLogStream($this->log);

			return $mysqlType;

		}

	}

	// Service class to sync all backup schedules to crontab
	class cronFlusher {

		function __construct() {
			$this->error = '';
		}

		function flushSchedule() {

			global $config;

			if( ( $cron = $this->buildCron() ) === false) {
				return false;
			}

			$tmpName = tempnam($config['SYSTEM']['tmpdir'], 'xbmcron');
			$fp = @fopen($tmpName, "w");

			// Validate we got a resource OK
			if(!is_resource($fp)) {
				$this->error = 'cronFlusher->flushSchedule: '."Error: Could not open tempfile for writing - $tmpName";
				return false;
			}

		
			if( fwrite($fp, $cron) === false ) {
				$this->error = 'cronFlusher->flushSchedule: '."Error: Could not write to tempfile - $tmpName";
				return false;
			}

			fclose($fp);

			// If we are trying to flush as any other user, give the -u option to crontab
			// unfortunately the -u option can only be used for privileged users, otherwise we get an error
			// This is even the case if you try to use the -u option to specify the current user!!
			if( exec('whoami') != $config['SYSTEM']['user'] ) {
				exec("crontab -u ".$config['SYSTEM']['user']." $tmpName", $output, $returnVar);
			} else {
				exec("crontab $tmpName", $output, $returnVar);
			}

			if($returnVar != 0 ) {
				$this->error = 'cronFlusher->flushSchedule: '."Error: Could not install crontab with file - $tmpName - Got error $returnVar and output:\n".implode("\n", $output);
				return false;
			}

			unlink($tmpName);

			return $cron;
		}

		// Build the crontab
		function buildCron() {

			global $config;

			// Start with an empty string...
			$cron = '# This crontab is automatically managed and generated by '.XBM_RELEASE_VERSION."\n";
			$cron .="# You should NEVER edit this crontab directly, but rather reconfigure and use xbm-flush.php\n";

			// Get All Hosts
			$hostGetter = new hostGetter();
			if( ( $hosts = $hostGetter->getAll() ) === false ) {
				$this->error = 'cronFlusher->buildCron: '.$hostGetter->error;
				return false;
			}

			
			// Cycle through each host...
			foreach( $hosts as $host ) {

				// Get host info ..
				if( ! ($hostInfo = $host->getInfo() ) ) {
					$this->error = 'cronFlusher->buildCron: '.$host->error;
					return false;
				}

				if($hostInfo['active'] == 'Y' ) {
					$cron .= "\n# Host - ".$hostInfo['hostname']."\n";
				} else {
					continue;
				}

				// Get scheduled backups for host...
				if( ($scheduledBackups = $host->getScheduledBackups() ) === false ) {
					$this->error = 'cronFlusher->buildCron: '.$host->error;
					return false;
				}

				// Cycle through each scheduled backup ..
				foreach( $scheduledBackups as $scheduledBackup ) {
					// Get info for the scheduled backup ..
					if( ! ($scheduledBackupInfo = $scheduledBackup->getInfo() ) ) {
						$this->error = 'cronFlusher->buildCron: '.$scheduledBackup->error;
						return false;
					}

					if( $scheduledBackupInfo['active'] == 'Y' ) {
						$cron .= "\n# Backup: ".$scheduledBackupInfo['name']."\n";
						$cron .= $scheduledBackupInfo['cron_expression'].' xbm-backup -s '.$scheduledBackupInfo['scheduled_backup_id']." -q\n";
					} else {
						continue;
					}

					
				}
			}

			return $cron;
			
		}
	}


	// Timer class for timing things for performance monitoring...
	class Timer {

		var $classname  = "Timer";
		var $start	  = 0;
		var $stop	   = 0;
		var $elapsed	= 0;
		var $started	= false;

		# Constructor 
		function Timer( $start = true ) {
				if ( $start )
						$this->start();
		}

		# Start counting time 
		function start() {
				$this->started = true;
				$this->start = $this->_gettime();
		}

		# Stop counting time 
		function stop() {
				$this->started = false;
				$this->stop	  = $this->_gettime();
				$this->elapsed = $this->_compute();
		}

		# Get Elapsed Time 
		function elapsed() {
				if ( $this->started == true)
						$this->stop();

				return $this->elapsed;
		}

		# Reset timer
		function reset() {
				$this->started = false;
				$this->start	= 0;
				$this->stop	 = 0;
				$this->elapsed  = 0;
		}

		#### PRIVATE METHODS #### 

		# Get Current Time 
		function _gettime() {
			$mtime = microtime();
			$mtime = explode( " ", $mtime );
			return $mtime[1] + $mtime[0];
		}

		# Compute elapsed time 
		function _compute() {
			return $this->stop - $this->start;
		}

	}


	// Service class used to find available ports in the configured port range
	class portFinder {


		function __construct() {
			$this->log = false;
			$this->availablePort = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}


		// Inspects all entries in the running_backups table to see what ports are supposedly in use
		// cycles over the configured port range until it finds a free port
		// Will attempt to read from the table $attempts times - default 5
		// Will sleep $usleep microseconds between attempts - default 1MM microseconds = 1 second
		function findAvailablePort($attempts=5, $usleep = 1000000) {

			global $config;


			$dbGetter = new dbConnectionGetter();


			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'portFinder->findAvailablePort: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT port FROM running_backups";


			$count = 0;

			while($this->availablePort === false && $count < $attempts) {

				if( ! ($res = $conn->query($sql) ) ) {
					$this->error = 'portFinder->findAvailablePort: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
					return false;
				}

				$busyPorts = Array();
				while( $row = $res->fetch_array() ) {
					$busyPorts[] = $row['port'];
				}


				for( $i = $config['SYSTEM']['port_range']['low'] ; $i <= $config['SYSTEM']['port_range']['high'] ; $i++ ) {
					if( !in_array($i, $busyPorts) ) {

						$this->availablePort = $i;
						return true;
					}
				}
				$count++;
				sleep($usleep);
			}

			$this->availablePort = false;

			return true;

		}


	}

	

	// Service class to get the current runningBackup objects
	class runningBackupGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}


		function setLogStream($log) {
			$this->log = $log;
		}   
		
		// Get all scheduledBackup objects
		function getAll() {
		
			global $config;
			
			$dbGetter = new dbConnectionGetter();
			

			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'runningBackupGetter->getAll: '.$dbGetter->error;
				return false;
			}   
			

			$sql = "SELECT running_backup_id FROM running_backups";
			

			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'runningBackupGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false;
			}   
			
			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackup = new runningBackup($row['running_backup_id']);
				$runningBackup->setLogStream($this->log);
				$runningBackups[] = $runningBackup;
			}   
			
			return $runningBackups;
			
		}


	}


	// Service class to basically rm -rf a dirtree
	class recursiveDeleter {

		function __construct() {
			$this->error = '';
		}

		// Recursively delete everything in a directory
		function delTree($dir) {

			if(!is_dir($dir) ) {
				$this->error = 'recursiveDeleter->delTree: '."Error: Could not delete dir $dir - It is not a directory.";
				return false;
			}

			if( ( strlen($dir) == 0 ) || ( $dir == '/' ) ) {
				$this->error = 'recursiveDeleter->delTree: '."Error: Detected attempt to delete unsafe path: $dir - Aborting.";
				return false;
			}

			$files = glob( $dir . '*', GLOB_MARK ); 

			foreach( $files as $file ){ 
				if( substr( $file, -1 ) == '/' ) {
					if($this->delTree( $file ) == false ) {
						return false;
					}
				} else {
					if( ! unlink( $file ) ) {
						$this->error = 'recursiveDeleter->delTree: '."Error: Could not delete file: $file";
						return false;
					}
				}
			} 
			if( ! rmdir( $dir ) ) {
				$this->error = 'recursiveDeleter->delTree: '."Error: Could not rmdir() on $dir";
				return false;
			}

			return true;

		}

	}


	// Service class to get backupSnapshot objects.
	class backupSnapshotGetter {

		function __construct() {
			$this->error = '';
			$this->log = false;
		}
		
		function setLogStream($log) {
			$this->log = $log;
		}
			
		function getById($id) {
				
			if(!is_numeric($id) ) {
				$this->error = 'backupSnapshotGetter->getById: '."Error: The ID for this object is not an integer.";
				return false;
			}
			
			$dbGetter = new dbConnectionGetter();
				
			if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				$this->error = 'backupSnapshotGetter->getById: '.$dbGetter->error;
				return false;
			}


			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE backup_snapshot_id=".$id;
				
				
			if( ! ($res = $conn->query($sql) ) ) {
				$this->error = 'backupSnapshotGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error";
				return false; 
			}

			if($res->num_rows != 1) {
				$this->error = 'backupSnapshotGetter->getById: '."Error: Could not retrieve a Backup Snapshot with ID $id.";
				return false;
			}

			$backupSnapshot = new backupSnapshot($id);
			$backupSnapshot->setLogStream($this->log);

			return $backupSnapshot;
		}

	}

	// Service class for making temporary directories
	class remoteTempDir {

		function __construct() {
			$this->error = '';
		}

		// Create the tmpdir remotely and return the path information
		function init($host, $user, $dir, $prefix='') {

			$this->host = $host;
			$this->user = $user;
			$this->prefix = $prefix;

			if (substr($dir, -1) != '/') $dir .= '/';

			$c = 0;
			do
			{
				$path = $dir.$prefix.mt_rand(0, 9999999);
				$cmd = 'ssh '.$user.'@'.$host." 'mkdir $path' 2>&1";
				@exec($cmd, $output, $returnVar);
				$c++;
			} while ( ( $returnVar != 0 ) && $c < 5 );

			if($c >= 5) {
				$this->error = 'tempDirMaker->makeTempDir: '."Error: Gave up trying to create a temporary directory on ".$user."@".$host." after $c attempts. Last output:\n".implode("\n",$output);
				return false;
			}

			$this->dir = $path;
			return $path;

		}

		// Destroy the remote tmpdir
		function destroy() {


			if( !isSet($this->host) || !isSet($this->user) || !isSet($this->dir) ) {
				$this->error = 'remoteTempDir->destroy: '."Error: Expected this object to be populated with host, user and dir, but did not find them.";
				return false;
			}

			if( (strlen($this->dir) == 0 ) || ($this->dir == '/') ) {
				$this->error = 'remoteTempDir->destroy: '."Error: Detected possibly unsafe to rm -rf temp remote temp dir: ".$this->user."@".$this->host.':'.$this->dir;
				return false;
			}


			$cmd = 'ssh '.$this->user.'@'.$this->host." 'rm -rf ".$this->dir."' 2>&1";
			@exec($cmd, $output, $returnVar);

			if( $returnVar != 0 ) {
				$this->error = 'remoteTempDir->destroy: '."Error: Encoutnered a problem removing remote temp dir: ".$this->user."@".$this->host.":".$this->dir."  Last output:\n".implode("\n",$output);
				return false;
			}

			return true;
		}

	} // Class: Rmeote tempDir


	class backupSnapshotMerger {

		function __construct() {
			$this->error = '';
		}

		function merge($seedSnapshot, $deltaSnapshot) {

			// Create a new snapshot entry

			// Find the scheduled backup we are working in
			if( ! ( $scheduledBackup = $seedSnapshot->getScheduledBackup() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$seedSnapshot->error;
				return false;
			}

			$mergeSnapshot = new backupSnapshot();

			if( $mergeSnapshot->init($scheduledBackup, 'SEED', 'MERGE') === false ) {
				$this->error = 'backupSnapshotMerger->merge: '.$mergeSnapshot->error;
				return false;
			}


			// Set status to merging
			if( ! $mergeSnapshot->setStatus('MERGING') ) {
				$this->error = 'backupSnapshotMerger->merge: '.$mergeSnapshot->error;
				return false;
			}

			// Merge incremental over seed

			// Get paths for seed and delta
			if( ! ($seedPath = $seedSnapshot->getPath() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$seedSnapshot->error;
				return false;
			}

			if( ! ($deltaPath = $deltaSnapshot->getPath() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$deltaSnapshot->error;
				return false;
			}

			// Find the xtrabackup binary to use
			if( ! ($xbBinary = $scheduledBackup->getXtrabackupBinary() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$scheduledBackup->error;
				return false;
			}
			
			// Actually kick off the process to do it here...
			$mergeCommand = $xbBinary.' --prepare --apply-log-only --target-dir='.$seedPath.' --incremental-dir='.$deltaPath.' 1>&2';
			

			$mergeDescriptors = Array(
								0 => Array('pipe', 'r'), // Process will read from STDIN pipe
								1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
								2 => Array('pipe', 'w')  // Process will write to STDERR pipe
							);
								
			$mergeProc = proc_open($mergeCommand, $mergeDescriptors, $mergePipes);

			if(!is_resource($mergeProc) ) {
				$this->error = 'backupSnapshotMerger->merge: '."Error: Unable to merge deltas into seed with command: $mergeCommand";
				return false;
			}


			// Check the status of the backup every 5 seconds...
			do {

				if( ! ( $mergeStatus = proc_get_status($mergeProc) ) ) {
					$this->error = 'backupSnapshotMerger->merge: '."Error: Unable to retrieve status on merge process.";
					return false;
				}
				sleep(5);

			} while ($mergeStatus['running']);

			// Check exit status
			if($mergeStatus['exitcode'] <> 0 ) {
				$this->error = 'backupSnapshotMerger->merge: '."Error: There was an error merging snapshots - The process returned code ".$mergeStatus['exitcode'].".\n".
								"The command issues was:\n".$mergeCommand."\n".
								"The output is as follows:\n".stream_get_contents($mergePipes[2]);
				return false;
			}


			// We have a backup entry with a directory - we will need to remove it before we rename
			if( ! ( $mergePath = $mergeSnapshot->getPath() ) ) {

				if( ( $mergePath == '/' ) || ( strlen($mergePath) == 0 ) ) {
					$this->error = 'backupSnapshotMerger->merge: '."Error: Detected unsafe path to remove: $mergePath";
					return false;
				}

				if( ! rmdir($mergePath) ) {
					$this->error = 'backupSnapshotMerger->merge: '."Error: Unable to rmdir on: $mergePath";
					return false;
				}

			}



			// Rename the directory in place
			if( ! rename($seedPath, $mergePath) ) {
				$this->error = 'backupSnapshotMerger->merge: '."Error: Could not move seed from $seedPath to $mergePath - rename() failed.";
				return false;
			}

			unset($output);
			unset($returnVar);

			// Remove the incremental files
			// rm -rf on $deltaSnapshot->getPath...
			$rmCmd = 'rm -rf '.$deltaPath;
			exec($rmCmd, $output, $returnVar);

			if( $returnVar <> 0 ) {
				$this->error = 'backupSnapshotMerger->merge: '."Error: Could not remove old deltas with command: $rmCmd -- Got output:\n".implode("\n",$output);
				return false;
			}

			// Set the time to the time of the $deltaSnapshot
			if( ! ($deltaInfo = $deltaSnapshot->getInfo() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$deltaSnapshot->error;
				return false;
			}

			if( ! $mergeSnapshot->setSnapshotTime($deltaInfo['snapshot_time']) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$mergeSnapshot->error;
				return false;
			}

			// Set any snapshot with the parent id of the merged delta to now have the parent id of the new merge snapshot

			// get mergeInfo first
			if( ! ($mergeInfo = $mergeSnapshot->getInfo() ) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$mergeSnapshot->error;
				return false;
			}

			// reassign the children of the seed the new parent (merge)
			if( ! $deltaSnapshot->assignChildrenNewParent($mergeInfo['backup_snapshot_id']) ) {
				$this->error = 'backupSnapshotMerger->merge: '.$deltaSnapshot->error;
				return false;
			}

			// Set the status of the delta to MERGED
			if( ! $deltaSnapshot->setStatus('MERGED') ) {
				$this->error = 'backupSnapshotMerger->merge: '.$deltaSnapshot->error;
				return false;
			}			

			// Set the status of the seed snapshot to MERGED
			if( ! $seedSnapshot->setStatus('MERGED') ) {
				$this->error = 'backupSnapshotMerger->merge: '.$seedSnapshot->error;
				return false;
			}

			// Set status to COMPLETED
			if( ! $mergeSnapshot->setStatus('COMPLETED') ) {
				$this->error = 'backupSnapshotMerger->merge: '.$mergeSnapshot->error;
				return false;
			}


			return true;

		}

	} // Class: backupSnapshotMerger


?>
