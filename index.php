<?php

require_once('includes.php');

// create twitter archive object
$tb = new ArchiveMyTweets(TWITTER_USERNAME, TWITTER_PASSWORD, DB_NAME, DB_TABLE_PREFIX, DB_HOST, DB_USERNAME, DB_PASSWORD);
if ($tb->db_link === FALSE) { die('Could not establish a connection to the database: ' . mysql_error() . "\n"); }

if ($tb->is_installed()) {

	// get number of tweets
	$total_tweets = $tb->get_total_tweets();
	$max_tweets = $tb->get_most_tweets_in_a_month();

	// the big switch. this decides what to show on the page.
	if (isset($_GET['id'])) {
	
		// show a single tweet
		$single_tweet = true;
		$tweets = $tb->get_tweet($_GET['id']);
		$header = '';
	
	} else if (isset($_GET['q'])) {
	
		// show search results
		$search = true;
		$tweets = $tb->get_search_results($_GET['q']);
		if ($tweets !== false) {
			$header = 'Search results ('.mysql_num_rows($tweets).') for '.$_GET['q'];
		} else {
			$header = 'No results for '.$_GET['q'];
		}
	
	} else if (isset($_GET['year']) && isset($_GET['month'])) {
	
		// show tweets from a specific month
		$monthly_archive = true;
		$archive_year = $_GET['year'];
		$archive_month = $_GET['month'];
		$tweets = $tb->get_tweets_by_month($archive_year, $archive_month);
		$header = 'Tweets from '.date('F Y', strtotime($archive_year.'-'.$archive_month.'-01'));
	
	} else {
	
		// default view: show all the tweets
		$all_tweets = true;
		$tweets = $tb->get_tweets();
		$header = '';
	
	}

} else {
	
	die('Archive My Tweets is not yet installed. Please see the <a href="http://code.google.com/p/archive-my-tweets/">documentation</a>.');
	
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>@<?php echo TWITTER_USERNAME; ?> - <?php echo TWITTER_NAME; ?> - Tweets</title>
	<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/twitter.css" type="text/css" media="screen" title="twitter" charset="utf-8" />
</head>
<body>

<div id="container">
	
	<div id="header">
		<div class="user">
			<a href="http://twitter.com/<?php echo TWITTER_USERNAME; ?>" class="avatar"><img src="<?php echo BASE_URL; ?>img/avatar.png" alt="twitter avatar" /></a>
			<h1><?php echo TWITTER_NAME; ?> <span class="username"><a href="http://twitter.com/<?php echo TWITTER_USERNAME; ?>">@<?php echo TWITTER_USERNAME; ?></a></span></h1>
		</div>
		<div class="search">
			<form action="<?php echo BASE_URL; ?>" method="get">
				<p>
					<input type="text" size="20" name="q" value="<?php echo ($search) ? htmlentities($_GET['q']) : 'Search tweets'; ?>" class="input" onfocus="if(this.value=='Search tweets') {this.value='';}" onblur="if(this.value=='') {this.value='Search tweets';}" /><input type="submit" value="Go" class="go" />
				</p>
			</form>
		</div>
	</div>
	
	<div id="tweets">
		
		<?php
		
		echo ($header) ? '<h2>'.$header.'</h2>': '';
		
		if ($tweets !== false) {
			while($row = mysql_fetch_object($tweets)) {
				$t = new Tweet();
				$t->load($row);
				
				$classes = array();
				$classes[] = ($t->favorited != 0) ? 'favorited' : '';
				$classes[] = ($t->in_reply_to_status_id != 0) ? 'reply' : '';
				$classes[] = ($t->truncated != 0) ? 'truncated' : '';
				$class = ' '.implode(' ', $classes);
			
		?>
		
		<div class="tweet<?php echo $class; ?>">
			<div class="message">
				<p><?php echo $t->get_linked_tweet(); ?></p>
			</div>
			<p class="meta">
				<a href="<?php echo BASE_URL.$t->id; ?>/" rel="bookmark"><?php echo $t->get_date(); ?></a>
				via <?php echo $t->source; ?>
				<?php echo ($t->in_reply_to_status_id != 0) ? 'in reply to <a href="http://twitter.com/6932712/status/'.$t->in_reply_to_user_id.'">'.$t->in_reply_to_user_id.'</a>' : ''; ?>
			</p>
		</div>
		
		<?php } } else { ?>
		
		<p>No tweets!</p>
		
		<?php } ?>
		
	</div>
	
	<div id="sidebar">
				
		<div id="archive" class="widget">
			<ul class="links">
				<li class="<?php echo (isset($all_tweets)) ? 'here' : ''; ?>"><a href="<?php echo BASE_URL; ?>"><span class="item">All Tweets</span> <span class="total"><?php echo $total_tweets; ?></span></a></li>
				<?php
				
				// months
				$months = $tb->get_twitter_months();
				if ($months !== false) {
					$class = '';
					while ($row = mysql_fetch_object($months)) {
						$class = ($monthly_archive && $archive_year==$row->y && $archive_month==$row->m) ? 'here': '';
						$time = strtotime($row->y.'-'.$row->m.'-01');
						$date = date('F Y', $time);
						$url = BASE_URL.'archive/'.date('Y', $time).'/'.date('m', $time).'/';
						$bg_percent = round($row->total / $max_tweets * 240);
						echo '<li class="'.$class.'"><a href="'.$url.'" style="background-position: '.$bg_percent.'px 0px;"><span class="item">'.$date.'</span> <span class="total">'.$row->total.'</span></a></li>';
					}
				} else {
					echo '<li>No monthly data.</li>';
				}
				
				?>
			</ul>
		</div>
		
		<div id="sources" class="widget">
			<h3>Clients</h3>
			<?php
			
			// sources
			$sources = $tb->get_twitter_clients();
			if ($sources !== false) {
				echo '<ul>';
				while ($row = mysql_fetch_object($sources)) {
					echo '<li><span class="item">'.$row->source.'</span> <span class="total">'.$row->c.'</span></li>';
				}
				echo '</ul>';
			} else {
				echo '<p>No clients.</p>';
			}
			
			?>
		</div>
		
	</div>
	
</div>

<div id="footer">
	<p><a href="http://amwhalen.com/projects/archive-my-tweets/">Archive My Tweets</a> by <a href="http://amwhalen.com">Andrew M. Whalen</a>.</p>
</div>

</body>
</html>
