<?php
/*
 Plugin Name: ActionStream Extensions
 Description: Adds additional services to the DiSo ActionStream plugin
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: trunk
 License: Dual GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) and Modified BSD (http://www.fsf.org/licensing/licenses/index_html#ModifiedBSD)
 */

function actionstream_ext_services($services) {

	// Yelp
	$services['services']['yelp'] = array(
		'name' => 'Yelp',
		'url' => 'http://%s.yelp.com/',
	);

	$services['streams']['yelp'] = array(
		'reviews' => array(
			'name' => 'Reviews',
			'description' => 'Your most recent reviews',
			'html_form' => '[_1] posted a review of <a rel="hreview" class="entry-title" href="[_2]">[_3]</a>',
			'html_params' => array('url', 'title'),
		),
	);

	
	// Dopplr
	$services['services']['dopplr'] = array(
		'name' => 'Dopplr',
		'url' => 'http://www.dopplr.com/traveller/%s',
	);
	$services['streams']['dopplr'] = array();

	// Ebay
	$services['services']['ebay'] = array(
		'name' => 'eBay',
		'url' => 'http://myworld.ebay.com/%s',
	);
	$services['streams']['ebay'] = array(); 

	// Facebook
	$services['services']['facebook'] = array(
		'name' => 'Facebook',
		'url' => 'http://facebook.com/profile.php?id=%s',
		'ident_example' => '123456789',
	);
	$services['streams']['facebook'] = array();

	// LinkedIn
	$services['services']['linkedin'] = array(
		'name' => 'LinkedIn',
		'url' => 'http://www.linkedin.com/in/%s',
	);
	$services['streams']['linkedin'] = array();

	// Ohloh
	$services['services']['ohloh'] = array(
		'name' => 'Ohloh',
		'url' => 'http://www.ohloh.com/accounts/%s',
	);
	$services['streams']['ohloh'] = array();

	// Slashdot
	$services['services']['slashdot'] = array(
		'name' => 'Slashdot',
		'url' => 'http://slashdot.org/~%s',
	);
	$services['streams']['slashdot'] = array(); 

	// MySpace
	$services['services']['myspace'] = array(
		'name' => 'MySpace',
		'url' => 'http://www.myspace.com/%s',
	);
	$services['streams']['myspace'] = array(); // TODO
	
	// 43 Things
	$services['services']['43things'] = array(
		'name' => '43 Things',
		'url' => 'http://43things.com/person/%s',
	);
	$services['streams']['43things'] = array(); 
	$services['streams']['43things'] = array(
		'things' => array(
			'name' => 'Things',
			'description' => 'Your most recent things',
			'html_form' => '[_1] is doing things: <a class="entry-title" href="[_2]">[_3]</a>',
			'html_params' => array('url', 'title'),
			'rss2' => '',
		),
	);
	
	// Technorati
	$services['services']['technorati'] = array(
		'name' => 'Technorati',
		'url' => 'http://technorati.com/people/technorati/%s',
	);
	$services['streams']['technorati'] = array();
	
	// Cork'd
	$services['services']['corkd'] = array(
		'name' => 'Cork\'d',
		'url' => 'http://corkd.com/people/%s',
	);
	$services['streams']['corkd'] = array();

	// Jyte
	$services['services']['jyte'] = array(
		'name' => 'Jyte',
		'url' => 'http://jyte.com/profile/%s',
	);
	$services['streams']['jyte'] = array(); // TODO
	
	// Yahoo!
	$services['services']['yahoo'] = array(
		'name' => 'Yahoo!',
		'url' => 'http://profiles.yahoo.com/%s',
	);
	$services['streams']['yahoo'] = array();

	// Slideshare
	$services['services']['slideshare'] = array(
		'name' => 'SlideShare',
		'url' => 'http://www.slideshare.net/%s',
	);

	$services['streams']['slideshare'] = array(
		'slidedecks' => array(
			'name' => 'Slide Decks',
			'description' => 'Your most recent slide decks',
			'html_form' => '[_1] posted a slide deck titled <a class="entry-title" href="[_2]">[_3]</a><p><a href="[_2]"><img src="[_4]" alt="[_3]" /></a></p>',
			'html_params' => array('url', 'title', 'thumbnail'),
			'url' => 'http://www.slideshare.net/rss/user/{{ident}}',
			'rss2' => array(
				'thumbnail' => 'media:group/media:thumbnail/@url',
			),
		),
	);


	return $services;
}
add_filter('actionstream_services', 'actionstream_ext_services', 5);


function actionstream_ext_styles() {
	$url = trailingslashit(get_option('siteurl')) . PLUGINDIR . '/actionstream-extensions/style.css';
	echo '<link rel="stylesheet" type="text/css" href="'.$url.'" />';
}

add_action('wp_head', 'actionstream_ext_styles', 11);
add_action('admin_head', 'actionstream_ext_styles', 11);
?>
