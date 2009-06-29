<?php
/*
Plugin Name: XRDS-Simple
Plugin URI: http://wordpress.org/extend/plugins/xrds-simple/
Description: Provides framework for other plugins to advertise services via XRDS.
Version: trunk
Author: DiSo Development Team
Author URI: http://diso-project.org/
License: MIT license (http://www.opensource.org/licenses/mit-license.php)
*/


set_include_path(dirname(__FILE__) . '/lib' . PATH_SEPARATOR . get_include_path());

// Public Functions

/**
 * Convenience function for adding a new XRD to the XRDS structure.
 *
 * @param object $xrds current XRDS object
 * @param string $id ID of new XRD to add
 * @param mixed $type Type string or array of Type strings for the new XRD
 * @param string $expires expiration date for XRD, formatted as xs:dateTime
 * @return object newly created XRDS_XRD object
 * @since 1.1
 */
function &xrds_add_xrd(&$xrds, $id, $type=null, $expires=false) {
	$xrd;

	foreach ($xrds->xrd as $x) {
		if ($x->id == $id) {
			$xrd = $x;
			break;
		}
	}

	if (!isset($xrd) || !$xrd) {
		$xrd = new XRDS_XRD($id, $type, $expires);
		$xrds->xrd[] = $xrd;
	}

	return $xrd;
}


/**
 * Convenience function for adding a new service endpoint to the XRDS structure.
 *
 * @param object $xrds current XRDS object
 * @param string $id ID of the XRD to add the new service to.  If no XRD exists with the specified ID,
 *        a new one will be created.
 * @param object $service XRDS_Service object to add
 * @since 1.1
 */
function xrds_add_service(&$xrds, $xrd_id, &$service) {
	$xrd = xrds_add_xrd($xrds, $xrd_id);
	$xrd->service[] = $service;
}


/**
 * Convenience function for adding a new service with minimal options.  
 * Services will always be added to the 'main' XRD with the default priority.  
 * No additional parameters such as httpMethod on URIs can be passed.  If those 
 * are necessary, use xrds_add_service().
 *
 * @param object $xrds current XRDS object
 * @param string $name human readable name of the service
 * @param mixed $type one type (string) or array of multiple types
 * @param mixed $uri one URI (string) or array of multiple URIs
 * @return array updated XRDS-Simple structure
 * @since 1.1
 */
function xrds_add_simple_service(&$xrds, $name, $type, $uri) {
	if(is_array($uri)) {
		$uris = array();
		foreach($uri as $u) {
			$uris[] = new XRDS_URI($u);
		}
		$service = new XRDS_Service($type, null, $uris);
	} else {
		$service = new XRDS_Service($type, null, new XRDS_URI($uri));
	}
	xrds_add_service($xrds, 'main', $service);
}



// Private Functions

add_action('parse_request', 'xrds_parse_request');
add_action('query_vars', 'xrds_query_vars');
add_action('generate_rewrite_rules', 'xrds_rewrite_rules');

add_action('wp_head', 'xrds_meta');
add_action('admin_menu', 'xrds_admin_menu');

add_action('xrds_simple', 'xrds_atompub_service');
register_activation_hook('xrds-simple/xrds-simple.php', 'xrds_activate_plugin');

/**
 * Print HTML meta tags, advertising the location of the XRDS document.
 */
function xrds_meta() {
	echo '<meta http-equiv="X-XRDS-Location" content="' . xrds_url() . '" />'."\n";
	echo '<meta http-equiv="X-Yadis-Location" content="' . xrds_url() . '" />'."\n";
}


function xrds_activate_plugin() {
	global $wp_rewrite;

	$wp_rewrite->flush_rules();

	add_option('oauth_servers', array());
	add_option('oauth_server_tokens', array());
	add_option('oauth_consumers', array());
	add_option('oauth_consumer_tokens', array());
}

/**
 * Build the XRDS-Simple document.
 *
 * @return string XRDS-Simple XML document
 */
function xrds_write() {
	require_once 'XRDS.php';
	$xrds = new XRDS();
	xrds_add_xrd($xrds, 'main');

	do_action_ref_array('xrds_simple', array(&$xrds));
	
	// make sure main is last
	$xrd;
	for ($i=0; $i<sizeof($xrds->xrd); $i++) {
		$xrd = $xrds->xrd[$i];
		if ($xrd->id == 'main') {
			unset($xrds->xrd[$i]);
			break;
		}
	}
	if ($xrd) $xrds->xrd[] = $xrd;

	$xml = $xrds->to_xml(true);
	return $xml;
}


/**
 * Handle options page for XRDS-Simple.
 */
function xrds_options_page() {
	echo "<div class=\"wrap\">\n";
	echo "<h2>XRDS-Simple</h2>\n";

	echo '<h3>XRDS Document</h3>';
	echo '<pre>';
	echo htmlentities(xrds_write());
	echo '</pre>';

	echo '<h3>Registered Filters</h3>';
	global $wp_filter;
	if (array_key_exists('xrds_simple', $wp_filter) && !empty($wp_filter['xrds_simple'])) {
		echo '<ul>';
		foreach ($wp_filter['xrds_simple'] as $priority) {
			foreach ($priority as $idx => $data) {
				$function = $data['function'];
				if (is_array($function)) {
					list($class, $func) = $function;
					$function = "$class::$func";
				}
				echo '<li>'.$function.'</li>';
			}
		}
		echo '</ul>';
	} else {
		echo '<p>No registered filters.</p>';
	}

	echo '</div>';
}


/**
 * Add settings link to plugin page.
 */
function xrds_plugin_action_links($links, $file) {
	$this_plugin = xrds_plugin_file();

	if($file == $this_plugin) {
		$links[] = '<a href="' . add_query_arg('page', 'xrds-simple', 'options-general.php') . '">' . __('Settings') . '</a>';
	}

	return $links;
}


/**
 * Setup admin menu for XRDS.
 */
function xrds_admin_menu() {
	add_options_page('XRDS-Simple', 'XRDS-Simple', 8, 'xrds-simple', 'xrds_options_page');
	add_filter('plugin_action_links', 'xrds_plugin_action_links', 10, 2);
}


/**
 * Parse the WordPress request.  If the request is for the XRDS document, handle it accordingly.
 *
 * @param object $wp WP instance for the current request
 */
function xrds_parse_request($wp) {
	$accept = explode(',', $_SERVER['HTTP_ACCEPT']);
	if(array_key_exists('xrds', $wp->query_vars) || in_array('application/xrds+xml', $accept)) {
		if (array_key_exists('format', $_REQUEST) && $_REQUEST['format'] == 'text') { 
			@header('Content-type: text/plain');
		} else {
			@header('Content-type: application/xrds+xml');
		}
		echo xrds_write();
		exit;
	} else {
		@header('X-XRDS-Location: ' . xrds_url());
		@header('X-Yadis-Location: ' . xrds_url());
	}
}


/**
 * Get the URL for the XRDS document, based on the blog's permalink settings.
 *
 * @return string XRDS document URL
 */
function xrds_url() {
	global $wp_rewrite;

	$url = trailingslashit(get_option('home'));
	if($_SERVER['HTTPS'])
		$url = preg_replace('/^http:/', 'https:', $url);

	if ($wp_rewrite->using_permalinks()) {
		if ($wp_rewrite->using_index_permalinks()) {
			return $url . 'index.php/xrds';
		} else {
			return $url . 'xrds';
		}
	} else {
		return add_query_arg('xrds', '1', $url);
	}
}


/**
 * Add rewrite rules for XRDS.
 *
 * @param object $wp_rewrite WP_Rewrite object
 */
function xrds_rewrite_rules($wp_rewrite) {
	$xrds_rules = array( 
		'xrds' => 'index.php?xrds=1',
	);

	$wp_rewrite->rules = $xrds_rules + $wp_rewrite->rules;
}


/**
 * Add authorized query_vars for XRDS.
 *
 * @param array $vars
 */
function xrds_query_vars($vars) {
	$vars[] = 'xrds';
	return $vars;
}


/**
 * Contribute the AtomPub Service to XRDS-Simple.
 *
 * @param array $xrds current XRDS-Simple array
 * @return array updated XRDS-Simple array
 */
function xrds_atompub_service($xrds) {

	$service = new XRDS_Service(
		'http://www.w3.org/2007/app',
		'application/atomsvc+xml',
		new XRDS_URI(get_bloginfo('wpurl').'/wp-app.php/service')
	);

	xrds_add_service($xrds, 'main', $service);

	return $xrds;
}


/**
 * Get the file for the plugin, including the path.  This method will handle the case where the 
 * actual plugin files do not reside within the WordPress directory on the filesystem (such as 
 * a symlink).  The standard value should be 'xrds-simple/xrds-simple.php' unless files or folders have
 * been renamed.
 *
 * @return string plugin file
 */
function xrds_plugin_file() {
	static $file;

	if (empty($file)) {
		$path = 'xrds-simple';

		$base = plugin_basename(__FILE__);
		if ($base != __FILE__) {
			$path = basename(dirname($base));
		}

		$file = $path . '/' . basename(__FILE__);
	}

	return $file;
}

?>
