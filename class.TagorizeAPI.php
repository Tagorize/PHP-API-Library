<?php
/**
################################################################################################
# GNU GENERAL PUBLIC LICENSE
################################################################################################

Tagorize API Connector. PHP library to allow easy interfacing with the Tagorize API.

Copyright (C) 2010  Dave Bullough

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


define('API_SERVICE', 		'http://api.tagorize.com/services/rest?');
define('API_SIG_LENGTH', 	44);
define('API_NAMESPACE', 	'tagorize');
define('API_DEFAULT_DELIMITER', '|');
define('API_CACHE_AGE', 	1500);		// 25 minutes

class TagorizeAPIConnector {
	
	private $error, $data, $debug;
	private $settings, $custom_settings;
	
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
		
		$this->settings['key'] 		= $key;
		$this->settings['secret'] 	= $secret;
		$this->settings['cache_age'] 	= API_CACHE_AGE;
		$this->settings['response_type'] = 'array';
	}
	
	private function get_setting($key) 
	{
		// if setting has been oveeridden, use it
		if (isset($this->custom_settings[$key])) 
		{
			return $this->custom_settings[$key];
		} 
		else if	(isset($this->settings[$key])) 
		{
			return $this->settings[$key];
		} 
		else 
		{
			return '';
		}
	}
	
	public function set_response ($response_type) 
	{
		if (!preg_match("/(object|array)$/", $response_type)) 
		{
			throw new Exception('Response type not supported');
		}		
		$this->settings['response_type'] = $response_type; 
	}
	
	public function set_debug($on) 
	{
		if (!is_bool($on)) return;
		$this->debug = $on;
	}
	
	public function set_cache_dir ($dir) 
	{
		if (!file_exists($dir)) 
		{
			throw new Exception('Specified cache directory does not exist');
		}
		
		if (!is_writable($dir . "/tgz.tmp")) 
		{
			//throw new Exception('Specified cache directory is not writable');
		}
		
		$this->settings['cache_dir'] = $dir;
		$this->settings['cache_on'] = true;
		$this->settings['cache_refresh'] = false;
	}
	
	public function set_cache_age ($age) 
	{
		if (!is_numeric($age)) throw new Exception('Cache age is not numeric');
		
		$this->settings['cache_on'] = true;
		$this->settings['cache_age'] = $age;
	}
	
	public function get_last_error () 
	{
		return $this->error;
	}
	
	
	
	public function call ($method, TagorizeAPIParams $params, array $options=array()) 
	{
		if (!preg_match("/^(" . API_NAMESPACE . "\.)/", $method)) 
		{
			$method = API_NAMESPACE . ".$method";
		}
		
		// deal with custom options
		foreach(array_keys($options) as $option) 
		{
			$val = $options[$option];
			switch (strval($option))
			{
			case 'cache_age':
				if (!is_numeric($val)) 
				{
					throw new Exception('Custom option for ' . $method . ' \'cache_age\' is invalid. Expected integer.');
				} 
				else 
				{
					$this->custom_settings['cache_age'] = intval($val);					
				}
				break;
			case 'cache_on':
				if (is_bool($val)) 
				{
					$this->custom_settings['cache_on'] = $val;
				}
				break;
			default:
				throw new Exception($option . ' is not an overridable value');
			}
		}
				
		$params->add('method', 	$method);
		$params->add('api_key', $this->get_setting('key'));
		$params->add('api_sig', md5($method . $this->get_setting('secret')));
		
		if ($this->get_setting('cache_on')) 
		{
			if ($this->get_setting('cache_dir') == '')
			{
				throw new Exception('Cache directory not set. See set_cache_dir()');
			}
			
			$cache_file = $this->get_setting('cache_dir') . "/$method." . $params->sign() . ".tmp";
			if (file_exists($cache_file))
			{					
				if (date('U') - filemtime($cache_file) <= $this->get_setting('cache_age')) 
				{					
					$response = file_get_contents($cache_file);
				} 
				else 
				{
					$this->settings['cache_refresh'] = true;
				}
			} 
			else 
			{
				$this->settings['cache_refresh'] = true;
			}
		} 
		
		if (!$this->get_setting('cache_on') || $this->get_setting('cache_refresh'))
		{
			// return results back to request
			$response = $this->get(API_SERVICE . $params->chain());
		}
		
		$d = json_decode($response, true);
		
		if ($this->debug) 
		{
			print("<pre>");
			print_r(array_merge($this->settings, $this->custom_settings));
			print_r($d);
			print("</pre>");
		}
		
		if ($d == NULL) 
		{
			// default json error message
			$error_msg = 'Failed to parse the API response';

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
		// check status
		if ($d['query']['status'] == 'ok') 
		{
			$this->data = (
				$this->get_setting('response_type') == 'array' 
				? 
					$d['results'] 
				: 
					json_decode($response)->results
			);
			
			if ($this->get_setting('cache_on') && $this->get_setting('cache_refresh'))
			{
				try {
					$fh = fopen($cache_file, 'w');
					fwrite($fh, json_encode($d));
					fclose($fh);
				} 
				catch (Exception $e) 
				{
					throw new Exception("Can't open cache file for writing (" . $e->getMessage() . ")");
				}
				
			}
			$this->reset_custom_settings();
			return true;
		} 
		else 
		{
			$this->reset_custom_settings();
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
		
		if (in_array  ('curl', get_loaded_extensions()))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			$data = curl_exec($ch);
			curl_close($ch);
		} 
		else if (function_exists('file_get_contents')) 
		{
			$data = file_get_contents($url);
		}
		return $data;
	}
	
	private function reset_custom_settings () 
	{
		if (sizeof($this->custom_settings) == 0) return;
		foreach(array_keys($this->custom_settings) as $custom) 
		{
			unset($this->custom_settings[$custom]);
		}
	}
}

class TagorizeAPITag
{
	private $tag, $name, $meta;
	public function __construct ($name, array $meta=array()) 
	{
		if (!isset($name)) 
		{
			throw new Exception('Tag name cannot be empty');
		}
		if (isset($meta) && is_array($meta)) 
		{
			$this->meta = serialize($meta);
		}
		$this->name = $name;
	}
}

/**
 * Params objects are passed into the Connector and sent to the server
 */
class TagorizeAPIParams
{
	private $params;
	
	public function __construct() 
	{
		$this->params = array();
	}
	
	public function add ($key, $val) 
	{
		if (isset($this->params[$key])) throw new Exception('Duplicate key ' . $key . ' in params.');		
		//
		if ($key == 'delimiter' && strlen($val) > 1) 
		{
			throw new Exception('Delimiter cannot be more than one (1) character in length');
		}
		//
		$this->params[$key] = $val;
	}
	
	public function remove ($key) 
	{
		if (isset($this->params[$key])) 
		{
			unset($this->params[$key]);
		}
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
		foreach(array_keys($this->params) as $key) 
		{
			unset($this->params[$key]);
		}
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
