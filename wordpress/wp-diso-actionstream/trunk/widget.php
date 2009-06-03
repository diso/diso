<?php

/**
 * Widget class for displaying the activity stream of a user.
 */
class Activity_Stream_Widget extends WP_Widget {
	function Activity_Stream_Widget() {
		$widget_ops = array('classname' => 'widget_activity_stream', 'description' => __( 'Your Activity Stream') );
		$this->WP_Widget('activity-stream', __('Activity Stream'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		echo actionstream_render($instance['user_id'], $instance['num'], $instance['hide_user']);

		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'user_id' => 0, 'num' => 10, 'hide_user' => 0 ) );
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
        <p>
			<label for="<?php echo $this->get_field_id('num'); ?>"><?php _e('Max Items:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('num'); ?>" name="<?php 
				echo $this->get_field_name('num'); ?>" type="text" value="<?php esc_attr_e($instance['num']); ?>" />
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked($instance['hide_user'], true) ?> id="<?php 
				echo $this->get_field_id('hide_user'); ?>" name="<?php echo $this->get_field_name('hide_user'); ?>" />   
        	<label for="<?php echo $this->get_field_id('hide_user'); ?>"><?php _e('Hide Usernames'); ?></label>
		</p>
<?php
    }    

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args((array) $new_instance, array( 'title' => '', 'user_id' => 0, 'num' => 10, 'hide_user' => 0));

        $instance['title'] = strip_tags($new_instance['title']);
        $instance['user_id'] = $new_instance['user_id'];
        $instance['num'] = $new_instance['num'];
        $instance['hide_user'] = $new_instance['hide_user'] ? 1 : 0;

        return $instance;
    }    
}


/**
 * Widget class for displaying a list of activity stream services of a user.
 */
class Activity_Services_Widget extends WP_Widget {
	function Activity_Services_Widget() {
		$widget_ops = array('classname' => 'widget_activity_services', 'description' => __( 'Your Activity Services') );
		$this->WP_Widget('activity-services', __('Activity Services'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		echo actionstream_services($instance['user_id']);

		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'user_id' => 0, 'num' => 10, 'hide_user' => 0 ) );
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


function activity_stream_widgets() {
	register_widget('Activity_Stream_Widget');
	register_widget('Activity_Services_Widget');
}
add_action('widgets_init', 'activity_stream_widgets');
