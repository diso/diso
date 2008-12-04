<?php

require_once('XRDS/URI.php');
require_once('XRDS/LocalID.php');
require_once('XRDS/Service.php');

/**
 * XRDS Descriptor.
 */
class XRDS_XRD {

	/** ID of XRDS Descriptor. */
	public $id;

	/** XRDS Version. */
	public $version;

	/** Types. */
	public $type = array();
	
	/** Expiration date for this descriptor. */
	public $expires;

	/** Services. */
	public $service = array();

	public static function from_dom(DOMElement $dom) {
		$xrd = new XRDS_XRD();

		$xrd->version = $dom->getAttribute('version');
		$xrd->id = $dom->getAttribute('xml:id');

		$services = array();

		foreach ($dom->childNodes as $node) {
			switch($node->tagName) {
				case 'Type':
					$xrd->type[] = $node->nodeValue;
					break;

				case 'Expires':
					$xrd->expires = $node->nodeValue;
					break;

				case 'Service':
					$service = XRDS_Service::from_dom($node);
					$xrd->service[] = $service;
					break;
			}
		}

		usort($xrd->service, array('XRDS', 'priority_sort'));

		return $xrd;
	}

	public function to_dom($dom) {
		$xrd = $dom->createElementNS(XRDS::XRD_NS, 'XRD');

		if ($this->id) {
			$xrd->setAttribute('xml:id', $this->id);
		}

		if ($this->version) {
			$xrd->setAttribute('version', $this->version);
		}

		if ($this->expires) {
			$expires_dom = $xrd->createElement('Expires', $expires);
			$xrd->appendChild($expires_dom);
		}

		foreach ($this->type as $type) {
			$type_dom = $dom->createElement('Type', $type);
			$xrd->appendChild($type_dom);
		}

		foreach ($this->service as $service) {
			$service_dom = $service->to_dom($dom);
			$xrd->appendChild($service_dom);
		}

		return $xrd;
	}

}

?>
