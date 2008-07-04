<?php
/*
 Plugin Name: ActionStream Extensions
 Description: Adds additional services to the DiSo ActionStream plugin
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: trunk
 License: Dual GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) and Modified BSD (http://www.fsf.org/licensing/licenses/index_html#ModifiedBSD)
 */

function actionstream_ext_services($services, $streams) {
	$services['yelp'] = array(
		'name' => 'Yelp',
		'url' => 'http://%s.yelp.com/',
	);

	$streams['yelp'] = array(
		'reviews' => array(
			'name' => 'Reviews',
			'description' => 'Your most recent reviews',
			'html_form' => '[_1] posted a review of <a rel="hreview" class="entry-title" href="[_2]">[_3]</a>',
			'html_params' => array('url', 'title'),
		),
	);

	return array($services, $streams);
}
add_filter('actionstream_services', 'actionstream_ext_services', 5, 2);


function actionstream_ext_styles() {
	$url = trailingslashit(get_option('siteurl')) . PLUGINDIR . '/actionstream-extensions/style.css';
	echo '<link rel="stylesheet" type="text/css" href="'.$url.'" />';
}

add_action('wp_head', 'actionstream_ext_styles', 11);
add_action('admin_head', 'actionstream_ext_styles', 11);
?>
