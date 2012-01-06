<?php
/*

Copyright 2011-2012 Marin Software

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


	// This class is responsible for managing the materialized snapshots for a given scheduledBackup
	class materializedSnapshotManager {

		function __construct() {
			$this->infolog = false;
			$this->log = false;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		function setLogStream($log) {
			$this->log = $log;
		}


		// For the given scheduledBackup, update the materialized backup to the latest
		function materializeLatest($scheduledBackup = false) {

			// Validate
			if($scheduledBackup === false || !is_object($scheduledBackup) ) {
				throw new Exception('materializedSnapshotManager->materializeLatest: '."Error: Expected a scheduledBackup object to be passed as a parameter, but did not get one.");
			}

			// Find the latest backup snapshot for the scheduledBackup
			$snapshotGroups = $scheduledBackup->getSnapshotGroupsNewestToOldest();
			if(sizeOf($snapshotGroups) == 0 ) {
				throw new Exception('materializedSnapshotManager->materializeLatest: '."Error: Expected to find at least one snapshot group for the scheduledBackup, but got none.");
			}

			// Get the latest snapshot and its info..
			$latestSnapshot = $snapshotGroups[0]->getMostRecentCompletedBackupSnapshot();
			$latestSnapInfo = $latestSnapshot->getInfo();
			$latestSnapPath = $latestSnapshot->getPath();
			
			// Attempt to get the materialized snapshot for the scheduled Backup into "prevMaterialized"
			$prevMaterialized = $scheduledBackup->getMostRecentCompletedMaterializedSnapshot();

			// if we DO get one then we need to create a new materializedSnapshot
			if( $prevMaterialized != false ) {

				// Set state to UPDATING
				$prevMaterialized->setStatus('UPDATING');

				// Get the backup snapshot that the prevMaterialized is linked to
				$prevSnapshot = $prevMaterialized->getBackupSnapshot();
				// and its info..
				$prevSnapInfo = $prevSnapshot->getInfo();
				$prevSnapPath = $prevSnapshot->getPath();

				// if the materialized is already the latest
				if($latestSnapshot->id == $prevSnapshot->id) {
					// Just exit - nothing to do.
					return true;
				}

				// otherwise proceed - initialize a new materializedSnapshot
				$newMaterialized = new materializedSnapshot();
				$newMaterialized->setLogStream($this->log);
				$newMaterialized->init($scheduledBackup, $latestSnapshot);
				$materialPath = $newMaterialized->getPath();

				// Is the group for the materializedSnapshot the same as the latest snapshot?
				if($latestSnapInfo['snapshot_group_num'] == $prevSnapInfo['snapshot_group_num'] ) {

					// if yes - is the backupSnapshot the prevMaterialized is linked to a SEED?
					if($prevSnapInfo['type'] == 'SEED') {
						// if yes then --
						// we need to copy that seed to the new path first

						$copyCommand = 'cp -R '.$prevSnapPath.'/* '.$materialPath.'/';

						exec($copyCommand, $output, $returnVar);

						if($returnVar <> 0 ) {
							throw new Exception('materializedSnapshotManager->materializeLatest: '."Error: Failed to copy from $prevSnapPath to path $materialPath using command: $copyCommand -- Got output:\n".implode("\n",$output));
						}

						
					} else {

						// If the prevSnapshot is not a SEED then we need to move it to $materialPath
						if(!rename($prevMaterialized->getPath(), $materialPath) ) {
							throw new Exception('materializedSnapshotManager->materializeLatest: '."Error: Failed to move $prevSnapPath to $materialPath.");
						}

					}

					// now just roll forward, applying deltas step by step until we reach the current backup
					$xbBinary = $scheduledBackup->getXtraBackupBinary();

					$snapshotMerger = new backupSnapshotMerger();

					$parentSnapshot = $prevSnapshot;

					do {

						$deltaSnapshot = $parentSnapshot->getChild();
						$deltaPath = $deltaSnapshot->getPath();

						$this->infolog->write('Merging incremental snapshot deltas from '.$deltaPath.' into path '.$materialPath, XBM_LOG_INFO);

						$snapshotMerger->mergePaths($materialPath, $deltaPath, $xbBinary);

						$this->infolog->write('Done merging.', XBM_LOG_INFO);
						$parentSnapshot = $deltaSnapshot;

					} while ( $deltaSnapshot->id != $latestSnapshot->id );


				} else {
					// if groups for snapshot differ, is the backupSnapshot that we need to update to a SEED?
					if($latestSnapInfo['type'] == 'SEED') {
						// if yes, just create the symlink
						$newMaterialized->symlinkToSnapshot($latestSnapshot);
					} else {
						// if no, copy the SEED for the snapshotGroup of the new snapshot to the new path, then apply deltas
						$backupRestorer = new backupRestorer();
						$backupRestorer->setInfoLogStream($this->infolog);
						$backupRestorer->setLogStream($this->log);
						$backupRestorer->restoreLocal($latestSnap, $materialPath);
					}

				}
				// destroy the old snapshot
				$prevMaterialized->destroy();


			} else {
				// If no previous materialized snapshot exists at all..

				// Initialize a new materialized snapshot
				$newMaterialized = new materializedSnapshot();
				$newMaterialized->setLogStream($this->log);
				$newMaterialized->init($scheduledBackup, $latestSnapshot);
				$materialPath = $newMaterialized->getPath();

				// if we do not get one then check if the snapshot is a SEED
				if($latestSnapInfo['type'] == 'SEED') {
					// if yes - just create a symlink to it
					$newMaterialized->symlinkToSnapshot($latestSnapshot);

				} else {
					// if no - use the regular restore function to restore it to the target path
					$backupRestorer = new backupRestorer();
					$backupRestorer->setInfoLogStream($this->infolog);
					$backupRestorer->setLogStream($this->log);
					$backupRestorer->restoreLocal($latestSnapshot, $materialPath);
				}

			}


			$newMaterialized->setStatus('COMPLETED');

		} // Func: materializeLatest

	} // Class: materializedSnapshotManager

?>
