<?php

// Authenticate with Dropcart. KEEP YOUR API KEY A SECRET!

use Dropcart\Client;

$client = new Client();
$client->auth("<insert your API key here>");
$list = $client->getProductListing();

