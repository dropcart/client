<?php

require_once "../vendor/autoload.php";

use Dropcart\Client;

$id = "<insert your store identifier here>";
$key = "<insery your API key here>"; // KEEP YOUR API KEY SECRET!

// (This is only used for testing the API, will be removed in the future)
$key = "e5312706be8ae2aba08dcd1b3fb9274a438afdee4cdfe074ff3287c93038ee72";
Client::setEndpoint("http://api.staging.dropcart.nl");

try {

// Create a new client object.
$client = new Client();

// Authenticate with Dropcart API server. The public API key can be found in your account details.
$client->auth($key);

// Retrieve a list of active products that are for sale. You can manage these products within the Dropcart management panel.
$list = $client->getProductListing();
var_dump($list);

} catch (Exception $e) {
var_dump($e);
}
