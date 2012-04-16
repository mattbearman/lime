<?php

namespace Lime;

/**
 * Lime core class
 */
class Core {
	
	protected $base_url;
	protected $language;
	protected $dry_run = true;
	protected $preview = false;
	protected $rewrite = false;
	protected $links = array();
	protected $log = array();
	
	public function __construct() {
		
		if($_SERVER['SERVER_ADDR'] == '127.0.0.1') {
			ini_set('display_errors', 1);
			error_reporting(E_ALL);
		}
		
		// figure out the base url
		$this->base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
		$this->base_url .= '://'. $_SERVER['HTTP_HOST'];
		$this->base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
		
		// set some constants
		define('LIME_PAGES_PATH',  LIME_PATH.'pages/');
		define('LIME_WEBROOT_PATH',  LIME_PATH.'httpdocs/');
		
		// load the page class and markdown classes
		require_once LIME_PATH.'system/classes/page.php';
		require_once LIME_PATH.'system/classes/markdown.php';
		
		// load the language
		require_once LIME_PATH.'system/language/en.php';
		
		$this->language = new Language;
	}
	
	/**
	 * Crawl from the $folder either creating html files, or looking for a target file
	 */
	private function crawl($folder='/', $defalt_template=false, $target=false) {
		
		// load the default template for this folder (if there is one)
		if(file_exists(LIME_PAGES_PATH.$folder.'_default.php')) {
			$defalt_template = $folder.'_default.php';
		}
		
		// get all fiels in this folder
		$files = scandir(LIME_PAGES_PATH.$folder);
		
		// remove hidden files from list
		$files = $this->filter_hidden_files($files);
		
		// get pages (.txt files)
		$pages = $this->filter_pages($files);

		// process sub folders first
		foreach($files as $file) {
			
			// path to the file
			$file_path = LIME_PAGES_PATH.$folder.$file;
				
			// is it a folder
			if(is_dir($file_path)) {
				$this->crawl($folder.$file.'/', $defalt_template, $target);
			}
		}
		
		// now proces the page files		
		foreach($pages as $file_name) {
			
			$page = &$this->create_page($uri=$folder.$file_name, $defalt_template);
			
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
	public function remove_unused_files($folder='') {
		
		$path = LIME_WEBROOT_PATH.$folder;
		
		// traverse httpdocs and remove files that don't have matching pages files
		$httpdocs = scandir($path);
		
		$httpdocs = $this->filter_hidden_files($httpdocs);
		
		// count the number of files to be removed
		$delete_count = 0;
		
		foreach($httpdocs as $file) {
			// is it a directory
			if(is_dir($folder.$file)) {
				if($this->remove_unused_files($folder.$file.'/')) {
					// should the directory be removed?
					if(!is_dir(LIME_PAGES_PATH.$folder.$file)) {
						$this->log('delete', sprintf($this->language->delete_directory, $folder.$file.'/'));
						
						if(!$this->dry_run) {
							rmdir(LIME_WEBROOT_PATH.$folder.$file);
						}
					}
				}
			}
			else {
				// only delete html files
				if(array_pop(explode('.', $file)) == 'html') {
					// remove the .html and replace with .txt
					$text_file = str_ireplace('.html', '.txt', $file);
					
					if(!file_exists(LIME_PAGES_PATH.$folder.$text_file)) {
						$this->log('delete', sprintf($this->language->delete_file, $folder.$file));
						$delete_count++;
						
						if(!$this->dry_run) {
							unlink(LIME_WEBROOT_PATH.$folder.$file);
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
	public function preview($uri) {
		
		// set the preview flag
		$this->preview = true;
		
		$this->crawl($folder='/', $default_template=false, $target=$uri);
	}
	
	/**
	 * Publish pages
	 */
	public function publish($dry_run=true) {
		
		$this->dry_run = $dry_run;
		
		$this->crawl();
		
		$this->remove_unused_files();
		
		if($dry_run) {
			require LIME_PATH.'system/views/publish.php';
		}
	}
	
	/**
	 * Log a message
	 */
	protected function log($type, $message) {
		// suppress duplicate messages	
		if(!@in_array($message, $this->log[$type])) {
			$this->log[$type][] = $message;
		}
	}
	
	/**
	 * Page factory function
	 */
	protected function create_page($uri, $defalt_template) {
		$page = new Page($uri, $defalt_template, array(
			'base_url'=>&$this->base_url,
			'preview'=>&$this->preview,
			'dry_run'=>&$this->dry_run,
			'rewrite'=>&$this->rewrite,
			'language'=>&$this->language,
			'links'=>&$this->links,
			'log'=>&$this->log
		));
		
		return $page;
	}
	
	/**
	 * Filter live pages from a list of files/folders
	 */
	protected function filter_pages($files) {
		
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
	protected function filter_hidden_files($files) {
		
		$filtered = array();
		
		foreach($files as $file) {
			
			// discard any files that start with a dot or underscore
			if($file[0] != '.' && $file[0] != '_') {
				$filtered[] = $file;
			}
		}
		
		return $filtered;
	}
	
	protected function page_link($page) {
		
		$page = trim($page, '/');
		
		// if this link has already been resolved, get it from cache
		if(isset($this->links[$page])) {
			return $this->links[$page];
		}

		// path to the associated page text file
		$page_file = LIME_PAGES_PATH.$page;
		
		$page_url = $this->base_url;
		
		if($this->preview) {
			$page_url .= 'lime.php?preview='.$page;
		}
		
		else {
			
			// is it a folder?
			if(is_dir(LIME_PAGES_PATH.$page)) {
				$page_file .= '/index.txt';
				
				$page_url .= $page;
			}
			
			else {
				$page_file .= '.txt';
			
				$page_url .= $page.($this->rewrite ? '' : '.html');
			}
			
		}
			
		// is it a 404?
		if(!file_exists($page_file)) {
			$debug = debug_backtrace();
			
			$this->log('error', sprintf($this->language->broken_link, $debug[0]['file'], $page, $debug[0]['line']));
		}
		
		// cache this link to save further processing
		$this->links[$page] = $page_url;
		
		return $page_url;
	}
	
	/**
	 * Include a file, tracking the current file name
	 */
}
