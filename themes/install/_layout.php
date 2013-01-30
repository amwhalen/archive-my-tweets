<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Archive My Tweets Installer</title>
	<link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<!--[if lt IE 9]>
		<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
</head>
<body>

	<div class="container">
		<div class="row">

			<div class="page-header">
				<h1>
					Archive My Tweets
					<small>Your personal Twitter archive</small>
				</h1>
			</div>
	
			<?php echo $content; ?>

		</div><!-- /.row -->
	</div><!-- /.container -->

	<div class="footer" id="footer">
		<div class="container">
			<div class="row">
				<p>	</div>
		</div><!-- /.container -->
	</div><!-- /.footer -->

	<script src="assets/jquery/jquery-1.9.0.min.js"></script>
	<script src="assets/bootstrap/js/bootstrap.min.js"></script>
	<script>
		$(function() {
			// popovers
			$("a[rel=popover]").popover().click(function(e) { e.preventDefault(); })
		})
	</script>
</body>
</html>
