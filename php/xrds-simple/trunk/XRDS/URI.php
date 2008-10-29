<?php

/**
 * XRDS URI.
 */
class XRDS_URI {

	/** Priority. */
	public $priority;

	/** URI value. */
	public $uri;

	/** HTTP method. */
	public $http_method;

	public static function from_dom(DOMElement $dom) {
		$uri = new XRDS_URI();

		$uri->priority = $dom->getAttribute('priority');
		$uri->http_method = $dom->getAttributeNS(XRDS::SIMPLE_NS, 'httpMethod');
		$uri->uri = $dom->nodeValue;

		return $uri;
	}

	public function to_dom($dom) {
		$uri = $dom->createElement('URI', $this->uri);

		if ($this->priority) {
			$uri->setAttribute('priority', $this->priority);
		}

		if ($this->http_method) {
			$uri->setAttribute('simple:httpMethod', $this->http_method);
		}

		return $uri;
	}
}

?>
