<!-- index -->

	<?php

	if ($tweets !== false) {
		foreach ($tweets as $row):
			$t = new \AMWhalen\ArchiveMyTweets\Tweet();
			$t->load($row);

			$classes = array('tweet');
			if ($t->in_reply_to_status_id != 0) $classes[] = 'reply';
			if ($t->truncated != 0) $classes[] = 'truncated';
			$class = implode(' ', $classes);

	?>

	<entry>
		<id>tag:archivemytweets.org,2012:<?php echo $config['system']['baseUrl'] ?>::Tweet/<?php echo $t->id; ?></id>
		<link type="text/html" rel="alternate" href="<?php echo 'https://twitter.com/'.$config['twitter']['username'].'/status/'.$t->id; ?>"/>
		<title><?php echo $t->tweet; ?></title>
		<updated><?php echo $t->get_date("c"); ?></updated>
		<author>
			<name><?php echo $config['twitter']['username'] ?> - <?php echo $config['twitter']['name']; ?></name>
		</author>
		<content type="html">
			<?php echo htmlspecialchars("<p>".$t->get_linked_tweet()."</p><hr/>");
				echo htmlspecialchars($t->source);
				echo ($t->in_reply_to_status_id != 0) ? htmlspecialchars(' <a href="http://twitter.com/'.$t->in_reply_to_screen_name.'/status/'.$t->in_reply_to_status_id.'">in reply to '.$t->in_reply_to_screen_name.'</a>') : '';
			?>

		</content>
	</entry>
	<?php endforeach; ?>
	<?php } ?>
<!-- /index -->
