<?php

namespace AMWhalen\ArchiveMyTweets;
use PDO;

/**
 * The MySQL persistence class
 */
class Model {

	protected $db;
	protected $table;

	/**
	 * Constructor
	 *
	 * @param $db A PDO instance.
	 * @param $prefix The table prefix.
	 */
	public function __construct($db, $prefix) {
		$this->db = $db;
		$this->table = $prefix . 'tweets';
	}

	/**
	 * Returns the table name
	 */
	public function getTableName() {
		return $this->table;
	}

	/**
	 * Gets a tweet by ID
	 *
	 * @return array|false Returns the tweet with the given ID or false on failure.
	 */
	public function getTweet($id) {

		$stmt = $this->db->prepare("select * from ".$this->table." where id=:id limit 1");
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$status = $stmt->execute();

		if ($status && $stmt->rowCount()) {
			return $stmt->fetch();
		} else {
			return false;
		}

	}

	/**
	 * Gets the tweet that was made before the given tweet ID
	 * 
	 * @param int $id The current tweet ID
	 * @return array|false Returns the tweet that was made before the given tweet ID, or false if one was not found.
	 */
	public function getTweetBefore($id) {

		$stmt = $this->db->prepare("select * from ".$this->table." where id < :id order by id desc limit 1");
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$status = $stmt->execute();

		if ($status && $stmt->rowCount()) {
			return $stmt->fetch();
		} else {
			return false;
		}

	}

	/**
	 * Gets the tweet that was made after the given tweet ID
	 * 
	 * @param int $id The current tweet ID
	 * @return array|false Returns the tweet that was made after the given tweet ID, or false if one was not found.
	 */
	public function getTweetAfter($id) {

		$stmt = $this->db->prepare("select * from ".$this->table." where id > :id order by id asc limit 1");
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$status = $stmt->execute();

		if ($status && $stmt->rowCount()) {
			return $stmt->fetch();
		} else {
			return false;
		}

	}

	/**
	 * Gets the most recent tweet as a Tweet object
	 *
	 * @return array|false Returns the most recent tweet or false on failure.
	 */
	public function getLatestTweet() {
	
		$stmt = $this->db->prepare("select * from ".$this->table." order by id desc limit 1");
		$status = $stmt->execute();

		if ($status && $stmt->rowCount()) {
			return $stmt->fetch();
		} else {
			return false;
		}
	
	}

	public function getTweets($offset=0, $perPage=50) {

		$stmt = $this->db->prepare("select * from ".$this->table." order by id desc limit :offset,:perPage");
		$stmt->bindValue(':offset',  (int) $offset,  PDO::PARAM_INT);
		$stmt->bindValue(':perPage', (int) $perPage, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getSearchResults($k, $offset=0, $perPage=50, $count=false) {
	
		if (trim($k) == '') return false;
	
		if ($count) {
			$sql  = 'select count(*) as total from '.$this->table.' where 1 ';
		} else {
			$sql  = 'select * from '.$this->table.' where 1 ';
		}
		
		// split out the quoted items
		// $phrases[0] is an array of full pattern matches (quotes intact)
		// $phrases[1] is an array of strings matched by the first parenthesized subpattern, and so on. (quotes stripped)
		// the .+? means match 1 or more characters, but don't be "greedy", i.e., match the smallest amount
		preg_match_all("/\"(.+?)\"/", $k, $phrases);
		$words = explode(' ', preg_replace('/".+?"/', '', $k));
		$word_list = array_merge($phrases[1], $words);
			
		// create the sql statement
		$sql .= 'AND (';
		$wordParams = array();
		$i = 1;
		foreach ($word_list as $word) {
			if (strlen($word)) {
				$key = ':word'.$i;
				$wordParams[$key] = '%' . str_replace(",", "", strtolower($word)) . '%';
				$sql .= "(tweet like ".$key.") or ";
				$i++;
			}
		}
		$sql = rtrim($sql, " or "); // remove that dangling "or"
		$sql .= ') order by id desc';

		if (!$count) {
			$sql .= ' limit :offset,:perPage';
		}

		// bind each search term
		$stmt = $this->db->prepare($sql);
		foreach ($wordParams as $key=>$param) {
			$stmt->bindValue($key, $param, PDO::PARAM_STR);
		}
		if (!$count) {
			$stmt->bindValue(':offset',  (int) $offset,  PDO::PARAM_INT);
			$stmt->bindValue(':perPage', (int) $perPage, PDO::PARAM_INT);
		}
		$stmt->execute();

		if ($count) {
			$row = $stmt->fetch();
			return $row['total'];
		} else {
			return $stmt->fetchAll();
		}
	
	}

	public function getFavoriteTweets($offset=0, $perPage=50) {

		$stmt = $this->db->prepare("select * from ".$this->table." where favorited=1 order by id desc limit :offset,:perPage");
		$stmt->bindValue(':offset',  (int) $offset,  PDO::PARAM_INT);
		$stmt->bindValue(':perPage', (int) $perPage, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getTweetsByMonth($year, $month, $offset=0, $perPage=50) {

		$stmt = $this->db->prepare('select * from '.$this->table.' where year(created_at)=:year and month(created_at)=:month order by id desc limit :offset,:perPage');
		$stmt->bindValue(':year',    (int) $year,    PDO::PARAM_INT);
		$stmt->bindValue(':month',   (int) $month,   PDO::PARAM_INT);
		$stmt->bindValue(':offset',  (int) $offset,  PDO::PARAM_INT);
		$stmt->bindValue(':perPage', (int) $perPage, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getTweetsByMonthCount($year, $month) {

		$stmt = $this->db->prepare('select count(*) as total from '.$this->table.' where year(created_at)=:year and month(created_at)=:month order by id desc');
		$stmt->bindValue(':year',    (int) $year,    PDO::PARAM_INT);
		$stmt->bindValue(':month',   (int) $month,   PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTweetsByClient($client, $offset=0, $perPage=50) {

		$stmt = $this->db->prepare('select * from '.$this->table.' where source REGEXP CONCAT("(<a.*>)?", :client, "(</a>)?") order by id desc limit :offset,:perPage');
		$stmt->bindValue(':client',        $client,  PDO::PARAM_STR);
		$stmt->bindValue(':offset',  (int) $offset,  PDO::PARAM_INT);
		$stmt->bindValue(':perPage', (int) $perPage, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getTweetsByClientCount($client) {

		$stmt = $this->db->prepare('select count(*) as total from '.$this->table.' where source REGEXP CONCAT("(<a.*>)?", :client, "(</a>)?")');
		$stmt->bindValue(':client',        $client,  PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTwitterMonths() {

		$stmt = $this->db->prepare('select year(created_at) as y, month(created_at) as m, count(*) AS total FROM '.$this->table.' GROUP BY year(created_at),month(created_at) order by created_at desc');
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getMostTweetsInAMonth() {

		$stmt = $this->db->prepare('select year(created_at) as y, month(created_at) as m, count(*) AS total FROM '.$this->table.' GROUP BY year(created_at),month(created_at) order by total desc limit 1');
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTwitterClients() {

		$stmt = $this->db->prepare('select source, count(*) as total, count(source) as c from '.$this->table.' group by source order by count(source) desc');
		$stmt->execute();
		return $stmt->fetchAll();

	}

	public function getMostPopularClientTotal() {

		$stmt = $this->db->prepare('select count(*) AS total FROM '.$this->table.' GROUP BY source order by total desc limit 1');
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTotalTweets() {

		$stmt = $this->db->prepare('select count(*) as total from '.$this->table);
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTotalFavoriteTweets() {

		$stmt = $this->db->prepare('select count(*) as total from '.$this->table.' where favorited=1');
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	public function getTotalClients() {

		$stmt = $this->db->prepare('select count(distinct source) as total from '.$this->table);
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['total'];

	}

	/**
	 * Adds an array of Tweet objects to the database.
	 *
	 * @param array $tweets An array of Tweet objects.
	 * @return int|boolean Returns the number of tweets added to the database, or returns FALSE if there was a MySQL error.
	 */
	public function addTweets($tweets) {
	
		if (count($tweets)) {
		
			// "insert ignore" will ignore rows with an id that already exists in the table
			$sql = 'insert ignore into '.$this->table." (id,user_id,created_at,tweet,source,truncated,favorited,in_reply_to_status_id,in_reply_to_user_id,in_reply_to_screen_name) values";
		
			$i = 0;
			$params = array();
			$values = array();
			foreach ($tweets as $t) {
				$params[':id'.$i] = $t->id;
				$params[':user_id'.$i] = $t->user_id;
				$params[':created_at'.$i] = $t->created_at;
				$params[':tweet'.$i] = $t->tweet;
				$params[':source'.$i] = $t->source;
				$params[':truncated'.$i] = $t->truncated;
				$params[':favorited'.$i] = $t->favorited;
				$params[':in_reply_to_status_id'.$i] = $t->in_reply_to_status_id;
				$params[':in_reply_to_user_id'.$i] = $t->in_reply_to_user_id;
				$params[':in_reply_to_screen_name'.$i] = $t->in_reply_to_screen_name;
				$values[] = '(:id'.$i.',:user_id'.$i.',:created_at'.$i.',:tweet'.$i.',:source'.$i.',:truncated'.$i.',:favorited'.$i.',:in_reply_to_status_id'.$i.',:in_reply_to_user_id'.$i.',:in_reply_to_screen_name'.$i.')';
				$i++;
			}
			
			// join all the value groups together: values(1,2,3),(4,5,6),(6,7,8)
			$sql .= implode(",", $values);

			// integer params
			$intParamKeys = array(':id', ':user_id', ':in_reply_to_status_id', ':in_reply_to_user_id', ':in_reply_to_screen_name');

			$stmt = $this->db->prepare($sql);
			$paramType = PDO::PARAM_STR;
			foreach ($params as $key=>$value) {
				// some params are ints that need to be bound correctly
				foreach ($intParamKeys as $intK) {
					if (substr($key, 0, strlen($intK)) == $intK) {
						$paramType = PDO::PARAM_INT;
						break;
					} else {
						$paramType = PDO::PARAM_STR;
					}
				}
				$stmt->bindValue($key, $value, $paramType);
			}
			$status = $stmt->execute();
			return $stmt->rowCount();
		
		}
				
		return 0;
	
	}

	/**
	 * Returns the last error message from the DB
	 */
	public function getLastErrorMessage() {
		$error = $this->db->errorInfo();
		return $error[2];
	}

	/**
	 * Returns true if the database table exists.
	 *
	 * @return boolean Returns true if the database table exists, or false if the table hasn't been created.
	 */
	public function isInstalled() {

		$stmt = $this->db->prepare("show tables like '" . $this->table . "'");
		$status = $stmt->execute();
		return ($status && $stmt->rowCount());
	
	}

	/**
	 * Creates the database table necessary to hold the tweets.
	 *
	 * @return boolean Returns true on success, false on failure.
	 * @throws \Exception if there was an error
	 */
	public function install() {
	
		$stmt = $this->db->prepare('create table '.$this->table.' ( id bigint(20) unsigned not null unique, user_id bigint(20) unsigned not null, created_at datetime not null, tweet varchar(140), source varchar(255), truncated tinyint(1), favorited tinyint(1), in_reply_to_status_id bigint(20), in_reply_to_user_id bigint(20), in_reply_to_screen_name varchar(15), index(source) ) ENGINE=MyISAM DEFAULT CHARSET=utf8;');
	
		// TODO: run SQL updates here, each in its own function

		$status = $stmt->execute();
		if (!$status) {
			$errorInfo = $stmt->errorInfo();
			Throw new \Exception($errorInfo[2]);
		}
		return $status;
	
	}

	// TODO: upgrade function for running database migration files

}