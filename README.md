Archive My Tweets
=================

Archive your tweets to easily browse and search them - all on your own website and in your control. See an example installation on my website: http://amwhalen.com/twitter/.

![archive my tweets](https://raw.github.com/amwhalen/archive-my-tweets/master/img/screenshots/amt.png)

Server Requirements
-------------------

* PHP 5.3.0+ with cURL and PDO
* MySQL


Installation
------------

1. [Download the archive-my-tweets source code](https://github.com/amwhalen/archive-my-tweets/archive/master.zip), unzip it, and upload it into a directory on your LAMP web server (e.g. /tweets/).
2. Visit the installation location in your web browser, e.g. http://www.example.com/tweets/.
3. Fill out the form with your information and save. That's it!


Getting Twitter API Tokens
--------------------------

Installation requires that you register your "app" with Twitter. Below is the process:

1. Visit https://dev.twitter.com/apps/new and sign in with your Twitter credentials.
2. Fill in the Name and Description with whatever you'd like.
3. Fill in the Website and Callback fields with the URL of your twitter archive, e.g. http://amwhalen.com/twitter/.
4. Save your information and put the keys and tokens into your config.php file.


Setting Up a Cron Job
---------------------

If you want to automatically update your tweets you'll need to set up a cron job. You can find more information on Cron elsewhere, but here's an example that will run your cron.php every hour of the day:

	0 * * * * /usr/bin/env php /path/to/the/cron.php

If you want to set up the cron job to run remotely, use this instead:

	0 * * * * /usr/bin/env wget -O - -q -t 1 http://example.com/tweets/cron.php?secret=MY_SECRET

The "secret" is so that only you can run the cron script instead of just any visitor. This will protect your Twitter API limit (350 requests per hour), which is tied to your username. If you don't have wget installed on your server, you could try to use cURL instead:

	0 * * * * /usr/bin/env curl --silent --compressed http://example.com/tweets/cron.php?secret=MY_SECRET
	
You could also use webcron service (like http://www.easycron.com) for setting cron jobs up.



Importing Your Official Twitter Archive
---------------------------------------

If you started using Archive My Tweets after you already had 3200 tweets, then you're in luck. It's now possible to import your older tweets from your downloaded twitter archive.

Twitter now allows most accounts (they're still rolling this out) to download an official archive of all your tweets from the beginning of time. This is great news, and especially amazing is the JavaScript app they've included with it to browse and search your tweets.

To import the archive follow these steps:

1. Visit your Twitter account settings: [https://twitter.com/settings/account](https://twitter.com/settings/account)
2. Near the bottom of the settings page there should be a button to download your archive. (If you don't see it yet, you may have to wait until it's rolled out to all accounts.)
3. Once you've downloaded and unzipped your archive, copy all of the .js files in the data/js/tweets/ folder over to your server into the 'json' folder.
4. Manually run your cron.php file, or wait until your next scheduled update. Check for any errors in the output to make sure it's loading in your tweets.

![js files](https://raw.github.com/amwhalen/archive-my-tweets/master/img/screenshots/jsfiles.png)

You'll only have to do this one time, as the cron.php running regularly will import all your newest tweets. Tweets that are already in your database will be ignored, so don't worry about duplication.


Upgrading
---------

Just replace all of the old files with the new files. If you're using an FTP client, the easiest way is to upload and overwrite the entire directory.

If you're running a cloned copy from GitHub, just do a 'git pull' to grab the latest code.

Whichever method you choose, just remember to keep your customized config.php file in place.


Profile Picture
---------------

You can replace the file img/avatar.png with your own profile picture. If you want to use a different file name, just change the location of the image in the themes/default/_layout.php file.


FAQ
---

* **Why aren't my monthly /archive/ pages working?** Your FTP client may have missed uploading the .htaccess file. Make sure your client is configured to upload "hidden" files like this.

* **Why is my cron.php page blank when I access it?** Your server may need cURL support in PHP. See the [PHP Docs for installing cURL](http://www.php.net/manual/en/curl.setup.php).

* **Why don't my older tweets show up?** Twitter limits API calls to return only the most recent 3200 tweets from any user's timeline. See the Importing Your Official Twitter Archive section for a solution.

* **My server doesn't have PHP 5.3.0 or higher, what can I do?** You can download the [old code that supports PHP 5.2 or higher](https://github.com/amwhalen/archive-my-tweets/archive/php52.zip). Beware that this code will not receive any updates.

License
-------

Archive My Tweets is released under the terms of the [MIT License](http://www.opensource.org/licenses/mit-license.html).

The MIT License (MIT)
Copyright (c) 2013 Andrew M. Whalen

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
