<?php

/**
 * Storage container for the oauth credentials, both server and consumer side.
 * This implementation uses the WordPress options table.
 */

class OAuthStoreWordPress
{

	/**
	 * Constructor.
	 */
	function __construct () { }


	/**
	 * Find stored credentials for the consumer key and token. Used by an OAuth server
	 * when verifying an OAuth request.
	 * 
	 * TODO: also check the status of the consumer key
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param string token_type		false, 'request' or 'access'
	 * @exception OAuthException when no secrets where found
	 * @return array	assoc (consumer_secret, token_secret, osr_id, ost_id, user_id)
	 */
	public function getSecretsForVerify ( $consumer_key, $token_key, $token_type = 'access' ) { 
		$consumers = get_option('oauth_consumers');
		if (array_key_exists($consumer_key, $consumers)) {
			$consumer = $consumers[$consumer_key];
		}

		if ($token_type !== false) {
			$tokens = get_option('oauth_consumer_tokens');
			if (array_key_exists($token_key, $tokens)) {
				$token = $tokens[$token_key];
			}
		}

		$secrets = array(
			'consumer_key' => false,
			'consumer_secret' => false,
			'token' => false,
			'token_secret' => false,
			'user_id' => false,
		);

		if (@$consumer) { // TODO check $consumer['enabled']
			$secrets['consumer_key'] = $consumer['key'];
			$secrets['consumer_secret'] = $consumer['secret'];
		}

		if (@$token) { // TODO check $token['type']
			$secrets['token'] = $token['token'];
			$secrets['token_secret'] = $token['secret'];
			$secrets['user_id'] = $token['user'];
		}

		return $secrets;
	}


	/**
	 * Find the server details for signing a request, always looks for an access token.
	 * The returned credentials depend on which local user is making the request.
	 * 
	 * For signing we need all of the following:
	 * 
	 * consumer_key			consumer key associated with the server
	 * consumer_secret		consumer secret associated with this server
	 * token				access token associated with this server
	 * token_secret			secret for the access token
	 * signature_methods	signing methods supported by the server (array)
	 * 
	 * @todo filter on token type (we should know how and with what to sign this request, and there might be old access tokens)
	 * @param string uri	uri of the server
	 * @param int user_id	id of the logged on user
	 * @exception OAuthException when no credentials found
	 * @return array
	 */
	public function getSecretsForSignature ( $uri, $user_id ) { 
		$secrets = array();

		// Find a consumer key and token for the given uri
		$ps		= parse_url($uri);
		$host	= isset($ps['host']) ? strtolower($ps['host']) : 'localhost';
		$path	= isset($ps['path']) ? $ps['path'] : '';
		$path = trailingslashit($path);

		$tokens = get_option('oauth_server_tokens');
		$servers = get_option('oauth_servers');

		foreach ($tokens as $key => $token) {
			$server = $servers[$token['consumer_key']];
			if ($token['type'] != 'access') continue;
			if ($token['user'] != $user_id) continue;
			//if ($server['user'] != $user_id) continue;
			if ($server['server_uri_host'] != $host) continue;
			if (strpos($path, $server['server_uri_path']) !== 0) continue;

			$secrets = array_merge($server, $token);
			$secrets['token_secret'] = $secrets['secret'];
		}

		return $secrets;
	}


	/**
	 * Get the token and token secret we obtained from a server.
	 * 
	 * @param string	consumer_key
	 * @param string 	token
	 * @param string	token_type
	 * @param int		user_id			the user requesting the token, 0 for public secrets
	 * @exception OAuthException when no credentials found
	 * @return array
	 */
	public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $user_id = 0 ) { 
		if ($token_type != 'request' && $token_type != 'access') {
			throw new OAuthException('Unkown token type "'.$token_type.'", must be either "request" or "access"');
		}

		$secrets = array();
		$tokens = get_option('oauth_server_tokens');
		$servers = get_option('oauth_servers');

		$server_token = $tokens[$token];
		$server = $servers[$consumer_key];

		if (!$server_token || !$server) return $secrets;
		if ($server_token['consumer_key'] != $consumer_key) return $secrets;
		if ($server_token['type'] != $token_type) return $secrets;
		if ($user_id && $server_token['user'] != $user_id) return $secrets;

		$secrets = array_merge($server, $server_token);
		$secrets['token_secret'] = $secrets['secret'];

		return $secrets;
	}


	/**
	 * Add a request token we obtained from a server.
	 * 
	 * @todo remove old tokens for this user and this ocr_id
	 * @param string consumer_key	key of the server in the consumer registry
	 * @param string token_type		one of 'request' or 'access'
	 * @param string token
	 * @param string token_secret
	 * @param int 	 user_id			the user this token owns
	 * @exception OAuthException when server is not known
	 * @exception OAuthException when we received a duplicate token
	 */
	public function addServerToken ( $consumer_key, $token_type, $token, $token_secret, $user_id ) { 
		if ($token_type != 'request' && $token_type != 'access') {
			throw new OAuthException('Unkwown token type "'.$token_type.'", must be either "request" or "access"');
		}

		$servers = get_option('oauth_servers');
		if (!array_key_exists($consumer_key, $servers)) {
			throw new OAuthException('No server associated with consumer_key "'.$consumer_key.'"');
		}

		$server_token = array(
			'consumer_key' => $consumer_key,
			'token' => $token,
			'secret' => $token_secret,
			'type' => $token_type,
			'user' => $user_id,
		);

		$tokens = get_option('oauth_server_tokens');
		$tokens[$token] = $server_token;
		update_option('oauth_server_tokens', $tokens);
	}


	/**
	 * Delete a server key.  This removes access to that site.
	 * 
	 * @param string consumer_key
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 */
	public function deleteServer ( $key, $user_id, $user_is_admin = false ) { 
		$servers = get_option('oauth_servers');
		if (array_key_exists($key, $servers)) {
			unset($servers[$key]);
			update_option('oauth_servers', $servers);
		}
	}


	/**
	 * Get a server from the server registry using the consumer key
	 * 
	 * @param string consumer_key
	 * @exception OAuthException when server is not found
	 * @return array
	 */	
	public function getServer( $key ) { 
		$servers = get_option('oauth_servers');
		if (array_key_exists($key, $servers)) {
			return $servers[$key];
		}
		
		throw new OAuthException('No server with consumer_key "'.$key.'"');
	}


	/**
	 * Get a list of all server token this user has access to.
	 * 
	 * @param int user_id
	 * @return array
	 */
	public function listServerTokens( $user_id) {
		$consumers = get_option('oauth_consumers');
		$tokens = get_option('oauth_consumer_tokens');
		$user_tokens = array();

		foreach ($tokens as $token) {
			if ($token['type'] == 'access') {
				if ($token['user'] == $user_id) {
					$user_tokens[] = array_merge($consumers[$token['consumer_key']], $token);
				}
			}
		}

		return $user_tokens;
	}


	/**
	 * Count how many tokens we have for the given server
	 * 
	 * @param string consumer_key
	 * @return int
	 */
	public function countServerTokens ( $consumer_key ) { 
		$count = 0;

		$tokens = get_option('oauth_server_tokens');
		foreach ($tokens as $token) {
			if ($token['consumer_key'] == $consumer_key) $count++;
		}

		return $count;
	}


	/**
	 * Get a specific server token for the given user
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param int user_id
	 * @exception OAuthException when no such token found
	 * @return array
	 */
	public function getServerToken ( $consumer_key, $token, $user_id ) { 
		$tokens = get_option('oauth_server_tokens');
		if (is_array($tokens) && array_key_exists($token, $tokens)) {
			return $tokens[$token];
		}
	}


	/**
	 * Delete a token we obtained from a server.
	 * 
	 * @param string consumer_key
	 * @param string token
	 * @param int user_id
	 * @param boolean no_user_check
	 */
	public function deleteServerToken ( $consumer_key, $token, $user_id, $no_user_check = false ) { 
		$tokens = get_option('oauth_server_tokens');
		if (array_key_exists($token, $tokens)) {
			unset($tokens[$token]);
			update_option('oauth_server_tokens', $tokens);
		}
	}


	/**
	 * Get a list of all consumers from the consumer registry
	 * 
	 * @param string q	query term
	 * @param int user_id
	 * @return array
	 */	
	public function listServers ( $q = '', $user_id ) { }


	/**
	 * Insert/update a new server for our site (we will be the consumer)
	 * 
	 * (This is the registry at the consumers, registering servers ;-) )
	 * 
	 * @param array server
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 * @exception OAuthException when fields are missing or on duplicate consumer_key
	 * @return string consumer key
	 */
	public function updateServer( $server, $user_id, $user_is_admin = false ) {
		foreach (array('consumer_key', 'consumer_secret', 'server_uri') as $f) {
			if (empty($server[$f])) {
				throw new OAuthException('The field "'.$f.'" must be set and non empty');
			}
		}

		$key = $server['consumer_key'];
		$parts = parse_url($server['server_uri']);
		$server['server_uri_host']  = (isset($parts['host']) ? strtolower($parts['host']) : 'localhost');
		$server['server_uri_path']  = (isset($parts['path']) ? $parts['path'] : '/');

		$servers = get_option('oauth_servers');

		// TODO Check if the current user can update this server definition
		// throw new OAuthException('The user "'.$user_id.'" is not allowed to update this server');
		
		if (array_key_exists($key, $servers)) {
			$old_server = $servers[$key];
			$server = array_merge($old_server, $server);
		}

		$servers[$key] = $server;
		update_option('oauth_servers', $servers);

		return $key;
	}


	/**
	 * Insert/update a new consumer with this server (we will be the server)
	 * When this is a new consumer, then also generate the consumer key and secret.
	 * Never updates the consumer key and secret.
	 * When the id is set, then the key and secret must correspond to the entry
	 * being updated.
	 * 
	 * (This is the registry at the server, registering consumers ;-) )
	 * 
	 * @param array consumer
	 * @param int user_id	user registering this consumer
	 * @param boolean user_is_admin
	 * @return string consumer key
	 */
	public function updateConsumer( $consumer, $user_id, $user_is_admin = false )
	{
		foreach (array('requester_name', 'requester_email') as $f) {
			if (empty($consumer[$f])) {
				throw new OAuthException('The field "'.$f.'" must be set and non empty');
			}
		}

		$consumers = get_option('oauth_consumers');

		if (!empty($consumer['key'])) {
			$key = $consumer['key'];

			// TODO Check if the current user can update this server definition
			// throw new OAuthException('The user "'.$user_id.'" is not allowed to update this consumer');
			
			if (empty($consumer['secret'])) {
				throw new OAuthException('The field "secret" must be set and non empty');
			}

			if (array_key_exists($key, $consumers)) {
				$old_consumer = $consumers[$key];
				if ($consumer['secret'] != $old_consumer['secret']) {
					throw new OAuthException('The consumer key and secret do not match');
				}
				$consumer = array_merge($old_consumer, $consumer);
			}
		} else {
			$consumer['key'] = $this->generateKey(true);
			$consumer['secret'] = $this->generateKey();

			$key = $consumer['key'];
		}

		$consumers[$key] = $consumer;
		update_option('oauth_consumers', $consumers);

		return $key;
	}


	/**
	 * Delete a consumer key.  This removes access to our site for all applications using this key.
	 * 
	 * @param string consumer_key
	 * @param int user_id	user registering this server
	 * @param boolean user_is_admin
	 */
	public function deleteConsumer( $key, $user_id, $user_is_admin = false ) {
		$consumers = get_option('oauth_consumers');
		if (array_key_exists($key, $consumers)) {
			unset($consumers[$key]);
			update_option('oauth_consumers', $consumers);
		}
	}	


	/**
	 * Fetch a consumer of this server, by consumer_key.
	 * 
	 * @param string key
	 * @exception OAuthException when consumer not found
	 * @return array
	 */
	public function getConsumer( $key ) {
		$consumers = get_option('oauth_consumers');
		if (array_key_exists($key, $consumers)) {
			return $consumers[$key];
		}
		
		throw new OAuthException('No consumer with consumer_key "'.$key.'"');
	}


	/**
	 * Add an unautorized request token to our server.
	 * 
	 * @param string consumer_key
	 * @return array (token, token_secret)
	 */
	public function addConsumerRequestToken( $consumer_key ) { 
		$token = array();

		$token['token']  = $this->generateKey(true);
		$token['secret'] = $this->generateKey();
		$token['consumer_key'] = $consumer_key;
		$token['type'] = 'request';

		$tokens = get_option('oauth_consumer_tokens');
		$tokens[$token['token']] = $token;
		update_option('oauth_consumer_tokens', $tokens);
	
		return array('token'=>$token['token'], 'token_secret'=>$token['secret']);
	}


	/**
	 * Fetch the consumer request token, by request token.
	 * 
	 * @param string token
	 * @return array  token and consumer details
	 */
	public function getConsumerRequestToken( $token ) { 
		$tokens = get_option('oauth_consumer_tokens');
		if (array_key_exists($token, $tokens)) {
			return $tokens[$token];
		}
	}


	/**
	 * Delete a consumer token.  The token must be a request or authorized token.
	 * 
	 * @param string token
	 */
	public function deleteConsumerRequestToken( $token ) {
		$tokens = get_option('oauth_consumer_tokens');
		if (array_key_exists($token, $tokens)) {
			unset($tokens[$token]);
			update_option('oauth_consumer_tokens', $tokens);
		}
	}


	/**
	 * Upgrade a request token to be an authorized request token.
	 * 
	 * @param string token
	 * @param int	 user_id  user authorizing the token
	 */
	public function authorizeConsumerRequestToken( $token, $user_id ) { 
		$tokens = get_option('oauth_consumer_tokens');
		if (array_key_exists($token, $tokens)) {
			$tokens[$token]['user'] = $user_id;
			$tokens[$token]['authorized'] = true;
			update_option('oauth_consumer_tokens', $tokens);
		}
	}


	/**
	 * Count the consumer access tokens for the given consumer.
	 * 
	 * @param string consumer_key
	 * @return int
	 */
	public function countConsumerAccessTokens ( $consumer_key ) { 
		$count = 0;

		$tokens = get_option('oauth_consumer_tokens');
		foreach ($tokens as $token) {
			if ($token['consumer_key'] == $consumer_key && $token['type'] == 'access') $count++;
		}

		return $count;
	}


	/**
	 * Exchange an authorized request token for new access token.
	 * 
	 * @param string token
	 * @param int	 user_id  user authorizing the token
	 * @exception OAuthException when token could not be exchanged
	 * @return array (token, token_secret)
	 */
	public function exchangeConsumerRequestForAccessToken( $token ) { 
		$tokens = get_option('oauth_consumer_tokens');

		if (array_key_exists($token, $tokens)) {
			$new_token = $tokens[$token];

			if (!$new_token['authorized']) {
				throw new OAuthException('Can\'t exchange request token "'.$token.'" for access token. Token not authorized');
			}

			$new_token['token']  = $this->generateKey(true);
			$new_token['secret'] = $this->generateKey();
			$new_token['type'] = 'access';

			$tokens[$new_token['token']] = $new_token;
			unset($tokens[$token]);

			update_option('oauth_consumer_tokens', $tokens);
			return array('token' => $new_token['token'], 'token_secret' => $new_token['secret']);
		} else {
			throw new OAuthException('Can\'t exchange request token "'.$token.'" for access token. No such token.');
		}
	}


	/**
	 * Fetch the consumer access token, by access token.
	 * 
	 * @param string token
	 * @param int user_id
	 * @exception OAuthException when token is not found
	 * @return array  token and consumer details
	 */
	public function getConsumerAccessToken ( $token, $user_id ) { 
		$tokens = get_option('oauth_consumer_tokens');
		if (array_key_exists($token, $tokens)) {
			return $tokens[$token];
		}
	}


	/**
	 * Delete a consumer access token.
	 * 
	 * @param string token
	 * @param int user_id
	 */
	public function deleteConsumerAccessToken( $token, $user_id ) {
		$tokens = get_option('oauth_consumer_tokens');
		if (array_key_exists($token, $tokens)) {
			unset($tokens[$token]);
			update_option('oauth_consumer_tokens', $tokens);
		}
	}


	/**
	 * Fetch a list of all consumers
	 * 
	 * @param int user_id
	 * @return array
	 */
	public function listConsumers ( $user_id ) { }


	/**
	 * Fetch a list of all consumer tokens accessing the account of the given user.
	 * 
	 * @param int user_id
	 * @return array
	 */
	public function listConsumerTokens ( $user_id ) { }


	/**
	 * Check an nonce/timestamp combination.  Clears any nonce combinations
	 * that are older than the one received.
	 * 
	 * @param string	consumer_key
	 * @param string 	token
	 * @param int		timestamp
	 * @param string 	nonce
	 * @exception OAuthException	thrown when the nonce is not in sequence
	 */
	public function checkServerNonce ( $consumer_key, $token, $timestamp, $nonce ) { }


	/**
	 * Generate a unique key
	 * 
	 * @param boolean unique	force the key to be unique
	 * @return string
	 */
	public function generateKey ( $unique = false )
	{
		$key = md5(uniqid(rand(), true));
		if ($unique)
		{
			list($usec,$sec) = explode(' ',microtime());
			$key .= dechex($usec).dechex($sec);
		}
		return $key;
	}


	/**
	 * Add an entry to the log table
	 * 
	 * @param array keys (osr_consumer_key, ost_token, ocr_consumer_key, oct_token)
	 * @param string received
	 * @param string sent
	 * @param string base_string
	 * @param string notes
	 * @param int (optional) user_id
	 */
	public function addLog ( $keys, $received, $sent, $base_string, $notes, $user_id = null ) { }

	
	/**
	 * Get a page of entries from the log.  Returns the last 100 records
	 * matching the options given.
	 * 
	 * @param array options
	 * @param int user_id	current user
	 * @return array log records
	 */
	public function listLog ( $options, $user_id ) { }


}


/* vi:set ts=4 sts=4 sw=4 binary noeol: */

?>