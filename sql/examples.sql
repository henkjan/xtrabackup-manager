/* This is an example file showing how to create and setup basic hosts/backups */

/* Create a backup volume called My Storage Array that is accessible via /data */
INSERT INTO backup_volumes (name, path) VALUES ('My Storage Array', '/data');
SET @bv=LAST_INSERT_ID();

/* Create a host */

INSERT INTO hosts (hostname, description, active, staging_path) VALUES ('mybackuphost.mydomain.com', 'Example DB to Backup', 'Y', '/tmp');
SET @hv=LAST_INSERT_ID();

/* Create a scheduled_backup for the host */
INSERT INTO scheduled_backups (name, cron_expression, snapshots_retained, backup_user, 
	datadir_path, mysql_user, mysql_password, lock_tables, host_id, active, backup_volume_id, 
	mysql_type_id
) VALUES (
	'My Daily Backup Example', /* Name of the backup */
	'0 19 * * *', /* The cron expression that defines when you want this backup to fire - minute, hour, day, month, day-of-week */
	7, /* How many snapshots to retain - 7 for a week of daily backups */
	'mysql', /* The user to connect to the remote host with via SSH. 
				You need to setup SSH trust so that no pass is needed. 
				Needs access to the datadir, sp using mysql user is logical. */
	'/mysqldb/data', /* Path to the datadir on remote host that you want to backup */
	'backup', /* User that xtrabackup will use to connect to the MySQL DB and issue FLUSH TABLES WITH READ LOCK if necessary */
	'backupUserPassword', /* Password for the above mysql user. */
	'Y', /* Whether to issue FLUSH TABLES WITH READ LOCK at the end of the backup while copying .frm and MyISAM tables. */
	@hv, /* The host_id of the entry in the hosts table that corresponds to the host this backup should run on */
	'Y', /* Is this scheduled backup active? Y or N */
	@bv, /* The backup volume storage to use to store the snapshots for this scheduled backup */
	4   /* The mysql_type_id from the mysql_types table for the type of MySQL server that is running - 4 is MySQL 5.0 w/ built-in InnoDB
			This helps choose the correct xtrabackup binary to use for backup purposes */
);

