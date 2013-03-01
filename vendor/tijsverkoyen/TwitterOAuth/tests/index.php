<?php

//require
require_once '../../../autoload.php';
require_once 'config.php';

use \TijsVerkoyen\Twitter\Twitter;

// create instance
$twitter = new Twitter(CONSUMER_KEY, CONSUMER_SECRET);

// The code below will do the oAuth-dance
//$response = $twitter->oAuthRequestToken('http://classes.dev/TijsVerkoyen/Twitter/tests/');
//if(!isset($_GET['oauth_token'])) $response = $twitter->oAuthAuthorize($response['oauth_token']);
//$response = $twitter->oAuthAccessToken($_GET['oauth_token'], $_GET['oauth_verifier']);
//var_dump($response);
//exit;

$twitter->setOAuthToken(OAUTH_TOKEN);
$twitter->setOAuthTokenSecret(OAUTH_TOKEN_SECRET);

try {
//  $response = $twitter->statusesMentionsTimeline();
//  $response = $twitter->statusesUserTimeline();
//  $response = $twitter->statusesHomeTimeline();
//  $response = $twitter->statusesRetweetsOfMe();

//  $response = $twitter->statusesRetweets('21947795900469248');
//  $response = $twitter->statusesShow('210462857140252672');
//  $response = $twitter->statusesDestroy('264832934299705344');
//  $response = $twitter->statusesUpdate('Running the tests.. 私のさえずりを設定する '. time());
//  $response = $twitter->statusesRetweet('241259202004267009');
//  @todo $response = $twitter->statusesUpdateWithMedia();
//  $response = $twitter->statusesOEmbed('240192632003911681');

//  $response = $twitter->searchTweets('#freebandnames');

//  $response = $twitter->directMessages();
//  $response = $twitter->directMessagesSent();
//  $response = $twitter->directMessagesShow('283891767105953793');
//  $response = $twitter->directMessagesDestroy('264854339762393088');
//  $response = $twitter->directMessagesNew(null, 'tijs_dev', 'Running the tests.. 私のさえずりを設定する '. time());

//  $response = $twitter->friendsIds(null, 'tijsverkoyen');
//  $response = $twitter->followersIds(null, 'tijsverkoyen');
//  $response = $twitter->friendshipsLookup(null, 'tijsverkoyen');
//  $response = $twitter->friendshipsIncoming();
//  $response = $twitter->friendshipsOutgoing();
//  $response = $twitter->friendshipsCreate(null, 'tijsverkoyen');
//  $response = $twitter->friendshipsDestroy(null, 'tijsverkoyen');
//  $response = $twitter->friendshipsUpdate(null, 'tijsverkoyen', false, true);
//  $response = $twitter->friendshipsShow(null, 'bert', null, 'ernie');
//  $response = $twitter->friendsList(null, 'tijsverkoyen');
//  $response = $twitter->followersList(null, 'tijsverkoyen');

//	$response = $twitter->accountSettings();
//  $response = $twitter->accountVerifyCredentials();
//  $response = $twitter->accountSettingsUpdate(null, null, null, null, 'Europe/Brussels', 'it');
//  $response = $twitter->accountUpdateDeliveryDevice('none');
//  $response = $twitter->accountUpdateProfile(null, 'http://github.com/tijsverkoyen/TwitterOAuth');
//  @todo $response = $twitter->accountUpdateProfileBackgroundImage();
//  @todo $response = $twitter->accountUpdateProfileColors();
//  @todo $response = $twitter->accountUpdateProfileImage();
//  $response = $twitter->blocksList();
//  $response = $twitter->blocksIds();
//  $response = $twitter->blocksCreate(null, 'netlash');
//  $response = $twitter->blocksDestroy(null, 'netlash');
//  $response = $twitter->usersLookup(null, array('tijsverkoyen', 'sumocoders'));
//  $response = $twitter->usersShow(null, 'tijsverkoyen');
//  $response = $twitter->usersSearch('Twitter API');
//  $response = $twitter->usersContributees(null, 'themattharris');
//  $response = $twitter->usersContributors(null, 'twitterapi');
//  $response = $twitter->accountRemoveProfileBanner();
//  $response = $twitter->accountUpdateProfileBanner();
//  $response = $twitter->usersProfileBanner(null, 'tijs_dev');

//  $response = $twitter->usersSuggestionsSlug('twitter');
//  $response = $twitter->usersSuggestions();
//  $response = $twitter->usersSuggestionsSlugMembers('music');

//  $response = $twitter->favoritesList(null, 'twitter');
//  $response = $twitter->favoritesDestroy('243138128959913986');
//  $response = $twitter->favoritesCreate('243138128959913986');

//  @todo $response = $twitter->listsList();
//  @todo $response = $twitter->listsStatuses();
//  @todo $response = $twitter->listsMembersDestroy();
//  @todo $response = $twitter->listsMemberships();
//  @todo $response = $twitter->listsSubscribers();
//  @todo $response = $twitter->listsSubscribersCreate();
//  @todo $response = $twitter->listsSubscribersShow();
//  @todo $response = $twitter->listsSubscribersDestroy();
//  @todo $response = $twitter->listsMembersCreateAll();
//  @todo $response = $twitter->listsMembersShow();
//  @todo $response = $twitter->listsMembers():
//  @todo $response = $twitter->listsMembersCreate();
//  @todo $response = $twitter->listsDestroy();
//  @todo $response = $twitter->listsUpdate();
//  @todo $response = $twitter->listsCreate();
//  @todo $response = $twitter->listsShow();
//  @todo $response = $twitter->listsSubscriptions();
//  @todo $response = $twitter->listsMembersDestroyAll();

//  $response = $twitter->savedSearchesList();
//  $response = $twitter->savedSearchesShow('3205644');
//  $response = $twitter->savedSearchesCreate(time());
//  $response = $twitter->savedSearchesDestroy('3205644');

//  $response = $twitter->geoId('df51dec6f4ee2b2c');
//  $response = $twitter->geoReverseGeoCode(37.7821120598956, -122.400612831116);
//  $response = $twitter->geoSearch(37.7821120598956, -122.400612831116);
//  $response = $twitter->geoSimilarPlaces(37.7821120598956, -122.400612831116, 'Twitter HQ');
//  $response = $twitter->geoPlace('Twitter HQ', '247f43d441defc03', '36179c9bf78835898ebf521c1defd4be', 37.7821120598956, -122.400612831116, array('street_address' => '795 Folsom St'));

//  $response = $twitter->trendsPlace(1);
//  $response = $twitter->trendsAvailable();
//  $response = $twitter->trendsClosest(37.781157, -122.400612831116);

//  $response = $twitter->reportSpam('FujitaKatsuhisa');

//  $response = $twitter->helpConfiguration();
//  $response = $twitter->helpLanguages();
//  $response = $twitter->helpPrivacy();
//  $response = $twitter->helpTos();
//  $response = $twitter->applicationRateLimitStatus();
} catch (Exception $e) {
    var_dump($e);
}

// output
var_dump($response);
