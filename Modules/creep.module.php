<?php

$core->moduleconf['help_admin']['invisible'] = 'Set Bot as Invisible! (InspIRCD with m_invisible Only)';
$core->moduleconf['help_admin']['visible'] = 'Set Bot as Visible! (InspIRCD with m_invisible Only)';

	function u_invisible()
		{
			global $core;			
			if (! $core->has_access($core->con['buffer']['user_host'], 10))
					{
						$core->notice("Access Denied");
						return;
					}
			$core->send("MODE " . $core->CONFIG['nick'] . " +Q");
			$core->notice("Going invisible...");

		}

	function u_visible()
		{
			global $core;			
			if (! $core->has_access($core->con['buffer']['user_host'], 10))
					{
						$core->notice("Access Denied");
						return;
					}
			$core->send("MODE " . $core->CONFIG['nick'] . " -Q");
			$core->notice("Becoming visible...");

		}


?>