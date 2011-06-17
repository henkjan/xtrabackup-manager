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
class configCSV {

//		var $tables = array("backup_volumes", "hosts", "scheduled_backups", "config");
	var $tables = array("backup_volumes", "hosts", "scheduled_backups");
	var $dumpDir = "./confdata/";
	var $fpaths = array(); // Set in constructor

	function __construct($log) {
		$this->setLogStream($log);
		foreach ( $this->tables as $t ) {
			array_push( $this->fpaths, $this->dumpDir . $t );
		}
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
		$tmpdir = $config['SYSTEM']['tmpdir'] . '/xbm-conftool/';
		if( ! file_exists($tmpdir) ) {
			mkdir($tmpdir);
		}
		else {
			// If directory exists, remove any old files that may have been left there
			$this->rm($tmpdir . '/*');
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
				// Cleanup
				$this->rm($tmpdir);
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			// Let's be extra nice to the user and fetch the column names onto the top of the file
			$sql = "SELECT * FROM " . $table ." LIMIT 1";
			if( ! ($res = $conn->query($sql) ) ) {
				// Cleanup
				$this->rm($tmpdir);
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
			if( ! file_put_contents($dir.$fname, $header) ) {
				// Cleanup
				$this->rm($tmpdir);
				$this->rm($this->fpaths);
				throw new Exception('configCSV->exportConfig: '.
					"Error: The file $fname was successfully exported into $tmpdir, but failed to write to the final location $dir. (Trying to write headers.)");
			}
			if( strlen($data) > 0 ) {
				if( ! file_put_contents($dir.$fname, $data, FILE_APPEND) ) {
					// Cleanup
					$this->rm($tmpdir);
					$this->rm($this->fpaths);
					throw new Exception('configCSV->exportConfig: '.
						"Error: The file $fname was successfully exported into $tmpdir, but failed to move it to the final location $dir.");
				}
			}
			if( ! unlink( $tmpdir.$fname ) ) {
				// Cleanup
				$this->rm($tmpdir);
				$this->rm($this->fpaths);
				throw new Exception('configCSV->exportConfig: '.
					"Warning: The file $fname was successfully exported into $dir, but failed to remove temporary copy in $tmpdir.");
			}
		}
		// Cleanup
		$this->rm($tmpdir);
	}

	function importConfig($dir=null) {
		if( $dir ) {
			$this->setDumpDir($dir);
		}
		$dir = $this->getDumpDir();

		global $config;
		// We first copy CSV into $tmpdir, assuming that mysqld has read access there. Then we LOAD DATA from there.
		$tmpdir = $config['SYSTEM']['tmpdir'] . '/xbm-conftool/';
		if( ! file_exists($tmpdir) ) {
			mkdir($tmpdir);
		}
		else {
			// If directory exists, remove any old files that may have been left there
			$this->rm($tmpdir . '/*');
		}
		chmod($tmpdir, 0777);

		// Create mysql connection
		$dbGetter = new dbConnectionGetter($config);
		if( ! ( $conn = $dbGetter->getConnection($this->log) ) ) {
				// Cleanup
				$this->rm($tmpdir);
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
				// Cleanup
				$this->rm($tmpdir);
				throw new Exception('configCSV->exportConfig: '.
					"Error: Failed to move $fname to $tmpdir.");
			}
			chmod($tmpdir.$fname, 0777);

			$sql = "TRUNCATE TABLE " . $table;
			if( ! ($res = $conn->query($sql) ) ) {
				// Cleanup
				$this->rm($tmpdir);
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			$sql = "LOAD DATA INFILE '" . $tmpdir.$fname . "' INTO TABLE " . $table;
			if( ! ($res = $conn->query($sql) ) ) {
				// Cleanup
				$this->rm($tmpdir);
				throw new Exception('configCSV->exportConfig: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
			if( ! unlink( $tmpdir.$fname ) ) {
				// Cleanup
				$this->rm($tmpdir);
				throw new Exception('configCSV->exportConfig: '.
					"Warning: The configurations in $fname were successfully imported into MySQL, but failed to remove temporary copy in $tmpdir.");
			}
		}
	}

	/**
	 * Remove files matching $fileglob parameter.
	 *
	 * @param $fileglob String or array of strings that are filename(s) or glob pattern(s).
	 * @see http://www.php.net/manual/en/function.unlink.php#53549
	 */
	function rm($fileglob)
	{
		// Note that this is $this->rm() calling rm(). Not recursive.
		return rm($fileglob);
	}
}


	/**
	 * Remove files matching $fileglob parameter.
	 *
	 * Note: Moved outside the class because didn't know how to pass member method to array_map().
	 * 
	 * @param $fileglob String or array of strings that are filename(s) or glob pattern(s).
	 * @see http://www.php.net/manual/en/function.unlink.php#53549
	 */
	function rm($fileglob)
	{
		if (is_string($fileglob)) {
			if (is_file($fileglob)) {
				return unlink($fileglob);
			} else if (is_dir($fileglob)) {
				$ok = rm("$fileglob/*");
				if (! $ok) {
					return false;
				}
				return rmdir($fileglob);
			} else {
				$matching = glob($fileglob);
				if ($matching === false) {
					// This is not an error
					// throw new Exception('configCSV->exportConfig: Error: No files match supplied glob ' . $fileglob);
					return false;
				}	  
				$rcs = array_map('rm', $matching);
				if (in_array(false, $rcs)) {
					return false;
				}
			}	  
		} else if (is_array($fileglob)) {
			$rcs = array_map('rm', $fileglob);
			if (in_array(false, $rcs)) {
				return false;
			}
		} else {
			throw new Exception('configCSV->exportConfig: Error: Param #1 must be filename or glob pattern, or array of filenames or glob patterns');
			return false;
		}
		return true;
	}


?>
