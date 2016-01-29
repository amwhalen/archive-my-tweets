<?php if (!defined('ARCHIVE_MY_TWEETS')) exit('No direct access allowed.');

if (file_exists(dirname(__FILE__).'/config.php')) {

	require_once('includes.php');
	$config = (isset($config)) ? $config : array();
	if(isset($_GET['atom']) && $_GET['atom'] == "1")
	{
		$config['theme'] = 'atom';
	}
	$amt = new \AMWhalen\ArchiveMyTweets\App($config);
	$amt->run();

} else {

	require_once('amt/installer.php');
	$installer = new \AMWhalen\ArchiveMyTweets\Installer(dirname(__FILE__));
	$installer->run();

}
