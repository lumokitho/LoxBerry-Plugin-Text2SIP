<?php
// LoxBerry Text2SIP-Plugin 
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de
// Version 0.2
// 20.11.2016 00:30:18

// Configuration parameters
$psubdir          		=array_pop(array_filter(explode('/',pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME))));
$mydir            		=pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME);
$pluginlogfile 				=$mydir."/../../../../log/plugins/$psubdir/Text2SIP.log";
$pluginlogfile_handle = fopen($pluginlogfile, "a");
$plugindatadir    		=$mydir."/../../../../data/plugins/$psubdir/wav";
$plugincfgfile 				=$mydir."/../../../../config/plugins/$psubdir/Text2SIP.cfg";
$pluginlanguagefile 	=$mydir."/../../../../templates/plugins/$psubdir/de/language.dat";
$user 								="Text2SIP";
$pass 								="loxberry";
$pico2wave        		= "/usr/bin/pico2wave";
$sox 			            = "/usr/bin/sox";
$sipcmd           	  = $mydir."/../../../../webfrontend/cgi/plugins/$psubdir/bin/sipcmd";

// Enable logging
ini_set("error_log", $pluginlogfile);
ini_set("log_errors", 1);

$plugin_phrase_array 	= parse_ini_file("$pluginlanguagefile");
$plugin_cfg_array 		= parse_ini_file("$plugincfgfile");
$DEBUG_USE            = $plugin_cfg_array['DEBUG_USE'                            ];
$PLUGIN_USE           = $plugin_cfg_array['PLUGIN_USE'                           ];
if ( !$PLUGIN_USE == "on" ) { die( $PLUGIN_USE.$plugin_phrase_array['ERROR0003'] ); }

function debuglog($debugtext,$parameter="")
{
	global $DEBUG_USE,$pluginlogfile_handle,$plugin_phrase_array;
	if ( !$DEBUG_USE == "on" ) { return; }
  if ( isset($plugin_phrase_array[$debugtext]) )
  {
		fwrite($pluginlogfile_handle, date('Y-m-d H:i:s')." [DBG] ".$plugin_phrase_array[$debugtext]." ".$parameter."\n");
  }
  else
  {
		fwrite($pluginlogfile_handle, date('Y-m-d H:i:s')." [DBG] ".$debugtext." ".$parameter."\n");
  }
		return;
}


function authenticate()
{
		header("WWW-Authenticate: Basic realm='LoxBerry - Text2SIP-Plugin'");
    header("HTTP/1.0 401 Unauthorized");
		return "\nError, Access denied.\n";
}

// Defaults for inexistent variables
if (!isset($_REQUEST["mode"])) {$_REQUEST["mode"] = 'normal';}

if($_REQUEST["mode"] == "download_logfile")
{
	if (file_exists($pluginlogfile)) 
	{
		error_log( date('Y-m-d H:i:s ')."[LOG] Download logfile\n", 3, $pluginlogfile);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="'.basename($pluginlogfile).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($pluginlogfile));
    readfile($pluginlogfile);
	}
	else
	{
		error_log( date('Y-m-d H:i:s ')."Error reading logfile!\n", 3, $pluginlogfile);
		die("Error reading logfile."); 
	}
	exit;
}
else if($_REQUEST["mode"] == "show_logfile")
{
	if (file_exists($pluginlogfile)) 
	{
		error_log( date('Y-m-d H:i:s ')."[LOG] Show logfile\n", 3, $pluginlogfile);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: inline; filename="'.basename($pluginlogfile).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($pluginlogfile));
    readfile($pluginlogfile);
	}
	else
	{
		error_log( date('Y-m-d H:i:s ')."Error reading logfile!\n", 3, $pluginlogfile);
		die("Error reading logfile."); 
	}
	exit;
}
else if($_REQUEST["mode"] == "empty_logfile")
{
	if (file_exists($pluginlogfile)) 
	{
		if( ( isset($_SERVER['PHP_AUTH_USER'] ) && ( $_SERVER['PHP_AUTH_USER'] == "$user" ) ) AND  ( isset($_SERVER['PHP_AUTH_PW'] ) && ( $_SERVER['PHP_AUTH_PW'] == "$pass" )) )
		{
				$f = @fopen("$pluginlogfile", "r+");
				if ($f !== false) 
				{
				    ftruncate($f, 0);
				    fclose($f);
						error_log( date('Y-m-d H:i:s ')."[LOG] Logfile content deleted\n", 3, $pluginlogfile);
						$result = "\n<img src='/plugins/$psubdir/Text2SIP_ok.png'>";
				}
				else
				{
						error_log( date('Y-m-d H:i:s ')."[LOG] Logfile content not deleted due to problems doing it.\n", 3, $pluginlogfile);
						$result = "\n<img src='/plugins/$psubdir/Text2SIP_fail.png'>";
				}
		}
		else
		{
				$result = authenticate();
		}
	}
	else
	{
		$result = "\n<img src='/plugins/$psubdir/Text2SIP_fail.png'>";
	}
}
else if($_REQUEST["mode"] == "make_call")
{
		if ( $_REQUEST["vg"] == "" ) 
		{ 
			error_log( date('Y-m-d H:i:s ').$plugin_phrase_array['ERROR0001']."\n", 3, $pluginlogfile);
    	die($plugin_phrase_array['ERROR0001']);
		}
    $guide 															= intval($_REQUEST["vg"]);
    if ( $guide == 0 )
    {
			error_log( date('Y-m-d H:i:s ').$plugin_phrase_array['ERROR0002']."\n", 3, $pluginlogfile);
    	die($plugin_phrase_array['ERROR0002']);
		}

    $P2W_lang         							= $plugin_cfg_array['P2W_lang'.$guide];
    $P2W_Text                       = $plugin_cfg_array['P2W_Text'.$guide                      ];
    $SIPCMD_CALLING_USER_NUMBER     = $plugin_cfg_array['SIPCMD_CALLING_USER_NUMBER'.$guide    ];
    $SIPCMD_CALLING_USER_PASSWORD   = $plugin_cfg_array['SIPCMD_CALLING_USER_PASSWORD'.$guide  ];
    $SIPCMD_CALLING_USER_NAME       = $plugin_cfg_array['SIPCMD_CALLING_USER_NAME'.$guide      ];
    $SIPCMD_SIP_PROXY               = $plugin_cfg_array['SIPCMD_SIP_PROXY'.$guide              ];
    $SIPCMD_CALLED_USER             = $plugin_cfg_array['SIPCMD_CALLED_USER'.$guide            ];
    $SIPCMD_CALL_PAUSE_BEFORE_GUIDE = $plugin_cfg_array['SIPCMD_CALL_PAUSE_BEFORE_GUIDE'.$guide];
    $SIPCMD_CALL_PAUSE_AFTER_GUIDE  = $plugin_cfg_array['SIPCMD_CALL_PAUSE_AFTER_GUIDE'.$guide ];
    $SIPCMD_CALL_RESULT_VI          = $plugin_cfg_array['SIPCMD_CALL_RESULT_VI'.$guide         ];
    $SIPCMD_CALL_TIMEOUT			      = $plugin_cfg_array['SIPCMD_CALL_TIMEOUT'.$guide           ];

		if       ($P2W_lang == "gb" ) { $P2W_lang = "en-GB"; }
		else if  ($P2W_lang == "us" ) { $P2W_lang = "en-US"; }
		else if  ($P2W_lang == "es" ) { $P2W_lang = "es-ES"; }
		else if  ($P2W_lang == "fr" ) { $P2W_lang = "fr-FR"; }
		else if  ($P2W_lang == "it" ) { $P2W_lang = "it-IT"; }
		else if  ($P2W_lang == "de" ) { $P2W_lang = "de-DE"; }
		else 
		{ 
			debuglog('DBG_INVALID_LANGUAGE',$P2W_lang);
			$P2W_lang = "de-DE";
		}
		$tempname_prefix = tempnam("$plugindatadir/", "Text2SIP_WEB_");
		$pluginjobfile   = $tempname_prefix.".tsp";
		$plugintmpfile   = $tempname_prefix.".tmp.wav";
		$pluginwavfile   = $tempname_prefix.".wav";
		$pluginjobfile_handle = fopen($pluginjobfile, "w");
		if ( !$pluginjobfile_handle )
		{
			error_log( date('Y-m-d H:i:s ').$plugin_phrase_array['ERROR0004']."\n", 3, $pluginlogfile);
    	die($plugin_phrase_array['ERROR0004']);
		}
		debuglog('DBG_CREATE_JOB',$pluginjobfile);
    $cmd = $pico2wave . ' -l "'.$P2W_lang.'" -w "'.$plugintmpfile.'" "'.$P2W_Text.'" 2>&1 >>'.$pluginlogfile;
		fwrite($pluginjobfile_handle, "$cmd \n");
		debuglog('DBG_ADD_CMD_TO_JOB',$cmd );
    $cmd = $sox  . ' -v 0.9 "'.$plugintmpfile.'" -t wav -b 16 -r 8000 "'.$pluginwavfile.'" 2>&1 >>'.$pluginlogfile;
		fwrite($pluginjobfile_handle, "$cmd \n");
		debuglog('DBG_ADD_CMD_TO_JOB',$cmd );
 		$check_result ="";
		$debug_value  ='2>/dev/null';
		if ( $DEBUG_USE == "on" )
		{
			$debug_value = '2>&1';
		}
		if ( !$SIPCMD_CALL_RESULT_VI == "" && substr($SIPCMD_CALL_RESULT_VI,0,7) == "http://")
		{
	    $check_result = '|while read DTMF_LINE; do DTMF_CODE=`echo $DTMF_LINE |grep "receive DTMF:"|cut -c16`; echo "DTMF: $DTMF_CODE"; wget -q -t 1 -T 10 -O /dev/null "'.$SIPCMD_CALL_RESULT_VI.'$DTMF_CODE"; done ';     
		} 
		if ( $SIPCMD_CALL_TIMEOUT < 1 )
		{
	    $SIPCMD_CALL_TIMEOUT = 1;
		} 
    $cmd = $sipcmd . ' -T '.$SIPCMD_CALL_TIMEOUT.' -P sip -u '.$SIPCMD_CALLING_USER_NUMBER.' -c '.$SIPCMD_CALLING_USER_PASSWORD.' -a "'.$SIPCMD_CALLING_USER_NAME.'" -w '.$SIPCMD_SIP_PROXY.' -x "c'.$SIPCMD_CALLED_USER.';w'.$SIPCMD_CALL_PAUSE_BEFORE_GUIDE.';v'.$pluginwavfile.';w'.$SIPCMD_CALL_PAUSE_AFTER_GUIDE.';h" '.$debug_value.' |tee -a '.$pluginlogfile.$check_result;
		fwrite($pluginjobfile_handle, "$cmd \n");
		debuglog('DBG_ADD_CMD_TO_JOB',$cmd );
    
		$cmd = 'rm -f '.$tempname_prefix.'* 2>&1 >>'.$pluginlogfile; 
		fwrite($pluginjobfile_handle, "$cmd \n");
		debuglog('DBG_ADD_CMD_TO_JOB',$cmd );
		$cmd = "tsp bash $pluginjobfile  2>&1 >>$pluginlogfile \n";
		debuglog('DBG_ADD_JOB_TO_QUEUE',$cmd );
		$code = system ($cmd);
		debuglog('DBG_ADD_JOB_TO_QUEUE_ID',$code );
		fclose($pluginjobfile_handle);
		fclose($pluginlogfile_handle);
    exit;
}
else
{
		$result = "Error, invalid request \n";
}

header('Content-Type: text/plain; charset=utf-8');
echo "$result";
exit;