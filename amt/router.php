<?php

namespace AMWhalen\ArchiveMyTweets;

class Router {

	protected $controller;

	public function __construct(Controller $controller) {
		$this->controller = $controller;
	}

	/**
	 * Calls a specific controller method based on $_GET['method']
	 */
	public function route() {

		$method = (isset($_GET['method'])) ? $_GET['method'] : false;

		if ($method) {

			if (method_exists($this->controller, $method)) {
				$this->controller->$method();
			} else {
				$this->controller->notFound();
			}

		} else {

			// default route
			$this->controller->index();
		
		}

	}

}