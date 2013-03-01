<?php

if (!file_exists(dirname(__FILE__).'/config.php')) {
	die("Missing config.php file. Copy config.example.php to config.php and customize the settings.\n");
}

require_once('includes.php');

$isCLI = (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']));
$isWeb = (isset($_GET['secret']) && $_GET['secret'] == TWITTER_CRON_SECRET);

if ($isCLI || $isWeb) {

	$amt = new \AMWhalen\ArchiveMyTweets\App($config);

	// Import JSON from an official twitter archive
	// monthly .js files should be in a folder called 'json'
	$importOutput = $amt->importJSON(dirname(__FILE__) . '/json');
	if ($isWeb) {
		echo '<pre>' . $importOutput . '</pre>';
	} else {
		echo $importOutput;
	}

	// API tweets
	$archiveOutput = $amt->archive();
	if ($isWeb) {
		echo '<pre>' . $archiveOutput . '</pre>';
	} else {
		echo $archiveOutput;
	}

} else {

	echo "Not authorized.\n";
	exit(1);
	
}

?>
