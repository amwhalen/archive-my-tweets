<?php

namespace AMWhalen\ArchiveMyTweets;

class Controller {

	protected $model;
	protected $view;
	protected $paginator;
	protected $data;

	public function __construct(Model $model, View $view, Paginator $paginator, $data=array()) {

		$this->model = $model;
		$this->view = $view;
		$this->paginator = $paginator;

		// load with default data
		$this->data = $data;

	}

	public function index() {

		// get data for all index views
		$this->data['twitterMonths']       = $this->model->getTwitterMonths();
		$this->data['twitterClients']      = $this->model->getTwitterClients();
		$this->data['maxTweets']           = $this->model->getMostTweetsInAMonth();
		$this->data['totalTweets']         = $this->model->getTotalTweets();
		$this->data['totalFavoriteTweets'] = $this->model->getTotalFavoriteTweets();
		$this->data['totalClients']        = $this->model->getTotalClients();
		$this->data['maxClients']          = $this->model->getMostPopularClientTotal();
		$this->data['header']              = '';
		$this->data['prevTweet']           = null;
		$this->data['nextTweet']           = null;
		$perPage = 50;

		$current_page = (isset($_GET['page'])) ? htmlentities($_GET['page']) : 1;
		$offset = ($current_page > 1) ? (($current_page-1) * $perPage) : 0;

		// the big switch. this decides what to show on the page.
		if (isset($_GET['id'])) {
		
			// show a single tweet
			$this->data['pageType'] = 'single';
			$this->data['single_tweet'] = true;
			$this->data['tweets'] = array($this->model->getTweet($_GET['id']));
			$this->data['header'] = '';
			$this->data['prevTweet'] = $this->model->getTweetBefore($_GET['id']);
			$this->data['nextTweet'] = $this->model->getTweetAfter($_GET['id']);
			if ($this->data['nextTweet'] || $this->data['prevTweet']) {
				$pagination = '<div class="amt-pagination"><ul class="pager">';
				if ($this->data['prevTweet']) {
					$pagination .= '<li class="previous"><a href="'.$this->data['config']['system']['baseUrl'].$this->data['prevTweet']['id'].'/">&larr; Previous Tweet</a></li>';
				}
				if ($this->data['nextTweet']) {
					$pagination .= '<li class="next"><a href="'.$this->data['config']['system']['baseUrl'].$this->data['nextTweet']['id'].'/">Next Tweet &rarr;</a></li>';
				}
				$pagination .= '</ul></div>';
			} else {
				$pagination = '';
			}
			$this->data['pagination'] = $pagination;

		} else if (isset($_GET['q'])) {

			// show search results
			$searchTerm = str_replace('&quot;', '"', htmlspecialchars($_GET['q']));
			$this->data['pageType'] = 'search';
			$this->data['search'] = true;
			$this->data['searchTerm'] = $searchTerm;
			$this->data['tweets'] = $this->model->getSearchResults($searchTerm, $offset, $perPage);
			$this->data['totalTweetsForSearch'] = $this->model->getSearchResults($searchTerm, $offset, $perPage, true);
			$pageBaseUrl = $this->data['config']['system']['baseUrl'].'?q='.urlencode($searchTerm);
			$this->data['pagination'] = $this->paginator->paginate($pageBaseUrl, $this->data['totalTweetsForSearch'], $current_page, $perPage, false);
			$header = 'Search <small>'.$searchTerm.'</small>';
			$this->data['header'] = $header;
		
		} else if (isset($_GET['year']) && isset($_GET['month'])) {
		
			// show tweets from a specific month
			$this->data['pageType'] = 'month';
			$this->data['monthly_archive'] = true;
			$this->data['archive_year'] = $_GET['year'];
			$this->data['archive_month'] = $_GET['month'];
			$this->data['tweets'] = $this->model->getTweetsByMonth($this->data['archive_year'], $this->data['archive_month'], $offset, $perPage);
			$this->data['totalTweetsByMonth'] = $this->model->getTweetsByMonthCount($this->data['archive_year'], $this->data['archive_month']);
			$pageBaseUrl = $this->data['config']['system']['baseUrl'].'archive/'.urlencode($this->data['archive_year']).'/'.urlencode($this->data['archive_month']).'/';
			$this->data['pagination'] = $this->paginator->paginate($pageBaseUrl, $this->data['totalTweetsByMonth'], $current_page, $perPage);
			$this->data['header'] = date('F Y', strtotime($this->data['archive_year'].'-'.$this->data['archive_month'].'-01'));
		
		} else if (isset($_GET['client'])) {
		
			// show tweets from a specific client
			$this->data['pageType'] = 'client_archive';
			$this->data['per_client_archive'] = true;
			$this->data['client'] = htmlspecialchars($_GET['client']);
			$this->data['tweets'] = $this->model->getTweetsByClient($this->data['client'], $offset, $perPage);
			$this->data['totalTweetsByClient'] = $this->model->getTweetsByClientCount($this->data['client']);
			$pageBaseUrl = $this->data['config']['system']['baseUrl'].'client/'.urlencode($this->data['client']).'/';
			$this->data['pagination'] = $this->paginator->paginate($pageBaseUrl, $this->data['totalTweetsByClient'], $current_page, $perPage);
			$this->data['header'] .= 'Tweets from '.$this->data['client'];

		} else if (isset($_GET['favorites'])) {
		
			// show only favorite tweets
			$this->data['pageType'] = 'favorites';
			$this->data['favorite_tweets'] = true;
			$this->data['client'] = $_GET['favorites'];
			$this->data['tweets'] = $this->model->getFavoriteTweets($offset, $perPage);
			$pageBaseUrl = $this->data['config']['system']['baseUrl'].'favorites/';
			$this->data['pagination'] = $this->paginator->paginate($pageBaseUrl, $this->data['totalFavoriteTweets'], $current_page, $perPage);
			$this->data['header'] .= 'Favorite Tweets';

		} else {
		
			// default view: show all the tweets
			$this->data['pageType'] = 'recent';
			$this->data['all_tweets'] = true;
			$this->data['tweets'] = $this->model->getTweets($offset, $perPage);
			$this->data['pagination'] = $this->paginator->paginate($this->data['config']['system']['baseUrl'], $this->data['totalTweets'], $current_page, $perPage);
			$this->data['header'] = ($offset != 0) ? 'Recent Tweets <small>Page '.$current_page.'</small>' : 'Recent Tweets';
		
		}

		// render index template
		$this->data['content'] = $this->view->render('index.php', $this->data, true);
		$this->view->render('_layout.php', $this->data);

	}

	public function stats() {

		$this->data['maxTweets']           = $this->model->getMostTweetsInAMonth();
		$this->data['totalTweets']         = $this->model->getTotalTweets();
		$this->data['totalFavoriteTweets'] = $this->model->getTotalFavoriteTweets();
		$this->data['totalClients']        = $this->model->getTotalClients();
		$this->data['maxClients']          = $this->model->getMostPopularClientTotal();

		$this->data['pageType'] = 'stats';
		$this->data['content'] = $this->view->render('stats.php', $this->data, true);
		$this->view->render('_layout.php', $this->data);

	}

	public function notFound() {

		if (!headers_sent()) {
			header("HTTP/1.0 404 Not Found");
		}
		$this->data['pageType'] = 'notfound';
		$this->data['content'] = '<h1>Not Found</h1>';
		$this->view->render('_layout.php', $this->data);

	}

}
