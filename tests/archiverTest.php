<?php

namespace AMWhalen\ArchiveMyTweets;

class ArchiverTest extends \PHPUnit_Framework_TestCase {

	protected $username;
	protected $model;
	protected $twitter;
	protected $latestTweet;
	protected $arrayOfTweets;

	public function setUp() {
		
		require_once dirname(__FILE__) . '/../includes.php';

		$this->latestTweet = array(
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

		$this->arrayOfTweets = array(
			array(
				'id' => 293780221621067777,
				'user' => array(
					'id' => 14061545
				),
				'created_at' => '2013-01-22 13:00:38',
				'text' => "Newer tweet.",
				'source' => '<a href="http://twitterrific.com" rel="nofollow">Twitterrific for Mac</a>',
				'truncated' => 0,
				'favorited' => 0,
				'in_reply_to_status_id' => 0,
				'in_reply_to_user_id' => 0,
				'in_reply_to_screen_name' => 0
			)
		);

		$this->username = 'awhalen';

	}

	public function testDecrement64BitInteger() {

		$model = $this->getModelThatReturns(1);
		$twitter = $this->getMockTwitter();

		$archiver = new Archiver($this->username, $twitter, $model);

		$this->assertEquals("1", $archiver->decrement64BitInteger(2));
		$this->assertEquals("0", $archiver->decrement64BitInteger(1));
		$this->assertEquals("-1", $archiver->decrement64BitInteger(0));
		$this->assertEquals("293780221621067775", $archiver->decrement64BitInteger("293780221621067776"));
		$this->assertEquals("-293780221621067777", $archiver->decrement64BitInteger("-293780221621067776"));
		$this->assertEquals("293780221621067779", $archiver->decrement64BitInteger("293780221621067780"));
		$this->assertEquals("-293780221621067780", $archiver->decrement64BitInteger("-293780221621067779"));

	}

	public function testDatabaseError() {

		$model = $this->getModelThatReturns(false);
		$twitter = $this->getTwitterReturnsOneTweet();

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();
		
		$this->assertTrue($this->didFindString($output, 'ERROR INSERTING TWEETS INTO DATABASE'));

	}

	public function testNoNewTweets() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturnsNoTweets();

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'NO tweets on page 1'));

	}

	public function testTooManyExceptions() {

		$model = $this->getModelThatReturns(1);

		// Throw exceptions forever
		$twitter = $this->getMockTwitter();
		$twitter->expects($this->any())
			->method('statusesUserTimeline')
			->will($this->throwException(new \Exception('Fake Twitter API Exception!')));

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Too many connection errors.'));

	}

	public function testTwitterException() {

		$model = $this->getModelThatReturns(1);
		$twitter = $this->getTwitterThrowsException();

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Exception:'));

	}

	public function testNoNewTweetsAddedToModel() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturnsOneTweet();

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Zero tweets added.'));

	}

	public function testMaxTweetsOnFirstPage() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturns200Tweets();

		$archiver = new Archiver($this->username, $twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, '200 tweets on page 1'));

	}

	/**
	 * -------------------------------------
	 * Protected methods
	 */

	/**
	 * Construct and return a mock Model
	 */
	protected function getModelThatReturns($returnValue) {

		// Create a Mock Object for the Model class
		$model = $this->getMockBuilder('AMWhalen\ArchiveMyTweets\Model')
			->disableOriginalConstructor()
			->getMock();

		$model->expects($this->any())
			->method('addTweets')
			->will($this->returnValue($returnValue));

		return $model;

	}

	/**
	 * Constructs and returns a mock Twitter
	 */
	protected function getMockTwitter() {

		// Create a Mock Object for the Twitter class
		$twitter = $this->getMockBuilder('TijsVerkoyen\Twitter\Twitter')
			->disableOriginalConstructor()
			->getMock();

		$twitter->expects($this->any())
			->method('getLastRateLimitStatus')
			->will($this->returnValue(array('remaining'=>180,'limit'=>180)));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 1 tweet on the first page, and zero on the second page
	 */
	protected function getTwitterReturnsOneTweet() {

		$twitter = $this->getMockTwitter();

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue($this->arrayOfTweets));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(2))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 1 OLD tweet on the first page, and zero on the second page
	 */
	protected function getTwitterReturnsOneOldTweet() {

		$twitter = $this->getMockTwitter();

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue(array($this->latestTweet)));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(2))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 1 tweet on the first request, exception on second, and return zero on the third
	 */
	protected function getTwitterThrowsException() {

		$twitter = $this->getMockTwitter();

		// remember to include the calls to getLastRateLimitStatus() for the at() indexes

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue($this->arrayOfTweets));

		// Throw an exception!
		$twitter->expects($this->at(2))
			->method('statusesUserTimeline')
			->will($this->throwException(new \Exception('Fake Twitter API Exception!')));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(4))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 0 tweets on the first page
	 */
	protected function getTwitterReturnsNoTweets() {

		$twitter = $this->getMockTwitter();

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 200 tweets, then zero
	 */
	protected function getTwitterReturns200Tweets() {

		$twitter = $this->getMockTwitter();

		$lotsOfTweets = array();
		for ($i = 0; $i < 200; $i++) {
			$lotsOfTweets[] = array('id' => $i);
		}

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue($lotsOfTweets));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(2))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Returns true if the string is found in the haystack
	 */
	protected function didFindString($haystack, $needle) {
		return strstr($haystack, $needle) !== false;
	}

}