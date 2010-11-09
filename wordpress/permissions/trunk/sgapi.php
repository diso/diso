<?php

if(!function_exists('json_decode')) {
	function json_decode($json, $assoc=false) {
		if(!$assoc) throw 'This is just a wrapper around JSON.php';
		require_once 'JSON.php';
		$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		return $json->decode($json);
	}
}

/**
 * Socaial Graph API.
 *
 * @see http://code.google.com/apis/socialgraph/docs/api.html
 */
class SocialGraphApi {

	/** API URL */
	var $api_url = 'http://socialgraph.apis.google.com/lookup';

	/** boolean - Return edges out from returned nodes. */
	var $edges_out;

	/** boolean - Return edges in to returned nodes. */
	var $edges_in;

	/** boolean - Follow me links, also returning reachable nodes. */
	var $follow_me;

	/** boolean - Return internal representation of nodes. */
	var $sgn;


	/**
	 * Constructor.
	 *
	 * @param Array optional parameters.
	 */
	function SocialGraphApi($params) {
		$this->edges_out = isset($params['edgesout']) ? $params['edgesout'] : '0';
		$this->edges_in = isset($params['edgesin']) ? $params['edgesin'] : '0';
		$this->follow_me = isset($params['followme']) ? $params['followme'] : '0';
		$this->sgn = isset($params['sgn']) ? $params['sgn'] : '0';
	}
	

	/**
	 * Get the Social Graph data for the specified URIs.
	 *
	 * @param mixed $uris URIs to lookup
	 * @return Array social graph data
	 */
	function get($uris) {
		// is array? implode else pass
		if (is_array($uris)) {
			$uris = implode(',',$uris);
		}
		if (empty($uris)) return null;
		
		$qs = '';
		$qs .= 'q=' . $uris . 
			   '&edo=' . $this->edges_out .
			   '&edi=' . $this->edges_in .
			   '&fme=' . $this->follow_me .
			   '&sgn=' . $this->sgn;
		
		$get = $this->api_url."?$qs";

		if(function_exists('curl_init')) {
			$ch = curl_init($get);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$result = curl_exec($ch);
			curl_close($ch);
		} else {
			$result = file_get_contents($get);
		}
		
		if (empty($result)) return null;
		
		//TODO: handle errors
		$this->data = json_decode($result, true);
		return $this->data;
	}


	/**
	 * Get all the nodes claimed by the specified URIs.
	 *
	 * @param mixed $uris URIs to lookup
	 * @return Array claimed nodes
	 */
	public static function get_claimed($uris) {
		$sgapi = new SocialGraphApi(array('followme'=>1));
		$data = $sgapi->get($uris);
		$canonical = array_unique(array_values($data['canonical_mapping']));

		$claimed = array();
		foreach ($canonical as $c) {
			$claimed = array_merge($claimed, $data['nodes'][$c]['claimed_nodes']);
		}

		return array_unique(array_merge($canonical, $claimed));
	}


	/**
	 * Get all the verified equivalent nodes.  These are nodes that have a 
	 * bi-directional "me" relationship with one of the specified URIs.
	 *
	 * @param mixed $uris URIs to lookup
	 * @return Array equivalent nodes
	 */
	public static function get_equivalent($uris) {
		$sgapi = new SocialGraphApi(array('followme'=>1));
		$data = $sgapi->get($uris);
		$canonical = array_unique(array_values($data['canonical_mapping']));

		$claimed = array();
		foreach ($canonical as $c) {
			$claimed = array_merge($claimed, 
				$data['nodes'][$c]['claimed_nodes']);
		}

		$verified = array();
		foreach ($claimed as $c) {
			foreach ($data['nodes'][$c]['claimed_nodes'] as $node) {
				if (in_array($node, $canonical)) {
					$verified[] = $c;
					continue 2;
				}
			}
		}

		return array_unique(array_merge($canonical, $verified));
	}

}
