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

/*
 STUBS for testing:
*/
if (!function_exists('get_option')) {
  function get_option($name, $default='') {
  	global $options;
  	if (isset($options[$name]))
  		return $options[$name];
  	else
  		return $default;
  }
}

function debug ($msg) {
	print "$msg\n";
}

if (!function_exists('do_action')) {
  function do_action() {
  	global $registry;
  	$arg_list = func_get_args();
  	$func = array_shift($arg_list);
  	if (in_array($func, array_keys($registry))){
  		debug ( "calling do_action: $registry[$func]");
  		//debug ( print_r ($arg_list));
  		return call_user_func_array ($registry[$func], $arg_list);
  	}
  }
}

function do_debug_log($xmpp_obj, $msg){
	debug ($msg);
}

if (!function_exists('has_action')) {
  function has_action() {
  	$arg_list = func_get_args();
  	$func = array_shift($arg_list);
  	//debug( "calling has_action: $func \n");
  	return true;
  }
}
/*
function do_heartbeat($xmpp_obj) {
	debug ("heartbeat: $xmpp_obj->countdown");
}

function do_connected($xmpp_obj) {
	debug (print_r($xmpp_obj, true));
	debug ('handling connected');
	debug ("...login");
	
	$username = get_option('xmpp_username');
	$pwd = get_option('xmpp_password');
	
	$xmpp_obj->login($username, $pwd, 'xxxx');
}

function do_loggedin($xmpp_obj) {
	
	// browser for transport gateways
	debug('...browse');
	$xmpp_obj->jab->browse();
	
	// retrieve this user's roster
	debug('...roster');
	$xmpp_obj->jab->get_roster();
	
	debug("...presence");
	$xmpp_obj->set_presence('', "online from php");
	
	debug("...message");
	$xmpp_obj->message('steveivy@gmail.com','chat',NULL,'test message from php client');
	
	//$xmpp_obj->disconnect();
}

function roster_rosterupdate ($xmpp_obj, $jid, $is_new) {
	debug ("roster updated with: $jid (is_new: $is_new)");
}
*/
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
	function XMPP_Jabber_Client(&$jab){
		$this->jab = &$jab;
		$this->first_roster_update = true;
		$this->countdown = 0;
		$this->connected=false;
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
		if (has_action('xmpp_authenticated')) {
			do_action ('xmpp_authenticated', $this);
		}
	}
	
	#$this->_call_handler("authfailure",-1,"No authentication method available","");
	function handle_authfailure($code, $error) {
		if (has_action('xmpp_authfailure')) {
			do_action ('xmpp_authfailure', $this, $code, $error);
		}
	}
	
	#this->_call_handler("deregistered",$this->jid);
	function handle_deregistered($jid) {
		if (has_action('xmpp_deregistered')) {
			do_action ('xmpp_deregistered', $this, $jid);
		}
	}
	
	#$this->_call_handler("deregfailure",-2,"Unrecognized response from server");
	function handle_deregfailure($code, $error) {
		if (has_action('xmpp_deregfailure')) {
			do_action ('xmpp_deregfailure', $this, $code, $error);
		}
	}
	
	#$this->_call_handler("error",$code,$msg,$xmlns,$packet);
	function handle_error($code, $msg, $xmlns, $data) {
		debug ("handle_error: $msg");
		if (has_action('xmpp_error')) {
			do_action ('xmpp_error', $this, $code, $msg, $xmlns, $data);
		}
	}
	
	#$this->_call_handler("heartbeat");
	// IS THIS USEFUL?
	function handle_heartbeat() {
		if ($this->countdown>0) {
			$this->countdown--;
			debug ('heartbeat: ${this->countdown}');
			if (has_action('xmpp_heartbeat')) {
				do_action ('xmpp_heartbeat', $this);
			}
			
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
		if (has_action('xmpp_message_chat')) {
			do_action ('xmpp_message_chat', $this, $from, $to, $body, 
														 			 		$subject, $thread, $id, $extended, $data);
		}
	}
	
	#$this->_call_handler("message_groupchat",$packet);
	function handle_message_groupchat($data) {
		if (has_action('xmpp_message_groupchat')) {
			do_action ('xmpp_message_groupchat', $this, $data);
		}
	}
	
	#$this->_call_handler("message_headline",$from, $to, $body,
	#														$subject, $extended, $packet);
	function handle_message_headline($from, $to, $body, 
												 			 		 $subject, $extended, $data)
	{
		if (has_action('xmpp_message_headline')) {
			do_action ('xmpp_message_headline', $this, $from, $to, $body, 
														 			 				$subject, $extended, $data);
		}
	}
	
	#$this->_call_handler("message_normal", $from, $to, $body, 
	#														$subject,$thread,$id,$extended,$packet);
	function handle_message_normal($from, $to, $body, 
												 			 		 $subject, $thread, $id, $extended, $data)
	{
		if (has_action('xmpp_message_normal')) {
			do_action ('xmpp_message_normal', $this, $from, $to, $body, 
														 			 		 	$subject, $thread, $id, $extended, $data);
		}
	}
	
	#$this->_call_handler("msgevent_composing_start",$from);
	function handle_msgevent_composing_start($from) {
		if (has_action('xmpp_msgevent_composing_start')) {
			do_action ('xmpp_msgevent_composing_start', $this, $from);
		}
	}
	
	#$this->_call_handler("msgevent_composing_stop",$from);
	function handle_msgevent_composing_stop($from) {
		if (has_action('xmpp_msgevent_composing_stop')) {
			do_action ('xmpp_msgevent_composing_stop', $this, $from);
		}
	}
	
	#$this->_call_handler("msgevent_delivered",$from);
	function handle_msgevent_delivered($from) {
		if (has_action('xmpp_msgevent_delivered')) {
			do_action ('xmpp_msgevent_delivered', $this, $from);
		}
	}
	
	#$this->_call_handler("msgevent_displayed",$from);
	function handle_msgevent_displayed($from) {
		if (has_action('xmpp_msgevent_displayed')) {
			do_action ('xmpp_msgevent_displayed', $this, $from);
		}
	}
	
	#$this->_call_handler("msgevent_offline",$from);
	function handle_msgevent_offline($from) {
		if (has_action('xmpp_msgevent_offline')) {
			do_action ('xmpp_msgevent_offline', $this, $from);
		}
	}
	
	#$this->_call_handler("passwordchanged");
	function handle_passwordchanged() {
		if (has_action('xmpp_passwordchanged')) {
			do_action ('xmpp_passwordchanged', $this);
		}
	}
	
	#$this->_call_handler("passwordfailure",-2,"Unrecognized response from server");
	function handle_passwordfailure($code, $error) {
		if (has_action('xmpp_passwordfailure')) {
			do_action ('xmpp_passwordfailure', $this, $code, $error);
		}
	}
	
	#$this->_call_handler("regfailure",-1,"Username already registered","");
	function handle_regfailure($code, $error) {
		if (has_action('xmpp_regfailure')) {
			do_action ('xmpp_regfailure', $this, $code, $error);
		}
	}
	
	#$this->_call_handler("registered",$this->jid);
	function handle_registered($jid) {
		if (has_action('xmpp_registered')) {
			do_action ('xmpp_registered', $this, $jid);
		}
	}
	
	#$this->_call_handler("rosteradded");
	function handle_rosteradded() {
		if (has_action('xmpp_rosteradded')) {
			do_action ('xmpp_rosteradded', $this);
		}
	}
	
	#$this->_call_handler("rosteraddfailure",-2,"Unrecognized response from server");
	function handle_rosteraddfailure($code, $error) {
		if (has_action('xmpp_rosteraddfailure')) {
			do_action ('xmpp_rosteraddfailure', $this, $code, $error);
		}
	}
	
	#$this->_call_handler("rosterremoved");
	function handle_rosterremoved() {
		if (has_action('xmpp_rosterremoved')) {
			do_action ('xmpp_rosterremoved', $this);
		}
	}
	
	#$this->_call_handler("rosterremovefailure",-2,"Unrecognized response from server");
	function handle_rosterremovefailure($code, $error) {
		if (has_action('xmpp_rosterremovefailure')) {
			do_action ('xmpp_rosterremovefailure', $this, $code, $error);
		}
	}
	
	#$this->_call_handler("rosterupdate",$jid,$is_new);
	function handle_rosterupdate($jid, $is_new) {
		if (has_action('xmpp_rosterupdate')) {
			do_action ('xmpp_rosterupdate', $this, $jid, $is_new);
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
		if (has_action('xmpp_terminated')) {
			do_action ('xmpp_terminated', $this);
		}
	}
	
	#$this->_call_handler('connected');
	function handle_connected() {
		if (has_action('xmpp_connected')) {
			do_action ('xmpp_connected', $this);
		}
	}
	
	#$this->_call_handler('disconnected'); 
	function handle_disconnected() {
		if (has_action('xmpp_disconnected')) {
			do_action ('xmpp_disconnected', $this);
		}
	}
	
	#$this->_call_handler('probe',$packet);
	#$this->_call_handler('stream_error',$packet);
	
	#$this->_call_handler('subscribe',$packet);
	function handle_subscribe($data) {
		if (has_action('xmpp_subscribe')) {
			do_action ('xmpp_subscribe', $this, $data);
		}
	}
	
	#$this->_call_handler('subscribed',$packet);
	function handle_subscribed($data) {
		if (has_action('xmpp_subscribed')) {
			do_action ('xmpp_subscribed', $this, $data);
		}
	}
	
	#$this->_call_handler('unsubscribe',$packet);
	function handle_unsubscribe($data) {
		if (has_action('xmpp_unsubscribe')) {
			do_action ('xmpp_unsubscribe', $this, $data);
		}
	}
	
	#$this->_call_handler('unsubscribed',$packet);
	function handle_unsubscribed($data) {
		if (has_action('xmpp_unsubscribed')) {
			do_action ('xmpp_unsubscribed', $this, $data);
		}
	}
	
	#$this->_call_handler("privatedata",$packetid,$namespace,$values);
	#$this->_call_handler('debug_log',$msg);
	
	#$this->_call_handler("contactupdated",$packetid);
	function handle_contactupdated($id) {
		if (has_action('xmpp_contactupdated')) {
			do_action ('xmpp_contactupdated', $this, $id);
		}
	}
	
	#$this->_call_handler("contactupdatefailure",-2,"Unrecognized response from server");
	function handle_contactupdatefailure($code, $error) {
		if (has_action('xmpp_contactupdatefailure')) {
			do_action ('xmpp_contactupdatefailure', $this, $code, $error);
		}
	}	
	
	#$this->_call_handler('debug_log',$msg);
	function handle_debug_log($msg){
		if (has_action('xmpp_debug_log')) {
			do_action ('xmpp_debug_log', $this, $msg);
		}
	}
}

function XMPP_Setup () {
	// include the Jabber class
	require_once("jabber-php/class_Jabber.php");

	// create an instance of the Jabber class
	$display_debug_info = true;
	$jab = new Jabber($display_debug_info);

	// create an instance of our event handler class
	$xmpp = new XMPP_Jabber_Client($jab);
	
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

function XMPP_Run($freq=1, $runtime=30){
	$xmpp_client = XMPP_Setup();
	
	$server = get_option ('xmpp_server');
	
	$connected = $xmpp_client->connect ($server);
	debug(print_r($xmpp_client, true));
  
  debug("connected: ".$connected);
	
	if ($xmpp_client->connected) {
		debug('...run');
		$xmpp_client->run($freq, $runtime);		
	}
	
	// execute runs in connect
	// will get here once 
	debug("...disconnect");
	$xmpp_client->disconnect();
}

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
  	
    XMPP_Run(1,15);
}