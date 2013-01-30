<?php

namespace AMWhalen\ArchiveMyTweets;

class RouterTest extends \PHPUnit_Framework_TestCase {

	protected $controller;
	protected $router;

	// will be changed by a callback method then checked afterwards
	protected $callbackCalled;

	public function setUp() {
		
		require_once dirname(__FILE__) . '/../includes.php';

		// Create a mock object for the Controller class
		$this->controller = $this->getMockBuilder('AMWhalen\ArchiveMyTweets\Controller')
			->disableOriginalConstructor()
			->getMock();

		// Override the index() method to assert that it was called
		$this->controller->expects($this->any())
			->method('index')
			->will($this->returnCallback(array($this, 'controllerIndexCallback')));

		// Override the notFound() method to assert that it was called
		$this->controller->expects($this->any())
			->method('notFound')
			->will($this->returnCallback(array($this, 'controllerNotFoundCallback')));

		// router used to test
		$this->router = new Router($this->controller);

	}

	public function testRoute() {

		// preconditions
		$_SERVER['REMOTE_ADDR'] = 'notempty'; // not empty so the CLI controller is not called
		$_GET['method'] = 'index';
		$this->callbackCalled = false;

		// test
		$this->router->route();

		// make sure the controller->index() method was called
		$this->assertEquals('index', $this->callbackCalled);

	}

	public function testDefaultRoute() {

		// preconditions
		$_SERVER['REMOTE_ADDR'] = 'notempty'; // not empty so the CLI controller is not called
		$_GET['method'] = '';
		$this->callbackCalled = false;

		// test
		$this->router->route();

		// make sure the controller->index() method was called
		$this->assertEquals('index', $this->callbackCalled);

	}

	public function testRouteNotFound() {

		// preconditions: the specified method should produce a "not found" error
		$_SERVER['REMOTE_ADDR'] = 'notempty'; // not empty so the CLI controller is not called
		$_GET['method'] = 'this_method_does_not_exist';
		$this->callbackCalled = false;

		// test
		$this->router->route();

		// make sure the controller->notFound() method was called
		$this->assertEquals('notFound', $this->callbackCalled);

	}

	// callback for controller methods
	public function controllerIndexCallback() { $this->callbackCalled = 'index'; }
	public function controllerNotFoundCallback() { $this->callbackCalled = 'notFound'; }

}