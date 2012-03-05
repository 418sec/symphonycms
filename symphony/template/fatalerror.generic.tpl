<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Symphony Error</title>
	<link media="screen" href="{SYMPHONY_URL}/assets/symphony.basic.css" type="text/css" rel="stylesheet">
	<link media="screen" href="{SYMPHONY_URL}/assets/symphony.frames.css" type="text/css" rel="stylesheet">
	<script type="text/javascript" src="{SYMPHONY_URL}/assets/jquery.js"></script>
	<script type="text/javascript" src="{SYMPHONY_URL}/assets/symphony.js"></script>
	<script type="text/javascript" src="{SYMPHONY_URL}/assets/symphony.collapsible.js"></script>
	<script>
	
		jQuery(document).ready(function() {
			
			// Init Symphony
			Symphony.init();
			
			// Init collapsibles
			var collapsible = jQuery('.frame ul').symphonyCollapsible({
				items: 'li',
				handles: 'header',
				content: '.content',
				save_state: true,
				storage: 'symphony.collapsible.error'
			});
			
			// Hide backtrace and query log by default
			if(!window.localStorage['symphony.collapsible.error.0.collapsed']) {
				collapsible.trigger('collapse.collapsible', [0]);
			};
		});
	
	</script>
</head>
<body id="fatalerror">
	<div class="frame">
		<ul>
			<li>
				<h1><em>Symphony %s:</em> %s</h1>
				<p>An error occurred in <code>%s</code> around line <code>%d</code></p>
				<ul>%s</ul>
			</li>
			<li>
				<header>Markdown for copy/paste</header>
				<div class="content">
					<pre>%s</pre>
				</div>
			</li>
			<li>
				<header>Backtrace</header>
				<div class="content">
					<ul>%s</ul>
				</div>
			</li>
			<li>
				<header>Database Query Log</header>
				<div class="content">
					<ul>%s</ul>
				</div>
			</li>
		</ul>
	</div>
</body>
</html>
