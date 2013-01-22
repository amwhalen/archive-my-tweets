<?php

require_once('includes.php');

$isCLI = (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']));
$isWeb = (isset($_GET['secret']) && $_GET['secret'] == TWITTER_CRON_SECRET);

if ($isCLI || $isWeb) {

	$tb = new ArchiveMyTweets(TWITTER_USERNAME, TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET, TWITTER_OAUTH_TOKEN, TWITTER_OAUTH_SECRET, DB_NAME, DB_TABLE_PREFIX, DB_HOST, DB_USERNAME, DB_PASSWORD);

	// API tweets
	$output = $tb->backup();
	if ($isWeb) {
		echo '<pre>' . $output . '</pre>';
	} else {
		echo $output;
	}

	// Import JSON from an official twitter archive
	// monthly .js files should be in a folder called 'json'
	$importOutput = $tb->importJSON(dirname(__FILE__) . '/json');
	if ($isWeb) {
		echo '<pre>' . $importOutput . '</pre>';
	} else {
		echo $importOutput;
	}

} else {

	echo "Not authorized.\n";
	exit(1);
	
}

?>
