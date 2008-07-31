<?php
/*
Plugin Name: wp-XRDS-Simple
Plugin URI: http://singpolyma.net/plugins/xrds/
Description: Add XRDS information to your blog.
Version: 0.1
Author: Stephen Paul Weber
Author URI: http://singpolyma.net/
*/

//Licensed under an MIT-style license



function xrds_meta() {
	echo '<meta http-equiv="X-XRDS-Location" content="'.get_bloginfo('home').'/?xrds" />'."\n";
	echo '<meta http-equiv="X-Yadis-Location" content="'.get_bloginfo('home').'/?xrds" />'."\n";
}//end xrds_meta


function xrds_add_xrd($xrds, $id, $type=array(), $expires=false) {
	if(!is_array($xrds)) $xrds = array();
	$xrds[$id] = array('type' => $type, 'expires' => $expires, 'services' => array());
	return $xrds;
}

/*
Format of $content:
array(
	'NodeName (ie, Type)' => array( array('attribute' => 'value', 'content' => 'content string') , ... ) ,
)
*/

function xrds_add_service($xrds, $xrd_id, $name, $content, $priority=10) {
	if (!is_array($xrds[$xrd_id])) {
		$xrds = xrds_add_xrd($xrds, $xrd_id);
	}
	$xrds[$xrd_id]['services'][$name] = array('priority' => $priority, 'content' => $content);
	return $xrds;
}

function xrds_write() {

	$xrds = array();
	$xrds = apply_filters('xrds_simple', $xrds);

	if($xrds['main']) {//make sure main is last
		$o = $xrds['main'];
		unset($xrds['main']);
		$xrds['main'] = $o;
	}//end if main

	$xml = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
	$xml .= '<xrds:XRDS xmlns:xrds="xri://$xrds" xmlns="xri://$xrd*($v*2.0)" xmlns:simple="http://xrds-simple.net/core/1.0" xmlns:openid="http://openid.net/xmlns/1.0">'."\n";
	foreach($xrds as $id => $xrd) {
		$xml .= '	<XRD xml:id="'.htmlspecialchars($id).'" version="2.0">' . "\n";
		$xml .= '		<Type>xri://$xrds*simple</Type>'."\n";
		if(!$xrd['type']) $xrd['type'] = array();
		if(!is_array($xrd['type'])) $xrd['type'] = array($xrd['type']);
		foreach($xrd['type'] as $type)
			$xml .= '		<Type>'.htmlspecialchars($type).'</Type>'."\n";
		if($xrd['expires'])
			$xml .= '	<Expires>'.htmlspecialchars($xrd['expires']).'</Expires>'."\n";
		foreach($xrd['services'] as $service) {
			$xml .= '		<Service priority="'.floor($service['priority']).'">'."\n";
			foreach($service['content'] as $node => $nodes) {
				if(!is_array($nodes)) $nodes = array($nodes);//sanity check
				foreach($nodes as $attr) {
					$xml .= '			<'.htmlspecialchars($node);
					if(!is_array($attr)) $attr = array('content' => $attr);//sanity check
					foreach($attr as $name => $v) {
						if($name == 'content') continue;
						$xml .= ' '.htmlspecialchars($name).'="'.htmlspecialchars($v).'"';
					}//end foreach attr
					$xml .= '>'.htmlspecialchars($attr['content']).'</'.htmlspecialchars($node).'>'."\n";
				}//end foreach content
			}//end foreach
			$xml .= '		</Service>'."\n";
		}//end foreach services
		$xml .= '	</XRD>'."\n";
	}//end foreach

	$xml .= '</xrds:XRDS>'."\n";

	return $xml;
}//end xrds_write

function xrds_checkXML($data) {//returns FALSE if $data is well-formed XML, errorcode otherwise
	$rtrn = 0;
	$theParser = xml_parser_create();
	if(!xml_parse_into_struct($theParser,$data,$vals)) {
		$errorcode = xml_get_error_code($theParser);
		if($errorcode != XML_ERROR_NONE && $errorcode != 27)
			$rtrn = $errorcode;
	}//end if ! parse
	xml_parser_free($theParser);
	return $rtrn;
}//end function checkXML

function xrds_page() {
	echo "<div class=\"wrap\">\n";
	echo "<h2>XRDS-Simple</h2>\n";

	echo '<h3>XRDS Document</h3>';
	echo '<pre>';
	echo htmlentities(xrds_write());
	echo '</pre>';

	echo '<h3>Registered Filters</h3>';
	echo '<ul>';
	global $wp_filter;
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

	echo '</div>';
}//end xrds_page

function xrds_tab($s) {
	add_submenu_page('options-general.php', 'XRDS-Simple', 'XRDS-Simple', 1, __FILE__, 'xrds_page');
	return $s;
}//end function


function xrds_parse_request($wp) {
	$accept = explode(',', $_SERVER['HTTP_ACCEPT']);
	if(isset($_GET['xrds']) || in_array('application/xrds+xml', $accept)) {
		header('Content-type: application/xrds+xml');
		echo xrds_write();
		exit;
	} else {
		header('X-XRDS-Location: '.get_bloginfo('home').'/?xrds');
		header('X-Yadis-Location: '.get_bloginfo('home').'/?xrds');
	}
}


/**
 * Contribute the AtomPub Service to XRDS-Simple.
 */
function xrds_atompub_service($xrds) {
	$xrds = xrds_add_service($xrds, 'main', 'AtomPub Service', 
		array(
			'Type' => array( array('content' => 'http://www.w3.org/2007/app') ),
			'MediaType' => array( array('content' => 'application/atomsvc+xml') ),
			'URI' => array( array('content' => get_bloginfo('wpurl').'/wp-app.php/service' ) ),
		)
	);

	return $xrds;
}

add_action('wp_head','xrds_meta');
add_action('parse_request', 'xrds_parse_request');
add_action('admin_menu', 'xrds_tab');
add_filter('xrds_simple', 'xrds_atompub_service');

?>
