<?php

class Export_ID_XML_Generator {

	public static function generate_article($postID) {

		$post = get_post($postID);
		if (!$post) 
			return false;

		$article = new Export_ID_XML_Generator($post);
		return $article->render();

	}

	public static function generate_date($date) {

	}

	public static function generate_category($catID, $date) {

		// Query All Posts by Print Date
		
		// Generate XML For Each Article

	}

	// Post Object for Which to Generate XML
	private $post;
	// Render Context for Post Template
	private $context;
	// XML Template to Use for Rendering
	private $article;

	// Constructor for Object-Based Version of Class
	public function __construct($post) {

		$this->post = $post;
		$this->context = array();
		$this->template = 'standard.xml';

		// Test Context
		$this->context = array(
			'headline' => 'Article Headline Here',
			'author' => 'Andrew Stephens',
			'rank' => 'Production Director',
			'bio' => 'Andrew Stephens is the Production Director at the Tufts Daily.'
		);

	}

	public function render() {

		$m = new Mustache_Engine;

		// Get Template File
		$filename = dirname(__FILE__).'/templates/'.$this->template;
		$handle = fopen($filename, "r");
		if (!$handle)
			return '<error>Template not found.</error>';
		$contents = fread($handle, filesize($filename));
		fclose($handle);

		return $m->render($contents, $this->context);

	}



}