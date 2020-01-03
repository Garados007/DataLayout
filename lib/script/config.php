<?php
//This is a template config file
//To get your version, just copy this file to 'config.php' and do your settings.


//Database
define('DB_SERVER', '192.168.0.10');
define('DB_USER', 'Minecraft');
define('DB_PW', 'amazon');
define('DB_NAME', 'codetest');
define('DB_PREFIX', 'test_'); //The prefix for the tables. leave it blank for no prefix.
define('DB_USE_TRIGGER', false); //Use sql trigger to clear the tables
define('DB_LOG_QUERYS', false);  //for debug mode - logs all querys to a log file


//Runtime Setting
define('RELEASE_MODE', false);
define('MAINTENANCE', false);
define('SCORE_MAX_ITEMS', 50); //maximum number of score entries
define('SCORE_MIN_GAMES', 1); //minimum number of games need to view entry in top rankings

//Server Setting
define('URI_HOST', 'http://localhost/');
define('URI_PATH', 'werwolf/'); //if you leave this blank, then the root of the webspace is the root of this project
define('MANUAL_BUILD', false); //false-server build it own files, true-manual build of files

//Language Setting
define('LANG_BACKUP', 'de'); //this is the backup language when none is setted
