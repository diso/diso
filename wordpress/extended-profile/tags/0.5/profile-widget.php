<?php

function widget_diso_profile_init() {

	if (!function_exists('register_sidebar_widget'))
		return;
	
	
	function widget_diso_profile($args) {
		extract($args);
				
		$options = get_option('widget_diso_profile');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		diso_profile($options['userid']);
		echo $after_widget;
	}
	
	function widget_diso_profile_control() {
		global $wpdb;

		$options = get_option('widget_diso_profile');
		if ( !is_array($options) )
			$options = array('title'=>'DiSo Profile', 'userid'=>false);
		if ( $_POST['diso_profile-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['diso_profile-title']));
			$options['userid'] = strip_tags(stripslashes($_POST['diso_profile-userid']));
			update_option('widget_diso_profile', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		echo '<p style="text-align:right;"><label for="diso_profile-title">Title:</label><br /> <input style="width: 200px;" id="diso_profile-title" name="diso_profile-title" type="text" value="'.$title.'" /></p>';

		echo '<p style="text-align:right;"><label for="diso_profile-userid">User:</label><br /> ';
		echo '	<select style="width: 200px;" id="diso_profile-userid" name="diso_profile-userid">';
		$users = $wpdb->get_results("SELECT display_name,ID FROM $wpdb->users ORDER BY user_registered,ID");
		foreach($users as $user)
			echo '		<option value="'.$user->ID.'"'.($options['userid'] == $user->ID ? ' selected="selected"' : '').'>'.htmlspecialchars($user->display_name).'</option>';
		echo '	</select>';
		echo '</p>';

		echo '<input type="hidden" id="diso_profile-submit" name="diso_profile-submit" value="1" />';
	}
	
			
	register_sidebar_widget('DiSo Profile', 'widget_diso_profile');
	register_widget_control('DiSo Profile', 'widget_diso_profile_control', 270, 270);
}

add_action('plugins_loaded', 'widget_diso_profile_init');

?>
