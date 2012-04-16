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
			<input type="submit" name="make_files" value="Do it!" />
		</form>
	</body>
</html>
