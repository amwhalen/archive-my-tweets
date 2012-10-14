Archive My Tweets
=================

Archive your tweets to easily browse and search them - all on your own website and in your control. See an example installation on my website: http://amwhalen.com/twitter/.

Installation
------------

1. Download the archive-my-tweets source code and put it into a directory on your LAMP web server (e.g. /tweets/).
2. Copy config.example.php to config.php and edit it so it contains your Twitter info and tokens (see below), database info, and cron secret key.
3. Visit /tweets/cron.php?secret=YOUR-CRON-SECRET-KEY to install and load your tweets.
4. Go to /tweets/ to view and search your tweets.

Getting Twitter API Tokens
--------------------------

1. Visit https://dev.twitter.com/apps/new and sign in with your Twitter credentials.
2. Fill in the Name and Description with whatever you'd like.
3. Fill in the Website and Callback fields with the URL of your twitter archive, e.g. http://amwhalen.com/twitter/.
4. Save your information and put the keys and tokens into your config.php file.

Profile Picture
---------------

You can replace the file img/avatar.png with your own profile picture. It should be sized at 73x73 pixels. If you want to use a different file name, just change the location of the image in the index.php file.

Setting Up a Cron Job
---------------------

If you want to automatically update your tweets you'll need to set up a cron job. You can find more information on Cron elsewhere, but here's an example that run your cron.php every hour of the day:

	0 * * * * /usr/bin/php /path/to/the/cron.php

If you want to set up the cron remotely, use this instead:

	0 * * * * /usr/bin/wget -O - -q -t 1 http://example.com/tweets/cron.php?secret=MY_SECRET

The "secret" is so that only you can run the cron script instead of just any visitor. This will protect your Twitter API limit (350 requests per hour), which is tied to your username. If you don't have wget installed on your server, you could try to use cURL instead:

	0 * * * * /usr/bin/curl --silent --compressed http://example.com/tweets/cron.php?secret=MY_SECRET

FAQ
---

* **Why aren't my monthly /archive/ pages working?** Your FTP client may have missed uploading the .htaccess file. Make sure your client is configured to upload "hidden" files like this.

* **Why is my cron.php page blank when I access it?** Your server may need cURL support in PHP. See the [PHP Docs for installing cURL](http://www.php.net/manual/en/curl.setup.php).
