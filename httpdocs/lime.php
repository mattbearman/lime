<?php

define('LIME_PATH', '../');

// load the core class
require_once LIME_PATH.'system/classes/core.php';

$lime = new Lime\Core;

// if preview is set, preview that page
if(isset($_GET['preview'])) {
	$preview = $_GET['preview'];
	
	$lime->preview($preview);
}

// publish the pages, first pass is a dry run, pages are only actually published if $_POST['make_files'] is set
else {
	$lime->publish($dry_run = !isset($_POST['make_files']));
}
