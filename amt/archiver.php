<?php

namespace AMWhalen\ArchiveMyTweets;
use Twitter;

require_once 'twitter.php';
require_once 'tweet.php';

/**
 * Interacts with the Twitter API to archive tweets for an account.
 */
class Archiver {

	protected $twitter;
	protected $model;

	/**
	 * Constructor
	 */
	public function __construct(Twitter $twitter, Model $model) {

		$this->twitter = $twitter;
		$this->model  = $model;

	}

	/**
	 * Grabs all the latest tweets and puts them into the database.
	 *
	 * @return string Returns a string with informational output.
	 */
	public function archive() {
	
		$str = '';
	
		// twitter variables
		$got_results = true;
		$page = 1;
		$results = array();
		$per_request = 200;
		$latest_tweet = $this->model->getLatestTweet();
		$since_id = ($latest_tweet !== false) ? $latest_tweet['id'] : NULL;
		$exception_count = 0;
		
		// setup the output string
		$str .= "Retrieving ".$per_request." tweets per page.\n";
		if ($since_id != NULL) {
			$str .= "Getting tweets with an id greater than ".$since_id.".\n";
		}
		
		// keep track of how many tweets were added
		$numAdded = 0;
		
		// keep going while we're getting back more tweets
		while ($got_results) {
		
			try {
				
				$tweetResults = $this->twitter->statusesUserTimeline(NULL, NULL, $since_id, NULL, $per_request, $page, FALSE, TRUE, FALSE);
			
				$num_results = count($tweetResults);

				if ($num_results == 0) {
					// because retweets are stripped out of results,
					// we can only be sure we're on the last page if there were zero results
					$got_results = false;
					$str .= "No tweets on page ".$page.".\n";
				} else {

					// if it's less than the per request amount, some retweets may have been filtered out.
					if ($num_results < $per_request) {
						$str .= "Got ".$num_results." results on page ".$page.". (Some tweets may have been filtered out.)\n";
					} else {
						$str .= "Got ".$num_results." results on page ".$page.".\n";
					}

					$page++;
					
					// add these tweets to the database
					$tweets = array();
					foreach ($tweetResults as $t) {
						
						$tweet = new Tweet();
						$tweet->load_array($t);
						$tweets[] = $tweet;
									
					}
					$result = $this->model->addTweets($tweets);
		
					if ($result === false) {
						$str .= 'ERROR INSERTING TWEETS INTO DATABASE: ' . $this->model->getLastErrorMessage() . "\n";
					} else if ( $result == 0 ) {
						$str .= 'Zero tweets added.' . "\n";
					} else {
						$numAdded += $result;
					}
					
				}
			
			} catch (\Exception $e) {
				$str .= 'Exception: ' . $e->getMessage() . "\n";
				$exception_count++;
			}
			
			if ($exception_count > 25) { return $str . 'Twitter is being flaky. Too many exceptions! Try again later.' . "\n"; }
		
		}
		
		$rate = $this->twitter->accountRateLimitStatus();
		$timezone = (function_exists('date_default_timezone_get')) ? ' '.date_default_timezone_get() : '';
		$plural_q = ($page != 1) ? 'queries' : 'query';
		$plural_t = ($numAdded != 1) ? 'tweets' : 'tweet';
		
		// add API info to the output
		$str .= $numAdded." new ".$plural_t." over ".$page." ".$plural_q.".\n";
		$str .= "API: (".$rate['remaining_hits']."/".$rate['hourly_limit']." remaining) API count resets at ".date("g:ia", strtotime($rate['reset_time'])).$timezone.".\n";

		return $str;
	
	}

}