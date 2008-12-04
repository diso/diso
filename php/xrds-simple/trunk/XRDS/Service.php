<?php

/**
 * XRDS Service.
 */
class XRDS_Service {

	public $priority;

	public $type = array();

	public $media_type = array();

	public $uri = array();

	public $local_id = array();

	public $must_support = array();

	public static function from_dom(DOMElement $dom) {
		$service = new XRDS_Service();

		$service->priority = $dom->getAttribute('priority');

		$elements = $dom->getElementsByTagName('Type');
		foreach ($elements as $e) {
			$service->type[] = $e->nodeValue;
		}

		$elements = $dom->getElementsByTagName('MediaType');
		foreach ($elements as $e) {
			$service->media_type[] = $e->nodeValue;
		}

		$elements = $dom->getElementsByTagName('URI');
		foreach ($elements as $e) {
			$uri = XRDS_URI::from_dom($e);
			$service->uri[] = $uri;
		}
		usort($service->uri, array('XRDS', 'priority_sort'));

		$elements = $dom->getElementsByTagName('LocalID');
		foreach ($elements as $e) {
			$local_id = XRDS_LocalID::from_dom($e);
			$service->local_id[] = $local_id;
		}

		$elements = $dom->getElementsByTagNameNS(XRDS::SIMPLE_NS, 'MustSupport');
		foreach ($elements as $e) {
			$service->must_support[] = $e->nodeValue;
		}

		return $service;
	}

	public function to_dom($dom) {
		$service = $dom->createElement('Service');

		if ($this->priority) {
			$service->setAttribute('priority', $this->priority);
		}

		foreach ($this->type as $type) {
			$type_dom = $dom->createElement('Type', $type);
			$service->appendChild($type_dom);
		}

		foreach ($this->media_type as $type) {
			$type_dom = $dom->createElement('MediaType', $type);
			$service->appendChild($type_dom);
		}

		foreach ($this->uri as $uri) {
			$uri_dom = $uri->to_dom($dom);
			$service->appendChild($uri_dom);
		}

		foreach ($this->local_id as $local_id) {
			$id_dom = $local_id->to_dom($dom);
			$service->appendChild($id_dom);
		}

		foreach ($this->must_support as $support) {
			$support_dom = $dom->createElement('simple:MustSupport', $support);
			$service->appendChild($support_dom);
		}

		return $service;
	}
}

?>
