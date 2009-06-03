<?php

/**
 * Widget class for displaying the profile of a user.
 */
class Extended_Profile_Widget extends WP_Widget {

	function Extended_Profile_Widget() {
		$widget_ops = array('classname' => 'widget_extended_profile', 'description' => __( 'User hCard Profile') );
		$this->WP_Widget('extended-profile', __('User Profile'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		extended_profile($instance['user_id']);

		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'user_id' => 0 ) );
?>
        <p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php 
				echo $this->get_field_name('title'); ?>" type="text" value="<?php esc_attr_e($instance['title']); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('user_id'); ?>"><?php _e('User:'); ?></label>
			<select name="<?php echo $this->get_field_name('user_id'); ?>" id="<?php echo $this->get_field_id('user_id'); ?>" class="widefat">
				<option value="-1"<?php selected( $instance['user_id'], -1 ) ?>><?php _e('Select a User'); ?></option>
<?php
			$users = get_users_of_blog();
			foreach ( $users as $user ) {
				echo '
				<option value="' . $user->ID . '"' . selected( $instance['user_id'], $user->ID ) . '>' . esc_html($user->display_name) . '</option>';
			}
?>
			</select>
		</p>
<?php
    }    

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args((array) $new_instance, array( 'title' => '', 'user_id' => 0 ));

        $instance['title'] = strip_tags($new_instance['title']);
        $instance['user_id'] = $new_instance['user_id'];

        return $instance;
    }    

}

function extended_profile_widgets() {
	register_widget('Extended_Profile_Widget');
}
add_action('widgets_init', 'extended_profile_widgets');
