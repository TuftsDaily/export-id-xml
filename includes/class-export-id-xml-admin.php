<?php

class Export_ID_XML_Admin {

	public function run() {
		
		$post_meta = new Export_ID_XML_Admin_PostMeta();
		$user_meta = new Export_ID_XML_Admin_UserMeta();

		$post_meta->init();
		$user_meta->init();

	}
	
}

class Export_ID_XML_Admin_PostMeta {

	private $inputs = array();
	private $data = array();

	public static $META_KEY_NAME = 'export_id_xml_options';

	public function __construct() {

		$this->inputs = array(
			new Export_ID_XML_InputDef('print-date', 'date', 'Print Date', 'When will this article appear in print?'),
			new Export_ID_XML_InputDef('jumpword', 'text', 'Jump Word', 'Used to connect two parts of article in print.'),
			new Export_ID_XML_InputDef('conthead', 'box', 'Continuation Headline', 'Headline shown on second part of article in print.'),
			new Export_ID_XML_InputDef('kicker', 'text', 'Kicker', 'Small print tag to categorize article. (ex. Album Review)'),
			new Export_ID_XML_InputDef('hammer', 'box', 'Hammer', 'Large print, bold but short headline to attract attention. (ex. "Monaco dies")'),
			new Export_ID_XML_InputDef('off-the-hill-university', 'text', 'Off the Hill University', null, 'off-the-hill'),
			new Export_ID_XML_InputDef('off-the-hill-author', 'text', 'Off the Hill Author', null, 'off-the-hill')
			// new Export_ID_XML_InputDef('inside-this-issue', 'text', 'Inside This Issue', 'Text to display on the top of the front page.')
		);

	}

	public function init() {

		// Hook for Creating the Box
		$meta_box_init = function() {
			add_meta_box('export_id_xml_options', 'Print Options', array($this, 'display'), 'post', 'side', 'default');
		};
		add_action('add_meta_boxes', $meta_box_init);

		// Hook for Saving Contents
		add_action('save_post', array($this, 'save'), 10, 2);

		// Hook for Loading JS/CSS into Admin Area
		$meta_box_scripts = function() {
			wp_enqueue_script('export_id_xml_script', plugins_url('admin/postmeta-script.js', __FILE__));
			wp_enqueue_style('export_id_xml_style', plugins_url('admin/postmeta-style.css', __FILE__));
		};
		add_action('admin_enqueue_scripts', $meta_box_scripts);

	}

	public function display() {

		global $post;

		wp_nonce_field(basename(__FILE__), 'export_id_xml_nonce');

		/* Preload the existing data for the form. */
		$this->data = get_post_meta($post->ID, self::$META_KEY_NAME, true);

		foreach($this->inputs as $input) {

			// This is admittedly a dumb construct but allows for future expansion.
			switch($input->type) {

				case 'box':

					echo '<label for="export_id_xml_'.$input->id.'" />';
						echo '<strong>'.$input->title.'</strong>';
						if ($input->detail) { echo '<br /><em>'.$input->detail.'</em>'; }
					echo '</label>';

					echo '<textarea';
					echo ' id="'.$input->gen_html_id().'"';
					echo ' name="'.$input->gen_html_id().'"';
					if ($input->showfor) {
						echo ' class="showfor" data-showfor="'.$input->showfor.'"';
					}
					echo '>';
					if ($this->data != '' && isSet($this->data[$input->id])) {
						echo $this->data[$input->id];
					}
					echo '</textarea>';
					break;

				default:
					echo '<label for="export_id_xml_'.$input->id.'" />';
						echo '<strong>'.$input->title.'</strong>';
						if ($input->detail) { echo '<br /><em>'.$input->detail.'</em>'; }
					echo '</label>';

					echo '<input type="'.$input->type.'"';
					echo ' id="'.$input->gen_html_id().'"';
					echo ' name="'.$input->gen_html_id().'"';
					if ($this->data != '' && isSet($this->data[$input->id])) {
						echo ' value="'.$this->data[$input->id].'"';
					}
					if ($input->showfor) {
						echo ' class="showfor" data-showfor="'.$input->showfor.'"';
					}
					echo ' />';
			}


		}

	}

	public function save($post_id, $post) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['export_id_xml_nonce'] ) || !wp_verify_nonce( $_POST['export_id_xml_nonce'], basename( __FILE__ ) ) )
    		return $post_id;

    	/* Get the post type object. */
  		$post_type = get_post_type_object( $post->post_type );

  		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		$id_xml_postmeta = array();
		foreach($this->inputs as $input) {
			$id_xml_postmeta[$input->id] = esc_attr($_POST[$input->gen_html_id()]);
		}
		update_post_meta( $post_id, self::$META_KEY_NAME, $id_xml_postmeta );

		/* Save print date separately because we query by it later. */
		if ( isSet( $id_xml_postmeta['print-date'] ) ) {
			update_post_meta( $post_id, 'export_id_xml_print_date', $id_xml_postmeta['print-date'] );	
		}

	}

}

class Export_ID_XML_Admin_UserMeta {

	public function init() {

		add_action('show_user_profile', array($this, 'display'));
		add_action('edit_user_profile', array($this, 'display'));
		add_action('profile_update', array($this, 'save'));

	}

	public function display($user) {
?>

	<h3>Print Information</h3>

	<table class="form-table">

		<tr>
			<th><label for="daily-rank">Rank</label></th>

			<td>
				<input type="text" name="daily-rank" id="daily-rank" value="<?php echo esc_attr( get_the_author_meta( 'daily-rank', $user->ID ) ); ?>" class="regular-text" /><br />
				<span class="description">Something like "Executive News Editor", "Arts Editor", or "Assistant Features Editor".</span>
			</td>
		</tr>

	</table>

<?php
	}

	public function save($user_id) {

		if (!current_user_can('edit_user', $user_id)) {
			die('bad permission');
			return false;
		}

		$rank = $_POST['daily-rank'];
		update_user_meta( $user_id, 'daily-rank', $rank );

		if (!$rank) { return; }
		$this->autogen_bio($rank, $user_id);

	}

	private function autogen_bio($rank, $user_id) {

		$existing_bio = get_the_author_meta('description', $user_id);

		if (!$existing_bio) {

			// Choose the/a/an
			$vowels = array('a', 'e', 'i', 'o', 'u');
			if (strpos($rank, 'Executive') !== false) {
				$article = 'the';
			} else if (in_array(strtolower($rank[0]), $vowels)) {
				$article = 'an';
			} else {
				$article = 'a';
			}

			$name = get_the_author_meta('display_name', $user_id);
			
			// Something Like: Andrew Stephens is a Layout Editor at the Tufts Daily.
			$bio = $name.' is '.$article.' '.$rank.' at the Tufts Daily.';

			update_user_meta($user_id, 'description', $bio);
		}

	}

}

/**
 * Input Definition class.
 * Stores data about available inputs for use in form generation above.
 * Essentially a struct.
 */
class Export_ID_XML_InputDef {

	/**
	 * HTML idenifier for the input to be created.
	 * @var string
	 */
	public $id;

	/**
	 * HTML input type.
	 * Used for display and validation.
	 * @var string
	 */
	public $type;

	/**
	 * Input title (used for display).
	 * @var string
	 */
	public $title;

	/**
	 * Provides detail about what is expected for the input (used for display).
	 * Optional.
	 * @var string
	 */
	public $detail;

	/**
	 * Post categories to show the input for.
	 * Such inputs are hidden by default and then show using Javascript.
	 * @var string
	 */
	public $showfor;

	public function __construct($id, $type, $title, $detail='', $showfor=null) {
		$this->id = $id;
		$this->type = $type;
		$this->title = $title;
		$this->detail = $detail;
		$this->showfor = $showfor;
	}

	public function gen_html_id() {
		return 'export_id_xml_'.$this->id;
	}

}