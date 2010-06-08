<?php
/**
################################################################################################
# GNU GENERAL PUBLIC LICENSE
################################################################################################

Tagorize API Connector. PHP library to allow easy interfacing with the Tagorize API.

Copyright (C) 2010  Dave Bullough

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
################################################################################################
# USAGE
################################################################################################    

This class is intended to be used as an easy interface to the Tagorize REST API.

Prerequisites:

- PHP version 5.1 and greater
- cURL (favoured), or file_get_contents available
- json_decode
- Tagorize API key and secret, freely obtained from http://api.tagorize.com
 
Usage:

include('TagorizeAPI.php');
$connector = new TagorizeAPIConnector('api-key', 'api-secret');

*/


define('API_SIG_LENGTH', 44);
define('API_NAMESPACE', 'tagorize');
define('API_DEFAULT_DELIMITER', '|');

class TagorizeAPIConnector {
	
	private $key, $secret, $error, $data, $debug;
	private $response_type = 'array';
	
	public function __construct ($key, $secret) 
	{		
		if (func_num_args() != 2) 
		{
			throw new Exception('Not enough supplied arguments');
		}
		
		if (strlen($key . $secret) != API_SIG_LENGTH) 
		{
			throw new Exception('API credentials did not validate');
		}
		
		$this->key = $key;
		$this->secret = $secret;
	}
	
	public function set_response ($response_type) 
	{
		if (!preg_match("/(object|array)$/", $response_type)) 
		{
			throw new Exception('Response type not supported');
		}
		$this->response_type = $response_type;
	}
	
	public function set_debug($on) 
	{
		if (!is_bool($on)) return;
		$this->debug = $on;
	}
	
	public function get_last_error () 
	{
		return $this->error;
	}
	
	public function call ($method, TagorizeAPIParams $params) 
	{
		if (!preg_match("/^(" . API_NAMESPACE . "\.)/", $method)) 
		{
			$method = API_NAMESPACE . ".$method";
		}
		//
		$params->add('method', 	$method);
		$params->add('api_key', $this->key);
		$params->add('api_sig', md5($method . $this->secret));
		
		// return results back to request
		$response = $this->get('http://api.dev.tagorize.com/services/rest?' . $params->chain());
		//print($response);
		$d = json_decode($response, true);
		
		if ($d == NULL) 
		{
			// default json error message
			$error_msg = 'Failed to parse the API response';
			// attempt to derive more information
			if (function_exists('json_last_error')) 
			{
				switch(json_last_error())
				{
					case JSON_ERROR_DEPTH:
						$error_msg .= ' - Maximum stack depth exceeded';
					break;
					case JSON_ERROR_CTRL_CHAR:
						$error_msg .= ' - Unexpected control character found';
					break;
					case JSON_ERROR_SYNTAX:
						$error_msg .= ' - Syntax error, malformed JSON';
					break;
				}
			}
			throw new Exception($error_msg);
		}
		//
		if ($this->debug) 
		{
			print("<pre>");
			print_r($d);
			print("</pre>");
		}
		// check status
		if ($d['query']['status'] == 'ok') 
		{
			// if user requested object notation, send that back instead
			$this->data = ($this->response_type == 'array' ? $d['results'] : json_decode($response)->results);
			return true;
		} 
		else 
		{
			$this->error = $d['error']['message'];			
			return false;
		}
	}
	
	public function results () 
	{
		return $this->data;
	}
	
	private function get ($url) 
	{
		if (empty($url)) throw new Exception("Invalid URL.");
		
		// use curl if we can due to slightly faster response times
		if (in_array  ('curl', get_loaded_extensions()))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			$data = curl_exec($ch);
			curl_close($ch);
		} 
		// try another approach
		else if (function_exists('file_get_contents')) 
		{
			$data = file_get_contents($url);
		}
		return $data;
	}
}

class TagorizeAPIParams
{
	private $params;
	
	public function __construct() 
	{
		$this->reset();
	}
	
	public function add ($key, $val) 
	{
		// don't allow dupes
		if (isset($this->params[$key])) throw new Exception("Duplicate key '$key' in params.");		
		//
		if ($key == 'delimiter' && strlen($val) > 1) 
		{
			throw new Exception('Delimiter cannot be more than one (1) character in length');
		}
		//
		$this->params[$key] = $val;
	}
	
	public function get () 
	{
		return $this->sort($this->params);
	}
	
	public function sign () 
	{
		return md5($this->chain());
	}
	
	public function reset () 
	{
		$this->params = array();
	}
	
	public function chain () 
	{
		$keys = $this->get();
		$str = '';
		$delimiter = (isset($this->params['delimiter']) ? $this->params['delimiter'] : API_DEFAULT_DELIMITER);
		foreach ($keys as $k=>$val) 
		{
			// array types need to be iterated over
			if (is_array($val)) 
			{
				$val = implode($delimiter, $val);
			}
			$str .= "$k=$val&";
		}
		return rtrim($str, '&');
	}
	
	private function sort ($a) 
	{
		ksort($a);
		return $a;
	}
}
?>
