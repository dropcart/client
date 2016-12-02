## Table of contents

- [\Dropcart\Client](#class-dropcartclient)

<hr /> 
### Class: \Dropcart\Client

> Dropcart client access object The Dropcart Client class represents a stateful connection with the Dropcart API server. Each time you construct an instance, the client must authenticate using a private key. Every method call blocks to perform an HTTP request to the Dropcart servers.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct()</strong> : <em>void</em><br /><em>Constructs a new client instance. Each client will maintain a connection with the Dropcart API server. Only upon initialization of a new instance will the globally set endpoint URL be read.</em> |
| public | <strong>auth(</strong><em>\Dropcart\unknown</em> <strong>$store_identifier</strong>, <em>\Dropcart\unknown</em> <strong>$private_key</strong>)</strong> : <em>void</em><br /><em>Initialize the instance by performing the necessary authentication with the Dropcart API server. Performs an HTTP request to verify the supplied store identifier and private key combination.</em> |
| public | <strong>getProductInfo()</strong> : <em>mixed</em> |
| public | <strong>getProductsListing()</strong> : <em>mixed</em> |
| public static | <strong>setEndpoint(</strong><em>string</em> <strong>$url</strong>)</strong> : <em>void</em><br /><em>Changes the Dropcart Endpoint URL. Modify the endpoint URL to which every *new* client instance will connect. All existing client objects remain the use the previous endpoint URL. The default value for this method is: `https://api.dropcart.nl`. failing to set a correct URL will throw errors during the lifetime of a Client object.</em> |

