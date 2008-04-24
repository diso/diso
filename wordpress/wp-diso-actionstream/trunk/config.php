<?php

require_once dirname(__FILE__).'/../../../wp-config.php';

function get_actionstream_config() {
	if(!class_exists('Spcy'))
		require_once dirname(__FILE__).'/spyc.php5';
	static $yaml;
	if(!$yaml) {
		$yaml = Spyc::YAMLLoad(dirname(__FILE__).'/config.yaml');//file straight from MT plugin - yay sharing!
		$streams = get_option('actionstream_streams');
		$yaml['action_streams'] = array_merge($yaml['action_streams'], $streams ? $streams : array());
		$services = get_option('actionstream_services');
		$yaml['profile_services'] = array_merge($yaml['profile_services'], $services ? $services : array());
	}
	return $yaml;
}//end function get_actionstream_config

$actionstream_config = array(
		'db' => $wpdb,
		'item_table' => $wpdb->prefix.'actionstream_items'
	);

?>
