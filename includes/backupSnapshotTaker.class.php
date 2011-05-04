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


	class backupSnapshotTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->alertEmail = false;
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

		// Set email alert target
		function setAlertEmailAddress($email) {
			$this->alertEmail = $email;
		}

		// The main functin of this class - take the snapshot for a scheduled backup
		// Takes a scheduledBackup object as a param
		function takeScheduledBackupSnapshot ( $scheduledBackup = false ) {

			global $config;

			// Quick input validation...
			if($scheduledBackup === false ) {
				throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Expected a scheduledBackup object to be passed to this function and did not get one.");
			}

			// Start outputting stuff to the info log about this backup.

			// First fetch info to know what we're backing up
			// Get info on the backup
			$sbInfo = $scheduledBackup->getInfo();

			// Get the host of the backup
			$sbHost = $scheduledBackup->getHost();

			$hostInfo = $sbHost->getInfo();

			// Setup to write to host log
			$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', $this->infologVerbose, $config['LOGS']['level']);
			$this->setInfoLogStream($infolog);

			$msg = 'Initializing Scheduled Backup "'.$sbInfo['name'].'" (ID #'.$sbInfo['scheduled_backup_id'].') for host: '.$hostInfo['hostname'].' ... ';
			$this->infolog->write($msg, XBM_LOG_INFO);


			// Check to see if we can even start this party



			// Create this object now, so we don't recreate it a tonne of times in the loop
			$runningBackupGetter = new runningBackupGetter();

			$readyToRun = false;
			while($readyToRun == false ) {
			
				// If this one is already running, exit - we NEVER run two at once.
				if($scheduledBackup->isRunning()) {
					$this->infolog->write("Detected this scheduled backup job is already running, exiting...", XBM_LOG_ERROR);
					throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Detected this scheduled backup job is already running.");
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


			// Attempt to get a lock on the scheduledBackup and provision a Port from the pool

			// We attempt to create a running backup - this gets us a port and all that jazz as a part of it...

			// Creat object for the lock...
			$runningBackup = new runningBackup();
			$runningBackup->setInfoLogStream($this->infolog);

			// Initialize a backup for sbHost for scheduledBackup
			$runningBackup->init($sbHost, $scheduledBackup);

			// Start a try block so we can catch any exceptions from here on in and cleanup that runningBackup entry..
			try {
	
				// Now get back the info about the running backup we just created.
				$rbInfo = $runningBackup->getInfo();

				// Create new object shell
				$snapshot = new backupSnapshot();

				// If there is already a valid seed, take an incremental
				if($scheduledBackup->getSeed() !== false) {


					/**********************
	
			 		TAKING INCREMENTAL BACKUP
	
					***********************/
	
	
					// Find the most recent COMPLETED snapshot for the scheduledBackup
					$recentBackupSnapshot = $scheduledBackup->getMostRecentCompletedBackupSnapshot();
	
					// Get the log sequence
					$lsn = $recentBackupSnapshot->getLsn();
	
	
					// Init the snapshot here and use the ID for the tempdirname
					$snapshot->init($scheduledBackup, 'DELTA', 'INCREMENTAL', $recentBackupSnapshot->id);

					// Start another TRY block so we can catch any exceptions and clean up our snapshot status.
					try {
						$snapshotInfo = $snapshot->getInfo();
		
		
						$tempDir = $runningBackup->getStagingTmpdir();
			
						// Build the command to run the snapshot into the staging dir
						$xbBinary = $scheduledBackup->getXtrabackupBinary();
	
						// Command should look like this:
						// xtrabackup_51 --backup --target-dir=/data/backups/bup06-int/test --incremental-lsn='0:46850' --datadir=/mysqldb/data 2>&1
						$xbCommand = "ssh ".$sbInfo['backup_user']."@".$hostInfo['hostname']." '".$xbBinary." --backup --target-dir=".$tempDir." --incremental-lsn=".$lsn." --datadir=".$sbInfo['datadir_path']."' 1>&2";
	
					
						// Set up how we'll interact with the IO file handlers of the process
						$xbDescriptors = Array(
											0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
											1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
											2 => Array('pipe', 'w')  // Process will write to STDERR pipe
										);
	
						// Connect to the remote host and create the backup into a staging directory then copy it back via netcat and tar
		
	
						////////
						////////
						// KICK OFF XTRABACKUP INCREMENTAL //
						////////
						////////
					
						// Set the state of the snapshot to RUNNING
						$snapshot->setStatus('RUNNING');
					
						// Info output
						$this->infolog->write("Staging an INCREMENTAL xtrabackup snapshot of ".$sbInfo['datadir_path']." via ssh: ".$sbInfo['backup_user']."@".$hostInfo['hostname']." to $tempDir...", XBM_LOG_INFO);
					
						// Start the xtrabackup process
						$xbProc = proc_open($xbCommand, $xbDescriptors, $xbPipes);
					
						// Check that we launched OK.
						if( !is_resource($xbProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to use ssh to start xtrabackup with: $xbCommand .");
						}   
						
						// Check the status of the backup every 5 seconds...
						$streamContents = '';
						stream_set_blocking($xbPipes[2], 0);
						do {
	
							$streamContents .= stream_get_contents($xbPipes[2]);
	
							if( ! ( $xbStatus = proc_get_status($xbProc) ) ) {
								throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to retrieve status on backup process.");
							}
							sleep(5);
	
						} while ($xbStatus['running']);
	

						if($xbStatus['exitcode'] <> 0 ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: There was an error backing up - The process returned code ".$xbStatus['exitcode'].".".
													" The output from the backup is as follows:\n".$streamContents);
						}
	
		
					  	/*print_r($xbStatus);
						echo "\nWe got STDOUT: \n";
						echo stream_get_contents($xbPipes[1]);
						echo "\nWe got STDERR: \n";
						echo stream_get_contents($xbPipes[2]); */
		
						$this->infolog->write("Xtrabackup completed staging the backup with the following output:\n".$streamContents, XBM_LOG_INFO);
		
		
						// Copy it to local machine
		
						$path = $snapshot->getPath();
		
		
						// Fire up a netcat listener
		
						// Set the command we plan to run
						$ncCommand = ' cd '.$path.' ; nc -l '.$rbInfo['port'].' | tar xvif - 2>&1 > /dev/null';
		
						// Open the process with a stream to read from it
						$ncProc = popen($ncCommand,'r');
						if(!is_resource($ncProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand .");
						}
		
						// Set the stream so we can read from it without blocking (return if there is no data!)
						stream_set_blocking($ncProc, 0);
		
						// Sleep for one second before we check to see if netcat returned anything problems wise...
						sleep(1);
		
						// Read from stream and check to see if we have any output at all...
						$ncOutput = fgets($ncProc);
		
						if(strlen($ncOutput) > 0 ) {
							// If we got output, read it all into ncOutput and throw an error
							while(!feof($ncProc) ) {
								$ncOutput .= "\n".fgets($ncProc);
							}
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand . Got error output: $ncOutput .");
						}
		
						// Info output
						$this->infolog->write("Started Netcat (nc) listener on port ".$rbInfo['port']." to receive backup tar stream into directory $path ...", XBM_LOG_INFO);
		
	
						// Copy the backup back via the netcat listener
						$copyCommand = "ssh ".$sbInfo['backup_user']."@".$hostInfo['hostname']." 'cd $tempDir; tar cvf - . | nc ".$config['SYSTEM']['xbm_hostname']." ".$rbInfo['port']." '";
	
						// Set the state of the snapshot to COPYING
						$snapshot->setStatus('COPYING');
	
	
	
						// Set up how we'll interact with the IO file handlers of the process
						$copyDescriptors = Array(
											0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
											1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
											2 => Array('pipe', 'w')  // Process will write to STDERR pipe
										);
		
		
						// Start the xtrabackup process
						$copyProc = proc_open($copyCommand, $copyDescriptors, $copyPipes);
		
						// Check that we launched OK.
						if( !is_resource($copyProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to use ssh to start copy with: $copyCommand .");
						}
		
						// Check the status of the backup every 5 seconds...
						$streamContents = '';
						stream_set_blocking($copyPipes[2], 0);
						do {
							$streamContents .= stream_get_contents($copyPipes[2]);
							if( ! ( $copyStatus = proc_get_status($copyProc) ) ) {
								throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to retrieve status on copy process.");
							}
							sleep(5);
		
						} while ($copyStatus['running']);
		
		
						if($copyStatus['exitcode'] <> 0 ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: There was an error copying files - The process returned code ".$copyStatus['exitcode'].".".
													" The output from the backup is as follows:\n".$streamContents);
						}
		
						/* For debugging...
						print_r($copyStatus);
						echo "\nWe got STDOUT: \n";
						echo stream_get_contents($copyPipes[1]);
						echo "\nWe got STDERR: \n";
						echo stream_get_contents($copyPipes[2]); 
						*/
		
						// Set the snapshot time to be now
						$snapshot->setSnapshotTime(date('Y-m-d H:i:s'));

						// Set the state of the snapshot to COMPLETED
						$snapshot->setStatus('COMPLETED');

						$this->infolog->write("Completed copying the backup via netcat with the following output:\n".$streamContents, XBM_LOG_INFO);

					} catch(Exception $e) {
						// Remove the snapshot files and mark it as failed.
						$snapshot->deleteFiles();
						$snapshot->setStatus('FAILED');
						throw new Exception($e->getMessage());
					}
		
	
	
					// Now we must check to see if there is any cleanup needed 
					// do we need to collapse/merge any snapshots to keep our retention levels right?
	
					$this->infolog->write("Applying snapshot retention policy ... Checking to see if any snapshots need to be merged to maintain snapshot count.", XBM_LOG_INFO);
	
	
					$scheduledBackup->applyRetentionPolicy();
					
		
					// Clean up after ourselves.. 
					// release our lock on the netcat port, scheduled backup, etc.
					$runningBackup->finish();
	
	
				// If there is not a valid seed, take a full backup
				} else {
	
					/**********************
	
						TAKING FULL BACKUP
	
					***********************/
	
					// Initialise the snapshot we're taking..
	
					$snapshot->init($scheduledBackup, 'SEED', 'FULL');

					// Start a try block to cleanup the snapshot if we fail..
					try {
		
						// Wow - we made it here!!
		
						// Now we should run a backup on the right port / host / etc.
						$path = $snapshot->getPath();
		
		
						// Fire up a netcat listener
		
						// Set the command we plan to run
						$ncCommand = ' cd '.$path.' ; nc -l '.$rbInfo['port'].' | tar xvif - 2>&1 > /dev/null';
		
						// Open the process with a stream to read from it
						$ncProc = popen($ncCommand,'r');
						if(!is_resource($ncProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand .");
						}
		
						// Set the stream so we can read from it without blocking (return if there is no data!)
						stream_set_blocking($ncProc, 0);
		
						// Sleep for one second before we check to see if netcat returned anything problems wise...
						sleep(1);
		
						// Read from stream and check to see if we have any output at all...
						$ncOutput = fgets($ncProc);
		
						if(strlen($ncOutput) > 0 ) {
							// If we got output, read it all into ncOutput and throw an error
							while(!feof($ncProc) ) {
								$ncOutput .= "\n".fgets($ncProc);
							}
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand . Got error output: $ncOutput .");
						}
		
						// Info output
						$this->infolog->write("Started Netcat (nc) listener on port ".$rbInfo['port']." to receive backup tar stream into directory $path ...", XBM_LOG_INFO);
		
						// Find which binary we should use
						$xbBinary = $scheduledBackup->getXtrabackupBinary();
		
						// Proceed with running the backup
		
						// Build the command...
						$xbCommand = 'ssh '.$sbInfo['backup_user'].'@'.$hostInfo['hostname']." 'innobackupex-1.5.1 --ibbackup=".$xbBinary." --stream=tar ".$sbInfo['datadir_path']." --user=".$sbInfo['mysql_user'].
									" --password=".$sbInfo['mysql_password']." --slave-info ";
		
						// If table locking for the backup is disabled add the --no-lock option to innobackupex
						if($sbInfo['lock_tables'] == 'N') {
							$xbCommand .= " --no-lock ";
						}
		
						$xbCommand .= " | nc ".$config['SYSTEM']['xbm_hostname']." ".$rbInfo['port'].
									' ; exit ${PIPESTATUS[0]}\''; // Makes sure the command run on the remote machine returns the exit status of innobackupex, which is what SSH will return
		
						// Set up how we'll interact with the IO file handlers of the process
						$xbDescriptors = Array(
											0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
											1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
											2 => Array('pipe', 'w')  // Process will write to STDERR pipe
										);
		
						// DEBUG
						//echo "Attempting to run:\n $xbCommand \n";
		
						////////
						////////
						// KICK OFF XTRABACKUP //
						////////
						////////
		
						// Set the state of the snapshot to RUNNING
						$snapshot->setStatus('RUNNING');
		
						// Info output
						$this->infolog->write("Running FULL xtrabackup snapshot of ".$sbInfo['datadir_path']." via ssh: ".$sbInfo['backup_user']."@".$hostInfo['hostname']." ...", XBM_LOG_INFO);
		
						// Start the xtrabackup process
						$xbProc = proc_open($xbCommand, $xbDescriptors, $xbPipes);
		
						// Check that we launched OK.
						if( !is_resource($xbProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to use ssh to start innobackupex with: $xbCommand .");
						}
		
						// Check the status of the backup every 5 seconds...
						$streamContents = '';
						stream_set_blocking($xbPipes[2], 0);
						do {
							$streamContents .= stream_get_contents($xbPipes[2]);

							if( ! ( $xbStatus = proc_get_status($xbProc) ) ) {
								throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to retrieve status on backup process.");
							}
							sleep(5);
		
						} while ($xbStatus['running']);
		
		
						if($xbStatus['exitcode'] <> 0 ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: There was an error backing up - The process returned code ".$xbStatus['exitcode'].".".
													" The output from the backup is as follows:\n".$streamContents);
						} 
		
		
/*						print_r($xbStatus);
						echo "\nWe got STDOUT: \n";
						echo stream_get_contents($xbPipes[1]);
						echo "\nWe got STDERR: \n";
						echo stream_get_contents($xbPipes[2]); */
		
						$this->infolog->write("Xtrabackup completed with the following output:\n".$streamContents, XBM_LOG_INFO);
		
						// Close out backup process (it should be killed already)
						proc_close($xbProc);
		
						// Wait for netcat to stop 
						pclose($ncProc);	
		
		
		
						$this->infolog->write("Xtrabackup step completed OK. Proceeding with apply-log step...", XBM_LOG_INFO);
		
						// Begin the apply log process
		
						// Set the state of the snapshot to APPLY LOG
						$snapshot->setStatus('APPLY LOG');
		
		
						// Build the command for applying log
						//$applyCommand = ' cd '.$path.' ; innobackupex-1.5.1 --apply-log --ibbackup='.$xbBinary.' --defaults-file=backup-my.cnf ./ 1>&2';
						$applyCommand = $xbBinary." --prepare --apply-log-only --target-dir=".$path." --defaults-file=".$path."/backup-my.cnf 1>&2";
		
						// Set up how we'll interact with the IO file handlers of the process
						$applyDescriptors = Array(
											0 => Array('pipe', 'r'), // Process will read from STDIN pipe
											1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
											2 => Array('pipe', 'w')  // Process will write to STDERR pipe
										);
		
						// Info log output...
						$this->infolog->write("Running apply-log with command: ".$applyCommand, XBM_LOG_INFO);
		
		
						// Kick off the command
						$applyProc = proc_open($applyCommand, $applyDescriptors, $applyPipes);
		
						// Check that we launched OK.
						if( !is_resource($applyProc) ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to run apply log command: $applyCommand");
						}
		
						// Check the status of the apply log every 5 seconds...
						$streamContents = '';
						stream_set_blocking($applyPipes[2], 0);
						do {
							$streamContents .= stream_get_contents($applyPipes[2]);
							if( ! ( $applyStatus = proc_get_status($applyProc) ) ) {
								throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Unable to retrieve status on apply log process.");
							}
							sleep(5);
		
						} while ($applyStatus['running']);
		
		
						if($applyStatus['exitcode'] <> 0 ) {
							throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: There was an error applying logs - The process returned code ".$applyStatus['exitcode'].".".
													" The output from the apply log process is as follows:\n".$streamContents);
						}
		
		
						/* For debug
						print_r($applyStatus);
						echo "\nWe got STDOUT: \n";
						echo stream_get_contents($applyPipes[1]);
						echo "\nWe got STDERR: \n";
						echo stream_get_contents($applyPipes[2]); */
		
						// Write output to infolog..
						$this->infolog->write("Apply log process completed successfully with the following output:\n".$streamContents, XBM_LOG_INFO);
		
						// Close out backup process (it should be killed already)
						proc_close($applyProc);
		
		
						// Set the snapshot time to be now
						$snapshot->setSnapshotTime(date('Y-m-d H:i:s'));
						// Set the state of the snapshot to COMPLETED
						$snapshot->setStatus('COMPLETED');
		
						$this->infolog->write("Backup completed successfully!", XBM_LOG_INFO);

					} catch (Exception $e) {
						$this->infolog->write($e->getMessage(), XBM_LOG_ERROR);
						// Remove files and make status failed
						$snapshot->deleteFiles();
						$snapshot->setStatus('FAILED');
						// Rethrow
						throw new Exception($e->getMessage());
					}	
					// Clean up our runningBackup locks
					$runningBackup->finish();
	
				}
			
			// Catch the exception case where we need to clean up our runningBackup
			} catch (Exception $e) {
				// Clean up the runningBackup and then re-throw..
				$runningBackup->finish();
				throw new Exception($e->getMessage());
			}

			return true;

		} // end takeScheduledBackupSnapshot



		// Handle the exit on error clean up. We can add email hooks in here later.
		function errorExit($error, $runningBackup = false, $snapshot = false) {

			$this->infolog->write("A fatal error occurred:\n".$this->error, XBM_LOG_ERROR);
			if( is_object($runningBackup) ) {
				if( ! $runningBackup->finish() ) {
					$this->infolog->write("Additionally another error occured while trying to remove the runningBackup locks:\n".$runningBackup->error, XBM_LOG_ERROR);
					throw new Exception($this->error ."\n"."Additionally another error occured while trying to remove the runningBackup locks:\n".$runningBackup->error);
				}
			}

			if( is_object($snapshot) ) {
				if( ! $snapshot->setStatus('FAILED') ) {
					$this->infolog->write("Additionally another error occurred while trying to set the Backup Snapshot status to FAILED:\n".$snapshot->error, XBM_LOG_ERROR);
					throw new Exception($this->error ."\n"."Additionally another error occurred while trying to set the Backup Snapshot status to FAILED:\n".$snapshot->error);
				}

				$this->infolog->write("Attempting to remove files for the failed backup...", XBM_LOG_INFO);
				if( ! $snapshot->deleteFiles() ) {
					$this->infolog->write("Additionally another error occurred while trying to remove the files for this backup snapshot:\n".$snapshot->error, XBM_LOG_ERROR);
					throw new Exception($this->error ."\n"."Additionally another error occurred while trying to remove the files for this backup snapshot:\n".$snapshot->error);
				} else {
					$this->infolog->write("Files removed successfully.", XBM_LOG_INFO);
				}
			}

			

			return false;
		}

	}

?>
