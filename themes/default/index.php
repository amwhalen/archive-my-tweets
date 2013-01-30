
<!-- index -->

			<div class="span8">
				<div id="tweets" class="rounded">
					
					<?php echo ($header) ? '<div class="page-header"><h1>'.$header.'</h1></div>': ''; ?>

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
					
						<div class="<?php echo $class; ?>">
							<p class="message">
								<?php if ($t->favorited) { echo '<span class="badge badge-warning"><i class="icon-star icon-white" title="Favorite"></i></span>'; } ?>
								<?php echo $t->get_linked_tweet(); ?>
							</p>
							<p class="meta">
								<a href="<?php echo ($single_tweet ? 'http://twitter.com/'.$config['twitter']['username'].'/status/' : $config['system']['baseUrl']).$t->id; ?>/" rel="bookmark"><?php echo $t->get_date(); ?></a>
								via
								<?php echo $t->source; echo ($t->in_reply_to_status_id != 0) ? ' <a href="http://twitter.com/'.$t->in_reply_to_screen_name.'/status/'.$t->in_reply_to_status_id.'">in reply to '.$t->in_reply_to_screen_name.'</a>' : ''; ?>
							</p>
						</div>
					
						<?php endforeach; ?>
						
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
						
					<div id="archive" class="widget rounded">
						<ul class="links">
							<li class="all-tweets <?php echo ($all_tweets) ? 'here' : ''; ?>"><a href="<?php echo $config['system']['baseUrl']; ?>"><span class="month">All Tweets</span><span class="total"><?php echo $totalTweets; ?></span><span class="bar"></span></a></li>
							<li class="<?php echo ($favorite_tweets) ? 'here' : ''; ?>"><a href="<?php echo $config['system']['baseUrl']; ?>favorites"><span class="month">Favorites</span><span class="total"><?php echo $totalFavoriteTweets; ?></span><span class="bar"></span></a></li>
							<?php
							
							// months
							if ($twitterMonths !== false) {
								$class = '';
								foreach ($twitterMonths as $row) {
									$class = ($monthly_archive && $archive_year==$row['y'] && $archive_month==$row['m']) ? 'here': '';
									$time = strtotime($row['y'].'-'.$row['m'].'-01');
									$date = date('F Y', $time);
									$url = $config['system']['baseUrl'].'archive/'.date('Y', $time).'/'.date('m', $time).'/';
									$bg_percent = round($row['total'] / $maxTweets * 100);
									echo '<li class="'.$class.'"><a href="'.$url.'"><span class="month">'.$date.'</span><span class="total">'.$row['total'].'</span><span class="bar" style="width: '.$bg_percent.'%;"></span></a></li>';
								}
							} else {
								echo '<li>No monthly data.</li>';
							}
							
							?>
						</ul>
					</div><!-- /archive -->
				
					<div id="sources" class="widget rounded">
						<h3>Clients <div class="pull-right muted"><?php echo $totalClients; ?></div></h3>

						<ul class="links">
						<?php			
							// sources
							if ($twitterClients !== false) {
								$class = '';
								foreach ($twitterClients as $row) {
									$client_name = strip_tags($row['source']);
									$class = ($per_client_archive && $client==$client_name) ? 'here': '';
									$url = $config['system']['baseUrl'].'client/'.$client_name.'/';
									$bg_percent = round($row['total'] / $maxClients * 100);
									echo '<li class="'.$class.'"><a href="'.$url.'"><span class="month">'.$client_name.'</span><span class="total">'.$row['c'].'</span><span class="bar" style="width: '.$bg_percent.'%;"></span></a></li>';
								}
							} else {
								echo '<li>No clients.</li>';
							}

						?>
						</ul>
					</div><!-- /sources -->

				</div><!-- /sidebar -->
			</div><!-- /.span4 -->

<!-- /index -->
