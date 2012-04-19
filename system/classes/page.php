<?php

namespace Lime;

/**
 * Lime page class
 */
class Page {
	
	private $core;
	
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
	public function __construct($core, $uri, $defalt_template=false) {
		
		// set reference to core object
		$this->core = &$core;
		
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
		
		// look for date in format YYYY-MM-DD YY-M-D will also work
		if(preg_match('/^([0-9]{2,4}-[0-1]?[0-9]-[0-3]?[0-9])-(.+)/i', $this->file_name, $matches)) {
			$this->date = strtotime($matches[1]);
			
			$this->title = $matches[2];
		}
		
		// create title from file name
		$this->title = ucwords(str_replace('-', ' ', $this->title));
		
		// create the link to this page
		$this->link = $this->url($this->folder.$this->file_name);
		
		// load content
		$this->load_content();
	}
	
	/**
	 * Load the content and process the markdown into HTML
	 */
	public function load_content() {
		
		// if path is a directory, laad the default (index) file
		$file_path = $this->core->source_path.$this->uri.'.txt';
		
		if(file_exists($file_path)) {
			$markdown_content = file_get_contents($file_path);
			
			// extract and process any links in the markdown
			$markdown_content = $this->resolve_markdown_links($markdown_content);
			
			// complie markdown into HTML
			$this->content = Markdown($markdown_content);
		}
	}
	
	/**
	 * Extract and process links in markdown
	 */
	public function resolve_markdown_links($markdown) {
		// find relative links, format [...](/...)
		
		// set refernce to this page object to be used in the context of the regex replace closure
		$self = $this;
		
		// prepare for the ugliest regex evar! so many escapes...
		return preg_replace_callback('/\[(.+?)\]\((\/.*?)\)/', function($matches) use (&$self) {
			// resolve internal link
			return "[{$matches[1]}](".$self->url($matches[2], $self->uri.'.txt').')';
		}, $markdown);
	}
	
	/**
	 * Render the html into the template
	 */
	public function render() {
		
		// has this page already been rendered?
		if(!$this->html) {
		
			// is there a page specific template
			$page_template_path = $this->core->template_path.$this->uri.'.php'; 
			
			if(file_exists($page_template_path)) {
				$this->template = $this->uri.'.php';
			}
		
			ob_start();
			
			require $this->core->template_path.$this->template;
			
			$this->html = ob_get_clean();
		}
		
		return $this->html;
	}
	
	public function make_file() {
		
		$folder_path = $this->core->webroot_path.$this->folder;
		$file_name = $this->folder.$this->file_name.'.html';
		$file_path = $this->core->webroot_path.$file_name;
		
		// write file flag
		$write_file = false;
		
		// first check to see if the folder exists
		if(!is_dir($folder_path)) {
			// folder doesn't exist, so we need to create it
			$this->core->log('create', sprintf($this->core->language->create_directory, $this->folder));
			
			if(!$this->core->dry_run) {
				mkdir($folder_path);
			}
		}
		
		// does the file exist
		if(!file_exists($file_path)) {
			// file doesn't exist, so we need to create it
			$this->core->log('create', sprintf($this->core->language->create_file, $file_name));

			$write_file = true;
		}
		
		// see if the file has changed
		else {
			if($this->html != file_get_contents($file_path)) {
				$this->core->log('update', sprintf($this->core->language->update_file, $file_name));
			
				$write_file = true;
			}
		}
		
		// should we write the file?
		if(!$this->core->dry_run && $write_file) {
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
		$files = scandir($this->core->source_path.$this->folder);
		
		$files = $this->core->filter_hidden_files($files);
		
		$pages = $this->core->filter_pages($files);
		
		foreach($pages as $page) {
			
			// don't include self in list of siblings
			if($page != $this->file_name) {
				$this->siblings[] = &$this->core->create_page($uri=$this->folder.$page, false);
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
	
	/**
	 * Get a link, if $page is not specified then use the current page, if from_markdown is set, than that will be used in 404 error messages instead of template name
	 * 
	 * @param string $page
	 * @param string $from_markdown
	 */
	public function url($page=false, $from_markdown=false) {
		
		if(!$page) {
			$page = $this->uri;
		}
		
		// remove leading and trailing slashes
		$page = trim($page, '/');
		
		// if this link has already been resolved, get it from cache
		if(isset($this->core->links[$page])) {
			return $this->core->links[$page];
		}

		// path to the associated page text file
		$page_file = $this->core->source_path.$page;
		
		// set up url to page by starting with base url
		$page_url = $this->core->base_url;
		
		// if previewing, use the preview url
		if($this->core->preview) {
			$page_url .= $this->core->accessor.'?preview='.$page;
		}
		
		else {
			
			// is it a folder?
			if(is_dir($this->core->source_path.$page)) {
				$page_file .= '/index.txt';
				
				$page_url .= $page;
			}
			
			else {
				$page_file .= '.txt';
			
				$page_url .= $page.($this->core->rewrite ? '' : '.html');
			}
			
		}
			
		// is it a 404?
		if(!file_exists($page_file)) {
			
			if(!$from_markdown) {
				$debug = debug_backtrace();
				
				$this->core->log('error', sprintf($this->core->language->broken_template_link, $debug[0]['file'], $page, $debug[0]['line']));
			}
			
			else {
				$this->core->log('error', sprintf($this->core->language->broken_content_link, $from_markdown, $page));
			}
		}
		
		// cache this link to save further processing
		$this->core->cache_link($page, $page_url);
		
		return $page_url;
	}
	
	public function __get($name) {
		// allowed to get page details
		$allowed = array('core', 'uri', 'content', 'title', 'file_name', 'html', 'template');
		
		if(in_array($name, $allowed)) {
			return $this->$name;
		}
		
		return null;
	}
	
	public function __tostring() {
		return $this->uri;
	}
}
