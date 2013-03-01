<?php

namespace AMWhalen\ArchiveMyTweets;

/**
 * The ArchiveMyTweets application class.
 */
class App {

	protected $config;
	protected $model;
	protected $view;
	protected $controller;
	protected $router;

	// current version
	const VERSION = '0.5';

	/**
	 * Constructor
	 *
	 * @param array $config Exp
	 */
	public function __construct($config) {
		
		// merge user config with default config
		$defaultConfig = array(
			'twitter' => array(
				'username' => '',
				'name'     => ''
			),
			'auth' => array(
				'consumerKey'    => '',
				'consumerSecret' => '',
				'oauthToken'     => '',
				'oauthSecret'    => ''
			),
			'db' => array(
				'host'     => '',
				'username' => '',
				'password' => '',
				'database' => '',
				'prefix'   => ''
			),
			'baseUrl'    => '',
			'cronSecret' => '',
			'theme'      => 'default'
		);
		$this->config = array_merge($defaultConfig, $config);

		// Model
		try {
			$dsn  = "mysql:host=".$this->config['db']['host'].";dbname=".$this->config['db']['database'].";charset=utf8";
			$user = $this->config['db']['username'];
			$pass = $this->config['db']['password'];
			$db = new \PDO($dsn, $user, $pass);
			$this->model = new Model($db, $this->config['db']['prefix']);
		} catch(\PDOException $e) {
			Throw $e;
		}

		// View
		try {
			$this->view = new View(dirname(__FILE__).'/../themes/'.$this->config['theme']);
		} catch (\Exception $e) {
			Throw $e;
		}

		// Controller
		$controllerData = array(
			'config' => array(
				'twitter' => $this->config['twitter'],
				'system' => array(
					'baseUrl' => $this->config['baseUrl']
				)
			)
		);
		$this->controller = new Controller($this->model, $this->view, new Paginator(), $controllerData);
		$this->router = new Router($this->controller);

	}

	/**
	 * Returns the congiguration array
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Runs the web interface
	 */
	public function run() {
		$this->router->route();
	}
	
	/**
	 * Grabs all the latest tweets and puts them into the database.
	 *
	 * @return string Returns a string with informational output.
	 */
	public function archive() {

		// create twitter instance
		$twitter = new \TijsVerkoyen\Twitter\Twitter($this->config['auth']['consumerKey'], $this->config['auth']['consumerSecret']);
		$twitter->setOAuthToken($this->config['auth']['oauthToken']);
		$twitter->setOAuthTokenSecret($this->config['auth']['oauthSecret']);

		$archiver = new Archiver($this->config['twitter']['username'], $twitter, $this->model);
		return $archiver->archive();

	}

	/**
	 * Imports tweets from the JSON files in a downloaded Twitter Archive
	 *
	 * @param string $directory The directory to look for Twitter .js files.
	 * @return string Returns a string with informational output.
	 */
	public function importJSON($directory) {

		$importer = new Importer();
		return $importer->importJSON($directory, $this->model);

	}

};

?>
