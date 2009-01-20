<?php

/**
 * XRDS URI.
 */
class XRDS_LocalID {

	/** Priority. */
	public $priority;

	/** URI value. */
	public $uri;

	/**
	 * When converted to string, simply return the URI.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->uri;
	}

	/**
	 * Create an XRDS_LocalID object from a DOMElement.
	 *
	 * @param DOMElement $dom DOM element to load
	 * @return XRDS_LocalID object
	 */
	public static function from_dom(DOMElement $dom) {
		$local_id = new XRDS_LocalID();

		$local_id->priority = $dom->getAttribute('priority');
		$local_id->uri = $dom->nodeValue;

		return $local_id;
	}

	/**
	 * Create a a DOMDocument from this XRDS_LocalID object.
	 *
	 * @return DOMDocument
	 */
	public function to_dom($dom) {
		$local_id = $dom->createElement('LocalID', $this->uri);

		if ($this->priority) {
			$local_id->setAttribute('priority', $this->priority);
		}

		return $local_id;
	}
}

?>
