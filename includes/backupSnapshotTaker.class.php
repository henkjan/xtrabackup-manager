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


	class backupSnapshotTaker {


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

		// The main functin of this class - take the snapshot for a scheduled backup based on the backup strategy
		// Takes a scheduledBackup object as a param
		function takeScheduledBackupSnapshot ( $scheduledBackup = false ) {

			global $config;

			// Quick input validation...
			if($scheduledBackup === false ) {
				throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Expected a scheduledBackup object to be passed to this function and did not get one.");
			}

			$this->launchTime = time();

			// Get the backup strategy for the scheduledBackup
			$sbInfo = $scheduledBackup->getInfo();

			// Get the host of the backup
			$sbHost = $scheduledBackup->getHost();

			// Get host info
			$hostInfo = $sbHost->getInfo();

			// create a backupTakerFactory
			$takerFactory = new backupTakerFactory();

			// use it to get the right object type for the backup strategy
			$backupTaker = $takerFactory->getBackupTakerByStrategy($sbInfo['strategy_code']);

			// Setup to write to host log
			$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', $this->infologVerbose, $config['LOGS']['level']);
			$this->setInfoLogStream($infolog);

			$msg = 'Initializing Scheduled Backup "'.$sbInfo['name'].'" (ID #'.$sbInfo['scheduled_backup_id'].') for host: '.$hostInfo['hostname'].' ... ';
			$this->infolog->write($msg, XBM_LOG_INFO);
			$msg = 'Using Backup Strategy: '.$sbInfo['strategy_name'];
			$this->infolog->write($msg, XBM_LOG_INFO);



			// Check to see if we can even start this party

			// Create this object now, so we don't recreate it a tonne of times in the loop
			$runningBackupGetter = new runningBackupGetter();
			
			$readyToRun = false; 
			while($readyToRun == false ) {

				// If this one is already running, exit - we NEVER run two at once.
				if($scheduledBackup->isRunning()) {
					$this->infolog->write("Detected this scheduled backup job is already running, exiting...", XBM_LOG_ERROR);
					throw new Exception('rotatingBackupTaker->takeScheduledBackupSnapshot: '."Error: Detected this scheduled backup job is already running.");
				}

				// Check to see how many backups are running for the host already...
				$runningBackups = $sbHost->getRunningBackups();

				// If we are at or greater than max num of backups for the host, then sleep before we try again.
				if( sizeOf($runningBackups) >= $config['SYSTEM']['max_host_concurrent_backups'] ) {
					// Output to info log - this currently spits out every 30 secs (define is 30 at time of writing) 
					// maybe it is too much
					$this->infolog->write("Found ".sizeOf($runningBackups)." backup(s) running for this host out of a maximum of ".
						$config['SYSTEM']['max_host_concurrent_backups']." per host. Sleeping ".XBM_SLEEP_SECS." before retry...", XBM_LOG_INFO);
					sleep(XBM_SLEEP_SECS);
					continue;
				}


				// Now check to see the how many backups are running globally and if we should be allowed to run...
				$globalRunningBackups = $runningBackupGetter->getAll();

				if( sizeOf($globalRunningBackups) >= $config['SYSTEM']['max_global_concurrent_backups'] ) {
					//output to info log -- currentl every 30 secs based on define at time of writing
					// maybe too much?
					$this->infolog->write("Found ".sizeOf($globalRunningBackups)." backup(s) running out of a global maximum of ".
						$config['SYSTEM']['max_global_concurrent_backups'].". Sleeping ".XBM_SLEEP_SECS." before retry...", XBM_LOG_INFO);
					sleep(XBM_SLEEP_SECS);
					continue;
				}

				// If we made it to here - we are ready to run!
				$readyToRun = true;
			}




	
			// Populate the backupTaker with the relevant settings like log/infolog/Verbose, etc.
			$backupTaker->setLogStream($this->log);
			$backupTaker->setInfoLogStream($this->infolog);
			$backupTaker->setInfoLogVerbose($this->infologVerbose);
			$backupTaker->setLaunchTime($this->launchTime);

			// Kick off takeScheduledBackupSnapshot method of the actual backup taker
			$backupTaker->takeScheduledBackupSnapshot($scheduledBackup);

			// Apply the retention policy
			$this->infolog->write("Applying snapshot retention policy ...", XBM_LOG_INFO);
			try {
				$backupTaker->applyRetentionPolicy($scheduledBackup);
			} catch ( Exception $e ) {
				$this->infolog->write($e->getMessage());
			}
			$this->infolog->write("Application of retention policy complete.", XBM_LOG_INFO);

			// Perform any post processing
			$this->infolog->write("Performing any post-processing necessary ...", XBM_LOG_INFO);
			try {
				$backupTaker->postProcess($scheduledBackup);
			} catch ( Exception $e ) {
				$this->infolog->write($e->getMessage());
			}
			$this->infolog->write("Application of retention policy complete.", XBM_LOG_INFO);
			$this->infolog->write("Post-processing completed.", XBM_LOG_INFO);

			$this->infolog->write("Scheduled Backup Task Complete!", XBM_LOG_INFO);

			return true;

		} // end takeScheduledBackupSnapshot


	}

?>
