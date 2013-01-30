<p class="lead">
	Your Twitter archive has been installed!
</p>

<p>Next steps:</p>

<ul>
	<li>If you have more than 3200 tweets you'll need to <a href="https://github.com/amwhalen/archive-my-tweets#importing-your-official-twitter-archive" target="_blank">download and import your official twitter archive</a> to see your oldest tweets.</li>
	<li>Remember to <a href="https://github.com/amwhalen/archive-my-tweets#setting-up-a-cron-job" target="_blank">setup a cron job</a> so your newest tweets will continue to be fed into this archive.</li>
	<li>If you need to make any changes to your settings, just modify the config.php file.</li>
</ul>

<p><a href="" class="btn btn-primary btn-large">See My Twitter Archive &raquo;</a></p>

<h3>Archiver Output</h3>
<p>Your most recent tweets should have been imported. Check the output below to make sure there were no errors during the process.</p>
<pre><?php echo $archiverOutput; ?></pre>