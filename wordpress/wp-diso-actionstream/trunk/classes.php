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
	 * @var parent
	 */
	protected $parent;

	/**
	 * @var post_id
	 */
	protected $post_id;

	/**
	 * Constructor.
	 *
	 * @param array $data
	 * @param string $service
	 * @param string $setup_idx
	 * @param int $user_id
	 */
	function __construct($data, $service=NULL, $setup_idx=NULL, $user_id=0, $parent=NULL) {
		$this->config = get_actionstream_config();
		$this->service = $service;
		$this->setup_idx = $setup_idx;
		$this->user_id = $user_id;
		$this->parent = $parent;

		if ($data) {
			if ( is_array($data) ) {
				$this->data = $data;
			} elseif ( is_object($data) ) {
				$this->from_post($data);
			} else {
				global $wpdb;
				if(strlen($data) == 40) { //sha1
					$data = $wpdb->get_results('SELECT data,service,setup_idx FROM ' . activity_stream_items_table() . " WHERE identifier_hash='".$wpdb->escape($data)."'", ARRAY_A);
					$this->data = unserialize($data[0]['data']);
					$this->service = $data[0]['service'];
					$this->setup_idx = $data[0]['setup_idx'];
				} else { // wordpress post ID
					$this->from_post(get_post($data));
				}
			}
		} else {
			$this->data = array();
		}
	}

	protected function from_post($post) {
		$this->user_id = $post->post_author;
		$this->post_id = $post->ID;
		$data = array(
			'created_on' => strtotime($post->post_date_gmt . ' UTC'),
			'modified_on' => strtotime($post->post_modified_gmt . ' UTC'),
			'identifier' => $post->guid
			);
		if($post->post_title) $data['title'] = $post->post_title;
		if($post->post_content) {
			$data['description'] = apply_filters('the_content',$post->post_content);
			if(!preg_match('/^\s*<p>/',$post->post_content)) $data['description'] = preg_replace('/^\s*<p>|<\/p>\s*$/','',$data['description']);
		}
		if($post->post_type != 'actionstream') {
			$data['ident'] = get_userdata($post->post_author);
			$data['ident'] = $data['ident']->display_name;
			if($post->post_excerpt) $data['description'] = $post->post_excerpt;
		}
		if($post->post_type == 'actionstream' && $post->post_excerpt) $data['url'] = $post->post_excerpt;
		if(!$data['url']) $data['url'] = get_permalink($post);
		$this->data = array_merge(get_post_custom($post->ID), $data);
		$tags = get_the_tags($post->ID);
		// Find service
		foreach((array)$tags as $tag) {
			if(preg_match('/^service_(.*)$/', $tag->name, $match))
				$this->service = $match[1];
		}
		// Find setup_idx
		foreach((array)$tags as $tag) {
			if(preg_match('/^'.preg_quote($this->service, '/').'_(.*)$/', $tag->name, $match))
				$this->setup_idx = $match[1];
		}
		if(!$this->service) $this->service = 'website';
		if(!$this->setup_idx) $this->setup_idx = 'posted';
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
		$v = $this->data[$k];
		if(!$v && $k == 'service') return $this->service ? $this->service : 'website';
		if(is_array($v) && count($v) < 2) return $v[0];
		return $v;
	}

	/**
	 * Get the Wordpress post_id
	 *
	 * @return numeric
	 */
	function post_id() {
		return $this->post_id;
	}

	/**
	 * Get/set parent post_id
	 *
	 * @params (optional) ID to set parent to
	 * @return numeric
	 */
	function parent($id=NULL) {
		if($id) $this->parent = $id;
		return $this->parent;
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
	 * Determine if specified item is duplicate
	 *
	 * @param ActionStreamItem $b item to compare
	 * @return boolean false if specified item is not a duplicate, or the item it is a duplicate of
	 */
	function is_dupe_of($b) {
		if(is_array($b)) {
			$dupe = false;
			foreach($b as $b) {
				if($dupe = $this->is_dupe_of($b)) {
					break;
				}
			}
			return $dupe;
		}
		if($this->get('service') == $b->get('service')) return false; // From same service are not dupes
		if(!$this->get('created_on') && $this->get('modified_on')) $this->set('created_on', $this->get('modified_on'));
		$created_on = $this->set('created_on', (int)$this->get('created_on') ? (int)$this->get('created_on') : time());
		if(abs($this->get('created_on') - $b->get('created_on')) > 36000) return false; // If they're too far apart, they aren't duplicates
		if($this->identifier() == $b->identifier()) return $b; // duh
		if($this->get('url') == $b->get('url')) return $b; // This seems reasonable, but may not always work out
		if(ActionStreamItem::similar_enough($this->get('title'), $b->get('title'))) {
			return ActionStreamItem::similar_enough($this->get('description'), $b->get('description')) ? $b : false;
		}
		return false;
	}

	/**
	 * Save actionstream item as a WordPress post
	 * WARNING: *does not* save dupes. Do that by creating items with a parent of this item
	 */
	function save_as_post() {
		global $wpdb;
		// Ensure sane defaults
		if(!$this->get('created_on') && $this->get('modified_on')) $this->set('created_on', $this->get('modified_on'));
		$this->set('created_on', (int)$this->get('created_on') ? (int)$this->get('created_on') : time());
		// Build post object
		$post = array(
			'post_modified'     => date('Y-m-d H:i:s', $this->get('modified_on')),
			'post_modified_gmt' => gmdate('Y-m-d H:i:s', $this->get('modified_on')),
			'post_status'       => 'publish',
			'post_type'         => 'actionstream',
			'tags_input'        => 'service_'.$this->service.','.$this->service.'_'.$this->setup_idx,
			'post_author'       => $this->user_id,
			'guid'              => $this->identifier()
			);
		// Check for existing post
		$id = $wpdb->get_row("SELECT ID,post_date,post_date_gmt FROM $wpdb->posts WHERE post_type='actionstream' AND guid='".$wpdb->escape($post['guid'])."' LIMIT 1");
		if($id && $id->ID) {
			$post['ID'] = $id->ID;
			$post['post_date'] = $id->post_date;
			$post['post_date_gmt'] = $id->post_date_gmt;
		} else {
			$post['post_date']         = date('Y-m-d H:i:s', $this->get('created_on'));
			$post['post_date_gmt']     = gmdate('Y-m-d H:i:s', $this->get('created_on'));
		}
		if($this->get('title')) $post['post_title'] = $this->get('title');
		if($this->get('description')) $post['post_content'] = $this->get('description');
		if($this->get('url')) $post['post_excerpt'] = $this->get('url');
		if($this->get('coment_status')) $post['comment_status'] = $this->get('coment_status');
			else $post['comment_status'] = 'open';
		// Handle inserting a dupe
		if($this->parent) $post['post_parent'] = $this->parent;
		// Actually insert the post
		$id = wp_insert_post($post);
		// Update meta keys for this post
		foreach($this->data as $k => $v) {
			if(preg_match('/^title|description|created_on|modified_on|identifier|url|dupes$/', $k)) continue;
			update_post_meta($id, $k, $this->get($k));
		}
		// Return post_ID
		$this->post_id = $id;
		return $id;
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
		if(!$this->service) $this->service = 'website';
		if(!$this->setup_idx) $this->setup_idx = 'posted';
		$string = ActionStreamItem::interpolate(
				$this->to_array(), 
				$this->config['action_streams'][$this->service][$this->setup_idx]['html_params'], 
				$this->config['action_streams'][$this->service][$this->setup_idx]['html_form'], 
				$this->config['profile_services'][$this->service], $hide_user
			);

		if($reply = $this->get('in-reply-to')) {
			foreach((array)$reply as $r) {
				if(preg_match('/https?:\/\/[^\s]+/', $r, $m)) $r = $m[0];
				$string .= ' <a rev="reply" rel="in-reply-to" href="'.htmlspecialchars($r).'">in reply to</a> ';
			}
		}

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

		if ( $data['ident'] ) {
			$pre = '<span class="author vcard" '.($hide_user ? 'style="display:none;"' : '').'>';
			$post = '</span>';
			if($service && $service['url'] && $service['url'] != '{{ident}}') {
				$pre .= '<a class="url fn nickname" href="'
				. htmlspecialchars(str_replace('{{ident}}',$data['ident'],$service['url'])).'">';
				$post = '</a>'.$post;
			} else {
				$pre .= '<span class="fn nickname">';
				$post = '</span>'.$post;
			}
			$data['ident'] = $pre.htmlspecialchars($data['ident']).$post;
		}

		foreach ($fields as $i => $k) {
			if ( $data[$k] == html_entity_decode(strip_tags($data[$k]),ENT_QUOTES) ) {
				$data[$k] = htmlspecialchars($data[$k]);
			}
			$template = str_replace('[_'.($i+1).']', $data[$k], $template);
			$template = str_replace('[%_'.($i+1).']', rawurlencode($data[$k]), $template);
		}

		return $template;
	}

	/**
	 * Determine if strings are similar enough to be considered duplicates
	 *
	 * @param string $a first string to compare
	 * @param string $b second string to compare
	 * @return boolean true if strings are similar
	 */
	protected static function similar_enough($a, $b) {
		$a = substr(strip_tags($a), 0, 255);
		$b = substr(strip_tags($b), 0, 255);
		$avg_length = strlen($a) + strlen($b);
		return levenshtein($a, $b) <= $avg_length / 4;
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
		foreach($this->items(40,true) as $item)
			$saved[] = new ActionStreamItem($item);
		foreach($this->ident as $service => $id) {
			$setup = $this->config['action_streams'][$service];
			if(!is_array($setup)) continue;
			//TODO: HTML/Microformats
			foreach($setup as $setup_idx => $stream) {
				foreach((array)$id as $id) {
					if(is_array($id)) {
						if($id['push']) continue; // Skip feeds we get over push
						$id = $id['ident']; 
					}
					$url = str_replace('{{ident}}', $id, $stream['url']);
					if(!$url) {//feed autodetect
						$profile_url = str_replace('{{ident}}',$id,$this->config['profile_services'][$service]['url']);
						if($stream['scraper']) {
							$url = $profile_url;
						} else {
							$raw = get_raw_actionstream($profile_url);

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
						}
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
									'identifier' => 'id/child::text()',
									'in-reply-to' => 'thr:in-reply-to/@ref'
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
							$doc->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
							$doc->registerXPathNamespace('thr', 'http://purl.org/syndication/thread/1.0');
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
								if(($k == 'created_on' || $k == 'modified_on' || $k == 'dtstart') && !is_numeric($value)) $value = strtotime($value);
								$update->set($k, $value);
							}//end get
							if($dupe_of = $update->is_dupe_of($saved)) {
								$dupe_of->add_dupe($service, $update);
								$dupe_of->save();
								$update->parent($dupe_of->post_id());
							} else {
								$update->save();
								$saved[] = $update;
							}
							$update->save_as_post();
						}//end foreach items
					}//end if xpath
				}//end foreach id
			}//end foreach setup
		}//end foreach ident

		// PuSH if the plugin is installed
		if(function_exists('publish_to_hub')) {
			$feedlink = get_feed_link('action_stream');
			$feedlink .= (strpos($selflink, '?') ? '&' : '?') . 'user=' . $this->user_id;
			$services = array_keys($this->ident);
			sort($services);
			$feeds = array($feedlink, $feedlink.'&full');
			/* This is a cool idea, but I have 16383 subsets of my services... not practical at all
			foreach(ActionStream::subsets($services) as $subset) {
				$include = 'include[]='.implode('include[]=', $subset);
				$exclude = 'exclude[]='.implode('exclude[]=', $subset);
				$feeds = array_merge($feeds, array($feedlink.$include, $feedlink.'&full'.$include, $feedlink.$exclude, $feedlink.'&full'.$exclude));
			}*/
			publish_to_hub(NULL, $feeds);
		}
	}//end function update


	/**
	 * Get items.
	 *
	 * @param int $num number of items to get
	 */
	function items($num=10, $from_posts=false) {
		global $wpdb;
		if($from_posts) {
			$userdata = get_userdata($this->user_id);
			if($userdata->actionstream_local_updates) $extra_type = '&post_type[]=post';
			return get_posts("numberposts=$num&post_type[]=actionstream$extra_type&author=$this->user_id&post_parent=0");
		} else {
			return $wpdb->get_results("SELECT created_on,service,setup_idx,data,user_id 
				FROM " . activity_stream_items_table() . " " . ($this->user_id ? 'WHERE user_id='.$this->user_id.' ' : '')
				. "ORDER BY created_on DESC LIMIT $num", ARRAY_A);
		}
	}


	/**
	 * Get string representation of this activity stream.
	 *
	 * @param int $num
	 * @param boolean $hide_user
	 * @param array $permissions
	 * @param boolean $collapse
	 */
	function toString($num=10, $hide_user=false, $permissions=array(), $collapse=true, $filter=array()) {
		$items = $this->items($collapse ? $num*4 : $num, true);
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
			if(!is_array($item)) {
				$to_push = new ActionStreamItem($item);
				$item = $to_push->to_array();
			} else {
				$to_push = $item;
			}
			if (!array_key_exists($item['service'], $yaml['profile_services'])) continue;
			if(function_exists('diso_user_is') && !diso_user_is($permissions[$item['service']])) continue;
			if($filter['include'] && !in_array($item['service'], (array)$filter['include'])) continue;
			if($filter['exclude'] && in_array($item['service'], (array)$filter['exclude'])) continue;
			if (!$collapse && ($count++ >= $num)) break;

			$current_day = date(get_option('date_format'), $item['created_on']+$gmt_offset);

			if (!array_key_exists($current_day, $sorted_items)) {
				$sorted_items[$current_day] = array();
			}

			if (($item['service'] != $last_service) || empty($sorted_items[$current_day])) {
				if ($collapse && ($count++ >= $num)) break;
				$group = 'as_group-' . $an_id . ++$group_counter;
			}

			$sorted_items["$current_day"][$group][] = $to_push;
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

					if(is_array($item)) {
						$as_item = new ActionStreamItem(unserialize($item['data']), $item['service'], $item['setup_idx'], $item['user_id']);
					} else {
						$as_item = $item;
					}

					$rtrn .= '<li id="post-'.htmlspecialchars($as_item->post_id() ? $as_item->post_id() : sha1($as_item->identifier())).'" class="hentry service-icon service-'.$as_item->get('service').' '.$group_id . '">';
					$rtrn .= "\n\t".$as_item->toString($hide_user);

					if ($first_item) $first_item = false;

					$rtrn .= "\n</li>\n";

				}
				// javascript magic to toggle collapsable items
				if (sizeof($items) > 1 && $collapse) {
					$js .= 'jQuery(".'.$group_id.':not(:first)").hide();
						jQuery(".'.$group_id.':first").show(); // Sometimes they all hide
						jQuery(".'.$group_id.':first")
							.append("(and ")
							.append(jQuery(document.createElement("a"))
								.addClass("expand")
								.attr("href","#")
								.text("'.sizeof($items).' more")
								.click(function() {
									jQuery(".'.$group_id.':not(:first)").toggle();
									jQuery(".'.$group_id.':first").show(); // Sometimes they all hide
									return false;
								}))
							.append(")");';
				}
			}

			$rtrn .= "</ul>\n";
		}

		if($js) $rtrn .= '<script type="text/javascript">'.$js.'</script>';

		$feedlink = get_feed_link('action_stream');
		$feedlink .= (strpos($feedlink, '?') ? '&' : '?') . 'user=' . $this->user_id;
		$rtrn .= '<div style="text-align:right;">
        <a id="actionstream_feed" href="'.clean_url($feedlink).'" rel="alternate" type="application/rss+xml" title="ActionStream Feed">
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
					$ident[$service] = array_unique(array_merge((array)$ident[$service], array($match[1])));
					break;
				}//end if preg_match
			}//echo foreach action_streams
		}//end foreach urls
		return $ident;
	}//end function from_urls

	protected static function append_all($lists, $element) {
		for($i=0; $i<count($lists); $i++) {
			array_push($lists[$i], $element);
		}
		return $lists;
	}

	protected static function choose($a, $len) {
		if($len == 0) return array(array());
		if(!is_array($a) || count($a) == 0) return array();
		$new_element = array_pop($a);
		return array_merge(ActionStream::choose($a, $len), ActionStream::append_all(ActionStream::choose($a, $len-1), $new_element));
	}

	protected static function subsets($a) {
		$r = array();
		for($i=1; $i<=count($a); $i++) {
			$r = array_merge($r, ActionStream::choose($a, $i));
		}
		return $r;
	}

}//end class ActionStream

?>
