<?php

namespace AMWhalen\ArchiveMyTweets;

class PaginatorTest extends \PHPUnit_Framework_TestCase {

	protected $baseUrl;

	public function setUp() {
		$this->baseUrl = 'http://amwhalen.com/twitter/';
	}

	public function testPaginationFirstPage() {

		$paginator = new Paginator();
		$output = $paginator->paginate($baseUrl, 500, 1, 100);

		$this->assertTrue($this->didFindString($output, 'Older Tweets'));
		$this->assertFalse($this->didFindString($output, 'Newer Tweets'));

	}

	public function testPaginationMiddlePage() {

		$paginator = new Paginator();
		$output = $paginator->paginate($baseUrl, 500, 3, 100);

		$this->assertTrue($this->didFindString($output, 'Older Tweets'));
		$this->assertTrue($this->didFindString($output, 'Newer Tweets'));

	}

	public function testPaginationLastPage() {

		$paginator = new Paginator();
		$output = $paginator->paginate($baseUrl, 500, 5, 100);

		$this->assertFalse($this->didFindString($output, 'Older Tweets'));
		$this->assertTrue($this->didFindString($output, 'Newer Tweets'));

	}

	public function testPaginationOnlyPage() {

		$paginator = new Paginator();
		$output = $paginator->paginate($baseUrl, 100, 1, 100);

		$this->assertFalse($this->didFindString($output, 'Older Tweets'));
		$this->assertFalse($this->didFindString($output, 'Newer Tweets'));

	}

	/**
	 * Returns true if the string is found in the haystack
	 */
	protected function didFindString($haystack, $needle) {
		return strstr($haystack, $needle) !== false;
	}

}