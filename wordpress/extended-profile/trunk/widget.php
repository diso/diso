<?php

add_action('plugins_loaded', 'widget_user_profile_init');

/**
 * Initialize User Profile widget.  This includes all of the logic for managing and displaying the widget.
 */
function widget_user_profile_init() {

	if (!function_exists('register_sidebar_widget')) {
		return;
	}
	
	/**
	 * Display user profile widget.
	 *
	 * @param array $args widget configuration.
	 */
	function widget_user_profile($args) {
		extract($args);
				
		$options = get_option('widget_user_profile');
		$title = $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		extended_profile($options['user']);
		echo $after_widget;
	}
	

	/**
	 * Manage user profile widget.
	 */
	function widget_user_profile_control() {
		$options = get_option('widget_user_profile');

		if ( !is_array($options) )
			$options = array('title'=>'User Profile', 'user'=>false);
		
		if ( $_POST['profile_submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['profile_title']));
			$options['user'] = strip_tags(stripslashes($_POST['profile_user']));
			update_option('widget_user_profile', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		?>

		<p style="text-align:right;">
			<label for="profile_title">Title:</label><br /> 
			<input style="width: 200px;" id="profile_title" name="profile_title" type="text" value="<?php echo $title; ?>" />
		</p>

		<p style="text-align:right;"><label for="profile_user">User:</label><br /> 
			<?php wp_dropdown_users(array('selected' => $options['user'], 'name' => 'profile_user')); ?>
		</p>
		<style type="text/css"> #profile_user { width: 200px; } </style>

		<input type="hidden" id="profile_submit" name="profile_submit" value="1" />

		<?php
	}
	
	register_sidebar_widget('User Profile', 'widget_user_profile');
	register_widget_control('User Profile', 'widget_user_profile_control');
}

?>
