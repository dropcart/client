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
	
	// Retrieve a list of all categories. These can be managed within the Dropcart management panel.
	$categories = $client->getCategories();
	print("<h1>Categories</h1>");
	print("<pre>");
	print_r($categories);
	print("</pre>");
	flush();
	
	// Retrieve a list of active products that are for sale. You can manage these products within the Dropcart management panel.
	// If you do not supply a category, the top-most category is used by default.
	$products = $client->getProductListing();
	print("<h1>Product listing</h1>");
	print("<pre>");
	print_r($products);
	print("</pre>");
	flush();
	
	foreach ($products as $product) {
		$product = $client->getProductInfo($product);
		print("<h1>Individual product</h1>");
		print("<pre>");
		print_r($product);
		print("</pre>");
		flush();
	}

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
