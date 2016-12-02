## Table of contents

- [\Dropcart\Client](#class-dropcartclient)
- [\Dropcart\ClientException](#class-dropcartclientexception)

<hr /> 
### Class: \Dropcart\Client

> Dropcart client access object <p> The Dropcart Client class represents a stateful connection with the Dropcart API server. Each time you construct an instance, the client must authenticate using a private key. Every method call blocks to perform an HTTP request to the Dropcart servers. </p>

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct()</strong> : <em>void</em><br /><em>Constructs a new client instance. <p> Each client will maintain a connection with the Dropcart API server. Only upon initialization of a new instance will the globally set endpoint URL be read. </p></em> |
| public | <strong>auth(</strong><em>string</em> <strong>$public_key</strong>)</strong> : <em>void</em><br /><em>Initialize the instance by performing the necessary authentication with the Dropcart API server. <p> May perform an HTTP request to verify the supplied store identifier and private key combination. May perform other HTTP requests to eagerly load store details, such as categories. An exception may be thrown either by this method or by any other method of this class whenever authorization failed. </p> <p> Clients MUST NOT call this method multiple times to change the authentication of the client instance. Note: Dropcart servers monitor access and blocks IP adresses with suspisious account activity. Authenticating with multiple unrelated accounts may trigger suspisious activity detectors. </p></em> |
| public | <strong>getCategories()</strong> : <em>mixed</em><br /><em>Retrieves a list of categories. <p> The first time this method is called, makes a blocking request with the Dropcart API servers to retrieve the categories related to the authenticated store. </p></em> |
| public | <strong>getProductInfo()</strong> : <em>mixed</em> |
| public | <strong>getProductListing()</strong> : <em>mixed</em><br /><em>Retrieves a list of products. <p> Makes a blocking request with the Dropcart API server to retrieve the products associated with the account currently authenticated with. </p></em> |
| public static | <strong>setEndpoint(</strong><em>string</em> <strong>$url</strong>)</strong> : <em>void</em><br /><em>Changes the Dropcart Endpoint URL. <p> Modify the endpoint URL to which only NEW INSTANCE will use to connect to. All existing client objects remain the use the previous endpoint URL. The default value for this method is: `https://api.dropcart.nl`. </p> <p> The parameter needs to be a valid URL WITHOUT trailing slash. This method does not perform any validation on the supplied argument, and failing to set a correct URL will throw errors during the lifetime of a Client object. </p></em> |

<hr /> 
### Class: \Dropcart\ClientException

> All exceptions thrown by the client module will inherit from this class. Catching these exceptions thus allows clients to handle all errors.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$context</strong>, <em>mixed</em> <strong>$previous</strong>)</strong> : <em>void</em> |

*This class extends \Exception*

*This class implements \Throwable*

