<?php

if (count($errors)) {
	
	?>
	<p class="lead">
		Sorry!
		Archive My Tweets can't be installed because your server does not meet the minimum requirements.
		There may be more information about these errors in the <a href="https://github.com/amwhalen/archive-my-tweets" target="_blank">documentation</a>.
	</p>
	<?php

	foreach ($errors as $error) {
		echo '<div class="alert alert-error">'.$error.'</div>';
	}
	
	foreach ($warnings as $warning) {
		echo '<div class="alert alert-warning">'.$warning.'</div>';
	}

} else {

	foreach ($warnings as $warning) {
		echo '<div class="alert alert-warning">'.$warning.'</div>';
	}

	if (isset($formErrors)) {
		echo '<div class="alert alert-error">There were errors in your form. See below.</div>';
	}

	if (isset($databaseErrors)) {
		echo '<div class="alert alert-error">'.$databaseErrors.'</div>';
	}

	if (isset($twitterErrors)) {
		echo '<div class="alert alert-error">'.$twitterErrors.'</div>';
	}

?>

<p class="lead">
	You're just one step away from your own Twitter archive!
	Fill out and submit this form to install Archive My Tweets on your server.
	You can change these settings any time after installation by modifying your <code>config.php</code> file.
	Find out more in the <a href="https://github.com/amwhalen/archive-my-tweets" target="_blank">documentation</a>.
</p>

<form class="form-horizontal" action="index.php" method="post">

	<input type="hidden" name="installer" value="1">

	<fieldset>
	<legend>Account</legend>

		<div class="control-group<?php if (isset($formErrors['twitterUsername'])) { echo ' error'; } ?>">
			<label class="control-label" for="twitterUsername">Twitter Username</label>
			<div class="controls">
				<div class="input-prepend">
					<span class="add-on">@</span>
					<input class="span2" id="twitterUsername" name="twitterUsername" type="text" placeholder="awhalen" value="<?php echo htmlentities($form['twitterUsername']); ?>">
				</div>
				<?php if (isset($formErrors['twitterUsername'])) { echo '<div class="help-block">'.$formErrors['twitterUsername'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['twitterName'])) { echo ' error'; } ?>">
			<label class="control-label" for="twitterName">Your Full Name</label>
			<div class="controls">
				<input class="span4" type="text" id="twitterName" name="twitterName" placeholder="Andrew M. Whalen" value="<?php echo htmlentities($form['twitterName']); ?>">
				<?php if (isset($formErrors['twitterName'])) { echo '<div class="help-block">'.$formErrors['twitterName'].'</div>'; } ?>
			</div>
		</div>

	</fieldset>

	<fieldset>
    <legend>Twitter App Credentials</legend>

		<div class="control-group">
			<label class="control-label" for="apiHelp"></label>
			<div class="controls">
				<div class="help-block">
					The consumer and oauth tokens are required to properly retrieve all of your data from the Twitter API.
					<ol>
						<li>Visit <a href="https://dev.twitter.com/apps/new" target="_blank">https://dev.twitter.com/apps/new</a> and sign in with your Twitter credentials.</li>
						<li>Fill in the Name and Description with whatever you'd like.</li>
						<li>Fill in the Website and Callback fields with the URL of your twitter archive, e.g. http://amwhalen.com/twitter/.</li>
						<li>Save your information and put the keys and tokens into this form.</li>
					</ol>
				</div>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['consumerKey']) || isset($twitterErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="consumerKey">Consumer Key</label>
			<div class="controls">
				<input class="span6" type="text" id="consumerKey" name="consumerKey" value="<?php echo htmlentities($form['consumerKey']); ?>">
				<?php if (isset($formErrors['consumerKey'])) { echo '<div class="help-block">'.$formErrors['consumerKey'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['consumerSecret']) || isset($twitterErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="consumerSecret">Consumer Secret</label>
			<div class="controls">
				<input class="span6" type="text" id="consumerSecret" name="consumerSecret" value="<?php echo htmlentities($form['consumerSecret']); ?>">
				<?php if (isset($formErrors['consumerSecret'])) { echo '<div class="help-block">'.$formErrors['consumerSecret'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['oauthToken']) || isset($twitterErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="oauthToken">Twitter OAuth Token</label>
			<div class="controls">
				<input class="span6" type="text" id="oauthToken" name="oauthToken" value="<?php echo htmlentities($form['oauthToken']); ?>">
				<?php if (isset($formErrors['oauthToken'])) { echo '<div class="help-block">'.$formErrors['oauthToken'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['oauthSecret']) || isset($twitterErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="oauthSecret">Twitter OAuth Secret</label>
			<div class="controls">
				<input class="span6" type="text" id="oauthSecret" name="oauthSecret" value="<?php echo htmlentities($form['oauthSecret']); ?>">
				<?php if (isset($formErrors['oauthSecret'])) { echo '<div class="help-block">'.$formErrors['oauthSecret'].'</div>'; } ?>
			</div>
		</div>

	</fieldset>

	<fieldset>
    <legend>Server</legend>
		
		<div class="control-group<?php if (isset($formErrors['baseUrl'])) { echo ' error'; } ?>">
			<label class="control-label" for="baseUrl">Full URL</label>
			<div class="controls">
				<input class="span4" type="text" id="baseUrl" name="baseUrl" value="<?php echo htmlentities($form['baseUrl']); ?>">
				<?php if (isset($formErrors['baseUrl'])) { echo '<div class="help-block">'.$formErrors['baseUrl'].'</div>'; } ?>
				<div class="help-block">
					The full URL to your installation, with a trailing slash.
					Example: <code>http://amwhalen.com/twitter/</code>
				</div>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['timezone'])) { echo ' error'; } ?>">
			<label class="control-label" for="timezone">Time Zone</label>
			<div class="controls">
				<select name="timezone" class="span4">
					<?php foreach ($timezones as $tz): ?>
					<option value="<?php echo $tz; ?>"<?php if ($form['timezone'] == $tz) { echo ' selected="selected"'; } ?>><?php echo $tz; ?></option>
					<?php endforeach; ?>
				</select>
				<?php if (isset($formErrors['timezone'])) { echo '<div class="help-block">'.$formErrors['timezone'].'</div>'; } ?>
				<div class="help-block">
					Select the closest time zone to you so your tweets will display the proper dates and times.
				</div>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['cronKey'])) { echo ' error'; } ?>">
			<label class="control-label" for="cronKey">Cron Key</label>
			<div class="controls">
				<input class="span4" type="text" id="cronKey" name="cronKey" value="<?php echo htmlentities($form['cronKey']); ?>">
				<?php if (isset($formErrors['cronKey'])) { echo '<div class="help-block">'.$formErrors['cronKey'].'</div>'; } ?>
				<div class="help-block">
					Alpha-numeric only, don't use spaces or strange characters.
					This is required to load your tweets by calling your cron.php on the web.
					Using a secret key to access your cron.php file will help protect your precious API call limit from being used up.
					See the <a href="https://github.com/amwhalen/archive-my-tweets#setting-up-a-cron-job" target="_blank">docs for more information</a>.
				</div>
			</div>
		</div>

	</fieldset>

	<fieldset>
    <legend>Database</legend>

		<div class="control-group<?php if (isset($formErrors['databaseHost']) || isset($databaseErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="databaseHost">Database Host</label>
			<div class="controls">
				<input class="span4" type="text" id="databaseHost" name="databaseHost" value="<?php echo htmlentities($form['databaseHost']); ?>">
				<?php if (isset($formErrors['databaseHost'])) { echo '<div class="help-block">'.$formErrors['databaseHost'].'</div>'; } ?>
				<div class="help-block">
					The default of <code>localhost</code> is fine for the majority of setups.
				</div>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['databaseDatabase']) || isset($databaseErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="databaseDatabase">Database Name</label>
			<div class="controls">
				<input class="span4" type="text" id="databaseDatabase" name="databaseDatabase" value="<?php echo htmlentities($form['databaseDatabase']); ?>">
				<?php if (isset($formErrors['databaseDatabase'])) { echo '<div class="help-block">'.$formErrors['databaseDatabase'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['databaseUsername']) || isset($databaseErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="databaseUsername">Database Username</label>
			<div class="controls">
				<input class="span4" type="text" id="databaseUsername" name="databaseUsername" value="<?php echo htmlentities($form['databaseUsername']); ?>">
				<?php if (isset($formErrors['databaseUsername'])) { echo '<div class="help-block">'.$formErrors['databaseUsername'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['databasePassword']) || isset($databaseErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="databasePassword">Database Password</label>
			<div class="controls">
				<input class="span4" type="password" id="databasePassword" name="databasePassword" value="<?php echo htmlentities($form['databasePassword']); ?>">
				<?php if (isset($formErrors['databasePassword'])) { echo '<div class="help-block">'.$formErrors['databasePassword'].'</div>'; } ?>
			</div>
		</div>
		<div class="control-group<?php if (isset($formErrors['databasePrefix']) || isset($databaseErrors)) { echo ' error'; } ?>">
			<label class="control-label" for="databasePrefix">Database Table Prefix</label>
			<div class="controls">
				<input class="span4" type="text" id="databasePrefix" name="databasePrefix" value="<?php echo htmlentities($form['databasePrefix']); ?>">
				<?php if (isset($formErrors['databasePrefix'])) { echo '<div class="help-block">'.$formErrors['databasePrefix'].'</div>'; } ?>
				<div class="help-block">
					Set a table prefix to prevent Archive My Tweet's database table names from clashing with other tables in your database.
					The suggested prefix is <code>amt_</code>.
				</div>
			</div>
		</div>

	</fieldset>
	
	<div class="control-group">
		<div class="controls">
			<button type="submit" class="btn btn-primary btn-large">Save and Install &raquo;</button>
		</div>
	</div>
</form>

<?php

}

?>