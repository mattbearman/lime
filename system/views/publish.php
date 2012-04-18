<!doctype html>
<html lang="en-us">
	<head>
		<title>LiME</title>
	</head>
	<body>
		<h3><a href="<?=$this->base_url.$this->accessor.'?preview'?>">Preview Changes</a></h3>
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
