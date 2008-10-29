<?php

/**
 * XRDS URI.
 */
class XRDS_LocalID {

	/** Priority. */
	public $priority;

	/** URI value. */
	public $uri;

	public static function from_dom(DOMElement $dom) {
		$local_id = new XRDS_LocalID();

		$local_id->priority = $dom->getAttribute('priority');
		$local_id->uri = $dom->nodeValue;

		return $local_id;
	}

	public function to_dom($dom) {
		$local_id = $dom->createElement('LocalID', $this->uri);

		if ($this->priority) {
			$local_id->setAttribute('priority', $this->priority);
		}

		return $local_id;
	}
}

?>
