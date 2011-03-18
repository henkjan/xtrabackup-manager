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

	/* The user that xbm should install it's cron in and launch backups from */	
	$config['SYSTEM']['user'] = 'xbm';

	// The tmpdir to use -- usually for just generating temporary crontab files.
	$config['SYSTEM']['tmpdir'] = '/tmp';
	
	// Where the logs should be stored
	$config['LOGS']['logdir'] = '/home/xbm/xbm/logs';

	// What log level should we use - DEBUG or NORMAL
	$config['LOGS']['level'] = 'DEBUG';

	// The port range made available for use by XBM with netcat
	$config['SYSTEM']['port_range']['low'] = 10000;
	$config['SYSTEM']['port_range']['high'] = 11000;

	// How many can run at once
	// Globally in this install of XBM
	$config['SYSTEM']['max_global_concurrent_backups'] = 4;
	// For any one host at a time...
	$config['SYSTEM']['max_host_concurrent_backups'] = 1;

	// Currently this DOES NOTHING!
	// Maximum amount of time to sleep while waiting for a free slot at the host level
	// 0 is infinite 
	$config['SYSTEM']['max_host_sleep_secs'] = 0;


	// The hostname of the host xbm runs on - needs to resolve on the hosts to be backed up
	$config['SYSTEM']['xbm_hostname'] = 'bup06-int';

	// Where to send emails when ALERTs occur - not enabled yet
	$config['ALERTS']['email'] = 'lmulcahy@marinsoftware.com';
	$config['ALERTS']['enabled'] = true;

	/* Credentials for connecting to the XBM DB */
	$config['DB']['user'] = 'xbm';
	$config['DB']['password'] = 'xbm';
	$config['DB']['host'] = 'localhost';
	$config['DB']['port'] = 3306;
	$config['DB']['schema'] = 'xbm';
	$config['DB']['socket'] = '/mysqldb/tmp/mysql.sock';


?>
