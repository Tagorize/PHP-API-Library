About
====================

Official support: <http://api.tagorize.com>

This is a simple library for making calls to the Tagorize REST API with PHP. Parameters can be neatly enclosed in a `TagorizeAPIParameters` object to save the developer serializing their own arrays to be added to a URL.

Prerequisites
====================

- PHP5 for basic functionality
- PHP 5.3 for more verbose debugging of response data (requires `json_last_error`)
- cURL (preferable, but only for speed)
- Writable directory for local caching
- API key. Obtain one from <http://api.tagorize.com>

Usage
====================

	// Reference the file
	include_once('class.TagorizeAPI.php');

	// Create an API connector, passing in the API key and secret
	$t = new TagorizeAPIConnector ('xxx', 'xxx');
	
	// Parameters are passed into the connector object via a Params object, which is a simple key/value store that performs some simple validation rules to ensure that data being passed to the API won't get rejected
	$p = new TagorizeAPIParams();
	
	// Use the add() ,method to add a paramater, here we add a limit value and set it to 6
	$p->add('limit', 6);
	$p->add('filter', 'filter');

	// Do another call using the same connector. This time we have passed in a custom options object containing overrides for the settings made earlier
	if ($t->call("tags.suggest", $p)) 
	{		
		// Loop results in array format
		foreach ($t->results() as $result) 
		{
			print($result['name'] . "\n");
		}
	} 
	else 
	{
		print("Error: " . $t->get_last_error());		
	}
	
	unset($t);
	
Using the cache
====================

Judicious use of a cache when calling a remote API will speed up response times for your application, and reduce overhead on the server dealing with your requests.

If you know your application data only gets updated once a day, you should set the cache timeout value to something also around 24 hours (86400 seconds).

Example
--------------------

	// Create a new connector
	$t = new TagorizeAPIConnector ('xxx', 'xxx');	
	
	// Set the cache directory. This must be a directory writable by the account used for your server
	$t->set_cache_dir('./cache');
	
	// Set the expiry date for the cache files. Once the files expire they are re-fetched, otherwise API calls will come from disk
	$t->set_cache_age(86400);
	
	// Rest of your code as normal will fetch data from the local cache.

Overrides
====================

Supported in this version:

- cache_age
- cache_on

Certain API calls may require a more frequent cache refresh, so developers can override the cache age set at initialisation by passing in a config array to the `call()` method

	// Create the config object
	$config = array(
		'cache_age'=>60
	)
	
	// ... rest of init code here for params, etc
	$t->call('method.name', $params, $config);
	
Overrides only last for that method call. As soon as execution has completed, values are restored to the ones set suring initialisation

Debuggin
====================

Calling the `set_debug` method on a `TagorizeAPIConnector` class will output the returned JSON data to screen for debugging purposes.

	$t->set_debug(true);