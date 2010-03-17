<?php

/**
 * The ArchiveMyTweets class.
 */
class ArchiveMyTweets {

	var $db_link = null;
	var $db_name = null;
	var $db_username = null;
	var $db_password = null;
	var $db_host = null;
	var $db_prefix = null;
	var $username = null;
	var $password = null;
	var $tweets_table = null;
	var $twitter = null;

	/**
	 * Constructor
	 *
	 * @param string $user The Twitter username.
	 * @param string $pass The Twitter password.
	 * @param string $db The database name.
	 * @param string $prefix The database table prefix.
	 * @param string $host The database host.
	 * @param string $db_user The database userame.
	 * @param string $db_pass The database password.
	 * @author awhalen
	 */
	public function __construct($user, $pass, $db, $prefix, $host, $db_user, $db_pass) {
		
		// set everything
		$this->username = $user;
		$this->password = $pass;
		$this->db_name = $db;
		$this->db_prefix = $prefix;
		$this->db_host = $host;
		$this->db_username = $db_user;
		$this->db_password = $db_pass;
		$this->tweets_table = $this->db_prefix . 'tweets';
		
		// sign in to Twitter
		$this->twitter = new Twitter($this->username, $this->password);
		
	}
	
	/**
	 * Grabs all the latest tweets and puts them into the database.
	 *
	 * @return void
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
		
		// keep going while we're getting back more tweets
		while($got_results) {
		
			try {
				
				$result = $this->twitter->getUserTimeline(NULL, $since_id, NULL, $per_request, $page);
			
				$num_results = count($result);

				if ($num_results == 0) {
					// because retweets are stripped out of results,
					// we can only be sure we're on the last page if there were zero results
					$got_results = false;
					$echo_str .= "No tweets on page ".$page.".\n";
				} else {

					// if it's less than the per request amount, some retweets may have been filtered out.
					if ($num_results < $per_request) {
						$echo_str .= "Got ".$num_results." results on page ".$page.". Retweets were probably filtered out.\n";
					} else {
						$echo_str .= "Got ".$num_results." results on page ".$page.".\n";
					}

					$page++;

					$results = array_merge($results, $result);

				}
			
			} catch (Exception $e) {
				$echo_str .= 'Exception: ' . $e->getMessage() . "\n";
				$exception_count++;
			}
			
			if ($exception_count > 5) { return $echo_str . 'Twitter is being flaky. Too many exceptions! Try again later.' . "\n"; }
		
		}
		
		$rate = $this->twitter->getRateLimitStatus();
		$timezone = (function_exists('date_default_timezone_get')) ? ' '.date_default_timezone_get() : '';
		$plural_q = ($page != 1) ? 'queries' : 'query';
		$plural_t = (count($results) != 1) ? 'tweets' : 'tweet';
		
		// add API info to the output
		$echo_str .= count($results)." new ".$plural_t." over ".$page." ".$plural_q.".\n";
		$echo_str .= "API: (".$rate['remaining_hits']."/".$rate['hourly_limit'].") API count resets at ".date("g:ia", $rate['reset_time']).$timezone.".\n";
		
		// finally, add the tweets to the database
		$tweets = array();
		foreach ($results as $t) {
			
			$tweet = new Tweet();
			$tweet->load_array($t);
			$tweets[] = $tweet;
									
		}
		$this->add_tweets($tweets);
		
		return $echo_str;
	
	}
	
	/**
	 * Returns a mysql resource with all twitter clients used and how many times they were used.
	 *
	 * @return mysql_resource A mysql resource containing twitter clients (source) and counts for each (c).
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
	 * @param string[optional] $sort This decides if the months are sorted descending (desc) or ascending (asc) by date. Default is 'desc'.
	 * @return mysql_resource Returns a mysql resource containing the months (m, y) that have tweets and the totals (total).
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
	 * Gets the most recent tweet from the table
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
	 * Gets the most recent tweet from the table
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
	 * Search the tweets
	 */
	public function get_search_results($k) {
	
		$sql  = 'select * from '.$this->tweets_table.' where 1 and (';
		
		//$words = explode('"', $k);
		$words = explode(' ', $k);
		$or_statements = array();
		foreach($words as $w) {
			if (trim($w) == '') continue;
			$or_statements[] = "(tweet like '%".mysql_real_escape_string($w)."%')";			
		}
		
		$sql .= implode(' or ', $or_statements);
		
		$sql .= ') order by id desc';
		
		$result = $this->query($sql);
	
		if (mysql_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Gets tweets in a certain month
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
	 * Gets the most tweets made in a month
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
	 * Returns the total number of tweets
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
	 * Adds an array of tweets to the database
	 */
	private function add_tweets($tweets) {
	
		if (count($tweets)) {
		
			// "insert ignore" will ignore rows with an id that already exists in the table
			$sql = 'insert ignore into '.$this->tweets_table." (id,user_id,created_at,tweet,source,truncated,favorited,in_reply_to_status_id,in_reply_to_user_id) values";
		
			$values = array();
			
			foreach ($tweets as $t) {
				$values[] = "('".mysql_real_escape_string($t->id)."','".mysql_real_escape_string($t->user_id)."','".mysql_real_escape_string($t->created_at)."','".mysql_real_escape_string($t->tweet)."','".mysql_real_escape_string($t->source)."','".mysql_real_escape_string($t->truncated)."','".mysql_real_escape_string($t->favorited)."','".mysql_real_escape_string($t->in_reply_to_status_id)."','".mysql_real_escape_string($t->in_reply_to_user_id)."')";
			}
			
			// join all the value groups together: values(1,2,3),(4,5,6),(6,7,8)
			$sql .= implode(",", $values);
			
			return $this->query($sql);
		
		}
		
		return false;
	
	}
		
	/**
	 * Installs the backup database tables.
	 */
	private function install() {
	
		$sql = 'create table '.$this->tweets_table.' ( id bigint(20) unsigned not null unique, user_id bigint(20) unsigned not null, created_at datetime not null, tweet varchar(140), source varchar(255), truncated tinyint(1), favorited tinyint(1), in_reply_to_status_id bigint(20), in_reply_to_user_id bigint(20), index(source) ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';
		$result = $this->query($sql);
	
		return $result;
	
	}
	
	/**
	 * Returns true if the database table exists.
	 */
	public function is_installed() {
	
		$sql = "show tables like '" . $this->tweets_table . "'";
		$result = $this->query($sql);
		
		return (mysql_num_rows($result) != 0) ? true : false;
	
	}
	
	/**
	 * Runs the database query. Dies if there was an error with it.
	 */
	private function query($sql) {
	
		$result = mysql_query($sql, $this->get_db_link());
	
		if (!$result) {
			$message  = 'Invalid query: ' . mysql_error() . "\n";
			$message .= 'Whole query: ' . $sql . "\n";
			die($message);
		}
		
		return $result;
	
	}
	
	/**
	 * @return the current db link
	 */
	private function get_db_link() {
	
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