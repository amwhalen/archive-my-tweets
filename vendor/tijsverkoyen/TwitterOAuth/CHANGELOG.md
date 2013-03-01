# Changelog since 2.3.1

* Fixed faulty composer.json-file.

# Changelog since 2.3.0

* Fixed blocksCreate, blocksDestroy, usersLookup, usersShow, usersSearch to reflect version 1.1 of the API.
* Implemented usersContributees, usersContributors.
* Implemented friendshipsLookup, friendshipsUpdate, friendsList, followersList.
* Implemented blocksIds, blocksList.
* Implemented accountSettings, accountSettingsUpdate, accountRemoveProfileBanner, usersProfileBanner.

# Changelog since 2.2.1

* Fixed issue with wrong classname for Exceptions
* Fixed issue with reference to class itself
* Removed/Updated methods to reflect the current Twitter API

# Changelog since 2.2.0

* Made the class available through composer

# Changelog since 2.1.2

* Made it compliant with PSR

# Changelog since 2.1.1

* Code styling
* No more converting to integer for the cursor (thx to Jamaica)

# Changelog since 2.1.0

* Fixed issue with generation of basestring
* Added a new method: http://dev.twitter.com/doc/post/:user/:list_id/create_all

# Changelog since 2.0.3

* Made a lot of changes to reflect the current API, some of the methods aren't backwards compatible, so be carefull before upgrading

# Changelog since 2.0.2

* Tested geo*
* Implemented accountUpdateProfileImage
* Implemented accountUpdateProfileBackgroundImage
* Fixed issue with GET and POST (thx to Luiz Felipe)
* Added a way to detect open_basedir (thx to Lee Kindness)

# Changelog since 2.0.1

* Fixed some documentation
* Added a new method: usersProfileImage
* Fixed trendsLocation
* Added new GEO-methods: geoSearch, geoSimilarPlaces, geoPlaceCreate (not tested because geo-services were disabled.)
* Added legalToS
* Added legalPrivacy
* Fixed helpTest

# Changelog since 2.0.0

* No more fatal if twitter is over capacity
* Fix for calculating the header-string (thx to Dextro)
* Fix for userListsIdStatuses (thx to Josh)