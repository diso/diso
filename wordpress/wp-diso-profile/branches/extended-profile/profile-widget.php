<?php

function widget_ext_profile_init() {

	if (!function_exists('register_sidebar_widget'))
		return;
	
	
	function widget_ext_profile($args) {
		extract($args);
				
		$options = get_option('widget_ext_profile');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		extended_profile($options['userid']);
		echo $after_widget;
	}
	
	function widget_ext_profile_control() {
		global $wpdb;

		$options = get_option('widget_ext_profile');
		if ( !is_array($options) )
			$options = array('title'=>'Extended Profile', 'userid'=>false);
		if ( $_POST['submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['title']));
			$options['userid'] = strip_tags(stripslashes($_POST['userid']));
			update_option('widget_ext_profile', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		echo '<p style="text-align:right;"><label for="title">Title:</label><br /> <input style="width: 200px;" id="title" name="title" type="text" value="'.$title.'" /></p>';

		echo '<p style="text-align:right;"><label for="userid">User:</label><br /> ';
		echo '	<select style="width: 200px;" id="userid" name="userid">';
		$users = $wpdb->get_results("SELECT display_name,ID FROM $wpdb->users ORDER BY user_registered,ID");
		foreach($users as $user)
			echo '		<option value="'.$user->ID.'"'.($options['userid'] == $user->ID ? ' selected="selected"' : '').'>'.htmlspecialchars($user->display_name).'</option>';
		echo '	</select>';
		echo '</p>';

		echo '<input type="hidden" id="submit" name="submit" value="1" />';
	}
	
			
	register_sidebar_widget('Extended Profile', 'widget_ext_profile');
	register_widget_control('Extended Profile', 'widget_ext_profile_control', 270, 270);
}

add_action('plugins_loaded', 'widget_ext_profile_init');

?>
