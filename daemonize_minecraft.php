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

	if (!defined('DAEMONIZE')) die("This file should not be run directly.");

	function minecraft_command($signal)
	{

		global $_CONFIG, $_STATE;

		switch ($signal)
		{
			case SIGHUP:
				logmsg("SIGHUP - reload");
				$command = 'reload';
				break;
			case SIGUSR1:
				logmsg("SIGUSR1 - save-on");
				$command = 'save-on';
				break;
			case SIGUSR2:
				logmsg("SIGUSR2 - save-off");
				$command = 'save-off';
				break;
			case SIGALRM:
				logmsg("SIGALRM - save-all");
				$command = 'save-all';

				if (isset($_CONFIG['AlarmInterval']))
					pcntl_alarm($_CONFIG['AlarmInterval']);
				break;

		}

		fwrite($_STATE['Descriptors'][0], $command.PHP_EOL);
		fflush($_STATE['Descriptors'][0]);
	}

	function minecraft_sigterm($signal)
	{
          echo "terming\n";
		global $_STATE;
		logmsg("SIGTERM - stop");
		$_STATE['Running'] = false;
		fwrite($_STATE['Descriptors'][0], 'stop'.PHP_EOL);
		fflush($_STATE['Descriptors'][0]);
		if (isset($_CONFIG['Command_Pidfile']) && file_exists($_CONFIG['Command_Pidfile']))
			unlink($_CONFIG['Command_Pidfile']);
	}

	pcntl_signal(SIGHUP, 'minecraft_command');
	pcntl_signal(SIGUSR1, 'minecraft_command');
	pcntl_signal(SIGUSR2, 'minecraft_command');
	pcntl_signal(SIGALRM, 'minecraft_command');
	pcntl_signal(SIGTERM, 'minecraft_sigterm');

?>
