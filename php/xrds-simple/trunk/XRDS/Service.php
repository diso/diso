<?php

/**
 * XRDS Service.
 */
class XRDS_Service {

	/** Priority. */
	public $priority;

	/** Types */
	public $type = array();

	/** Media types */
	public $media_type = array();

	/** URIs */
	public $uri = array();

	/** Local IDs */
	public $local_id = array();

	/** Required Ssupport */
	public $must_support = array();

	/**
	 * Create an XRDS_Service object from a DOMElement.
	 *
	 * @param DOMElement $dom DOM element to load
	 * @return XRDS_Service object
	 */
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
		usort($service->local_id, array('XRDS', 'priority_sort'));

		$elements = $dom->getElementsByTagNameNS(XRDS::SIMPLE_NS, 'MustSupport');
		foreach ($elements as $e) {
			$service->must_support[] = $e->nodeValue;
		}

		return $service;
	}

	/**
	 * Create a a DOMDocument from this XRDS_Service object.
	 *
	 * @return DOMDocument
	 */
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
