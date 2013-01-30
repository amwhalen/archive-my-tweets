<?php

namespace AMWhalen\ArchiveMyTweets;

class TweetTest extends \PHPUnit_Framework_TestCase {

	public function setUp() {
		require_once dirname(__FILE__) . '/../includes.php';
	}

	// get_date
	public function testGetDate() {
		$t = new Tweet();
		$t->created_at = '2013-01-22 13:00:37';
		$this->assertEquals('1:00pm January 22nd 2013', $t->get_date());
	}

	// get_linked_tweet(plain)
	public function testLinkedTweetPlain() {
		$t = new Tweet();
		$t->tweet = 'This is just a plain old tweet.';
		$this->assertEquals($t->tweet, $t->get_linked_tweet());
	}

	// get_linked_tweet(url)
	public function testLinkedTweetWithUrl() {
		$t = new Tweet();
		$t->tweet = 'This is a tweet with a URL http://amwhalen.com in it.';
		$this->assertEquals('This is a tweet with a URL <a href="http://amwhalen.com">http://amwhalen.com</a> in it.', $t->get_linked_tweet());
	}

	// get_linked_tweet(username)
	public function testLinkedTweetWithUsername() {
		$t = new Tweet();
		$t->tweet = 'This is a tweet with a username @awhalen in it.';
		$this->assertEquals('This is a tweet with a username <a href="http://twitter.com/awhalen">@awhalen</a> in it.', $t->get_linked_tweet());
	}

	// get_linked_tweet(hashtag)
	public function testLinkedTweetWithHashTag() {
		$t = new Tweet();
		$t->tweet = 'This is a tweet with a hashtag #awesome in it.';
		$this->assertEquals('This is a tweet with a hashtag <a href="http://search.twitter.com/search?q=%23awesome">#awesome</a> in it.', $t->get_linked_tweet());
	}

	// all three links
	public function testLinkedTweet() {
		$t = new Tweet();
		$t->tweet = '@awhalen check out http://amwhalen.com #awesome';
		$this->assertEquals('<a href="http://twitter.com/awhalen">@awhalen</a> check out <a href="http://amwhalen.com">http://amwhalen.com</a> <a href="http://search.twitter.com/search?q=%23awesome">#awesome</a>', $t->get_linked_tweet());
	}

	// load(array)
	public function testLoadWithArray() {
		$tweetData = array(
			'id' => 293780221621067776,
			'user_id' => 14061545,
			'created_at' => '2013-01-22 13:00:37',
			'tweet' => "Archive My Tweets has a new look, and can now import your official twitter archive. https://t.co/e8HDtbYa",
			'source' => '<a href="http://twitterrific.com" rel="nofollow">Twitterrific for Mac</a>',
			'truncated' => 0,
			'favorited' => 0,
			'in_reply_to_status_id' => 0,
			'in_reply_to_user_id' => 0,
			'in_reply_to_screen_name' => 0,
		);

		// test with all set
		$t = new Tweet();
		$t->load($tweetData);
		foreach (array_keys($tweetData) as $key) {
			$this->assertEquals($tweetData[$key], $t->$key);
		}

		// test with some not set
		unset($tweetData['in_reply_to_status_id']);
		unset($tweetData['truncated']);
		unset($tweetData['favorited']);
		$t = new Tweet();
		$t->load($tweetData);
		foreach (array_keys($tweetData) as $key) {
			$this->assertEquals($tweetData[$key], $t->$key);
		}
	}

	// load(object)
	public function testLoadObject() {
		$tweetData = new \stdClass;
		$tweetData->id = 293780221621067776;
		$tweetData->user_id = array('id' => 14061545);
		$tweetData->created_at = '2013-01-22 13:00:37';
		$tweetData->tweet = "Archive My Tweets has a new look, and can now import your official twitter archive. https://t.co/e8HDtbYa";
		$tweetData->source = '<a href="http://twitterrific.com" rel="nofollow">Twitterrific for Mac</a>';
		$tweetData->truncated = 0;
		$tweetData->favorited = 0;
		$tweetData->in_reply_to_status_id = 0;
		$tweetData->in_reply_to_user_id = 0;
		$tweetData->in_reply_to_screen_name = 0;

		// test with all set
		$t = new Tweet();
		$t->load($tweetData);
		foreach ($tweetData as $key=>$value) {
			$this->assertEquals($tweetData->$key, $t->$key);
		}

		// test with some not set
		unset($tweetData->in_reply_to_status_id);
		unset($tweetData->truncated);
		unset($tweetData->favorited);
		$t = new Tweet();
		$t->load($tweetData);
		foreach ($tweetData as $key=>$value) {
			$this->assertEquals($tweetData->$key, $t->$key);
		}
	}

	// load_json_object
	public function testLoadJsonObject() {
		$user = new \stdClass;
		$user->id = 14061545;

		$tweetData = new \stdClass;
		$tweetData->id = 293780221621067776;
		$tweetData->user = $user;
		$tweetData->created_at = '2013-01-22 13:00:37';
		$tweetData->text = "Archive My Tweets has a new look, and can now import your official twitter archive. https://t.co/e8HDtbYa";
		$tweetData->source = '<a href="http://twitterrific.com" rel="nofollow">Twitterrific for Mac</a>';
		$tweetData->truncated = 0;
		$tweetData->favorited = 0;
		$tweetData->in_reply_to_status_id = 0;
		$tweetData->in_reply_to_user_id = 0;
		$tweetData->in_reply_to_screen_name = 0;

		$t = new Tweet();
		$t->load_json_object($tweetData);
		foreach ($tweetData as $key=>$value) {
			if ($key == 'user' || $key == 'text') continue;
			$this->assertEquals($tweetData->$key, $t->$key);
		}
		$this->assertEquals($tweetData->user->id, $t->user_id);
		$this->assertEquals($tweetData->text, $t->tweet);
	}

	// load_array
	public function testLoadArray() {
		$tweetData = array(
			'id' => 293780221621067776,
			'user' => array('id' => 14061545),
			'created_at' => '2013-01-22 13:00:37',
			'text' => "Archive My Tweets has a new look, and can now import your official twitter archive. https://t.co/e8HDtbYa",
			'source' => '<a href="http://twitterrific.com" rel="nofollow">Twitterrific for Mac</a>',
			'truncated' => 0,
			'favorited' => 0,
			'in_reply_to_status_id' => 0,
			'in_reply_to_user_id' => 0,
			'in_reply_to_screen_name' => 0,
		);
		$t = new Tweet();
		$t->load_array($tweetData);
		foreach (array_keys($tweetData) as $key) {
			if ($key == 'user' || $key == 'text') continue;
			$this->assertEquals($tweetData[$key], $t->$key);
		}
		$this->assertEquals($tweetData['user']['id'], $t->user_id);
		$this->assertEquals($tweetData['text'], $t->tweet);
	}

}