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

 	/* classes related to DB conections */

	class dbConnectionGetter {

		function __construct() {
			$this->error = '';
		}

		// Return a new dbConnection connection object to use to connect to the DB
		function getConnection($logStream) {

			global $config;
	
			$conn = new dbConnection(
						$config['DB']['host'], 
						$config['DB']['user'], 
						$config['DB']['password'], 
						$config['DB']['schema'], 
						$config['DB']['port'],
						$config['DB']['socket']
						);

			// Check for error connecting...
			if($conn->connect_error) {
				$this->error = 'dbConnectionGetter->getConnection: ' . "Error: Can't connect to MySQL (".$conn->connect_errno.") "
					. $conn->connect_error;
				return false;
			}

			$conn->setLogStream($logStream);

			return $conn;

		}

	}

	class dbConnection extends mysqli {

		// Construct
		function __construct($host, $username, $password, $schema, $port, $socket) {

			parent::__construct($host, $username, $password, $schema, $port, $socket);
			$this->log = false;

		}

		function setLogStream($log) {
			$this->log = $log;
		}

		// Construct
		function query($sql) {

			if( ( $this->log !== false ) ) {
				$backtrace = debug_backtrace();
				if(isSet($backtrace[1]))
					$this->log->write($backtrace[1]['class'].$backtrace[1]['type'].$backtrace[1]['function'].': Sending SQL: '.$sql, XBM_LOG_DEBUG);
				else
					$this->log->write('Sending SQL: '.$sql, XBM_LOG_DEBUG);

				$timer = new Timer();
			}

			$res = parent::query($sql);

			if( ( $this->log !== false ) ) {
				$elapsed = $timer->elapsed();
				if(isSet($backtrace[1]))
					$this->log->write($backtrace[1]['class'].$backtrace[1]['type'].$backtrace[1]['function'].': Query took '.$elapsed, XBM_LOG_DEBUG);
				else
					$this->log->write('Query took '.$elapsed, XBM_LOG_DEBUG);

			}

			return $res;

		}
	}

?>
