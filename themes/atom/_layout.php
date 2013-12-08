<?php echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"; ?>
<feed
	xmlns="http://www.w3.org/2005/Atom"
	xmlns:thr="http://purl.org/syndication/thread/1.0"
	xml:lang="en-US"
>
	<id>tag:archivemytweets.com,2012:<?php echo $config['system']['baseUrl']; ?></id>
	<link type="text/html" rel="alternate" href="<?php echo $config['system']['baseUrl']; ?>"/>
	<link rel="self" type="application/atom+xml" href="<?php
// From http://stackoverflow.com/a/2236887
function strleft($s1, $s2) { return substr($s1, 0, strpos($s1, $s2)); }
$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
$protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);

echo $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];

?>" />

	<title><?php echo $config['twitter']['username']; ?> - <?php echo $config['twitter']['name']; ?> - Tweets</title>
<?php
// Get the first date of the first tweet
if($tweets !== false && $tweets[0])
{
	$t = new \AMWhalen\ArchiveMyTweets\Tweet();
	$t->load($tweets[0]);
	$last_updated = $t->get_date("c");
}
else
{
	// Do not know when the feed was last updated, so answer "now"
	$last_updated = date("c");
}
?>
	<updated><?php echo $last_updated ?></updated>
	<?php echo $content; ?>
</feed>
