<?php

use Handlebars\Handlebars;

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

		var_dump($catID);
		var_dump($date);

		// Query All Posts by Print Date
		$args = array(
			'meta_key' => 'export_id_xml_print_date',
			'meta_value' => $date,
			'category' => $catID
		);
		$posts = get_posts($args);

		if (sizeof($posts) == 0) {
			return "<error>No posts found for this category and date.</error>";
		}
		
		// Generate XML For Each Article
		$aggregateXML = "";
		foreach ($posts as $post) {
			$aggregateXML .= self::generate_article($post->ID);
		}
		return $aggregateXML;

	}

	// Post Object for Which to Generate XML
	private $post;
	// Render Context for Post Template
	private $context;
	// XML Template to Use for Rendering
	private $template;
	// Data Attached to Post by Print Options Box
	private $meta;

	// Constructor for Object-Based Version of Class
	public function __construct($post) {

		$this->post = $post;
		$this->context = array();
		$this->template = 'standard';

		$this->meta = get_post_meta( $post->ID, Export_ID_XML_Admin_PostMeta::$META_KEY_NAME, true );

		$this->context['headline'] = $this->get_title();
		$this->context['author'] = $this->get_author();
		$this->context['rank'] = $this->get_rank();
		$this->context['body'] = $this->get_body();
		$this->context['jumpword'] = $this->get_jumpword();
		$this->context['conthead'] = $this->get_conthead();
		$this->context['hammer'] = $this->get_hammer();

	}

	public function render() {

		$engine = new Handlebars(array(
		    'loader' => new \Handlebars\Loader\FilesystemLoader(__DIR__.'/templates/')
		));

		return $engine->render($this->template, $this->context);

	}

	private function get_title() {
		return $this->post->post_title;
	}

	private function get_author() {

		// Off-the-Hill Articles Store Author in Editorial Metadata
		if ($this->get_post_print_meta('is-off-the-hill')) 
			return $this->get_post_print_meta('off-the-hill-author');
		

		// Get Using Co-Authors Plus Plugin Functions
		if (function_exists('get_coauthors')) {

			$authors = get_coauthors($this->post->ID);
			$merged_authors = '';

			foreach ($authors as $i=>$author_data) {

				if ($i < sizeof($authors)-2) {
					$merged_authors .= $author_data->display_name.', ';
				} else if ($i == sizeof($authors)-2) {
					$merged_authors .= $author_data->display_name.' and ';
				} else {
					$merged_authors .= $author_data->display_name;
				}
				
			}

			return $merged_authors;

		// Otherwise, Use Built-In WP Functions
		} else {
			$author_data = get_userdata($this->post->post_author);
			return $author_data->display_name;
		}

	}

	private function get_rank() {

		// Off-the-Hill Articles Store University Paper Name as the Rank
		if ($this->get_post_print_meta('is-off-the-hill'))
			return $this->get_post_print_meta('off-the-hill-paper');
		

		// Get Using Co-Authors Plus Plugin Functions
		if (function_exists('coauthors')) {

			$shared_rank = null;

			// Co-Authors Plus Plugin Provides Author List Formatted
			foreach (get_coauthors($this->post->ID) as $author_data) {

				// Ranks Must Match, or Be Edited Manually
				$rank = $this->get_author_rank($author_data->ID);

				// If Null, This is First Author so Set Rank from That
				if ($shared_rank == null) {
					$shared_rank = $rank;

				// If Current Author's Rank Doesn't Match Previous, Set to Placeholder
				} else if ($shared_rank != $rank) {
					$shared_rank = "AUTHORS HAVE DIFFERENT RANKS";
				}

				// If They Match, No Change Necessary

			}

			return $shared_rank;

		// Otherwise, Use Built-In WP Functions
		} else {
			return $this->get_author_rank($post->post_author);
		}

	}

	private function get_bio() {

		// No Bio for Off-The-Hills
		if ($this->get_post_print_meta('is-off-the-hill'))
			return false;
		

		// Use Coauthors Plugin, Combining Bios if Necessary
		if (function_exists('get_coauthors')) {

			// Built an Array of User Bios
			$bios = array();
			foreach (get_coauthors($this->post->ID) as $author_data) {
				$bios[] = $author_data->description;
			}

			// Convert Bios to Unified String, with Separator Space
			return implode(' ', $bios);

		// Use Standard WP Functions
		} else {

			$author_data = get_userdata($this->post->post_author);
			$bio = $author_data->description;
			if (!$bio)
				return "This author does not have a bio set on WordPress.";

			return $bio;

		}

	}

	private function get_body() {

		$body = $this->post->post_content;
		$body = str_replace("\r\n\r\n", "\r\n", $body); // Replace Double-Newline
		$body = str_replace("&nbsp;", " ", $body); // Strip &nbsp;
		//$body = strip_tags($body, '<strong><em>'); // And Strip Out HTML
		$body = wptexturize($body); // Fix quotations and other encoding
		return $body;

	}

	private function get_jumpword() {
		return ( isSet( $this->meta['jumpword'] ) ) ? $this->meta['jumpword'] : false;
	}

	private function get_conthead() {
		return ( isSet( $this->meta['conthead'] ) ) ? $this->meta['conthead'] : false;
	}

	private function get_hammer() {
		return ( isSet( $this->meta['hammer'] ) ) ? $this->meta['hammer'] : false;
	}	

	/**
	 * Given an user ID, returns their author rank.
	 * 
	 * Ranks are stored in user meta. 
	 * This is controlled in class-export-id-xml-admin.php.
	 * 
	 * @return string Author's rank text.
	 */
	private function get_author_rank($user_id) {

		$rank = get_user_meta($user_id, 'daily-rank', true);
		if (!$rank) { $rank = "RANK NOT SET ON WEB"; }

		return $rank;

	}

	/**
	 * Given a key, returns the associated post meta value.
	 * 
	 * All print post meta is stored in a serialized array, 
	 * and the appropriate value is extracted, if exists.
	 *
	 * @return mixed Meta value if key exists, false otherwise.
	 */

	private function get_post_print_meta($key) {

		$printData = get_post_meta($this->post->ID, 'xml_print_meta', true);
		if (!$printData) return false;
		return (array_key_exists($key, $printData) ? $printData[$key] : false);

	}

}