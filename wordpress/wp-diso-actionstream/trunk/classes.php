<?php

class ActionStreamItem {

	protected $data, $service, $setup_idx, $config, $user_id;

	function __construct($data, $service, $setup_idx, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->service = $service;
		$this->setup_idx = $setup_idx;
		$this->user_id = $user_id;

		if($data) {
			if(is_array($data)) {
				$this->data = $data;
			} else {
				global $actionstream_config, $actionstream_config;
				$data = $actionstream_config['db']->get_result("SELECT data,service,setup_idx FROM {$actionstream_config['item_table']} WHERE identifier_hash='$data'", ARRAY_A);
				$this->data = unserialize($data[0]['data']);
				$this->service = unserialize($data[0]['service']);
				$this->setup_idx = unserialize($data[0]['setup_idx']);
			}//end if-else is_array data
		} else {
			$this->data = array();
		}//end if-else data
	}//end constructor

	function set($k, $v) {
		$this->data[$k] = $v;
	}//end function set

	function get($k) {
		return $this->data[$k];
	}//end function get

	function identifier() {
      $identifier_field = $this->config['action_streams'][$this->service][$this->setup_idx]['identifier'];
      if(!$identifier_field) $identifier_field = 'identifier';
		if(!$this->data[$identifier_field]) return $this->data['created_on'].$this->data['service'];
      return $this->data[$identifier_field];
	}//end function identifier

	function save() {
		global $actionstream_config;
		if(!$this->data['created_on'] && $this->data['modified_on']) $this->data['created_on'] = $this->data['modified_on'];
		$created_on = $this->data['created_on'] = (int)$this->data['created_on'] ? (int)$this->data['created_on'] : time();
		$data = $actionstream_config['db']->escape(serialize($this->data));
		$identifier_hash = sha1($this->identifier());
		$actionstream_config['db']->query("INSERT INTO {$actionstream_config['item_table']} (identifier_hash, user_id, created_on, service, setup_idx, data) VALUES ('$identifier_hash', $this->user_id, $created_on, '$this->service', '$this->setup_idx', '$data') ON DUPLICATE KEY UPDATE data='$data'");
	}//end function save

	function __toString($hide_user=false) {
		return ActionStreamItem::interpolate($this->data, $this->config['action_streams'][$this->service][$this->setup_idx]['html_params'], $this->config['action_streams'][$this->service][$this->setup_idx]['html_form'], $this->config['profile_services'][$this->service], $hide_user).' <abbr class="published" title="'.date('c',$this->data['created_on']).'">@ '.date('Y-m-d H:i',$this->data['created_on']).'</abbr>';
	}//end function toString

	protected static function interpolate($data, $fields, $template, $service, $hide_user) {
		array_unshift($fields, 'ident');
		if($data['ident'] && $service) {
			$data['ident'] = '<span class="author vcard" '.($hide_user ? 'style="display:none;"' : '').'><a class="url fn nickname" href="'.htmlspecialchars(str_replace('%s',$data['ident'],$service['url'])).'">'.htmlspecialchars($data['ident']).'</a></span>';
		}//end if ident
		foreach($fields as $i => $k) {
			if($data[$k] == html_entity_decode(strip_tags($data[$k]))) $data[$k] = htmlspecialchars($data[$k]);
			$template = str_replace('[_'.($i+1).']', $data[$k], $template);
		}//end foreach fields
		return $template;
	}//end function interpolate

}//end class ActionStreamItem

class ActionStream {

	protected $config, $ident, $user_id;

	function __construct($ident, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->ident = $ident;
		$this->user_id = $user_id;
	}//end constructor

	function update() {
		foreach($this->ident as $service => $id) {
			$setup = $this->config['action_streams'][$service];
			if(!is_array($setup)) continue;
			//TODO: HTML/Microformats
			foreach($setup as $setup_idx => $stream) {
				$url = str_replace('{{ident}}', $id, $stream['url']);
				if(!$url) {//feed autodetect
					$raw = get_raw_actionstream(str_replace('%s',$id,$this->config['profile_services'][$service]['url']));

					preg_match('/<[\s]*link.+\/atom\+xml[^\f]+?href="(.+)"/', $raw, $match);
					$aurl = html_entity_decode($match[1]);

					preg_match('/<[\s]*link.+\/rss\+xml[^\f]+?href="(.+)"/', $raw, $match);
					$rurl = html_entity_decode($match[1]);

					if(($stream['atom'] && $aurl) || !$rurl) {
						$url = $aurl;
						if(!$stream['atom']) $stream['atom'] = array();
					} else {
						$url = $rurl;
						if(!$stream['rss2']) $stream['rss2'] = array();
					}//end if-else atom/rss2
					if(!$url) continue;
				}//end if ! url
				$raw = get_raw_actionstream($url);
				if(!$raw) continue;

				if(isset($stream['atom'])) {
					if(!$stream['atom']) $stream['atom'] = array();
					$stream['xpath'] = array(
							'foreach' => '//entry',
							'get' => array_merge(array(
								'created_on' => 'published/child::text()',
								'modified_on' => 'updated/child::text()',
								'title' => 'title/child::text()',
								'url' => 'link[@rel=\'alternate\']/@href',
								'identifier' => 'id/child::text()'
							), $stream['atom'])
					);
				}//end if atom

				if(isset($stream['rss2'])) {
					if(!$stream['rss2']) $stream['rss2'] = array();
					$stream['xpath'] = array(
							'foreach' => '//item',
							'get' => array_merge(array(
								'created_on' => 'pubDate/child::text()',
								'title' => 'title/child::text()',
								'url' => 'link/child::text()',
								'identifier' => 'guid/child::text()'
							), $stream['rss2'])
					);
				}//end if atom

				if($stream['xpath']) {
					@$doc = simplexml_load_string(str_replace('xmlns=','a=',$raw));
					if($doc && method_exists($doc, 'registerXPathNamespace')) {
						$doc->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
						$doc->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
						$doc->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
					}
					if($doc && $stream['xpath']['foreach'])
						$items = $doc->xpath($stream['xpath']['foreach']);
					if(!$items) $items = array();

					foreach($items as $item) {
						$update = new ActionStreamItem(array('ident' => $id), $service, $setup_idx, $this->user_id);
						foreach($stream['xpath']['get'] as $k => $p) {
							@$value = $item->xpath($p);//TEMP
							$value = $value[0].'';
							if($service == 'twitter') {
								$value = preg_replace('/^'.$id.'\: /','',$value);
								$value = preg_replace('/@([a-zA-z0-9_]+)/','<span class="reply vcard tag">@<a class="url fn" href="http://twitter.com/$1">$1</a></span>',$value);
								$value = preg_replace('/#([a-zA-z0-9_]+)/','#<a href="http://hashtags.org/tag/$1" rel="tag">$1</a>',$value);
							}//end if twitter
							if(($k == 'created_on' || $k == 'modified_on') && !is_numeric($value)) $value = strtotime($value);
							$update->set($k, $value);
						}//end get
						$update->save();
					}//end foreach items
				}//end if xpath

			}//end foreach setup
		}//end foreach ident
	}//end function update

	function items($num=10) {
		global $actionstream_config;
		$items = $actionstream_config['db']->get_results("SELECT created_on,service,setup_idx,data,user_id FROM {$actionstream_config['item_table']} ".($this->user_id ? 'WHERE user_id='.$this->user_id.' ' : '')."ORDER BY created_on DESC", ARRAY_A);
		return $items;
	}//end function items

	function __toString($num=10, $hide_user=false, $permissions=array(), $collapse_off=false) {
		$items = $this->items($num);
		if(!$items || !count($items))
			return 'No items to display in actionstream.';
		$previous_service = false;
		$during_service = '';
		$after_service = array();
		$previous_day = false;
		$c = 0;
		if(count($items) <= $num) $num = count($items);
		foreach($items as $item) {

			if(function_exists('user_is') && !user_is($permissions[$item['service']])) continue;

			if($item['service'] == $previous_service && date(get_option('date_format'),$item['created_on']+get_option('gmt_offset')) == $previous_day) {

				$after_service[] = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

			} else {

				$c++;

				$group_id = 'actionstream-group-'.md5(microtime(true)) . $c;

				if($during_service) {
					$rtrn .= '<li class="hentry service-icon service-'.$previous_service.' '.$group_id.'">'.$during_service->__toString($hide_user);
					if(count($after_service) && !$collapse_off) $rtrn .= ' (and <a href="#" class="block" onclick="actionstream_group_toggle(\''.$group_id.'\', this.className); this.className = this.className == \'block\' ? \'none\' : \'block\'; return false;">'.count($after_service).' more</a>...)';
					$rtrn .= '</li>';
				}//end if during service

				foreach($after_service as $cnt)//not sure if I'm a fan of hiding the user on hidden entries... suggestion came from jangro.com
					$rtrn .= '<li class="hentry service-icon service-'.$previous_service.' '.$group_id. ($collapse_off ? '' : ' actionstream-hidden') . '">'.$cnt->__toString($hide_user).($collapse_off?'':'<script type="text/javascript">actionstream_group_toggle(\''.$group_id.'\', \'none\');</script>') . '</li>';
				$after_service = array();

				if($c > $num) {$rtrn .= '</ul>'; break;}

				if(date(get_option('date_format'),$item['created_on']+get_option('gmt_offset')) != $previous_day) {//new day
					if($previous_day) $rtrn .= '</ul>';
					$previous_day = date(get_option('date_format'),$item['created_on']+get_option('gmt_offset'));
					$rtrn .= '<h3 class="action-stream-header">On '.$previous_day.'</h3>';
					$rtrn .= '<ul class="hfeed action-stream-list">';
				}//end if new day
				$during_service = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

			}//end if-else service
			$previous_service = $item['service'];
		}//end foreach

		$wpurl = get_bloginfo('wpurl');

		$rtrn = <<<JS
<script type="text/javascript">
	function actionstream_group_toggle(id, display) {

		var ua = navigator.userAgent.toLowerCase();
		var isIE = (/msie/.test(ua)) && !(/opera/.test(ua)) && (/win/.test(ua));
	
		if(!isIE) {
			var head = document.getElementsByTagName('head')[0];
			var css = document.createElement('style');
			css.type = 'text/css';
			css.appendChild(document.createTextNode('.'+id+'.actionstream-hidden {display:'+display+';}'));
			head.appendChild(css);
		} else {
      	var last_style_node = document.styleSheets[document.styleSheets.length - 1];
         if (typeof(last_style_node.addRule) == "object") last_style_node.addRule('.'+id+'.actionstream-hidden', 'display:'+display+';');
      }
	}
</script>
$rtrn
<div style="text-align:right;">
	<a href="$wpurl/wp-content/plugins/wp-diso-actionstream/feed.php?user={$this->user_id}" rel="alternate" type="application/rss+xml">
		<img src="$wpurl/wp-content/plugins/wp-diso-actionstream/images/feed.png" alt="ActionStream Feed" />
	</a>
</div>
JS;

		return $rtrn;
	}//end function toString

	static function from_urls($url, $urls) {
		$actionstream_yaml = get_actionstream_config();
		$urls[] = $url;
		$urls = array_unique($urls);
		$ident = array();
		foreach($urls as $url) {
			foreach($actionstream_yaml['profile_services'] as $service => $setup)  {
				$regex = '/'.str_replace('%s', '(.*)', preg_quote($setup['url'],'/')).'?/';
				if(preg_match($regex, $url, $match)) {
					$match[1] = explode('/', $match[1]);
					$match[1] = $match[1][0];
					$ident[$service] = $match[1];
					break;
				}//end if preg_match
			}//echo foreach action_streams
		}//end foreach urls
		return $ident;
	}//end function from_urls

}//end class ActionStream

?>
