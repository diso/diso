<?php

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/classes.php';


$user_id = activity_stream_get_user_id( $_REQUEST['user'] );
$userdata = get_userdata($user_id);
$stream = new ActionStream($userdata->actionstream, $userdata->ID);
$stream = $stream->items(10*4, true);

$selflink = get_feed_link('action_stream');
$selflink .= (strpos($selflink, '?') ? '&' : '?') . 'user=' . $user_id;
if(isset($_REQUEST['full'])) $selflink .= (strpos($selflink, '?') ? '&' : '?') . 'full';

header('Content-Type: application/rss+xml');
header('ETag: '.md5(time())); // Hack to override default wordpress headers that break feed readers
header('Last-Modified: '.date('r'));
echo '<?xml version="1.0" ?>';

?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>ActionStream for <?php echo htmlspecialchars($userdata->display_name); ?></title>
		<description>ActionStream data</description>
		<link><?php echo htmlspecialchars($userdata->user_url); ?></link>
		<atom:link rel="self" href="<?php echo htmlspecialchars($selflink); ?>" />
<?php

if($userdata->photo) {
	echo '		<image><title>'.htmlspecialchars($userdata->display_name).'</title><url>'.htmlspecialchars($userdata->photo).'</url><link>'.htmlspecialchars($userdata->user_url).'</link></image>';
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

	if($as_item->get('service') == $previous_service) {

		$after_service[] = $as_item;

	} else {

		if($during_service) {

			$c++;

			echo '	<item>'."\n";

			echo '		<title>';
			$t = iconv('UTF-8//IGNORE','UTF-8//IGNORE',substr(strip_tags(html_entity_decode($during_service,ENT_QUOTES,'UTF-8')),0,60));
			echo htmlspecialchars($t);
			if(strlen(strip_tags(html_entity_decode($during_service,ENT_QUOTES,'UTF-8'))) > 60) echo '...';

			if(count($after_service)) echo ' (and '.count($after_service).' more...)'."\n";
			echo '</title>'."\n";
			if($during_service->get('created_on')) echo '<pubDate>'.date('r',$during_service->get('created_on')).'</pubDate>'."\n";
			if($during_service->get('url')) echo '<link>'.htmlspecialchars($during_service->get('url')).'</link>'."\n";
			if($during_service->identifier()) echo '<guid isPermaLink="false">'.htmlspecialchars($during_service->identifier()).'</guid>'."\n";
				else echo '<guid isPermaLink="false">NO IDENTIFIER</guid>'."\n";
			echo '		<description>'."\n";
			echo htmlspecialchars("\t\t\t<ul class=\"hfeed action-stream-list\">",ENT_NOQUOTES,'UTF-8');
			echo htmlspecialchars('<li class="hentry service-icon service-'.$previous_service.'">'.$during_service, ENT_NOQUOTES, 'UTF-8');

			foreach($after_service as $cnt)//not sure if I'm a fan of hiding the user on hidden entries... suggestion came from jangro.com
				echo htmlspecialchars('<li class="hentry service-icon service-'.$previous_service.' actionstream-hidden">'.$cnt.'</li>', ENT_NOQUOTES, 'UTF-8');
			$after_service = array();

			echo htmlspecialchars('</ul>')."\n";
			echo '		</description>'."\n";
			echo '	</item>'."\n";

			if($c > 20) break;

		}//end if during service

		$during_service = $as_item;

	}//end if-else service

	if(!isset($_REQUEST['full'])) $previous_service = $as_item->get('service');

}//end foreach

?>
	</channel>
</rss>
