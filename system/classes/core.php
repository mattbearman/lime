<?php

namespace Lime;

/**
 * Lime core class
 */
class Core {
	
	private $version = '0.1';
	
	private $lime_path;
	private $site_path;
	private $webroot_path;
	private $source_path;
	private $template_path;
	
	private $accessor;
	
	private $base_url;
	private $language;
	private $dry_run = true;
	private $preview = false;
	private $rewrite = false;
	private $links = array();
	private $log = array();
	
	/**
	 * Constructor - optionally pass in hte path back to the lime root directory from the front accessor file, this is usually ../../
	 * 
	 * @param string $relative_path
	 */
	public function __construct($relative_path='../../') {
		
		// if the user and server are on the same IP, chances are it's a local development test, so show errors
		if($_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR']) {
			ini_set('display_errors', 1);
			error_reporting(E_ALL);
		}
		
		// set the absolute path to the lime root directory
		$this->lime_path = $_SERVER['DOCUMENT_ROOT'].'/'.$relative_path;
		
		// save the name of the front accessor
		$this->accessor = trim($_SERVER['SCRIPT_NAME'], '/');
		
		// set path to webroot, source and includes
		$this->site_path = $this->lime_path.'site/';
		$this->webroot_path = $this->site_path.'webroot/';
		$this->source_path = $this->site_path.'source/';
		$this->template_path = $this->site_path.'templates/';
		
		// figure out the base url
		$this->base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
		$this->base_url .= '://'. $_SERVER['HTTP_HOST'];
		$this->base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
		
		// load the page class and markdown classes
		require_once $this->lime_path.'system/classes/page.php';
		require_once $this->lime_path.'system/classes/directory.php';
		require_once $this->lime_path.'system/classes/markdown.php';
		
		// load the language
		// @todo: allow other languages than english
		require_once $this->lime_path.'system/language/en.php';
		
		$this->language = new Language;
		
		$this->start_complier();
	}

	/**
	 * Fire up the LiME Compiler
	 */
	private function start_complier() {
		
		// if preview is set, preview that page
		if(isset($_GET['preview'])) {
			$this->preview($_GET['preview']);
		}
		
		// publish the pages, first pass is a dry run, pages are only actually published if $_POST['make_files'] is set
		else {
			$this->publish($dry_run = !isset($_POST['make_files']));
		}
	}
	
	/**
	 * Crawl from the $start_directory either creating html files, or looking for a target file
	 */
	private function crawl($start_directory='/', $defalt_template=false, $target=false) {
		
		// load the default template for this folder (if there is one)
		if(file_exists($this->template_path.$start_directory.'_default.php')) {
			$defalt_template = $start_directory.'_default.php';
		}
		
		// get all fiels in this folder
		$files = scandir($this->source_path.$start_directory);
		
		// remove hidden files from list
		$files = $this->filter_hidden_files($files);
		
		// get pages (.txt files)
		$pages = $this->filter_pages($files);
		
		// process sub folders first
		foreach($files as $file) {
			
			// path to the file
			$file_path = $this->source_path.$start_directory.$file;
				
			// is it a folder
			if(is_dir($file_path)) {
				$this->crawl($start_directory.$file.'/', $defalt_template, $target);
			}
		}
		
		// now proces the page files		
		foreach($pages as $file_name) {
			
			$page = &$this->create_page($uri=$start_directory.$file_name, $defalt_template);
			
			$page->render();
			
			// target is only set when previewing, so if this is the target, output it to screen
			if($page->compare_uri($target) && $this->preview) {
				echo $page->render(); exit;
			} 
			
			else {
				$page->make_file();
			}
		}
	}
	
	/**
	 * Recursively remove unused html files / folders, each call removes all the unused files in the specified folder. Returns true if the folder is now empty
	 * 
	 * @param string $folder
	 * 
	 * @return bool $folder_now_empty
	 */
	private function remove_unused_files($folder='') {
		
		$path = $this->webroot_path.$folder;
		
		// traverse httpdocs and remove files that don't have matching pages files
		$httpdocs = scandir($path);
		
		$httpdocs = $this->filter_hidden_files($httpdocs);
		
		// count the number of files to be removed
		$delete_count = 0;
		
		foreach($httpdocs as $file) {
			// is it a directory
			if(is_dir($folder.$file)) {
				
				// remove_unused_files only returns true if the directory will be empty after file deletion.
				// only remove directory if it is empty (returned true)
				if($this->remove_unused_files($folder.$file.'/')) {
					// should the directory be removed?
					if(!is_dir($this->source_path.$folder.$file)) {
						$this->log('delete', sprintf($this->language->delete_directory, $folder.$file.'/'));
						
						if(!$this->dry_run) {
							rmdir($this->webroot_path.$folder.$file);
						}
					}
				}
			}
			else {
				// only delete html files
				if(array_pop(explode('.', $file)) == 'html') {
					// remove the .html and replace with .txt
					$text_file = str_ireplace('.html', '.txt', $file);
					
					if(!file_exists($this->source_path.$folder.$text_file)) {
						$this->log('delete', sprintf($this->language->delete_file, $folder.$file));
						$delete_count++;
						
						if(!$this->dry_run) {
							unlink($this->webroot_path.$folder.$file);
						}
					}
				}
			}
		}
		
		return $delete_count == count($httpdocs);
	}
	
	/**
	 * Preview a page
	 */
	private function preview($uri) {
		
		// set the preview flag
		$this->preview = true;
		
		$this->crawl($folder='/', $default_template=false, $target=$uri);
	}
	
	/**
	 * Publish pages
	 */
	private function publish($dry_run=true) {
		
		$this->dry_run = $dry_run;
		
		$this->crawl();
		
		$this->remove_unused_files();
		
		if($dry_run) {
			require $this->lime_path.'system/views/publish.php';
		}
	}
	
	/**
	 * Page factory function
	 */
	public function create_page($uri, $defalt_template) {
		$page = new Page($this, $uri, $defalt_template);
		
		return $page;
	}
	
	/**
	 * Cache a page link
	 */
	public function cache_link($name, $url) {
		$this->links[$name] = $url;
	}
	
	/**
	 * Log a message
	 */
	public function log($type, $message) {
		// suppress duplicate messages	
		if(!@in_array($message, $this->log[$type])) {
			$this->log[$type][] = $message;
		}
	}
	
	/**
	 * Filter live pages from a list of files/folders
	 */
	public function filter_pages($files) {
		
		$pages = array();
		
		foreach($files as $file) {
			
			// extract file extension
			$file_parts = explode('.', $file);
			$file_extenstion = array_pop($file_parts);
			
			// file name without extenstion
			$file_name = implode('.', $file_parts);
			
			// is it a txt (page) file
			if($file_extenstion == 'txt') {
				$pages[] = $file_name;
			}
		}

		return $pages;
	}
	
	/**
	 * Remove any files/folders that are hidden (start with . or _)
	 */
	public function filter_hidden_files($files) {
		
		$filtered = array();
		
		foreach($files as $file) {
			
			// discard any files that start with a dot or underscore
			if($file[0] != '.' && $file[0] != '_') {
				$filtered[] = $file;
			}
		}
		
		return $filtered;
	}
	
	/**
	 * Allow all properties to be retrieved, but not edited
	 */
	public function __get($var) {
		return $this->$var;
	}
}
