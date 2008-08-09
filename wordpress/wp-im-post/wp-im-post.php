<?php
/*
Plugin Name: WP Post Via IM
Plugin URI: http://diso.googlecode.com/
Description:  Post to you blog via IM (jabber, gtalk)
Version: 0.1
Author: Steve Ivy
Author URI: http://diso-project.org/
*/

/*
lookup_id: see f the jid is in the usertable
*/
function lookup_jid ($jid) {
    if (email_exists($jid)) {
        return true;
    }
    return false;
}

function subscribe_hook ($data) {
    
    # TODO: is JID in user tables?
    if (lookup_jid ()){
    # TODO: say yes
    # TODO: subscribe back
    # TODO: otherwise say no
    }
}

function message_hook ($from, $to, $body, 
            $subject, $thread, $id, $extended, $data) {
     # TODO: is JID in user tables?
     if ($uid = lookup_jid ($from)) {
         # parse message
         // Create post object
           $my_post = array();
           $my_post['post_title'] = $subject;
           $my_post['post_content'] = $body;
           $my_post['post_status'] = 'draft';
           $my_post['post_author'] = $uid;
           $my_post['post_category'] = array(0);

           # post new blog post
           wp_insert_post( $my_post );
           
         # TODO: respond with error, or success status and link to post
     }
}

if (!class_exists('XMPP_Jabber_Client')) {
    # TODO: 
} else {

    # register 
    # add_action ( 'hook_name', 'your_function_name', [priority], [accepted_args] );
    add_action ('xmpp_message_chat', 'message_hook', 10, 9);
    add_action ('xmpp_subscribe', 'subscribe_hook', 10, 1);
    
}

?>