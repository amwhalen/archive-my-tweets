<?php

/**
 * The Tweet class
 */
class Tweet {

	public $id = NULL;
	public $user_id = NULL;
	public $created_at = NULL; // YYYY-MM-DD HH:MM:SS
	public $tweet = '';
	public $source = '';
	public $truncated = NULL;
	public $favorited = NULL;
	public $in_reply_to_status_id = NULL;
	public $in_reply_to_user_id = NULL;
	public $in_reply_to_screen_name = NULL;
	
	/**
	 * Constructor
	 */
	public function __construct() {
	
	}
	
	/**
	 * Returns the nicely-formatted date of the tweet.
	 */
	public function get_date($format='g:ia F jS Y') {
		
		return date($format, strtotime($this->created_at));
		
	}
	
	/**
	 * Returns the tweet text all linked up.
	 */
	public function get_linked_tweet() {
		
		// props to: http://davidwalsh.name/linkify-twitter-feed

		// linkify URLs
		$status_text = preg_replace(
			'/(https?:\/\/\S+)/',
			'<a href="\1">\1</a>',
			$this->tweet
		);

		// linkify twitter users
		$status_text = preg_replace(
			'/(^|\s)@(\w+)/',
			'\1@<a href="http://twitter.com/\2">\2</a>',
			$status_text
		);

		// linkify tags
		$status_text = preg_replace(
			'/(^|\s)#(\w+)/',
			'\1#<a href="http://search.twitter.com/search?q=%23\2">\2</a>',
			$status_text
		);

		return $status_text;
		
	}
	
	/**
	 * Loads this object from an array.
	 */
	public function load_array($t) {
		
		$this->id = $t['id'];
		$this->user_id = $t['user']['id'];
		$this->created_at = date('Y-m-d H:i:s', strtotime($t['created_at']));
		$this->tweet = $t['text'];
		$this->source = $t['source'];
		$this->truncated = ($t['truncated']) ? '1' : '0';
		$this->favorited = ($t['favorited']) ? '1' : '0';
		$this->in_reply_to_status_id = $t['in_reply_to_status_id'];
		$this->in_reply_to_user_id = $t['in_reply_to_user_id'];
		$this->in_reply_to_screen_name = $t['in_reply_to_screen_name'];
	
	}
	
	/**
	 * Loads this object from an object (database row).
	 */
	public function load($row) {
	
		foreach ($this as $k=>$v) {
			if (isset($row->$k)) {
				$this->$k = $row->$k;
			} else {
				$this->$k = NULL;
			}
		}
	
	}

};

?>