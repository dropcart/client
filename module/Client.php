<?php

namespace Dropcart;

use \Firebase\JWT\JWT;
use \GuzzleHttp\Message\Request;

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
	
	private static $g_endpoint_url = "https://api.dropcart.nl";
	
	private $context = [];
	
	private function _findUrl($suffix) {
		$result = Client::$g_endpoint_url . "/v2/" . $suffix;
		$this->context[] = ['url' => $result];
		return $result;
	}
	
	private $client;
	
	private $public_key = null;
	private $country = null;
	
	private function _decorateAuth($request) {
		// Decorate public key
		$token = [
				'iss' => $this->public_key
		];
		$jwt = JWT::encode($token, $this->public_key);
		$request->setHeader("Authorization", "Bearer " . $jwt);
		
		// Decoreate country of origin
		$query = $request->getQuery();
		$query['country'] = $this->country;
	}
	
	private function _checkResult($response) {
		$code = $response->getStatusCode();
		$this->context[] = ['code' => $code, 'body' => $response->json()];
		if ($code == 200 || $code == 201)
			return;
		throw new ClientException($this->context, null);
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
			$this->client = new \GuzzleHttp\Client();
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
	 */
	public function auth($public_key, $country)
	{
		try {
			if ($this->public_key != null) return;
			
			$this->public_key = $public_key;
			$this->country = $country;
			
			// Eagerly load categories
			$this->getCategories();
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
	}
	
	private $categories = null;
	
	/**
	 * Retrieves a list of categories.
	 * 
	 * <p>
	 * The first time this method is called, makes a blocking request with the Dropcart API servers to
	 * retrieve the categories related to the authenticated store.
	 * </p>
	 */
	public function getCategories()
	{
		try {
			if ($this->categories != null) return $this->categories;
			
			$request = $this->client->createRequest('GET', $this->_findUrl('categories'));
			$this->_decorateAuth($request);
			$response = $this->client->send($request, ['timeout' => 1.0]);
			$this->_checkResult($response);
			$this->categories = $response->json();
			return $this->categories;
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
	 */
	public function getProductListing()
	{
		try {
			$request = $this->client->createRequest('GET', $this->_findUrl('products'));
			$this->_decorateAuth($request);
			$response = $this->client->send($request, ['timeout' => 1.0]);
			$this->_checkResult($response);
			$product_list = $response->json();
			return $product_list;
		} catch (\Exception $any) {
			throw $this->wrapException($any);
		}
	}
	
	public function getProductInfo()
	{
		
	}
	
	private function wrapException($any)
	{
		if ($any instanceof ClientException) {
			return $any;
		} else {
			return new ClientException($this->context, $any);
		}
	}
	
}
