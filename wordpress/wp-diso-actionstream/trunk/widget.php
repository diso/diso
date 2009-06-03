<?php

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

