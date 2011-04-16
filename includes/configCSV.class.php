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
class configCSV {

//		var $tables = array("backup_volumes", "hosts", "scheduled_backups", "config");
	var $tables = array("backup_volumes", "hosts", "scheduled_backups");
	var $dumpDir = "./confdata/";

	function __construct($log) {
		$this->setLogStream($log);
	}

	function setLogStream($log) {
		$this->log = $log;
	}

	function setDumpDir($dir) {
		$this->dumpDir = $dir;
	}

	function getDumpDir() {
		return $this->dumpDir;
	}

	function exportConfig($dir=null) {
		if( $dir ) {
			$this->setDumpDir($dir);
		}
		$dir = $this->getDumpDir();
		// MySQL needs this as absolute path.
		if( substr($dir, 0, 1) != '/' ) {
			$dir = getcwd() . '/' . $dir;
		}

		global $config;
		// We first dump all tables into $tmpdir, assuming that mysqld has write access there. Then we copy to final destination.
		$tmpdir = $config['SYSTEM']['tmpdir'] . '/xbm/';
		if( ! file_exists($tmpdir) ) {
			mkdir($tmpdir);
		}
		// Must be writable to mysqld, not just this script
		chmod($tmpdir, 0777);

		// Now connect to mysqld and dump tables one by one
		$dbGetter = new dbConnectionGetter($config);
		if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
			throw new Exception('configCSV->exportConfig: '."Error: Connection to MySQL failed: $dbGetter->error");
			return false;
		}
		foreach( $this->tables as $table ) {
			$fname = $table . ".csv";
			$sql = "SELECT * FROM " . $table . " INTO OUTFILE '" . $tmpdir . $fname . "'";
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			// Let's be extra nice to the user and fetch the column names onto the top of the file
			$sql = "SELECT * FROM " . $table ." LIMIT 1";
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			$fields = $res->fetch_fields();
			// Collect the column names from each object
			$field_names = array();
			foreach($fields as $field) {
				array_push($field_names, $field->name);
			}
			$header = implode("\t", $field_names);
			$header .= "\n";

			// Write to final destination, first headers, then actual data
			$data = file_get_contents($tmpdir.$fname);
			if( ! ( file_put_contents($dir.$fname, $header) 
                             && file_put_contents($dir.$fname, $data, FILE_APPEND) ) ) {
				throw new Exception('configCSV->exportConfig: '.
					"Error: The file $fname was successfully exported into $tmpdir, but failed to move it to the final location $dir.");
			}
			if( ! unlink( $tmpdir.$fname ) ) {
				throw new Exception('configCSV->exportConfig: '.
					"Warning: The file $fname was successfully exported into $dir, but failed to remove temporary copy in $tmpdir.");
			}
/*
			if( ! rename($tmpdir.$fname, $dir.$fname) ) {
				throw new Exception('configCSV->exportConfig: '.
					"Error: The file $fname was successfully exported into $tmpdir, but failed to move it to the final location $dir.");
			}
*/
		}
	}

	function importConfig($dir=null) {
		if( $dir ) {
			$this->setDumpDir($dir);
		}
		$dir = $this->getDumpDir();
		// MySQL needs this as absolute path.
		if( substr($dir, 0, 1) != '/' ) {
			$dir = getcwd() . '/' . $dir;
		}

		global $config;
		// We first copy CSV into $tmpdir, assuming that mysqld has read access there. Then we LOAD DATA from there.
		$tmpdir = $config['SYSTEM']['tmpdir'] . '/xbm/';
		if( ! file_exists($tmpdir) ) {
			mkdir($tmpdir);
		}
		chmod($tmpdir, 0777);

		// Create mysql connection
		$dbGetter = new dbConnectionGetter($config);
		if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
			throw new Exception('configCSV->exportConfig: '."Error: Connection to MySQL failed: $dbGetter->error");
			return false;
		}
		foreach( $this->tables as $table ) {
			$fname = $table . ".csv";

			// Our CSV files contain column names on first row. Remove it.
			$data = file_get_contents($dir.$fname);
			$data_array = explode("\n", $data);
			array_shift($data_array);
			$data = implode("\n", $data_array);
			if( ! file_put_contents($tmpdir.$fname, $data) ) {
				throw new Exception('configCSV->exportConfig: '.
					"Error: The file $fname was successfully exported into $tmpdir, but failed to move it to the final location $dir.");
			}
			chmod($tmpdir.$fname, 0777);

			$sql = "TRUNCATE TABLE " . $table;
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			$sql = "LOAD DATA INFILE '" . $tmpdir.$fname . "' INTO TABLE " . $table;
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			if( ! unlink( $tmpdir.$fname ) ) {
				throw new Exception('configCSV->exportConfig: '.
					"Warning: The configurations in $fname were successfully imported into MySQL, but failed to remove temporary copy in $tmpdir.");
			}
		}
	}
}
?>
