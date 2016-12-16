<?php

namespace Dropcart;

/**
 * All exceptions thrown by the client module will inherit from this class. Catching these exceptions thus
 * allows clients to handle all errors.
 * 
 * @license MIT
 */
class ClientException extends \Exception {
	
	/**
	 * An array describing the context of the client the moment the exception occurred. Useful for troubleshooting.
	 */
	public $context;
	
	public function __construct($context = null, $previous = null) {
		parent::__construct("An error occurred", 0, $previous);
		$this->context = $context;
	}
	
}
