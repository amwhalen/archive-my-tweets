<?php

// namespaces require PHP 5.3, give nice error output before any files are included
if (version_compare(phpversion(), '5.3.0') < 0) {
	die('Archive My Tweets requires PHP 5.3.0 or higher. Your server is running PHP '.phpversion().'.');
}

if (file_exists(dirname(__FILE__).'/config.php')) {
	
	require_once('includes.php');
	$config = (isset($config)) ? $config : array();
	$amt = new \AMWhalen\ArchiveMyTweets\App($config);
	$amt->run();
	
} else {

	require_once('amt/installer.php');
	$installer = new \AMWhalen\ArchiveMyTweets\Installer(dirname(__FILE__));
	$installer->run();

}