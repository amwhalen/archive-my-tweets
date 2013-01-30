<?php

namespace AMWhalen\ArchiveMyTweets;

class ImporterTest extends \PHPUnit_Framework_TestCase {

	protected $model;

	public function setUp() {
		
		require_once dirname(__FILE__) . '/../includes.php';

		// Create a Mock Object for the Model class
		$this->model = $this->getMockBuilder('AMWhalen\ArchiveMyTweets\Model')
			->disableOriginalConstructor()
			->getMock();

		// Calling $model->addTweets() will now return 6
		$this->model->expects($this->any())
			->method('addTweets')
			->will($this->returnValue(6));

	}

	public function testGetTweetsInJsonFile() {

		// test data
		$filename = dirname(__FILE__) . '/json/2012_10.js';
		$notJSONFilename = dirname(__FILE__) . '/json/not_json.js';
		$badFilename = dirname(__FILE__) . '/json/this_is_not_a_real_file.js';

		$importer = new Importer();

		// make sure the return value is correct
		$tweets = $importer->getTweetsInJsonFile($filename);
		$this->assertCount(6, $tweets);
		$this->assertEquals('AMWhalen\ArchiveMyTweets\Tweet', get_class($tweets[0]));
		$this->assertEquals(263364339371765760, $tweets[0]->id);
		$this->assertEquals(14061545, $tweets[0]->user_id);
		$this->assertEquals(263360591496896513, $tweets[0]->in_reply_to_status_id);

		// test non-existent file
		$this->assertFalse($importer->getTweetsInJsonFile($badFilename));

		// test non-json data
		$this->assertFalse($importer->getTweetsInJsonFile($notJSONFilename));

	}

	public function testImportJSON() {

		// make sure all the tweets get added that should be added
		$importer = new Importer();
		$output = $importer->importJSON(dirname(__FILE__) . '/json', $this->model);
		$this->assertTrue($this->didFindString($output, 'Added new tweets: 6'));

	}

	public function testBadImportDirectory() {

		// check for the bad directory string in the output
		$importer = new Importer();
		$output = $importer->importJSON(dirname(__FILE__) . '/this_is_not_a_real_directory', $this->model);
		$this->assertTrue($this->didFindString($output, 'Could not import'));

	}

	public function testNoFilesInImportDirectory() {

		// give the importer a directory that contains no valid .js files
		$importer = new Importer();
		$output = $importer->importJSON(dirname(__FILE__), $this->model);
		$this->assertTrue($this->didFindString($output, 'No Twitter Archive JS files found.'));

	}

	public function testDatabaseError() {

		// model with errors
		$model = $this->getMockBuilder('AMWhalen\ArchiveMyTweets\Model')
			->disableOriginalConstructor()
			->getMock();

		// Calling $model->addTweets() will now return FALSE
		$model->expects($this->any())
			->method('addTweets')
			->will($this->returnValue(false));

		// expect model error
		$importer = new Importer();
		$output = $importer->importJSON(dirname(__FILE__) . '/json', $model);
		$this->assertTrue($this->didFindString($output, 'ERROR INSERTING INTO DATABASE'));

	}

	public function testNoNewTweets() {

		// model with all current tweets
		$model = $this->getMockBuilder('AMWhalen\ArchiveMyTweets\Model')
			->disableOriginalConstructor()
			->getMock();

		// Calling $model->addTweets() will now return 0
		$model->expects($this->any())
			->method('addTweets')
			->will($this->returnValue(0));

		// expect model to not add any new tweets
		$importer = new Importer();
		$output = $importer->importJSON(dirname(__FILE__) . '/json', $model);
		$this->assertTrue($this->didFindString($output, 'No new tweets found.'));

	}

	/**
	 * Returns true if the string is found in the haystack
	 */
	protected function didFindString($haystack, $needle) {
		return strstr($haystack, $needle) !== false;
	}

}
