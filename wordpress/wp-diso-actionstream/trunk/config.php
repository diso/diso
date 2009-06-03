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

		$yaml = Spyc::YAMLLoad(dirname(__FILE__).'/config.yaml');//file straight from MT plugin - yay sharing!

		$add_services = apply_filters('actionstream_services', array('streams' => array(), 'services' => array()) );

		$yaml['action_streams'] = array_merge($yaml['action_streams'], $add_services['streams']);
		$yaml['profile_services'] = array_merge($yaml['profile_services'], $add_services['services']);
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
