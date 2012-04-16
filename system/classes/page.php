<?php

namespace Lime;

/**
 * Lime page class
 */
class Page extends Core {
	
	private $uri;
	private $file_name;
	private $folder;
	private $template;
	
	private $content = '';
	private $title = '';
	private $date = false;
	private $siblings = false;
	private $html;
	private $link;
	
	/**
	 * Create a new page,
	 * 
	 * @param string $uri
	 * @param string $default_template
	 */
	public function __construct($uri, $defalt_template=false, $settings=false) {
		
		$this->uri = trim($uri, '/');
		
		// initially set template to default, this will be overridden if a specific template is found 
		$this->template = $defalt_template;
		
		// extract file name and folder
		$path_parts = explode('/', $this->uri);
		$this->file_name = array_pop($path_parts);
		$this->folder = implode('/', $path_parts).'/';
		$this->title = $this->file_name;
		
		// does the URI have a date specifed
		$matches = array();
		
		if(preg_match('/^([0-9]{2,4}-[0-1]?[0-9]-[0-3]?[0-9])-(.+)/i', $this->file_name, $matches)) {
			$this->date = $matches[1];
			
			$this->title = $matches[2];
		}
		
		// create title from file name
		$this->title = ucwords(str_replace('-', ' ', $this->title));
		
		// load settings from core
		foreach($settings as $name=>&$value) {
			$this->$name = &$value;
		}
		
		//echo $this->folder.$this->file_name.'<br>';
		
		// create the link to this page
		$this->link = $this->page_link($this->folder.$this->file_name);
		
		// load content
		$this->load_content();
	}
	
	public function load_content() {
		
		// if path is a directory, laad the default (index) file
		$file_path = LIME_PAGES_PATH.$this->uri;
		
		if(is_dir($file_path)) {
			$file_path.='/index';
		}
		
		// is there a page specific template
		$page_template_path = $file_path.'.php'; 
		
		if(file_exists($page_template_path)) {
			$this->template = $this->uri.'.php';
		}
		
		$file_path.='.txt';
		
		if(file_exists($file_path)) {
			$this->content = Markdown(file_get_contents($file_path));
		}
	}
	
	public function render() {
		
		// has this page already been rendered?
		if(!$this->html) {
		
			ob_start();
			
			require LIME_PAGES_PATH.$this->template;
			
			$this->html = ob_get_clean();
		}
		
		return $this->html;
	}
	
	public function make_file() {
		
		$folder_path = LIME_WEBROOT_PATH.$this->folder;
		$file_name = $this->folder.$this->file_name.'.html';
		$file_path = LIME_WEBROOT_PATH.$file_name;
		
		// write file flag
		$write_file = false;
		
		// first check to see if the folder exists
		if(!is_dir($folder_path)) {
			// folder doesn't exist, so we need to create it
			$this->log('create', sprintf($this->language->create_directory, $this->folder));
			
			if(!$this->dry_run) {
				mkdir($folder_path);
			}
		}
		
		// does the file exist
		if(!file_exists($file_path)) {
			// file doesn't exist, so we need to create it
			$this->log('create', sprintf($this->language->create_file, $file_name));
			
			$write_file = true;
		}
		
		// see if the file has changed
		else {
			if($this->html != file_get_contents($file_path)) {
				$this->log('update', sprintf($this->language->update_file, $file_name));
			
				$write_file = true;
			}
		}
		
		// should we write the file?
		if(!$this->dry_run && $write_file) {
			$file_handle = fopen($file_path, 'w');
			fwrite($file_handle, $this->html);
			fclose($file_handle);
		}
	}
	
	public function siblings() {
		
		if($this->siblings) {
			return $this->siblings;
		}
		
		$this->siblings = array();
		
		// load the siblings
		$files = scandir(LIME_PAGES_PATH.$this->folder);
		
		$files = $this->filter_hidden_files($files);
		
		$pages = $this->filter_pages($files);
		
		foreach($pages as $page) {
			
			// don't include self in list of siblings
			if($page != $this->file_name) {
				$this->siblings[] = &$this->create_page($uri=$this->folder.$page, false);
			}
		}

		return $this->siblings;
	}
	
	/**
	 * Get word limited excerpt of the content
	 * 
	 * @param $word_limit number of words to return, default 100
	 * @param $end_char character to append if word limit is reached, default ellipsis
	 */
	public function excerpt($word_limit=100, $end_char='&#8230;')
	{
		preg_match('/^\s*+(?:\S++\s*+){1,'.(int) $word_limit.'}/', $this->content, $matches);

		if (strlen($this->content) == strlen($matches[0]))
		{
			$end_char = '';
		}

		return rtrim($matches[0]).$end_char;
	}
	
	/**
	 * See if the passed in url matches this page's url, allow for leading/trailng slashes and index removal
	 */
	public function compare_uri($uri) {
		
		$uri = trim($uri, '/');
		
		if($uri == $this->uri) {
			return true;
		}
		
		// if this page is index, try it without the index
		if($this->file_name == 'index') {
			return $uri == trim($this->folder, '/');
		}
	}
	
	public function __tostring() {
		return $this->uri;
	}
	
	public function __get($name) {
		// allowed to get page details
		$allowed = array('content', 'title', 'file_name', 'html', 'template');
		
		if(in_array($name, $allowed)) {
			return $this->$name;
		}
		
		return null;
	}
}
