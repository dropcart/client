<?php

// Authenticate with Dropcart. KEEP YOUR API KEY A SECRET!

require_once "../vendor/autoload.php";

use Dropcart\Client;

// This is only used for testing the API
Client::setEndpoint("http://api.staging.dropcart.nl");

// Create a new client object.
$client = new Client();
// Authenticate with Dropcart API server. The public API key can be found in your account details.
$client->auth("<insert your store identifier here>", "<insert your API key here>");
// Retrieve a list of active products that are for sale. You can manage these products within the Dropcart management panel.
$list = $client->getProductListing();

