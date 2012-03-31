<?php

class Core
	{
		var $CONFIG;
		var $MODULE;
		var $users;
		var $reconnect = true;
		var $logging_enable = 0;
		var $joined;
		var $logging_fp;
		var $channel_users;
		var $moduleconf;
		var $on;
		
		function __construct($config)
			{
				$this->CONFIG = $config;
				$this->declared = array();
				$this->on = array("null");
				$this->moduleconf = array('help_basic' => array(), 'admin_help' => array());
				$this->clean();
			}
		
		function Core($config)
			{
				$this->CONFIG = $config;
				$this->declared = array();
				$this->on = array("null");
				$this->moduleconf = array('help_basic' => array(), 'admin_help' => array());
				$this->clean();
			}
			
		function clean()
			{
				$this->con = array();
				$this->users = array(array());
				$this->joined = array();
				$this->logging_fp = array();
				$this->channel_users = array(array());
				$this->on = array("null");
			}
		
		function loadModuleFiles()
			{
				foreach($this->CONFIG['modules'] as $module)
					{
						$name = explode(".", $module);
						$name = $name[0];
						$module_code = str_replace(array("<?php", "?>"), "", file_get_contents("./Modules/" . $module));
						if(!in_array($name, $this->declared))
							{
								eval($module_code);
								$this->declared[] = $name;
							}
					}
				return;
			}
			
		function serializer($data)
			{
				$serialize = serialize($data);
				$nl2br = preg_replace("/\r\n|\n|\r/", "<br />", $serialize);
				$slashes = addslashes($nl2br);
				$output = $slashes;
				return $output;
			}
			
		function deserializer($data)
			{
				$slashes = stripslashes($data);
				$br2nl = preg_replace("<br \/>", "\n", $slashes);
				$unserialize = unserialize($br2nl);
				$output = $unserialize;
				return $output;
			}
			
		function rectify($data)
			{
				$output = explode("\n", $data);
				return $output;
			}
			
		# 3:19, I am a machine Gene!
		
		function read($file)
			{
				$open = fopen($file, "r");
				$data = fread($open, filesize($file) + 1024);
				fclose($open);
				return $data;
			}
			
		function append($data, $file)
			{
				$open = fopen($file, "a");
				fwrite($open, $data);
				fclose($open);
			}
			
		function write($data, $file)
			{
				$open = fopen($file, "w");
				fwrite($open, $data);
				fclose($open);
			}

		function array_remove(array &$a_Input, $m_SearchValue, $b_Strict = False)
			{
    				$a_Keys = array_keys($a_Input, $m_SearchValue, $b_Strict);
    				foreach($a_Keys as $s_Key)
					{
        					unset($a_Input[$s_Key]);
    					}
    				return $a_Input;
			}
			
		function connect()
			{
				$this->con['socket'] = fsockopen($this->CONFIG['server'], $this->CONFIG['port'], $errno, $errstr, 30);
					if (!$this->con['socket'])
						{
							$this->m_print ("Could not connect to: " . $this->CONFIG['server'] ." on port ". $this->CONFIG['port']);
							die();
							return false;
						}

				#$this->send("NICK " . $this->CONFIG['nick'] . " " . $this->CONFIG['name']);				
				$this->send("NICK " . $this->CONFIG['nick']);
				$this->send("USER " . $this->CONFIG['nick'] . " " . $this->CONFIG['owner'] . " " . $this->CONFIG['owner'] . " :". $this->CONFIG['name']);
				
				
				while (!feof($this->con['socket']))
					{
						$this->con['buffer']['all'] = trim(fgets($this->con['socket'], 4096));
			
						$this->m_print (date("[d/m @ H:i]") . "<- " . $this->con['buffer']['all']);
						
						if(substr($this->con['buffer']['all'], 0, 6) == 'PING :')
							{
								$this->send('PONG :' . substr($this->con['buffer']['all'], 6));
								return true;
							}

						if(strstr($this->con['buffer']['all'], "376") || stristr($this->con['buffer']['all'], "255"))
							{
								$this->send('PING :' . $this->CONFIG['server']);
							}

						if(strstr($this->con['buffer']['all'], "PONG"))
							{
								return true;
							}
					}
			}
			
		function join($channel)
			{
				$this->send("JOIN " . $channel);
				if($this->CONFIG['sayonline'] > 0)
					{
						$this->send("PRIVMSG " . $channel . " :" . $this->CONFIG['onlinemsg']);
					}
				$this->send("MODE " . $channel . " +ao " . $this->CONFIG['nick'] . " " . $this->CONFIG['nick']);
				$this->joined[] = $channel;
			}
		

		function listen()
			{
				while (!feof($this->con['socket']))
					{
						$this->con['buffer']['all'] = trim(fgets($this->con['socket'], 4096));
			
						if (strlen($this->con['buffer']['all']) <= 0)
						continue;

						$this->m_print (date("[d/m @ H:i]")."<- ".$this->con['buffer']['all']);
			
							if(substr($this->con['buffer']['all'], 0, 6) == 'PING :')
								{
									$this->send('PONG :'.substr($this->con['buffer']['all'], 6));
								}
							elseif ($old_buffer != $this->con['buffer']['all'])
								{
									$this->parse_buffer();
									$this->process_commands();				
								}
						$old_buffer = $this->con['buffer']['all'];
					}
			}
			
		function process_commands()
			{
				$tmp = explode(" ", $this->con['buffer']['text'], 2);
				$first_word = $tmp[0];	
	
				$funtion = 0;
				
				if($this->checkIgnored($this->con['buffer']['username']))
					return;
				
				if(substr($first_word, 0, strlen($this->CONFIG['prefix'])) == $this->CONFIG['prefix'])
					{
						$command = substr($first_word, strlen($this->CONFIG['prefix']));
						$function = "u_" . $command;
					} 
					
		
				if (method_exists($this, $function))
					$this->$function($this->con['buffer']['text']);
				elseif(function_exists($function))
					call_user_func($function, $this->con['buffer']['text']);
			}
			
			
		function swatchtime($unixtime = NULL, $showdate = 1)
  			{
				if($unixtime == NULL)
					{
						$unixtime = gmdate("U");
					}
					$mpb = 1.440;
					$h = gmdate("H", $unixtime) + 1;
					$i = gmdate("i", $unixtime);
					$s = gmdate("s", $unixtime);

					$b = (($h * 60) + $i + ($s / 60)) / $mpb;
		
						$bfloor = floor($b %1000);
						$zero = 3 - strlen($bfloor);
						if($showdate > 0)
							{
								$bstr = "d" . gmdate("d") . "." . gmdate("m") . "." . gmdate("y") . " @";
							} else {
								$bstr = "@";
								}
						for($i = 1 ; $i <= $zero ; $i++)
							{
								$bstr .= "0";
							}
				
						$bstr .= $bfloor;
					return $bstr;
				}
		
		function onCommand($text)
			{
				if(!is_array($this->on) || count($this->on) <= 1)
					return;
					
				if($this->checkIgnored($text['username']))
					return;
				
				foreach($this->on as $function)
					{
						$function = "o_" . $function;						
						if (method_exists($this, $function))
							$this->$function($text);
						elseif(function_exists($function))
							call_user_func($function, $text);
					}
				return;
			}
	
		function parse_buffer()
			{
		
				$buffer = $this->con['buffer']['all'];
				$buffer = explode(" ", $buffer, 4);
				
				#$extb = explode(" ", $buffer, 5);
				
				$cut = explode("!", $buffer[0], 2);
		
				$buffer['username'] = str_replace(":", "", $cut[0]);
				
				$chop = explode("@", $cut[1]);
				
				$buffer['identd'] = $chop[0];
				
				$buffer['hostname'] = substr($buffer[0], strpos($buffer[0], "@")+1);
		
				$buffer['user_host'] = substr($buffer[0],1);
				
				$buffer['channel_temp'] = str_replace(":", "", $buffer[2]);
		
					switch(strtoupper($buffer[1]))
						{
							case "JOIN":
			   					$buffer['text'] = "*JOINS: ". $buffer['username']." ( ".$buffer['user_host']." )";
								$buffer['command'] = "JOIN";
								$buffer['channel'] = $buffer['channel_temp'];
								if($this->CONFIG['welcome_new'] > 0 && $buffer['username'] != $this->CONFIG['nick'])
									{
										$this->notice(str_replace("{NICK}", $buffer['username'], $this->CONFIG['welcome_msg']), $buffer['username']);
									}
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Idle");
			   				break;
							case "QUIT":
			   					$buffer['text'] = "*QUITS: ". $buffer['username']." ( ".$buffer['user_host']." )";
								$buffer['command'] = "QUIT";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Leaving");
			   				break;
							case "NOTICE":
			   					$buffer['text'] = "*NOTICE: ". $buffer['username'];
								$buffer['command'] = "NOTICE";
								$buffer['channel'] = substr($buffer[2], 1);
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Making a Notice");
			   				break;
							case "PART":
			  					$buffer['text'] = "*PARTS: ". $buffer['username']." ( ".$buffer['user_host']." )";
								$buffer['command'] = "PART";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Parting");
			  				break;
							case "MODE":
			  					$buffer['text'] = $buffer['username']." sets mode: ".$buffer[3];
								$buffer['command'] = "MODE";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Changing the Mode");
							break;
							case "NICK":
								$buffer['text'] = "*NICK: ".$buffer['username']." => ".substr($buffer[2], 1)." ( ".$buffer['user_host']." )";
								$buffer['command'] = "NICK";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Changing Nick");
							break;
							case "TOPIC":
								$buffer['text'] = "*TOPIC: ".$buffer[3]."";
								$buffer['command'] = "TOPIC";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Changing Topic");
							break;
							case "KICK":
								$tempus = explode(" ", $buffer[3]);
								$buffer['text'] = $buffer['username']." KICKS: ".$tempus[0];
								$buffer['command'] = "KICK";
								$buffer['channel'] = $buffer['channel_temp'];
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Kicking Someone");
							break;
																	
							default:
								$buffer['command'] = $buffer[1];
								$buffer['channel'] = $buffer[2];
									if (strpos($buffer['channel'], "#") === false)
										{
											$buffer['channel'] = $buffer['username'];
										}
									$buffer['text'] = substr($buffer[3], 1);
								$this->onCommand($buffer);
								$this->seenlog($buffer['username'], $buffer['channel'], "Talking");
								if($buffer['command'] == "PRIVMSG" && $buffer['channel'] != NULL && $buffer['text'] != NULL)
										{
											$this->checkstatus($buffer['text'], $buffer['channel'], $buffer['username']);
										}
							break;	
						}
				$this->con['buffer'] = $buffer;
			}
			
		function pm($message, $target = "")
			{
				$channel = $target;
				if($target == "")
					{
						$channel = $this->con['buffer']['username'];
					}
				$pm = 'PRIVMSG '. $channel .' :' . $message;
				$this->send($pm);
			}
			
		function notice($message, $target = "")
			{
				if($target == "")
					{
						$target = $this->con['buffer']['username'];
					}
				$notice = 'NOTICE '. $target .' :'.$message;
				$this->send($notice);
			}
			
		function checkIgnored($username)
			{
				$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
				
				if($data == NULL || $data == "")
					{
						$this->write($this->serializer(array()), $this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
						$data = array();
					}
				
				$data = $this->deserializer($data);
				
				
				if(in_array(strtolower($username), $data))
					return true;
				else
					return false;
			}
			
		function checkstatus($buffer, $channel, $username)
			{
				$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
				$data_ = $this->rectify($data);
				
				foreach($data_ as $entry)
					{
						$afk = $this->deserializer($entry);
						if($afk['username'] != "" && $afk['username'] != NULL)
							{
								$strstr = stristr(str_replace(",", "", $buffer), $afk['username']);
							} 
								else
									{
										$strstr = NULL;
									}
									
						if($strstr != FALSE && $strstr != NULL)
							{
								$n = 0;
								$i = NULL;

								foreach($afk['asked'] as $asked)
									{
										$key = NULL;
										$i = NULL;
										if($key == NULL)
											{
											if($asked['username'] === strtolower($username))
												{
													if(date("U") - $asked['time'] > 600)
														{
															$key = NULL;
															$i = $n;
															unset($afk['asked'][$n]);
															$afk['asked'] = array_values($afk['asked']);
															break;
														} else
															{
																$key = $n;
break;	
															}
												} else
													{
														$key = NULL;
													}
											$n = $n + 1;
											} else
												{
													$key = $key;
													continue;
												}
									}

								if(!is_numeric($key) || is_numeric($i))
									{
										$key = NULL;
										$i = NULL;
										$n = 0;

										$afk['asked'][] = array('username' => strtolower($username), 'time' => date("U"));
										$this->notice($afk['username'] . " has been away for " . $this->event($afk['time']) .". REASON: '" . $afk['reason'] . "'", $username);
										if(!$this->checkIgnored($username))
											{
												$this->pm(ucfirst($afk['username']) . ", " . $username . " has just asked for you in " . $channel . ". You have been away for " . $this->event($afk['time']), $afk['username']);
												$new = array(	'username' => $afk['username'],
																'reason' => $afk['reason'],
																'time' => $afk['time'],
																'asked' => $afk['asked']);
												$new = $this->serializer($new);
												$this->write(str_replace($entry, $new, $data), $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']); 
											}
									
									}
									
								return;
							}
					}
			}
		
		function m_print ($string)
			{
				$string = $string."\n";
				print($string);
				$this->log($string, $this->CONFIG['log_dir'] . "" . $this->CONFIG['log_name'] ."-" . date("dD.M.Y") . "" . $this->CONFIG['log_ext']);
			}
	
		function log($string, $file)
			{
				if ($this->logging_enable == 0)
					{
						return;
					}
		
				if (! isset($this->logging_fp[$file]))
					{
						if ( ! ($this->logging_fp = fopen($file, "a")))
							{
								print("Could not open " . $file . " for appending.");
								die();
								return;
							}
					}
		
				if (! fwrite($this->logging_fp, $string))
					{
						print("Could not write to file '" . $file . "' for logging.");
						die();
					}
				
			}
			
		function rejoin()
			{
				$data = $this->deserializer($this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['botassignfile']));
				if(is_array($data))
					{
						$output = array_merge($data, $this->CONFIG['channels']);
					} else
						{
							$output = $this->CONFIG['channels'];
						}
				return $output;
			}
			
		function seenlog($username, $channel, $action)
			{
				$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['userfile']);
				$data_ = $this->deserializer($data);

				$new = array();
				$replace = array();

				foreach($data_ as $entry)
					{
						if(strtolower($entry['username']) == strtolower($username))							{
								$entry = array('username' => $username,
										'channel' => $channel,
										'action' => $action,
										'time' => date("U"));

								$replace[] = 1;
							}

						$new[] = $entry;					}

				if(count($replace) < 1)
					$new[] = array('username' => $username,
							'channel' => $channel,
							'action' => $action,
							'time' => date("U"));

				$new = $this->serializer($new);
				$this->write($new . "\n", $this->CONFIG['data_dir'] . "" . $this->CONFIG['userfile']);

			}
			
		function logging_enable($bool)
			{
				if ($bool == 1)
					{
						$this->logging_enable = 1;
					}
				else
					{ 
						$this->logging_enable = 0;
						@fclose($this->logging_fp);
						unset($this->logging_fp);
					}
			}
		
		function welcoming_enable($bool)
			{
				if ($bool == 1)
					{
						$this->CONFIG['welcome_new'] = 1;
					}
				else
					{ 
						$this->CONFIG['welcome_new'] = 0;
					}
			}
			
		function welcomemsg($bool)
			{
						$this->CONFIG['welcome_msg'] = $bool;
			}

		function event($time)
			{
						$context = array(
        					array(60 * 60 * 24 * 365 , "years"),
        					array(60 * 60 * 24 * 7, "weeks"),
        					array(60 * 60 * 24 , "days"),
   		    				array(60 * 60 , "hours"),
        					array(60 , "minutes"),
							array(1 , "seconds"),
   			 			);
    
    					$now = time();
   						$difference = $now - $time;
	
    
    					for ($i = 0, $n = count($context); $i < $n; $i++) {
        
        						$seconds = $context[$i][0];
        						$name = $context[$i][1];
        
        						if (($count = floor($difference / $seconds)) > 0) {
            				   			break;
        							}
    						}
    
    				$print = ($count == 1) ? '1 ' . substr($name, 0, -1) : $count . " " . $name;
    
   		 				if ($i + 1 < $n) {
  			      				$seconds2 = $context[$i + 1][0];
    		  					$name2 = $context[$i + 1][1];
        
    		   					if (($count2 = floor(($difference - ($seconds * $count)) / $seconds2)) > 0) {
										$print .= ($count2 == 1) ? ' 1 ' . substr($name2, 0, -1) : " " . $count2 . " " . $name2;
    		    			}
    				}
	
   				return $print;
			}

		
		function send($command)
			{
				fputs($this->con['socket'], $command."\n\r");
				$this->m_print (date("[d/m @ H:i]") . "-> " . $command);
			}
			
		function getServerAddr()
			{
    			if($_SERVER['SERVER_ADDR'])
					{
        				return $_SERVER['SERVER_ADDR'];
    				}
   
    			$ifconfig = shell_exec('/sbin/ifconfig eth0');
    			preg_match('/addr:([\d\.]+)/', $ifconfig, $match);
   
    			return $match[1];
			}
			
		
		function format($type, $msg)
			{
				return "[".$type."] ".$msg;
			}
		
		function has_access($user_host, $level)
			{
				if (@$this->users[$user_host]['time'] + $this->CONFIG['user_session_length'] >= time())
					{
						if ($this->users[$user_host]['level'] >= $level)
							{
								return true;
							}
					}
				return false;
			}
		
		function u_time($text)
			{
				# $this->pm($this->format("Time", date("F j, Y, g:i a", time())));
				$this->notice($this->format("Time", $this->swatchtime()));
			}
			
		function u_owner($text)
			{
				$this->notice("The owner of this bot is " . $this->CONFIG['owner'] . ".");
			}
			
		function u_nick($text)
			{
				$args = explode(" ", $text, 2);
					if ($this->has_access($this->con['buffer']['user_host'], 10))
						{	
							$this->send("NICK ". $args[1]);
							$this->CONFIG['nick'] = $args[1];
						}
					else 
						{
							$this->notice("Access Denied");
						}
			}

		function u_prefix($text)
			{
				$args = explode(" ", $text, 2);
					if ($this->has_access($this->con['buffer']['user_host'], 10))
						{	
							$this->notice("Changing prefix from " . $this->CONFIG['prefix'] . " to " . $args[1]);
							$this->CONFIG['prefix'] = $args[1];
						}
					else 
						{
							$this->notice("Access Denied");
						}
			}
		
	
		function u_noob($text)
			{
				$this->notice($this->format("Beginner Help", "Welcome " . $this->con['buffer']['username']  .", IRC Help: http://www.irchelp.org/irchelp/irctutorial.html"));
			}
	
		function u_login($text)
			{
				$text = explode(" ", $text, 3);
				$username = strtolower($text[1]);
				$password = strtolower($text[2]);
				
				$data = $this->read($this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
				
				if(strlen($data) < 3)
					{
						$new = array(	'username' => $username,
										'password' => md5($password));
						$new = $this->serializer($new);
						$this->write($new, $this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
						$this->users[$this->con['buffer']['user_host']]['time'] = time();
						$this->users[$this->con['buffer']['user_host']]['level'] = 10;
						$this->notice("Logged In. (". $username .". logged in as ". $this->con['buffer']['user_host'] .". Auto-logout in ". $this->CONFIG['user_session_length'] ." seconds.)");
						$this->notice("Your Password is '" . $password . "'. Remember this!");
						return;
					}
				
				$data = $this->rectify($data);
				
				foreach($data as $entry)
					{
						$entry = $this->deserializer($entry);
						if($entry['username'] === $username && $entry['password'] === md5($password))
							{
								$this->users[$this->con['buffer']['user_host']]['time'] = time();
								$this->users[$this->con['buffer']['user_host']]['level'] = 10;
								$this->notice("Logged In. (". $username .". logged in as ". $this->con['buffer']['user_host'] .". Auto-logout in ". $this->CONFIG['user_session_length'] ." seconds.)");
								return;
							}
					}
				
				$this->notice("Invalid Login, you bitch!");
			}
			
		function u_help($text)
			{
				global $moduleconf;				
				$basic = array('time' => 'Tells you the time.',
								'noob' => 'Information for n00bs.', 
								'login' => 'Lets you login to ' . $this->CONFIG['nick'] . '. Syntax: /msg ' . $this->CONFIG['nick'] . ' ' . $this->CONFIG['prefix'] . 'login username password', 
								'owner' => 'Reveal the bot owner.',
								'seen' => 'See a users last recorded action.',
								'afk' => 'Set yourself as Away From Keyboard.');
				
				$username_temp = explode("!", $this->con['buffer']['user_host']);
				$username = $username_temp[0];
				
				$this->notice("[Function] Description", $username);
				$this->notice("-", $username);
				
				if(is_array($this->moduleconf['help_basic']))
					{
						$basic = array_merge($basic, $this->moduleconf['help_basic']);
					}
								
				$basicvalue = array_values($basic);
				foreach($basicvalue as $value)
					{
						$this->notice("[" . $this->CONFIG['prefix'] . "" . array_search($value, $basic) . "] " . $value . "", $username);
					}
		
				$admin = array(	'nick' => 'Change the bot nickname.',
								'restart' => 'Restart Bot.',
								'join' => 'Tell Bot to join a Channel.', 
								'part' => 'Tell Bot to leave a Channel.', 
								'disconnect' => 'Disconnect Bot', 
								'logging' => 'Enable/Disable Logging.',
								'adduser' => 'Add a User.',
								'deluser' => 'Delete a User.',
								'ignore' => 'Ignore a User.',
								'listento' => 'Stop ignoring a User.',
								'removeafk' => 'Remove a user AFK message.',
								'welcoming' => 'Enable/Disable Welcoming.',
								'welcomemsg' => 'Alter the Welcome Message.',
								'prefix' => 'Change the Bot function prefix.');
				$admin_text = "";
								
				if ($this->has_access($this->con['buffer']['user_host'], 10))
						{
							$this->notice(" === BotMaster Functions === ", $username);
							if(is_array($this->moduleconf['help_admin']))
								{
									$admin = array_merge($admin, $this->moduleconf['help_admin']);
								}
				$adminvalue = array_values($admin);
				foreach($adminvalue as $value)
					{
						$this->notice("[" . $this->CONFIG['prefix'] . "" . array_search($value, $admin) . "] " . $value . "", $username);
					}
						}
			}
		
		function u_join($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				$chan = $tmp[1];
				if (strpos($chan, "#") === 0 && count($tmp) === 2)
					{
						$this->join($chan);
						$this->notice("I will comply... Joining Channel '" . $chan ."'...");
						$channels = $this->deserializer($this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['botassignfile']));
						$channels[] = $chan;
						$channels = $this->serializer($channels);
						$this->write($channels, $this->CONFIG['data_dir'] . "" . $this->CONFIG['botassignfile']);
						return;
					}
				$this->notice("Syntax: " . $this->CONFIG['prefix'] . "join #channel");
			}
						
		function u_part($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				$chan = $tmp[1];
				if (strpos($chan, "#") === 0 && count($tmp) === 2)
					{
						$this->send("PART " . $chan);
						$this->notice("Parting " . $chan . "...");
						$channels = $this->deserializer($this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['botassignfile']));
						$channels = $this->array_remove($channels, $chan);
						$this->write($this->serializer($channels), $this->CONFIG['data_dir'] . "" . $this->CONFIG['botassignfile']);
						return;
					}
								
				$this->notice("Syntax: " . $this->CONFIG['prefix'] . "part #channel");
			}
		
	
		function u_disconnect($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$this->notice("Disconnecting...");
				$this->reconnect = false;
				$this->send("QUIT GoodBye");
			}
			
		function u_seen($text)
				{
					$options = explode(" ", $text, 2);
					if(count($options) != 2)
						{
							$this->notice("Syntax: " . $this->CONFIG['prefix'] . "seen username");
							return;
						}
						
					$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['userfile']);
					$data = $this->deserializer($data);
					foreach($data as $entry)						{
							if(strtolower($entry['username']) == strtolower($options[1]))
								{
									$this->notice("[" . $entry['username'] . "] Seen " . $this->event($entry['time']) ." ago. " . $entry['action'] . " -> " . $entry['channel']);
									return;
								}
						}
						
					$this->notice("Sorry, not seen " . $options[1]);
					return;
				}
				
		function u_restart($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$this->notice("Restarting...");
				$this->reconnect = true;
				$this->send("QUIT Restarting");
			}
	
		function u_logout($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 1))
					{
						$this->notice("Access Denied");
						return;
					}
				$this->notice("Logged Out. (". $this->con['buffer']['user_host'] .")");
				unset ($this->users[$this->con['buffer']['user_host']]);
			}
	
		function u_logging($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				if (count($tmp) != 2 || ($tmp[1] != 1 && $tmp[1] != 0))
					{
						$this->notice("Enable Logging. (1=enable, 0=disable) Syntax: " . $this->CONFIG['prefix'] . "logging 0");
						return;
					}
				$this->logging_enable($tmp[1]);
				$enabled_disabled = ($tmp[1] == 1) ? "enabled" : "disabled";
				$this->notice("Logging: " . $enabled_disabled);
			}
			
		function u_welcoming($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				if (count($tmp) != 2 || ($tmp[1] != 1 && $tmp[1] != 0))
					{
						$this->notice("Enable Welcoming. (1=enable, 0=disable) Syntax: " . $this->CONFIG['prefix'] . "welcoming 0");
						return;
					}
				$this->welcoming_enable($tmp[1]);
				$enabled_disabled = ($tmp[1] == 1) ? "enabled" : "disabled";
				$this->notice("Welcoming " . $enabled_disabled);
			}
			
		function u_welcomemsg($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				if (count($tmp) != 2)
					{
						$this->notice("Alter Welcome Message Syntax: " . $this->CONFIG['prefix'] . "welcomemsg Sample Message here...");
						return;
					}
				$this->welcomemsg($tmp[1]);
				$this->notice("New Welcome Message: " . $tmp[1]);
			}
			
		function u_adduser($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 3);
				if (count($tmp) != 3)
					{
						$this->notice("Adding a user: " . $this->CONFIG['prefix'] . "adduser username password");
						return;
					}
				$new = array('username' => $tmp[1],
							'password' => md5($tmp[2]));
				$new = $this->serializer($new);
				$this->append("\n" . $new, $this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
				$this->notice("New User Added: " . $tmp[1]);
			}
			
		function u_deluser($text)
			{
				if (! $this->has_access($this->con['buffer']['user_host'], 10))
					{
						$this->notice("Access Denied");
						return;
					}
				$tmp = explode(" ", $text, 2);
				if (count($tmp) != 2)
					{
						$this->notice("Deleting a user: " . $this->CONFIG['prefix'] . "deluser username");
						return;
					}
				$data = $this->read($this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
				$data_ = $this->rectify($data);
				foreach($data_ as $entry)
					{
						$entry_ = $this->deserializer($entry);
						if(strtolower($entry_['username']) == strtolower($tmp[1]))
							{
								if(stristr($data, "\n" . $entry) !== FALSE)
									{
										$this->write(str_replace("\n" . $entry, "", $data), $this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
									} elseif(stristr($data, $entry . "\n") !== FALSE)
										{
											$this->write(str_replace($entry . "\n", "", $data), $this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
										} else
											{
												$this->write(NULL, $this->CONFIG['pass_dir'] . "" . $this->CONFIG['passfname']);
											}
								$this->notice("User Removed!");
								return;
							}
					}
				$this->notice("No Action Taken!");
			}
			
		function u_afk($text, $user = FALSE)
			{
				$options = explode(" ", $text, 2);
					if(count($options) != 2)
						{
							$this->notice("Syntax: " . $this->CONFIG['prefix'] . "afk REASON/BACK");
							return;
						}
				$reason = $options[1];
				$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
				$parsed_data = $this->rectify($data);

				if($user != FALSE)
					$user_search = $user;
				else
					$user_search = $this->con['buffer']['username'];				
				if(strtolower($reason) == "back")
					{
						if(stristr($data, $user_search) == FALSE)
							{
								$this->notice("Back from Where?");
								return;
							}
						$i = 0;
						foreach($parsed_data as $entry)
							{
								$entry_ = $this->deserializer($entry);
								if(strtolower($entry_['username']) == strtolower($user_search))
									{
										if(stristr($data, "\n" . $entry) !== FALSE)
											{
												$this->write(str_replace("\n" . $entry, "", $data), $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
											} elseif(stristr($data, $entry . "\n") !== FALSE)
												{
													$this->write(str_replace($entry . "\n", "", $data), $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
												} else
													{
														$this->write(NULL, $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
													}
										
										$askedU = count($entry_['asked']) - 1;
										$this->notice("You are back after " . $this->event($entry_['time']) . "! " . $askedU . " users were alerted.");
										if($askedU > 0)
											{
												$whoAsk = NULL;
												foreach($entry_['asked'] as $who)
													{														
														if($who['username'] != strtolower($this->con['buffer']['username']))
															$whoAsk = $whoAsk . "[" . ucfirst($who['username']) . " -> " . $this->event($who['time']) . " ago] ";													
													}												
													
												$this->notice("People who Asked for you! " . $whoAsk);											
											}
										$i = 1;
										return;
									}
							}
								if($i < 1)
									{
										$this->notice("Back from Where?");
										return;
									}
					} else
						{
							foreach($parsed_data as $entry)
								{
									$entry_ = $this->deserializer($entry);
									if($entry_['username'] == $this->con['buffer']['username'])
										{
											$new = array('username' => $this->con['buffer']['username'],
													'reason' => $reason,
													'time' => date("U"),
													'asked' => array(0 => array(
													'username' => strtolower($this->con['buffer']['username']), 
													'time' => date("U"))));
											$new = $this->serializer($new);
											$this->write(str_replace($entry, $new, $data), $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
											$this->notice("Away Status Updated");
											return;
										}
								}
							$new = array('username' => $this->con['buffer']['username'],
										'reason' => $reason,
										'time' => date("U"),
										'asked' => array(	0 => array(
													'username' => strtolower($this->con['buffer']['username']), 
													'time' => date("U"))));
										
							$new = $this->serializer($new);
							
							$this->append($new . "\n", $this->CONFIG['data_dir'] . "" . $this->CONFIG['afkfile']);
							$this->notice("You have been marked as away!");
							return;
						}
					
			}
			
	function u_removeafk($text)
		{
			if (! $this->has_access($this->con['buffer']['user_host'], 10))
				{
					$this->notice("Access Denied");
					return;
				}
				
			$tmp = explode(" ", $text, 2);
			$username = $tmp[1];
			
			if (count($tmp) != 2)
				{
					$this->notice("Syntax: " . $this->CONFIG['prefix'] . "removeafk username");
					return;
				}

			$this->u_afk(".afk back", $username);
			
		}
		
	function u_ignore($text)
		{
			if (! $this->has_access($this->con['buffer']['user_host'], 10))
				{
					$this->notice("Access Denied");
					return;
				}
				
			$tmp = explode(" ", $text, 3);
			$username = $tmp[1];
			
			if (count($tmp) != 2)
				{
					$this->notice("Syntax: " . $this->CONFIG['prefix'] . "ignore username");
					return;
				}

			$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
			$data = $this->deserializer($data);
			
			$data[] = strtolower($username);
			
			$data = $this->serializer($data);
			
			$this->write($data, $this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
			
			$this->notice($username . " is being ignored!");
			
		}
		
	function u_listento($text)
		{
			if (! $this->has_access($this->con['buffer']['user_host'], 10))
				{
					$this->notice("Access Denied");
					return;
				}
				
			$tmp = explode(" ", $text, 3);
			$username = strtolower($tmp[1]);
			
			if (count($tmp) != 2)
				{
					$this->notice("Syntax: " . $this->CONFIG['prefix'] . "listento username");
					return;
				}

			$data = $this->read($this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
			$data = $this->deserializer($data);
			
			$key = array_keys($data, $username);
			$key = $key[0];
			
			if(!in_array($username, $data))
				$this->notice($username . " is not being ignored!");
			else
				{
					if(isset($data[$key]))
						unset($data[$key]);

					$this->notice($username . " has been removed from the blacklist!");
				}

			$data = array_values($data);
			$data = $this->serializer($data);
			
			$this->write($data, $this->CONFIG['data_dir'] . "" . $this->CONFIG['ignorefile']);
			
		}
		
	function u_back()
		{
			$this->u_afk(".afk back");
		}
			
	function __destruct()
		{
			return "Destroying!";
		}
			
}
	
?>
