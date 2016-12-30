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
		// Select a random number of products
		$num = rand(1, 5);
		for ($acc = 0; $acc < $num; $acc++) {
			// Select a random product
			$index = rand(0, count($products) - 1);
			// Choose random quantity
			$quantity = (int) (rand(1, 50) / rand(1, 5));
			$bagCode = $client->addShoppingBag($bagCode, $products[$index], $quantity);
		}
	} else {
		die("Example does not work without products for sale.");
	}
	
	// Check the current bag:
	print("<h1>1. Shopping bag contents:</h1>");
	print("<pre>");
	print_r($client->readShoppingBag($bagCode));
	print("</pre>");
	flush();
	
	// Create a transaction
	print("<h1>2. Constructing transaction:</h1>");
	print("<pre>");
	$transaction = $client->createTransaction($bagCode);
	print_r($transaction);
	print("</pre>");
	flush();
	
	if (count($transaction['errors']) == 0) {
		print("<h1>3. Updating transaction:</h1>");
		// Update transaction with customer details
		$details['first_name'] = "My First Name";
		$details['last_name'] = "Example";
		$details['email'] = "m.example@example.com";
		$details['telephone'] = "+316 123 123 45";
		$details['shipping_first_name'] = $details['first_name'];
		$details['shipping_last_name'] = $details['last_name'];
		$details['shipping_address_1'] = "Prof. van der Waalsstraat 1";
		$details['shipping_city'] = "Alkmaar";
		$details['shipping_postcode'] = "1234AB";
		$details['shipping_country'] = "Nederland";
		$details['billing_first_name'] = $details['shipping_first_name'];
		$details['billing_last_name'] = $details['shipping_last_name'];
		$details['billing_address_1'] = $details['shipping_address_1'];
		$details['billing_city'] = $details['shipping_city'];
		$details['billing_postcode'] = $details['shipping_postcode'];
		$details['billing_country'] = $details['shipping_country'];
		print("<pre>");
		$transaction = $client->updateTransaction($bagCode, $transaction['reference'], $transaction['checksum'], $details);
		print_r($transaction);
		print("</pre>");
		flush();
		
		if ($transaction['transaction']['system_status'] == "FINAL") {
			// Perform confirmation
			print("<h1>4. Confirming transaction:</h1>");
			print("<pre>");
			$transaction = $client->confirmTransaction($bagCode, $transaction['reference'], $transaction['checksum']);
			print_r($transaction);
			print("</pre>");
			flush();
		}
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

