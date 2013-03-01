<?php

require_once '../../../autoload.php';
require_once 'config.php';
require_once 'PHPUnit/Framework/TestCase.php';

use \TijsVerkoyen\Twitter\Twitter;

/**
 * test case.
 */
class TwitterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Twitter instance
     *
     * @var	Twitter
     */
    private $twitter;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->twitter = new Twitter(CONSUMER_KEY, CONSUMER_SECRET);
        $this->twitter->setOAuthToken(OAUTH_TOKEN);
        $this->twitter->setOAuthTokenSecret(OAUTH_TOKEN_SECRET);
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->twitter = null;
        parent::tearDown();
    }

    /**
     * Test if an array is a direct message
     * @param array $item
     */
    private function isDirectMessage(array $item)
    {
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('text', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('sender', $item);
        $this->isUser($item['sender']);
        $this->assertArrayHasKey('recipient', $item);
        $this->isUser($item['recipient']);
    }

    /**
     * Test if an array is a tweet
     * @param array $item
     */
    private function isTweet(array $item)
    {
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('text', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('user', $item);
    }

    /**
     * Test if an array is a user
     * @param array $item
     */
    private function isUser(array $item)
    {
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('lang', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('screen_name', $item);
        $this->assertArrayHasKey('verified', $item);
    }

    /**
     * Test if an array is a trend
     * @param array $item
     */
    private function isTrend(array $item)
    {
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('placeType', $item);
        $this->assertArrayHasKey('code', $item['placeType']);
        $this->assertArrayHasKey('name', $item['placeType']);
        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('parentid', $item);
        $this->assertArrayHasKey('country', $item);
        $this->assertArrayHasKey('woeid', $item);
        $this->assertArrayHasKey('countryCode', $item);
    }

    /**
     * Test if an arrat is a place
     * @param array $item
     */
    private function isPlace(array $item)
    {
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('contained_within', $item);
        $this->assertArrayHasKey('place_type', $item);
        $this->assertArrayHasKey('country_code', $item);
        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('bounding_box', $item);
        $this->assertArrayHasKey('attributes', $item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('full_name', $item);
        $this->assertArrayHasKey('country', $item);
    }

    /**
     * Tests Twitter->getTimeOut()
     */
    public function testGetTimeOut()
    {
        $this->twitter->setTimeOut(5);
        $this->assertEquals(5, $this->twitter->getTimeOut());
    }

    /**
     * Tests Twitter->getUserAgent()
     */
    public function testGetUserAgent()
    {
        $this->twitter->setUserAgent('testing/1.0.0');
        $this->assertEquals('PHP Twitter/' . Twitter::VERSION . ' testing/1.0.0', $this->twitter->getUserAgent());
    }

    /**
     * Tests Twitter->statusesMentionsTimeline()
     */
    public function testStatusesMentionsTimeline()
    {
        $response = $this->twitter->statusesMentionsTimeline(2);

        $this->assertEquals(count($response), 2);
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->statusesUserTimeline()
     */
    public function testStatusesUserTimeline()
    {
        $response = $this->twitter->statusesUserTimeline(null, 'tijsverkoyen', null, 2);

        $this->assertEquals(count($response), 2);
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->statusesHomeTimeline()
     */
    public function testStatusesHomeTimeline()
    {
        $response = $this->twitter->statusesHomeTimeline(2);

        $this->assertEquals(count($response), 2);
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->statusesRetweetsOfMe()
     */
    public function testStatusesRetweetsOfMe()
    {
        $response = $this->twitter->statusesRetweetsOfMe(2);

        $this->assertEquals(count($response), 2);
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->statusesRetweets()
     */
    public function testStatusesRetweets()
    {
        $response = $this->twitter->statusesRetweets('21947795900469248', 2);

        $this->assertEquals(count($response), 2);
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->statusesShow()
     */
    public function testStatusesShow()
    {
        $response = $this->twitter->statusesShow('210462857140252672');
        $this->isTweet($response);
    }

    /**
     * Tests Twitter->statusesDestroy()
     */
    public function testStatusesDestroy()
    {
        $response = $this->twitter->statusesUpdate('Running the tests.. 私のさえずりを設定する '. time());
        $response = $this->twitter->statusesDestroy($response['id']);
        $this->isTweet($response);
    }

    /**
     * Tests Twitter->statusesUpdate()
     */
    public function testStatusesUpdate()
    {
        $response = $this->twitter->statusesUpdate('Running the tests.. 私のさえずりを設定する '. time());
        $this->isTweet($response);
        $this->twitter->statusesDestroy($response['id']);
    }

    /**
     * Tests Twitter->statusesRetweet()
     */
    public function testStatusesRetweet()
    {
        $response = $this->twitter->statusesRetweet('241259202004267009');
        $this->isTweet($response);
        $this->twitter->statusesDestroy($response['id']);
    }

    /**
     * Tests Twitter->statusesOEmbed()
     */
    public function testStatusesOEmbed()
    {
        $response = $this->twitter->statusesOEmbed('240192632003911681');
        $this->assertArrayHasKey('provider_name', $response);
        $this->assertArrayHasKey('provider_url', $response);
        $this->assertArrayHasKey('author_name', $response);
        $this->assertArrayHasKey('author_url', $response);
        $this->assertArrayHasKey('url', $response);
        $this->assertArrayHasKey('cache_age', $response);
        $this->assertArrayHasKey('type', $response);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('height', $response);
        $this->assertArrayHasKey('width', $response);
        $this->assertArrayHasKey('html', $response);
    }

    /**
     * Tests Twitter->searchTweets()
     */
    public function testSearchTweets()
    {
        $response = $this->twitter->searchTweets('#freebandnames');
        $this->assertArrayHasKey('statuses', $response);
        foreach ($response['statuses'] as $row) {
            $this->isTweet($row);
        }
        $this->assertArrayHasKey('search_metadata', $response);
        $this->assertArrayHasKey('completed_in', $response['search_metadata']);
        $this->assertArrayHasKey('max_id', $response['search_metadata']);
        $this->assertArrayHasKey('query', $response['search_metadata']);
        $this->assertArrayHasKey('refresh_url', $response['search_metadata']);
        $this->assertArrayHasKey('count', $response['search_metadata']);
        $this->assertArrayHasKey('since_id', $response['search_metadata']);
    }

    /**
     * Tests Twitter->directMessages()
     */
    public function testDirectMessages()
    {
        $response = $this->twitter->directMessages();
        foreach ($response as $row) {
            $this->isDirectMessage($row);
        }
    }

    /**
     * Tests Twitter->directMessagesSent()
     */
    public function testDirectMessagesSent()
    {
        $response = $this->twitter->directMessagesSent();
        foreach ($response as $row) {
            $this->isDirectMessage($row);
        }
    }

    /**
     * Tests Twitter->directMessagesShow()
     */
    public function testDirectMessagesShow()
    {
        $response = $this->twitter->directMessagesShow('283891767105953793');
        $this->isDirectMessage($response);
    }

    /**
     * Tests Twitter->directMessagesDestroy
     */
    public function testDirectMessagesDestroy()
    {
        $response = $this->twitter->directMessagesNew(null, 'tijs_dev', 'Running the tests.. 私のさえずりを設定する '. time());
        $response = $this->twitter->directMessagesDestroy($response['id']);
        $this->isDirectMessage($response);
    }

    /**
     * Tests Twitter->directMessagesNew
     */
    public function testDirectMessagesNew()
    {
        $response = $this->twitter->directMessagesNew(null, 'tijs_dev', 'Running the tests.. 私のさえずりを設定する '. time());
        $this->isDirectMessage($response);
        $this->twitter->directMessagesDestroy($response['id']);
    }

    /**
     * Tests Twitter->friendsIds
     */
    public function testFriendsIds()
    {
        $response = $this->twitter->friendsIds(null, 'tijsverkoyen');
        $this->assertArrayHasKey('ids', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->followersIds
     */
    public function testFollowersIds()
    {
        $response = $this->twitter->followersIds(null, 'tijsverkoyen');
        $this->assertArrayHasKey('ids', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->friendshipsLookup
     */
    public function testFriendshipsLookup()
    {
        $response = $this->twitter->friendshipsLookup(null, array('tijsverkoyen', 'sumocoders'));
        foreach ($response as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('connections', $row);
        }
    }

    /**
     * Tests Twitter->friendshipsIncoming
     */
    public function testFriendshipsIncoming()
    {
        $response = $this->twitter->friendshipsIncoming();
        $this->assertArrayHasKey('ids', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->friendshipsOutgoing
     */
    public function testFriendshipsOutgoing()
    {
        $response = $this->twitter->friendshipsOutgoing();
        $this->assertArrayHasKey('ids', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->friendshipsCreate
     */
    public function testFriendshipsCreate()
    {
        $response = $this->twitter->friendshipsCreate(null, 'tijsverkoyen');
        $this->isUser($response);
        $this->twitter->friendshipsDestroy(null, 'tijsverkoyen');
    }

    /**
     * Tests Twitter->friendshipsDestroy
     */
    public function testFriendshipsDestroy()
    {
        $response = $this->twitter->friendshipsCreate(null, 'tijsverkoyen');
        $response = $this->twitter->friendshipsDestroy(null, 'tijsverkoyen');
        $this->isUser($response);
    }

    /**
     * Tests Twitter->friendshipsUpdate
     */
    public function testFriendshipsUpdate()
    {
        $response = $this->twitter->friendshipsUpdate(null, 'sumocoders', true, true);
        $this->assertArrayHasKey('relationship', $response);
        $this->assertArrayHasKey('target', $response['relationship']);
        $this->assertArrayHasKey('followed_by', $response['relationship']['target']);
        $this->assertArrayHasKey('following', $response['relationship']['target']);
        $this->assertArrayHasKey('screen_name', $response['relationship']['target']);
        $this->assertArrayHasKey('id', $response['relationship']['target']);
        $this->assertArrayHasKey('source', $response['relationship']);
        $this->assertArrayHasKey('followed_by', $response['relationship']['source']);
        $this->assertArrayHasKey('following', $response['relationship']['source']);
        $this->assertArrayHasKey('screen_name', $response['relationship']['source']);
        $this->assertArrayHasKey('id', $response['relationship']['source']);
    }

    /**
     * Tests Twitter->friendshipsShow
     */
    public function testFriendshipsShow()
    {
        $response = $this->twitter->friendshipsShow(null, 'Bert', null, 'Ernie');
        $this->assertArrayHasKey('relationship', $response);
        $this->assertArrayHasKey('target', $response['relationship']);
        $this->assertArrayHasKey('followed_by', $response['relationship']['target']);
        $this->assertArrayHasKey('following', $response['relationship']['target']);
        $this->assertArrayHasKey('screen_name', $response['relationship']['target']);
        $this->assertArrayHasKey('id', $response['relationship']['target']);
        $this->assertArrayHasKey('source', $response['relationship']);
        $this->assertArrayHasKey('followed_by', $response['relationship']['source']);
        $this->assertArrayHasKey('following', $response['relationship']['source']);
        $this->assertArrayHasKey('screen_name', $response['relationship']['source']);
        $this->assertArrayHasKey('id', $response['relationship']['source']);
    }

    /**
     * Tests Twitter->friendsList
     */
    public function testFriendsList()
    {
        $response = $this->twitter->friendsList(null, 'tijsverkoyen');
        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);

    }

    /**
     * Tests Twitter->followersList
     */
    public function testFollowersList()
    {
        $response = $this->twitter->followersList(null, 'tijsverkoyen');
        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);

    }

    /**
     * Tests Twitter->accountSettings
     */
    public function testAccountSettings()
    {
        $response = $this->twitter->accountSettings();
        $this->assertArrayHasKey('protected', $response);
        $this->assertArrayHasKey('screen_name', $response);
        $this->assertArrayHasKey('discoverable_by_email', $response);
        $this->assertArrayHasKey('time_zone', $response);
        $this->assertArrayHasKey('tzinfo_name', $response['time_zone']);
        $this->assertArrayHasKey('name', $response['time_zone']);
        $this->assertArrayHasKey('utc_offset', $response['time_zone']);
        $this->assertArrayHasKey('use_cookie_personalization', $response);
        $this->assertArrayHasKey('sleep_time', $response);
        $this->assertArrayHasKey('enabled', $response['sleep_time']);
        $this->assertArrayHasKey('start_time', $response['sleep_time']);
        $this->assertArrayHasKey('end_time', $response['sleep_time']);
        $this->assertArrayHasKey('geo_enabled', $response);
        $this->assertArrayHasKey('always_use_https', $response);
        $this->assertArrayHasKey('language', $response);
    }

    /**
     * Tests Twitter->accountVerifyCredentials
     */
    public function testAccountVerifyCredentials()
    {
        $response = $this->twitter->accountVerifyCredentials();
        $this->isUser($response);
    }

    /**
     * Tests Twitter->accountSettingsUpdate
     */
    public function testAccountSettingsUpdate()
    {
        $response = $this->twitter->accountSettingsUpdate(null, null, null, null, null, 'en');
        $this->assertArrayHasKey('protected', $response);
        $this->assertArrayHasKey('screen_name', $response);
        $this->assertArrayHasKey('discoverable_by_email', $response);
        $this->assertArrayHasKey('time_zone', $response);
        $this->assertArrayHasKey('tzinfo_name', $response['time_zone']);
        $this->assertArrayHasKey('name', $response['time_zone']);
        $this->assertArrayHasKey('utc_offset', $response['time_zone']);
        $this->assertArrayHasKey('use_cookie_personalization', $response);
        $this->assertArrayHasKey('sleep_time', $response);
        $this->assertArrayHasKey('enabled', $response['sleep_time']);
        $this->assertArrayHasKey('start_time', $response['sleep_time']);
        $this->assertArrayHasKey('end_time', $response['sleep_time']);
        $this->assertArrayHasKey('geo_enabled', $response);
        $this->assertArrayHasKey('always_use_https', $response);
        $this->assertArrayHasKey('language', $response);
    }

    /**
     * Tests Twitter->accountUpdateDeliveryDevice
     */
    public function testAccountUpdateDeliveryDevice()
    {
        $this->markTestSkipped('No example data available at https://dev.twitter.com/docs/api/1.1/post/account/update_delivery_device');
        $response = $this->twitter->accountUpdateDeliveryDevice('none');
    }

    /**
     * Tests Twitter->accountUpdateProfile
     */
    public function testAccountUpdateProfile()
    {
        $response = $this->twitter->accountUpdateProfile(null, 'http://github.com/tijsverkoyen/TwitterOAuth');
        $this->isUser($response);
    }

    /**
     * Tests Twitter->blocksList
     */
    public function testBlocksList()
    {
        $response = $this->twitter->blocksList();
        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->blocksIds
     */
    public function testBlocksIds()
    {
        $response = $this->twitter->blocksIds();
        $this->assertArrayHasKey('ids', $response);
        $this->assertArrayHasKey('next_cursor', $response);
        $this->assertArrayHasKey('previous_cursor', $response);
    }

    /**
     * Tests Twitter->blocksCreate
     */
    public function testBlocksCreate()
    {
        $response = $this->twitter->blocksCreate(null, 'netlash');
        $this->isUser($response);
        $this->twitter->blocksDestroy(null, 'netlash');
    }

    /**
     * Tests Twitter->blocksCreate
     */
    public function testBlocksDestroy()
    {
        $response = $this->twitter->blocksCreate(null, 'netlash');
        $response = $this->twitter->blocksDestroy(null, 'netlash');
        $this->isUser($response);
    }

    /**
     * Tests Twitter->usersLookup
     */
    public function testUsersLookup()
    {
        $response = $this->twitter->usersLookup(null, array('tijsverkoyen', 'sumocoders'));
        foreach ($response as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->usersShow
     */
    public function testUsersShow()
    {
        $response = $this->twitter->usersShow(null, 'tijsverkoyen');
        $this->isUser($response);
    }

    /**
     * Tests Twitter->usersSearch
     */
    public function testUsersSearch()
    {
        $response = $this->twitter->usersSearch('Twitter API');
        foreach ($response as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->usersContributees
     */
    public function testUsersContributees()
    {
        $response = $this->twitter->usersContributees(null, 'themattharris');
        foreach ($response as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->usersContributors
     */
    public function testUsersContributors()
    {
        $response = $this->twitter->usersContributors(null, 'twitterapi');
        foreach ($response as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->accountRemoveProfileBanner
     */
    public function testAccountRemoveProfileBanner()
    {
        // @todo upload
        $response = $this->twitter->accountRemoveProfileBanner();
        $this->assertTrue($response);
    }

    /**
     * Tests Twitter->usersProfileBanner
     */
    public function testUsersProfileBanner()
    {
        $response = $this->twitter->usersProfileBanner(null, 'tijs_dev');
        $this->assertArrayHasKey('sizes', $response);
        foreach ($response['sizes'] as $row) {
            $this->assertArrayHasKey('w', $row);
            $this->assertArrayHasKey('h', $row);
            $this->assertArrayHasKey('url', $row);
        }
    }

    /**
     * Tests Twitter->usersSuggestionsSlug
     */
    public function testUsersSuggestionsSlug()
    {
        $response = $this->twitter->usersSuggestionsSlug('Twitter');
        $this->assertArrayHasKey('size', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('slug', $response);
        $this->assertArrayHasKey('users', $response);
        foreach ($response['users'] as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->usersSuggestions
     */
    public function testUsersSuggestions()
    {
        $response = $this->twitter->usersSuggestions();
        foreach ($response as $row) {
            $this->assertArrayHasKey('size', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('slug', $row);
        }
    }

    /**
     * Tests Twitter->usersSuggestionsSlugMembers
     */
    public function testUsersSuggestionsSlugMembers()
    {
        $response = $this->twitter->usersSuggestionsSlugMembers('music');
        foreach ($response as $row) {
            $this->isUser($row);
        }
    }

    /**
     * Tests Twitter->favoritesList
     */
    public function testFavoritesList()
    {
        $response = $this->twitter->favoritesList(null, 'twitter');
        foreach ($response as $row) {
            $this->isTweet($row);
        }
    }

    /**
     * Tests Twitter->favoritesDestroy
     */
    public function testFavoritesDestroy()
    {
        $response = $this->twitter->favoritesCreate('243138128959913986');
        $response = $this->twitter->favoritesDestroy('243138128959913986');
        $this->isTweet($response);
    }

    /**
     * Tests Twitter->favoritesCreate
     */
    public function testFavoritesCreate()
    {
        $response = $this->twitter->favoritesCreate('243138128959913986');
        $this->twitter->favoritesDestroy('243138128959913986');
        $this->isTweet($response);
    }

    /**
     * Tests Twitter->savedSearchesList()
     */
    public function testSavedSearchesList()
    {
        $temp = $this->twitter->savedSearchesCreate(time());
        $response = $this->twitter->savedSearchesList();
        $this->twitter->savedSearchesDestroy($temp['id']);

        foreach ($response as $row) {
            $this->assertArrayHasKey('created_at', $row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('query', $row);
        }
    }

    /**
     * Tests Twitter->savedSearchesShow()
     */
    public function testSavedSearchesShow()
    {
        $response = $this->twitter->savedSearchesCreate(time());
        $response = $this->twitter->savedSearchesShow($response['id']);
        $this->twitter->savedSearchesDestroy($response['id']);

        $this->assertArrayHasKey('created_at', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('query', $response);
    }

    /**
     * Tests Twitter->savedSearchesCreate()
     */
    public function testSavedSearchesCreate()
    {
        $response = $this->twitter->savedSearchesCreate(time());
        $this->twitter->savedSearchesDestroy($response['id']);

        $this->assertArrayHasKey('created_at', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('query', $response);
    }

    /**
     * Tests Twitter->savedSearchesDestroy()
     */
    public function testSavedSearchesDestroy()
    {
        $response = $this->twitter->savedSearchesCreate(time());
        $response = $this->twitter->savedSearchesDestroy($response['id']);

        $this->assertArrayHasKey('created_at', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('query', $response);
    }

    /**
     * Tests Twitter->geoId()
     */
    public function testGeoId()
    {
        $response = $this->twitter->geoId('df51dec6f4ee2b2c');

        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('country_code', $response);
        $this->assertArrayHasKey('place_type', $response);
        $this->assertArrayHasKey('geometry', $response);
        $this->assertArrayHasKey('polylines', $response);
        $this->assertArrayHasKey('bounding_box', $response);
        $this->assertArrayHasKey('url', $response);
        $this->assertArrayHasKey('contained_within', $response);
        $this->assertArrayHasKey('attributes', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('country', $response);
        $this->assertArrayHasKey('full_name', $response);
    }

    /**
     * Tests Twitter->geoReverseGeoCode()
     */
    public function testGeoReverseGeoCode()
    {
        $response = $this->twitter->geoReverseGeoCode(37.7821120598956, -122.400612831116);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('places', $response['result']);
        foreach ($response['result']['places'] as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('contained_within', $row);
            $this->assertArrayHasKey('place_type', $row);
            $this->assertArrayHasKey('country_code', $row);
            $this->assertArrayHasKey('url', $row);
            $this->assertArrayHasKey('bounding_box', $row);
            $this->assertArrayHasKey('attributes', $row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('full_name', $row);
            $this->assertArrayHasKey('country', $row);
        }
        $this->assertArrayHasKey('type', $response['query']);
        $this->assertArrayHasKey('params', $response['query']);
        $this->assertArrayHasKey('coordinates', $response['query']['params']);
        $this->assertArrayHasKey('accuracy', $response['query']['params']);
        $this->assertArrayHasKey('granularity', $response['query']['params']);
        $this->assertArrayHasKey('url', $response['query']);
    }

    /**
     * Tests Twitter->geoSearch()
     */
    public function testGeoSearch()
    {
        $response = $this->twitter->geoSearch(37.7821120598956, -122.400612831116);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('places', $response['result']);
        foreach ($response['result']['places'] as $row) {
            $this->isPlace($row);
        }
        $this->assertArrayHasKey('query', $response);
        $this->assertArrayHasKey('type', $response['query']);
        $this->assertArrayHasKey('params', $response['query']);
        $this->assertArrayHasKey('coordinates', $response['query']['params']);
        $this->assertArrayHasKey('autocomplete', $response['query']['params']);
        $this->assertArrayHasKey('accuracy', $response['query']['params']);
        $this->assertArrayHasKey('granularity', $response['query']['params']);
        $this->assertArrayHasKey('query', $response['query']['params']);
        $this->assertArrayHasKey('url', $response['query']);
    }

    /**
     * Tests Twitter->geoSimilarPlaces()
     */
    public function testGeoSimilarPlaces()
    {
        $response = $this->twitter->geoSimilarPlaces(37.7821120598956, -122.400612831116, 'Twitter HQ');

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('places', $response['result']);
        foreach ($response['result']['places'] as $row) {
            $this->isPlace($row);
        }
        $this->assertArrayHasKey('token', $response['result']);
        $this->assertArrayHasKey('query', $response);
        $this->assertArrayHasKey('type', $response['query']);
        $this->assertArrayHasKey('params', $response['query']);
        $this->assertArrayHasKey('coordinates', $response['query']['params']);
        $this->assertArrayHasKey('autocomplete', $response['query']['params']);
        $this->assertArrayHasKey('accuracy', $response['query']['params']);
        $this->assertArrayHasKey('name', $response['query']['params']);
        $this->assertArrayHasKey('contained_within', $response['query']['params']);
        $this->assertArrayHasKey('granularity', $response['query']['params']);
        $this->assertArrayHasKey('query', $response['query']['params']);
        $this->assertArrayHasKey('strict', $response['query']['params']);
        $this->assertArrayHasKey('url', $response['query']);
    }

    /**
     * Tests Twitter->trendsPlace()
     */
    public function testTrendsPlace()
    {
        $response = $this->twitter->trendsPlace(1);
        foreach ($response as $row) {
            $this->assertArrayHasKey('as_of', $row);
            $this->assertArrayHasKey('created_at', $row);
            $this->assertArrayHasKey('trends', $row);
            foreach ($row['trends'] as $subRow) {
                $this->assertArrayHasKey('name', $subRow);
                $this->assertArrayHasKey('promoted_content', $subRow);
                $this->assertArrayHasKey('events', $subRow);
                $this->assertArrayHasKey('url', $subRow);
                $this->assertArrayHasKey('query', $subRow);
            }
        }
    }

    /**
     * Tests Twitter->trendsAvailable()
     */
    public function testTrendsAvailable()
    {
        $response = $this->twitter->trendsAvailable();
        foreach ($response as $row) {
            $this->isTrend($row);
        }
    }

    /**
     * Tests Twitter->trendsClosest()
     */
    public function testTrendsClosest()
    {
        $response = $this->twitter->trendsClosest(37.781157, -122.400612831116);
        foreach ($response as $row) {
            $this->isTrend($row);
        }
    }

    /**
     * Tests Twitter->reportSpam()
     */
    public function testReportSpam()
    {
        $response = $this->twitter->reportSpam('FujitaKatsuhisa');
        $this->isUser($response);
    }

    /**
     * Tests Twitter->helpConfiguration()
     */
    public function testHelpConfiguration()
    {
        $response = $this->twitter->helpConfiguration();
        $this->assertArrayHasKey('characters_reserved_per_media', $response);
        $this->assertArrayHasKey('short_url_length_https', $response);
        $this->assertArrayHasKey('photo_sizes', $response);
        $this->assertArrayHasKey('non_username_paths', $response);
        $this->assertArrayHasKey('max_media_per_upload', $response);
        $this->assertArrayHasKey('photo_size_limit', $response);
        $this->assertArrayHasKey('short_url_length', $response);
    }

    /**
     * Tests Twitter->helpLanguages()
     */
    public function testHelpLanguages()
    {
        $response = $this->twitter->helpLanguages();
        foreach ($response as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('status', $row);
            $this->assertArrayHasKey('code', $row);
        }
    }

    /**
     * Tests Twitter->helpPrivacy()
     */
    public function testHelpPrivacy()
    {
        $response = $this->twitter->helpPrivacy();
        $this->assertArrayHasKey('privacy', $response);
    }

    /**
     * Tests Twitter->helpTos()
     */
    public function testHelpTos()
    {
        $response = $this->twitter->helpTos();
        $this->assertArrayHasKey('tos', $response);
    }

    /**
     * Tests Twitter->applicationRateLimitStatus()
     */
    public function testApplicationRateLimitStatus()
    {
        $response = $this->twitter->applicationRateLimitStatus();
        $this->assertArrayHasKey('rate_limit_context', $response);
        $this->assertArrayHasKey('resources', $response);
        foreach ($response['resources'] as $row) {
            foreach ($row as $subRow) {
                $this->assertArrayHasKey('limit', $subRow);
                $this->assertArrayHasKey('remaining', $subRow);
                $this->assertArrayHasKey('reset', $subRow);
            }
        }
    }
}
