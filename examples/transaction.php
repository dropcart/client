<?php

require_once "../vendor/autoload.php";

use Dropcart\Client;
use Dropcart\ClientException;

$key = "<insery your API key here>"; // KEEP YOUR API KEY SECRET!

// (This is only used for testing the API, will be removed in the future)
$key = "e5312706be8ae2aba08dcd1b3fb9274a438afdee4cdfe074ff3287c93038ee72";
Client::setEndpoint("http://api.dropcart.dev");

try {

	// Create a new client object.
	$client = new Client();

	// Authenticate with Dropcart API server. The public API key can be found in your account details.
	// You must also supply the country of origin here.
	$client->auth($key, 'NL');

	// Retrieve a list of active products that are for sale. You can manage these products within the Dropcart management panel.
	// If you do not supply a category, the top-most category is used by default.
	$products = $client->getProductListing();
	
	// Construct a shopping bag by choosing adding the first two results (if any)
	// NOTE: The bag code contents is an internal representation and may change between client versions.
	$bagCode = ""; // empty string is empty shopping bag
	
	if (count($products) >= 1) {
		$bagCode = $client->addShoppingBag($bagCode, $products[0]); // Add one product
	} else {
		die("Example does not work without products for sale.");
	}
	if (count($products) >= 2) {
		$bagCode = $client->addShoppingBag($bagCode, $products[1]); // Add another product
	}
	
	// Check the current bag:
	print("<h1>1. Shopping bag contents:</h1>");
	print("<pre>");
	print_r($client->readShoppingBag($bagCode));
	print("</pre>");
	
	// Create a transaction
	print("<h1>2. Constructing transaction:</h1>");
	print("<pre>");
	$transaction = $client->createTransaction($bagCode);
	print_r($transaction);
	print("</pre>");

} catch (ClientException $e) {
	// Always catch exceptions of type ClientException
	// The context value contains useful information for troubleshooting.
	print("<h1>");
	print($e->getMessage());
	print("</h1>");
	print("<pre>");
	var_dump($e->context);
	print("</pre>");
}

