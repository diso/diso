<?php
/*
Plugin Name: Actionstream
Version: 0.20
Plugin URI: http://singpolyma.net/plugins/actionstream/
Description: Shows updates from activities across the web.
Author: Stephen Paul Weber (inspired by http://www.movabletype.org/2008/01/building_action_streams.html)
Author URI: http://singpolyma.net/
*/

//Copyright 2008 Stephen Paul Weber
//Released under the terms of an MIT-style

function get_actionstream_config() {
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

/* wordpress */
$actionstream_config = array(
		'db' => $wpdb,
		'item_table' => $wpdb->prefix.'actionstream_items'
	);

function actionstream_plugin_activation() {
	global $actionstream_config, $wpdb;
   $wpdb->query("CREATE TABLE IF NOT EXISTS {$actionstream_config['item_table']} (identifier_hash CHAR(40) PRIMARY KEY, user_id INT, created_on INT, service CHAR(15), setup_idx CHAR(15), data TEXT)");
   wp_schedule_event(time(), 'hourly', 'actionstream_poll');
}//end minifeed_activation
register_activation_hook(__FILE__,'actionstream_plugin_activation');

function actionstream_poll() {
	$streams = get_option('actionstreams');
	foreach($streams as $stream_user) {
		$userdata = get_userdata($stream_user);
		$actionstream = new ActionStream($userdata->actionstream, $stream_user);
		$actionstream->update();
	}//end foreach streams
}//end actionstream_poll
add_action( 'actionstream_poll', 'actionstream_poll' );

function get_raw_actionstream($url) {
	return wp_remote_fopen($url);
}//end function get_raw_actionstream

function actionstream_styles() {
	$url = get_bloginfo('wpurl');
	$url = $url . '/wp-content/plugins/wp-diso-actionstream/css/action-streams.css';
	echo '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
}//end function actionstream_styles
add_action('wp_head', 'actionstream_styles');
add_action('admin_head', 'actionstream_styles');

function actionstream_page() {
	global $userdata;
	require_once dirname(__FILE__).'/../../../wp-includes/pluggable.php';
	get_currentuserinfo();
	$actionstream_yaml = get_actionstream_config();

	$streams = get_option('actionstreams');
	if(!$streams) $streams = array();
	$streams[$userdata->ID] = $userdata->ID;
	update_option('actionstreams', $streams);

	if(!$userdata->actionstream) {
		$userdata->actionstream = ActionStream::from_urls($userdata->user_url, $userdata->urls);
		unset($userdata->actionstream['website']);
		update_usermeta($userdata->ID, 'actionstream', $userdata->actionstream);
	}//end if ! actionstream

	if($_POST['ident']) {
		$userdata->actionstream[$_POST['service']] = $_POST['ident'];
		update_usermeta($userdata->ID, 'actionstream', $userdata->actionstream);
		actionstream_poll();
	}//end if ident

	if($_POST['sgapi_import']) {
		require_once dirname(__FILE__).'/sgapi.php';
		$sga = new SocialGraphApi(array('edgesout'=>1,'edgesin'=>0,'followme'=>1,'sgn'=>0));
		$xfn = $sga->get($_POST['sgapi_import']);
		$userdata->actionstream = array_merge($userdata->actionstream, ActionStream::from_urls('',array_keys($xfn['nodes'])));
		unset($userdata->actionstream['website']);
		update_usermeta($userdata->ID, 'actionstream', $userdata->actionstream);
	}//end if sgapi_import

	echo '<div class="wrap">';

	echo '	<h2>Action Stream Services</h2>';
	echo '	<ul>';
	foreach($userdata->actionstream as $service => $id)
		echo '<li>'.htmlspecialchars(ucwords($service)).' : '.htmlspecialchars($id).'</li>';
	echo '	</ul>';

	echo '<h3>Add a Service</h3>';
	echo '<form method="post" action=""><div>';
	echo '<select name="service">';
	foreach($actionstream_yaml['action_streams'] as $service => $setup) {
		$setup = $actionstream_yaml['profile_services'][$service];
		echo '<option value="'.htmlspecialchars($service).'">';
		echo htmlspecialchars($setup['name'] ? $setup['name'] : ucwords($service));
		echo '</option>';
	}//end foreach
	echo '</select> ';
	echo '<input type="text" name="ident" /> ';
	echo '<input type="submit" value="Add &raquo;" />';
	echo '</div></form>';

	echo '<h3>Import Services</h3>';
	echo '<form method="post" action=""><div>';
	echo '<input type="text" name="sgapi_import" />';
	echo '<input type="submit" value="Go &raquo;" />';
	echo '</div></form>';

	echo '<h2>Stream Preview</h2>';
	echo '<p><b>Next Update:</b> '.round((wp_next_scheduled('actionstream_poll') - time())/60,2).' minutes</p>';
	actionstream_render($userdata->ID, 10);

	echo '</div>';

}//end function actionstream_page

function actionstream_tab($s) {
	add_submenu_page('profile.php', 'Action Stream', 'Action Stream', 'read', __FILE__, 'actionstream_page');
	return $s;
}//end function actionstream_tab
add_action('admin_menu', 'actionstream_tab');

function actionstream_wordpress_post($post_id) {
	$post = get_post($post_id);
	$item = array();
	$item['title'] = $post->post_title;
	$item['url'] = get_permalink($post->ID);
	$item['identifier'] = $item['url'];
	$item['description'] = $post->post_excerpt;
	if(!$item['description']) $item['description'] = substr(html_entity_decode(strip_tags($post->post_content)),0,200);
	$item['created_on'] = strtotime($post->post_date_gmt);
	$item['ident'] = get_userdata($post->post_author);
	$item['ident'] = $item['ident']->display_name;
	$obj = new ActionStreamItem($item, 'website', 'posted', $post->post_author);
	$obj->save();
}//end function actionstream_wordpress_post
add_action('publish_post', 'actionstream_wordpress_post');

function actionstream_service_register($name, $profile_definition, $stream_definition) {
	$services = get_option('actionstream_streams');
	if(!is_array($services)) $services = array();
	$services[$name] = $stream_definition;
	update_option('actionstream_streams', $services);

	$services = get_option('actionstream_services');
	if(!is_array($services)) $services = array();
	$services[$name] = $profile_definition;
	update_option('actionstream_services', $services);
}//end function actionstream_service_register

function actionstream_render($userid=false, $num=10, $hide_user=false, $echo=true) {
   if(!$userid) {//get administrator
      global $wpdb;
      $userid = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='wp_user_level' AND meta_value='10'");
   }//end if ! userid
   if(is_numeric($userid))
      $userdata = get_userdata($userid);
   else
      $userdata = get_userdatabylogin($userid);
	$rtrn = new ActionStream($userdata->actionstream, $userdata->ID);
	$rtrn = $rtrn->__toString($num, $hide_user);
	if($echo) echo $rtrn;
	return $rtrn;
}//end function actionstream_render

/*end wordpress */

class ActionStreamItem {

	protected $data, $service, $setup_idx, $config, $user_id;

	function __construct($data, $service, $setup_idx, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->service = $service;
		$this->setup_idx = $setup_idx;
		$this->user_id = $user_id;

		if($data) {
			if(is_array($data)) {
				$this->data = $data;
			} else {
				global $actionstream_config, $actionstream_config;
				$data = $actionstream_config['db']->get_result("SELECT data,service,setup_idx FROM {$actionstream_config['item_table']} WHERE identifier_hash='$data'", ARRAY_A);
				$this->data = unserialize($data[0]['data']);
				$this->service = unserialize($data[0]['service']);
				$this->setup_idx = unserialize($data[0]['setup_idx']);
			}//end if-else is_array data
		} else {
			$this->data = array();
		}//end if-else data
	}//end constructor

	function set($k, $v) {
		$this->data[$k] = $v;
	}//end function set

	function save() {
		global $actionstream_config;
		$identifier_field = $this->config['action_streams'][$this->service][$this->setup_idx]['identifier'];
		if(!$identifier_field) $identifier_field = 'identifier';
		$identifier_hash = sha1($this->data[$identifier_field]);
		if(!$this->data['created_on'] && $this->data['modified_on']) $this->data['created_on'] = $this->data['modified_on'];
		$created_on = (int)$this->data['created_on'] ? (int)$this->data['created_on'] : time();
		$data = $actionstream_config['db']->escape(serialize($this->data));
		$actionstream_config['db']->query("INSERT INTO {$actionstream_config['item_table']} (identifier_hash, user_id, created_on, service, setup_idx, data) VALUES ('$identifier_hash', $this->user_id, $created_on, '$this->service', '$this->setup_idx', '$data') ON DUPLICATE KEY UPDATE data='$data'");
	}//end function save

	function __toString($hide_user=false) {
		if($hide_user) $this->data['ident'] = '';
		return ActionStreamItem::interpolate($this->data, $this->config['action_streams'][$this->service][$this->setup_idx]['html_params'], $this->config['action_streams'][$this->service][$this->setup_idx]['html_form']);
	}//end function toString

	protected static function interpolate($data, $fields, $template) {
		array_unshift($fields, 'ident');
		foreach($fields as $i => $k) {
			if($data[$k] == strip_tags($data[$k])) $data[$k] = htmlspecialchars($data[$k]);
			$template = str_replace('[_'.($i+1).']', $data[$k], $template);
		}//end foreach fields
		return $template;
	}//end function interpolate

}//end class ActionStreamItem

class ActionStream {

	protected $config, $ident, $user_id;

	function __construct($ident, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->ident = $ident;
		$this->user_id = $user_id;
	}//end constructor

	function update() {

		foreach($this->ident as $service => $id) {
			$setup = $this->config['action_streams'][$service];
			//TODO: HTML/RSS/Microformats
			foreach($setup as $setup_idx => $stream) {
				$url = str_replace('{{ident}}', $id, $stream['url']);
				if(!$url) {//feed autodetect
					$raw = get_raw_actionstream(str_replace('%s',$id,$this->config['profile_services'][$service]['url']));

					preg_match('/<[\s]*link.+atom.+href="(.+)"/', $raw, $match);
					$aurl = html_entity_decode($match[1]);

					preg_match('/<[\s]*link.+rss.+href="(.+)"/', $raw, $match);
					$rurl = html_entity_decode($match[1]);

					if(($stream['atom'] && $aurl) || !$rurl) {
						$url = $aurl;
						if(!$stream['atom']) $stream['atom'] = array();
					} else {
						$url = $rurl;
						if(!$stream['rss2']) $stream['rss2'] = array();
					}//end if-else atom/rss2
					if(!$url) continue;
				}//end if ! url
				$raw = get_raw_actionstream($url);
				if(!$raw) continue;

				if($stream['atom']) {
					$stream['xpath'] = array(
							'foreach' => '//entry',
							'get' => array_merge(array(
								'created_on' => 'published/child::text()',
								'modified_on' => 'updated/child::text()',
								'title' => 'title/child::text()',
								'url' => 'link[@rel=\'alternate\']/@href',
								'identifier' => 'id/child::text()'
							), $stream['atom'])
					);
				}//end if atom

				if($stream['rss2']) {
					$stream['xpath'] = array(
							'foreach' => '//item',
							'get' => array_merge(array(
								'created_on' => 'pubDate/child::text()',
								'title' => 'title/child::text()',
								'url' => 'link/child::text()',
								'identifier' => 'guid/child::text()'
							), $stream['rss2'])
					);
				}//end if atom

				if($stream['xpath']) {
					$doc = simplexml_load_string(str_replace('xmlns=','a=',$raw));
					@$doc->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
					@$doc->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
					@$doc->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
					@$items = $doc->xpath($stream['xpath']['foreach']);
					if(!$items) $items = array();

					foreach($items as $item) {
						$update = new ActionStreamItem(array('ident' => $id), $service, $setup_idx, $this->user_id);
						foreach($stream['xpath']['get'] as $k => $p) {
							@$value = $item->xpath($p);
							$value = $value[0].'';
							if($service == 'twitter') {
								$value = preg_replace('/^'.$id.'\: /','',$value);
								$value = preg_replace('/@([a-zA-z0-9_]+)/','@<a href="http://twitter.com/$1">$1</a>',$value);
								$value = preg_replace('/#([a-zA-z0-9_]+)/','#<a href="http://hashtags.org/tag/$1" rel="tag">$1</a>',$value);
							}//end if twitter
							if(($k == 'created_on' || $k == 'modified_on') && !is_numeric($value)) $value = strtotime($value);
							$update->set($k, $value);
						}//end get
						$update->save();
					}//end foreach items
				}//end if xpath

			}//end foreach setup
		}//end foreach ident

	}//end function update

	function __toString($num=10, $hide_user=false) {
		global $actionstream_config;
		$items = $actionstream_config['db']->get_results("SELECT created_on,service,setup_idx,data,user_id FROM {$actionstream_config['item_table']} ".($this->user_id ? 'WHERE user_id='.$this->user_id.' ' : '')."ORDER BY created_on DESC", ARRAY_A);
		if(!$items || !count($items))
			return 'No items to display in actionstream.';
		$previous_service = false;
		$during_service = '';
		$after_service = array();
		$previous_day = 0;
		$c = 0;
		if(count($items) <= $num) $num = count($items);
		foreach($items as $item) {

			if($item['service'] == $previous_service && date('Y-m-d',$item['created_on']) == $previous_day) {

				$after_service[] = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

			} else {

				$c++;

				if($during_service) {
					$rtrn .= '<li class="hentry service-icon service-'.$previous_service.'">'.$during_service->__toString($hide_user);
					if(count($after_service)) $rtrn .= ' (and '.count($after_service).' more...)';
					$rtrn .= '</li>';
				}//end if during service

				foreach($after_service as $cnt)
					$rtrn .= '<li class="hentry service-icon service-'.$previous_service.'" style="display:none;">'.$cnt.'</li>';
				$after_service = array();

				if(date('Y-m-d',$item['created_on']) != $previous_day) {//new day
					if($previous_day) $rtrn .= '</ul>';
					if($c > $num) break;
					$previous_day = date('Y-m-d',$item['created_on']);
					$rtrn .= '<h3 class="action-stream-header">On '.$previous_day.'</h3>';
					$rtrn .= '<ul class="hfeed action-stream-list">';
				}//end if new day

				$during_service = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

			}//end if-else service

			$previous_service = $item['service'];
		}//end foreach
		return $rtrn;
	}//end function toString

	static function from_urls($url, $urls) {
		$actionstream_yaml = get_actionstream_config();
		$urls[] = $url;
		$urls = array_unique($urls);
		$ident = array();
		foreach($urls as $url) {
			foreach($actionstream_yaml['profile_services'] as $service => $setup)  {
				$regex = '/'.str_replace('%s', '(.*)', preg_quote($setup['url'],'/')).'?/';
				if(preg_match($regex, $url, $match)) {
					$match[1] = explode('/', $match[1]);
					$match[1] = $match[1][0];
					$ident[$service] = $match[1];
					break;
				}//end if preg_match
			}//echo foreach action_streams
		}//end foreach urls
		return $ident;
	}//end function from_urls

}//end class ActionStream

?>
