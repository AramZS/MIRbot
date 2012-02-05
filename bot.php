<?php

include "config.php";
include "loadmodules.php";
$CONFIG['modules'] = loadModules();
include "Modules/core.class.php";

set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);
	
$core = new Core($CONFIG);
$core->logging_enable($CONFIG['logging']);

while ($core->reconnect)
	{
		$core->connect();
		$core->loadModuleFiles();

		sleep(8);
		foreach ($CONFIG['commands'] as $command)
			$core->send($command);	

		$join = $core->rejoin();	

		foreach ($join as $chan)
			$core->join($chan);	

		$core->listen();	
			if (!$core->reconnect)
				{
					echo "Terminating.\n";		
					break;
				}
		echo "Delaying 10 seconds before reconnect.\n";
		sleep(10);
		$core->clean();
	}
?>
