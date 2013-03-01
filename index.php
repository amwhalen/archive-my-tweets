<?php

// namespaces require PHP 5.3, give nice error output before any files are included
if (version_compare(phpversion(), '5.3.0') < 0) {
	exit('Archive My Tweets requires PHP 5.3.0 or higher. Your server is running PHP '.phpversion().'.');
}

// run
define('ARCHIVE_MY_TWEETS', 1);
require_once dirname(__FILE__) . '/run.php';

