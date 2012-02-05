<?php
function loadModules()
	{
		$modules = array();
		$handler = opendir('./Modules');
		while($module = readdir($handler))
			{
				$ext = explode(".", $module, 2);
				if($module != "." && $module != ".." && ($ext[1] == "module.php"))
					{
						$modules[] = $module;
					}
			}
		closedir($handler);
		return $modules;
	}
?>

