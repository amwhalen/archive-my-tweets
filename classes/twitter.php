<?php

/**
 * Twitter class
 *
 * This source file can be used to communicate with Twitter (http://twitter.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-twitter-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * Known Issues
 *  - savedSearchesDestroy isn't working correctly
 *  - trendsLocation isn't working correctly
 *  - oAuthAuthenticate isn't working correctly
 *  - accountUpdateProfileImage isn't implemented
 *  - accountUpdateProfileBackgroundImage isn't implemented
 *  - helpTest isn't working correctly
 *
 * Changelog since 2.0.0
 * - no more fatal if twitter is over capacity
 * - fix for calculating the header-string (thx to Dextro)
 * - fix for userListsIdStatuses (thx to Josh)
 * -
 *
 * License
 * Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author		Tijs Verkoyen <php-twitter@verkoyen.eu>
 * @version		2.0.1
 *
 * @copyright	Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 * @license		BSD License
 */
class Twitter
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the twitter-api
	const API_URL = 'https://api.twitter.com/1';
	const SECURE_API_URL = 'https://api.twitter.com';

	// port for the twitter-api
	const API_PORT = 443;
	const SECURE_API_PORT = 443;

	// current version
	const VERSION = '2.0.1';


	/**
	 * A cURL instance
	 *
	 * @var	resource
	 */
	private $curl;


	/**
	 * The consumer key
	 *
	 * @var	string
	 */
	private $consumerKey;


	/**
	 * The consumer secret
	 *
	 * @var	string
	 */
	private $consumerSecret;


	/**
	 * The oAuth-token
	 *
	 * @var	string
	 */
	private $oAuthToken = '';


	/**
	 * The oAuth-token-secret
	 *
	 * @var	string
	 */
	private $oAuthTokenSecret = '';


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $consumerKey		The consumer key to use.
	 * @param	string $consumerSecret	The consumer secret to use.
	 */
	public function __construct($consumerKey, $consumerSecret)
	{
		$this->setConsumerKey($consumerKey);
		$this->setConsumerSecret($consumerSecret);
	}


	/**
	 * Default destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if($this->curl != null) curl_close($this->curl);
	}


	/**
	 * Format the parameters as a querystring
	 *
	 * @return	string
	 * @param	array $parameters
	 */
	private function buildQuery(array $parameters)
	{
		// no parameters?
		if(empty($parameters)) return '';

		// encode the keys
		$keys = self::urlencode_rfc3986(array_keys($parameters));

		// encode the values
		$values = self::urlencode_rfc3986(array_values($parameters));

		// reset the parameters
		$parameters = array_combine($keys, $values);

		// sort parameters by key
		uksort($parameters, 'strcmp');

		// loop parameters
		foreach($parameters as $key => $value)
		{
			// sort by value
			if(is_array($value)) $parameters[$key] = natsort($value);
		}

		// process parameters
		foreach($parameters as $key => $value) $chunks[] = $key .'='. str_replace('%25', '%', $value);

		// return
		return implode('&', $chunks);
	}


	/**
	 * All OAuth 1.0 requests use the same basic algorithm for creating a signature base string and a signature.
	 * The signature base string is composed of the HTTP method being used, followed by an ampersand ("&") and then the URL-encoded base URL being accessed,
	 * complete with path (but not query parameters), followed by an ampersand ("&").
	 * Then, you take all query parameters and POST body parameters (when the POST body is of the URL-encoded type, otherwise the POST body is ignored),
	 * including the OAuth parameters necessary for negotiation with the request at hand, and sort them in lexicographical order by first parameter name and
	 * then parameter value (for duplicate parameters), all the while ensuring that both the key and the value for each parameter are URL encoded in isolation.
	 * Instead of using the equals ("=") sign to mark the key/value relationship, you use the URL-encoded form of "%3D". Each parameter is then joined by the
	 * URL-escaped ampersand sign, "%26".
	 *
	 * @return	string
	 * @param	string $url
	 * @param	string $method
	 * @param	array $parameters
	 */
	private function calculateBaseString($url, $method, array $parameters)
	{
		// redefine
		$url = (string) $url;
		$parameters = (array) $parameters;

		// init var
		$pairs = array();
		$chunks = array();

		// sort parameters by key
		uksort($parameters, 'strcmp');

		// loop parameters
		foreach($parameters as $key => $value)
		{
			// sort by value
			if(is_array($value)) $parameters[$key] = natsort($value);
		}

		// process queries
		foreach($parameters as $key => $value)
		{
			$chunks[] = self::urlencode_rfc3986($key) .'%3D'. self::urlencode_rfc3986($value);
		}

		// buils base
		$base = $method .'&';
		$base .= urlencode($url) .'&';
		$base .= implode('%26', $chunks);

		// return
		return $base;
	}


	/**
	 * Build the Authorization header
	 * @later: fix me
	 *
	 * @return	string
	 * @param	array $parameters
	 */
	private function calculateHeader(array $parameters, $url = null)
	{
		// redefine
		$url = (string) $url;

		// divide into parts
		$parts = parse_url($url);

		// init var
		$chunks = array();

		// process queries
		foreach($parameters as $key => $value) $chunks[] = str_replace('%25', '%', self::urlencode_rfc3986($key) .'="'. self::urlencode_rfc3986($value) .'"');

		// build return
		$return = 'Authorization: OAuth realm="' . $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '", ';
		$return .= implode(',', $chunks);

		// prepend name and OAuth part
		return $return;
	}


	/**
	 * Make an call to the oAuth
	 * @todo	refactor me
	 *
	 * @return	array
	 * @param	string $method
	 * @param	array[optional] $parameters
	 */
	private function doOAuthCall($method, array $parameters = null)
	{
		// redefine
		$method = (string) $method;

		// append default parameters
		$parameters['oauth_consumer_key'] = $this->getConsumerKey();
		$parameters['oauth_nonce'] = md5(microtime() . rand());
		$parameters['oauth_timestamp'] = time();
		$parameters['oauth_signature_method'] = 'HMAC-SHA1';
		$parameters['oauth_version'] = '1.0';

		// calculate the base string
		$base = $this->calculateBaseString(self::SECURE_API_URL .'/oauth/'. $method, 'POST', $parameters);

		// add sign into the parameters
		$parameters['oauth_signature'] = $this->hmacsha1($this->getConsumerSecret() .'&' . $this->getOAuthTokenSecret(), $base);

		// calculate header
		$header = $this->calculateHeader($parameters, self::SECURE_API_URL .'/oauth/'. $method);

		// set options
		$options[CURLOPT_URL] = self::SECURE_API_URL .'/oauth/'. $method;
		$options[CURLOPT_PORT] = self::SECURE_API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = false;
		$options[CURLOPT_HTTPHEADER] = array('Expect:');
		$options[CURLOPT_POST] = 1;
		$options[CURLOPT_POSTFIELDS] = $this->buildQuery($parameters);

		// init
		if($this->curl == null) $this->curl = curl_init();

		// set options
		curl_setopt_array($this->curl, $options);

		// execute
		$response = curl_exec($this->curl);
		$headers = curl_getinfo($this->curl);

		// fetch errors
		$errorNumber = curl_errno($this->curl);
		$errorMessage = curl_error($this->curl);

		// error?
		if($errorNumber != '') throw new TwitterException($errorMessage, $errorNumber);

		// init var
		$return = array();

		// parse the string
		parse_str($response, $return);

		// return
		return $return;
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url
	 * @param	array[optiona] $aParameters
	 * @param	bool[optional] $authenticate
	 * @param	bool[optional] $usePost
	 */
	private function doCall($url, array $parameters = null, $authenticate = false, $method = 'GET', $filePath = null, $expectJSON = true)
	{
		// allowed methods
		$allowedMethods = array('GET', 'POST');

		// redefine
		$url = (string) $url;
		$parameters = (array) $parameters;
		$authenticate = (bool) $authenticate;
		$method = (string) $method;
		$expectJSON = (bool) $expectJSON;

		// validate method
		if(!in_array($method, $allowedMethods)) throw new TwitterException('Unknown method ('. $method .'). Allowed methods are: '. implode(', ', $allowedMethods));

		// append default parameters
		$oauth['oauth_consumer_key'] = $this->getConsumerKey();
		$oauth['oauth_nonce'] = md5(microtime() . rand());
		$oauth['oauth_timestamp'] = time();
		$oauth['oauth_token'] = $this->getOAuthToken();
		$oauth['oauth_signature_method'] = 'HMAC-SHA1';
		$oauth['oauth_version'] = '1.0';

		// set data
		$data = $oauth;
		if(!empty($parameters)) $data = array_merge($data, $parameters);

		// calculate the base string
		$base = $this->calculateBaseString(self::API_URL .'/'. $url, $method, $data);

		// add sign into the parameters
		$oauth['oauth_signature'] = $this->hmacsha1($this->getConsumerSecret() .'&' . $this->getOAuthTokenSecret(), $base);

		$headers[] = $this->calculateHeader($oauth, self::API_URL .'/'. $url);
		$headers[] = 'Expect:';

		// based on the method, we should handle the parameters in a different way
		if($method == 'POST')
		{
			// file provided?
			if($filePath != null)
			{
				// build a boundary
				$boundary = md5(time());

				// process file
				$fileInfo = pathinfo($filePath);

				// set mimeType
				$mimeType = 'application/octet-stream';
				if($fileInfo['extension'] == 'jpg' || $fileInfo['extension'] == 'jpeg') $mimeType = 'image/jpeg';
				elseif($fileInfo['extension'] == 'gif') $mimeType = 'image/gif';
				elseif($fileInfo['extension'] == 'png') $mimeType = 'image/png';

				// init var
				$content = '--'. $boundary ."\r\n";

				// set file
				$content = 'Content-Disposition: form-data; name="image";filename="'. $fileInfo['basename'] .'"' ."\r\n" . 'Content-Type: '. $mimeType . "\r\n\r\n" . file_get_contents($filePath) ."\r\n--". $boundary ."\r\n";

				// build headers
				$headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
				$headers[] = 'Content-Length: '. strlen($content);

				// set content
				$options[CURLOPT_POSTFIELDS] = $content;
			}

			// no file
			else $options[CURLOPT_POSTFIELDS] = $this->buildQuery($parameters);

			// enable post
			$options[CURLOPT_POST] = 1;
		}

		else
		{
			// add the parameters into the querystring
			if(!empty($parameters)) $url .= '?'. $this->buildQuery($parameters);
		}

		// set options
		$options[CURLOPT_URL] = self::API_URL .'/'. $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = false;
		$options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
		$options[CURLOPT_HTTPHEADER] = $headers;

		// init
		if($this->curl == null) $this->curl = curl_init();

		// set options
		curl_setopt_array($this->curl, $options);

		// execute
		$response = curl_exec($this->curl);
		$headers = curl_getinfo($this->curl);

		// fetch errors
		$errorNumber = curl_errno($this->curl);
		$errorMessage = curl_error($this->curl);

		// we don't expext JSON, return the response
		if(!$expectJSON) return $response;

		// replace ids with their string values, added because of some PHP-version can't handle these large values
		$response = preg_replace('/id":(\d+)/', 'id":"\1"', $response);

		// we expect JSON, so decode it
		$json = @json_decode($response, true);

		// validate JSON
		if($json === null)
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the error
				var_dump($errorMessage);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';
			}

			// throw exception
			throw new TwitterException('Invalid response.');
		}


		// any errors
		if(isset($json['errors']))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the error
				var_dump($errorMessage);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';
			}

			// throw exception
			if(isset($json['errors'][0]['message'])) throw new TwitterException($json['errors'][0]['message']);
			else throw new TwitterException('Invalid response.');
		}


		// any error
		if(isset($json['error']))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';
			}

			// throw exception
			throw new TwitterException($json['error']);
		}

		// return
		return $json;
	}


	/**
	 * Get the consumer key
	 *
	 * @return	string
	 */
	private function getConsumerKey()
	{
		return $this->consumerKey;
	}


	/**
	 * Get the consumer secret
	 *
	 * @return	string
	 */
	private function getConsumerSecret()
	{
		return $this->consumerSecret;
	}


	/**
	 * Get the oAuth-token
	 *
	 * @return	string
	 */
	private function getOAuthToken()
	{
		return $this->oAuthToken;
	}


	/**
	 * Get the oAuth-token-secret
	 *
	 * @return	string
	 */
	private function getOAuthTokenSecret()
	{
		return $this->oAuthTokenSecret;
	}


	/**
	 * Get the timeout
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Twitter/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Twitter/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Set the consumer key
	 *
	 * @return	void
	 * @param	string $key		The consumer key to use.
	 */
	private function setConsumerKey($key)
	{
		$this->consumerKey = (string) $key;
	}


	/**
	 * Set the consumer secret
	 *
	 * @return	void
	 * @param	string $secret	The consumer secret to use.
	 */
	private function setConsumerSecret($secret)
	{
		$this->consumerSecret = (string) $secret;
	}


	/**
	 * Set the oAuth-token
	 *
	 * @return	void
	 * @param	string $token	The token to use.
	 */
	public function setOAuthToken($token)
	{
		$this->oAuthToken = (string) $token;
	}


	/**
	 * Set the oAuth-secret
	 *
	 * @return	void
	 * @param	string $secret	The secret to use.
	 */
	public function setOAuthTokenSecret($secret)
	{
		$this->oAuthTokenSecret = (string) $secret;
	}


	/**
	 * Set the timeout
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Twitter/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


	/**
	 * Build the signature for the data
	 *
	 * @return	string
	 * @param	string $key		The key to use for signing.
	 * @param	string $data	The data that has to be signed.
	 */
	private function hmacsha1($key, $data)
	{
		return base64_encode(hash_hmac('SHA1', $data, $key, true));
	}


	/**
	 * URL-encode method for internatl use
	 *
	 * @return	string
	 * @param	mixed $value	The value to encode.
	 */
	private static function urlencode_rfc3986($value)
	{
		if(is_array($value)) return array_map(array('Twitter', 'urlencode_rfc3986'), $value);
		else
		{
			$search = array('+', ' ', '%7E', '%');
			$replace = array('%20', '%20', '~', '%25');

			return str_replace($search, $replace, urlencode($value));
		}
	}


// Timeline resources
	/**
	 * Returns the 20 most recent statuses from non-protected users who have set a custom user icon.
	 * The public timeline is cached for 60 seconds and requesting it more often than that is unproductive and a waste of resources.
	 *
	 * @return	array
	 * @param	bool[optional] $skipUser	When true each tweet returned in a timeline will not contain an entire user object. Instead, the user node will contain only an id element to indicate the numerical ID of the Twitter user that set the status.
	 */
	public function statusesPublicTimeline($skipUser = false)
	{
		// redefine
		$skipUser = (bool) $skipUser;

		// build parameters
		$parameters = array();
		if($skipUser) $parameters['skip_user'] = 'true';

		// make the call
		return (array) $this->doCall('statuses/public_timeline.json', $parameters);
	}


	/**
	 * Returns the 20 most recent statuses posted by the authenticating user and that user's friends. This is the equivalent of /timeline/home on the Web.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 * @param	bool[optional] $skipUser	When true each tweet returned in a timeline will not contain an entire user object. Instead, the user node will contain only an id element to indicate the numerical ID of the Twitter user that set the status.
	 */
	public function statusesHomeTimeline($sinceId = null, $maxId = null, $count = null, $page = null, $skipUser = false)
	{
		// redefine
		$skipUser = (bool) $skipUser;

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;
		if($skipUser) $parameters['skip_user'] = 'true';

		// make the call
		return (array) $this->doCall('statuses/home_timeline.json', $parameters, true);
	}


	/**
	 * Returns the 20 most recent statuses, including retweets, posted by the authenticating user and that user's friends.
	 * This is the equivalent of /timeline/home on the Web.
	 *
	 * Usage note: This home_timeline call is identical to statuses/friends_timeline, except that home_timeline also contains retweets, while statuses/friends_timeline does not for backwards compatibility reasons.
	 * In a future version of the API, statuses/friends_timeline will be deprected and replaced by home_timeline.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 * @param	bool[optional] $skipUser	When true each tweet returned in a timeline will not contain an entire user object. Instead, the user node will contain only an id element to indicate the numerical ID of the Twitter user that set the status.
	 */
	public function statusesFriendsTimeline($sinceId = null, $maxId = null, $count = null, $page = null, $skipUser = false)
	{
		// redefine
		$skipUser = (bool) $skipUser;

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;
		if($skipUser) $parameters['skip_user'] = 'true';

		// make the call
		return (array) $this->doCall('statuses/friends_timeline.json', $parameters, true);
	}


	/**
	 * Returns the 20 most recent statuses posted from the authenticating user. It's also possible to request another user's timeline via the id parameter.
	 * This is the equivalent of the Web / page for your own user, or the profile page for a third party.
	 *
	 * For backwards compatibility reasons, retweets are stripped out of the user_timeline when calling in XML or JSON (they appear with 'RT' in RSS and Atom).
	 * If you'd like them included, you can merge them in from statuses retweeted_by_me.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param	string[optional] $userId		Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $screenName	Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $sinceId		Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId			Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count			Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page				Specifies the page of results to retrieve.
	 * @param	bool[optional] $skipUser		When true each tweet returned in a timeline will not contain an entire user object. Instead, the user node will contain only an id element to indicate the numerical ID of the Twitter user that set the status.
	 */
	public function statusesUserTimeline($id = null, $userId = null, $screenName = null, $sinceId = null, $maxId = null, $count = null, $page = null, $skipUser = false)
	{
		// redefine
		$skipUser = (bool) $skipUser;

		// build parameters
		$parameters = array();
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;
		if($skipUser) $parameters['skip_user'] = 'true';

		// make the call
		return (array) $this->doCall('statuses/user_timeline.json', $parameters, true);
	}


	/**
	 * Returns the 20 most recent mentions (status containing @username) for the authenticating user.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function statusesMentions($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/mentions.json', $parameters);
	}


	/**
	 * Returns the 20 most recent retweets posted by the authenticating user.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function statusesRetweetedByMe($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/retweeted_by_me.json', $parameters);
	}


	/**
	 * Returns the 20 most recent retweets posted by the authenticating user's friends.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function statusesRetweetedToMe($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/retweeted_by_me.json', $parameters);
	}


// Tweets resources
	/**
	 * Returns the 20 most recent tweets of the authenticated user that have been retweeted by others.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function statusesReweetsOfMe($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/retweets_of_me.json', $parameters);
	}


	/**
	 * Returns a single status, specified by the id parameter below. The status's author will be returned inline.
	 *
	 * @return	array
	 * @param	string $id	The numerical ID of the desired status.
	 */
	public function statusesShow($id)
	{
		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return (array) $this->doCall('statuses/show.json', $parameters);
	}


	/**
	 * Updates the authenticating user's status. A status update with text identical to the authenticating user's text identical to the authenticating user's current status will be ignored to prevent duplicates.
	 *
	 * @return	array
	 * @param	string $status							The text of your status update, up to 140 characters. URL encode as necessary.
	 * @param	string[optional] $inReplyToStatusId		The ID of an existing status that the update is in reply to.
	 * @param	float[optional] $lat					The location's latitude that this tweet refers to.
	 * @param	float[optional] $long					The location's longitude that this tweet refers to.
	 * @param	string[optional] $placeId				A place in the world. These IDs can be retrieved from geo/reverse_geocode.
	 * @param	bool[optional] $displayCoordinates		Whether or not to put a pin on the exact coordinates a tweet has been sent from.
	 */
	public function statusesUpdate($status, $inReplyToStatusId = null, $lat = null, $long = null, $placeId = null, $displayCoordinates = false)
	{
		// build parameters
		$parameters['status'] = (string) $status;
		if($inReplyToStatusId != null) $parameters['in_reply_to_status_id'] = (string) $inReplyToStatusId;
		if($lat != null) $parameters['lat'] = (float) $lat;
		if($long != null) $parameters['long'] = (float) $long;
		if($placeId != null) $parameters['place_id'] = (string) $placeId;
		if($displayCoordinates) $parameters['display_coordinates'] = 'true';

		// make the call
		return (array) $this->doCall('statuses/update.json', $parameters, true, 'POST');
	}


	/**
	 * Destroys the status specified by the required ID parameter.
	 * Usage note: The authenticating user must be the author of the specified status.
	 *
	 * @return	bool
	 * @param	string $id	The numerical ID of the desired status.
	 */
	public function statusesDestroy($id)
	{
		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return (array) $this->doCall('statuses/destroy.json', $parameters, true, 'POST');
	}


	/**
	 * Retweets a tweet. Returns the original tweet with retweet details embedded.
	 *
	 * @return	array
	 * @param	string $id	The numerical ID of the desired status.
	 */
	public function statusesRetweet($id)
	{
		// make the call
		return (array) $this->doCall('statuses/retweet/'. $id .'.json', null, true, 'POST');
	}


	/**
	 * Returns up to 100 of the first retweets of a given tweet.
	 *
	 * @return	array
	 * @param	string $id				The numerical ID of the desired status.
	 * @param	int[optional] $count	Specifies the number of records to retrieve. May not be greater than 100.
	 * @param	int[optional] $page		Specifies the page of results to retrieve.
	 */
	public function statusesRetweets($id, $count = null)
	{
		// validate
		if($count != null && $count > 100) throw new TwitterException('Count may not be greater than 100.');

		// build parameters
		$parameters = null;
		if($count != null) $parameters['count'] = (int) $count;

		// make the call
		return (array) $this->doCall('statuses/retweets/'. $id .'.json', $parameters);
	}


	/**
	 * Show user objects of up to 100 members who retweeted the status.
	 *
	 * @return	array
	 * @param	string $id				The numerical ID of the desired status.
	 * @param	int[optional] $count	Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page		Specifies the page of results to retrieve.
	 */
	public function statusesIdRetweetedBy($id, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = null;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/'. (string) $id .'/retweeted_by.json', $parameters, true);
	}


	/**
	 * Show user ids of up to 100 users who retweeted the status.
	 *
	 * @return	array
	 * @param	string $id				The numerical ID of the desired status.
	 * @param	int[optional] $count	Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page		Specifies the page of results to retrieve.
	 */
	public function statusesIdRetweetedByIds($id, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = null;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('statuses/'. (string) $id .'/retweeted_by/ids.json', $parameters, true);
	}


// User resources
	/**
	 * Returns extended information of a given user, specified by ID or screen name as per the required id parameter.
	 * The author's most recent status will be returned inline.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param	string[optional] $userId		Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $screenName	Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 */
	public function usersShow($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('users/show.json', $parameters);
	}


	/**
	 * Return up to 100 users worth of extended information, specified by either ID, screen name, or combination of the two.
	 * The author's most recent status (if the authenticating user has permission) will be returned inline.
	 *
	 * @return	array
	 * @param	mixed[optional] $userIds		A comma separated list of user IDs, up to 100 in total.
	 * @param	mixed[optional] $screenNames	A comma separated list of screen names, up to 100 in total.
	 */
	public function usersLookup($userIds = null, $screenNames = null)
	{
		// redefine
		$userIds = (array) $userIds;
		$screenNames = (array) $screenNames;

		// validate
		if(empty($userIds) && empty($screenNames)) throw new TwitterException('Specify an userId or a screenName.');

		// build parameters
		if(!empty($userIds)) $parameters['user_id'] = implode(',', $userIds);
		if(!empty($screenNames)) $parameters['screen_name'] = implode(',', $screenNames);

		// make the call
		return (array) $this->doCall('users/lookup.json', $parameters, true);

	}


	/**
	 * Run a search for users similar to the Find People button on Twitter.com; the same results returned by people search on Twitter.com will be returned by using this API.
	 * Usage note: It is only possible to retrieve the first 1000 matches from this API.
	 *
	 * @return	array
	 * @param	string $q				The search query term.
	 * @param	int[optional] $perPage	Specifies the number of results to retrieve.
	 * @param	int[optional] $page		Specifies the page of results to retrieve.
	 */
	public function usersSearch($q, $perPage = null, $page = null)
	{
		// build parameters
		$parameters['q'] = (string) $q;
		if($perPage != null) $parameters['per_page'] = (int) $perPage;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('users/search.json', $parameters, true);

	}


	/**
	 * Access to Twitter's suggested user list. This returns the list of suggested user categories. The category can be used in the users/suggestions/category  endpoint to get the users in that category.
	 *
	 * @return	array
	 */
	public function usersSuggestions()
	{
		return (array) $this->doCall('users/suggestions.json', null, true);
	}


	/**
	 * Access the users in a given category of the Twitter suggested user list.
	 * It is recommended that end clients cache this data for no more than one hour.
	 *
	 * @return	array
	 * @param	string $slug	The short name of list or a category.
	 */
	public function usersSuggestionsSlug($slug)
	{
		return (array) $this->doCall('users/suggestions/'. (string) $slug .'.json');
	}


	/**
	 * Returns a user's friends, each with current status inline. They are ordered by the order in which the user followed them, most recently followed first, 100 at a time.
	 * (Please note that the result set isn't guaranteed to be 100 every time as suspended users will be filtered out.)
	 *
	 * Use the cursor option to access older friends.
	 * With no user specified, request defaults to the authenticated user's friends.
	 * It's also possible to request another user's friends list via the id, screen_name or user_id parameter.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param	string[optional] $userId		Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $screenName	Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	int[optional] $cursor			Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function statusesFriends($id = null, $userId = null, $screenName = null, $cursor = null)
	{
		// build parameters
		$parameters = array();
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		if($cursor != null) $parameters['cursor'] = (int) $cursor;

		// make the call
		return (array) $this->doCall('statuses/friends.json', $parameters);
	}


	/**
	 * Returns the authenticating user's followers, each with current status inline. They are ordered by the order in which they followed the user, 100 at a time. (Please note that the result set isn't guaranteed to be 100 every time as suspended users will be filtered out.)
	 * Use the cursor parameter to access earlier followers.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param	string[optional] $userId		Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $screenName	Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	int[optional] $cursor			Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function statusesFollowers($id = null, $userId = null, $screenName = null, $cursor = null)
	{
		// build parameters
		$parameters = array();
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		if($cursor != null) $parameters['cursor'] = (int) $cursor;

		// make the call
		return (array) $this->doCall('statuses/followers.json', $parameters);
	}


// Trends resources
	/**
	 * Returns the top ten topics that are currently trending on Twitter.
	 * The response includes the time of the request, the name of each trend, and the url to the Twitter Search results page for that topic.
	 *
	 * @return	array
	 */
	public function trends()
	{
		return (array) $this->doCall('trends.json');
	}


	/**
	 * Returns the current top 10 trending topics on Twitter.
	 * The response includes the time of the request, the name of each trending topic, and query used on Twitter Search results page for that topic.
	 *
	 * @return	array
	 * @param	string[optional] $exclude	Setting this equal to hashtags will remove all hashtags from the trends list.
	 */
	public function trendsCurrent($exclude = null)
	{
		// build parameters
		$parameters = array();
		if($exclude != null) $parameters['exclude'] = (string) $exclude;

		// make the call
		return (array) $this->doCall('trends/current.json', $parameters);
	}


	/**
	 * Returns the top 20 trending topics for each hour in a given day.
	 *
	 * @return	array
	 * @param	string[optional] $exclude	Setting this equal to hashtags will remove all hashtags from the trends list.
	 * @param	string[optional] $date		Permits specifying a start date for the report. The date should be formatted YYYY-MM-DD.
	 */
	public function trendsDaily($exclude = null, $date = null)
	{
		// build parameters
		$parameters = array();
		if($exclude != null) $parameters['exclude'] = (string) $exclude;
		if($date != null) $parameters['date'] = (string) $date;

		// make the call
		return (array) $this->doCall('trends/daily.json', $parameters);
	}


	/**
	 * Returns the top 30 trending topics for each day in a given week.
	 *
	 * @return	array
	 * @param	string[optional] $exclude	Setting this equal to hashtags will remove all hashtags from the trends list.
	 * @param	string[optional] $date		Permits specifying a start date for the report. The date should be formatted YYYY-MM-DD.
	 */
	public function trendsWeekly($exclude = null, $date = null)
	{
		// build parameters
		$parameters = array();
		if($exclude != null) $parameters['exclude'] = (string) $exclude;
		if($date != null) $parameters['date'] = (string) $date;

		// make the call
		return (array) $this->doCall('trends/weekly.json', $parameters);
	}


// List resources
	/**
	 * Creates a new list for the authenticated user. Accounts are limited to 20 lists.
	 *
	 * @return	array
	 * @param	string $user					The user.
	 * @param	string $name					The name of the list you are creating.
	 * @param	string[optional] $mode			Whether your list is public or private. Values can be public or private. Lists are public by default if no mode is specified.
	 * @param	string[optional] $description	The description of the list you are creating.
	 */
	public function userListsCreate($user, $name, $mode = null, $description = null)
	{
		// possible modes
		$allowedModes = array('public', 'private');

		// validate
		if($mode != null && !in_array($mode, $allowedModes)) throw new TwitterException('Invalid mode (), possible values are: '. implode($allowedModes) .'.');

		// build parameters
		$parameters['name'] = (string) $name;
		if($mode != null) $parameters['mode'] = (string) $mode;
		if($description != null) $parameters['description'] = (string) $description;

		// make the call
		return (array) $this->doCall((string) $user .'/lists.json', $parameters, true, 'POST');
	}


	/**
	 * List the lists of the specified user. Private lists will be included if the authenticated users is the same as the user who's lists are being returned.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function userLists($user, $cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall((string) $user .'/lists.json', $parameters, true);
	}


	/**
	 * Show the specified list. Private lists will only be shown if the authenticated user owns the specified list.
	 *
	 * @return	array
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 */
	public function userListsId($user, $id)
	{
		// make the call
		return (array) $this->doCall((string) $user .'/lists/'. (string) $id .'.json', null, true);
	}


	/**
	 * Updates the specified list.
	 *
	 * @return	array
	 * @param	string $user					The user.
	 * @param	string $id						The id of the list.
	 * @param	string[optional] $name			The name of the list you are creating.
	 * @param	string[optional] $mode			Whether your list is public or private. Values can be public or private. Lists are public by default if no mode is specified.
	 * @param	string[optional] $description	The description of the list you are creating.
	 */
	public function userListsIdUpdate($user, $id, $name = null, $mode = null, $description = null)
	{
		// possible modes
		$allowedModes = array('public', 'private');

		// validate
		if($mode != null && !in_array($mode, $allowedModes)) throw new TwitterException('Invalid mode (), possible values are: '. implode($allowedModes) .'.');

		// build parameters
		if($name != null) $parameters['name'] = (string) $name;
		if($mode != null) $parameters['mode'] = (string) $mode;
		if($description != null) $parameters['description'] = (string) $description;

		// make the call
		return (array) $this->doCall((string) $user .'/lists/'. (string) $id .'.json', $parameters, true, 'POST');
	}


	/**
	 * Show tweet timeline for members of the specified list.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string $id					The id of the list.
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function userListsIdStatuses($user, $id, $sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['per_page'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall((string) $user .'/lists/'. (string) $id .'/statuses.json', $parameters);
	}


	/**
	 * List the lists the specified user has been added to.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function userListsMemberships($user, $cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall((string) $user .'/lists/memberships.json', $parameters, true);
	}


	/**
	 * List the lists the specified user follows.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function userListsSubscriptions($user, $cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall((string) $user .'/lists/subscriptions.json', $parameters, true);
	}


// List Members resources
	/**
	 * Returns the members of the specified list.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string $id					The id of the list.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function userListMembers($user, $id, $cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/members.json', $parameters, true);
	}


	/**
	 * Add a member to a list. The authenticated user must own the list to be able to add members to it. Lists are limited to having 500 members.
	 *
	 * @return	array
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 * @param	string $userId	The id or screen name of the user to add as a member of the list.
	 */
	public function userListMembersCreate($user, $id, $userId)
	{
		// build parameters
		$parameters['id'] = (string) $userId;

		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/members.json', $parameters, true, 'POST');
	}


	/**
	 * Removes the specified member from the list. The authenticated user must be the list's owner to remove members from the list.
	 *
	 * @return	mixed
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 * @param	string $userId	Specfies the ID of the user for whom to return results for.
	 */
	public function userListMembersDelete($user, $id, $userId)
	{
		// build parameters
		$parameters['id'] = (string) $userId;
		$parameters['_method'] = 'DELETE';

		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/members.json', $parameters, true, 'POST');
	}


	/**
	 * Check if a user is a member of the specified list.
	 *
	 * @return	mixed
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 * @param	string $userId	Specfies the ID of the user for whom to return results for.
	 */
	public function userListMembersId($user, $id, $userId)
	{
		try
		{
			// make the call
			return (array) $this->doCall((string) $user .'/'. (string) $id .'/members/'. (string) $userId .'.json', null, true);
		}

		// catch exceptions
		catch(TwitterException $e)
		{
			if($e->getMessage() == 'The specified user is not a member of this list') return false;
			else throw $e;
		}
	}


// List Subscribers resources
	/**
	 * Returns the subscribers of the specified list.
	 *
	 * @return	array
	 * @param	string $user				The user.
	 * @param	string $id					The id of the list.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function userListSubscribers($user, $id, $cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/subscribers.json', $parameters, true);
	}


	/**
	 * Make the authenticated user follow the specified list.
	 *
	 * @return	array
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 */
	public function userListSubscribersCreate($user, $id)
	{
		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/subscribers.json', null, true, 'POST');
	}


	/**
	 * Unsubscribes the authenticated user form the specified list.
	 *
	 * @return	array
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 */
	public function userListSubscribersDelete($user, $id)
	{
		// build parameters
		$parameters['_method'] = 'DELETE';

		// make the call
		return (array) $this->doCall((string) $user .'/'. (string) $id .'/subscribers.json', $parameters, true, 'POST');
	}


	/**
	 * Check if the specified user is a subscriber of the specified list.
	 *
	 * @return	mixed
	 * @param	string $user	The user.
	 * @param	string $id		The id of the list.
	 * @param	string $userId	Specfies the ID of the user for whom to return results for.
	 */
	public function userListSubscribersId($user, $id, $userId)
	{
		try
		{
			// make the call
			return (array) $this->doCall((string) $user .'/'. (string) $id .'/subscribers/'. (string) $userId .'.json', null, true);
		}

		// catch exceptions
		catch(TwitterException $e)
		{
			if($e->getMessage() == 'The specified user is not a subscriber of this list') return false;
			else throw $e;
		}

	}


// Direct Messages resources
	/**
	 * Returns a list of the 20 most recent direct messages sent to the authenticating user.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function directMessages($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('direct_messages.json', $parameters, true);
	}


	/**
	 * Returns a list of the 20 most recent direct messages sent by the authenticating user.
	 *
	 * @return	array
	 * @param	string[optional] $sinceId	Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * @param	string[optional] $maxId		Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * @param	int[optional] $count		Specifies the number of records to retrieve. May not be greater than 200.
	 * @param	int[optional] $page			Specifies the page of results to retrieve.
	 */
	public function directMessagesSent($sinceId = null, $maxId = null, $count = null, $page = null)
	{
		// validate
		if($count != null && $count > 200) throw new TwitterException('Count may not be greater than 200.');

		// build parameters
		$parameters = array();
		if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
		if($maxId != null) $parameters['max_id'] = (string) $maxId;
		if($count != null) $parameters['count'] = (int) $count;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('direct_messages/sent.json', $parameters, true);
	}


	/**
	 * Sends a new direct message to the specified user from the authenticating user.
	 * Requires both the user and text parameters. Returns the sent message in the requested format when successful.
	 *
	 * @return	array
	 * @param	string $text					The text of your direct message. Be sure to URL encode as necessary, and keep it under 140 characters.
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function directMessagesNew($text, $id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		$parameters['text'] = (string) $text;
		if($id != null) $parameters['user'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('direct_messages/new.json', $parameters, true, 'POST');
	}


	/**
	 * Destroys the direct message specified in the required ID parameter. The authenticating user must be the recipient of the specified direct message.
	 *
	 * @return	array
	 * @param	string $id	The ID of the desired direct message.
	 */
	public function directMessagesDestroy($id)
	{
		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return (array) $this->doCall('direct_messages/destroy.json', $parameters, true, 'POST');
	}


// Friendship resources
	/**
	 * Allows the authenticating users to follow the user specified in the ID parameter.
	 * Returns the befriended user in the requested format when successful.
	 * Returns a string describing the failure condition when unsuccessful.
	 *
	 * @return	mixed
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	bool[optional] $follow			Returns public statuses that reference the given set of users.
	 */
	public function friendshipsCreate($id = null, $userId = null, $screenName = null, $follow = false)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		$parameters['follow'] = ($follow) ? 'true' : 'false';

		// make the call
		return (array) $this->doCall('friendships/create.json', $parameters, true, 'POST');
	}


	/**
	 * Allows the authenticating users to unfollow the user specified in the ID parameter.
	 * Returns the unfollowed user in the requested format when successful. Returns a string describing the failure condition when unsuccessful.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function friendshipsDestroy($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('friendships/destroy.json', $parameters, true, 'POST');
	}


	/**
	 * Tests for the existence of friendship between two users. Will return true if user_a follows user_b, otherwise will return false.
	 *
	 * @return	bool
	 * @param	string $userA	The ID or screen_name of the subject user.
	 * @param	string $userB	The ID or screen_name of the user to test for following.
	 */
	public function friendshipsExists($userA, $userB)
	{
		// build parameters
		$parameters['user_a'] = (string) $userA;
		$parameters['user_b'] = (string) $userB;

		// make the call
		return (bool) $this->doCall('friendships/exists.json', $parameters);
	}


	/**
	 * Returns detailed information about the relationship between two users.
	 *
	 * @return	array
	 * @param 	string[optional] $sourceId				The user_id of the subject user.
	 * @param 	string[optional] $sourceScreenName		The screen_name of the subject user.
	 * @param 	string[optional] $targetId				The screen_name of the subject user.
	 * @param 	string[optional] $targetScreenName		The screen_name of the target user.
	 */
	public function friendshipsShow($sourceId = null, $sourceScreenName = null, $targetId = null, $targetScreenName = null)
	{
		// validate
		if($sourceId == '' && $sourceScreenName == '') throw new TwitterException('Specify an sourceId or a sourceScreenName.');
		if($targetId == '' && $targetScreenName == '') throw new TwitterException('Specify an targetId or a targetScreenName.');

		// build parameters
		if($sourceId != null) $parameters['source_id'] = (string) $sourceId;
		if($sourceScreenName != null) $parameters['source_screen_name'] = (string) $sourceScreenName;
		if($targetId != null) $parameters['target_id'] = (string) $targetId;
		if($targetScreenName != null) $parameters['target_screen_name'] = (string) $targetScreenName;

		// make the call
		return (array) $this->doCall('friendships/show.json', $parameters);
	}


	/**
	 * Returns an array of numeric IDs for every user who has a pending request to follow the authenticating user.
	 *
	 * @return	array
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function friendshipsIncoming($cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall('friendships/incoming.json', $parameters, true);
	}


	/**
	 * Returns an array of numeric IDs for every protected user for whom the authenticating user has a pending follow request.
	 *
	 * @return	array
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function friendshipsOutgoing($cursor = null)
	{
		$parameters = null;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall('friendships/outgoing.json', $parameters, true);
	}


// Friends and Followers resources
	/**
	 * Returns an array of numeric IDs for every user the specified user is following.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function friendsIds($id = null, $userId = null, $screenName = null, $cursor = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall('friends/ids.json', $parameters);
	}


	/**
	 * Returns an array of numeric IDs for every user following the specified user.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 * @param	string[optional] $cursor	Breaks the results into pages. This is recommended for users who are following many users. Provide a value of -1  to begin paging. Provide values as returned to in the response body's next_cursor  and previous_cursor attributes to page back and forth in the list.
	 */
	public function followersIds($id = null, $userId = null, $screenName = null, $cursor = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;
		if($cursor != null) $parameters['cursor'] = (string) $cursor;

		// make the call
		return (array) $this->doCall('followers/ids.json', $parameters);
	}


// Account resources
	/**
	 * Returns an HTTP 200 OK response code and a representation of the requesting user if authentication was successful; returns a 401 status code and an error message if not. Use this method to test if supplied user credentials are valid.
	 *
	 * @return	array
	 */
	public function accountVerifyCredentials()
	{
		// make the call
		return (array) $this->doCall('account/verify_credentials.json', null, true);
	}


	/**
	 *
	 * @return	array
	 */
	public function accountRateLimitStatus()
	{
		// make the call
		return (array) $this->doCall('account/rate_limit_status.json', null);
	}


	/**
	 * Ends the session of the authenticating user, returning a null cookie. Use this method to sign users out of client-facing applications like widgets.
	 *
	 * @return	bool
	 */
	public function accountEndSession()
	{
		try
		{
			// make the call
			$this->doCall('account/end_session.json', null, true, 'POST');
		}

		// catch exceptions
		catch(TwitterException $e)
		{
			if($e->getMessage() == 'Logged out.') return true;
			else throw $e;
		}
	}


	/**
	 * Sets which device Twitter delivers updates to for the authenticating user. Sending none as the device parameter will disable IM or SMS updates.
	 *
	 * @return	array
	 * @param	string $device	Delivery device type to send updates to.
	 */
	public function accountUpdateDeliveryDevices($device)
	{
		// build parameters
		$parameters['device'] = (string) $device;

		// make the call
		return (array) $this->doCall('account/update_delivery_device.json', $parameters, true, 'POST');
	}


	/**
	 * Sets one or more hex values that control the color scheme of the authenticating user's profile page on twitter.com.
	 * Each parameter's value must be a valid hexidecimal value, and may be either three or six characters (ex: #fff or #ffffff).
	 *
	 * @return	array
	 * @param	string[optional] $profileBackgroundColor		Profile background color.
	 * @param	string[optional] $profileTextColor				Profile text color.
	 * @param	string[optional] $profileLinkColor				Profile link color.
	 * @param	string[optional] $profileSidebarFillColor		Profile sidebar's background color.
	 * @param	string[optional] $profileSidebarBorderColor		Profile sidebar's border color.
	 */
	public function accountUpdateProfileColors($profileBackgroundColor = null, $profileTextColor = null, $profileLinkColor = null, $profileSidebarFillColor = null, $profileSidebarBorderColor = null)
	{
		// validate
		if($profileBackgroundColor == '' && $profileTextColor == '' && $profileLinkColor == '' && $profileSidebarFillColor == '' && $profileSidebarBorderColor == '') throw new TwitterException('Specify a profileBackgroundColor, profileTextColor, profileLinkColor, profileSidebarFillColor or a profileSidebarBorderColor.');

		// build parameters
		if($profileBackgroundColor != null) $parameters['profile_background_color'] = (string) $profileBackgroundColor;
		if($profileTextColor != null) $parameters['profile_text_color'] = (string) $profileTextColor;
		if($profileLinkColor != null) $parameters['profile_link_color'] = (string) $profileLinkColor;
		if($profileSidebarFillColor != null) $parameters['profile_sidebar_fill_color'] = (string) $profileSidebarFillColor;
		if($profileSidebarBorderColor != null) $parameters['profile_sidebar_border_color'] = (string) $profileSidebarBorderColor;

		// make the call
		return (array) $this->doCall('account/update_profile_colors.json', $parameters, true, 'POST');
	}


	/**
	 * Updates the authenticating user's profile image.
	 *
	 * @return	array
	 * @param	string $image	The path to the avatar image for the profile. Must be a valid GIF, JPG, or PNG image of less than 700 kilobytes in size. Images with width larger than 500 pixels will be scaled down.
	 */
	public function accountUpdateProfileImage($image)
	{
		throw new TwitterException('Not implemented');

		// validate
		if(!file_exists($image)) throw new TwitterException('Image ('. $image .') doesn\'t exists.');

		// make the call
		return (array) $this->doCall('account/update_profile_image.json', null, true, 'POST', $image);
	}


	/**
	 * Updates the authenticating user's profile background image.
	 *
	 * @return	array
	 * @param	string $image	The path to the background image for the profile. Must be a valid GIF, JPG, or PNG image of less than 800 kilobytes in size. Images with width larger than 2048 pixels will be forceably scaled down.
	 * @param	bool $tile		Whether or not to tile the background image. If set to true the background image will be displayed tiled. The image will not be tiled otherwise.
	 */
	public function accountUpdateProfileBackgroundImage($image, $tile = false)
	{
		throw new TwitterException('Not implemented');

		// validate
		if(!file_exists($image)) throw new TwitterException('Image ('. $image .') doesn\'t exists.');

		// build parameters
		if($tile) $parameters['tile'] = 'true';

		// make the call
		return (array) $this->doCall('account/update_profile_background_image.json', $parameters, true, 'POST', $image);
	}


	/**
	 * Sets values that users are able to set under the "Account" tab of their settings page. Only the parameters specified will be updated.
	 *
	 * @return	array
	 * @param	string[optional] $name			Full name associated with the profile. Maximum of 20 characters.
	 * @param	string[optional] $url			URL associated with the profile. Will be prepended with "http://" if not present. Maximum of 100 characters.
	 * @param	string[optional] $location		The city or country describing where the user of the account is located. The contents are not normalized or geocoded in any way. Maximum of 30 characters.
	 * @param	string[optional] $description	A description of the user owning the account. Maximum of 160 characters.
	 */
	public function accountUpdateProfile($name = null, $url = null, $location = null, $description = null)
	{
		// build parameters
		$parameters = null;
		if($name != null) $parameters['name'] = (string) $name;
		if($url != null) $parameters['url'] = (string) $url;
		if($location != null) $parameters['location'] = (string) $location;
		if($description != null) $parameters['description'] = (string) $description;

		// make the call
		return (array) $this->doCall('account/update_profile.json', $parameters, true, 'POST');
	}


// Favorites resources
	/**
	 * Returns the 20 most recent favorite statuses for the authenticating user or user specified by the ID parameter in the requested format.
	 *
	 * @return	array
	 * @param	string[optional] $id	Specifies the ID or screen name of the user for whom to return results for.
	 * @param	int[optional] $page		Specifies the page of results to retrieve.
	 */
	public function favorites($id = null, $page = null)
	{
		// build parameters
		$parameters = null;
		if($id != null) $parameters['id'] = (string) $id;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('favorites.json', $parameters, true);
	}


	/**
	 * Favorites the status specified in the ID parameter as the authenticating user. Returns the favorite status when successful.
	 *
	 * @return	array
	 * @param	string $id	The numerical ID of the desired status.
	 */
	public function favoritesCreate($id)
	{
		// make the call
		return (array) $this->doCall('favorites/create/'. $id .'.json', null, true, 'POST');
	}


	/**
	 * Un-favorites the status specified in the ID parameter as the authenticating user. Returns the un-favorited status in the requested format when successful.
	 *
	 * @return	array
	 * @param	string $id	The numerical ID of the desired status.
	 */
	public function favoritesDestroy($id)
	{
		// make the call
		return (array) $this->doCall('favorites/destroy/'. $id .'.json', null, true, 'POST');
	}


// Notification resources
	/**
	 * Enables device notifications for updates from the specified user. Returns the specified user when successful.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function notificationsFollow($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('notifications/follow.json', $parameters, true, 'POST');
	}


	/**
	 * Disables notifications for updates from the specified user to the authenticating user. Returns the specified user when successful.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function notificationsLeave($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('notifications/leave.json', $parameters, true, 'POST');
	}


// Block resources
	/**
	 * Blocks the user specified in the ID parameter as the authenticating user. Destroys a friendship to the blocked user if it exists. Returns the blocked user in the requested format when successful.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function blocksCreate($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('blocks/create.json', $parameters, true, 'POST');
	}


	/**
	 * Un-blocks the user specified in the ID parameter for the authenticating user. Returns the un-blocked user in the requested format when successful.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function blocksDestroy($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('blocks/destroy.json', $parameters, true, 'POST');
	}


	/**
	 * Un-blocks the user specified in the ID parameter for the authenticating user. Returns the un-blocked user in the requested format when successful.
	 *
	 * @return	mixed
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function blocksExists($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		try
		{
			// make the call
			return (array) $this->doCall('blocks/exists.json', $parameters, true);
		}
		// catch exceptions
		catch(TwitterException $e)
		{
			if($e->getMessage() == 'You are not blocking this user.') return false;
			else throw $e;
		}
	}


	/**
	 * Returns an array of user objects that the authenticating user is blocking.
	 *
	 * @return	array
	 * @param	int[optional] $page		Specifies the page of results to retrieve. Note: there are pagination limits. See the FAQ for details.
	 */
	public function blocksBlocking($page = null)
	{
		// build parameters
		$parameters = null;
		if($page != null) $parameters['page'] = (int) $page;

		// make the call
		return (array) $this->doCall('blocks/blocking.json', $parameters, true);
	}


	/**
	 * Returns an array of numeric user ids the authenticating user is blocking.
	 *
	 * @return	array
	 */
	public function blocksBlockingIds()
	{
		// make the call
		return (array) $this->doCall('blocks/blocking/ids.json', null, true);
	}


// Spam Reporting resources
	/**
	 * The user specified in the id is blocked by the authenticated user and reported as a spammer.
	 *
	 * @return	array
	 * @param	string[optional] $id			Specifies the ID or screen name of the user for whom to return results for.
	 * @param 	string[optional] $userId		Specfies the screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
	 * @param	string[optional] $screenName	Specfies the ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
	 */
	public function reportSpam($id = null, $userId = null, $screenName = null)
	{
		// validate
		if($id == '' && $userId == '' && $screenName == '') throw new TwitterException('Specify an id or an userId or a screenName.');

		// build parameters
		if($id != null) $parameters['id'] = (string) $id;
		if($userId != null) $parameters['user_id'] = (string) $userId;
		if($screenName != null) $parameters['screen_name'] = (string) $screenName;

		// make the call
		return (array) $this->doCall('report_spam.json', $parameters, true, 'POST');
	}


// Saved Searches resources
	/**
	 * Returns the authenticated user's saved search queries.
	 *
	 * @return	array
	 */
	public function savedSearches()
	{
		// make the call
		return (array) $this->doCall('saved_searches.json', null, true);
	}


	/**
	 * Retrieve the data for a saved search owned by the authenticating user specified by the given id.
	 *
	 * @return	array
	 * @param	string $id	The ID of the desired saved search.
	 */
	public function savedSearchesShow($id)
	{
		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return (array) $this->doCall('saved_searches/show.json', $parameters, true);
	}


	/**
	 * Creates a saved search for the authenticated user.
	 *
	 * @return	array
	 * @param	string $query	The query of the search the user would like to save.
	 */
	public function savedSearchesCreate($query)
	{
		// build parameters
		$parameters['query'] = (string) $query;

		// make the call
		return (array) $this->doCall('saved_searches/create.json', $parameters, true, 'POST');
	}


	/**
	 * Destroys a saved search for the authenticated user. The search specified by id must be owned by the authenticating user.
	 * REMARK: This method seems not to work	@later
	 *
	 * @return	array
	 * @param	string $id	The ID of the desired saved search.
	 */
	public function savedSearchesDestroy($id)
	{
		return (array) $this->doCall('saved_searches/destroy/'. (string) $id .'.json', null, true, 'POST');
	}


// OAuth resources
	/**
	 * Allows a Consumer application to obtain an OAuth Request Token to request user authorization.
	 * This method fulfills Secion 6.1 of the OAuth 1.0 authentication flow.
	 *
	 * @return	array					An array containg the token and the secret
	 * @param	string $callbackURL
	 */
	public function oAuthRequestToken($callbackURL = null)
	{
		// init var
		$parameters = array();

		// set callback
		if($callbackURL != null) $parameters['oauth_callback'] = (string) $callbackURL;

		// make the call
		$response = $this->doOAuthCall('request_token', $parameters);

		// validate
		if(!isset($response['oauth_token'], $response['oauth_token_secret'])) throw new TwitterException('oAuthRequestToken: ' . implode(', ', $response));

		// set some properties
		if(isset($response['oauth_token'])) $this->setOAuthToken($response['oauth_token']);
		if(isset($response['oauth_token_secret'])) $this->setOAuthTokenSecret($response['oauth_token_secret']);

		// return
		return $response;
	}


	/**
	 * Allows a Consumer application to exchange the OAuth Request Token for an OAuth Access Token.
	 * This method fulfills Secion 6.3 of the OAuth 1.0 authentication flow.
	 *
	 * @return	array
	 * @param	string $token
	 * @param	string $verifier
	 */
	public function oAuthAccessToken($token, $verifier)
	{
		// init var
		$parameters = array();
		$parameters['oauth_token'] = (string) $token;
		$parameters['oauth_verifier'] = (string) $verifier;

		// make the call
		$response = $this->doOAuthCall('access_token', $parameters);

		// set some properties
		if(isset($response['oauth_token'])) $this->setOAuthToken($response['oauth_token']);
		if(isset($response['oauth_token_secret'])) $this->setOAuthTokenSecret($response['oauth_token_secret']);

		// return
		return $response;
	}


	/**
	 * Will redirect to the page to authorize the applicatione
	 *
	 * @return	void
	 */
	public function oAuthAuthorize()
	{
		header('Location: '. self::SECURE_API_URL .'/oauth/authorize?oauth_token='. $this->oAuthToken);
	}


	/**
	 * Allows a Consumer application to use an OAuth request_token to request user authorization. This method is a replacement fulfills Secion 6.2 of the OAuth 1.0 authentication flow for applications using the Sign in with Twitter authentication flow. The method will use the currently logged in user as the account to for access authorization unless the force_login parameter is set to true
	 * REMARK: This method seems not to work	@later
	 *
	 * @return	void
	 */
	public function oAuthAuthenticate()
	{
		// make the call
		return $this->doOAuthCall('authenticate');
	}


// Local Trends resources
	/**
	 * Returns the locations that Twitter has trending topic information for.
	 * The response is an array of "locations" that encode the location's WOEID (a Yahoo! Where On Earth ID) and some other human-readable information such as a canonical name and country the location belongs in.
	 * The WOEID that is returned in the location object is to be used when querying for a specific trend.
	 *
	 * @return	array
	 * @param	float[optional] $lat	If passed in conjunction with long, then the available trend locations will be sorted by distance to the lat  and long passed in. The sort is nearest to furthest.
	 * @param	float[optional] $long	If passed in conjunction with lat, then the available trend locations will be sorted by distance to the lat  and long passed in. The sort is nearest to furthest.
	 */
	public function trendsAvailable($lat = null, $long = null)
	{
		// build parameters
		$parameters = null;
		if($lat != null) $parameters['lat_for_trends'] = (float) $lat;
		if($long != null) $parameters['long_for_trends'] = (float) $long;

		// make the call
		return (array) $this->doCall('trends/available.json', $parameters);
	}


	/**
	 * Returns the top 10 trending topics for a specific location Twitter has trending topic information for.
	 * The response is an array of "trend" objects that encode the name of the trending topic, the query parameter that can be used to search for the topic on Search, and the direct URL that can be issued against Search.
	 * This information is cached for five minutes, and therefore users are discouraged from querying these endpoints faster than once every five minutes. Global trends information is also available from this API by using a WOEID of 1.
	 * REMARK: This method seems not to work	@later
	 *
	 * @return	array
	 * @param	string $woeid	The WOEID of the location to be querying for.
	 */
	public function trendsLocation($woeid)
	{
		// make the call
		return (array) $this->doCall('trends/location/'. (string) $woeid .'.json');
	}


// Geo resources
	/**
	 * Search for places (cities and neighborhoods) that can be attached to a statuses/update. Given a latitude and a longitude, return a list of all the valid places that can be used as a place_id when updating a status.
	 * Conceptually, a query can be made from the user's location, retrieve a list of places, have the user validate the location he or she is at, and then send the ID of this location up with a call to statuses/update.
	 * There are multiple granularities of places that can be returned -- "neighborhoods", "cities", etc. At this time, only United States data is available through this method.
	 * This API call is meant to be an informative call and will deliver generalized results about geography.
	 *
	 * @return	array
	 * @param	float $lat						The location's latitude that this tweet refers to.
	 * @param	float $long						The location's longitude that this tweet refers to.
	 * @param	string[optional] $accuracy		A hint on the "region" in which to search. If a number, then this is a radius in meters, but it can also take a string that is suffixed with ft to specify feet. If this is not passed in, then it is assumed to be 0m. If coming from a device, in practice, this value is whatever accuracy the device has measuring its location (whether it be coming from a GPS, WiFi triangulation, etc.).
	 * @param	string[optional] $granularity	The minimal granularity of data to return. If this is not passed in, then neighborhood is assumed. city can also be passed.
	 * @param	int[optional] $maxResults		A hint as to the number of results to return. This does not guarantee that the number of results returned will equal max_results, but instead informs how many "nearby" results to return. Ideally, only pass in the number of places you intend to display to the user here.
	 */
	public function geoReverseGeoCode($lat, $long, $accuracy = null, $granularity = null, $maxResults = null)
	{
		// build parameters
		$parameters['lat'] = (float) $lat;
		$parameters['long'] = (float) $long;
		if($accuracy != null) $parameters['accuracy'] = (string) $accuracy;
		if($granularity != null) $parameters['granularity'] = (string) $granularity;
		if($maxResults != null) $parameters['max_results'] = (int) $maxResults;

		// make the call
		return (array) $this->doCall('geo/reverse_geocode.json', $parameters);
	}


	/**
	 * Find out more details of a place that was returned from the geo/reverse_geocode method.
	 *
	 * @return	array
	 * @param	string $id
	 * @param	string[optional] $placeId	A place in the world. These IDs can be retrieved from geo/reverse_geocode.
	 */
	public function geoId($id, $placeId = null)
	{
		// build parameters
		$parameters = null;
		if($placeId != null) $parameters['place_id'] = (string) $placeId;

		// make the call
		return (array) $this->doCall('geo/id/'. (string) $id .'.json', $parameters);
	}


// Help resources
	/**
	 * Test
	 * REMARK: this methods seems not to work, so don't rely on it
	 *
	 * @return	bool
	 */
	public function helpTest()
	{
		// make the call
		return ($this->doCall('help/test', null, null, 'GET', null, false) == 'ok');
	}
}


/**
 * Twitter Exception class
 *
 * @author	Tijs Verkoyen <php-twitter@verkoyen.eu>
 */
class TwitterException extends Exception
{
}

?>