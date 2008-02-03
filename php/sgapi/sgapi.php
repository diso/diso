<?php

require 'JSON.php';
define ('APIURL','http://socialgraph.apis.google.com/lookup');
$JSON = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);

class SocialGraphApi {
	function SocialGraphApi($params) {
		// q  	Comma-separated list of URIs.  	Which node(s) in the social graph to query. -> uris
		// edo 	boolean 	Return edges out from returned nodes. -> edgesout
		// edi 	boolean 	Return edges in to returned nodes. -> edgesin
		// fme 	boolean 	Follow me links, also returning reachable nodes. -> followme
		// sgn 	boolean 	Return internal representation of nodes.
		
		$this->edgesout = isset($params['edgesout']) ? $params['edgesout'] : '0';
		$this->edgesin = isset($params['edgesin']) ? $params['edgesin'] : '0';
		$this->followme = isset($params['followme']) ? $params['followme'] : '0';
		$this->sgn = isset($params['sgn']) ? $params['sgn'] : '0';
	}
	
	
	function get($uris) {
		global $JSON;
		// is array? implode else pass
		if (is_array($uris)) {
			$uris = implode(',',$uris);
		}
		if (empty($uris)) return null;
		
		$qs = '';
		$qs .= 'q=' . $uris . 
			   '&edo=' . $this->edgesout .
			   '&edi=' . $this->edgesin .
			   '&fme=' . $this->followme .
			   '&sgn=' . $this->sgn;
		
		$ch = curl_init(APIURL."?$qs");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		
		if (empty($result)) return null;
		
		//TODO: handle errors
		$this->data = $JSON->decode($result);
		return $this->data;
	}
}
