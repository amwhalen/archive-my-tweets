<?php

// timezone, see: http://php.net/manual/en/timezones.php
date_default_timezone_set(''); // e.g. America/New_York

// twitter
define('TWITTER_USERNAME', ''); // e.g. awhalen
define('TWITTER_NAME',     ''); // e.g. Andrew M. Whalen

// The URL for your installation of archive-my-tweets
define('BASE_URL', ''); // e.g. http://amwhalen.com/twitter/ (start with http:// and have a slash at the end)

// consumer (application) credentials
define('TWITTER_CONSUMER_KEY',    ''); // register at http://dev.twitter.com/apps/
define('TWITTER_CONSUMER_SECRET', '');

// OAuth Tokens
define('TWITTER_OAUTH_TOKEN',  '');
define('TWITTER_OAUTH_SECRET', '');

// mysql database credentials
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_NAME',     '');

// to run a cron job a secret key is required so no one can destroy your API limit by visiting cron.php
// this can be anything you want
define('TWITTER_CRON_SECRET', '');

// extra database stuff. the defaults are probably fine.
define('DB_TABLE_PREFIX', 	'amt2_');
define('DB_HOST', 			'localhost'); // you can add a port number like this: example.com:3306

// end PHP