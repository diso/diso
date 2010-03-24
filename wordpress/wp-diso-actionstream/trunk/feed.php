<?php

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/classes.php';

function h($s, $quote_style = ENT_COMPAT, $charset=NULL, $double_encode=true) {
	return htmlspecialchars($s, $quote_style, $charset, $double_encode);
}

$user_id = activity_stream_get_user_id( $_REQUEST['user'] );
$userdata = get_userdata($user_id);
$stream = new ActionStream($userdata->actionstream, $userdata->ID);
$stream = $stream->items(get_option('posts_per_page')*4, true);

$selflink = get_feed_link('action_stream');
$selflink .= (strpos($selflink, '?') ? '&' : '?') . 'user=' . $user_id;
if(isset($_REQUEST['full'])) $selflink .= (strpos($selflink, '?') ? '&' : '?') . 'full';
if(is_array($_REQUEST['include'])) {
	sort($_REQUEST['include']);
	$selflink .= (strpos($selflink, '?') ? '&' : '?') . 'include[]=' . implode('&include[]=', $_REQUEST['include']);
}
if(is_array($_REQUEST['exclude'])) {
	sort($_REQUEST['exclude']);
	$selflink .= (strpos($selflink, '?') ? '&' : '?') . 'exclude[]=' . implode('&exclude[]=', $_REQUEST['exclude']);
}

header('Content-Type: application/rss+xml');
header('ETag: '.md5(time())); // Hack to override default wordpress headers that break feed readers
header('Last-Modified: '.date('r'));
header('Expires: '.date('r', time()+120));

echo '<?xml version="1.0" ?>'."\n";

?>
<rss version="2.0"
     xmlns:activity="http://activitystrea.ms/spec/1.0/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:geo="http://www.georss.org/georss"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:thr="http://purl.org/syndication/thread/1.0"
     xmlns:v="http://www.w3.org/2006/vcard/ns#"
     xmlns:xCal="urn:ietf:params:xml:ns:xcal">
	<channel>
		<title>ActionStream for <?php echo h($userdata->display_name); ?></title>
		<description>ActionStream data</description>
		<link><?php echo h($userdata->user_url); ?></link>
		<atom:link rel="self" href="<?php echo h($selflink); ?>" />
		<activity:subject>
			<activity:object-type>http://activitystrea.ms/schema/1.0/person</activity:object-type>
			<atom:title><?php echo h($userdata->display_name); ?></atom:title>
			<atom:id><?php echo h($userdata->user_url); ?></atom:id>
			<v:vCard>
				<v:fn><?php echo h($userdata->display_name); ?></v:fn>
				<v:nickname><?php echo h($userdata->nickname); ?></v:nickname>
				<v:url rdf:resource="<?php echo h($userdata->user_url); ?>" />
				<?php if($userdata->photo): ?><v:photo rdf:resource="<?php echo h($userdata->photo); ?>" /><?php endif; ?>
			</v:vCard>
		</activity:subject>
<?php

if(function_exists('get_pubsub_endpoints') && !$_REQUEST['include'] && !$_REQUEST['exclude']) {
	foreach((array)get_pubsub_endpoints() as $hub) {
	?>
		<atom:link rel="hub" href="<?php echo h($hub); ?>" />
	<?php
	}
}

if($userdata->photo) {
	echo '		<image><title>'.h($userdata->display_name).'</title><url>'.h($userdata->photo).'</url><link>'.h($userdata->user_url).'</link></image>';
}

$after_service = array();
$c = 0;

foreach($stream as $item) {

	if(is_array($item)) {
		$as_item  = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);
	} else {
		$as_item = new ActionStreamItem($item);
	}

	if(function_exists('diso_user_is') && !diso_user_is($userdata->profile_permissions[$as_item->get('service')])) continue;
	if($_REQUEST['include'] && !in_array($as_item->get('service'), (array)$_REQUEST['include'])) continue;
	if($_REQUEST['exclude'] && in_array($as_item->get('service'), (array)$_REQUEST['exclude'])) continue;

	if($as_item->get('service') == $previous_service) {

		$after_service[] = $as_item;

	} else {

		if($during_service) {

			$c++;
			$service = $during_service->get('service');

			echo '	<item>'."\n";

			echo '		<title>';
			if(count($after_service)) echo h(count($after_service).' items from '.$service."\n");
				else echo h($during_service->get('title') ? $during_service->get('title') : strip_tags($during_service.''));
			echo '</title>'."\n";
			if($during_service->get('created_on')) echo '<pubDate>'.date('r',$during_service->get('created_on')).'</pubDate>'."\n";
			if($during_service->get('url')) echo '<link>'.h($during_service->get('url')).'</link>'."\n";
			if($during_service->identifier()) echo '<guid isPermaLink="false">'.h($during_service->identifier()).'</guid>'."\n";
				else echo '<guid isPermaLink="false">NO IDENTIFIER</guid>'."\n";

			echo '		<description>'."\n";
			$item_wrapper = 'div';
			if(count($after_service)) {
				$item_wrapper = 'li';
				echo h("\t\t\t<ul class=\"hfeed action-stream-list\">",ENT_NOQUOTES,'UTF-8');
			}
			echo h('<'.$item_wrapper.' class="hentry service-icon service-'.$service.'">'.$during_service.'</'.$item_wrapper.'>'."\n", ENT_NOQUOTES, 'UTF-8');
			foreach($after_service as $cnt)
				echo h('<li class="hentry service-icon service-'.$service.' actionstream-hidden">'.$cnt.'</li>'."\n", ENT_NOQUOTES, 'UTF-8');
			if(count($after_service)) echo h('</ul>')."\n";

			echo '		</description>'."\n";
			if(!count($after_service) && $during_service->get('in-reply-to')) {
				foreach((array)$during_service->get('in-reply-to') as $r) {
					if(preg_match('/^https?:/', $r)) $href = $r;
					if(!$href && preg_match('/https?:\/\/[^\s]+/', $r, $m)) $href = $m[0];
					echo '		<thr:in-reply-to ref="'.h($r).'"';
					if($href) echo ' href="'.h($href).'"';
					echo ' />'."\n";
				}
			}
			/* Other namespaces */
			if(!count($after_service)) {
				/* GeoRSS */
				if($during_service->get('lat') && $during_service->get('long')) {
					echo '		<geo:point>'.h($during_service->get('lat').' '.$during_service->get('long')).'</geo:point>'."\n";
				}
				/* MediaRSS */
				if($during_service->get('thumbnail')) {
					echo '		<media:content><media:thumbnail url="'.h($during_service->get('thumbnail')).'" /></media:content>'."\n";
				}
				/* xCalendar (iCal profile) */
				if($during_service->get('dtstart')) {
					echo '		<xCal:dtstart>'.h(date('c', $during_service->get('dtstart'))).'</xCal:dtstart>'."\n";
				}
				if($during_service->get('dtend')) {
					echo '		<xCal:dtend>'.h(date('c', $during_service->get('dtend'))).'</xCal:dtend>'."\n";
				}
				if($during_service->get('location')) {
					echo '		<xCal:location>'.h($during_service->get('location')).'</xCal:location>'."\n";
				}
			}

			echo '	</item>'."\n";

			$after_service = array();
			if($c > get_option('posts_per_page')) break;

		}//end if during service

		$during_service = $as_item;

	}//end if-else service

	if(!isset($_REQUEST['full'])) $previous_service = $as_item->get('service');

}//end foreach

?>
	</channel>
</rss>
