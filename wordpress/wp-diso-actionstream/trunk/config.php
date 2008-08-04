<?php

global $wpdb;

function get_actionstream_config() {
	if(!class_exists('Spcy'))
		require_once dirname(__FILE__).'/spyc.php5';
	static $yaml;
	if(!$yaml) {
		$yaml = Spyc::YAMLLoad(dirname(__FILE__).'/config.yaml');//file straight from MT plugin - yay sharing!

		$actionstream['streams'] = get_option('actionstream_streams');
		$actionstream['services'] = get_option('actionstream_services');
		
		$add_services = apply_filters('actionstream_services', array('streams' => array(), 'services' => array()) );

		$yaml['action_streams'] = array_merge($yaml['action_streams'], $add_services['streams']);
		$yaml['profile_services'] = array_merge($yaml['profile_services'], $add_services['services']);
	}
	return $yaml;
}//end function get_actionstream_config

global $actionstream_config;
$actionstream_config = array(
		'db' => $wpdb,
		'item_table' => $wpdb->prefix.'actionstream_items'
	);

?>
