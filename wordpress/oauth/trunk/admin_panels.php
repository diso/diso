<?php

add_action('admin_menu', 'oauth_admin_menu');
function oauth_admin_menu() {
	$hookname = add_options_page('OAuth', 'OAuth', 8, 'oauth', 'oauth_options_page');

	$hookname = add_users_page(__('Your Services', 'oauth'), __('Your Services', 'oauth'),
		'read', 'oauth_services', 'oauth_profile_panel' );

}

function oauth_options_page() {
	global $action;

	$user = wp_get_current_user();
	$store = oauth_store();

	switch($action) {
		case 'new_consumer':
			$data = array(
				'requester_name' => 'Test Consumer',
				'requester_email' => get_option('admin_email'),
			);
			$store->updateConsumer($data, $user->ID, true);
			break;

		case 'delete_consumers':
			check_admin_referer('oauth_delete_consumers');
			$delete = $_POST['delete'];
			$consumers = get_option('oauth_consumers');

			$count = 0;
			if (is_array($consumers) && !empty($consumers)) {
				foreach ($consumers as $key => $consumer) {
					if (in_array(md5($key), $delete)) {
						$store->deleteConsumer($key, $user->ID);
						$count++;
					}
				}
			}

			if ($count) {
				$message = sprintf('Deleted %1$s OAuth %2$s', $count, ($count == 1 ? 'Consumer' : 'Consumers'));
			}
			break;

		case 'delete_consumer_tokens':
			check_admin_referer('oauth_delete_consumer_tokens');
			$delete = $_POST['delete'];
			$tokens = get_option('oauth_consumer_tokens');

			$count = 0;
			if (is_array($tokens) && !empty($tokens)) {
				foreach ($tokens as $key => $token) {
					if (in_array(md5($key), $delete)) {
						$store->deleteConsumerRequestToken($key);
						$count++;
					}
				}
			}

			if ($count) {
				$message = sprintf('Deleted %1$s Consumer %2$s', $count, ($count == 1 ? 'Token' : 'Tokens'));
			}

			break;

		case 'delete_servers':
			check_admin_referer('oauth_delete_servers');
			$delete = $_POST['delete'];
			$servers = get_option('oauth_servers');

			$count = 0;
			if (is_array($servers) && !empty($servers)) {
				foreach ($servers as $key => $server) {
					if (in_array(md5($key), $delete)) {
						$store->deleteServer($key, $user->ID);
						$count++;
					}
				}
			}

			if ($count) {
				$message = sprintf('Deleted %1$s OAuth %2$s', $count, ($count == 1 ? 'Server' : 'Servers'));
			}
			break;

		case 'delete_server_tokens':
			check_admin_referer('oauth_delete_server_tokens');
			$delete = $_POST['delete'];
			$tokens = get_option('oauth_server_tokens');

			$count = 0;
			if (is_array($tokens) && !empty($tokens)) {
				foreach ($tokens as $key => $token) {
					if (in_array(md5($key), $delete)) {
						$store->deleteServerToken($token['consumer_key'], $key, $user->ID);
						$count++;
					}
				}
			}

			if ($count) {
				$message = sprintf('Deleted %1$s Server %2$s', $count, ($count == 1 ? 'Token' : 'Tokens'));
			}
			break;

	}

	if (false) { // register consumer
		$data = array(
			'requester_name' => 'Default Consumer',
			'requester_email' => 'will@willnorris.com',
		);
		$store->updateConsumer($data, $user->ID, true);
	}

?>
	<?php screen_icon('oauth'); ?>

	<style type="text/css"> #icon-oauth { background-image: url("<?php echo plugins_url('oauth/icon.png'); ?>"); } </style>

	<?php if (!empty($message)) {
		echo '<div id="message" class="updated fade"><p><strong>' . $message . '</strong></p></div>';
	}?>

	<div class="wrap">
		<h2><?php _e('OAuth', 'oauth') ?></h2>

		<h3><?php _e('OAuth Consumers', 'oauth') ?></h3>
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">
			<div class="tablenav">
				<div class="alignleft actions">
					<select name="action">
						<option value="" selected="selected">Bulk Actions</option>
						<option value="delete_consumers">Delete</option>
					</select>
					<input type="submit" value="Apply" name="doaction" id="doaction" class="button-secondary action" />
					<?php wp_nonce_field('oauth_delete_consumers'); ?>
				</div>
				<br class="clear" />
			</div>

			<div class="clear"></div>

			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
						<th scope="col"><?php _e('Requester', 'oauth'); ?></th>
						<th scope="col"><?php _e('Consumer Key', 'oauth'); ?></th>
						<th scope="col"><?php _e('Consumer Secret', 'oauth'); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
					$consumers = get_option('oauth_consumers');

					if (!is_array($consumers) || empty($consumers)) {
						echo '<tr><td colspan="2">'.__('No OAuth Consumers.', 'oauth').'</td></tr>';
					} else {
						foreach ($consumers as $consumer) {
							echo '
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($consumer['consumer_key']).'" /></th>
								<td>' . sprintf('%1$s <br /> <a href="mailto:%2$s">%2$s</a>', $consumer['requester_name'], $consumer['requester_email']) . '</td>
								<td>' . $consumer['consumer_key'] . '</td>
								<td>' . $consumer['consumer_secret'] . '</td>
							</tr>';
						}   
					}
				?>
				</tbody>
			</table>
		</form>
		<p><a href="<?php echo add_query_arg('action', 'new_consumer') ?>">Create New OAuth Consumer</a></p>

		<br />

		<h3><?php _e('OAuth Consumer Tokens', 'oauth') ?></h3>
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">
			<div class="tablenav">
				<div class="alignleft actions">
					<select name="action">
						<option value="" selected="selected">Bulk Actions</option>
						<option value="delete_consumer_tokens">Delete</option>
					</select>
					<input type="submit" value="Apply" name="doaction" id="doaction" class="button-secondary action" />
					<?php wp_nonce_field('oauth_delete_consumer_tokens'); ?>
				</div>
				<br class="clear" />
			</div>

			<div class="clear"></div>

			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
						<th scope="col"><?php _e('Consumer Key', 'oauth'); ?></th>
						<th scope="col"><?php _e('Token', 'oauth'); ?></th>
						<th scope="col"><?php _e('Secret', 'oauth'); ?></th>
						<th scope="col"><?php _e('Type', 'oauth'); ?></th>
						<th scope="col"><?php _e('User', 'oauth'); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
					$tokens = get_option('oauth_consumer_tokens');

					if (!is_array($tokens) || empty($tokens)) {
						echo '<tr><td colspan="2">'.__('No OAuth Consumer Tokens.', 'oauth').'</td></tr>';
					} else {
						foreach ($tokens as $token) {
							$u = get_userdata($token['user']);

							echo '
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($token['token']).'" /></th>
								<td>' . $token['consumer_key'] . '</td>
								<td>' . $token['token'] . '</td>
								<td>' . $token['token_secret'] . '</td>
								<td>' . $token['type'] . (($token['type'] == 'request' && $token['authorized']) ? '<br />(authorized)' : '') . '</td>
								<td>' . ($u ? $u->user_login : ' - ') . '</td>
							</tr>';
						}   
					}
				?>
				</tbody>
			</table>
		</form>

		<br /><hr />

		<h3><?php _e('OAuth Servers', 'oauth') ?></h3>
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">
			<div class="tablenav">
				<div class="alignleft actions">
					<select name="action">
						<option value="" selected="selected">Bulk Actions</option>
						<option value="delete_servers">Delete</option>
					</select>
					<input type="submit" value="Apply" name="doaction" id="doaction" class="button-secondary action" />
					<?php wp_nonce_field('oauth_delete_servers'); ?>
				</div>
				<br class="clear" />
			</div>

			<div class="clear"></div>

			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
						<th scope="col"><?php _e('Server URL', 'oauth'); ?></th>
						<th scope="col"><?php _e('Consumer Key &amp; Secret', 'oauth'); ?></th>
						<th scope="col"><?php _e('OAuth Enpoints', 'oauth'); ?></th>
						<th scope="col"><?php _e('Signature Methods', 'oauth'); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
					$servers = get_option('oauth_servers');

					if (!is_array($servers) || empty($servers)) {
						echo '<tr><td colspan="2">'.__('No OAuth Servers.', 'oauth').'</td></tr>';
					} else {
						foreach ($servers as $server) {
							echo '
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($server['consumer_key']).'" /></th>
								<td>' . $server['server_uri'] . '</td>
								<td>' . sprintf('<strong>Key:</strong> %1$s <br /> <strong>Secret:</strong> %2$s', $server['consumer_key'], $server['consumer_secret']) . '</td>
								<td>' . sprintf('<strong>Request:</strong> %1$s <br /> <strong>Authorize:</strong> %2$s <br /> <strong>Access:</strong> %3$s ', 
									$server['request_token_uri'], $server['authorize_uri'], $server['access_token_uri']) . '</td>
								<td>' . join('<br />', $server['signature_methods']) . '</td>
							</tr>';
						}   
					}
				?>
				</tbody>
			</table>
		</form>

		<br />

		<h3><?php _e('OAuth Server Tokens', 'oauth') ?></h3>
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">
			<div class="tablenav">
				<div class="alignleft actions">
					<select name="action">
						<option value="" selected="selected">Bulk Actions</option>
						<option value="delete_server_tokens">Delete</option>
					</select>
					<input type="submit" value="Apply" name="doaction" id="doaction" class="button-secondary action" />
					<?php wp_nonce_field('oauth_delete_server_tokens'); ?>
				</div>
				<br class="clear" />
			</div>

			<div class="clear"></div>

			<table class="widefat">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
						<th scope="col"><?php _e('Consumer Key', 'oauth'); ?></th>
						<th scope="col"><?php _e('Token', 'oauth'); ?></th>
						<th scope="col"><?php _e('Secret', 'oauth'); ?></th>
						<th scope="col"><?php _e('Type', 'oauth'); ?></th>
						<th scope="col"><?php _e('User', 'oauth'); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
					$tokens = get_option('oauth_server_tokens');

					if (!is_array($tokens) || empty($tokens)) {
						echo '<tr><td colspan="2">'.__('No OAuth Server Tokens.', 'oauth').'</td></tr>';
					} else {
						foreach ($tokens as $token) {
							$u = get_userdata($token['user']);

							echo '
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($token['token']).'" /></th>
								<td>' . $token['consumer_key'] . '</td>
								<td>' . $token['token'] . '</td>
								<td>' . $token['token_secret'] . '</td>
								<td>' . $token['type'] . '</td>
								<td>' . ($u ? $u->user_login : ' - ') . '</td>
							</tr>';
						}   
					}
				?>
				</tbody>
			</table>
		</form>

	</div>
<?php

}

/**
 * Handle OAuth user profile page.
 */
function oauth_profile_panel() {
	$user = wp_get_current_user();

	if (@$_POST['action'] == 'delete') {
		$tokens = oauth_get_user_access_tokens($user->ID);
		$count = 0;
		foreach ($_POST['delete'] as $token_hash) {
			foreach ($tokens as $token) {
				if (md5($token['token']) == $token_hash) {
					oauth_delete_token($token['token'], $user->ID);
					$count++;
				}
			}
		}
		if ($count) {
			echo '<div class="updated"><p>'.__('Deleted '.$count.' service' . ($count>1 ? 's' : '') . '.').'</p></div>';
		}

	}

 	if (function_exists('screen_icon')) {
		screen_icon('oauth');

		echo '
		<style type="text/css"> #icon-oauth { background-image: url("' . plugins_url('oauth/icon.png') . '"); } </style>';
	}

?>
	<div class="wrap">
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">

			<h2><?php _e('Your Services', 'oauth'); ?></h2>

		<div class="tablenav">
			<div class="alignleft">
				<input type="submit" value="<?php _e('Delete'); ?>" name="deleteit" class="button-secondary delete" />
				<input type="hidden" name="action" value="delete" />
				<?php wp_nonce_field('oauth-delete_services'); ?>
			</div>
		</div>

		<br class="clear" />

		<table class="widefat">
			<thead>
				<tr>
					<th scope="col" class="check-column"><input type="checkbox" /></th>
					<th scope="col"><?php _e('Service', 'oauth'); ?></th>
				</tr>
			</thead>
			<tbody>

			<?php
				$tokens = oauth_get_user_access_tokens($user->ID);

				if (empty($tokens)) {
					echo '<tr><td colspan="2">'.__('No Services.', 'oauth').'</td></tr>';
				} else {
					foreach ($tokens as $token) {
						echo '
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($token['token']).'" /></th>
							<td>'.($token['token']).'</td>
						</tr>';
					}   
				}   
			?>  

			</tbody>
			</table>
		</form>
	</div>
<?php
}


function oauth_authorize_token() {
	$server = oauth_server();
	$token = $server->getParam('oauth_token', true);

	ob_start();
?>

	<form method="get">
		<h1><?php _e('An application would like to connect to your account'); ?></h1>
		<p><?php printf(__('Would you like to connect <strong>%s</strong> to your account? ' 
			. 'Connecting this application will allow it to read, modify, and delete WordPress content.'), 'foo'); ?></p>

		<label for="authorize_yes"><input type="radio" id="authorize_yes" name="authorize" value="1" /> Yes</label><br />
		<label for="authorize_no"><input type="radio" id="authorize_no" name="authorize" value="" /> No</label><br />
		
		<?php wp_nonce_field('oauth_authorize_token'); ?>
		<input type="hidden" name="oauth_token" value="<?php echo $token ?>" />

		<?php foreach ($_REQUEST as $key => $value) {
			if (stripos($key, 'oauth_') !== 0) {
				echo '<input type="hidden" name="' . htmlentities($key) . '" value="' . htmlentities($value) . '" />' . "\n";
			}
		} ?>

		<p class="submit">
			<input type="submit" name="submit" value="Continue" />
		</p>
	</form>

<?php

	$html = ob_get_contents();
	ob_end_clean();
	wp_die($html);
}

?>
