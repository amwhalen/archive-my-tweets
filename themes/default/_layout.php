<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>@<?php echo $config['twitter']['username']; ?> - <?php echo $config['twitter']['name']; ?> - Tweets</title>
	<link href="<?php echo $config['system']['baseUrl']; ?>css/archive.css" rel="stylesheet">
	<link href="<?php echo $config['system']['baseUrl']; ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<!--[if lt IE 9]>
		<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
</head>
<body class="amt-<?php echo $pageType; ?>">

	<div class="navbar navbar-inverse navbar-fixed-top">
		<div class="navbar-inner">
			<div class="container">
				<a class="brand avatar" href="<?php echo $config['system']['baseUrl']; ?>"><img src="<?php echo $config['system']['baseUrl']; ?>img/avatar.png"></a>
				<ul id="primary-nav" class="nav">
					<li><a class="brand" href="<?php echo $config['system']['baseUrl']; ?>">@<?php echo $config['twitter']['username']; ?></a></li>
				</ul>
				<ul id="search-nav" class="nav pull-right">
					<!--<li><a href="<?php echo $config['system']['baseUrl']; ?>stats">Stats</a></li>-->
					<form action="<?php echo $config['system']['baseUrl']; ?>" class="navbar-search pull-right" method="get">
						<input type="text" size="20" name="q" value="<?php echo ($search) ? htmlentities($searchTerm) : ''; ?>" class="span3 search-query" placeholder="Search my tweets" />
					</form>
				</ul>
			</div>
		</div>
    </div>

	<div class="container">
		<div class="row">
	
			<?php echo $content; ?>

		</div><!-- /.row -->
	</div><!-- /.container -->

	<div class="footer" id="footer">
		<div class="container">
			<p><a href="http://amwhalen.com/projects/archive-my-tweets/">Archive My Tweets</a> by <a href="http://amwhalen.com">Andrew M. Whalen</a>.</p>
		</div><!-- /.container -->
	</div><!-- /.footer -->

	<script src="<?php echo $config['system']['baseUrl']; ?>assets/jquery/jquery-1.9.0.min.js"></script>
	<script src="<?php echo $config['system']['baseUrl']; ?>assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
