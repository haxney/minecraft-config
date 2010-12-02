#!/usr/local/bin/php
<?php

/*

Copyright (c) 2010, Adam Pippin
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. All advertising materials mentioning features or use of this software
   must display the following acknowledgement:
   This product includes software developed by Adam Pippin.
4. Neither the name Adam Pippin or the names of its contributors may be
   used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY ADAM PIPPIN ''AS IS'' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL ADAM PIPPIN BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

	/* Default settings. Can be overridden on the command line. */

	$_CONFIG = array(
		// The command to run, should be the full path to the server_nogui.sh script.
		'Command'=>'/home/dhackney/mine/server_nogui.sh',
		// Pidfile for this script. Null to not output one.
		'Monitor_Pidfile'=>null,
		// Pidfile for our launched command. Null to not output one.
		'Command_Pidfile'=>'/home/dhackney/mine/minecraft.pid',
		// Logfile for this script, null to not log.
		'Logfile'=>'/home/dhackney/mine/logs/daemon.log',
		// GID to run the command as. Must be a numeric id, not a name.
		'GID'=>32009,
		// UID to run the command as. Must be a numeric id, not a name.
		'UID'=>32007,
		// Where to write the command's stdout. Use /dev/null to not output.
		'Stdout'=>'/dev/null',
		// Where to write the command's stderr. Use /dev/null to not output.
		'Stderr'=>'/dev/null',
		// The file to include to handle signals. It will include "BASEPATH/daemonize_<SIGNALS FILE>.php".
		'Signals'=>'minecraft',
		// The working directory when we spawn the command.
		'WorkingDirectory'=>getcwd(),
		// Environment variables when we spawn the command.
		'Environment'=>$_ENV,
		// If set, fires the ALRM signal every AlarmInterval seconds. Use for
		// automatic saving, etc.
		'AlarmInterval'=>600,
		// The number of seconds to wait between polling the state of the
		// command.
		'MonitorInterval'=>5,
		// Prints log messages to stdout
		'Interactive'=>false
		);

	/* Holds the current state of the program. */

	$_STATE = array(
		'Running'=>true,
		'LogHandle'=>null,
		'ProcessHandle'=>null,
		'Descriptors'=>null,
		'Status'=>null
		);

	// Automatically detect the path to this script (so we know where to look
	// for included files).
	define('BASEPATH', dirname(__FILE__).'/');
	// Bypass the root uid/gid check.
	define('I_AM_INSECURE', false);
	define('DAEMONIZE', true);

	/* Parse command line arguments */

	for ($i=1; $i<$argc; $i++)
	{
		if (!stristr($argv[$i], '='))
			throw new Exception("Invalid command line argument: \"".$argv[$i]."\"");

		$key = substr($argv[$i], 0, strpos($argv[$i], '='));
		$value = substr($argv[$i], strpos($argv[$i], '=')+1);

		if (!isset($_CONFIG[$key]))
			throw new Exception("Unknown command line argument: \"".$key."\"");

		$_CONFIG[$key] = $value;
	}

	/* Attempt to validate configured values */

	if (!file_exists($_CONFIG['Command']))
		throw new Exception("Command does not exist.");
	if (file_exists($_CONFIG['Monitor_Pidfile']))
		throw new Exception("Monitor pidfile already exists (".$_CONFIG['Monitor_Pidfile']."). Already running?");
	if (file_exists($_CONFIG['Command_Pidfile']))
		throw new Exception("Command pidfile already exists (".$_CONFIG['Command_Pidfile']."). Already running?");
	if (!is_numeric($_CONFIG['GID']))
		throw new Exception("Invalid group id. Must be specified numerically.");
	if (!is_numeric($_CONFIG['UID']))
		throw new Exception("Invalid user id. Must be specified numerically.");
	if (($_CONFIG['GID']==0 || $_CONFIG['UID']==0) && !I_AM_INSECURE)
		throw new Exception("Running services as root is a BAD IDEA. Change the \"I_AM_INSECURE\" constant in the daemonize script to bypass this check.");
	if (isset($_CONFIG['Signals']) && !file_exists(BASEPATH.'daemonize_'.$_CONFIG['Signals'].'.php'))
		throw new Exception("Signals file (".$_CONFIG['Signals'].") does not exist.");
	if (!file_exists($_CONFIG['WorkingDirectory']) && is_dir($_CONFIG['WorkingDirectory']))
		throw new Exception("Working directory does not exist.");
	if (isset($_CONFIG['AlarmInterval']) && !is_numeric($_CONFIG['AlarmInterval']))
		throw new Exception("Invalid alarm interval. Must be a numeric value specifying seconds.");
	if (!isset($_CONFIG['MonitorInterval']) || !is_numeric($_CONFIG['MonitorInterval']))
		throw new Exception("Invalid monitor interval. Must be a numeric value specifying seconds.");

	// Set the tick interval to be more frequent - required to intercept signals.
	declare(ticks = 1);

	// Include the signal handlers
	if (!@include(BASEPATH.'daemonize_'.$_CONFIG['Signals'].'.php'))
		throw new Exception("Could not include the signals file.");

	if (isset($_CONFIG['Logfile']))
		$_STATE['LogHandle'] = fopen($_CONFIG['Logfile'], 'a');

	if (isset($_CONFIG['Monitor_Pidfile']))
		file_put_contents($_CONFIG['Monitor_Pidfile'], getmypid());

	logmsg("-----");
	logmsg("Daemonize - By Adam Pippin (NuclearDog)");
	logmsg("http://nucleardog.com/");
	logmsg("adam@nucleardog.com");
	logmsg("Command: \"".$_CONFIG['Command']."\"");
	logmsg("Monitor Pidfile: \"".$_CONFIG['Monitor_Pidfile']);
	logmsg("Command Pidfile: \"".$_CONFIG['Command_Pidfile']);
	logmsg("Running as UID/GID ".$_CONFIG['UID']."/".$_CONFIG['GID']);
	logmsg("Piping stdout to: \"".$_CONFIG['Stdout']);
	logmsg("Piping stderr to: \"".$_CONFIG['Stderr']);
	logmsg("Loading signals file: \"".$_CONFIG['Signals']."\"");
	logmsg("Working Directory: \"".$_CONFIG['WorkingDirectory']."\"");
	logmsg("");

	for (;;)
	{
		// Start the command
		logmsg("Starting command");
		commandStart();

		// Block until it quits
		logmsg("Monitoring");
		commandMonitor();

		// Clean up descriptors and stuff
		logmsg("Died - Cleaning up");
		commandCleanup();

		// If we're shutting down, break out of the loop.
		if ($_STATE['Running']==false)
		{
			logmsg("Saying goodnight - remember to tip your waitress!");
			break;
		}

	}

	if (isset($_CONFIG['Monitor_Pidfile']))
		unlink($_CONFIG['Monitor_Pidfile']);

	/* Functions */

	function logmsg($str)
	{
		global $_CONFIG, $_STATE;
		$str = '['.date('Y-m-d H:i:s').'] '.$str;
		if ($_CONFIG['Interactive']==true)
			echo $str.PHP_EOL;
		if (!isset($_STATE['LogHandle'])) return;
		fputs($_STATE['LogHandle'], $str.PHP_EOL);
		fflush($_STATE['LogHandle']);
	}

	function commandStart()
	{
		global $_CONFIG, $_STATE;

		// Set up the descriptors for the process
		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('file', $_CONFIG['Stdout'], 'a'),
			2 => array('file', $_CONFIG['Stderr'], 'a')
			);
		// Set the current working directory
		$cwd = $_CONFIG['WorkingDirectory'];
		// Set up the environment variables
		$env = $_CONFIG['Environment'];

		// Set the effective uid/gid so we spawn the process as the correct user.
		posix_setegid($_CONFIG['GID']);
		posix_seteuid($_CONFIG['UID']);

		$_STATE['ProcessHandle'] = proc_open($_CONFIG['Command'], $descriptors, $_STATE['Descriptors'], $cwd, $env);

		if (!isset($_STATE['ProcessHandle']) || !is_resource($_STATE['ProcessHandle']))
			throw new Exception("Could not start command.");

		// Reset the effective uid/gid
		posix_setegid(0);
		posix_seteuid(0);

		$_STATE['Status'] = proc_get_status($_STATE['ProcessHandle']);
		if (isset($_CONFIG['Command_Pidfile']))
			file_put_contents($_CONFIG['Command_Pidfile'], $_STATE['Status']['pid']);

		if (isset($_CONFIG['AlarmInterval']))
			pcntl_alarm($_CONFIG['AlarmInterval']);
	}

	function commandMonitor()
	{
		global $_CONFIG, $_STATE;

		for (;;)
		{
			$_STATE['Status'] = proc_get_status($_STATE['ProcessHandle']);
			if (!isset($_STATE['Status']) || $_STATE['Status']['running']==false)
				break;
			sleep($_CONFIG['MonitorInterval']);
		}

		if (isset($_CONFIG['Command_Pidfile']))
			unlink($_CONFIG['Command_Pidfile']);
	}

	function commandCleanup()
	{
		global $_CONFIG, $_STATE;

		fclose($_STATE['Descriptors'][0]);
		proc_close($_STATE['ProcessHandle']);
	}

?>
