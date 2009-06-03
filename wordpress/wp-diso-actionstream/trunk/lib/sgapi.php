<?php

if(!class_exists('SocialGraphApi')) {

if(!class_exists('Services_JSON'))
	require_once dirname(__FILE__).'/JSON.php';
define('SGAPIURL','http://socialgraph.apis.google.com/lookup');

class SocialGraphApi {

	function SocialGraphApi($params) {
		
		// edo 	boolean 	Return edges out from returned nodes. -> edgesout
		// edi 	boolean 	Return edges in to returned nodes. -> edgesin
		// fme 	boolean 	Follow me links, also returning reachable nodes. -> followme
		// sgn 	boolean 	Return internal representation of nodes.
		
		$this->edgesout = isset($params['edgesout']) ? $params['edgesout'] : '0';
		$this->edgesin = isset($params['edgesin']) ? $params['edgesin'] : '0';
		$this->followme = isset($params['followme']) ? $params['followme'] : '0';
		$this->sgn = isset($params['sgn']) ? $params['sgn'] : '0';
		$this->JSON = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
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
		
		$result = wp_remote_fopen(SGAPIURL.'?'.$qs);

		/*$ch = curl_init(SGAPIURL."?$qs");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);*/
		
		if (empty($result)) return null;
		
		try {
			$this->data = $this->JSON->decode($result);
		} catch (Exception $ex) {$this->data = null;}
		return $this->data;
	}
}//end class

}//end if !
