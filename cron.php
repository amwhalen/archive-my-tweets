<?php

require_once('includes.php');

$isCLI = (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']));
$isWeb = (isset($_GET['secret']) && $_GET['secret'] == TWITTER_CRON_SECRET);

if ($isCLI || $isWeb) {

	// create and backup
	$tb = new ArchiveMyTweets(TWITTER_USERNAME, TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET, TWITTER_OAUTH_TOKEN, TWITTER_OAUTH_SECRET, DB_NAME, DB_TABLE_PREFIX, DB_HOST, DB_USERNAME, DB_PASSWORD);
	$output = $tb->backup();

	if ($isWeb) {
		echo '<pre>' . $output . '</pre>';
	} else {
		echo $output;
	}

} else {

	echo "Not authorized.\n";
	exit(1);
	
}

?>
