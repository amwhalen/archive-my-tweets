<?php

namespace AMWhalen\ArchiveMyTweets;
use DateTimeZone;

require_once dirname(__FILE__).'/view.php';
require_once dirname(__FILE__).'/model.php';
require_once dirname(__FILE__).'/../vendor/tijsverkoyen/TwitterOAuth/Twitter.php';
require_once dirname(__FILE__).'/../vendor/tijsverkoyen/TwitterOAuth/Exception.php';
require_once dirname(__FILE__).'/archiver.php';

/**
 * Installer
 */
class Installer {

    protected $directory;
    protected $view;
    protected $data;
    protected $model;
    protected $twitter;

    /**
     * Constructor
     *
     * @param string $directory The directory to install in.
     */
    function __construct($directory) {
        $this->directory = $directory;
        $this->view = new View($directory.'/themes/install');
        $this->data = array();
    }

    /**
     * Run the installer
     */
    public function run() {

        $this->step1();

    }

    /**
     * Step 1: verify requirements and display config form
     */
    public function step1($formErrors=array()) {

        $problems = $this->verifyRequirements();
        $this->data['errors'] = $problems['errors'];
        $this->data['warnings'] = $problems['warnings'];

        $this->data['timezones'] = $this->getTimeZones();

        // form data
        $this->data['form']['twitterUsername']  = (isset($_POST['twitterUsername']) && $_POST['twitterUsername']) ? $_POST['twitterUsername'] : '';
        $this->data['form']['twitterName']      = (isset($_POST['twitterName']) && $_POST['twitterName']) ? $_POST['twitterName'] : '';
        $this->data['form']['consumerKey']      = (isset($_POST['consumerKey']) && $_POST['consumerKey']) ? $_POST['consumerKey'] : '';
        $this->data['form']['consumerSecret']   = (isset($_POST['consumerSecret']) && $_POST['consumerSecret']) ? $_POST['consumerSecret'] : '';
        $this->data['form']['oauthToken']       = (isset($_POST['oauthToken']) && $_POST['oauthToken']) ? $_POST['oauthToken'] : '';
        $this->data['form']['oauthSecret']      = (isset($_POST['oauthSecret']) && $_POST['oauthSecret']) ? $_POST['oauthSecret'] : '';
        $this->data['form']['baseUrl']          = (isset($_POST['baseUrl']) && $_POST['baseUrl']) ? $_POST['baseUrl'] : $this->guessBaseUrl();
        $this->data['form']['timezone']         = (isset($_POST['timezone']) && $_POST['timezone']) ? $_POST['timezone'] : 'America/New_York';
        $this->data['form']['cronKey']          = (isset($_POST['cronKey']) && $_POST['cronKey']) ? $_POST['cronKey'] : '';
        $this->data['form']['databaseHost']     = (isset($_POST['databaseHost']) && $_POST['databaseHost']) ? $_POST['databaseHost'] : 'localhost';
        $this->data['form']['databaseDatabase'] = (isset($_POST['databaseDatabase']) && $_POST['databaseDatabase']) ? $_POST['databaseDatabase'] : '';
        $this->data['form']['databaseUsername'] = (isset($_POST['databaseUsername']) && $_POST['databaseUsername']) ? $_POST['databaseUsername'] : '';
        $this->data['form']['databasePassword'] = (isset($_POST['databasePassword']) && $_POST['databasePassword']) ? $_POST['databasePassword'] : '';
        $this->data['form']['databasePrefix']   = (isset($_POST['databasePrefix']) && $_POST['databasePrefix']) ? $_POST['databasePrefix'] : 'amt_';

        // form submission
        if (isset($_POST['installer'])) {

            $installProblem = false;

            // validate form data
            $formErrors = $this->validateFormData($this->data['form']);
            if ($formErrors !== false) {

                $installProblem = true;
                $this->data['formErrors'] = $formErrors;

            }

            // test database connection
            if (!$installProblem) {
                try {
                    $dsn  = "mysql:host=".$this->data['form']['databaseHost'].";dbname=".$this->data['form']['databaseDatabase'].";charset=utf8";
                    $user = $this->data['form']['databaseUsername'];
                    $pass = $this->data['form']['databasePassword'];
                    $db = new \PDO($dsn, $user, $pass);
                    $this->model = new Model($db, $this->data['form']['databasePrefix']);
                } catch (\Exception $e) {
                    $installProblem = true;
                    $this->data['databaseErrors'] = 'There was a problem connecting to your database: ' . $e->getMessage();
                }
            }

            // test twitter connection
            if (!$installProblem) {

                $this->twitter = new \TijsVerkoyen\Twitter\Twitter($this->data['form']['consumerKey'], $this->data['form']['consumerSecret']);
                $this->twitter->setOAuthToken($this->data['form']['oauthToken']);
                $this->twitter->setOAuthTokenSecret($this->data['form']['oauthSecret']);

                try {
                    $tweetResults = $this->twitter->statusesUserTimeline(null, $this->data['form']['twitterUsername']);
                } catch (\Exception $e) {
                    $installProblem = true;
                    $this->data['twitterErrors'] = 'There was a problem connecting to twitter: ' . $e->getMessage();
                }
            }

            // use model to install database, if it fails, return form with errors
            if (!$installProblem) {
                try {
                    $this->model->install();
                } catch (\Exception $e) {
                    $installProblem = true;
                    $this->data['databaseErrors'] = 'There was a problem while installing the database tables: ' . $e->getMessage();
                }

                // make sure
                if (!$this->model->isInstalled()) {
                    $installProblem = true;
                    $this->data['databaseErrors'] = 'The database tables were not installed for some unknown reason.';
                }
            }

            // write the config.php file to disk, it it fails, return form with errors
            if (!$installProblem) {
                $written = $this->writeConfig($this->directory, $this->data['form']);
                if (!$written) {
                    $installProblem = true;
                    $this->data['configError'] = 'The config.php file could not be written. Please check the permissions on this directory: '.$this->directory;
                }
            }

            // after all this, if there were no problems, then redirect to step 2
            if (!$installProblem) {
                return $this->step2();
            }

        }

        $this->data['content'] = $this->view->render('install01.php', $this->data, true);
        $this->view->render('_layout.php', $this->data);

    }

    /**
     * Step 2: archive and display
     */
    public function step2() {

        // run the archiver
        $archiver = new Archiver($this->data['form']['twitterUsername'], $this->twitter, $this->model);
        $this->data['archiverOutput'] = $archiver->archive();

        $this->data['content'] = $this->view->render('install02.php', $this->data, true);
        $this->view->render('_layout.php', $this->data);

    }

    /**
     * Validates form data and returns an array of errors if any, or false if all data validates
     */
    public function validateFormData($data) {

        $errors = array();

        // twitter name
        if (strlen($data['twitterName']) < 1 || strlen($data['twitterName']) > 100) {
            $errors['twitterName'] = 'Full name is required and must be less than 100 characters.';
        }

        // twitter username
        if (!preg_match("/^[a-zA-Z0-9_]+$/", $data['twitterUsername']) || strlen($data['twitterUsername']) < 1 || strlen($data['twitterUsername']) > 15) {
            $errors['twitterUsername'] = 'Username must only use letters, numbers, underscores, and be 15 or fewer characters in length.';
        }

        // cron key
        if (!preg_match("/^[a-zA-Z0-9_]+$/", $data['cronKey']) || strlen($data['cronKey']) < 1 || strlen($data['cronKey']) > 50) {
            $errors['cronKey'] = 'The cron key must only use letters, numbers, underscores, and be 50 or fewer characters in length.';
        }

        // timezone
        if (!date_default_timezone_set($data['timezone'])) {
            $errors['timezone'] = 'Not a valid timezone.';
        }

        // database prefix
        if (!preg_match("/^[a-zA-Z0-9_]+$/", $data['databasePrefix']) || strlen($data['databasePrefix']) < 1 || strlen($data['databasePrefix']) > 15) {
            $errors['databasePrefix'] = 'The database prefix must only use letters, numbers, underscores, and be 15 or fewer characters in length.';
        }

        // do one last check to require all fields without a specific check
        $requiredFields = array('twitterName', 'twitterUsername', 'consumerKey', 'consumerSecret', 'oauthToken', 'oauthSecret', 'baseUrl', 'timezone', 'cronKey', 'databaseHost', 'databaseDatabase', 'databaseUsername', 'databasePassword', 'databasePrefix');
        foreach ($requiredFields as $field) {
            if (!isset($errors[$field]) && strlen(trim($data[$field])) < 1) {
                $errors[$field] = 'This field is required.';
            }
        }

        return (count($errors)) ? $errors : false;

    }

    /**
     * Guesses the base URL for this app
     */
    protected function guessBaseUrl() {

        $pageURL = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return str_replace('index.php', '', $pageURL);

    }

    /**
     * Returns a list of valid timezones
     */
    public function getTimeZones() {

        $regions = array(
            'Africa'     => DateTimeZone::AFRICA,
            'America'    => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Asia'       => DateTimeZone::ASIA,
            'Atlantic'   => DateTimeZone::ATLANTIC,
            'Europe'     => DateTimeZone::EUROPE,
            'Indian'     => DateTimeZone::INDIAN,
            'Pacific'    => DateTimeZone::PACIFIC
        );

        $tzlist = array();
        foreach ($regions as $name => $mask) {
            $tzlist = array_merge($tzlist, DateTimeZone::listIdentifiers($mask));
        }

        return $tzlist;

    }

    /**
     * Writes the config.php file
     */
    public function writeConfig($directory, $data) {

        $config = "<?php\n";

        // timezone
        $config .= "date_default_timezone_set('".htmlspecialchars($data['timezone'], ENT_QUOTES)."');\n";

        // define all config settings
        $config .= "define('TWITTER_USERNAME',        '".htmlspecialchars($data['twitterUsername'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_NAME',            '".htmlspecialchars($data['twitterName'], ENT_QUOTES)."');\n";
        $config .= "define('BASE_URL',                '".htmlspecialchars($data['baseUrl'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_CONSUMER_KEY',    '".htmlspecialchars($data['consumerKey'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_CONSUMER_SECRET', '".htmlspecialchars($data['consumerSecret'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_OAUTH_TOKEN',     '".htmlspecialchars($data['oauthToken'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_OAUTH_SECRET',    '".htmlspecialchars($data['oauthSecret'], ENT_QUOTES)."');\n";
        $config .= "define('DB_USERNAME',             '".htmlspecialchars($data['databaseUsername'], ENT_QUOTES)."');\n";
        $config .= "define('DB_PASSWORD',             '".htmlspecialchars($data['databasePassword'], ENT_QUOTES)."');\n";
        $config .= "define('DB_NAME',                 '".htmlspecialchars($data['databaseDatabase'], ENT_QUOTES)."');\n";
        $config .= "define('TWITTER_CRON_SECRET',     '".htmlspecialchars($data['cronKey'], ENT_QUOTES)."');\n";
        $config .= "define('DB_TABLE_PREFIX',         '".htmlspecialchars($data['databasePrefix'], ENT_QUOTES)."');\n";
        $config .= "define('DB_HOST',                 '".htmlspecialchars($data['databaseHost'], ENT_QUOTES)."');\n";

        $filename = $directory . '/config.php';
        $written = file_put_contents($filename, $config);
        return ($written) ? true : false;

    }

    /**
     * Checks that all installation requirements are met
     *
     * @return array Returns an array of error messages for missing requirements.
     */
    public function verifyRequirements() {

        $problems = array(
            'warnings' => array(),
            'errors' => array()
        );

        // Requirement: PHP 5.3
        if (version_compare(phpversion(), '5.3.0') < 0) {
            $problems['errors'][] = 'PHP 5.3.0 or greater is required. Your installed version is '.phpversion().'.';
        }

        // Requirement: PDO
        if (!class_exists('PDO')) {
            $problems['errors'][] = 'The PHP Data Objects (PDO) extension is required.';
        }

        // Requirement: mod_rewrite
        if (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) {
            $problems['errors'][] = 'The Apache mod_rewrite module is required.';
        }

        // Requirement: cURL
        if (!function_exists('curl_init')) {
            $problems['errors'][] = 'The PHP cURL extension is required.';
        }

        // Requirement: writable config.php
        if (!is_writable($this->directory)) {
            $problems['errors'][] = 'The directory for your config.php file is not writable. Please change the permissions on this directory (through SSH or your FTP client) so it writable to all users. When this installer is completed, you can change the permissions back. The directory to make writable: <code>'.$this->directory.'</code>';
        }

        // Optional: json_decode
        if (!function_exists('json_decode')) {
            $problems['warnings'][] = 'Your version of PHP is missing the <code>json_decode()</code> function. This is included and enabled by default for PHP versions 5.2.0 and higher. This is only required if you want to import tweets from an official twitter archive download, otherwise Archive My Tweets can run without it.';
        }

        // Optional: 64-bit integers
        if (PHP_INT_SIZE != 8) {
            $problems['warnings'][] = 'Your PHP installation does not support 64 bit integers and this may cause problems for you. Support for 64 bit integers is recommended and is offered by most modern web hosts.';
        }

        return $problems;

    }

}