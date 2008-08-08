<?php

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/classes.php';

if(!$_REQUEST['user']) {//get administrator
	$_REQUEST['user'] = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='wp_user_level' AND meta_value='10'");
}//end if ! _REQUEST['user']
if(is_numeric($_REQUEST['user']))
	$userdata = get_userdata($_REQUEST['user']);
else
	$userdata = get_userdatabylogin($_REQUEST['user']);
$stream = new ActionStream($userdata->actionstream, $userdata->ID);
$stream = $stream->items(10);

header('Content-Type: application/rss+xml');
echo '<?xml version="1.0" ?>';

?>
<rss version="2.0">
	<channel>
		<title>ActionStream for <?php echo htmlspecialchars($userdata->display_name); ?></title>
		<description>ActionStream data</description>
		<link><?php echo htmlspecialchars($userdata->user_url); ?></link>
<?php

$after_service = array();
$c = 0;

foreach($stream as $item) {

	if(function_exists('diso_user_is') && !diso_user_is($userdata->profile_permissions[$item['service']])) continue;

	if($item['service'] == $previous_service) {

		$after_service[] = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

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

		$during_service = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

	}//end if-else service

	if(!isset($_REQUEST['full'])) $previous_service = $item['service'];

}//end foreach

?>
	</channel>
</rss>
