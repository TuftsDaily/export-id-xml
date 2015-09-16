<?php

use Handlebars\Handlebars;

class Export_ID_XML_Generator {

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
			'post_status' => 'any'
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

		// Set Category ID Values, if Necessary
		if (!isSet($this->OPINION_CATEGORY_ID)) {
			$this->OPINION_CATEGORY_ID = get_cat_ID("Opinion");
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
		$this->context['photos'] = $this->get_photos();

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

		// TODO Change Template Based on Category
		return 'standard';

	}

	private function get_title() {
		return $this->post->post_title;
	}

	private function get_author() {

		// Off-the-Hill Articles Store Author in Editorial Metadata
		if (isSet($this->meta['off-the-hill-author']) && $this->meta['off-the-hill-author'] != '')
			return $this->meta['off-the-hill-author'];


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
		if (isSet($this->meta['off-the-hill-author']) && $this->meta['off-the-hill-author'] != '')
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
		if (isSet($this->meta['off-the-hill-author']))
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
		return ( isSet( $this->meta['jumpword'] ) ) ? strtoupper($this->meta['jumpword']) : false;
	}

	private function get_conthead() {
		return ( isSet( $this->meta['conthead'] ) ) ? $this->meta['conthead'] : false;
	}

	private function get_hammer() {
		return ( isSet( $this->meta['hammer'] ) ) ? $this->meta['hammer'] : false;
	}

	private function get_kicker() {
		return ( isSet( $this->meta['kicker'] ) ) ? strtoupper($this->meta['kicker']) : false;
	}

	private function get_oth() {
		
		if (!isSet($this->meta['off-the-hill-university']) || $this->meta['off-the-hill-university'] == '')
			return false;

		$oth = [];
		$oth['university'] = $this->meta['off-the-hill-university'];
		return $oth;

	}

	private function get_column_title() {

		// TODO Get Category Name as Such: Section > Section Columns > Column Name
		return "";

	}

	private function get_photos() {

		$media = get_attached_media( 'image', $this->post->ID );
		$photos = array();

		foreach($media as $object) {
			$photos[] = [
				'url' => wp_get_attachment_url($object->ID),
				'caption' => $object->post_content,
				'credit' => $object->post_excerpt
			];
		}

		return $photos;

	}

	/////////////////////////////////////////////////////////////////
	/// Helper Functions Below
	////////////////////////////////////////////////////////////////

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
		global $post;

		$cats = wp_get_post_categories($post->ID);
		return in_array($checkCatId, $cats);

	}

	/**
	 * Create a hierarchical object of the categories of a post.
	 * Stores as catArray class object following call.
	 * 
	 */

	private function build_cat_array() {
		global $post;

		foreach(wp_get_post_categories($post->ID) as $catId) {

			$cat = get_category($catId);
			$lvl = $this->get_cat_lvl($catId);
			if (!isSet($this->catArray[$lvl])) {
				$this->catArray[$lvl] = array();
			}
			$this->catArray[$lvl][] = $cat;

		}

	}

	/**
	 * Returns the depth of a given category ID within its hierarchy.
	 * Does NOT rely on the built_cat_array() function above.
	 *
	 * For example, if a post is classified as Arts > Book Review, we would
	 * return 1 given the "Book Review" category ID.
	 *
	 * @param  integer Category ID being queried.
	 * @param  integer Level counter, used internally for recursion.
	 * @return integer Depth level of queried hierarchy. 
	 */
	private function get_cat_lvl($catId, $count=0) {
		global $post;

		$c = get_category($catId);
		if ($c->category_parent == 0) {
			return $count;
		} else {
			return $this->get_cat_lvl($c->category_parent, $count+1);
		}

	}

	/**
	 * Get category name at a given level.
	 *
	 * Given an hierarchical array of categories, get the category name at the
	 * specified level. If there are multiple categories at the level, default
	 * to the first entry.
	 * 
	 * @param  integer Level number of desired category, zero-indexed.
	 * @param  integer Which category to return, if specificity is needed.
	 * @return string Name of category that matches given criteria.
	 */
	private function get_cat_name_at_lvl($lvl, $which=0) {
		return $this->catArray[$lvl][$which]->name;
	}

}