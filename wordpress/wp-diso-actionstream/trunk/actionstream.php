<?php
/*
Plugin Name: Actionstream
Version: 0.50
Plugin URI: http://singpolyma.net/plugins/actionstream/
Description: Shows updates from activities across the web.
Author: DiSo Development Team
Author URI: http://code.google.com/p/diso/
*/

//Copyright 2008 Stephen Paul Weber
//Released under the terms of an MIT-style license

register_activation_hook(__FILE__,'actionstream_plugin_activation');
add_action( 'actionstream_poll', 'actionstream_poll' );

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/classes.php';

/* wordpress */
global $actionstream_config;

function actionstream_plugin_activation() {
	global $actionstream_config;
	wp_schedule_event(time(), 'hourly', 'actionstream_poll');
	$sql = "CREATE TABLE {$actionstream_config['item_table']} (
				identifier_hash CHAR(40)  PRIMARY KEY,
				user_id INT, created_on INT,
				service CHAR(15),
				setup_idx CHAR(15),
				data TEXT
			);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}//end actionstream_plugin_activation

function actionstream_poll() {
	global $wpdb;
	$users = $wpdb->get_results("SELECT user_id, meta_value from $wpdb->usermeta WHERE meta_key='actionstream'");
	foreach($users as $user) {
		$actionstream = unserialize($user->meta_value);
		if (!is_array($actionstream) || empty($actionstream)) { continue; }
		$actionstream = new ActionStream($actionstream, $user->user_id);
		$actionstream->update();
	}//end foreach streams
}//end actionstream_poll

function get_raw_actionstream($url) {
	return wp_remote_fopen($url);
}//end function get_raw_actionstream

function actionstream_styles() {
	$url = actionstream_plugin_url() . '/css/action-streams.css';
	echo '<link rel="stylesheet" type="text/css" href="' . clean_url($url) . '" />';
}//end function actionstream_styles
add_action('wp_head', 'actionstream_styles');
add_action('admin_head', 'actionstream_styles');

function actionstream_plugin_url() {
	if (function_exists('plugins_url')) {
		return plugins_url('wp-diso-actionstream');
	} else {
		return get_bloginfo('wpurl') . '/' . PLUGINDIR . '/wp-diso-actionstream';
	}
}

function actionstream_page() {
	$actionstream_yaml = get_actionstream_config();
	$user = wp_get_current_user();

	$actionstream = get_usermeta($user->ID, 'actionstream');
	if(!$actionstream) {
		$actionstream = ActionStream::from_urls(get_usermeta($user->ID, 'user_url'), get_usermeta($user->ID, 'urls'));
		unset($actionstream['website']);
		update_usermeta($user->ID, 'actionstream', $actionstream);
		update_usermeta($user->ID, 'actionstream_local_updates', true);
		update_usermeta($user->ID, 'actionstream_collapse_similar', true);
	}//end if ! actionstream

	if ($_POST['submit']) {
		check_admin_referer('actionstream-update-services');
		update_usermeta($user->ID, 'actionstream_local_updates', isset($_POST['enable_local_updates']) ? true : false);
		update_usermeta($user->ID, 'actionstream_collapse_similar', isset($_POST['enable_collapse_similar']) ? true : false);


		if($_POST['ident']) {
			$actionstream[$_POST['service']] = $_POST['ident'];
			update_usermeta($user->ID, 'actionstream', $actionstream);
			actionstream_poll();
		}//end if ident

		if($_POST['sgapi_import']) {
			require_once dirname(__FILE__).'/sgapi.php';
			$sga = new SocialGraphApi(array('edgesout'=>1,'edgesin'=>0,'followme'=>1,'sgn'=>0));
			$xfn = $sga->get($_POST['sgapi_import']);
			$actionstream = array_merge($actionstream, ActionStream::from_urls('',array_keys($xfn['nodes'])));
			unset($actionstream['website']);
			update_usermeta($user->ID, 'actionstream', $actionstream);
		}//end if sgapi_import

	}
	get_currentuserinfo();

	if(isset($_REQUEST['update'])) {
		check_admin_referer('actionstream-update-now');
		actionstream_poll();
	}

	if(isset($_REQUEST['remove'])) {
		check_admin_referer('actionstream-remove-' . $_REQUEST['remove']);
		unset($actionstream[$_REQUEST['remove']]);
		update_usermeta($user->ID, 'actionstream', $actionstream);
	}

	echo '<div class="wrap" style="max-width: 99%;">';

	echo '	<h2>Action Stream</h2>';

	// Action Stream Preview
	echo '<div class="highlight" style="float: right; width: 47.5%; color: #333; padding: 0 1em 1em; margin: 1em; border: 1px solid #dadada; ">';
	echo '<h3>Stream Preview</h3>';
	echo '<p><b>Next Update:</b> '.round((wp_next_scheduled('actionstream_poll') - time())/60,2).' minutes';
	echo ' <small>(<a href="'.wp_nonce_url('?page=wp-diso-actionstream&update=1', 'actionstream-update-now').'">Update Now</a>)</small></p>';
	actionstream_render($user->ID, 10);
	echo' </div>';


	echo '<div style="width: 47.5%">';
	echo '	<ul style="padding:0px;">';
	ksort($actionstream);
	foreach($actionstream as $service => $id) {
		$setup = $actionstream_yaml['profile_services'][$service];
		$remove_link = wp_nonce_url('?page='.$_REQUEST['page'].'&remove='.htmlspecialchars($service), 'actionstream-remove-'.htmlspecialchars($service));
		echo '<li style="padding-left:30px;" class="service-icon service-'.htmlspecialchars($service).'"><a href="'.$remove_link.'"><img alt="Remove Service" src="'.clean_url(actionstream_plugin_url().'/images/delete.gif').'" /></a> ';
			echo htmlspecialchars($setup['name'] ? $setup['name'] : ucwords($service)).' : ';
			if($setup['url']) echo ' <a href="'.htmlspecialchars(str_replace('%s', $id, $setup['url'])).'">';
			echo htmlspecialchars($id);
			if($setup['url']) echo '</a>';
			if (empty($setup)) {
				echo ' <small><em>(configuration missing)</em></small>';
			}
			echo '</li>';
	}//end foreach actionstream
	echo '	</ul>';

	
	echo '<br />';
	echo '<h3>Update Services</h3>';
	echo '<form method="post" action="?page='.$_REQUEST['page'].'">';
	wp_nonce_field('actionstream-update-services');
	echo '<p><input type="checkbox" id="enable_local_updates" name="enable_local_updates" '.(get_usermeta($user->ID, 'actionstream_local_updates') ? 'checked="checked"' : '').'" /> <label for="enable_local_updates">Show Local Updates</a></label> </p>';
	echo '<p><input type="checkbox" id="enable_collapse_similar" name="enable_collapse_similar" '.(get_usermeta($user->ID, 'actionstream_collapse_similar') ? 'checked="checked"' : '').'" /> <label for="enable_collapse_similar">Collapse Similar Items</a></label> </p>';
	echo '<h4>Add/Update Service</h4>';
	echo '<div style="margin-left: 2em;">';
	echo '<select id="add-service" name="service" onchange="update_ident_form();">';
	ksort($actionstream_yaml['action_streams']);
	foreach($actionstream_yaml['action_streams'] as $service => $setup) {
		if($setup['scraper']) continue;//FIXME: we don't support scraper yet
		$setup = $actionstream_yaml['profile_services'][$service];
		echo '<option class="service-icon service-'.htmlspecialchars($service).'" value="'.htmlspecialchars($service).'" title="'.htmlspecialchars($setup['url']).'|'.htmlspecialchars($setup['ident_example']).'|'.htmlspecialchars($setup['ident_label']).'">';
		echo htmlspecialchars($setup['name'] ? $setup['name'] : ucwords($service));
		echo '</option>';
	}//end foreach
	echo '</select> <br />';
	echo ' <span id="add-ident-pre"></span> ';
	echo '<input type="text" id="add-ident" name="ident" /> ';
	echo ' <span id="add-ident-post"></span> <br />';
	echo '</div>';

?>
<script type="text/javascript">
	function update_ident_form() {
		var option = document.getElementById('add-service').options[document.getElementById('add-service').selectedIndex];
		var data = option.title.split(/\|/);
		document.getElementById('add-ident-pre').innerHTML = data[0].split(/%s/)[0] ? data[0].split(/%s/)[0] : '';
		document.getElementById('add-ident-post').innerHTML = data[0].split(/%s/)[1] ? data[0].split(/%s/)[1] : '';
		if(data[1]) document.getElementById('add-ident-pre').title = 'Example: ' + data[0].replace(/%s/, data[1]);
			else document.getElementById('add-ident-pre').title = '';
		document.getElementById('add-ident').title = document.getElementById('add-ident-pre').title;
		document.getElementById('add-ident').value = data[2];
	}
	update_ident_form();
</script>
<?php

	echo '<h4>Import List from Another Service</h4>';
	echo '<div style="margin-left: 2em;">';
	echo '<p>Any supported urls with <code>rel="me"</code> will be imported</p>';
	echo '<input type="text" name="sgapi_import" />';
	echo '</div>';
	echo '<p class="submit"><input type="submit" name="submit" value="Save Changes" /></p>';
	echo '</form>';

	echo '</div>';
	echo '</div>';

}//end function actionstream_page

function actionstream_plugin_actions($links, $file) {
	static $this_plugin;
	if(!$this_plugin) $this_plugin = plugin_basename(__FILE__);
	if($file == $this_plugin) {
		$settings_link = '<a href="users.php?page=wp-diso-actionstream" style="font-weight:bold;">Manage</a>';
		$links[] = $settings_link;
	}//end if this_plugin
	return $links;
}//end actionstream_plugin_actions

function actionstream_tab($s) {
	add_submenu_page('profile.php', 'Action Stream', 'Action Stream', 'read', 'wp-diso-actionstream', 'actionstream_page');
	add_filter('plugin_action_links', 'actionstream_plugin_actions', 10, 2);
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
	$item['created_on'] = strtotime($post->post_date_gmt.'Z');
	$item['ident'] = get_userdata($post->post_author);
	if($item['ident']->actionstream_local_updates) return;
	$item['ident'] = $item['ident']->display_name;
	$obj = new ActionStreamItem($item, 'website', 'posted', $post->post_author);
	$obj->save();
}//end function actionstream_wordpress_post
add_action('publish_post', 'actionstream_wordpress_post');

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
	$rtrn = $rtrn->__toString($num, $hide_user, $userdata->profile_permissions, $userdata->actionstream_collapse_similar);
	if($echo) echo $rtrn;
	return $rtrn;
}//end function actionstream_render

function actionstream_services($userid=false, $urls_only=false) {
   if(!$userid) {//get administrator
      global $wpdb;
      $userid = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='wp_user_level' AND meta_value='10'");
   }//end if ! userid
   if(is_numeric($userid)) {
      $userdata = get_userdata($userid);
   } else {
      $userdata = get_userdatabylogin($userid);
   }
   $actionstream = $userdata->actionstream;
   ksort($actionstream);

   $actionstream_yaml = get_actionstream_config(); 
	$rtrn = array();
   foreach ($actionstream as $service => $username) {
		if(function_exists('diso_user_is') && !diso_user_is($userdata->profile_permissions[$service])) continue;
	   $setup = $actionstream_yaml['profile_services'][$service];
	   if (empty($setup)) { continue; }
	   $url = sprintf($setup['url'], $username);
		if(!$urls_only) {
			if($userdata->urls && count($userdata->urls) && in_array($url, $userdata->urls))
			   array_unshift($rtrn, '<li class="service-icon service-'.$service.' profile"><a href="'.$url.'" rel="me">'.$setup['name'].'</a></li>' . "\n");
			else
			   $rtrn[] = '<li class="service-icon service-'.$service.'"><a href="'.$url.'" rel="me">'.$setup['name'].'</a></li>' . "\n";
		} else {
			$rtrn[] = $url;
		}
   }
   if(!$urls_only) $rtrn = '<ul class="actionstream_services">' . "\n" . implode("\n",$rtrn) . '</ul>' . "\n";

   return $rtrn;
}

function diso_actionstream_parse_page_token($content) {
	if(preg_match('/<!--actionstream(\((.*)\))?-->/',$content,$matches)) {
		$user = $matches[2];
		$content = preg_replace('/<!--actionstream(\((.*)\))?-->/',actionstream_render($user,10,false,false), $content);
	}//end if match

	if(preg_match('/<!--actionstream_services(\((.*)\))?-->/',$content,$matches)) {
		$user = $matches[2];
		$content = preg_replace('/<!--actionstream_services(\((.*)\))?-->/', actionstream_services($user), $content);
	}//end if match

	return $content;
}//end function diso_profile_parse_page_token
add_filter('the_content', 'diso_actionstream_parse_page_token');

//### Begin Widget ###

function widget_actionstreamwidget_init() {

	if (!function_exists('register_sidebar_widget'))
		return;
	
	function widget_actionstreamwidget($args) {
		extract($args);
				
		$options = get_option('widget_actionstreamwidget');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		actionstream_render($options['userid'], $options['num'], $options['hide_user']);
		echo $after_widget;
	}
	
	function widget_actionstreamwidget_control() {
		global $wpdb;
		$options = get_option('widget_actionstreamwidget');
		if ( !is_array($options) )
			$options = array('title'=>'ActionStream', 'userid'=>false, 'num'=>10, 'hide_user'=>false);
		if ( $_POST['actionstreamwidget-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['actionstreamwidget-title']));
			$options['userid'] = strip_tags(stripslashes($_POST['actionstreamwidget-userid']));
			$options['num'] = strip_tags(stripslashes($_POST['actionstreamwidget-num']));
			$options['hide_user'] = strip_tags(stripslashes($_POST['actionstreamwidget-hide_user']));
			update_option('widget_actionstreamwidget', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		echo '<p style="text-align:right;"><label for="actionstreamwidget-title">Title:</label><br /> <input style="width: 200px;" id="actionstreamwidget-title" name="actionstreamwidget-title" type="text" value="'.$title.'" /></p>';

		echo '<p style="text-align:right;"><label for="actionstreamwidget-userid">User:</label><br /> ';
		echo '	<select style="width: 200px;" id="actionstreamwidget-userid" name="actionstreamwidget-userid">';
		$users = $wpdb->get_results("SELECT display_name,ID FROM $wpdb->users ORDER BY user_registered,ID");
		foreach($users as $user)
			echo '		<option value="'.$user->ID.'"'.($options['userid'] == $user->ID ? ' selected="selected"' : '').'>'.htmlspecialchars($user->display_name).'</option>';
		echo '	</select>';
		echo '</p>';
		
		echo '<p style="text-align:right;"><label for="actionstreamwidget-num">Max Items:</label><br /> <input style="width: 200px;" id="actionstreamwidget-num" name="actionstreamwidget-num" type="text" value="'.$options['num'].'" /></p>';
		echo '<p style="text-align:right;"><label for="actionstreamwidget-hide_user">Hide Usernames?</label> <input id="actionstreamwidget-hide_user" name="actionstreamwidget-hide_user" type="checkbox" '.($options['hide_user'] ? 'checked="checked"' : '').' /></p>';

		echo '<input type="hidden" id="actionstreamwidget-submit" name="actionstreamwidget-submit" value="1" />';
	}
	
			
	register_sidebar_widget('Actionstream', 'widget_actionstreamwidget');
	register_widget_control('Actionstream', 'widget_actionstreamwidget_control', 270, 270);
}
add_action('plugins_loaded', 'widget_actionstreamwidget_init');


//### Begin ActionStream Services Widget ###

function widget_actionstream_services_widget_init() {

	if (!function_exists('register_sidebar_widget'))
		return;
	
	function widget_actionstream_services_widget($args) {
		extract($args);
				
		$options = get_option('widget_actionstream_services_widget');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		echo actionstream_services($options['userid']);
		echo $after_widget;
	}
	
	function widget_actionstream_services_widget_control() {
		global $wpdb;
		$options = get_option('widget_actionstream_services_widget');
		if ( !is_array($options) )
			$options = array('title'=>'ActionStream Services', 'userid'=>false, 'num'=>10, 'hide_user'=>false);
		if ( $_POST['actionstream_services_widget-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['actionstream_services_widget-title']));
			$options['userid'] = strip_tags(stripslashes($_POST['actionstream_services_widget-userid']));
			$options['num'] = strip_tags(stripslashes($_POST['actionstream_services_widget-num']));
			$options['hide_user'] = strip_tags(stripslashes($_POST['actionstream_services_widget-hide_user']));
			update_option('widget_actionstream_services_widget', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		echo '<p style="text-align:right;"><label for="actionstream_services_widget-title">Title:</label><br /> <input style="width: 200px;" id="actionstream_services_widget-title" name="actionstream_services_widget-title" type="text" value="'.$title.'" /></p>';

		echo '<p style="text-align:right;"><label for="actionstream_services_widget-userid">User:</label><br /> ';
		echo '	<select style="width: 200px;" id="actionstream_services_widget-userid" name="actionstream_services_widget-userid">';
		$users = $wpdb->get_results("SELECT display_name,ID FROM $wpdb->users ORDER BY user_registered,ID");
		foreach($users as $user)
			echo '		<option value="'.$user->ID.'"'.($options['userid'] == $user->ID ? ' selected="selected"' : '').'>'.htmlspecialchars($user->display_name).'</option>';
		echo '	</select>';
		echo '</p>';
		
		echo '<p style="text-align:right;"><label for="actionstream_services_widget-num">Max Items:</label><br /> <input style="width: 200px;" id="actionstream_services_widget-num" name="actionstream_services_widget-num" type="text" value="'.$options['num'].'" /></p>';
		echo '<p style="text-align:right;"><label for="actionstream_services_widget-hide_user">Hide Usernames?</label> <input id="actionstream_services_widget-hide_user" name="actionstream_services_widget-hide_user" type="checkbox" '.($options['hide_user'] ? 'checked="checked"' : '').' /></p>';

		echo '<input type="hidden" id="actionstream_services_widget-submit" name="actionstream_services_widget-submit" value="1" />';
	}
	
			
	register_sidebar_widget('Actionstream Services', 'widget_actionstream_services_widget');
	register_widget_control('Actionstream Services', 'widget_actionstream_services_widget_control', 270, 270);
}
add_action('plugins_loaded', 'widget_actionstream_services_widget_init');


function do_feed_action_stream() {
	global $wpdb;
	require_once(dirname(__FILE__) . '/feed.php');
}
add_action('init', create_function('', 'global $wp_rewrite; add_feed("action_stream", "do_feed_action_stream"); $wp_rewrite->flush_rules();'));

/**
 * Add ActionStream fields to DiSo Permissions plugin.
 */
function diso_actionstream_permissions($permissions) {

	$user = wp_get_current_user();
	$config = get_actionstream_config();
	$actionstream = get_usermeta($user->ID, 'actionstream');
	$fields = array();

	foreach ($actionstream as $service => $id) {
		$setup = $config['profile_services'][$service];
		$name = $setup['name'] ? $setup['name'] : ucwords($service);
		$fields[$service] = $name;
	}

	$permissions['actionstream'] = array(
		'name' => 'ActionStream Permissions',
		'order' => 5,
		'fields' => $fields,
	);

	return $permissions;
}
add_filter('diso_permission_fields', 'diso_actionstream_permissions');

/*end wordpress */

add_action( 'wp_head', create_function('', 'wp_enqueue_script("jquery");'), 9);

?>
