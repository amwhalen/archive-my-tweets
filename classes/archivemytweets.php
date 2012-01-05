<?php

/**
 * The ArchiveMyTweets class.
 */
class ArchiveMyTweets {

	private $db_link = null;
	private $db_name = null;
	private $db_username = null;
	private $db_password = null;
	private $db_host = null;
	private $db_prefix = null;
	private $username = null;
	private $consumer_key = null;
	private $consumer_secret = null;
	private $oauth_token = null;
	private $oauth_secret = null;
	private $tweets_table = null;
	private $twitter = null;
	
	// current version
	const VERSION = '0.3';

	/**
	 * Constructor
	 *
	 * @param string $user 
	 * @param string $consumer_key 
	 * @param string $consumer_secret 
	 * @param string $oauth_token 
	 * @param string $oauth_secret 
	 * @param string $db 
	 * @param string $prefix 
	 * @param string $host 
	 * @param string $db_user 
	 * @param string $db_pass 
	 */
	public function __construct($user, $consumer_key, $consumer_secret, $oauth_token, $oauth_secret, $db, $prefix, $host, $db_user, $db_pass) {
		
		// set everything
		$this->username = $user;
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;
		$this->oauth_token = $oauth_token;
		$this->oauth_secret = $oauth_secret;
		$this->db_name = $db;
		$this->db_prefix = $prefix;
		$this->db_host = $host;
		$this->db_username = $db_user;
		$this->db_password = $db_pass;
		$this->tweets_table = $this->db_prefix . 'tweets';
				
		// create twitter instance
		$this->twitter = new Twitter($this->consumer_key, $this->consumer_secret);

		// set oauth
		$this->twitter->setOAuthToken($this->oauth_token);
		$this->twitter->setOAuthTokenSecret($this->oauth_secret);
		
	}
	
	/**
	 * Grabs all the latest tweets and puts them into the database.
	 *
	 * @return string Returns a string with informational output.
	 * @author awhalen
	 */
	public function backup() {
	
		$echo_str = '';
	
		// install if it's not installed yet
		if (!$this->is_installed()) {
			$installed_ok = $this->install();
			if ($installed_ok) {
				$echo_str .= 'Installed the table "'.$this->tweets_table.'" in database "'.$this->db_name.'" for twitter backups.' . "\n";
			} else {
				$echo_str .= "There was an error while creating the database table: ".mysql_error()."\n";
				die($echo_str);
			}
		}
	
		// variables
		$got_results = true;
		$page = 1;
		$results = array();
		$per_request = 200;
		$latest_tweet = $this->get_latest_tweet();
		$since_id = ($latest_tweet !== false) ? $latest_tweet->id : NULL;
		$exception_count = 0;
		
		// setup the output string
		$echo_str .= "Retrieving ".$per_request." tweets per page.\n";
		if ($since_id != NULL) {
			$echo_str .= "Getting tweets with an id greater than ".$since_id.".\n";
		}
		
		// keep track of how many tweets were added
		$numAdded = 0;
		
		// keep going while we're getting back more tweets
		while ($got_results) {
		
			try {
				
				//$result = $this->twitter->statusesUserTimeline(NULL, NULL, NULL, $since_id, NULL, $per_request, $page);
				$tweetResults = $this->twitter->statusesUserTimeline(NULL, NULL, $since_id, NULL, $per_request, $page, FALSE, TRUE, FALSE);
			
				$num_results = count($tweetResults);

				if ($num_results == 0) {
					// because retweets are stripped out of results,
					// we can only be sure we're on the last page if there were zero results
					$got_results = false;
					$echo_str .= "No tweets on page ".$page.".\n";
				} else {

					// if it's less than the per request amount, some retweets may have been filtered out.
					if ($num_results < $per_request) {
						$echo_str .= "Got ".$num_results." results on page ".$page.". (Some tweets may have been filtered out.)\n";
					} else {
						$echo_str .= "Got ".$num_results." results on page ".$page.".\n";
					}

					$page++;

					//$results = array_merge($results, $result);
					
					// add these tweets to the database
					$tweets = array();
					foreach ($tweetResults as $t) {
						
						$tweet = new Tweet();
						$tweet->load_array($t);
						$tweets[] = $tweet;
									
					}
					$result = $this->add_tweets($tweets);
		
					if ($result === false) {
						$echo_str .= 'ERROR INSERTING INTO DATABASE: ' . mysql_error() . "\n";
					} else if ( $result == 0 ) {
						$echo_str .= 'Zero tweets added.' . "\n";
					} else {
						$numAdded += $result;
					}
					
				}
			
			} catch (Exception $e) {
				$echo_str .= 'Exception: ' . $e->getMessage() . "\n";
				$exception_count++;
			}
			
			if ($exception_count > 25) { return $echo_str . 'Twitter is being flaky. Too many exceptions! Try again later.' . "\n"; }
		
		}
		
		$rate = $this->twitter->accountRateLimitStatus();
		$timezone = (function_exists('date_default_timezone_get')) ? ' '.date_default_timezone_get() : '';
		$plural_q = ($page != 1) ? 'queries' : 'query';
		$plural_t = ($numAdded != 1) ? 'tweets' : 'tweet';
		
		// add API info to the output
		$echo_str .= $numAdded." new ".$plural_t." over ".$page." ".$plural_q.".\n";
		$echo_str .= "API: (".$rate['remaining_hits']."/".$rate['hourly_limit']." remaining) API count resets at ".date("g:ia", strtotime($rate['reset_time'])).$timezone.".\n";
		
		// all tweets used to be added here
		
		return $echo_str;
	
	}
	
	/**
	 * Returns a mysql resource with all twitter clients used and how many times they were used.
	 *
	 * @return mixed Returns a mysql resource containing twitter clients (source) and counts for each (c), or returns false on failure.
	 * @author awhalen
	 */
	public function get_twitter_clients() {
	
		$sql = 'select source, count(source) as c from '.$this->tweets_table.' group by source order by count(source) desc';
	
		$result = $this->query($sql);
	
		$sources = array();
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Returns a mysql resource containing the months that have tweets and the totals.
	 *
	 * @param string $sort This decides if the months are sorted descending (desc) or ascending (asc) by date. Default is 'desc'.
	 * @return mixed Returns a mysql resource containing the months (m, y) that have tweets and the totals (total), or returns false on failure.
	 * @author awhalen
	 */
	public function get_twitter_months($sort='desc') {
	
		$sql = 'select year(created_at) as y, month(created_at) as m, count(*) AS total FROM '.$this->tweets_table.' GROUP BY year(created_at),month(created_at) order by created_at '.$sort;
		
		$result = $this->query($sql);
	
		$sources = array();
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets the most recent tweet
	 *
	 * @return mixed Returns the most recent tweet or false on failure.
	 * @author awhalen
	 */
	public function get_latest_tweet() {
	
		$sql = 'select * from '.$this->tweets_table.' order by id desc limit 1';
		
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			$row = mysql_fetch_object($result);
			$t = new Tweet();
			$t->load($row);
			return $t;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets a tweet
	 *
	 * @param int $id The tweet ID.
	 * @return mixed The mysql resource containing the tweet. On failure this returns false.
	 * @author awhalen
	 */
	public function get_tweet($id) {
	
		$sql = "select * from ".$this->tweets_table." where id='".mysql_real_escape_string($id)."' limit 1";
		
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets tweets from the database
	 *
	 * @param int $offset The offset at which to start retrieving tweets.
	 * @param int $limit The maximum number of tweets to return.
	 * @return mixed Returns a mysql resource with tweets on success, or returns false on failure.
	 * @author awhalen
	 */
	public function get_tweets($offset=0, $limit=20) {
	
		$sql = 'select * from '.$this->tweets_table.' order by id desc limit '.$offset.','.$limit;
				
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets search results for the given keyword string.
	 *
	 * @param string $k The keywords to search for. Keywords in quotes will be treated as phrases. e.g. "I love" will only match tweets that contain "I love" (without quotes).
	 * @return mixed Returns a mysql resource on success or false on failure.
	 * @author awhalen
	 */
	public function get_search_results($k) {
	
		if (trim($k) == '') return false;
	
		$sql  = 'select * from '.$this->tweets_table.' where 1 ';
		
		// split out the quoted items
		// $phrases[0] is an array of full pattern matches (quotes intact)
		// $phrases[1] is an array of strings matched by the first parenthesized subpattern, and so on. (quotes stripped)
		// the .+? means match 1 or more characters, but don't be "greedy", i.e., match the smallest amount
		preg_match_all("/\"(.+?)\"/", $k, $phrases);
		$words = explode(' ', preg_replace('/".+?"/', '', $k));
		$word_list = array_merge($phrases[1], $words);
			
		// create the sql statement
		$sql .= 'AND (';
		foreach ($word_list as $word) {
			if (strlen($word)) {
				$word = str_replace(",", "", strtolower($word));
				$sql .= "(tweet like '%".mysql_real_escape_string(strtolower($word))."%') or ";
			}
		}
		$sql = trim($sql, " or "); // remove that dangling "or"
		$sql .= ' )';
		
		$sql .= ' order by id desc';
				
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets tweets from a particular month.
	 *
	 * @param int $year The year to get tweets from. YYYY format.
	 * @param int $month The month to get tweets from. MM format.
	 * @return mixed Returns a mysql resource on success or false on failure.
	 * @author awhalen
	 */
	public function get_tweets_by_month($year, $month) {
		
		$sql = 'select * from '.$this->tweets_table.' where year(created_at)='.mysql_real_escape_string($year).' and month(created_at)='.mysql_real_escape_string($month).' order by id desc';
	
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
		
	}
	
	/**
	 * Returns the maximum number of tweets made in a single month.
	 *
	 * @return mixed Returns the maximum number of tweets in a single month on success, or returns false on failure.
	 * @author awhalen
	 */
	public function get_most_tweets_in_a_month() {
		
		$sql = 'select year(created_at) as y, month(created_at) as m, count(*) AS total FROM '.$this->tweets_table.' GROUP BY year(created_at),month(created_at) order by total desc limit 1';
		
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			$row = mysql_fetch_object($result);
			return $row->total;
		} else {
			return false;
		}
		
	}
	
	/**
	 * Returns the total number of tweets in the database.
	 *
	 * @return mixed Returns the total number of tweets on success or false on failure.
	 * @author awhalen
	 */
	public function get_total_tweets() {
	
		$sql = 'select count(*) as c from '.$this->tweets_table;
		
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			$row = mysql_fetch_object($result);
			return $row->c;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Returns an array containing monthly data.
	 * Months are keys, the values contain tweets in that month.
	 *
	 * @return mixed Returns an associative array containing monthly totals, or returns false on failure.
	 * @author awhalen
	 */
	public function get_data_for_chart() {
	
		$months = $this->get_twitter_months('asc');
		if ($months !== false) {
			$data = array();
			while ($row = mysql_fetch_object($months)) {
				$data[$row->y.'-'.$row->m.'-01'] = $row->total;
			}
			return $data;
		} else {
			return false;
		}
		
	}
	
	/**
	 * Returns the HTML to display the pagination links.
	 *
	 * @param int $total_tweets The total tweets in the DB.
	 * @param int $current_page The current page displayed.
	 * @param int $per_page The total tweets per page.
	 * @return string The pagination links HTML.
	 * @author awhalen
	 */
	public function get_pagination($total_tweets, $current_page=1, $per_page=100) {
		
		$num_pages = ceil($total_tweets / $per_page);
		
		$html = '<ul>';
		
		if ($current_page > 1) {
			$html .= '<li><a href="' . BASE_URL . 'page/' . ($current_page - 1) . '">&larr; Newer Tweets</a></li>';
		}
		
		if ($current_page < $num_pages) {
			$html .= '<li><a href="' . BASE_URL . 'page/' . ($current_page + 1) . '">Older Tweets &rarr;</a></li>';
		}
		
		$html .= '</ul>';
		
		$html .= '<div class="pages">Page ' . $current_page . ' of ' . $num_pages . '</div>';
		
		return $html;
		
	}
	
	/**
	 * Adds an array of Tweet objects to the database.
	 *
	 * @param array $tweets An array of Tweet objects.
	 * @return int|boolean Returns the number of tweets added to the database, or returns FALSE if there was a MySQL error.
	 * @author awhalen
	 */
	private function add_tweets($tweets) {
	
		if (count($tweets)) {
		
			// "insert ignore" will ignore rows with an id that already exists in the table
			$sql = 'insert ignore into '.$this->tweets_table." (id,user_id,created_at,tweet,source,truncated,favorited,in_reply_to_status_id,in_reply_to_user_id,in_reply_to_screen_name) values";
		
			$values = array();
			
			foreach ($tweets as $t) {
				$values[] = "('".mysql_real_escape_string($t->id)."','".mysql_real_escape_string($t->user_id)."','".mysql_real_escape_string($t->created_at)."','".mysql_real_escape_string($t->tweet)."','".mysql_real_escape_string($t->source)."','".mysql_real_escape_string($t->truncated)."','".mysql_real_escape_string($t->favorited)."','".mysql_real_escape_string($t->in_reply_to_status_id)."','".mysql_real_escape_string($t->in_reply_to_user_id)."','".mysql_real_escape_string($t->in_reply_to_screen_name)."')";
			}
			
			// join all the value groups together: values(1,2,3),(4,5,6),(6,7,8)
			$sql .= implode(",", $values);
						
			$result = $this->query($sql);
			
			if ($result === false) {
				return false;
			} else {
				return mysql_affected_rows($this->get_db_link());
			}
		
		}
				
		return 0;
	
	}
		
	/**
	 * Creates the database table necessary to hold the tweets.
	 *
	 * @return boolean Returns true on success, false on failure.
	 * @author awhalen
	 */
	private function install() {
	
		$sql = 'create table '.$this->tweets_table.' ( id bigint(20) unsigned not null unique, user_id bigint(20) unsigned not null, created_at datetime not null, tweet varchar(140), source varchar(255), truncated tinyint(1), favorited tinyint(1), in_reply_to_status_id bigint(20), in_reply_to_user_id bigint(20), in_reply_to_screen_name varchar(15), index(source) ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';
		$result = $this->query($sql);
	
		return $result;
	
	}
	
	/**
	 * Returns true if the database table exists.
	 *
	 * @return boolean Returns true if the database table exists, or false if the table hasn't been created.
	 * @author awhalen
	 */
	public function is_installed() {
	
		$sql = "show tables like '" . $this->tweets_table . "'";
		$result = $this->query($sql);
		
		return (mysql_num_rows($result) != 0) ? true : false;
	
	}
	
	/**
	 * Runs a SQL query.
	 *
	 * @param string $sql The SQL to run.
	 * @return mixed Returns a mysql resource for SELECT, SHOW, DESCRIBE, EXPLAIN queries, or a boolean for INSERT, UPDATE, DELETE, DROP, CREATE queries.
	 * @author awhalen
	 */
	private function query($sql) {
	
		return mysql_query($sql, $this->get_db_link());		
	
	}
	
	/**
	 * Returns a MySQL link identifier on success or FALSE on failure. If the link doesn't exist, it's created and cached for later requests.
	 *
	 * @return mixed Returns a MySQL link identifier on success or FALSE on failure.
	 * @author awhalen
	 */
	public function get_db_link() {
	
		// doesn't exist yet?
		if ($this->db_link == null) {
			$this->db_link = @mysql_connect($this->db_host, $this->db_username, $this->db_password);
			if (!$this->db_link) {
				die('Could not connect to database: ' . mysql_error() . "\n");
			}
			@mysql_select_db($this->db_name) or die('Unable to select database "'.$this->db_name.'"' . "\n");
			@mysql_set_charset('utf8', $this->db_link);
		}
		
		return $this->db_link;
	
	}

};

?>