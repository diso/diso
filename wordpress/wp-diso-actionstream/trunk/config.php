<?php

/**
 * Load YAML file of Activity Stream services.
 *
 * @return array configuration array with two keys: action_streams and profile_services
 */
function get_actionstream_config() {
	static $yaml;

	if ( !$yaml ) {
		if ( !class_exists('Spcy') ) {
			require_once dirname(__FILE__).'/lib/spyc.php';
		}

		$add_services = apply_filters('actionstream_services', array('streams' => array(), 'services' => array()));

		// YAML data from http://github.com/singpolyma/actionstream-data
		$yaml = array(
			'action_streams'   => array_merge(Spyc::YAMLLoad(dirname(__FILE__).'/streams.yaml'), $add_services['streams']),
			'profile_services' => array_merge(Spyc::YAMLLoad(dirname(__FILE__).'/services.yaml'), $add_services['services'])
		);
	}

	return $yaml;
}

/**
 * Get the name of the items SQL table.
 */
function activity_stream_items_table() {
	global $wpdb;
	return $wpdb->prefix . 'actionstream_items';
}

?>
