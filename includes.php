<?php

$missing_config = 'Missing config.php file. Copy config.example.php to config.php and customize the settings.';

if ( ! file_exists( dirname(__FILE__) . '/config.php' ) ) {
	die($missing_config);
}

require_once 'config.php';
require_once 'classes/archivemytweets.php';
require_once 'classes/tweet.php';
require_once 'classes/twitter.php';

?>