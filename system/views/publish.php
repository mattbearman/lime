<!doctype html>
<html lang="en-us">
	<head>
		<title>LiME</title>
	</head>
	<body>
		<?foreach($this->log as $log_type=>$messages):?>
			<h3><?=ucfirst($log_type)?>s</h3>
			<pre><?foreach($messages as $message):?>
<?=$message?> 
<?endforeach?></pre>
		<?endforeach?>
		<form method="post">
			<button onclick="window.location = '<?=$this->base_url.$this->accessor.'?preview'?>'; return false;">Preview Changes</button>
			<input type="submit" name="make_files" value="Do it!" />
		</form>
	</body>
</html>
