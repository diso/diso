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
	// Yelp
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

	// Bright Kite
	$services['brightkite'] = array(
		'name' => 'Brightkite',
		'url' => 'http://brightkite.com/people/%s',
	);

	$streams['brightkite'] = array(
		'checkins' => array(
			'name' => 'Checkins',
			'description' => 'Your most recent checkins',
			'html_form' => '[_1] <a href="[_2]">checked in</a> @ <a class="entry-title" href="[_3]">[_4]</a>',
			'html_params' => array('url', 'placeLink', 'placeName'),
			'url' => 'http://brightkite.com/people/{{ident}}/objects.rss',
			'xpath' => array(
				'foreach' => "//item[bk:eventType='checkin']",
				'get' => array(
					'created_on' => 'pubDate/child::text()',
					'title' => 'title/child::text()',
					'url' => 'link/child::text()',
					'placeLink' => 'bk:placeLink/child::text()',
					'placeName' => 'bk:placeName/child::text()',
				),
			),
		),
		'photos' => array(
			'name' => 'Photos',
			'description' => 'Your most recent photos',
			'html_form' => '[_1] posted a photo @ <a class="entry-title" href="[_3]">[_4]</a><p><a title="[_5]" href="[_2]"><img src="[_6]" alt="[_5]" /></a></p>',
			'html_params' => array('url', 'placeLink', 'placeName', 'caption', 'thumbnail'),
			'url' => 'http://brightkite.com/people/{{ident}}/objects.rss',
			'xpath' => array(
				'foreach' => "//item[bk:eventType='photo']",
				'get' => array(
					'created_on' => 'pubDate/child::text()',
					'url' => 'link/child::text()',
					'placeLink' => 'bk:placeLink/child::text()',
					'placeName' => 'bk:placeName/child::text()',
					'caption' => 'bk:photoCaption/child::text()',
					'thumbnail' => 'media:thumbnail/@url',
				),
			),
		),
		'messages' => array(
			'name' => 'Messages',
			'description' => 'Your most recent text messages',
			'html_form' => '[_1] <a href="[_2]">posted a text message</a> @ <a class="entry-title" href="[_3]">[_4]</a><p>[_5]</p>',
			'html_params' => array('url', 'placeLink', 'placeName', 'message'),
			'url' => 'http://brightkite.com/people/{{ident}}/objects.rss',
			'xpath' => array(
				'foreach' => "//item[bk:eventType='message']",
				'get' => array(
					'created_on' => 'pubDate/child::text()',
					'title' => 'title/child::text()',
					'url' => 'link/child::text()',
					'placeLink' => 'bk:placeLink/child::text()',
					'placeName' => 'bk:placeName/child::text()',
					'message' => 'description/child::text()',
				),
			),
		),
	);

	
	// Dopplr
	$services['dopplr'] = array(
		'name' => 'Dopplr',
		'url' => 'http://www.dopplr.com/traveller/%s',
	);
	$streams['dopplr'] = array();

	// Ebay
	$services['ebay'] = array(
		'name' => 'eBay',
		'url' => 'http://myworld.ebay.com/%s',
	);
	$streams['ebay'] = array(); 

	// Facebook
	$services['facebook'] = array(
		'name' => 'Facebook',
		'url' => 'http://facebook.com/profile.php?id=%s',
		'ident_example' => '123456789',
	);
	$streams['facebook'] = array();

	// LinkedIn
	$services['linkedin'] = array(
		'name' => 'LinkedIn',
		'url' => 'http://www.linkedin.com/in/%s',
	);
	$streams['linkedin'] = array();

	// Ohloh
	$services['ohloh'] = array(
		'name' => 'Ohloh',
		'url' => 'http://www.ohloh.com/accounts/%s',
	);
	$streams['ohloh'] = array();

	// Slashdot
	$services['slashdot'] = array(
		'name' => 'Slashdot',
		'url' => 'http://slashdot.org/~%s',
	);
	$streams['slashdot'] = array(); 

	// MySpace
	$services['myspace'] = array(
		'name' => 'MySpace',
		'url' => 'http://www.myspace.com/%s',
	);
	$streams['myspace'] = array(); // TODO
	
	// 43 Things
	$services['43things'] = array(
		'name' => '43 Things',
		'url' => 'http://43things.com/person/%s',
	);
	$streams['43things'] = array(); // TODO
	
	// Technorati
	$services['technorati'] = array(
		'name' => 'Technorati',
		'url' => 'http://technorati.com/people/technorati/%s',
	);
	$streams['technorati'] = array();
	
	// Cork'd
	$services['corkd'] = array(
		'name' => 'Cork\'d',
		'url' => 'http://corkd.com/people/%s',
	);
	$streams['corkd'] = array();

	// Jyte
	$services['jyte'] = array(
		'name' => 'Jyte',
		'url' => 'http://jyte.com/profile/%s',
	);
	$streams['jyte'] = array(); // TODO
	
	// Yahoo!
	$services['yahoo'] = array(
		'name' => 'Yahoo!',
		'url' => 'http://profiles.yahoo.com/%s',
	);
	$streams['yahoo'] = array();

	// Get Satisfaction
	$services['getsatisfaction'] = array(
		'name' => 'Get Satisfaction',
		'url' => 'http://getsatisfaction.com/people/%s',
	);
	$streams['getsatisfaction'] = array(
		'activity' => array(
			'name' => 'Activity',
			'description' => 'Your most recent activity',
			'html_form' => '<a class="entry-title" href="[_2]">[_3]</a>',
			'html_params' => array('url', 'title'),
			'url' => 'http://getsatisfaction.com/people/{{ident}}.rss',
			'identifier' => 'url',
			'xpath' => array(
				'foreach' => '//item',
				'get' => array(
					'created_on' => 'pubDate/child::text()',
					'title' => 'title/child::text()',
					'url' => 'link/child::text()',
				),
			),
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
