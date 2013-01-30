<?php

namespace AMWhalen\ArchiveMyTweets;

class ArchiverTest extends \PHPUnit_Framework_TestCase {

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

	}

	public function testOneNewTweet() {

		$model = $this->getModelThatReturns(1);
		$twitter = $this->getTwitterReturnsOneTweet();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Got 1 results on page 1. (Some tweets may have been filtered out.)'));
		$this->assertTrue($this->didFindString($output, '1 new tweet over 2 queries.'));

		// this shouldn't appear as we're pretending that there's no latest tweet in the DB
		$this->assertFalse($this->didFindString($output, 'Getting tweets with an id greater than'));

	}

	public function testGetFromLatestId() {

		$model = $this->getModelThatReturns(1);
		$twitter = $this->getTwitterReturnsOneTweet();

		$model->expects($this->any())
			->method('getLatestTweet')
			->will($this->returnValue($this->latestTweet));

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Getting tweets with an id greater than'));

	}

	public function testDatabaseError() {

		$model = $this->getModelThatReturns(false);
		$twitter = $this->getTwitterReturnsOneTweet();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();
		
		$this->assertTrue($this->didFindString($output, 'ERROR INSERTING TWEETS INTO DATABASE'));

	}

	public function testNoNewTweets() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturnsNoTweets();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'No tweets on page 1.'));

	}

	public function testTwitterException() {

		$model = $this->getModelThatReturns(1);
		$twitter = $this->getTwitterThrowsException();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Exception:'));

	}

	public function testNoNewTweetsAddedToModel() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturnsOneTweet();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Zero tweets added.'));

	}

	public function testMaxTweetsOnFirstPage() {

		$model = $this->getModelThatReturns(0);
		$twitter = $this->getTwitterReturns200Tweets();

		$archiver = new Archiver($twitter, $model);
		$output = $archiver->archive();

		$this->assertTrue($this->didFindString($output, 'Got 200 results on page 1.'));
		$this->assertFalse($this->didFindString($output, '(Some tweets may have been filtered out.)'));

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
		$twitter = $this->getMockBuilder('Twitter')
			->disableOriginalConstructor()
			->getMock();

		// accountRateLimitStatus
		$twitter->expects($this->any())
			->method('accountRateLimitStatus')
			->will($this->returnValue(array(
				'remaining_hits' => 145,
				'hourly_limit'   => 150,
				'reset_time'     => 'Sun Jan 27 03:04:44 +0000 2013'
			)));

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
		$twitter->expects($this->at(1))
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
		$twitter->expects($this->at(1))
			->method('statusesUserTimeline')
			->will($this->returnValue(array()));

		return $twitter;

	}

	/**
	 * Sets the mock Twitter object to return 1 tweet on the first page, exception on second, and return zero on the third page
	 */
	protected function getTwitterThrowsException() {

		$twitter = $this->getMockTwitter();

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue($this->arrayOfTweets));

		// Throw an exception!
		$twitter->expects($this->at(1))
			->method('statusesUserTimeline')
			->will($this->returnCallback(function() { Throw new \Exception('Twitter API Exception!'); }));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(2))
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
			$lotsOfTweets[] = array();
		}

		// Calling $twitter->statusesUserTimeline() will return an array the first time
		$twitter->expects($this->at(0))
			->method('statusesUserTimeline')
			->will($this->returnValue($lotsOfTweets));

		// Calling $twitter->statusesUserTimeline() will return an empty array the second time
		$twitter->expects($this->at(1))
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