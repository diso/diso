<?php

header('Content-Type: text/plain;charset=utf-8');

/* Sanitize user input */
switch($_GET['type']) {
	case 'vcard':
	case 'hcard':
	case 'pcjson':
		/* OK */
		break;
	default:
		die('INVALID TYPE');
}

/* We need WP funsctions */
require '../../../wp-config.php';

/* Magic globals for templates */
$_contacts = array();

/* Go through "bookmarks" and get contacts */
foreach(get_bookmarks() as $contact) {
	$_contacts[$contact->link_name] = array(
		'fn' => $contact->link_name,
		'url' => array_merge(array($contact->link_url), (array)$_contacts[$contact->link_name]['url']),
		'rel' => implode(' ',array_unique(explode(' ',trim($contact->link_rel . ' ' . $_contacts[$contact->link_name]['rel'])))),
		'note' => trim($contact->link_notes . ' ' . $_contacts[$contact->link_name]['note']),
	);
	/*TODO: fix get_user_by_uri and add any info from their profile that may exist.
	*/
	/* TODO: also get categories */
}

/* Function for templates */
function next_contact() {
	global $_contacts;
	return array_shift($_contacts);
}

/* render the template */
require $_GET['type'].'.php';

?>
