<?php

require_once('XRDS/XRD.php');

/**
 * XRDS-Simple Document.
 *
 * @see http://xrds-simple.net/core/1.0/
 */
class XRDS {

	const XRDS_NS = 'xri://$xrds';
	const XRD_NS = 'xri://$XRD*($v*2.0)';
	const OPENID_NS = 'http://openid.net/xmlns/1.0';
	const SIMPLE_NS = 'http://xrds-simple.net/core/1.0';

	/** XRDS Descriptors */
	public $xrd = array();

	public function __construct() {
	}

	public function getServiceURI($type) {
		if (!is_array($type)) {
			$type = array($type);
		}

		foreach ($this->xrd as $xrd) {
			foreach ($xrd->service as $service) {
				foreach ($type as $t) {
					if (!in_array($t, $service->type)) {
						continue 2;
					}
				}
				return $service->uri[0];
			}
		}

	}


	/* Marshalling / Unmarshalling functions */

	public static function load($file) {
		$dom = new DOMDocument();
		$dom->load($file);
		$xrds_elements = $dom->getElementsByTagName('XRDS');
		
		return self::from_dom($xrds_elements->item(0));
	}

	public static function loadXML($xml) {
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$xrds_elements = $dom->getElementsByTagName('XRDS');
		
		return self::from_dom($xrds_elements->item(0));
	}

	public static function from_dom(DOMElement $dom) {
		$xrds = new XRDS();

		$xrd_elements = $dom->getElementsByTagName('XRD');
		foreach ($xrd_elements as $element) {
			$xrd = XRDS_XRD::from_dom($element);
			$xrds->xrd[] = $xrd;
		}

		return $xrds;
	}

	public function to_dom() {
		$dom = new DOMDocument();
		$xrds = $dom->createElementNS(XRDS::XRDS_NS, 'XRDS');
		$dom->appendChild($xrds);

		$xrds->setAttribute('xmlns:simple', XRDS::SIMPLE_NS);
		$xrds->setAttribute('xmlns:openid', XRDS::OPENID_NS);

		foreach ($this->xrd as $xrd) {
			$xrd_dom = $xrd->to_dom($dom);
			$xrds->appendChild($xrd_dom);
		}

		return $dom;
	}

	public function to_xml() {
		$dom = $this->to_dom();
		return $dom->saveXML();
	}

	public function priority_sort($a, $b) {
		// TODO what happens when there is no priority on one or both of the elements?
		if ($a->priority == $b->priority) return 0;
		if ($a->priority > $b->priority) return 1;
		if ($a->priority < $b->priority) return -1;
	}
}

?>
