<?php

require_once "../vendor/autoload.php";

use Dropcart\Client;
use Dropcart\ClientException;

$key = "<insery your API key here>"; // KEEP YOUR API KEY SECRET!

// (This is only used for testing the API, will be removed in the future)
$key = "e5312706be8ae2aba08dcd1b3fb9274a438afdee4cdfe074ff3287c93038ee72";
Client::setEndpoint("http://api.dropcart.dev");

// Input handling
$has_input = false;
if (isset($_POST['query'])) {
	$has_input = true;
} else {
	$_POST['query'] = null;
}

try {

	// Create a new client object.
	$client = new Client();

	// Authenticate with Dropcart API server. The public API key can be found in your account details.
	// You must also supply the country of origin here.
	$client->auth($key, 'NL');

	// Perform a search based on user input.
	if ($has_input) {
		$products = $client->findProductListing($_POST['query']);
	} else {
		$products = "No search results yet";
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
	return;
}

?>
<html>
<body>
<form action="search_products.php" method="POST">
<label for="query">Query:</label> <input type="text" id="query" name="query" value="<?php htmlspecialchars($_POST['query']) ?>" /><br />
<button type="submit">Search now...</button>
</form>
<hr />
<pre>
<?php

var_dump($products);

?>
</pre>
</body>
</html>