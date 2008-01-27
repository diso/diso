<?php
/*
Plugin Name: XMPP Messaging
Plugin URI: http://diso.googlecode.com/
Description:  Provide XMPP services API within WordPress
Version: 0.1
Author: Steve Ivy
Author URI: http://diso-project.org/
*/

#$this->_call_handler("authenticated");
#$this->_call_handler("authfailure",-1,"No authentication method available","");
#this->_call_handler("deregistered",$this->jid);
#$this->_call_handler("deregfailure",-2,"Unrecognized response from server");
#$this->_call_handler("error",$code,$msg,$xmlns,$packet);
#$this->_call_handler("heartbeat");
#$this->_call_handler("message_chat",$from,$to,$body,$subject,$thread,$id,$extended,$packet);
#$this->_call_handler("message_groupchat",$packet);
#$this->_call_handler("message_headline",$from,$to,$body,$subject,$extended,$packet);
#$this->_call_handler("message_normal",$from,$to,$body,$subject,$thread,$id,$extended,$packet);
#$this->_call_handler("msgevent_composing_start",$from);
#$this->_call_handler("msgevent_composing_stop",$from);
#$this->_call_handler("msgevent_delivered",$from);
#$this->_call_handler("msgevent_displayed",$from);
#$this->_call_handler("msgevent_offline",$from);
#$this->_call_handler("passwordchanged");
#$this->_call_handler("passwordfailure",-2,"Unrecognized response from server");
#$this->_call_handler("regfailure",-1,"Username already registered","");
#$this->_call_handler("registered",$this->jid);
#$this->_call_handler("rosteradded");
#$this->_call_handler("rosteraddfailure",-2,"Unrecognized response from server");
#$this->_call_handler("rosterremoved");
#$this->_call_handler("rosterremovefailure",-2,"Unrecognized response from server");
#$this->_call_handler("rosterupdate",$jid,$is_new);
#$this->_call_handler("servicefields",&$fields,$packet_id,$reg_key,$reg_instructions,&$reg_x);
#$this->_call_handler("servicefieldsfailure",-2,"Unrecognized response from server");
#$this->_call_handler("serviceregfailure",-2,"Unrecognized response from server");
#$this->_call_handler("serviceregistered",$jid);
#$this->_call_handler('servicederegfailure",-2,"Unrecognized response from server");
#$this->_call_handler('servicederegistered");
#$this->_call_handler("serviceupdate",$jid,$is_new);
#$this->_call_handler("terminated");
#$this->_call_handler('connected');
#$this->_call_handler('disconnected'); // called when the connection to the Jabber server is lost unexpectedly
#$this->_call_handler('probe',$packet);
#$this->_call_handler('stream_error',$packet);
#$this->_call_handler('subscribe',$packet);
#$this->_call_handler('subscribed',$packet);
#$this->_call_handler('unsubscribe',$packet);
#$this->_call_handler('unsubscribed',$packet);
#$this->_call_handler("privatedata",$packetid,$namespace,$values);
#$this->_call_handler('debug_log',$msg);
#$this->_call_handler("contactupdated",$packetid);
#$this->_call_handler("contactupdatefailure",-2,"Unrecognized response from server");

/**
 * XMPP_Messaging is a wrapper for XMPP_Jabber_Client that does initialization and setup
 */

function debug ($msg) {
	print "$msg\n";
}

define ('XMPP_CLIENT_ID', 'XMPP_CLIENT_ID');

/**
 * XMPP_Client is a jabber client for wordpress. 
 * It exposes XMPP events as Wordpress action hooks. Plugins can add handlers 
 * for these xmpp_* hooks and provide functionality based on them.
 *
 * XMPP_Client also provides methods for initiating XMPP communications.
 */
class XMPP_Jabber_Client {
	/**
	 * constructor
	 */
	function XMPP_Jabber_Client($identifier, &$jab){
		//debug ('passed in id -> ' . $identifier);
		$this->jab = &$jab;
		$this->first_roster_update = true;
		$this->countdown = 0;
		$this->connected=false;
		$this->identifier=$identifier;
	}
		
	############### FUNCTIONS ################
	# XMPP functionality
	# Wraps the Jabber functionality for convenience
	#
	# Session Management
	#
	
	function connect($server, $port=5222, $timeout=null, $alternate_ip=false) {
		$this->connected = $this->jab->connect($server, $port, $timeout, $alternate_ip);
		return $this->connected;
	}
	
	function run($callback_frequency, $runtime) {
		return $this->jab->execute($callback_frequency, $runtime);
	}
	
	function login($username, $password, $resource=NULL){
		if ($this->connected)
			return $this->jab->login($username, $password, $resource);
		else
			return false;
	}
	
	# convenience method
	function connect_and_login ($server, $username, $password, $resource, $port=5222, $timeout=null, $alternate_ip=false)
	{
		if ($this->connect($server, $port, $timeout, $alternate_ip)) {
			return $this->login ($username, $password, $roster);
		}
	}
	
	function disconnect() {
		if (!$this->jab->terminated)
			$this->jab->terminated=true;
		$this->jab->disconnect();
	}
	
	#
	# Presence Management
	#
	function set_presence($show = NULL, $status = NULL, $to = NULL, $priority = NULL) {
		$this->jab->set_presence($show, $status, $to, $priority);
	}
	
	#
	# Roster Management
	#
	function get_roster() {
		$this->jab->browse();
		$this->jab->get_roster();
		debug (print_r($this->jab->roster, true));
	}
	
	function roster() {
	  return $this->jab->roster;
	}
	
	function subscribe($to, $request_message=NULL) {
		return $this->jab->subscribe($to,$request_message=NULL);
	}
	
	function unsubscribe($to) {
		return $this->jab->subscribe($to);
	}
	
	function subscription_request_accept($to) {
		return $this->jab->subscription_request_accept($to);
	}
	
	#
	# Messaging
	#
	function message($to, $type = "normal", $id = NULL, $body = NULL, $thread = NULL, $subject = NULL, $payload = NULL, $raw = false)
	{
		debug ("message to: $to, $type, $body");
		return $this->jab->message ($to, $type, $id, $body, $thread, $subject, $payload, $raw);
	}
	
	############### HANDLERS ################
	# Event handlers for responding to XMPP activity
	#
	#$this->_call_handler("authenticated");
	function handle_authenticated () {
		do_action ('xmpp_authenticated', $this);
	}
	
	#$this->_call_handler("authfailure",-1,"No authentication method available","");
	function handle_authfailure($code, $error) {
		do_action ('xmpp_authfailure', $this, $code, $error);
	}
	
	#this->_call_handler("deregistered",$this->jid);
	function handle_deregistered($jid) {
		do_action ('xmpp_deregistered', $this, $jid);
	}
	
	#$this->_call_handler("deregfailure",-2,"Unrecognized response from server");
	function handle_deregfailure($code, $error) {
		do_action ('xmpp_deregfailure', $this, $code, $error);
	}
	
	#$this->_call_handler("error",$code,$msg,$xmlns,$packet);
	function handle_error($code, $msg, $xmlns, $data) {
		//debug ("handle_error: $msg");
		do_action ('xmpp_error', $this, $code, $msg, $xmlns, $data);
	}
	
	#$this->_call_handler("heartbeat");
	// IS THIS USEFUL?
	function handle_heartbeat() {
		if ($this->countdown>0) {
			$this->countdown--;
			debug ('heartbeat: ${this->countdown}');
			do_action ('xmpp_heartbeat', $this);
			
			if ($this->countdown==1){
				$this->jab->terminated = true;
			}
		}
	}

	# TODO: add a generic xmpp_message hook for those who might want to get notifications of all incoming messages

	#$this->_call_handler("message_chat", $from, $to, $body, 
	#											 			$subject, $thread, $id, $extended, $packet);
	function handle_message_chat($from, $to, $body, 
												 			 $subject, $thread, $id, $extended, $data)
	{
		do_action ('xmpp_message_chat', $this, $from, $to, $body, 
														 			 		$subject, $thread, $id, $extended, $data);
	}
	
	#$this->_call_handler("message_groupchat",$packet);
	function handle_message_groupchat($data) {
		do_action ('xmpp_message_groupchat', $this, $data);
	}
	
	#$this->_call_handler("message_headline",$from, $to, $body,
	#														$subject, $extended, $packet);
	function handle_message_headline($from, $to, $body, 
												 			 		 $subject, $extended, $data)
	{
		do_action ('xmpp_message_headline', $this, $from, $to, $body, 
														 			 				$subject, $extended, $data);
	}
	
	#$this->_call_handler("message_normal", $from, $to, $body, 
	#														$subject,$thread,$id,$extended,$packet);
	function handle_message_normal($from, $to, $body, 
												 			 		 $subject, $thread, $id, $extended, $data)
	{
		do_action ('xmpp_message_normal', $this, $from, $to, $body, 
														 			 		 	$subject, $thread, $id, $extended, $data);
	}
	
	#$this->_call_handler("msgevent_composing_start",$from);
	function handle_msgevent_composing_start($from) {
		do_action ('xmpp_msgevent_composing_start', $this, $from);
	}
	
	#$this->_call_handler("msgevent_composing_stop",$from);
	function handle_msgevent_composing_stop($from) {
		do_action ('xmpp_msgevent_composing_stop', $this, $from);
	}
	
	#$this->_call_handler("msgevent_delivered",$from);
	function handle_msgevent_delivered($from) {
		do_action ('xmpp_msgevent_delivered', $this, $from);
	}
	
	#$this->_call_handler("msgevent_displayed",$from);
	function handle_msgevent_displayed($from) {
		do_action ('xmpp_msgevent_displayed', $this, $from);
	}
	
	#$this->_call_handler("msgevent_offline",$from);
	function handle_msgevent_offline($from) {
		do_action ('xmpp_msgevent_offline', $this, $from);
	}
	
	#$this->_call_handler("passwordchanged");
	function handle_passwordchanged() {
		do_action ('xmpp_passwordchanged', $this);
	}
	
	#$this->_call_handler("passwordfailure",-2,"Unrecognized response from server");
	function handle_passwordfailure($code, $error) {
		do_action ('xmpp_passwordfailure', $this, $code, $error);
	}
	
	#$this->_call_handler("regfailure",-1,"Username already registered","");
	function handle_regfailure($code, $error) {
		do_action ('xmpp_regfailure', $this, $code, $error);
	}
	
	#$this->_call_handler("registered",$this->jid);
	function handle_registered($jid) {
		do_action ('xmpp_registered', $this, $jid);
	}
	
	#$this->_call_handler("rosteradded");
	function handle_rosteradded() {
		do_action ('xmpp_rosteradded', $this);
	}
	
	#$this->_call_handler("rosteraddfailure",-2,"Unrecognized response from server");
	function handle_rosteraddfailure($code, $error) {
		do_action ('xmpp_rosteraddfailure', $this, $code, $error);
	}
	
	#$this->_call_handler("rosterremoved");
	function handle_rosterremoved() {
		do_action ('xmpp_rosterremoved', $this);
	}
	
	#$this->_call_handler("rosterremovefailure",-2,"Unrecognized response from server");
	function handle_rosterremovefailure($code, $error) {
		do_action ('xmpp_rosterremovefailure', $this, $code, $error);
	}
	
	#$this->_call_handler("rosterupdate",$jid,$is_new);
	function handle_rosterupdate() {
		$args = func_get_args(); 
		//debug ('handle_rosterupdate args: ' . print_r($args, true));
		if (func_num_args()>0) {
			list($jid,$is_new) = func_get_args();
			//debug ("xmpp_rosterupdate (xmpp, $jid,$is_new)");
			do_action ('xmpp_rosterupdate', $this, $jid, $is_new);
		} else {
			do_action ('xmpp_rosterupdate', $this);
		}
	}

	#$this->_call_handler("servicefields",&$fields,$packet_id,$reg_key,
	#													$reg_instructions,&$reg_x);
	

	#$this->_call_handler("servicefieldsfailure",-2,"Unrecognized response from server");
	#$this->_call_handler("serviceregfailure",-2,"Unrecognized response from server");
	#$this->_call_handler("serviceregistered",$jid);
	#$this->_call_handler('servicederegfailure",-2,"Unrecognized response from server");
	#$this->_call_handler('servicederegistered");
	#$this->_call_handler("serviceupdate",$jid,$is_new);
	
	#$this->_call_handler("terminated");
	function handle_terminated() {
		do_action ('xmpp_terminated', $this);
	}
	
	#$this->_call_handler('connected');
	function handle_connected() {
		do_action ('xmpp_connected', $this);
	}
	
	#$this->_call_handler('disconnected'); 
	function handle_disconnected() {
		do_action ('xmpp_disconnected', $this);
	}
	
	#$this->_call_handler('probe',$packet);
	#$this->_call_handler('stream_error',$packet);
	
	#$this->_call_handler('subscribe',$packet);
	function handle_subscribe($data) {
		do_action ('xmpp_subscribe', $this, $data);
	}
	
	#$this->_call_handler('subscribed',$packet);
	function handle_subscribed($data) {
		do_action ('xmpp_subscribed', $this, $data);
	}
	
	#$this->_call_handler('unsubscribe',$packet);
	function handle_unsubscribe($data) {
		do_action ('xmpp_unsubscribe', $this, $data);
	}
	
	#$this->_call_handler('unsubscribed',$packet);
	function handle_unsubscribed($data) {
		do_action ('xmpp_unsubscribed', $this, $data);
	}
	
	#$this->_call_handler("privatedata",$packetid,$namespace,$values);
	#$this->_call_handler('debug_log',$msg);
	
	#$this->_call_handler("contactupdated",$packetid);
	function handle_contactupdated($id) {
		do_action ('xmpp_contactupdated', $this, $id);
	}
	
	#$this->_call_handler("contactupdatefailure",-2,"Unrecognized response from server");
	function handle_contactupdatefailure($code, $error) {
		do_action ('xmpp_contactupdatefailure', $this, $code, $error);
	}	
	
	#$this->_call_handler('debug_log',$msg);
	function handle_debug_log($msg){
		do_action ('xmpp_debug_log', $this, $msg);
	}
}

$xmpp_msg 					=	'';

function XMPP_Setup ($identifier) {
	//debug ('setup passed in id -> ' . $identifier);
	// include the Jabber class
	require_once("jabber-php/class_Jabber.php");

	// create an instance of the Jabber class
	$display_debug_info = true;
	$jab = new Jabber($display_debug_info);

	// create an instance of our event handler class
	$xmpp = new XMPP_Jabber_Client($identifier, $jab);
	
	//debug ("XMPP ID -> " .$xmpp->identifier);
	
	// register handlers for everything
	$jab->set_handler("authenticated", $xmpp, 'handle_authenticated');
	$jab->set_handler("authfailure", $xmpp, 'handle_authfailure');
	$jab->set_handler("deregistered", $xmpp, 'handle_deregistered');
	$jab->set_handler("deregfailure", $xmpp, 'handle_deregfailure');
	$jab->set_handler("error", $xmpp, 'handle_error');
	$jab->set_handler("heartbeat", $xmpp, 'handle_heartbeat');
	$jab->set_handler("message_chat", $xmpp, 'handle_message_chat');
	$jab->set_handler("message_groupchat", $xmpp, 'handle_message_groupchat');
	$jab->set_handler("message_headline", $xmpp, 'handle_message_headline');
	$jab->set_handler("message_normal", $xmpp, 'handle_message_normal');
	$jab->set_handler("msgevent_composing_start", $xmpp, 'handle_msgevent_composing_start');
	$jab->set_handler("msgevent_composing_stop", $xmpp, 'handle_msgevent_composing_stop');
	$jab->set_handler("msgevent_delivered", $xmpp, 'handle_msgevent_delivered');
	$jab->set_handler("msgevent_displayed", $xmpp, 'handle_msgevent_displayed');
	$jab->set_handler("msgevent_offline", $xmpp, 'handle_msgevent_offline');
	$jab->set_handler("passwordchanged", $xmpp, 'handle_passwordchanged');
	$jab->set_handler("passwordfailure", $xmpp, 'handle_passwordfailure');
	$jab->set_handler("regfailure", $xmpp, 'handle_regfailure');
	$jab->set_handler("registered", $xmpp, 'handle_registered');
	$jab->set_handler("rosteradded", $xmpp, 'handle_rosteradded');
	$jab->set_handler("rosteraddfailure", $xmpp, 'handle_rosteraddfailure');
	$jab->set_handler("rosterremoved", $xmpp, 'handle_rosterremoved');
	$jab->set_handler("rosterremovefailure", $xmpp, 'handle_rosterremovefailure');
	$jab->set_handler("rosterupdate", $xmpp, 'handle_rosterupdate');
	//$jab->set_handler("servicefields", $xmpp, 'handle_servicefields');
	//$jab->set_handler("servicefieldsfailure", $xmpp, 'handle_servicefieldsfailure');
	//$jab->set_handler("serviceregfailure", $xmpp, 'handle_serviceregfailure');
	//$jab->set_handler("serviceregistered", $xmpp, 'handle_serviceregistered');
	//$jab->set_handler("servicederegfailure", $xmpp, 'handle_servicederegfailure');
	//$jab->set_handler("servicederegistered", $xmpp, 'handle_servicederegistered');
	//$jab->set_handler("serviceupdate", $xmpp, 'handle_serviceupdate');
	$jab->set_handler("terminated", $xmpp, 'handle_terminated');
	$jab->set_handler("connected", $xmpp, 'handle_connected');
	$jab->set_handler("disconnected", $xmpp, 'handle_disconnected');
	$jab->set_handler("probe", $xmpp, 'handle_probe');
	$jab->set_handler("stream_error", $xmpp, 'handle_stream_error');
	$jab->set_handler("subscribe", $xmpp, 'handle_subscribe');
	$jab->set_handler("subscribed", $xmpp, 'handle_subscribed');
	$jab->set_handler("unsubscribe", $xmpp, 'handle_unsubscribe');
	$jab->set_handler("unsubscribed", $xmpp, 'handle_unsubscribed');
	$jab->set_handler("privatedata", $xmpp, 'handle_privatedata');
	$jab->set_handler("debug_log", $xmpp, 'handle_debug_log');
	$jab->set_handler("contactupdated", $xmpp, 'handle_contactupdated');
	$jab->set_handler("contactupdatefailure", $xmpp, 'handle_contactupdatefailure');

	return $xmpp;
}

function XMPP_Run($identifier, $freq=1, $runtime=30, $server='', $port=5222){
	global $xmpp_msg;
	$xmpp_client = XMPP_Setup($identifier);
	
	if ($server=='') $server = get_option ('xmpp_server');
	
	$connected = $xmpp_client->connect ($server, $port);
	if (!$connected) {
		$xmpp_msg = $xmpp_client->jab->error;
	}
	//debug(print_r($xmpp_client, true));
  
  //debug("connected: ". $connected);
	
	if ($xmpp_client->connected) {
		//debug('...run');
		$xmpp_client->run($freq, $runtime);	
	} else {
		//debug ($xmpp_client->jab->error);
	}
	
	// execute runs in connect
	// will get here once 
	if ($connected) {
		//debug("...disconnect");
		$xmpp_client->disconnect();
	}
}

############################################################
# Plugin Management
#
// set your Jabber server hostname, username, and password here
// TODO: wp options
$xmpp_server        = get_option("xmpp_server");
$xmpp_server_OK     = get_option("xmpp_server_OK");
$xmpp_username      = get_option("xmpp_username");
$xmpp_password      = get_option("xmpp_password");

function handle_connected ($xmpp_obj) {
	if ($xmpp_obj->identifier != XMPP_CLIENT_ID) return;
	global $xmpp_username, $xmpp_password;
	//debug ('CONNECTED!');
	$xmpp_obj->login($xmpp_username, $xmpp_password);
}
add_action ('xmpp_connected', 'handle_connected');

function handle_authenticated ($xmpp_obj) {
	if ($xmpp_obj->identifier != XMPP_CLIENT_ID) return;
	global $xmpp_server_OK, $xmpp_msg;
	//debug ('AUTHENTICATED!');
	$xmpp_server_OK = "Y";
	//debug ("handle_authenticated: xmpp_server_OK: $xmpp_server_OK");
	update_option( 'xmpp_server_OK', $xmpp_server_OK );
	$xmpp_msg .= 'Server connection OK.';
}
add_action ('xmpp_authenticated', 'handle_authenticated');

function handle_authfailure ($xmpp_obj) {
	if ($xmpp_obj->identifier != XMPP_CLIENT_ID) return;
	global $xmpp_server_OK, $xmpp_msg;
	//debug ('AUTH FAILURE!');
	$xmpp_server_OK = "N";
	update_option( 'xmpp_server_OK', $xmpp_server_OK );
	$xmpp_msg .= 'Server connection failed: ' . $xmpp_obj->jab->error;
	$xmpp_obj->jab->terminated=true;
}
add_action ('xmpp_authfailure', 'handle_authfailure');

function handle_error ($xmpp_obj, $code, $msg) {
	if ($xmpp_obj->identifier != XMPP_CLIENT_ID) return;
	debug ('ERROR!');
	debug ($code.', '.$msg);
	global $xmpp_msg;
	$xmpp_msg .= $xmpp_obj->jab->error;
}
add_action ('xmpp_error', 'handle_error', 5, 3);

function xmpp_options_page () {
	global $xmpp_server,
	       $xmpp_server_OK, 
	       $xmpp_username, 
	       $xmpp_password,
				 $xmpp_msg,
				 $match_identifier;

	$hidden_field_name = 'xmpp_submit_hidden';
  
  if( $_POST[ $hidden_field_name ] == 'Y' ) {
      // Read their posted value
      $xmpp_server        =   $_POST[ 'xmpp_server' ];
			$xmpp_server_OK     =   $_POST[ 'xmpp_server_OK' ];
			$xmpp_username      =   $_POST[ 'xmpp_username' ];
			$xmpp_password      =   $_POST[ 'xmpp_password' ];
      
			if ($_POST['settings_submit']=='Save') {
      	// Save the posted value in the database
      	update_option( 'xmpp_server', $xmpp_server );
      	update_option( 'xmpp_server_OK', $xmpp_server_OK );
      	update_option( 'xmpp_username', $xmpp_username );
      	update_option( 'xmpp_password', $xmpp_password );

	      // Put an options updated message on the screen
				?>
				<div class="updated"><p><strong>Connection settings saved.</strong></p></div>
				<?php
				
			} elseif ($_POST['settings_submit']=='Test Settings') {
				
				XMPP_Run(XMPP_CLIENT_ID, 0.5, 5, $xmpp_server);
				if ($xmpp_server_OK == 'Y') {
					?>
				<div class="updated"><p><strong><?php echo $xmpp_msg; ?></strong></p></div>
					<?php
				} else {
					?>
				<div class="error"><p><strong><?php echo $xmpp_msg; ?></strong></p></div>
					<?php
				}
			}
			
  }
  // Now display the options editing screen
  echo '<div class="wrap">';
	
	// header
  echo "<h2>XMPP Connection Settings</h2>";
	
	// options form
  
  ?>

	<form name="roster_settings" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y" />
	
	<fieldset>
	  <legend>XMPP Server and User Settings</legend>
	  
	  <!-- Oh, for the days of OAuth  -->
	  <p>Server:
	     <input type="text" name="xmpp_server" value="<?php echo $xmpp_server ?>" id="xmpp_server" /></p>
	  
	  <p>Username:
	     <input type="text" name="xmpp_username" value="<?php echo $xmpp_username ?>" id="xmpp_username" /></p>
	  
	  <p>Password:
	     <input type="password" name="xmpp_password" value="<?php echo $xmpp_password ?>" id="xmpp_password" /></p>
	  
	  <?php $submit = $xmpp_server_OK ? 'Save' : 'Test Settings'; ?>
	  <input type="submit" name="settings_submit" value="<?php echo $submit; ?>" id="settings_submit" />
	</fieldset>
	
	</form>
	</div>
	
	<?php  
}

/* ========= admin ========= */
function xmpp_add_pages() {
	// Add a new submenu under Options:
	add_options_page('XMPP Settings', 'XMPP Settings', 10, __FILE__, 'xmpp_options_page');
}
add_action('admin_menu', 'xmpp_add_pages');

if (basename($argv[0]) == basename(__FILE__)) {
  $registry = array (
  		//'xmpp_heartbeat' => 'do_heartbeat',
  		//'xmpp_connected' => 'do_connected',
  		//'xmpp_authenticated' => 'do_loggedin',
  		//'xmpp_debug_log' => 'do_debug_log',
  		//'xmpp_rosterupdate' => 'roster_rosterupdate'
  	);

  $options = array (
  		'xmpp_server' => 'diso-project.org',
  		'xmpp_username' => 'steve',
  		'xmpp_password' => 'jophilli',
  		'xmpp_port' => '5222',
  		//'xmpp_server' => '',
  		//'xmpp_server' => '',
  		//'xmpp_server' => '',

  	);
  	
    XMPP_Run('XMPP_CLIENT',1,15);
}