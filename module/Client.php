<?php

namespace Dropcart;

use Psr\Http\Message\RequestInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

/**
 * Dropcart client access object
 * 
 * <p>
 * The Dropcart Client class represents a stateful connection with the Dropcart API server. Each time you construct an instance,
 * the client must authenticate using a private key. Every method call blocks to perform an HTTP request to the Dropcart servers.
 * </p> 
 * 
 * @license MIT
 */
class Client {
	
	private static $g_instance = null;
	
	private static $g_endpoint_url = "https://api.live.dropcart.nl";
	private static $g_timeout = 60.0;
	private static $g_connect_timeout = 30.0;
	private static $g_customer_fields = ["first_name", "last_name", "email", "telephone", "shipping_first_name",
			"shipping_last_name", "shipping_company", "shipping_address_1", "shipping_address_2", "shipping_city",
			"shipping_postcode", "shipping_country", "billing_first_name", "billing_last_name", "billing_company",
			"billing_address_1", "billing_address_2", "billing_city", "billing_postcode", "billing_country"];
	
	private $context = [];
	
	private function findUrl($suffix, $postfix = "", $query = []) {
		$result = Client::$g_endpoint_url . "/v2/" . $suffix . $postfix;
		$result .= "?country=" . urlencode($this->country);
		foreach ($query as $key => $value) {
			$result .= "&" . urlencode($key) . "=" . urlencode($value);
		}
		$this->context[] = ['url' => $result];
		return $result;
	}
	
	private $client;
	
	private $public_key = null;
	private $private_key = null;
	private $country = null;
	
	private function authHeaderMiddleware() {
		$that = $this;
		return function (callable $handler) use ($that) {
			return function (RequestInterface $request, array $options) use ($handler, $that) {
				$token = [ 'iss' => $that->public_key, 'iat' => time() ];
				$jwt = JWT::encode($token, $that->private_key);
				$request = $request->withHeader("Authorization", "Bearer " . $jwt);
				return $handler($request, $options);
			};
		};
	}
	
	private function checkResult($response) {
		$code = $response->getStatusCode();
		$this->context[] = ['code' => $code, 'body' => (string) $response->getBody()];
		if ($code == 200 || $code == 201) {
			return;
		}
		throw new ClientException("Server responded with an error");
	}
	
	/**
	 * Changes the Dropcart Endpoint URL.
	 * 
	 * <p>
	 * Modify the endpoint URL to which only NEW INSTANCE will use to connect to. All existing client objects
	 * remain the use the previous endpoint URL. The default value for this method is: `https://api.dropcart.nl`.
	 * </p>
	 * 
	 * <p>
	 * The parameter needs to be a valid URL WITHOUT trailing slash. This method does not perform any validation
	 * on the supplied argument, and failing to set a correct URL will throw errors during the lifetime of a Client object.
	 * </p>
	 * 
	 * @param string $url
	 */
	public static function setEndpoint($url)
	{
		Client::$g_endpoint_url = $url;
	}
	
	/**
	 * Single shared instance of a client.
	 */
	public static function instance()
	{
		if (!Client::$g_instance) {
			Client::$g_instance = new Client();
		}
		return Client::$g_instance;
	}
	
	/**
	 * Constructs a new client instance.
	 * 
	 * <p>
	 * Each client will maintain a connection with the Dropcart API server. Only upon initialization of a new
	 * instance will the globally set endpoint URL be read.
	 * </p>
	 * 
	 * @see Client::setEndpoint Globally set endpoint URL before constructing a Client instance.
	 */
	public function __construct()
	{
		try {
			// Use static create method to use default Middleware
			$stack = \GuzzleHttp\HandlerStack::create();
			// Add authentication middleware
			$stack->push($this->authHeaderMiddleware());
			// Construct client with custom stack
			$this->client = new \GuzzleHttp\Client(['handler' => $stack, 'verify' => __DIR__ . '/../cacert.pem']);
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
	}
	
	/**
	 * Initialize the instance by performing the necessary authentication with the Dropcart API server.
	 * 
	 * <p>
	 * May perform an HTTP request to verify the supplied store identifier and private key combination.
	 * May perform other HTTP requests to eagerly load store details, such as categories.
	 * An exception may be thrown either by this method or by any other method of this class whenever
	 * authorization failed.
	 * </p>
	 * 
	 * <p>
	 * Clients MUST NOT call this method multiple times to change the authentication of the client instance.
	 * Note: Dropcart servers monitor access and blocks IP adresses with suspisious account activity.
	 * Authenticating with multiple unrelated accounts may trigger suspisious activity detectors.
	 * </p>
	 * 
	 * @param string $public_key
	 * @param string $private_key
	 * @param string $country
	 */
	public function auth($public_key, $private_key, $country)
	{
		try {
			if ($this->public_key != null) return;
			if ($this->private_key != null) return;

			$this->public_key = $public_key;
			$this->private_key = $private_key;
			$this->country = $country;
			
			// Eagerly load categories, so we can choose default category
			$this->getCategories();
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
	}
	
	private $categories = null;
	private $default_category = null;
	
	/**
	 * Retrieves a list of categories.
	 * 
	 * <p>
	 * The first time this method is called, makes a blocking request with the Dropcart API servers to
	 * retrieve the categories related to the authenticated store.
	 * </p>
	 * 
	 * <p>
	 * Returns an array of categories, one element per category. The category itself is an associative array with the following fields:
	 * `id`, `image`, `name`, `description`, `meta_description`
	 * </p>
	 */
	public function getCategories()
	{
		if ($this->categories != null) return $this->categories;
		
		try {
			$request = new Request('GET', $this->findUrl('categories'));
			$response = $this->client->send($request, [
					'timeout' => self::$g_timeout,
					'connect_timeout' => self::$g_connect_timeout
			]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			
			if (isset($json['data']) && count($json['data']) > 0) {
				$this->categories = $json['data'];
					
				if (is_null($this->default_category)) {
					if (count($this->categories) > 0) {
						$this->default_category = $this->categories[0];
					} else {
						$this->default_category = ['id' => 0];
					}
				}
				
				return $this->categories;
			}
			throw $this->wrapException(new ClientException("Store has no defined categories"));
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
	}
	
	/**
	 * Retrieves a list of products.
	 * 
	 * <p>
	 * Makes a blocking request with the Dropcart API server to retrieve the products associated with
	 * the account currently authenticated with.
	 * </p>
	 * 
	 * <p>
	 * An optional category parameter can be supplied, either an integer (category ID) or a category
	 * as one of the elements returned by the `getCategories` method. If the parameter is not supplied,
	 * a default category is used. 
	 * </p>
	 * 
	 * <p>
	 * Returns an array of products, one element for each product. The product itself is an associative array with the summary fields of a product. These fields are:
	 * `id`, `ean`, `sku`, `shipping_days`, `image`, `price`, `in_stock`, `name`, `description`. See the API documentation for information concering the
	 * value ranges of these fields. The return value is similar to that of `findProductListing`.
	 * </p>
	 * 
	 * @param mixed $category
	 */
	public function getProductListing($category = null, $page = null, $show_unavailable_items = false, $brands = [], $query = null)
	{
		if (is_null($category) && $this->default_category) {
			$category = $this->default_category;
		}
		
		if (is_int($category)) {
			$category_id = $category;
		} else {
			$category_id = $category['id'];
		}
		
		$param = [];
		if ($page) $param['page'] = (string) $page;
		if ($show_unavailable_items) $param['show_unavailable_items'] = 'true';
		if (!empty($brands)) $param['brands'] = implode(",", $brands);
		if ($query) $param['query'] = (string) $query;
		
		try {
			$request = new Request('GET', $this->findUrl('products', "/" . $category_id, $param));
			$response = $this->client->send($request, ['timeout' => self::$g_timeout, 'connect_timeout' => self::$g_connect_timeout]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = [
				'list' => [],
				'pagination' => [],
				'brands' => [],
			];
			if (isset($json['data'])) {
				$result['list'] = $json['data'];
			}
			if (isset($json['meta']) && isset($json['meta']['pagination'])) {
				$result['pagination'] = $json['meta']['pagination'];
			}
			if (isset($json['meta']) && isset($json['meta']['brands'])) {
				$result['brands'] = $json['meta']['brands'];
			}
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Product listing has no results"));
	}
	
	/**
	 * Retrieves detailed information concerning a single product.
	 * 
	 * <p>
	 * Makes a blocking request with the Dropcart API server to retrieve the product information associated with
	 * the account currently authenticated with.
	 * </p>
	 * 
	 * <p>
	 * The parameter supplied specifies what product is requested. Either an integer (product ID) or a product
	 * array as one of the elements returned by `getProductListing` or `findProductListing`. The parameter is
	 * required, it is an error to not supply its value.
	 * </p>
	 * 
	 * <p>
	 * Returns a product, which is an associative array. The fields are:
	 * `id`, `name`, `description`, `ean`, `sku`, `attributes`, `brand`, `images`, `price`, `in_stock`.
	 * See the API documentation for information
	 * concering the value ranges of these fields.
	 * </p>
	 * 
	 * @param mixed $product
	 */
	public function getProductInfo($product)
	{
		$product_id = $this->productToInt($product);
		try {
			$request = new Request('GET', $this->findUrl('product', "/" . $product_id));
			$response = $this->client->send($request, ['timeout' => self::$g_timeout, 'connect_timeout' => self::$g_connect_timeout]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
				
			if (isset($json['data'])) {
				$product = $json['data'];
				return $product;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Product info has no or too many results"));
	}
	
	private function productToInt($product) {
		if (is_int($product)) {
			$product_id = $product;
		} else if (isset($product['id'])) {
			$product_id = $product['id'];
		} else {
			throw $this->wrapException(new ClientException("Supplied product is invalid"));
		}
		return $product_id;
	}
	
	// BEGIN SHOPPING BAG SHARED-CODE
	
	/**
	 * Adds a product to a shopping bag.
	 *
	 * <p>
	 * If the product is already contained in the bag, the quantities will be added.
	 * It is invalid to call this method with a non-positive quantity, i.e. zero and negative
	 * integers are not allowed.
	 * </p>
	 *
	 * <p>
	 * The coded result can be stored in a Cookie or session. The exact representation may
	 * differ between versions of the Dropcart client. Use `readShoppingBag` for extracting a
	 * stable representation. An empty string represents an empty shopping bag.
	 * </p>
	 *
	 * @param string $coding
	 * @param mixed $product
	 * @param integer $quantity
	 */
	public function addShoppingBag($coding, $product, $quantity = 1) {
		$product_id = $this->productToInt($product);
		if ($quantity <= 0) {
			throw $this->wrapException(new ClientException("Non-positive quantity not allowed: " . $quantity));
		}
		$bag = $this->readShoppingBagInternal($coding);
		$bag[] = [
				'product' => $product_id,
				'quantity' => $quantity
		];
		$result = $this->normalizeShoppingBag($bag);
		return $this->writeShoppingBagInternal($bag);
	}
	
	/**
	 * Removes a product from a shopping bag.
	 *
	 * <p>
	 * If the product is already contained in the bag, the quantity will be subtracted:
	 * It is invalid to call this method with a non-positive quantity, i.e. zero and negative
	 * integers are not allowed.
	 * </p>
	 *
	 * <p>
	 * The coded result can be stored in a Cookie or session. The exact representation may
	 * differ between versions of the client. Use `readShoppingBag` for extracting a stable
	 * representation. An empty string represents an empty shopping bag.
	 * </p>
	 *
	 * <p>
	 * If quantity is `-1` (negative one), then all of the products are removed, i.e. effectively
	 * setting the quantity of the associated product to zero (and thus removing it from the
	 * bag).
	 * </p>
	 *
	 * @param string $coding
	 * @param mixed $product
	 * @param integer $quantity
	 */
	public function removeShoppingBag($coding, $product, $quantity = -1) {
		$product_id = $this->productToInt($product);
		$quantity = (int) $quantity;
		if ($quantity == 0 || $quantity < -1) {
			throw $this->wrapException(new ClientException("Non-positive quantity not allowed except -1"));
		}
		$bag = $this->readShoppingBagInternal($coding);
		if ($quantity == -1) {
			$remove = FALSE;
			foreach($bag as $key => $pointer) {
				if ($pointer['product'] == $product_id) {
					$remove = $key;
					break;
				}
			}
			if ($remove === FALSE) {
				// Silently ignore deletion of non-occurring bag
			} else {
				unset($bag[$remove]);
			}
		} else {
			$bag[] = [
					'product' => $product_id,
					'quantity' => -$quantity
			];
			// Normalization removes non-positive occurrences
		}
		$bag = $this->normalizeShoppingBag($bag);
		$this->verifyShoppingBag($bag);
		return $this->writeShoppingBagInternal($bag);
	}
	
	private function readShoppingBagInternal($coding) {
		$this->checkShoppingBag($coding);
		$array = explode("~", (string) $coding);
		if ($array === FALSE) $array = [];
		if (count($array) == 1 && $array[0] == "") $array = [];
		$result = [];
		foreach ($array as $pointer) {
			$subarray = explode("=", $pointer);
			if (count($subarray) != 2) {
				throw $this->wrapException(new ClientException("Invalid shopping bag coding: " . $coding));
			}
			$subresult = [
					'product' => (int) $subarray[0],
					'quantity' => (int) $subarray[1]
			];
			$result[] = $subresult;
		}
		$result = $this->normalizeShoppingBag($result);
		$this->verifyShoppingBag($result);
		return $result;
	}
	
	private function normalizeShoppingBag($bag) {
		// Normalize: collapse multiple products quantities by sum
		$keys = [];
		$delete = [];
		// We let first occurring elements survive
		foreach ($bag as $key => $pointer) {
			$product_id = $this->productToInt($pointer['product']);
			if (isset($keys[$product_id])) {
				// Update previous element in array
				$bag[$keys[$product_id]]['quantity'] += (int) $pointer['quantity'];
				// Delete current element in array
				$delete[] = $key;
			} else {
				$keys[$product_id] = $key;
			}
		}
		// Remove only after full iteration
		foreach ($delete as $key) {
			unset($bag[$key]);
		}
		// Normalize: remove non-positive quantities
		$delete = [];
		foreach ($bag as $key => $pointer) {
			if ($pointer['quantity'] <= 0) {
				$delete[] = $key;
			}
		}
		// Remove only after full iteration
		foreach ($delete as $key) {
			unset($bag[$key]);
		}
		return $bag;
	}
	
	private function verifyShoppingBag($bag) {
		// Verify: no non-positive entries
		foreach ($bag as $pointer) {
			if ($pointer['product'] < 0) {
				throw $this->wrapException(new ClientException("Invalid shopping bag entry product identifier"));
			}
			if ($pointer['quantity'] <= 0) {
				throw $this->wrapException(new ClientException("Invalid non-positive shopping bag entry quantity"));
			}
		}
	}
	
	private function checkShoppingBag($coding) {
		// Verify: coding matches regular expression
		$re = '/^(?:(?:\d+=\d+)(?:~\d+=\d+)*)?$/m';
		if (!preg_match($re, $coding)) {
			throw $this->wrapException(new ClientException("Shopping bag code does not match expression: " . $coding));
		}
	}
	
	/**
	 * Inverse of `readShoppingBagInternal`.
	 */
	private function writeShoppingBagInternal($bag) {
		// External format:
		// [
		//     ['product' => product,
		//      'quantity' => quantity],
		//     ...
		// ]
	
		// Internal format: "~" separated string of (id "=" qty) substrings
		// E.g. 5=1:3=5:1=6
		$result = "";
		foreach ($bag as $pointer) {
			$product_id = $this->productToInt($pointer['product']);
			$quantity = (int) $pointer['quantity'];
			if (strlen($result) > 0) {
				$result .= "~";
			}
			$result .= $product_id;
			$result .= "=";
			$result .= $quantity;
		}
		return $result;
	}
	
	// END SHOPPING BAG SHARED-CODE
	
	/**
	 * Extracts from a shopping bag coding an easy representation for the current shopping bag.
	 * 
	 * <p>
	 * May perform one or more a blocking request(s) with the Dropcart API server to retrieve the product information
	 * associated with the account currently authenticated with and the products stored in the shopping bag.
	 * </p>
	 * 
	 * <p>
	 * An array of product pointers is returned, where a product pointer consists of a product and quantities as follows:
	 * ```[ ['product' => product, 'quantity' => integer], ... ]```
	 * In other words, the outer array contains arrays as elements, and the inner array is an association of the keys `product` and `quantity`.
	 * See the examples for consuming the result. The product is in a similar format as `getProductInfo`. See the API documentation for information
	 * concering the value ranges of these product fields.
	 * </p>
	 * 
	 * @param string $coding
	 */
	public function readShoppingBag($coding) {
		$bag = $this->readShoppingBagInternal($coding);
		// Load product information
		foreach ($bag as &$pointer) {
			$pointer['product'] = $this->getProductInfo((int) $pointer['product']);
		}
		return $bag;
	}
	
	/**
	 * Start a transaction for handling an order.
	 * 
	 * <p>
	 * Makes a blocking request with the Dropcart API server to create a transaction associated with the account currently authenticated with.
	 * The products stored in the shopping bag are used to create an order quote.
	 * </p>
	 * 
	 * <p>
	 * The result of this function call is an associative array, with keys:
	 * `errors`, `warnings`, `transaction`, `reference`, `checksum`, `missing_customer_details` and `shopping_bag`.
	 * The `errors` field is an array of error messages, and `warnings` an array of warnings. Clients SHOULD show error messages to
	 * web clients, and MUST show warning messages to web clients. The `transaction` field contains a transaction object. The `shopping_bag`
	 * field contains an updated shopping bag (e.g. items may be removed by created a transaction).
	 * </p>
	 * 
	 * <p>
	 * The `status` field determines what transaction methods are valid, i.e. for `"PARTIAL"` it is `updateTransaction` and for `"FINAL"` it is `confirmTransaction`.
	 * Of these keys, `reference` and `checksum` are required for a next invocation.
	 * </p>
	 * 
	 * @param string $coding
	 */
	public function createTransaction($coding, $customerDetails = []) {
		// Round-trip to verify and normalize code
		$bag = $this->readShoppingBagInternal($coding);
		$coding = $this->writeShoppingBagInternal($bag);
		// Verify customer details
		$postData = [];
		foreach (self::$g_customer_fields as $field) {
			if (isset($customerDetails[$field])) {
				$postData[$field] = $customerDetails[$field];
			}
		}
		try {
			$request = new Request('POST', $this->findUrl('order', "/create/" . urlencode($coding)));
			$response = $this->client->send($request, [
					'timeout' => self::$g_timeout,
					'connect_timeout' => self::$g_connect_timeout,
					'form_params' => $postData
			]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = $this->loadTransactionResult($json);
			
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Transaction creation has no or too many results"));
	}
	
	/**
	 * Retrieve an existing transaction by reference and checksum.
	 * 
	 * <p>
	 * Makes a blocking request with the Dropcart API server to retrieve a transaction associated with the account currently authenticated with.
	 * The products stored in the shopping bag are used to create an order quote.<br />
	 * This method is only defined for use during the order process for PARTIAL and FINAL quotes. If the transaction is already
	 * CONFIRMED or PAYED, this method throws an exception. Instead, `statusTransaction` should be called to retrieve the current status of the
	 * transaction: that method will not retrieve all information, but only relevant for checking the result of a transaction after it .
	 * </p>
	 * 
	 * <p>
	 * The result of this function call is similar to `updateTransaction` or `createTransaction`, but will not modify an existing transaction.
	 * This method is useful for implementing a stateless Dropcart client.
	 * </p>
	 * 
	 * @param string $coding
	 * @param string $reference
	 * @param string $checksum
	 */
	public function getTransaction($coding, $reference, $checksum) {
		try {
			$url = $this->findUrl('order', "/" . urlencode($reference) . "/" . urlencode($coding) . "/" . urlencode($checksum));
			$request = new Request('GET', $url);
			$response = $this->client->send($request, [
					'timeout' => self::$g_timeout,
					'connect_timeout' => self::$g_connect_timeout
			]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = $this->loadTransactionResult($json);
		
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Transaction retrieval has no or too many results"));
	}
	
	/**
	 * Modify an existing transaction for handling an order.
	 * 
	 * <p>
	 * Makes a blocking request with the Dropcart API server to create a transaction associated with the account currently authenticated with.
	 * The products stored in the shopping bag are used to create an order quote.
	 * </p>
	 * 
	 * <p>
	 * The `coding` input parameter SHOULD be the same shopping bag as used when creating the transaction. If either the client has modified
	 * the shopping bag, or the server can no longer give a quote for the supplied shopping bag, warnings will be issued.<br />
	 * The `reference` and `checksum` parameter are given by the result of `createTransaction` or a previous invocation of `updateTransaction` and must be supplied verbatim.<br />
	 * The `customerDetails` field is an associative array, containing fields as specified by `missing_customer_details`. The fields which
	 * are required are necessary to supply, the optional fields can be left out.
	 * </p>
	 * 
	 * <p>
	 * The result of this function call is an associative array, with keys:
	 * `errors`, `warnings`, `transaction`, `reference`, `checksum`, `missing_customer_details` and `shopping_bag`.
	 * The `errors` field is an array of error messages, and `warnings` an array of warnings. Clients SHOULD show error messages to
	 * web clients, and MUST show warning messages to web clients. The `transaction` field contains a transaction object. The `shopping_bag`
	 * field contains an updated shopping bag (e.g. items may be removed by created a transaction).
	 * </p>
	 * 
	 * <p>
	 * The `status` field determines what transaction methods are valid, i.e. for `"PARTIAL"` it is `updateTransaction` and for `"FINAL"` it is `confirmTransaction`.
	 * Of these keys, `reference` and `checksum` are required for a next invocation.
	 * </p>
	 * 
	 * @param string $coding
	 * @param string $reference
	 * @param string $checksum
	 */
	public function updateTransaction($coding, $reference, $checksum, $customerDetails) {
		// Round-trip to verify and normalize code
		$bag = $this->readShoppingBagInternal($coding);
		$coding = $this->writeShoppingBagInternal($bag);
		// Verify customer details
		$postData = [];
		foreach (self::$g_customer_fields as $field) {
			if (isset($customerDetails[$field])) {
				$postData[$field] = $customerDetails[$field];
			}
		}
		try {
			$url = $this->findUrl('order', "/" . urlencode($reference) . "/" . urlencode($coding) . "/" . urlencode($checksum));
			$request = new Request('POST', $url);
			$response = $this->client->send($request, [
					'timeout' => self::$g_timeout,
					'connect_timeout' => self::$g_connect_timeout,
					'form_params' => $postData
			]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = $this->loadTransactionResult($json);
				
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Transaction update has no or too many results"));
	}

	/**
	 * Get the payment methods you've accepted on Mollie.
	 *
	 * <p>
	 * Login to Mollie and open the website profile belonging to the installation.
	 * Set the Payments Methods you want to accept to 'on'.
	 * </p>
	 *
	 * <p>
	 * Returns an array with payment method objects.<br>
	 * ->id<br>
	 * ->name<br>
	 * ->logo<br>
	 * <i>optional</i>
	 * ->extra's<br>
	 *    ->fields [ <br>
	 * 			-> type (selectable)<br>
	 * 			-> values (object, or reference)<br>
	 * 			-> id<br>
	 * 			-> name<br>
	 * 	]
	 * </p>
	 */
	public function getPaymentMethods()
	{
		$postData = [];
		foreach (self::$g_customer_fields as $field) {
			if (isset($customerDetails[$field])) {
				$postData[$field] = $customerDetails[$field];
			}
		}
		try {
			$url = $this->findUrl('tools', "/get-payment-methods");
			$request = new Request('GET', $url);
			$response = $this->client->send($request, [
				'timeout' => self::$g_timeout,
				'connect_timeout' => self::$g_connect_timeout,
				'form_params' => $postData
			]);
			$this->checkResult($response);
			$result = json_decode($response->getBody(), true);

			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("There are no payment methods available."));

	}
	
	/**
	 * Confirm a transaction for handling an order. This call MUST only be called when the Web client consents with the order.
	 *
	 * <p>
	 * Makes a blocking request with the Dropcart API server to confirm a transaction associated with the account currently authenticated with.
	 * The products stored in the shopping bag are used to create an order quote.
	 * </p>
	 *
	 * <p>
	 * The `coding` input parameter SHOULD be the same shopping bag as used when creating the transaction. If either the client has modified
	 * the shopping bag, or the server can no longer give a quote for the supplied shopping bag, warnings will be issued.<br />
	 * The `reference` and `checksum` parameter are given by the result of `createTransaction` or `updateTransaction` and must be supplied verbatim.<br />
	 * The `returnURL` is an absolute URL to which the client is redirected after performing a payment.
	 * </p>
	 *
	 * <p>
	 * The result of this function call is an associative array, with keys:
	 * `errors`, `warnings`, `redirect`.<br />
	 * Only if `errors` and `warnings` are empty, will the `redirect` field be defined. Otherwise, the result is the same as performing a
	 * `updateTransaction` with the previously supplied customer details. If `redirect` is defined it will contain a URL. The Web client MUST be
	 * redirected to the URL, unmodified. After the payment has been performed, the Web client will be redirected back to `returnURL`.
	 * </p>
	 *
	 * @param string $coding
	 * @param string $reference
	 * @param string $checksum
	 * @param string $returnURL
	 */
	public function confirmTransaction($coding, $reference, $checksum, $returnURL, $method = 'ideal', $method_data = []) {
		// Round-trip to verify and normalize code
		$bag = $this->readShoppingBagInternal($coding);
		$coding = $this->writeShoppingBagInternal($bag);
		$postData = ['return_url' => $returnURL, 'method' => $method, 'method_data' => $method_data];
		try {
			$url = $this->findUrl('pay', "/" . urlencode($reference) . "/" . urlencode($coding) . "/" . urlencode($checksum));
			$request = new Request('POST', $url);
			$response = $this->client->send($request, [
					'timeout' => self::$g_timeout,
					'connect_timeout' => self::$g_connect_timeout,
					'form_params' => $postData
			]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = $this->loadTransactionResult($json);
	
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Transaction confirm has no or too many results"));
	}
	
	/**
	 * Retrieve the status of a transaction after an order has been confirmed.
	 *
	 * <p>
	 * Makes a blocking request with the Dropcart API server to retrieve status of a transaction associated with the account currently authenticated with.
	 * </p>
	 *
	 * <p>
	 * The `reference` and `checksum` parameter are given by the result of `createTransaction` or `updateTransaction` and must be supplied verbatim.<br />
	 * Note that the `checksum` is supplied for verification.
	 * </p>
	 *
	 * <p>
	 * The result of this function call is an associative array, with keys:
	 * `status`.<br />
	 * The `status` field contains either<br/>
	 * `PARTIAL` if the order is not yet complete (missing customer details),<br/>
	 * `FINAL` if the order is complete but not yet confirmed,<br/>
	 * `CONFIRMED` if the transaction is confirmed but not yet payed,<br/>
	 * and `PAYED` if the transaction is payed.
	 * </p>
	 * 
	 * <p>
	 * If this method results in an exception, all state on the client should be released: no reference, checksum or shopping bag SHOULD be retained
	 * after such an exception.
	 * </p>
	 * 
	 * @param unknown $reference
	 * @param unknown $checksum
	 * @return array 
	 */
	public function statusTransaction($reference, $checksum) {
		try {
			$url = $this->findUrl('status', "/" . urlencode($reference) . "/" . urlencode($checksum));
			$request = new Request('GET', $url);
			$response = $this->client->send($request, ['timeout' => self::$g_timeout, 'connect_timeout' => self::$g_connect_timeout]);
			$this->checkResult($response);
			$json = json_decode($response->getBody(), true);
			$result = $this->loadTransactionResult($json);
		
			if (count($result) > 0) {
				return $result;
			}
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
		throw $this->wrapException(new ClientException("Transaction status has no or too many results"));
	}

	/**
	 * @param $json
	 * @return array
	 */
	private function loadTransactionResult($json) {
		$result = [];
		if (isset($json['meta']) && isset($json['meta']['shopping_bag'])) {
			$result['shopping_bag'] = $json['meta']['shopping_bag'];
		}
		if (isset($json['meta']) && isset($json['meta']['reference'])) {
			$result['reference'] = $json['meta']['reference'];
		}
		if (isset($json['meta']) && isset($json['meta']['checksum'])) {
			$result['checksum'] = $json['meta']['checksum'];
		}
		if (isset($json['meta']) && isset($json['meta']['missing_customer_details'])) {
			$result['missing_customer_details'] = $json['meta']['missing_customer_details'];
		}
		if (isset($json['meta']) && isset($json['meta']['customer_details'])) {
			$result['customer_details'] = $json['meta']['customer_details'];
		}
		if (isset($json['meta']) && isset($json['meta']['warnings'])) {
			$result['warnings'] = $json['meta']['warnings'];
		}
		if (isset($json['meta']) && isset($json['meta']['errors'])) {
			$result['errors'] = $json['meta']['errors'];
		}
		if (isset($json['meta']) && isset($json['meta']['redirect'])) {
			$result['redirect'] = $json['meta']['redirect'];
		}
		if (isset($json['meta']) && isset($json['meta']['status'])) {
			$result['status'] = $json['meta']['status'];
		}
		if (isset($json['data']) && count($json['data']) > 0) {
			$result['transaction'] = $json['data'];
		}
		return $result;
	}
	
	private function wrapException($any)
	{
		if ($any instanceof ClientException) {
			if (is_null($any->context)) {
				$any->context = $this->context;
			}
			return $any;
		} else {
			$this->context['last_exception'] = (string) $any;
			if ($any instanceof RequestException) {
				$response = $any->getResponse();
				if ($response) {
					$this->context['last_response'] = (string) $response->getBody();
				} else {
					$this->context['last_response'] = "Unknown";
				}
			}
			return new ClientException($this->context, $any);
		}
	}
	
}
