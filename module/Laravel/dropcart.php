<?php

/**
 * The configuration file for DropCart in an Laravel or Lumen application
 *
 * @author     DropCart <info@dropcart.nl>
 * @copyright  2017  DropCart
 * @license    https://creativecommons.org/licenses/by-nc-nd/3.0/legalcode.txt  CreativeCommons by-nc-nd-3.0
 */

return [

	'key' 		=> env('DROPCART_KEY', ''),
	'secret'	=> env('DROPCART_SECRET', ''),
	'country'	=> env('DROPCART_COUNTRY', 'NL'),
	'endpoint'	=> env('DROPCART_ENDPOINT', 'https://api.dropcart.nl'),

];