<?php

class Export_ID_XML {

	public function __construct() {

		// Load Handlebars Template Engine
		require_once('Handlebars/Autoloader.php');
		Handlebars\Autoloader::register();

		// Load Plugin Modules
		require_once('class-export-id-xml-admin.php');
		require_once('class-export-id-xml-generator.php');

	}

	public function run() {

		$this->setup_admin_hooks();
		$this->setup_public_hooks();

	}

	private function setup_admin_hooks() {

		$admin = new Export_ID_XML_Admin();
		$admin->run();

	}

	private function setup_public_hooks() {

		$this->setup_rewrite_hooks();

	}

	private function setup_rewrite_hooks() {

		add_filter('query_vars', array($this, 'add_query_vars'), 0);
		add_action('init', array($this, 'add_endpoints'), 0);
		add_action('parse_request', array($this, 'sniff_requests'), 0);

	}

	public function add_query_vars($vars) {
		$vars[] = '__xml';
		$vars[] = 'xml_category';
		$vars[] = 'xml_date';
		$vars[] = 'xml_article';
		return $vars;
	}

	public function add_endpoints() {

		// Matches Specific Article
		//add_rewrite_rule('^xml/article/([0-9]+)/?','index.php?__xml=1&xml_article=$matches[1]','top');

		// Matches Date and Category
		add_rewrite_rule('^xml/category/([A-Za-z]+)/date/([0-9-]+)/?','index.php?__xml=1&xml_category=$matches[1]&xml_date=$matches[2]','top');
		//add_rewrite_rule('^xml/?','index.php?__xml=1','top');

		// Matches Date Queries Only
		//add_rewrite_rule('^xml/date/([0-9-]+)/?','index.php?__xml=1&xml_date=$matches[1]','top');
	}

	public function sniff_requests() {

		global $wp;
		if(isset($wp->query_vars['__xml'])){
			$this->handle_request();
			exit;
		}

	}

	private function handle_request() {

		global $wp;
		$xml_category = $wp->query_vars['xml_category'];
		$xml_date = $wp->query_vars['xml_date'];
		
		// Make Sure Required Params Were Given
		if(!$xml_category)
			$this->send_error("Category for articles not provided."); 
		if(!$xml_date)
			$this->send_error("Date for articles not provided."); 
		
		// Make Sure Params are Valid
		$cat = get_category_by_slug($xml_category);
		if (!$cat)
			$this->send_error("Category not found.");
		$catID = $cat->cat_ID;

		// Check for Proper Date Format: YYYY-MM-DD
		if (!preg_match("/[0-9]{4}-[0-9]{2}-[0-9]{2}/", $xml_date))
			$this->send_error("Invalid date format.");

		// Generate and Output the XML
		$xml = Export_ID_XML_Generator::generate_category($catID, $xml_date);
		//$xml = Export_ID_XML_Generator::generate_article(18);

		ob_end_clean();

		header("Content-type: text/xml");
		$response = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$response .= $xml;
		echo $response;

	}

	private function send_error($msg) {

		ob_end_clean();

		header("Content-type: text/xml");
		$response = "<?xml version='1.0' encoding='UTF-8'?>";
		$response .= "<error>".$msg."</error>";

		echo $response;
		exit;

	}
	
}