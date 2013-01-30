<?php

require_once 'amt/router.php';
require_once 'amt/model.php';
require_once 'amt/view.php';
require_once 'amt/paginator.php';
require_once 'amt/controller.php';
require_once 'amt/importer.php';
require_once 'amt/archiver.php';
require_once 'amt/app.php';
require_once 'amt/tweet.php';
require_once 'amt/twitter.php';

if (file_exists(dirname(__FILE__).'/config.php')) {
	require_once 'config.php';
	// round up the config into a nice array
	$config = array(
		'twitter' => array(
			'username' => TWITTER_USERNAME,
			'name'     => TWITTER_NAME
		),
		'auth' => array(
			'consumerKey'    => TWITTER_CONSUMER_KEY,
			'consumerSecret' => TWITTER_CONSUMER_SECRET,
			'oauthToken'     => TWITTER_OAUTH_TOKEN,
			'oauthSecret'    => TWITTER_OAUTH_SECRET
		),
		'db' => array(
			'host'     => DB_HOST,
			'username' => DB_USERNAME,
			'password' => DB_PASSWORD,
			'database' => DB_NAME,
			'prefix'   => DB_TABLE_PREFIX
		),
		'baseUrl'    => BASE_URL,
		'cronSecret' => TWITTER_CRON_SECRET
	);
}

