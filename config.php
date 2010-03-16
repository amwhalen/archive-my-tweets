<?php

// The URL for your installation of archive-my-tweets
define('BASE_URL', 'h'); // e.g. http://amwhalen.com/twitter/ (start with http:// and have a slash at the end)

// twitter credentials
define('TWITTER_USERNAME', ''); // e.g. awhalen
define('TWITTER_PASSWORD', ''); // e.g. MySecretPassword123
define('TWITTER_NAME', 	   ''); // e.g. Andrew M. Whalen

// mysql database credentials
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_NAME', 	  '');

// to run a cron job a secret key is required so no one can destroy your API limit by visiting cron.php
// this can be anything you want
define('TWITTER_CRON_SECRET', '');

// extra database stuff. the defaults are probably fine.
define('DB_TABLE_PREFIX', 	'amt_');
define('DB_HOST', 			'localhost'); // add a port number like this: example.com:3306

?>