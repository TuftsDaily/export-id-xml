<?php
/*
Plugin Name: Export to InDesign XML
Plugin URI: https://github.com/TuftsDaily/daily-xml-plugin
Description: Export a post or category in XML format to import to InDesign.
Author: Andrew Stephens
Author URI: http://andrewmediaprod.com/
Version: 3.0
*/

require_once('includes/class-export-id-xml.php');
function export_id_xml() {

	$plugin = new Export_ID_XML();
	$plugin->run();

}
export_id_xml();