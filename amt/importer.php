<?php

namespace AMWhalen\ArchiveMyTweets;

/**
 * Imports tweets from an official Twitter archive download
 */
class Importer {

    public function __construct() {

    }

    /**
     * Imports tweets from the JSON files in a downloaded Twitter Archive
     *
     * @param string $directory The directory to look for Twitter .js files.
     * @param Model $model The persistence model.
     * @return string Returns a string with informational output.
     * @author awhalen
     */
    public function importJSON($directory, $model) {

        $str = 'Importing from Twitter Archive JS Files...' . "\n";

        if (!is_dir($directory)) {
            return $str . 'Could not import from official Twitter archive. Not a valid directory: ' . $directory . "\n";
        }

        $jsFiles = glob($directory . "/*.js");
        if (count($jsFiles)) {

            // find all JS files and grab the tweets from each one
            foreach ($jsFiles as $filename) {
                $tweets = $this->getTweetsInJsonFile($filename);
                if ($tweets != false) {
                    $numFoundTweets = count($tweets);
                    $plural = ($numFoundTweets == 1) ? '' : 's';
                    $str .= basename($filename) . ': found '.$numFoundTweets.' tweet' . $plural . "\n";

                    // add
                    $numAdded = 0;
                    $result = $model->addTweets($tweets);
                    if ($result === false) {
                        $str .= 'ERROR INSERTING INTO DATABASE: ' . $model->getLastErrorMessage() . "\n";
                    } else if ($result == 0) {
                        $str .= 'No new tweets found.' . "\n";
                    } else {
                        $numAdded += $result;
                        $str .= 'Added new tweets: ' . $result . "\n";
                    }

                } else {
                    $str .= $filename . ': No tweets found' . "\n";
                }
            }

            $str .= 'JS import done. Added tweets: ' . $numAdded . "\n";

        } else {

            $str .= 'No Twitter Archive JS files found.' . "\n";

        }

        return $str;

    }

    /**
     * Returns an array of Tweet objects that are populated from a Twitter JSON file.
     *
     * @return array|false
     */
    public function getTweetsInJsonFile($filename) {

        $tweets = array();

        if (!file_exists($filename) || file_exists($filename) && !is_readable($filename)) {
            return false;
        }

        $jsonString = file_get_contents($filename);
        if ($jsonString === false) {
            return false;
        }

        // the twitter format includes extra JS code, but we just want the JSON array
        $pattern = '/\[.*\]/s';
        $matchError = preg_match($pattern, $jsonString, $matches);
        // $matchError can be zero or false if not found or there was a failure
        if (!$matchError) {
            return false;
        }
        $jsonArrayString = $matches[0];

        $jsonTweets = json_decode($jsonArrayString);
        foreach ($jsonTweets as $tweet) {
            $t = new Tweet();
            $t->load_json_object($tweet);
            $tweets[] = $t;
        }

        return $tweets;

    }

}