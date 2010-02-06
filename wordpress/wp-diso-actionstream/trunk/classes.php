<?php

/**
 * Activity Stream item.
 */
class ActionStreamItem {

	/**
	 * @var array
	 */
	protected $data; 
	
	/**
	 * @var string
	 */
	protected $service;

	/**
	 * @var string
	 */
	protected $setup_idx;

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var int
	 */
	protected $user_id;


	/**
	 * Constructor.
	 *
	 * @param array $data
	 * @param string $service
	 * @param string $setup_idx
	 * @param int $user_id
	 */
	function __construct($data, $service, $setup_idx, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->service = $service;
		$this->setup_idx = $setup_idx;
		$this->user_id = $user_id;

		if ($data) {
			if ( is_array($data) ) {
				$this->data = $data;
			} else {
				global $wpdb;
				$data = $wpdb->get_results('SELECT data,service,setup_idx FROM ' . activity_stream_items_table() . " WHERE identifier_hash='$data'", ARRAY_A);
				$this->data = unserialize($data[0]['data']);
				$this->service = $data[0]['service'];
				$this->setup_idx = $data[0]['setup_idx'];
			}
		} else {
			$this->data = array();
		}
	}


	/**
	 * Record the specified item as a duplicate.
	 *
	 * @param string $service
	 * @param ActionStreamItem $item item to record as a duplicate
	 */
	function add_dupe($service, $item) {
		$dupes = (array) $this->get('dupes');
		$dupes[$service] = $item->to_array();
		$this->set('dupes', $dupes);
	}


	/**
	 * Set a data value.
	 *
	 * @param string $k key of data
	 * @param mixed $v value of data
	 */
	function set($k, $v) {
		return $this->data[$k] = $v;
	}


	/**
	 * Get a data value.
	 *
	 * @param string $k key of data to get 
	 * @return mixed data value
	 */
	function get($k) {
		return $this->data[$k];
	}


	/**
	 * Get an array representation of this item.
	 *
	 * @return array
	 */
	function to_array() {
		$data = array(
			'service' => $this->service,
			'setup_idx' => $this->setup_idx,
			);
		foreach($this->data as $k => $v) {
			$data[$k] = $this->get($k);
		}
		return $data;
	}


	/**
	 * Get identifier for this item
	 *
	 * @return string
	 */
	function identifier() {
		$identifier_field = $this->config['action_streams'][$this->service][$this->setup_idx]['identifier'];

		if ( !$identifier_field ) {
			$identifier_field = 'identifier';
		}

		if ( !$this->data[$identifier_field] ) {
			return $this->get('created_on') . $this->service;
		}

		return $this->get($identifier_field);
	}


	/**
	 * Determine if strings are similar enough to be considered duplicates
	 *
	 * @param string $a first string to compare
	 * @param string $b second string to compare
	 * @return boolean true if strings are similar
	 */
	function similar_enough($a, $b) {
		$a = substr(strip_tags($a), 0, 255);
		$b = substr(strip_tags($b), 0, 255);
		$avg_length = strlen($a) + strlen($b);
		return levenshtein($a, $b) <= $avg_length / 4;
	}


	/**
	 * Determine if specified item is duplicate
	 *
	 * @param ActionStreamItem $b item to compare
	 * @return boolean true if specified item is a duplicate
	 */
	function is_dupe_of($b) {
		if(!$this->get('created_on') && $this->get('modified_on')) $this->set('created_on', $this->get('modified_on'));
		$created_on = $this->set('created_on', (int)$this->get('created_on') ? (int)$this->get('created_on') : time());
		if(abs($this->get('created_on') - $b->get('created_on')) > 36000) return false; // If they're too far apart, they aren't duplicates
		if($this->identifier() == $b->identifier()) return true; // duh
		if($this->get('url') == $b->get('url')) return true; // This seems reasonable, but may not always work out
		if($this->similar_enough($this->get('title'), $b->get('title'))) {
			return $this->similar_enough($this->get('description'), $b->get('description'));
		}
		return false;
	}


	/**
	 * Save this ActionStreamItem to the database.
	 */
	function save() {
		global $wpdb;
		if(!$this->get('created_on') && $this->get('modified_on')) $this->set('created_on', $this->get('modified_on'));
		$created_on = $this->set('created_on', (int)$this->get('created_on') ? (int)$this->get('created_on') : time());
		$data = $wpdb->escape(serialize($this->data));
		$identifier_hash = sha1($this->identifier());
		$wpdb->query("INSERT INTO " . activity_stream_items_table() . " 
			(identifier_hash, user_id, created_on, service, setup_idx, data) 
			VALUES ('$identifier_hash', $this->user_id, $created_on, '$this->service', '$this->setup_idx', '$data') 
			ON DUPLICATE KEY UPDATE data='$data'");
	}


	/**
	 * Get string representation of this item.
	 *
	 * @param boolean $hide_user
	 * @return string
	 */
	function toString($hide_user=false) {
		$string = ActionStreamItem::interpolate(
				$this->to_array(), 
				$this->config['action_streams'][$this->service][$this->setup_idx]['html_params'], 
				$this->config['action_streams'][$this->service][$this->setup_idx]['html_form'], 
				$this->config['profile_services'][$this->service], $hide_user
			);

		$string .= sprintf(' <abbr class="published" title="%s">@ %s %s</abbr>',
			date('c',$this->get('created_on')),
			date(get_option('date_format'),$this->get('created_on')),
			date(get_option('time_format'),$this->get('created_on'))
		);

		return $string;
	}

	/**
	 * Alias for PHP default magic
	 *
	 * @return string
	 */
	function __toString() {
		return $this->toString();
	}

	/**
	 * Interpolate
	 *
	 * @param $data
	 * @param $fields
	 * @param $template
	 * @param $service
	 * @param $hide_user
	 */
	protected static function interpolate($data, $fields, $template, $service, $hide_user) {
		if ( !is_array($fields) ) return;
		array_unshift($fields, 'ident');

		if ( $data['ident'] && $service ) {
			$data['ident'] = '<span class="author vcard" '.($hide_user ? 'style="display:none;"' : '')
				. '><a class="url fn nickname" href="' 
				. htmlspecialchars(str_replace('{{ident}}',$data['ident'],$service['url'])).'">'
				. htmlspecialchars($data['ident']).'</a></span>';
		}

		foreach ($fields as $i => $k) {
			if ( $data[$k] == html_entity_decode(strip_tags($data[$k])) ) {
				$data[$k] = htmlspecialchars($data[$k]);
			}
			$template = str_replace('[_'.($i+1).']', $data[$k], $template);
			$template = str_replace('[%_'.($i+1).']', rawurlencode($data[$k]), $template);
		}

		return $template;
	}

}//end class ActionStreamItem



/**
 * ActionStream class
 */
class ActionStream {

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var string
	 */
	protected $ident;

	/**
	 * @var int
	 */
	protected $user_id;

	/**
	 * Constructor
	 *
	 * @param string $ident
	 * @param int $user_id
	 */
	function __construct($ident, $user_id=0) {
		$this->config = get_actionstream_config();
		$this->ident = $ident;
		$this->user_id = $user_id;
	}


	/**
	 * Update this ActionStream
	 */
	function update() {
		$saved = array();
		foreach($this->ident as $service => $id) {
			$setup = $this->config['action_streams'][$service];
			if(!is_array($setup)) continue;
			//TODO: HTML/Microformats
			foreach($setup as $setup_idx => $stream) {
				$url = str_replace('{{ident}}', $id, $stream['url']);
				if(!$url) {//feed autodetect
					$raw = get_raw_actionstream(str_replace('{{ident}}',$id,$this->config['profile_services'][$service]['url']));

					preg_match('/<[\s]*link.+\/atom\+xml[^\f]+?href="(.+)"/', $raw, $match);
					$aurl = html_entity_decode($match[1]);

					preg_match('/<[\s]*link.+\/rss\+xml[^\f]+?href="(.+)"/', $raw, $match);
					$rurl = html_entity_decode($match[1]);

					if(($stream['atom'] && $aurl) || !$rurl) {
						$url = $aurl;
						if(!$stream['atom']) $stream['atom'] = array();
					} else {
						$url = $rurl;
						if(!$stream['rss']) $stream['rss'] = array();
					}//end if-else atom/rss
					if(!$url) continue;
				}//end if ! url
				$raw = get_raw_actionstream($url);
				if(!$raw) continue;

				if(isset($stream['atom'])) {
					if(!is_array($stream['atom'])) $stream['atom'] = array();
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

				if(isset($stream['rss'])) {
					if(!is_array($stream['rss'])) $stream['rss'] = array();
					$stream['xpath'] = array(
							'foreach' => '//item',
							'get' => array_merge(array(
								'created_on' => 'pubDate/child::text()',
								'title' => 'title/child::text()',
								'url' => 'link/child::text()',
								'identifier' => 'guid/child::text()'
							), $stream['rss'])
					);
				}//end if atom

				if($stream['xpath']) {
					unset($items);
					@$doc = simplexml_load_string(str_replace('xmlns=','a=',$raw), 'SimpleXMLElement', LIBXML_NOCDATA);
					if($doc && method_exists($doc, 'registerXPathNamespace')) {
						$doc->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
						$doc->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
						$doc->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
					}
					if($doc && $stream['xpath']['foreach']) {
						$stream['xpath']['foreach'] = str_replace('%s', $id, $stream['xpath']['foreach']);
						$items = $doc->xpath($stream['xpath']['foreach']);
					}
					if(!$items) $items = array();

					if(!is_array($stream['xpath']['get'])) {//DEBUG: this should never happen
						echo '<p>Invalid definition of '.$service;
						echo '<pre>';
						var_dump($stream);
						echo '</pre>';
						'</p>';
						continue;
					}
					foreach($items as $item) {
						$update = new ActionStreamItem(array('ident' => $id), $service, $setup_idx, $this->user_id);
						foreach($stream['xpath']['get'] as $k => $p) {
							@$value = $item->xpath($p);//TEMP
							$value = $value[0].'';
							if($service == 'twitter') {
								$value = preg_replace('/^'.$id.'\: /','',$value);
								if ($k == 'description') {
									$value = preg_replace('/(http:\/\/[a-z0-9_%\/\.+-]+)/i','<a href="$1">$1</a>', $value);
								}
								$value = preg_replace('/@([a-zA-z0-9_]+)/','<span class="reply vcard tag">@<a class="url fn" href="http://twitter.com/$1">$1</a></span>',$value);
								$value = preg_replace('/#([a-zA-z0-9_]+)/','#<a href="http://hashtags.org/tag/$1" rel="tag">$1</a>',$value);
							}//end if twitter
							if($service == 'backtype' && $k == 'description') {
								$value = preg_replace('/<p><a href="http:\/\/www\.backtype\.com\/.*?">Read more comments by .*?<\/a><\/p>/','',$value);
								$value = str_replace('<br>','<br />',$value);
							}
							if(($k == 'created_on' || $k == 'modified_on') && !is_numeric($value)) $value = strtotime($value);
							$update->set($k, $value);
						}//end get
						$dupe_of = false;
						foreach($saved as $i) {
							if($service != $i->get('service')) {
								if($update->is_dupe_of($i)) {
									$dupe_of = $i;
									break;
								}
							}
						}
						if($dupe_of) {
							$dupe_of->add_dupe($service, $update);
							$dupe_of->save();
						} else {
							$update->save();
							$saved[] = $update;
						}
					}//end foreach items
				}//end if xpath

			}//end foreach setup
		}//end foreach ident
	}//end function update


	/**
	 * Get items.
	 *
	 * @param int $num number of items to get
	 */
	function items($num=10) {
		global $wpdb;
		$items = $wpdb->get_results("SELECT created_on,service,setup_idx,data,user_id 
			FROM " . activity_stream_items_table() . " " . ($this->user_id ? 'WHERE user_id='.$this->user_id.' ' : '')
			. "ORDER BY created_on DESC", ARRAY_A);
		return $items;
	}


	/**
	 * Get string representation of this activity stream.
	 *
	 * @param int $num
	 * @param boolean $hide_user
	 * @param array $permissions
	 * @param boolean $collapse
	 */
	function toString($num=10, $hide_user=false, $permissions=array(), $collapse=true) {
		$items = $this->items($num);
		if(!$items || !count($items)) {
			return 'No items to display in actionstream.';
		}

		$sorted_items = array();
		$last_service;
		$group;
		$group_counter = 0;
		$an_id = md5(microtime(true));//in case there are multiple actionstreams on a page
		$gmt_offset = get_option('gmt_offset') * 3600;
		$yaml = get_actionstream_config();
		$count = 0;

		// build sorted_items array
		foreach ($items as $item) {
			if (!array_key_exists($item['service'], $yaml['profile_services'])) continue;
			if(function_exists('diso_user_is') && !diso_user_is($permissions[$item['service']])) continue;
			if (!$collapse && ($count++ >= $num)) break;

			$current_day = date(get_option('date_format'), $item['created_on']+$gmt_offset);

			if (!array_key_exists($current_day, $sorted_items)) {
				$sorted_items[$current_day] = array();
			}

			if (($item['service'] != $last_service) || empty($sorted_items[$current_day])) {
				if ($collapse && ($count++ >= $num)) break;
				$group = 'as_group-' . $an_id . ++$group_counter;
			}

			$sorted_items["$current_day"][$group][] = $item;
			$last_service = $item['service'];
		}

		// walk sorted_items array and build output string
		foreach ($sorted_items as $day => $group) {
			if (empty($group)) continue;

			$rtrn .= '<h3 class="action-stream-header">On '.$day.'</h3>';
			$rtrn .= '<ul class="hfeed action-stream-list">';

			foreach ($group as $group_id => $items) {
				$first_item = true;
				foreach ($items as $item) {

					$as_item = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);

					$rtrn .= '<li id="as-'.htmlspecialchars(sha1($as_item->identifier())).'" class="hentry service-icon service-'.$item['service'].' '.$group_id . '">';
					$rtrn .= "\n\t".$as_item->toString($hide_user);

					// javascript magic to toggle collapsable items
					if (sizeof($items) > 1 && $collapse) {
						if ($first_item) {
							$rtrn .= ' (and <a class="expand" href="#">'.(count($items) - 1).' more &hellip;</a>)';
							$rtrn .= '<script type="text/javascript">jQuery(function() {
								jQuery(".'.$group_id.':not(:first)").hide();
								jQuery(".'.$group_id.':first a.expand").click(function() {
									jQuery(".'.$group_id.':not(:first)").toggle();
									return false;
								})
							});</script>';
							$first_item = false;
						}
					}

					$rtrn .= "\n</li>\n";

				}
			}

			$rtrn .= "</ul>\n";
		}


		$feedlink = get_feed_link('action_stream');
		$feedlink .= (strpos($feedlink, '?') ? '&' : '?') . 'user=' . $this->user_id;
		$rtrn .= '<div style="text-align:right;">
        <a id="actionstream_feed" href="'.clean_url($feedlink).'" rel="alternate" type="application/rss+xml">
                <img src="'.clean_url(plugins_url('wp-diso-actionstream/images/feed.png')).'" alt="ActionStream Feed" />
        </a>
		</div>';

		return $rtrn;
	}//end function toString


	/**
	 * TODO
	 *
	 * @param string $url
	 * @param array $urls
	 */
	static function from_urls($url, $urls) {
		$actionstream_yaml = get_actionstream_config();
		$urls[] = $url;
		$urls = array_unique($urls);
		$ident = array();
		foreach($urls as $url) {
			foreach($actionstream_yaml['profile_services'] as $service => $setup)  {
				$regex = '/'.str_replace('{{ident}}', '(.*)', preg_quote($setup['url'],'/')).'?/';
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
