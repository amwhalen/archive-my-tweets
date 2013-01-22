<?php

require_once('includes.php');

// create twitter archive object
$tb = new ArchiveMyTweets(TWITTER_USERNAME, TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET, TWITTER_OAUTH_TOKEN, TWITTER_OAUTH_SECRET, DB_NAME, DB_TABLE_PREFIX, DB_HOST, DB_USERNAME, DB_PASSWORD);
if ($tb->get_db_link() === FALSE) { die('Could not establish a connection to the database: ' . mysql_error() . "\n"); }

if ($tb->is_installed()) {

	// get number of tweets
	$total_tweets = $tb->get_total_tweets();
	$max_tweets = $tb->get_most_tweets_in_a_month();
	$total_favorite_tweets = $tb->get_total_favorite_tweets();
	$total_clients = $tb->get_total_clients();
	$max_clients = $tb->get_most_popular_client_total();
	$per_page = defined('TWITTER_PER_PAGE') ? TWITTER_PER_PAGE : 50;

	// defaults
	$search = false;
	$single_tweet = false;
	$monthly_archive = false;
	$all_tweets = false;
	$favorite_tweets = false;
	$per_client_archive = false;

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
			$header = 'Search <small>'.$_GET['q'].'</small>';
		} else {
			$header = 'Search <small>'.$_GET['q'] . '</small>';
		}
	
	} else if (isset($_GET['year']) && isset($_GET['month'])) {
	
		// show tweets from a specific month
		$monthly_archive = true;
		$archive_year = $_GET['year'];
		$archive_month = $_GET['month'];
		$tweets = $tb->get_tweets_by_month($archive_year, $archive_month);
		$header = date('F Y', strtotime($archive_year.'-'.$archive_month.'-01'));
	
	} else if (isset($_GET['client'])) {
	
		// show tweets from a specific month
		$per_client_archive = true;
		$client = $_GET['client'];
		$tweets = $tb->get_tweets_by_client($client);
		$header .= 'Tweets from '.$client;

	} else if (isset($_GET['favorites'])) {
	
		// show only favorite tweets
		$favorite_tweets = true;
		$client = $_GET['favorites'];
		$tweets = $tb->get_favorite_tweets();
		$header .= 'Favorite Tweets';

	} else {
	
		$current_page = (isset($_GET['page'])) ? $_GET['page']: 1;
		$offset = ($current_page > 1) ? (($current_page-1) * $per_page) : 0;
	
		// default view: show all the tweets
		$all_tweets = true;
		$tweets = $tb->get_tweets($offset, $per_page);
		$pagination = $tb->get_pagination($total_tweets, $current_page, $per_page);
		$header = ($offset != 0) ? 'Recent Tweets <small>Page '.$current_page.'</small>' : 'Recent Tweets';
	
	}

} else {
	
	die('Archive My Tweets is not yet installed. Please see the <a href="https://github.com/amwhalen/archive-my-tweets">documentation</a>.');
	
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>@<?php echo TWITTER_USERNAME; ?> - <?php echo TWITTER_NAME; ?> - Tweets</title>
	<link href="<?php echo BASE_URL; ?>css/archive.css" rel="stylesheet">
	<link href="<?php echo BASE_URL; ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<!--[if lt IE 9]>
		<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
</head>
<body>

	<div class="navbar navbar-inverse navbar-fixed-top">
		<div class="navbar-inner">
			<div class="container">
				<a class="brand avatar" href="<?php echo BASE_URL; ?>"><img src="<?php echo BASE_URL; ?>img/avatar.png"></a>
				<ul id="primary-nav" class="nav">
					<li><a class="brand" href="<?php echo BASE_URL; ?>">@<?php echo TWITTER_USERNAME; ?></a></li>
				</ul>
				<ul id="search-nav" class="nav pull-right">
					<form action="<?php echo BASE_URL; ?>" class="navbar-form pull-right" method="get">
						<input type="text" size="20" name="q" value="<?php echo ($search) ? htmlentities($_GET['q']) : ''; ?>" class="span3" placeholder="Search my tweets" />
					</form>
				</ul>
			</div>
		</div>
    </div>

	<div class="container">
		<div class="row">
	
			<div class="span8">
				<div id="tweets">
					
					<?php echo ($header) ? '<div class="page-header"><h1>'.$header.'</h1></div>': ''; ?>

					<?php
					
					if ($tweets !== false) {
						while($row = mysql_fetch_object($tweets)) {
							$t = new Tweet();
							$t->load($row);
							
							$classes = array('tweet');
							if ($t->in_reply_to_status_id != 0) $classes[] = 'reply';
							if ($t->truncated != 0) $classes[] = 'truncated';
							$class = implode(' ', $classes);
						
					?>
					
						<div class="<?php echo $class; ?>">
							<p class="message">
								<?php if ($t->favorited) { echo '<span class="badge badge-warning"><i class="icon-star icon-white" title="Favorite"></i></span>'; } ?>
								<?php echo $t->get_linked_tweet(); ?>
							</p>
							<p class="meta">
								<a href="<?php echo ($single_tweet ? 'http://twitter.com/'.TWITTER_USERNAME.'/status/' : BASE_URL).$t->id; ?>/" rel="bookmark"><?php echo $t->get_date(); ?></a>
								via
								<?php echo $t->source; echo ($t->in_reply_to_status_id != 0) ? ' <a href="http://twitter.com/'.$t->in_reply_to_screen_name.'/status/'.$t->in_reply_to_status_id.'">in reply to '.$t->in_reply_to_screen_name.'</a>' : ''; ?>
							</p>
						</div>
					
						<?php } // end while ?>
						
						<?php if (isset($pagination)) { ?>
						<div id="pagination"><?php echo $pagination; ?></div>
						<?php } ?>
				
					<?php } else { ?>
					
						<p class="no-tweets lead">No tweets found!</p>
					
					<?php } ?>
				</div><!-- /tweets -->
			</div><!-- /span8 -->
	
			<div class="span4">
				<div id="sidebar">
						
					<div id="archive" class="widget">
						<ul class="links">
							<li class="all-tweets <?php echo ($all_tweets) ? 'here' : ''; ?>"><a href="<?php echo BASE_URL; ?>"><span class="month">All Tweets</span><span class="total"><?php echo $total_tweets; ?></span><span class="bar"></span></a></li>
							<li class="<?php echo ($favorite_tweets) ? 'here' : ''; ?>"><a href="<?php echo BASE_URL; ?>favorites"><span class="month">Favorites</span><span class="total"><?php echo $total_favorite_tweets; ?></span><span class="bar"></span></a></li>
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
									$bg_percent = round($row->total / $max_tweets * 100);
									echo '<li class="'.$class.'"><a href="'.$url.'"><span class="month">'.$date.'</span><span class="total">'.$row->total.'</span><span class="bar" style="width: '.$bg_percent.'%;"></span></a></li>';
								}
							} else {
								echo '<li>No monthly data.</li>';
							}
							
							?>
						</ul>
					</div><!-- /archive -->
				
					<div id="sources" class="widget">
						<h3>Clients <small><?php echo $total_clients; ?></small></h3>

			            <ul class="links">
						<?php			
			                // sources
			                $sources = $tb->get_twitter_clients();
			                if ($sources !== false) {
								$class = '';
								while ($row = mysql_fetch_object($sources)) {
									$client_name = strip_tags($row->source);
									$class = ($per_client_archive && $client==$client_name) ? 'here': '';
									$url = BASE_URL.'client/'.$client_name.'/';
									$bg_percent = round($row->total / $max_clients * 100);
									echo '<li class="'.$class.'"><a href="'.$url.'"><span class="month">'.$client_name.'</span><span class="total">'.$row->c.'</span><span class="bar" style="width: '.$bg_percent.'%;"></span></a></li>';
								}
				            } else {
				                    echo '<li>No clients.</li>';
				            }
				            
				        ?>
			            </ul>
					</div><!-- /sources -->

				</div><!-- /sidebar -->
			</div><!-- /.span4 -->

		</div><!-- /.row -->
	</div><!-- /.container -->

	<div class="footer" id="footer">
		<div class="container">
			<p><a href="http://amwhalen.com/projects/archive-my-tweets/">Archive My Tweets</a> by <a href="http://amwhalen.com">Andrew M. Whalen</a>.</p>
		</div><!-- /.container -->
	</div><!-- /.footer -->

	<script src="<?php echo BASE_URL; ?>assets/jquery/jquery-1.9.0.min.js"></script>
	<script src="<?php echo BASE_URL; ?>assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
