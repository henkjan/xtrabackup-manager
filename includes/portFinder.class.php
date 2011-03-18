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

?>
