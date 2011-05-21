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

			// Get the backup strategy for the scheduledBackup
			$sbInfo = $scheduledBackup->getInfo();

			// create a backupTakerFactory
			$takerFactory = new backupTakerFactory();

			// use it to get the right object type for the backup strategy
			$backupTaker = $takerFactory->getBackupTakerByStrategy($sbInfo['strategy_code']);
			
			// Populate it with the relevant settings like log/infolog/Verbose, etc.
			$backupTaker->setLogStream($this->log);
			$backupTaker->setInfoLogStream($this->infolog);
			$backupTaker->setInfoLogVerbose($this->infologVerbose);

			// Kick off takeScheduledBackupSnapshot method of the actual backup taker
			$backupTaker->takeScheduledBackupSnapshot($scheduledBackup);

			return true;

		} // end takeScheduledBackupSnapshot


	}

?>
