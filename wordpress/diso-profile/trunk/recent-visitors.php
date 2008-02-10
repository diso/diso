<?php
/*

LICENSE


This program is free software; you can redistribute it 
and/or modify it under the terms of the GNU General Public 
License (GPL) as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any 
later version.

This program is distributed in the hope that it will be 
useful, but WITHOUT ANY WARRANTY; without even the 
implied warranty of MERCHANTABILITY or FITNESS FOR A 
PARTICULAR PURPOSE.  See the GNU General Public License 
for more details.

To read the license please visit
http://www.gnu.org/copyleft/gpl.html

*/

function log_recent_visitor() {
	global $user_ID;
	get_currentuserinfo();
	if(!$user_ID) return;
	$options = get_option('widget_recent_visitors');
	if(!$options['max']) $options['max'] = 5;
	$ids = $options['ids'] ? $options['ids'] : array();
	$first = array_shift($ids);
	if($first != $user_ID) array_unshift($ids, $first);
	array_unshift($ids, $user_ID);
	$ids = array_unique($ids);
	$ids = array_slice($ids, 0, $options['max']);
	$options['ids'] = $ids;
	update_option('widget_recent_visitors', $options);
}//end log_recent_visitor

function recent_visitors($echo=true) {
	global $comment; $tmpcomment = $comment; $comment = NULL; 
	$options = get_option('widget_recent_visitors');
	$output = '<ul class="visitors" style="padding:0px;list-style-type:none;">'."\n";
	foreach($options['ids'] as $id) {
		if(!$id) continue;
		$userdata = get_userdata($id);
		$output .= '	<li id="visitor-'.$id.'" class="vcard visitor" style="padding:0px;list-style-type:none;display:inline;">';
		if($userdata->user_url) $output .= '<a class="url" rel="visitor" href="'.htmlentities($userdata->user_url).'">';
		$avatar = $userdata->photo;
		if(!$avatar && function_exists('show_allavatars'))
			$avatar = show_allavatars($userdata->user_email, $userdata->user_url, $id, false);
		if($avatar)
			$output .= '<img src="'.htmlentities($avatar).'" '.(!$options['names'] ? 'class="photo fn" alt="'.htmlentities($userdata->display_name).'"' : 'class="photo" alt=""').' style="width:35px;" />';
		if($options['names']) $output .= '<span class="fn">'.htmlentities($userdata->display_name).'</span>';
		if($userdata->user_url) $output .= '</a>';
		$output .= "</li>\n";
	}//end foreach id
	$output .= '</ul>'."\n";
	if($echo) echo $output;
	$comment = $tmpcomment;
	return $output;
}//end function recent_visitors

function widget_recent_visitors_init() {
	log_recent_visitor();

	if (!function_exists('register_sidebar_widget'))
		return;
	
	
	function widget_recent_visitors($args) {
		extract($args);
				
		$options = get_option('widget_recent_visitors');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		echo recent_visitors(false);
		echo $after_widget;
	}
	
	function widget_recent_visitors_control() {
		$options = get_option('widget_recent_visitors');
		if ( !is_array($options) )
			$options = array('title'=>'Recent Visitors', 'max'=>'5', 'ids'=>array(), 'names'=>false);
		if ( $_POST['recent_visitors-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['recent_visitors-title']));
			$options['max'] = strip_tags(stripslashes($_POST['max']));
			$options['names'] = isset($_POST['names']) ? true : false;
			update_option('widget_recent_visitors', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		echo '<p style="text-align:right;"><label for="recent_visitors-title">Title:</label><br /> <input style="width: 200px;" id="recent_visitors-title" name="recent_visitors-title" type="text" value="'.$title.'" /></p>';
		echo '<p style="text-align:right;"><label for="recent_visitors-max">Number of Visitors:</label><br /> <input type="text" style="width: 200px;" id="recent_visitors-max" name="recent_visitors-max" value="'.$options['max'].'" /></p>';
		echo '<p style="text-align:right;"><label for="recent_visitors-names">Display names?</label><br /> <input type="checkbox" id="recent_visitors-names" name="recent_visitors-names" '.($options['names']?'checked="checked"':'').'" /></p>';
	}
	
			
	register_sidebar_widget('Recent Visitors', 'widget_recent_visitors');
	register_widget_control('Recent Visitors', 'widget_recent_visitors_control', 270, 270);
}

add_action('plugins_loaded', 'widget_recent_visitors_init');

?>
