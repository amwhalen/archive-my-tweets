<?php
namespace TijsVerkoyen\Twitter;

/**
 * Twitter class
 *
 * @author		Tijs Verkoyen <php-twitter@verkoyen.eu>
 * @version		2.3.1
 * @copyright	Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license		BSD License
 */
class Twitter
{
    // internal constant to enable/disable debugging
    const DEBUG = false;

    // url for the twitter-api
    const API_URL = 'https://api.twitter.com/1.1';
    const SECURE_API_URL = 'https://api.twitter.com';

    // port for the twitter-api
    const API_PORT = 443;
    const SECURE_API_PORT = 443;

    // current version
    const VERSION = '2.3.1';

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
    private $timeOut = 10;

    /**
     * The user agent
     *
     * @var	string
     */
    private $userAgent;

    /**
     * The rate limit status for the last executed call.
     *
     * @var array
     */
    private $lastRateLimitStatus;

// class methods
    /**
     * Default constructor
     *
     * @param string $consumerKey    The consumer key to use.
     * @param string $consumerSecret The consumer secret to use.
     */
    public function __construct($consumerKey, $consumerSecret)
    {
        $this->setConsumerKey($consumerKey);
        $this->setConsumerSecret($consumerSecret);
    }

    /**
     * Default destructor
     */
    public function __destruct()
    {
        if($this->curl != null) curl_close($this->curl);
    }

    /**
     * Format the parameters as a querystring
     *
     * @param  array  $parameters The parameters.
     * @return string
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
        foreach ($parameters as $key => $value) {
            // sort by value
            if(is_array($value)) $parameters[$key] = natsort($value);
        }

        // process parameters
        foreach ($parameters as $key => $value) {
            $chunks[] = $key . '=' . str_replace('%25', '%', $value);
        }

        // return
        return implode('&', $chunks);
    }

    /**
     * All OAuth 1.0 requests use the same basic algorithm for creating a
     * signature base string and a signature. The signature base string is
     * composed of the HTTP method being used, followed by an ampersand ("&")
     * and then the URL-encoded base URL being accessed, complete with path
     * (but not query parameters), followed by an ampersand ("&"). Then, you
     * take all query parameters and POST body parameters (when the POST body is
     * of the URL-encoded type, otherwise the POST body is ignored), including
     * the OAuth parameters necessary for negotiation with the request at hand,
     * and sort them in lexicographical order by first parameter name and then
     * parameter value (for duplicate parameters), all the while ensuring that
     * both the key and the value for each parameter are URL encoded in
     * isolation. Instead of using the equals ("=") sign to mark the key/value
     * relationship, you use the URL-encoded form of "%3D". Each parameter is
     * then joined by the URL-escaped ampersand sign, "%26".
     *
     * @param  string $url        The URL.
     * @param  string $method     The method to use.
     * @param  array  $parameters The parameters.
     * @return string
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
        foreach ($parameters as $key => $value) {
            // sort by value
            if(is_array($value)) $parameters[$key] = natsort($value);
        }

        // process queries
        foreach ($parameters as $key => $value) {
            // only add if not already in the url
            if (substr_count($url, $key . '=' . $value) == 0) {
                $chunks[] = self::urlencode_rfc3986($key) . '%3D' .
                            self::urlencode_rfc3986($value);
            }
        }

        // buils base
        $base = $method . '&';
        $base .= urlencode($url);
        $base .= (substr_count($url, '?')) ? '%26' : '&';
        $base .= implode('%26', $chunks);
        $base = str_replace('%3F', '&', $base);

        // return
        return $base;
    }

    /**
     * Build the Authorization header
     * @later: fix me
     *
     * @param  array  $parameters The parameters.
     * @param  string $url        The URL.
     * @return string
     */
    private function calculateHeader(array $parameters, $url)
    {
        // redefine
        $url = (string) $url;

        // divide into parts
        $parts = parse_url($url);

        // init var
        $chunks = array();

        // process queries
        foreach ($parameters as $key => $value) {
            $chunks[] = str_replace(
                '%25', '%',
                self::urlencode_rfc3986($key) . '="' . self::urlencode_rfc3986($value) . '"'
            );
        }

        // build return
        $return = 'Authorization: OAuth realm="' . $parts['scheme'] . '://' .
                  $parts['host'] . $parts['path'] . '", ';
        $return .= implode(',', $chunks);

        // prepend name and OAuth part
        return $return;
    }

    /**
     * Make an call to the oAuth
     * @todo	refactor me
     *
     * @param  string          $method     The method.
     * @param  array[optional] $parameters The parameters.
     * @return array
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
        $base = $this->calculateBaseString(
            self::SECURE_API_URL . '/oauth/' . $method, 'POST', $parameters
        );

        // add sign into the parameters
        $parameters['oauth_signature'] = $this->hmacsha1(
            $this->getConsumerSecret() . '&' . $this->getOAuthTokenSecret(),
            $base
        );

        // calculate header
        $header = $this->calculateHeader(
            $parameters,
            self::SECURE_API_URL . '/oauth/' . $method
        );

        // set options
        $options[CURLOPT_URL] = self::SECURE_API_URL . '/oauth/' . $method;
        $options[CURLOPT_PORT] = self::SECURE_API_PORT;
        $options[CURLOPT_USERAGENT] = $this->getUserAgent();
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = false;
        $options[CURLOPT_HTTPHEADER] = array('Expect:');
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $this->buildQuery($parameters);

        // init
        $this->curl = curl_init();

        // set options
        curl_setopt_array($this->curl, $options);

        // execute
        $response = curl_exec($this->curl);
        $headers = curl_getinfo($this->curl);

        // fetch errors
        $errorNumber = curl_errno($this->curl);
        $errorMessage = curl_error($this->curl);

        // error?
        if ($errorNumber != '') {
            throw new Exception($errorMessage, $errorNumber);
        }

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
     * @param  string           $url           The url to call.
     * @param  array[optional]  $parameters    Optional parameters.
     * @param  bool[optional]   $authenticate  Should we authenticate.
     * @param  bool[optional]   $method        The method to use. Possible values are GET, POST.
     * @param  string[optional] $filePath      The path to the file to upload.
     * @param  bool[optional]   $expectJSON    Do we expect JSON.
     * @param  bool[optional]   $returnHeaders Should the headers be returned?
     * @return string
     */
    private function doCall(
        $url, array $parameters = null, $authenticate = false, $method = 'GET',
        $filePath = null, $expectJSON = true, $returnHeaders = false
    )
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
        if (!in_array($method, $allowedMethods)) {
            throw new Exception(
                'Unknown method (' . $method . '). Allowed methods are: ' .
                implode(', ', $allowedMethods)
            );
        }

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
        $base = $this->calculateBaseString(
            self::API_URL . '/' . $url, $method, $data
        );

        // based on the method, we should handle the parameters in a different way
        if ($method == 'POST') {
            // file provided?
            if ($filePath != null) {
                // build a boundary
                $boundary = md5(time());

                // process file
                $fileInfo = pathinfo($filePath);

                // set mimeType
                $mimeType = 'application/octet-stream';
                if ($fileInfo['extension'] == 'jpg' || $fileInfo['extension'] == 'jpeg') {
                    $mimeType = 'image/jpeg';
                } elseif($fileInfo['extension'] == 'gif') $mimeType = 'image/gif';
                elseif($fileInfo['extension'] == 'png') $mimeType = 'image/png';

                // init var
                $content = '--' . $boundary . "\r\n";

                // set file
                $content .= 'Content-Disposition: form-data; name=image; filename="' .
                            $fileInfo['basename'] . '"' . "\r\n";
                $content .= 'Content-Type: ' . $mimeType . "\r\n";
                $content .= "\r\n";
                $content .= file_get_contents($filePath);
                $content .= "\r\n";
                $content .= "--" . $boundary . '--';

                // build headers
                $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
                $headers[] = 'Content-Length: ' . strlen($content);

                // set content
                $options[CURLOPT_POSTFIELDS] = $content;
            }

            // no file
            else $options[CURLOPT_POSTFIELDS] = $this->buildQuery($parameters);

            // enable post
            $options[CURLOPT_POST] = true;
        } else {
            // add the parameters into the querystring
            if(!empty($parameters)) $url .= '?' . $this->buildQuery($parameters);
            $options[CURLOPT_POST] = false;
        }

        // add sign into the parameters
        $oauth['oauth_signature'] = $this->hmacsha1(
            $this->getConsumerSecret() . '&' . $this->getOAuthTokenSecret(),
            $base
        );

        $headers[] = $this->calculateHeader($oauth, self::API_URL . '/' . $url);
        $headers[] = 'Expect:';

        // set options
        $options[CURLOPT_URL] = self::API_URL . '/' . $url;
        $options[CURLOPT_PORT] = self::API_PORT;
        $options[CURLOPT_USERAGENT] = $this->getUserAgent();
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = false;
        $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        $options[CURLOPT_HTTPHEADER] = $headers;

        // include the response headers in the curl output
        $options[CURLOPT_HEADER] = true;

        // init
        if($this->curl == null) $this->curl = curl_init();

        // set options
        curl_setopt_array($this->curl, $options);

        // execute
        $response = curl_exec($this->curl);
        $headers = curl_getinfo($this->curl);

        // split the response headers from the body
        list($responseHeaders, $response) = explode("\r\n\r\n", $response, 2);

        // set the latest rate limit status
        $rateLimitStatus = array();
        foreach (explode("\r\n", $responseHeaders) as $i => $line) {
            // skip the first line, it's the HTTP response code
            if ($i !== 0) {
                list($key, $value) = explode(': ', $line);
                switch ($key) {
                    case "X-Rate-Limit-Limit":
                        $rateLimitStatus['limit'] = $value;
                        break;
                    case "X-Rate-Limit-Remaining":
                        $rateLimitStatus['remaining'] = $value;
                        break;
                    case "X-Rate-Limit-Reset":
                        $rateLimitStatus['reset'] = $value;
                        break;
                }
            }
        }
        $this->lastRateLimitStatus = $rateLimitStatus;

        // fetch errors
        $errorNumber = curl_errno($this->curl);
        $errorMessage = curl_error($this->curl);

        // return the headers
        if($returnHeaders) return $headers;

        // we don't expext JSON, return the response
        if(!$expectJSON) return $response;

        // replace ids with their string values, added because of some
        // PHP-version can't handle these large values
        $response = preg_replace('/id":(\d+)/', 'id":"\1"', $response);

        // we expect JSON, so decode it
        $json = @json_decode($response, true);

        // validate JSON
        if ($json === null) {
            // should we provide debug information
            if (self::DEBUG) {
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
            throw new Exception('Invalid response.');
        }

        // any errors
        if (isset($json['errors'])) {
            // should we provide debug information
            if (self::DEBUG) {
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
            if (isset($json['errors'][0]['message'])) {
                throw new Exception($json['errors'][0]['message']);
            } elseif (isset($json['errors']) && is_string($json['errors'])) {
                throw new Exception($json['errors']);
            } else throw new Exception('Invalid response.');
        }

        // any error
        if (isset($json['error'])) {
            // should we provide debug information
            if (self::DEBUG) {
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
            throw new Exception($json['error']);
        }

        // return
        return $json;
    }

    /**
     * Get the consumer key
     *
     * @return string
     */
    private function getConsumerKey()
    {
        return $this->consumerKey;
    }

    /**
     * Get the consumer secret
     *
     * @return string
     */
    private function getConsumerSecret()
    {
        return $this->consumerSecret;
    }

    /**
     * Returns the rate limit status for the last API call
     *
     * @return array
     */
    public function getLastRateLimitStatus()
    {
        return $this->lastRateLimitStatus;
    }

    /**
     * Get the oAuth-token
     *
     * @return string
     */
    private function getOAuthToken()
    {
        return $this->oAuthToken;
    }

    /**
     * Get the oAuth-token-secret
     *
     * @return string
     */
    private function getOAuthTokenSecret()
    {
        return $this->oAuthTokenSecret;
    }

    /**
     * Get the timeout
     *
     * @return int
     */
    public function getTimeOut()
    {
        return (int) $this->timeOut;
    }

    /**
     * Get the useragent that will be used. Our version will be prepended to yours.
     * It will look like: "PHP Twitter/<version> <your-user-agent>"
     *
     * @return string
     */
    public function getUserAgent()
    {
        return (string) 'PHP Twitter/' . self::VERSION . ' ' . $this->userAgent;
    }

    /**
     * Set the consumer key
     *
     * @param string $key The consumer key to use.
     */
    private function setConsumerKey($key)
    {
        $this->consumerKey = (string) $key;
    }

    /**
     * Set the consumer secret
     *
     * @param string $secret The consumer secret to use.
     */
    private function setConsumerSecret($secret)
    {
        $this->consumerSecret = (string) $secret;
    }

    /**
     * Set the oAuth-token
     *
     * @param string $token The token to use.
     */
    public function setOAuthToken($token)
    {
        $this->oAuthToken = (string) $token;
    }

    /**
     * Set the oAuth-secret
     *
     * @param string $secret The secret to use.
     */
    public function setOAuthTokenSecret($secret)
    {
        $this->oAuthTokenSecret = (string) $secret;
    }

    /**
     * Set the timeout
     *
     * @param int $seconds The timeout in seconds.
     */
    public function setTimeOut($seconds)
    {
        $this->timeOut = (int) $seconds;
    }

    /**
     * Get the useragent that will be used. Our version will be prepended to yours.
     * It will look like: "PHP Twitter/<version> <your-user-agent>"
     *
     * @param string $userAgent Your user-agent, it should look like <app-name>/<app-version>.
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = (string) $userAgent;
    }

    /**
     * Build the signature for the data
     *
     * @param  string $key  The key to use for signing.
     * @param  string $data The data that has to be signed.
     * @return string
     */
    private function hmacsha1($key, $data)
    {
        return base64_encode(hash_hmac('SHA1', $data, $key, true));
    }

    /**
     * URL-encode method for internal use
     *
     * @param  mixed  $value The value to encode.
     * @return string
     */
    private static function urlencode_rfc3986($value)
    {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'urlencode_rfc3986'), $value);
        } else {
            $search = array('+', ' ', '%7E', '%');
            $replace = array('%20', '%20', '~', '%25');

            return str_replace($search, $replace, urlencode($value));
        }
    }

// Timeline resources
    /**
     * Returns the 20 most recent mentions (tweets containing a users's @screen_name) for the authenticating user.
     * The timeline returned is the equivalent of the one seen when you view your mentions on twitter.com.
     * This method can only return up to 800 tweets.
     *
     * @param  int[optional]    $count              Specifies the number of tweets to try and retrieve, up to a maximum of 200. The value of count is best thought of as a limit to the number of tweets to return because suspended or deleted content is removed after the count has been applied. We include retweets in the count, even if include_rts is not supplied.
     * @param  string[optional] $sinceId            Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId              Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $trimUser           When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @param  bool[optional]   $contributorDetails This parameter enhances the contributors element of the status response to include the screen_name of the contributor. By default only the user_id of the contributor is included.
     * @param  bool[optional]   $includeEntities    The entities node will be disincluded when set to false.
     * @return array
     */
    public function statusesMentionsTimeline(
        $count = null, $sinceId = null, $maxId = null,
        $trimUser = null, $contributorDetails = null, $includeEntities = null
    )
    {
        // build parameters
        $parameters = null;
        $parameters['include_rts'] = 'true';
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }
        if ($contributorDetails != null) {
            $parameters['contributor_details'] = ($contributorDetails) ? 'true' : 'false';
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/mentions_timeline.json',
            $parameters, true
        );
    }

    /**
     * Returns a collection of the most recent Tweets posted by the user indicated by the screen_name or user_id parameters.
     * User timelines belonging to protected users may only be requested when the authenticated user either "owns" the timeline or is an approved follower of the owner.
     * The timeline returned is the equivalent of the one seen when you view a user's profile on twitter.com.
     * This method can only return up to 3,200 of a user's most recent Tweets. Native retweets of other statuses by the user is included in this total, regardless of whether include_rts is set to false when requesting this resource.
     *
     * @param  string[optional] $userId             The ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @param  string[optional] $screenName         The screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
     * @param  string[optional] $sinceId            Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  int[optional]    $count              Specifies the number of tweets to try and retrieve, up to a maximum of 200 per distinct request. The value of count is best thought of as a limit to the number of tweets to return because suspended or deleted content is removed after the count has been applied. We include retweets in the count, even if include_rts is not supplied.
     * @param  string[optional] $maxId              Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $trimUser           When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @param  bool[optional]   $excludeReplies     This parameter will prevent replies from appearing in the returned timeline. Using exclude_replies with the count parameter will mean you will receive up-to count tweets — this is because the count parameter retrieves that many tweets before filtering out retweets and replies.
     * @param  bool[optional]   $contributorDetails This parameter enhances the contributors element of the status response to include the screen_name of the contributor. By default only the user_id of the contributor is included.
     * @param  bool[optional]   $includeRts         When set to false, the timeline will strip any native retweets (though they will still count toward both the maximal length of the timeline and the slice selected by the count parameter). Note: If you're using the trim_user parameter in conjunction with include_rts, the retweets will still contain a full user object.
     * @return array
     */
    public function statusesUserTimeline(
        $userId = null, $screenName = null, $sinceId = null, $count = null,
        $maxId = null, $trimUser = null, $excludeReplies = null,
        $contributorDetails = null, $includeRts = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }
        if ($excludeReplies != null) {
            $parameters['exclude_replies'] = ($excludeReplies) ? 'true' : 'false';
        }
        if ($contributorDetails != null) {
            $parameters['contributor_details'] = ($contributorDetails) ? 'true' : 'false';
        }
        if ($includeRts != null) {
            $parameters['include_rts'] = ($includeRts) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/user_timeline.json',
            $parameters
        );
    }

    /**
     * Returns the 20 most recent statuses, including retweets if they exist, posted by the authenticating user and the user's they follow. This is the same timeline seen by a user when they login to twitter.com.
     * This method is identical to statusesFriendsTimeline, except that this method always includes retweets.
     *
     * @param  int[optional]    $count              Specifies the number of records to retrieve. Must be less than or equal to 200. Defaults to 20.
     * @param  string[optional] $sinceId            Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId              Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $trimUser           When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @param  bool[optional]   $excludeReplies     This parameter will prevent replies from appearing in the returned timeline. Using exclude_replies with the count parameter will mean you will receive up-to count tweets — this is because the count parameter retrieves that many tweets before filtering out retweets and replies.
     * @param  bool[optional]   $contributorDetails This parameter enhances the contributors element of the status response to include the screen_name of the contributor. By default only the user_id of the contributor is included.
     * @param  bool[optional]   $includeEntities    The entities node will be disincluded when set to false.
     * @return array
     */
    public function statusesHomeTimeline(
        $count = null, $sinceId = null, $maxId = null, $trimUser = null,
        $excludeReplies = null, $contributorDetails = null,
        $includeEntities = null
    )
    {
        // build parameters
        $parameters = null;
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }
        if ($excludeReplies != null) {
            $parameters['exclude_replies'] = ($excludeReplies) ? 'true' : 'false';
        }
        if ($contributorDetails != null) {
            $parameters['contributor_details'] = ($contributorDetails) ? 'true' : 'false';
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/home_timeline.json',
            $parameters, true
        );
    }

    /**
     * Returns the most recent tweets authored by the authenticating user that have recently been retweeted by others. This timeline is a subset of the user's GET statuses/user_timeline.
     *
     * @param  int[optional]    $count               Specifies the number of records to retrieve. Must be less than or equal to 100. If omitted, 20 will be assumed.
     * @param  string[optional] $sinceId             Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId               Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $trimUser            When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @param  bool[optional]   $includeEntities     The tweet entities node will be disincluded when set to false.
     * @param  bool[optional]   $includeUserEntities The user entities node will be disincluded when set to false.
     * @return array
     */
    public function statusesRetweetsOfMe(
        $count = null, $sinceId = null, $maxId = null,
        $trimUser = null, $includeEntities = null, $includeUserEntities = null
    )
    {
        // build parameters
        $parameters = null;
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($includeUserEntities != null) {
            $parameters['include_user_entities'] = ($includeUserEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/retweets_of_me.json',
            $parameters, true
        );
    }

// Tweets resources
    /**
     * Returns up to 100 of the first retweets of a given tweet.
     *
     * @param  string         $id       The numerical ID of the desired status.
     * @param  int[optional]  $count    Specifies the number of records to retrieve. Must be less than or equal to 100.
     * @param  bool[optional] $trimUser When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @return array
     */
    public function statusesRetweets($id, $count = null, $trimUser = null)
    {
        // build parameters
        $parameters = null;
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/retweets/' . (string) $id . '.json',
            $parameters
        );
    }

    /**
     * Returns a single Tweet, specified by the id parameter. The Tweet's author will also be embedded within the tweet.
     *
     * @param  string         $id               The numerical ID of the desired Tweet.
     * @param  bool[optional] $trimUser         When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @param  bool[optional] $includeMyRetweet When set to true, any Tweets returned that have been retweeted by the authenticating user will include an additional current_user_retweet node, containing the ID of the source status for the retweet.
     * @param  bool[optional] $includeEntities  The entities node will be disincluded when set to false.
     * @return array
     */
    public function statusesShow(
        $id, $trimUser = null, $includeMyRetweet = null, $includeEntities = null
    )
    {
        // build parameters
        $parameters['id'] = (string) $id;
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }
        if ($includeMyRetweet != null) {
            $parameters['include_my_retweet'] = ($includeMyRetweet) ? 'true' : 'false';
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/show.json',
            $parameters, true
        );
    }

    /**
     * Destroys the status specified by the required ID parameter. The authenticating user must be the author of the specified status. Returns the destroyed status if successful.
     *
     * @param  string         $id       The numerical ID of the desired status.
     * @param  bool[optional] $trimUser When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @return array
     */
    public function statusesDestroy($id, $trimUser = null)
    {
        // build parameters
        $parameters = null;
        if($trimUser != null) $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';

        // make the call
        return (array) $this->doCall(
            'statuses/destroy/' . (string) $id . '.json',
            $parameters, true, 'POST'
        );
    }

    /**
     * Updates the authenticating user's status. A status update with text identical to the authenticating user's text identical to the authenticating user's current status will be ignored to prevent duplicates.
     *
     * @param  string           $status             The text of your status update, typically up to 140 characters. URL encode as necessary. t.co link wrapping may effect character counts. There are some special commands in this field to be aware of. For instance, preceding a message with "D " or "M " and following it with a screen name can create a direct message to that user if the relationship allows for it.
     * @param  string[optional] $inReplyToStatusId  The ID of an existing status that the update is in reply to. Note: This parameter will be ignored unless the author of the tweet this parameter references is mentioned within the status text. Therefore, you must include @username, where username is the author of the referenced tweet, within the update.
     * @param  float[optional]  $lat                The latitude of the location this tweet refers to. This parameter will be ignored unless it is inside the range -90.0 to +90.0 (North is positive) inclusive. It will also be ignored if there isn't a corresponding long parameter.
     * @param  float[optional]  $long               The longitude of the location this tweet refers to. The valid ranges for longitude is -180.0 to +180.0 (East is positive) inclusive. This parameter will be ignored if outside that range, if it is not a number, if geo_enabled is disabled, or if there not a corresponding lat parameter.
     * @param  string[optional] $placeId            A place in the world. These IDs can be retrieved from GET geo/reverse_geocode.
     * @param  bool[optional]   $displayCoordinates Whether or not to put a pin on the exact coordinates a tweet has been sent from.
     * @param  bool[optional]   $trimUser           When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @return array
     */
    public function statusesUpdate(
        $status, $inReplyToStatusId = null, $lat = null, $long = null,
        $placeId = null, $displayCoordinates = null, $trimUser = null
    )
    {
        // build parameters
        $parameters['status'] = (string) $status;
        if ($inReplyToStatusId != null) {
            $parameters['in_reply_to_status_id'] = (string) $inReplyToStatusId;
        }
        if ($lat != null) {
            $parameters['lat'] = (float) $lat;
        }
        if ($long != null) {
            $parameters['long'] = (float) $long;
        }
        if ($placeId != null) {
            $parameters['place_id'] = (string) $placeId;
        }
        if ($displayCoordinates != null) {
            $parameters['display_coordinates'] = ($displayCoordinates) ? 'true' : 'false';
        }
        if ($trimUser) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/update.json',
            $parameters, true, 'POST'
        );
    }

    /**
     * Retweets a tweet. Returns the original tweet with retweet details embedded.
     *
     * @param  string         $id       The numerical ID of the desired status.
     * @param  bool[optional] $trimUser When set to true, each tweet returned in a timeline will include a user object including only the status authors numerical ID. Omit this parameter to receive the complete user object.
     * @return array
     */
    public function statusesRetweet($id, $trimUser = null)
    {
        $parameters = null;
        if ($trimUser != null) {
            $parameters['trim_user'] = ($trimUser) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'statuses/retweet/' . $id . '.json',
            $parameters, true, 'POST'
        );
    }

    /**
     * Not implemented yet
     */
    public function statusesUpdateWithMedia()
    {
        throw new Exception('Not Implemented');
    }

    /**
     * @param  string[optional] $id         The Tweet/status ID to return embed code for.
     * @param  string[optional] $url        The URL of the Tweet/status to be embedded.
     * @param  int[optional]    $maxwidth   The maximum width in pixels that the embed should be rendered at. This value is constrained to be between 250 and 550 pixels. Note that Twitter does not support the oEmbed maxheight parameter. Tweets are fundamentally text, and are therefore of unpredictable height that cannot be scaled like an image or video. Relatedly, the oEmbed response will not provide a value for height. Implementations that need consistent heights for Tweets should refer to the hide_thread and hide_media parameters below.
     * @param  bool[optional]   $hideMedia  Specifies whether the embedded Tweet should automatically expand images which were uploaded via POST statuses/update_with_media. When set to true images will not be expanded. Defaults to false.
     * @param  bool[optional]   $hideThread Specifies whether the embedded Tweet should automatically show the original message in the case that the embedded Tweet is a reply. When set to true the original Tweet will not be shown. Defaults to false.
     * @param  bool[optional]   $omitScript Specifies whether the embedded Tweet HTML should include a <script> element pointing to widgets.js. In cases where a page already includes widgets.js, setting this value to true will prevent a redundant script element from being included. When set to true the <script> element will not be included in the embed HTML, meaning that pages must include a reference to widgets.js manually. Defaults to false.
     * @param  string[optional] $align      Specifies whether the embedded Tweet should be left aligned, right aligned, or centered in the page. Valid values are left, right, center, and none. Defaults to none, meaning no alignment styles are specified for the Tweet.
     * @param  string[optional] $related    A value for the TWT related parameter, as described in Web Intents. This value will be forwarded to all Web Intents calls.
     * @param  string[optional] $lang       Language code for the rendered embed. This will affect the text and localization of the rendered HTML.
     * @return array
     */
    public function statusesOEmbed(
        $id = null, $url = null, $maxwidth = null, $hideMedia = null,
        $hideThread = null, $omitScript = null, $align = null, $related = null,
        $lang = null
    )
    {
        if ($id == null && $url == null) {
            throw new Exception('Either id or url should be specified.');
        }

        // build parameters
        $parameters = null;
        if ($id != null) {
            $parameters['id'] = (string) $id;
        }
        if ($url != null) {
            $parameters['url'] = (string) $url;
        }
        if ($maxwidth != null) {
            $parameters['maxwidth'] = (int) $maxwidth;
        }
        if ($hideMedia != null) {
            $parameters['hide_media'] = ($hideMedia) ? 'true' : 'false';
        }
        if ($hideThread != null) {
            $parameters['hide_thread'] = ($hideThread) ? 'true' : 'false';
        }
        if ($omitScript != null) {
            $parameters['omit_script'] = ($omitScript) ? 'true' : 'false';
        }
        if ($align != null) {
            $parameters['align'] = (string) $align;
        }
        if ($related != null) {
            $parameters['related'] = (string) $related;
        }
        if ($lang != null) {
            $parameters['lang'] = (string) $lang;
        }

        // make the call
        return (array) $this->doCall(
            'statuses/oembed.json',
            $parameters
        );
    }

// Search resources
    /**
     * Returns tweets that match a specified query.
     *
     * @param  string           $q               A UTF-8, URL-encoded search query of 1,000 characters maximum, including operators. Queries may additionally be limited by complexity.
     * @param  string[optional] $geocode         Returns tweets by users located within a given radius of the given latitude/longitude. The location is preferentially taking from the Geotagging API, but will fall back to their Twitter profile. The parameter value is specified by "latitude,longitude,radius", where radius units must be specified as either "mi" (miles) or "km" (kilometers). Note that you cannot use the near operator via the API to geocode arbitrary locations; however you can use this geocode parameter to search near geocodes directly. A maximum of 1,000 distinct "sub-regions" will be considered when using the radius modifier.
     * @param  string[optional] $lang            Restricts tweets to the given language, given by an ISO 639-1 code. Language detection is best-effort.
     * @param  string[optional] $locale          Specify the language of the query you are sending (only ja is currently effective). This is intended for language-specific consumers and the default should work in the majority of cases.
     * @param  string[optional] $resultType      Specifies what type of search results you would prefer to receive. The current default is "mixed." Valid values include: mixed: Include both popular and real time results in the response, recent: return only the most recent results in the response, popular: return only the most popular results in the response.
     * @param  int[optional]    $count           The number of tweets to return per page, up to a maximum of 100. Defaults to 15. This was formerly the "rpp" parameter in the old Search API.
     * @param  string[optional] $until           Returns tweets generated before the given date. Date should be formatted as YYYY-MM-DD. Keep in mind that the search index may not go back as far as the date you specify here.
     * @param  string[optional] $sinceId         Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId           Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $includeEntities The entities node will be disincluded when set to false.
     * @return array
     */
    public function searchTweets(
        $q, $geocode = null, $lang = null, $locale = null, $resultType= null,
        $count = null, $until = null, $sinceId = null, $maxId = null,
        $includeEntities = null
    )
    {
        $parameters['q'] = (string) $q;
        if ($geocode !== null) {
            $parameters['geocode'] = (string) $geocode;
        }
        if ($lang !== null) {
            $parameters['lang'] = (string) $lang;
        }
        if ($locale !== null) {
            $parameters['locale'] = (string) $locale;
        }
        if ($resultType !== null) {
            $parameters['result_type'] = (string) $resultType;
        }
        if ($count !== null) {
            $parameters['count'] = (int) $count;
        }
        if ($until !== null) {
            $parameters['until'] = (string) $until;
        }
        if ($sinceId !== null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId !== null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        return (array) $this->doCall(
            'search/tweets.json',
            $parameters
        );
    }

// Streaming resources
    /**
     * Not implemented yet
     */
    public function statusesFilter()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function statusesSample()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function statusesFirehose()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function user()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function site()
    {
        throw new Exception('Not implemented');
    }

// Direct Messages resources
    /**
     * Returns the 20 most recent direct messages sent to the authenticating user. Includes detailed information about the sender and recipient user. You can request up to 200 direct messages per call, up to a maximum of 800 incoming DMs.
     * Important: This method requires an access token with RWD (read, write & direct message) permissions. Consult The Application Permission Model for more information.
     *
     * @param  string[optional] $sinceId         Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId           Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  int[optional]    $count           Specifies the number of direct messages to try and retrieve, up to a maximum of 200. The value of count is best thought of as a limit to the number of Tweets to return because suspended or deleted content is removed after the count has been applied.
     * @param  int[optional]    $page            Specifies the page of results to retrieve.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function directMessages(
        $sinceId = null, $maxId = null, $count = null, $page = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // build parameters
        $parameters = array();
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($page != null) {
            $parameters['page'] = (int) $page;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'direct_messages.json',
            $parameters, true
        );
    }

    /**
     * Returns the 20 most recent direct messages sent by the authenticating user. Includes detailed information about the sender and recipient user. You can request up to 200 direct messages per call, up to a maximum of 800 outgoing DMs.
     * Important: This method requires an access token with RWD (read, write & direct message) permissions. Consult The Application Permission Model for more information.
     *
     * @param  string[optional] $sinceId         Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[optional] $maxId           Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  int[optional]    $count           Specifies the number of records to retrieve. Must be less than or equal to 200.
     * @param  int[optional]    $page            Specifies the page of results to retrieve.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @return array
     */
    public function directMessagesSent(
        $sinceId = null, $maxId = null, $count = null, $page = null,
        $includeEntities = null
    )
    {
        // build parameters
        $parameters = array();
        if ($sinceId != null) {
            $parameters['since_id'] = (string) $sinceId;
        }
        if ($maxId != null) {
            $parameters['max_id'] = (string) $maxId;
        }
        if ($count != null) {
            $parameters['count'] = (int) $count;
        }
        if ($page != null) {
            $parameters['page'] = (int) $page;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'direct_messages/sent.json',
            $parameters, true
        );
    }

    /**
     *
     * @param  string $id The ID of the direct message.
     * @return array
     */
    public function directMessagesShow($id)
    {
        // build parameters
        $parameters['id'] = (string) $id;

        // make the call
        return (array) $this->doCall(
            'direct_messages/show.json',
            $parameters, true
        );
    }

    /**
     * Destroys the direct message specified in the required ID parameter. The authenticating user must be the recipient of the specified direct message.
     * Important: This method requires an access token with RWD (read, write & direct message) permissions. Consult The Application Permission Model for more information.
     *
     * @param  string         $id              The ID of the direct message to delete.
     * @param  bool[optional] $includeEntities The entities node will not be included when set to false.
     * @return array
     */
    public function directMessagesDestroy($id, $includeEntities = null)
    {
        // build parameters
        $parameters['id'] = (string) $id;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'direct_messages/destroy.json',
            $parameters, true, 'POST'
        );
    }

    /**
     * Sends a new direct message to the specified user from the authenticating user. Requires both the user and text parameters and must be a POST. Returns the sent message in the requested format if successful.
     *
     * @param  string[optional] $userId     The ID of the user who should receive the direct message. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @param  string[optional] $screenName The screen name of the user who should receive the direct message. Helpful for disambiguating when a valid screen name is also a user ID.
     * @param  string           $text       The text of your direct message. Be sure to URL encode as necessary, and keep the message under 140 characters.
     * @return array
     */
    public function directMessagesNew(
        $userId = null, $screenName = null, $text
    )
    {
        // validate
        if ($userId == null && $screenName == null) {
            throw new Exception('One of user_id or screen_name are required.');
        }

        // build parameters
        $parameters['text'] = (string) $text;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }

        // make the call
        return (array) $this->doCall(
            'direct_messages/new.json',
            $parameters, true, 'POST'
        );
    }

// Friends & Followers resources
    /**
     * Returns a cursored collection of user IDs for every user the specified user is following (otherwise known as their "friends").
     * At this time, results are ordered with the most recent following first — however, this ordering is subject to unannounced change and eventual consistency issues. Results are given in groups of 5,000 user IDs and multiple "pages" of results can be navigated through using the next_cursor value in subsequent requests. See Using cursors to navigate collections for more information.
     * This method is especially powerful when used in conjunction with GET users/lookup, a method that allows you to convert user IDs into full user objects in bulk.
     *
     * @param  string[optional] $userId       The ID of the user for whom to return results for.
     * @param  string[optional] $screenName   The screen name of the user for whom to return results for.
     * @param  string[optional] $cursor       Causes the list of connections to be broken into pages of no more than 5000 IDs at a time. The number of IDs returned is not guaranteed to be 5000 as suspended users are filtered out after connections are queried. If no cursor is provided, a value of -1 will be assumed, which is the first "page." The response from the API will include a previous_cursor and next_cursor to allow paging back and forth
     * @param  bool[optional]   $stringifyIds Many programming environments will not consume our Tweet ids due to their size. Provide this option to have ids returned as strings instead.
     * @return array
     */
    public function friendsIds(
        $userId = null, $screenName = null, $cursor = null, $stringifyIds = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($cursor != null) {
            $parameters['cursor'] = (string) $cursor;
        }
        if ($stringifyIds !== null) {
            $parameters['stringify_ids'] = ((bool) $stringifyIds) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall('friends/ids.json', $parameters, true);
    }

    /**
     * Returns a cursored collection of user IDs for every user following the specified user.
     * At this time, results are ordered with the most recent following first — however, this ordering is subject to unannounced change and eventual consistency issues. Results are given in groups of 5,000 user IDs and multiple "pages" of results can be navigated through using the next_cursor value in subsequent requests. See Using cursors to navigate collections for more information.
     * This method is especially powerful when used in conjunction with GET users/lookup, a method that allows you to convert user IDs into full user objects in bulk.
     *
     * @param  string[optional] $userId       The ID of the user for whom to return results for.
     * @param  string[optional] $screenName   The screen name of the user for whom to return results for.
     * @param  string[optional] $cursor       Causes the list of connections to be broken into pages of no more than 5000 IDs at a time. The number of IDs returned is not guaranteed to be 5000 as suspended users are filtered out after connections are queried. If no cursor is provided, a value of -1 will be assumed, which is the first "page." The response from the API will include a previous_cursor and next_cursor to allow paging back and forth
     * @param  bool[optional]   $stringifyIds Many programming environments will not consume our Tweet ids due to their size. Provide this option to have ids returned as strings instead.
     * @return array
     */
    public function followersIds(
        $userId = null, $screenName = null, $cursor = null, $stringifyIds = true
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($cursor != null) {
            $parameters['cursor'] = (string) $cursor;
        }
        $parameters['stringify_ids'] = ((bool) $stringifyIds) ? 'true' : 'false';

        // make the call
        return (array) $this->doCall('followers/ids.json', $parameters, true);
    }

    /**
     * Returns the relationships of the authenticating user to the comma-separated list of up to 100 screen_names or user_ids provided.
     * Values for connections can be: following, following_requested, followed_by, none.
     *
     * @param  mixed[optional] $userIds     An array of user IDs, up to 100 are allowed in a single request.
     * @param  mixed[optional] $screenNames An array of screen names, up to 100 are allowed in a single request.
     * @return array
     */
    public function friendshipsLookup($userIds = null, $screenNames = null)
    {
        // redefine
        $userIds = (array) $userIds;
        $screenNames = (array) $screenNames;

        // validate
        if (empty($userIds) && empty($screenNames)) {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if (!empty($userIds)) {
            $parameters['user_id'] = implode(',', $userIds);
        }
        if (!empty($screenNames)) {
            $parameters['screen_name'] = implode(',', $screenNames);
        }

        // make the call
        return (array) $this->doCall('friendships/lookup.json', $parameters, true);
    }

    /**
     * Returns a collection of numeric IDs for every user who has a pending request to follow the authenticating user.
     *
     * @param string[optional] $cursor Causes the list of connections to be broken into pages of no more than 5000 IDs at a time. The number of IDs returned is not guaranteed to be 5000 as suspended users are filtered out after connections are queried. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
      * @param  bool[optional] 	$stringifyIds	Many programming environments will not consume our Tweet ids due to their size. Provide this option to have ids returned as strings instead.
     * @return array
     */
    public function friendshipsIncoming($cursor = null, $stringifyIds = true)
    {
        // build parameters
        $parameters = null;
        if($cursor != null) $parameters['cursor'] = (string) $cursor;
        $parameters['stringify_ids'] = ((bool) $stringifyIds) ? 'true' : 'false';

        // make the call
        return (array) $this->doCall(
            'friendships/incoming.json', $parameters, true
        );
    }

    /**
     * Returns a collection of numeric IDs for every protected user for whom the authenticating user has a pending follow request.
     *
     * @param  string[optional] $cursor       Causes the list of connections to be broken into pages of no more than 5000 IDs at a time. The number of IDs returned is not guaranteed to be 5000 as suspended users are filtered out after connections are queried. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
     * @param  bool[optional]   $stringifyIds Many programming environments will not consume our Tweet ids due to their size. Provide this option to have ids returned as strings instead.
     * @return array
     */
    public function friendshipsOutgoing($cursor = null, $stringifyIds = true)
    {
        // build parameters
        $parameters = null;
        if($cursor != null) $parameters['cursor'] = (string) $cursor;
        $parameters['stringify_ids'] = ((bool) $stringifyIds) ? 'true' : 'false';

        // make the call
        return (array) $this->doCall(
            'friendships/outgoing.json', $parameters, true
        );
    }

    /**
     * Allows the authenticating users to follow the user specified in the ID parameter.
     * Returns the befriended user in the requested format when successful. Returns a string describing the failure condition when unsuccessful. If you are already friends with the user a HTTP 403 may be returned, though for performance reasons you may get a 200 OK message even if the friendship already exists.
     * Actions taken in this method are asynchronous and changes will be eventually consistent.
     *
     * @param  string[optional] $userId     The ID of the user for whom to befriend.
     * @param  string[optional] $screenName The screen name of the user for whom to befriend.
     * @param  bool[optional]   $follow     Enable notifications for the target user.
     * @return array
     */
    public function friendshipsCreate(
        $userId = null, $screenName = null, $follow = false
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        $parameters['follow'] = ($follow) ? 'true' : 'false';

        // make the call
        return (array) $this->doCall(
            'friendships/create.json', $parameters, true, 'POST'
        );
    }

    /**
     * Allows the authenticating user to unfollow the user specified in the ID parameter.
     * Returns the unfollowed user in the requested format when successful. Returns a string describing the failure condition when unsuccessful.
     * Actions taken in this method are asynchronous and changes will be eventually consistent.
     *
     * @param  string[optional] $userId     The ID of the user for whom to unfollow.
     * @param  string[optional] $screenName The screen name of the user for whom to unfollow.
     * @return array
     */
    public function friendshipsDestroy($userId = null, $screenName = null)
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }

        // make the call
        return (array) $this->doCall(
            'friendships/destroy.json', $parameters, true, 'POST'
        );
    }

    /**
     * Allows one to enable or disable retweets and device notifications from the specified user.
     *
     * @param  string[optional] $userId     The ID of the user for whom to befriend.
     * @param  string[optional] $screenName The screen name of the user for whom to befriend.
     * @param  bool[optional]   $device     Enable/disable device notifications from the target user.
     * @param  bool[optional]   $retweets   Enable/disable retweets from the target user.
     * @return array
     */
    public function friendshipsUpdate(
        $userId = null, $screenName = null, $device = null, $retweets = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($device !== null) {
            $parameters['device'] = ((bool) $device) ? 'true' : 'false';
        }
        if ($retweets !== null) {
            $parameters['retweets'] = ((bool) $retweets) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'friendships/update.json', $parameters, true, 'POST'
        );
    }

    /**
     * Returns detailed information about the relationship between two arbitrary users.
     *
     * @param  string[optional] $sourceId         The user_id of the subject user.
     * @param  string[optional] $sourceScreenName The screen_name of the subject user.
     * @param  string[optional] $targetId         The screen_name of the subject user.
     * @param  string[optional] $targetScreenName The screen_name of the target user.
     * @return array
     */
    public function friendshipsShow(
        $sourceId = null, $sourceScreenName = null, $targetId = null,
        $targetScreenName = null)
    {
        // validate
        if ($sourceId == '' && $sourceScreenName == '') {
            throw new Exception('Specify an sourceId or a sourceScreenName.');
        }
        if ($targetId == '' && $targetScreenName == '') {
            throw new Exception('Specify an targetId or a targetScreenName.');
        }

        // build parameters
        if ($sourceId != null) {
            $parameters['source_id'] = (string) $sourceId;
        }
        if ($sourceScreenName != null) {
            $parameters['source_screen_name'] = (string) $sourceScreenName;
        }
        if ($targetId != null) {
            $parameters['target_id'] = (string) $targetId;
        }
        if ($targetScreenName != null) {
            $parameters['target_screen_name'] = (string) $targetScreenName;
        }

        // make the call
        return (array) $this->doCall('friendships/show.json', $parameters);
    }

    /**
     * Returns a cursored collection of user objects for every user the specified user is following (otherwise known as their "friends").
     * At this time, results are ordered with the most recent following first — however, this ordering is subject to unannounced change and eventual consistency issues. Results are given in groups of 20 users and multiple "pages" of results can be navigated through using the next_cursor value in subsequent requests. See Using cursors to navigate collections for more information.
     *
     * @param  string[optional] $userId          The ID of the user for whom to return results for.
     * @param  string[optional] $screenName      The screen name of the user for whom to return results for.
     * @param  int[optional]    $cursor          Causes the results to be broken into pages of no more than 20 records at a time. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
     * @param  bool[optional]   $includeEntities The user object entities node will be disincluded when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function friendsList(
        $userId = null, $screenName = null, $cursor = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($cursor !== null) {
            $parameters['cursor'] = (int) $cursor;
        }
        if ($includeEntities !== null) {
            $parameters['include_user_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'friends/list.json', $parameters, true
        );
    }

    /**
     * Returns a cursored collection of user objects for users following the specified user.
     * At this time, results are ordered with the most recent following first — however, this ordering is subject to unannounced change and eventual consistency issues. Results are given in groups of 20 users and multiple "pages" of results can be navigated through using the next_cursor value in subsequent requests. See Using cursors to navigate collections for more information.
     *
     * @param  string[optional] $userId          The ID of the user for whom to return results for.
     * @param  string[optional] $screenName      The screen name of the user for whom to return results for.
     * @param  int[optional]    $cursor          Causes the results to be broken into pages of no more than 20 records at a time. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
     * @param  bool[optional]   $includeEntities The user object entities node will be disincluded when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function followersList(
        $userId = null, $screenName = null, $cursor = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($cursor !== null) {
            $parameters['cursor'] = (int) $cursor;
        }
        if ($includeEntities !== null) {
            $parameters['include_user_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'followers/list.json', $parameters, true
        );
    }

// User resources
    /**
     * Returns settings (including current trend, geo and sleep time information) for the authenticating user.
     *
     * @return array
     */
    public function accountSettings()
    {
        // make the call
        return (array) $this->doCall(
            'account/settings.json', null, true
        );
    }

    /**
     * Returns an HTTP 200 OK response code and a representation of the requesting user if authentication was successful; returns a 401 status code and an error message if not. Use this method to test if supplied user credentials are valid.
     *
     * @param  bool[optional] $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional] $skipStatus      When set to true, statuses will not be included in the returned user objects.
     * @return array
     */
    public function accountVerifyCredentials(
        $includeEntities = null, $skipStatus = null
    )
    {
        // build parameters
        $parameters = null;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/verify_credentials.json', $parameters, true
        );
    }

    /**
     * Updates the authenticating user's settings.
     *
     * @param  string[optional] $trendLocationWoeId The Yahoo! Where On Earth ID to use as the user's default trend location. Global information is available by using 1 as the WOEID. The woeid must be one of the locations returned by trendsAvailable.
     * @param  bool[optional]   $sleepTimeEnabled   When set to true, will enable sleep time for the user. Sleep time is the time when push or SMS notifications should not be sent to the user.
     * @param  string[optional] $startSleepTime     The hour that sleep time should begin if it is enabled. The value for this parameter should be provided in ISO8601 format (i.e. 00-23). The time is considered to be in the same timezone as the user's time_zone setting.
     * @param  string[optional] $endSleepTime       The hour that sleep time should end if it is enabled. The value for this parameter should be provided in ISO8601 format (i.e. 00-23). The time is considered to be in the same timezone as the user's time_zone setting.
     * @param  string[optional] $timeZone           The timezone dates and times should be displayed in for the user. The timezone must be one of the Rails TimeZone names.
     * @param  string[optional] $lang               The language which Twitter should render in for this user. The language must be specified by the appropriate two letter ISO 639-1 representation. Currently supported languages are provided by helpLanguages.
     * @return array
     */
    public function accountSettingsUpdate(
        $trendLocationWoeId = null, $sleepTimeEnabled = null,
        $startSleepTime = null, $endSleepTime = null, $timeZone = null,
        $lang = null
    )
    {
        // build parameters
        if ($trendLocationWoeId !== null) {
            $parameters['trend_location_woeid'] = (string) $trendLocationWoeId;
        }
        if ($sleepTimeEnabled !== null) {
            if ((bool) $sleepTimeEnabled) {
                $parameters['sleep_time_enabled'] = 'true';
            } else {
                $parameters['sleep_time_enabled'] = 'false';
            }
        }
        if ($startSleepTime !== null) {
            $parameters['start_sleep_time'] = (string) $startSleepTime;
        }
        if ($endSleepTime !== null) {
            $parameters['end_sleep_time'] = (string) $endSleepTime;
        }
        if ($timeZone !== null) {
            $parameters['time_zone'] = (string) $timeZone;
        }
        if ($lang !== null) {
            $parameters['lang'] = (string) $lang;
        }

        // make the call
        return (array) $this->doCall(
            'account/settings.json', $parameters, true, 'POST'
        );
    }

    /**
     * Sets which device Twitter delivers updates to for the authenticating user. Sending none as the device parameter will disable SMS updates.
     *
     * @return array
     * @param  string         $device          Must be one of: sms, none.
     * @param  bool[optional] $includeEntities When set to true, each tweet will include a node called "entities,". This node offers a variety of metadata about the tweet in a discreet structure, including: user_mentions, urls, and hashtags. While entities are opt-in on timelines at present, they will be made a default component of output in the future. See Tweet Entities for more detail on entities.
     */
    public function accountUpdateDeliveryDevice(
        $device, $includeEntities = null
    )
    {
        // build parameters
        $parameters['device'] = (string) $device;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/update_delivery_device.json', $parameters, true, 'POST'
        );
    }

    /**
     * Sets values that users are able to set under the "Account" tab of their settings page. Only the parameters specified will be updated.
     *
     * @return array
     * @param  string[optional] $name            Full name associated with the profile. Maximum of 20 characters.
     * @param  string[optional] $url             URL associated with the profile. Will be prepended with "http://" if not present. Maximum of 100 characters.
     * @param  string[optional] $location        The city or country describing where the user of the account is located. The contents are not normalized or geocoded in any way. Maximum of 30 characters.
     * @param  string[optional] $description     A description of the user owning the account. Maximum of 160 characters.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to true, statuses will not be included in the returned user objects.
     */
    public function accountUpdateProfile($name = null, $url = null, $location = null, $description = null, $includeEntities = null, $skipStatus = null)
    {
        // build parameters
        $parameters = null;
        if ($name != null) {
            $parameters['name'] = (string) $name;
        }
        if ($url != null) {
            $parameters['url'] = (string) $url;
        }
        if ($location != null) {
            $parameters['location'] = (string) $location;
        }
        if ($description != null) {
            $parameters['description'] = (string) $description;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/update_profile.json', $parameters, true, 'POST'
        );
    }

    /**
     * Updates the authenticating user's profile background image.
     *
     * @return array
     * @param  string         $image           The path to the background image for the profile. Must be a valid GIF, JPG, or PNG image of less than 800 kilobytes in size. Images with width larger than 2048 pixels will be forceably scaled down.
     * @param  bool[optional] $tile            Whether or not to tile the background image. If set to true the background image will be displayed tiled. The image will not be tiled otherwise.
     * @param  bool[optional] $includeEntities When set to true each tweet will include a node called "entities,". This node offers a variety of metadata about the tweet in a discreet structure, including: user_mentions, urls, and hashtags.
     */
    public function accountUpdateProfileBackgroundImage($image, $tile = false, $includeEntities = null)
    {
        // validate
        if (!file_exists($image)) {
            throw new Exception('Image (' . $image . ') doesn\'t exists.');
        }

        // build parameters
        $parameters = null;
        if($tile) $parameters['tile'] = 'true';
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/update_profile_background_image.json',
            $parameters, true, 'POST', $image
        );
    }

    /**
     * Sets one or more hex values that control the color scheme of the authenticating user's profile page on twitter.com.
     * Each parameter's value must be a valid hexidecimal value, and may be either three or six characters (ex: #fff or #ffffff).
     *
     * @return array
     * @param  string[optional] $profileBackgroundColor    Profile background color.
     * @param  string[optional] $profileTextColor          Profile text color.
     * @param  string[optional] $profileLinkColor          Profile link color.
     * @param  string[optional] $profileSidebarFillColor   Profile sidebar's background color.
     * @param  string[optional] $profileSidebarBorderColor Profile sidebar's border color.
     * @param  bool[optional]   $includeEntities           When set to true each tweet will include a node called "entities,". This node offers a variety of metadata about the tweet in a discreet structure, including: user_mentions, urls, and hashtags.
     */
    public function accountUpdateProfileColors(
        $profileBackgroundColor = null, $profileTextColor = null,
        $profileLinkColor = null, $profileSidebarFillColor = null,
        $profileSidebarBorderColor = null, $includeEntities = null
    )
    {
        // validate
        if ($profileBackgroundColor == '' && $profileTextColor == '' &&
            $profileLinkColor == '' && $profileSidebarFillColor == '' &&
            $profileSidebarBorderColor == ''
        )
        {
            throw new Exception('Specify a profileBackgroundColor, profileTextColor, profileLinkColor, profileSidebarFillColor or a profileSidebarBorderColor.');
        }

        // build parameters
        if ($profileBackgroundColor != null) {
            $parameters['profile_background_color'] = (string) $profileBackgroundColor;
        }
        if ($profileTextColor != null) {
            $parameters['profile_text_color'] = (string) $profileTextColor;
        }
        if ($profileLinkColor != null) {
            $parameters['profile_link_color'] = (string) $profileLinkColor;
        }
        if ($profileSidebarFillColor != null) {
            $parameters['profile_sidebar_fill_color'] = (string) $profileSidebarFillColor;
        }
        if ($profileSidebarBorderColor != null) {
            $parameters['profile_sidebar_border_color'] = (string) $profileSidebarBorderColor;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/update_profile_colors.json', $parameters, true, 'POST'
        );
    }

    /**
     * Updates the authenticating user's profile image.
     *
     * @return array
     * @param  string         $image           The path to the avatar image for the profile. Must be a valid GIF, JPG, or PNG image of less than 700 kilobytes in size. Images with width larger than 500 pixels will be scaled down.
     * @param  bool[optional] $includeEntities When set to true each tweet will include a node called "entities,". This node offers a variety of metadata about the tweet in a discreet structure, including: user_mentions, urls, and hashtags.
     */
    public function accountUpdateProfileImage($image, $includeEntities = null)
    {
        // validate
        if (!file_exists($image)) {
            throw new Exception('Image (' . $image . ') doesn\'t exists.');
        }

        // build parameters
        $parameters = null;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'account/update_profile_image.json',
            $parameters, true, 'POST', $image
        );
    }

    /**
     * Not implemented yet
     * @param  int[optional]  $cursor          Causes the results to be broken into pages of no more than 20 records at a time. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
     * @param  bool[optional] $includeEntities The user object entities node will be disincluded when set to false.
     * @param  bool[optional] $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function blocksList(
        $cursor = null, $includeEntities = null, $skipStatus = null
    )
    {
        // build parameters
        $parameters = null;
        if ($cursor !== null) {
            $parameters['cursor'] = (int) $cursor;
        }
        if ($includeEntities !== null) {
            $parameters['include_user_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'blocks/list.json', $parameters, true
        );
    }

    /**
     * Returns an array of numeric user ids the authenticating user is blocking.
     *
     * @param  string[optional] $cursor       Causes the list of IDs to be broken into pages of no more than 5000 IDs at a time. The number of IDs returned is not guaranteed to be 5000 as suspended users are filtered out after connections are queried. If no cursor is provided, a value of -1 will be assumed, which is the first "page."
     * @param  bool[optional]   $stringifyIds Many programming environments will not consume our ids due to their size. Provide this option to have ids returned as strings instead.
     * @return array
     */
    public function blocksIds($cursor = null, $stringifyIds = null)
    {
        // build parameters
        $parameters = null;
        if ($cursor != null) {
            $parameters['cursor'] = (string) $cursor;
        }
        if ($stringifyIds !== null) {
            $parameters['stringify_ids'] = ((bool) $stringifyIds) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'blocks/ids.json', $parameters, true
        );
    }

    /**
     * Blocks the specified user from following the authenticating user. In addition the blocked user will not show in the authenticating users mentions or timeline (unless retweeted by another user). If a follow or friend relationship exists it is destroyed.
     *
     * @param  string[optional] $userId          The ID of the potentially blocked user. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @param  string[optional] $screenName      The screen name of the potentially blocked user. Helpful for disambiguating when a valid screen name is also a user ID.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function blocksCreate(
        $userId = null, $screenName = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'blocks/create.json', $parameters, true, 'POST'
        );
    }

    /**
     * Un-blocks the user specified in the ID parameter for the authenticating user. Returns the un-blocked user in the requested format when successful. If relationships existed before the block was instated, they will not be restored.
     *
     * @param  string[optional] $userId          The ID of the potentially blocked user. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @param  string[optional] $screenName      The screen name of the potentially blocked user. Helpful for disambiguating when a valid screen name is also a user ID.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function blocksDestroy(
        $userId = null, $screenName = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'blocks/destroy.json', $parameters, true, 'POST'
        );
    }

    /**
     * Returns fully-hydrated user objects for up to 100 users per request, as specified by comma-separated values passed to the user_id and/or screen_name parameters.
     *
     * @param  mixed[optional] $userIds         An array of user IDs, up to 100 are allowed in a single request.
     * @param  mixed[optional] $screenNames     An array of screen names, up to 100 are allowed in a single request.
     * @param  bool[optional]  $includeEntities The entities node that may appear within embedded statuses will be disincluded when set to false.
     * @return array
     */
    public function usersLookup(
        $userIds = null, $screenNames = null, $includeEntities = null
    )
    {
        // redefine
        $userIds = (array) $userIds;
        $screenNames = (array) $screenNames;

        // validate
        if (empty($userIds) && empty($screenNames)) {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if (!empty($userIds)) {
            $parameters['user_id'] = implode(',', $userIds);
        }
        if (!empty($screenNames)) {
            $parameters['screen_name'] = implode(',', $screenNames);
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall('users/lookup.json', $parameters, true);

    }

    /**
     * Returns a variety of information about the user specified by the required user_id or screen_name parameter.
     * The author's most recent Tweet will be returned inline when possible.
     *
     * @param  string[optional] $userId          The screen name of the user for whom to return results for. Either a id or screen_name is required for this method.
     * @param  string[optional] $screenName      The ID of the user for whom to return results for. Either an id or screen_name is required for this method.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @return array
     */
    public function usersShow(
        $userId = null, $screenName = null, $includeEntities = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : '0';
        }

        // make the call
        return (array) $this->doCall('users/show.json', $parameters);
    }

    /**
     * Run a search for users similar to the Find People button on Twitter.com; the same results returned by people search on Twitter.com will be returned by using this API.
     * Usage note: It is only possible to retrieve the first 1000 matches from this API.
     *
     * @param  string         $q               The search query to run against people search.
     * @param  int[optional]  $page            Specifies the page of results to retrieve.
     * @param  int[optional]  $count           The number of potential user results to retrieve per page. This value has a maximum of 20.
     * @param  bool[optional] $includeEntities The entities node will be disincluded from embedded tweet objects when set to false.
     * @return array
     */
    public function usersSearch(
        $q, $page = null, $count = null, $includeEntities = null
    )
    {
        // build parameters
        $parameters['q'] = (string) $q;
        if($page != null) $parameters['page'] = (int) $page;
        if($count != null) $parameters['count'] = (int) $count;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall('users/search.json', $parameters, true);
    }

    /**
     * Returns a collection of users that the specified user can "contribute" to.
     *
     * @param  string[optional] $userId          The ID of the user for whom to return results for.
     * @param  string[optional] $screenName      The screen name of the user for whom to return results for.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function usersContributees(
        $userId = null, $screenName = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'users/contributees.json', $parameters
        );
    }

    /**
     * Returns a collection of users who can contribute to the specified account.
     *
     * @param  string[optional] $userId          The ID of the user for whom to return results for.
     * @param  string[optional] $screenName      The screen name of the user for whom to return results for.
     * @param  bool[optional]   $includeEntities The entities node will not be included when set to false.
     * @param  bool[optional]   $skipStatus      When set to either true, t or 1 statuses will not be included in the returned user objects.
     * @return array
     */
    public function usersContributors(
        $userId = null, $screenName = null,
        $includeEntities = null, $skipStatus = null
    )
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }
        if ($skipStatus !== null) {
            $parameters['skip_status'] = ($skipStatus) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'users/contributors.json', $parameters
        );
    }

    /**
     * Removes the uploaded profile banner for the authenticating user.
     *
     * @return bool
     */
    public function accountRemoveProfileBanner()
    {
        $return = (array) $this->doCall(
            'account/remove_profile_banner.json', null, true, 'POST',
            null, false, true
        );

        return ($return['http_code'] == 200);
    }

    /**
     * Not implemented yet
     */
    public function accountUpdateProfileBanner()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Returns a map of the available size variations of the specified user's profile banner. If the user has not uploaded a profile banner, a HTTP 404 will be served instead.
     *
     * @param  string[optional] $userId     The ID of the user for whom to return results for. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @param  string[optional] $screenName The screen name of the user for whom to return results for. Helpful for disambiguating when a valid screen name is also a user ID.
     * @return array
     */
    public function usersProfileBanner($userId = null, $screenName = null)
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if($userId != null) $parameters['user_id'] = (string) $userId;
        if($screenName != null) $parameters['screen_name'] = (string) $screenName;

        return (array) $this->doCall(
            'users/profile_banner.json',
            $parameters, true
        );
    }

// Suggested users resources
    /**
     * Access the users in a given category of the Twitter suggested user list.
     * It is recommended that applications cache this data for no more than one hour.
     *
     * @param  string           $slug The short name of list or a category.
     * @param  string[optional] $lang Restricts the suggested categories to the requested language. The language must be specified by the appropriate two letter ISO 639-1 representation. Currently supported languages are provided by the helpLanguages API request. Unsupported language codes will receive English (en) results.
     * @return array
     */
    public function usersSuggestionsSlug($slug, $lang = null)
    {
        $parameters = null;
        if($lang != null) $parameters['lang'] = (string) $lang;

        return (array) $this->doCall(
            'users/suggestions/' . (string) $slug . '.json',
            $parameters, true
        );
    }

    /**
     * Access to Twitter's suggested user list. This returns the list of suggested user categories. The category can be used in usersSuggestionsSlug to get the users in that category.
     *
     * @param  string[optional] $lang Restricts the suggested categories to the requested language. The language must be specified by the appropriate two letter ISO 639-1 representation. Currently supported languages are provided by the helpLanguages API request. Unsupported language codes will receive English (en) results.
     * @return array
     */
    public function usersSuggestions($lang = null)
    {
        $parameters = null;
        if($lang != null) $parameters['lang'] = (string) $lang;

        return (array) $this->doCall(
            'users/suggestions.json',
            $parameters, true
        );
    }

    /**
     * Access the users in a given category of the Twitter suggested user list and return their most recent status if they are not a protected user.
     *
     * @param  string $slug The short name of list or a category
     * @return array
     */
    public function usersSuggestionsSlugMembers($slug)
    {
        return (array) $this->doCall(
            'users/suggestions/' . (string) $slug . '/members.json',
            null, true
        );
    }

// Favorites resources
    /**
     * Returns the 20 most recent Tweets favorited by the authenticating or specified user.
     *
     * @param  string[otpional] $userId          The ID of the user for whom to return results for.
     * @param  string[otpional] $screenName      The screen name of the user for whom to return results for.
     * @param  int[optional]    $count           Specifies the number of records to retrieve. Must be less than or equal to 200. Defaults to 20.
     * @param  string[otpional] $sinceId         Returns results with an ID greater than (that is, more recent than) the specified ID. There are limits to the number of Tweets which can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available.
     * @param  string[otpional] $maxId           Returns results with an ID less than (that is, older than) or equal to the specified ID.
     * @param  bool[optional]   $includeEntities The entities node will be omitted when set to false.
     * @return array
     */
    public function favoritesList(
        $userId = null, $screenName = null, $count = 20, $sinceId = null,
        $maxId = null, $includeEntities = null)
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('Specify an userId or a screenName.');
        }

        // build parameters
        $parameters = null;
        if($userId != null) $parameters['user_id'] = (string) $userId;
        if($screenName != null) $parameters['screen_name'] = (string) $screenName;
        if($count != null) $parameters['count'] = (int) $count;
        if($sinceId != null) $parameters['since_id'] = (string) $sinceId;
        if($maxId != null) $parameters['max_id'] = (string) $maxId;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall('favorites/list.json', $parameters, true);
    }

    /**
     * Un-favorites the status specified in the ID parameter as the authenticating user. Returns the un-favorited status in the requested format when successful.
     * This process invoked by this method is asynchronous. The immediately returned status may not indicate the resultant favorited status of the tweet. A 200 OK response from this method will indicate whether the intended action was successful or not.
     *
     * @return array
     * @param  string         $id              The numerical ID of the desired status.
     * @param  bool[optional] $includeEntities The entities node will be omitted when set to false.
     */
    public function favoritesDestroy($id, $includeEntities = null)
    {
        // build parameters
        $parameters['id'] = (string) $id;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'favorites/destroy.json', $parameters, true, 'POST'
        );
    }

    /**
     * Favorites the status specified in the ID parameter as the authenticating user. Returns the favorite status when successful.
     * This process invoked by this method is asynchronous. The immediately returned status may not indicate the resultant favorited status of the tweet. A 200 OK response from this method will indicate whether the intended action was successful or not.
     *
     * @param  string         $id              The numerical ID of the desired status.
     * @param  bool[optional] $includeEntities The entities node will be omitted when set to false.
     * @return array
     */
    public function favoritesCreate($id, $includeEntities = null)
    {
        // build parameters
        $parameters['id'] = (string) $id;
        if ($includeEntities !== null) {
            $parameters['include_entities'] = ($includeEntities) ? 'true' : 'false';
        }

        // make the call
        return (array) $this->doCall(
            'favorites/create.json', $parameters, true, 'POST'
        );
    }

// Lists resources
    /**
     * Not implemented yet
     */
    public function listsList()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsStatuses()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembersDestroy()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMemberships()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsSubscribers()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsSubscribersCreate()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsSubscribersShow()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsSubscribersDestroy()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembersCreateAll()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembersShow()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembers()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembersCreate()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsDestroy()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsUpdate()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsCreate()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsShow()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listSubscriptions()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Not implemented yet
     */
    public function listsMembersDestroyAll()
    {
        throw new Exception('Not implemented');
    }

// Saved Searches resources
    /**
     * Returns the authenticated user's saved search queries.
     *
     * @return array
     */
    public function savedSearchesList()
    {
        // make the call
        return (array) $this->doCall('saved_searches/list.json', null, true);
    }

    /**
     * Retrieve the information for the saved search represented by the given id. The authenticating user must be the owner of saved search ID being requested.
     *
     * @return array
     * @param  string $id The ID of the saved search.
     */
    public function savedSearchesShow($id)
    {
        // make the call
        return (array) $this->doCall(
            'saved_searches/show/' . (string) $id . '.json', null, true
        );
    }

    /**
     * Create a new saved search for the authenticated user. A user may only have 25 saved searches.
     *
     * @return array
     * @param  string $query The query of the search the user would like to save.
     */
    public function savedSearchesCreate($query)
    {
        // build parameters
        $parameters['query'] = (string) $query;

        // make the call
        return (array) $this->doCall(
            'saved_searches/create.json', $parameters, true, 'POST'
        );
    }

    /**
     * Destroys a saved search for the authenticating user. The authenticating user must be the owner of saved search id being destroyed.
     *
     * @return array
     * @param  string $id The ID of the saved search.
     */
    public function savedSearchesDestroy($id)
    {
        return (array) $this->doCall(
            'saved_searches/destroy/' . (string) $id . '.json',
            null, true, 'POST'
        );
    }

// Geo resources
    /**
     * Returns all the information about a known place.
     *
     * @param  string $id A place in the world. These IDs can be retrieved from geo/reverse_geocode.
     * @return array
     */
    public function geoId($id)
    {
        // build parameters
        $parameters = null;

        // make the call
        return (array) $this->doCall(
            'geo/id/' . (string) $id . '.json', $parameters
        );
    }

    /**
     * Given a latitude and a longitude, searches for up to 20 places that can be used as a place_id when updating a status.
     * This request is an informative call and will deliver generalized results about geography.
     *
     * @param  float            $lat         The latitude to search around. This parameter will be ignored unless it is inside the range -90.0 to +90.0 (North is positive) inclusive. It will also be ignored if there isn't a corresponding long parameter.
     * @param  float            $long        The longitude to search around. The valid ranges for longitude is -180.0 to +180.0 (East is positive) inclusive. This parameter will be ignored if outside that range, if it is not a number, if geo_enabled is disabled, or if there not a corresponding lat parameter.
     * @param  string[optional] $accuracy    A hint on the "region" in which to search. If a number, then this is a radius in meters, but it can also take a string that is suffixed with ft to specify feet. If this is not passed in, then it is assumed to be 0m. If coming from a device, in practice, this value is whatever accuracy the device has measuring its location (whether it be coming from a GPS, WiFi triangulation, etc.).
     * @param  string[optional] $granularity This is the minimal granularity of place types to return and must be one of: poi, neighborhood, city, admin or country. If no granularity is provided for the request neighborhood is assumed. Setting this to city, for example, will find places which have a type of city, admin or country.
     * @param  int[optional]    $maxResults  A hint as to the number of results to return. This does not guarantee that the number of results returned will equal max_results, but instead informs how many "nearby" results to return. Ideally, only pass in the number of places you intend to display to the user here.
     * @return array
     */
    public function geoReverseGeoCode(
        $lat, $long, $accuracy = null, $granularity = null, $maxResults = null
    )
    {
        // build parameters
        $parameters['lat'] = (float) $lat;
        $parameters['long'] = (float) $long;
        if ($accuracy != null) {
            $parameters['accuracy'] = (string) $accuracy;
        }
        if ($granularity != null) {
            $parameters['granularity'] = (string) $granularity;
        }
        if ($maxResults != null) {
            $parameters['max_results'] = (int) $maxResults;
        }

        // make the call
        return (array) $this->doCall('geo/reverse_geocode.json', $parameters);
    }

    /**
     * Search for places that can be attached to a statuses/update. Given a latitude and a longitude pair, an IP address, or a name, this request will return a list of all the valid places that can be used as the place_id when updating a status.
     * Conceptually, a query can be made from the user's location, retrieve a list of places, have the user validate the location he or she is at, and then send the ID of this location with a call to POST statuses/update.
     * This is the recommended method to use find places that can be attached to statuses/update. Unlike GET geo/reverse_geocode which provides raw data access, this endpoint can potentially re-order places with regards to the user who is authenticated. This approach is also preferred for interactive place matching with the user.
     *
     * @param  float[optional]  $lat             The latitude to search around. This parameter will be ignored unless it is inside the range -90.0 to +90.0 (North is positive) inclusive. It will also be ignored if there isn't a corresponding long parameter.
     * @param  float[optional]  $long            The longitude to search around. The valid ranges for longitude is -180.0 to +180.0 (East is positive) inclusive. This parameter will be ignored if outside that range, if it is not a number, if geo_enabled is disabled, or if there not a corresponding lat parameter.
     * @param  string[optional] $query           Free-form text to match against while executing a geo-based query, best suited for finding nearby locations by name. Remember to URL encode the query.
     * @param  string[optional] $ip              An IP address. Used when attempting to fix geolocation based off of the user's IP address.
     * @param  string[optional] $granularity     This is the minimal granularity of place types to return and must be one of: poi, neighborhood, city, admin or country. If no granularity is provided for the request neighborhood is assumed. Setting this to city, for example, will find places which have a type of city, admin or country.
     * @param  string[optional] $accuracy        A hint on the "region" in which to search. If a number, then this is a radius in meters, but it can also take a string that is suffixed with ft to specify feet. If this is not passed in, then it is assumed to be 0m. If coming from a device, in practice, this value is whatever accuracy the device has measuring its location (whether it be coming from a GPS, WiFi triangulation, etc.).
     * @param  int[optional]    $maxResults      A hint as to the number of results to return. This does not guarantee that the number of results returned will equal max_results, but instead informs how many "nearby" results to return. Ideally, only pass in the number of places you intend to display to the user here.
     * @param  string[optional] $containedWithin This is the place_id which you would like to restrict the search results to. Setting this value means only places within the given place_id will be found. Specify a place_id. For example, to scope all results to places within "San Francisco, CA USA", you would specify a place_id of "5a110d312052166f"
     * @param  array[optional]  $attributes      This parameter searches for places which have this given street address. There are other well-known, and application specific attributes available. Custom attributes are also permitted. This should be an key-value-pair-array.
     * @return array
     */
    public function geoSearch(
        $lat = null, $long = null, $query = null, $ip = null,
        $granularity = null, $accuracy = null, $maxResults = null,
        $containedWithin = null, array $attributes = null
    )
    {
        // build parameters
        $parameters = array();
        if ($lat != null) {
            $parameters['lat'] = (float) $lat;
        }
        if ($long != null) {
            $parameters['long'] = (float) $long;
        }
        if ($query != null) {
            $parameters['query'] = (string) $query;
        }
        if ($ip != null) {
            $parameters['ip'] = (string) $ip;
        }
        if ($accuracy != null) {
            $parameters['accuracy'] = (string) $accuracy;
        }
        if ($granularity != null) {
            $parameters['granularity'] = (string) $granularity;
        }
        if ($maxResults != null) {
            $parameters['max_results'] = (int) $maxResults;
        }
        if ($containedWithin != null) {
            $parameters['contained_within'] = (string) $containedWithin;
        }
        if ($attributes != null) {
            foreach ($attributes as $key => $value) {
                $parameters['attribute:' . $key] = (string) $value;
            }
        }

        // make the call
        return (array) $this->doCall('geo/search.json', $parameters);
    }

    /**
     * Locates places near the given coordinates which are similar in name.
     * Conceptually you would use this method to get a list of known places to choose from first. Then, if the desired place doesn't exist, make a request to POST geo/place to create a new one.
     * The token contained in the response is the token needed to be able to create a new place.
     *
     * @param  float            $lat             The latitude to search around. This parameter will be ignored unless it is inside the range -90.0 to +90.0 (North is positive) inclusive. It will also be ignored if there isn't a corresponding long parameter.
     * @param  float            $long            The longitude to search around. The valid ranges for longitude is -180.0 to +180.0 (East is positive) inclusive. This parameter will be ignored if outside that range, if it is not a number, if geo_enabled is disabled, or if there not a corresponding lat parameter.
     * @param  string           $name            The name a place is known as.
     * @param  string[optional] $containedWithin This is the place_id which you would like to restrict the search results to. Setting this value means only places within the given place_id will be found. Specify a place_id. For example, to scope all results to places within "San Francisco, CA USA", you would specify a place_id of "5a110d312052166f"
     * @param  array[optional]  $attributes      This parameter searches for places which have this given street address. There are other well-known, and application specific attributes available. Custom attributes are also permitted.
     * @return array
     */
    public function geoSimilarPlaces(
        $lat, $long, $name, $containedWithin = null, array $attributes = null
    )
    {
        // build parameters
        $parameters['lat'] = (float) $lat;
        $parameters['long'] = (float) $long;
        $parameters['name'] = (string) $name;
        if ($containedWithin != null) {
            $parameters['contained_within'] = (string) $containedWithin;
        }
        if ($attributes != null) {
            foreach ($attributes as $key => $value) {
                $parameters['attribute:' . $key] = (string) $value;
            }
        }

        // make the call
        return (array) $this->doCall('geo/similar_places.json', $parameters);
    }

    /**
     * Creates a new place at the given latitude and longitude.
     *
     * @param  string          $name            The name a place is known as.
     * @param  string          $containedWithin The place_id within which the new place can be found. Try and be as close as possible with the containing place. For example, for a room in a building, set the contained_within as the building place_id.
     * @param  string          $token           The token found in the response from geo/similar_places.
     * @param  float           $lat             The latitude the place is located at. This parameter will be ignored unless it is inside the range -90.0 to +90.0 (North is positive) inclusive. It will also be ignored if there isn't a corresponding long parameter.
     * @param  float           $long            The longitude the place is located at. The valid ranges for longitude is -180.0 to +180.0 (East is positive) inclusive. This parameter will be ignored if outside that range, if it is not a number, if geo_enabled is disabled, or if there not a corresponding lat parameter.
     * @param  array[optional] $attributes      This parameter searches for places which have this given street address. There are other well-known, and application specific attributes available. Custom attributes are also permitted. This should be an key-value-pair-array.
     * @return array
     */
    public function geoPlace(
        $name, $containedWithin, $token, $lat, $long, array $attributes = null
    )
    {
        // build parameters
        $parameters['name'] = (string) $name;
        $parameters['contained_within'] = (string) $containedWithin;
        $parameters['token'] = (string) $token;
        $parameters['lat'] = (float) $lat;
        $parameters['long'] = (float) $long;
        if ($attributes != null) {
            foreach ($attributes as $key => $value) {
                $parameters['attribute:' . $key] = (string) $value;
            }
        }

        // make the call
        return (array) $this->doCall(
            'geo/create.json', $parameters, true, 'POST'
        );
    }

// Trends resources
    /**
     * Returns the top 10 trending topics for a specific WOEID, if trending information is available for it.
     * The response is an array of "trend" objects that encode the name of the trending topic, the query parameter that can be used to search for the topic on Twitter Search, and the Twitter Search URL.
     * This information is cached for 5 minutes. Requesting more frequently than that will not return any more data, and will count against your rate limit usage.
     *
     * @param  string           $id      The Yahoo! Where On Earth ID of the location to return trending information for. Global information is available by using 1 as the WOEID.
     * @param  string[optional] $exclude Setting this equal to hashtags will remove all hashtags from the trends list.
     * @return array
     */
    public function trendsPlace($id, $exclude = null)
    {
        // build parameters
        $parameters['id'] = (string) $id;
        if ($exclude != null) {
            $parameters['exclude'] = (string) $exclude;
        }

        return (array) $this->doCall(
            'trends/place.json',
            $parameters
        );
    }

    /**
     * Returns the locations that Twitter has trending topic information for.
     * The response is an array of "locations" that encode the location's WOEID (a Yahoo! Where On Earth ID) and some other human-readable information such as a canonical name and country the location belongs in.
     * The WOEID that is returned in the location object is to be used when querying for a specific trend.
     *
     * @param  float[optional] $lat  If passed in conjunction with long, then the available trend locations will be sorted by distance to the lat  and long passed in. The sort is nearest to furthest.
     * @param  float[optional] $long If passed in conjunction with lat, then the available trend locations will be sorted by distance to the lat  and long passed in. The sort is nearest to furthest.
     * @return array
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
     * Returns the locations that Twitter has trending topic information for, closest to a specified location.
     * The response is an array of "locations" that encode the location's WOEID and some other human-readable information such as a canonical name and country the location belongs in.
     *
     * @param  float[optional] $lat  If provided with a long parameter the available trend locations will be sorted by distance, nearest to furthest, to the co-ordinate pair. The valid ranges for longitude is -180.0 to +180.0 (West is negative, East is positive) inclusive.
     * @param  float[optional] $long If provided with a lat parameter the available trend locations will be sorted by distance, nearest to furthest, to the co-ordinate pair. The valid ranges for longitude is -180.0 to +180.0 (West is negative, East is positive) inclusive.
     * @return array
     */
    public function trendsClosest($lat = null, $long = null)
    {
        // build parameters
        $parameters = null;
        if($lat != null) $parameters['lat'] = (float) $lat;
        if($long != null) $parameters['long'] = (float) $long;

        // make the call
        return (array) $this->doCall('trends/closest.json', $parameters);
    }

// Spam Reporting resources
    /**
     * The user specified in the id is blocked by the authenticated user and reported as a spammer.
     *
     * @param  string[optional] $screenName The ID or screen_name of the user you want to report as a spammer. Helpful for disambiguating when a valid screen name is also a user ID.
     * @param  string[optional] $userId     The ID of the user you want to report as a spammer. Helpful for disambiguating when a valid user ID is also a valid screen name.
     * @return array
     */
    public function reportSpam($screenName = null, $userId = null)
    {
        // validate
        if ($userId == '' && $screenName == '') {
            throw new Exception('One of these parameters must be provided.');
        }

        // build parameters
        if ($userId != null) {
            $parameters['user_id'] = (string) $userId;
        }
        if ($screenName != null) {
            $parameters['screen_name'] = (string) $screenName;
        }

        // make the call
        return (array) $this->doCall(
            'users/report_spam.json',
            $parameters, true, 'POST'
        );
    }

// OAuth resources
    /**
     * Allows a Consumer application to use an OAuth request_token to request user authorization. This method is a replacement fulfills Secion 6.2 of the OAuth 1.0 authentication flow for applications using the Sign in with Twitter authentication flow. The method will use the currently logged in user as the account to for access authorization unless the force_login parameter is set to true
     * REMARK: This method seems not to work	@later
     *
     * @param bool[optional] $force Force the authentication.
     */
    public function oAuthAuthenticate($force = false)
    {
        throw new Exception('Not implemented');

        // build parameters
        $parameters = null;
        if((bool) $force) $parameters['force_login'] = 'true';

        // make the call
        return $this->doCall('/oauth/authenticate.oauth', $parameters);
    }

    /**
     * Will redirect to the page to authorize the applicatione
     *
     * @param string $token The token.
     */
    public function oAuthAuthorize($token)
    {
        header('Location: ' . self::SECURE_API_URL .
               '/oauth/authorize?oauth_token=' . $token);
    }

    /**
     * Allows a Consumer application to exchange the OAuth Request Token for an OAuth Access Token.
     * This method fulfills Secion 6.3 of the OAuth 1.0 authentication flow.
     *
     * @param  string $token    The token to use.
     * @param  string $verifier The verifier.
     * @return array
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
        if (isset($response['oauth_token'])) {
            $this->setOAuthToken($response['oauth_token']);
        }
        if (isset($response['oauth_token_secret'])) {
            $this->setOAuthTokenSecret($response['oauth_token_secret']);
        }

        // return
        return $response;
    }

    /**
     * Allows a Consumer application to obtain an OAuth Request Token to request user authorization.
     * This method fulfills Secion 6.1 of the OAuth 1.0 authentication flow.
     *
     * @param  string[optional] $callbackURL The callback URL.
     * @return array            An array containg the token and the secret
     */
    public function oAuthRequestToken($callbackURL = null)
    {
        // init var
        $parameters = null;

        // set callback
        if ($callbackURL != null) {
            $parameters['oauth_callback'] = (string) $callbackURL;
        }

        // make the call
        $response = $this->doOAuthCall('request_token', $parameters);

        // validate
        if (!isset($response['oauth_token'], $response['oauth_token_secret'])) {
            throw new Exception(implode(', ', array_keys($response)));
        }

        // set some properties
        if (isset($response['oauth_token'])) {
            $this->setOAuthToken($response['oauth_token']);
        }
        if (isset($response['oauth_token_secret'])) {
            $this->setOAuthTokenSecret($response['oauth_token_secret']);
        }

        // return
        return $response;
    }

// Help resources
    /**
     * Returns the current configuration used by Twitter including twitter.com slugs which are not usernames, maximum photo resolutions, and t.co URL lengths.
     * It is recommended applications request this endpoint when they are loaded, but no more than once a day.
     *
     * @return array
     */
    public function helpConfiguration()
    {
        // make the call
        return $this->doCall(
            'help/configuration.json'
        );
    }

    /**
     * Returns the list of languages supported by Twitter along with their ISO 639-1 code. The ISO 639-1 code is the two letter value to use if you include lang with any of your requests.
     *
     * @return array
     */
    public function helpLanguages()
    {
        // make the call
        return $this->doCall(
            'help/languages.json'
        );
    }

    /**
     * Returns Twitter's Privacy Policy
     *
     * @return array
     */
    public function helpPrivacy()
    {
        // make the call
        return $this->doCall(
            'help/privacy.json'
        );
    }

    /**
     * Returns the Twitter Terms of Service in the requested format. These are not the same as the Developer Rules of the Road.
     *
     * @return array
     */
    public function helpTos()
    {
        // make the call
        return $this->doCall(
            'help/tos.json'
        );
    }

    /**
     * Returns the current rate limits for methods belonging to the specified resource families.
     * Each 1.1 API resource belongs to a "resource family" which is indicated in its method documentation. You can typically determine a method's resource family from the first component of the path after the resource version.
     * This method responds with a map of methods belonging to the families specified by the resources parameter, the current remaining uses for each of those resources within the current rate limiting window, and its expiration time in epoch time. It also includes a rate_limit_context field that indicates the current access token context.
     * You may also issue requests to this method without any parameters to receive a map of all rate limited GET methods. If your application only uses a few of methods, please explicitly provide a resources parameter with the specified resource families you work with.
     *
     * @param  array  $resources A comma-separated list of resource families you want to know the current rate limit disposition for. For best performance, only specify the resource families pertinent to your application.
     * @return string
     */
    public function applicationRateLimitStatus(array $resources = null)
    {
        $parameters = null;
        if (!empty($resources)) {
            $parameters['resources'] = implode(',', $resources);
        }

        // make the call
        return $this->doCall(
            'application/rate_limit_status.json',
            $parameters
        );
    }
}
