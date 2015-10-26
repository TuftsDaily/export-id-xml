<?php

use Handlebars\Handlebars;

class Export_ID_XML_Generator {

	// These Get Set 
	public static $OPINION_CATEGORY_ID = -1;
	public static $COLUMNS_CATEGORY_ID = -1;

	public static function generate_article($postID) {

		$post = get_post($postID);
		if (!$post) 
			return "<error>No post found with this ID.</error>";

		$article = new Export_ID_XML_Generator($post);
		return $article->render();

	}

	public static function generate_date($date) {

		// TODO Add Functionality, Maybe?

	}

	public static function generate_category($catID, $date) {

		// Query All Posts by Print Date
		$args = array(
			'meta_key' => 'export_id_xml_print_date',
			'meta_value' => $date,
			'category' => $catID,
			'post_status' => 'any',
			'posts_per_page' => -1
		);
		$posts = get_posts($args);

		if (sizeof($posts) == 0) {
			return "<error>No posts found for this category and date.</error>";
		}
		
		// Generate XML For Each Article
		$aggregateXML = "<section>";
		foreach ($posts as $post) {
			$aggregateXML .= self::generate_article($post->ID);
		}
		$aggregateXML .= "</section>";
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

		// Set Category ID Values, but First Time Only
		if (Export_ID_XML_Generator::$OPINION_CATEGORY_ID == -1) {
			Export_ID_XML_Generator::$OPINION_CATEGORY_ID = get_cat_ID("Opinion");
			Export_ID_XML_Generator::$COLUMNS_CATEGORY_ID = get_cat_ID("Columns");
		}

		$this->post = $post;
		$this->context = array();
		$this->template = $this->get_template_context();

		$this->meta = get_post_meta( $post->ID, Export_ID_XML_Admin_PostMeta::$META_KEY_NAME, true );

		$this->context['headline'] = $this->get_title();
		$this->context['author'] = $this->get_author();
		$this->context['rank'] = $this->get_rank();
		$this->context['body'] = $this->get_body();
		$this->context['jumpword'] = $this->get_jumpword();
		$this->context['conthead'] = $this->get_conthead();
		$this->context['hammer'] = $this->get_hammer();
		$this->context['kicker'] = $this->get_kicker();
		$this->context['bio'] = $this->get_bio();
		
		$photos = $this->get_photos();
		if(sizeof($photos) > 1) {
			$this->context['photos'] = $photos;
		} else if (sizeof($photos) == 1) {
			$this->context['photo'] = $photos[0];
		}

		// Optional for Off-the-Hill Context
		$this->context['oth'] = $this->get_oth();

	}

	public function render() {

		$engine = new Handlebars(array(
		    'loader' => new \Handlebars\Loader\FilesystemLoader(__DIR__.'/templates/')
		));

		return $engine->render($this->template, $this->context);

	}

	private function get_template_context() {

		if ($this->has_category(Export_ID_XML_Generator::$COLUMNS_CATEGORY_ID)) {
			return 'column';
		} else if ($this->has_category(Export_ID_XML_Generator::$OPINION_CATEGORY_ID)) {
			return 'opinion';
		} else {
			return 'standard';
		}

	}

	private function get_title() {
		return wptexturize($this->post->post_title);
	}

	private function get_author() {

		// Off-the-Hill Articles Store Author in Editorial Metadata
		if ($this->get_article_meta('off-the-hill-author'))
			return $this->get_article_meta('off-the-hill-author');


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

		// Off-the-Hill Articles Have No Rank with Author
		if ($this->get_article_meta('off-the-hill-author'))
			return false;
		

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
		if ($this->get_article_meta('off-the-hill-author'))
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
		$body = strip_tags($body, '<strong><em>'); // And Strip Out HTML
		$body = wptexturize($body); // Fix quotations and other encoding
		return $body;

	}

	private function get_jumpword() {
		return $this->get_article_meta('jumpword');
	}

	private function get_conthead() {
		return wptexturize($this->get_article_meta('conthead'));
	}

	private function get_hammer() {
		return wptexturize($this->get_article_meta('hammer'));
	}

	private function get_kicker() {
		return wptexturize($this->get_article_meta('kicker'));
	}

	private function get_oth() {
		
		if (!$this->get_article_meta('off-the-hill-university'))
			return false;

		$oth = [];
		$oth['university'] = $this->get_article_meta('off-the-hill-university');
		return $oth;

	}

	private function get_photos() {

		$media = get_attached_media( 'image', $this->post->ID );
		$photos = array();

		foreach($media as $object) {
			$photos[] = [
				'href' => wp_get_attachment_url($object->ID),
				'caption' => wptexturize($object->post_content),
				'credit' => $object->post_excerpt
			];
		}

		return $photos;

	}

	/////////////////////////////////////////////////////////////////
	/// Helper Functions Below
	////////////////////////////////////////////////////////////////


	/**
	 * Requests article data from the post meta field.
	 * 
	 * Assumes that post meta has already been retrieved, follows the standard
	 * JSON format, and is stored under the self::meta variable.
	 *
	 * Blank values are treated the same as non-existant.
	 *
	 * @return bool|string Meta value, or false if non-existant.
	 */
	private function get_article_meta($key) {
		if (!isSet($this->meta[$key]) || $this->meta[$key] == '') {
			return false;
		} else {
			return $this->meta[$key];
		}
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


	private function has_category($checkCatId) {

		$cats = wp_get_post_categories($this->post->ID);
		return in_array($checkCatId, $cats);

	}

	/**
	 * Create a hierarchical object of the categories of a post.
	 * Stores as catArray class object following call.
	 * 
	 */

}