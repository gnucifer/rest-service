<?php
class rest_service {

	private $codes = array(
		'100' => 'Continue',
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'503' => 'Service Unavailable'
	);

	private $response;

	private $supported_methods;
	
	public function set_status($code) {
		$this->codes[strval($code)];
		header("{$_SERVER['SERVER_PROTOCOL']} $code");
	}

	public function __construct($resources) {
		//Rebuild array

		$_resources = array();
		foreach($resources as $path => $methods) {
			foreach($methods as $method => $callback_info) {

				$_resources[$method][$path] = $callback_info;
			}
		}

		$this->resources = $_resources;
    $this->supported_methods = array_keys($_resources);
  }

	private function _parse_arguments($arguments) {
		$argument_options_defaults = array(
			'required' => FALSE,
			'default value' => NULL,
		);
		$args = array();
		foreach($arguments as $name => $options) {
			if(strncmp($key = trim($name, '{}'), $name, 1) !== 0){
				$args[$key] = $this->_parse_arguments($arguments[$name]);
			}
			else {
				$options += $argument_options_defaults;
				$key = isset($options['key'])? $options['key']: $name;
				if(array_key_exists($name, $_REQUEST)) {
					$value = $_REQUEST[$name];
					//validation
					if(
							isset($options['callback']) &&
							is_callable($options['callback']) &&
							!call_user_func_array($options['callback'], array(&$value))
					) {
						//TODO: Throw different exception if callback was invalid, like internal server error probably
						//create validation exception subclass perhaps
						throw new Exception();
					}
					$args[$key] = $value;
				}
				else if($options['required']){
					throw new Exception();
				}
				else {
					$args[$key] = $options['default value'];
				}
			}
		}
		return $args;
	}

	public function handle_request() {
		$uri = trim(array_shift(explode('?', $_SERVER['REQUEST_URI'])), '/');
		$method = $_SERVER['REQUEST_METHOD'];
		
		//Special case for put and delete
		if($method == 'PUT' || $method == 'DELETE'){	
			parse_str(file_get_contents('php://input'), $_REQUEST);
		}
		if(array_key_exists($method, $this->resources)) {
			if(array_key_exists($uri, $this->resources[$method])) {
				if($this->resources[$method][$uri]['callback']){
					//or let it crash and burn?
					//a bit ugly perhaps
					$code = 200;
					if(is_callable($this->resources[$method][$uri]['callback'])) {
						if(isset($this->resources[$method][$uri]['arguments'])) {
							try {
								$args = $this->_parse_arguments($this->resources[$method][$uri]['arguments']);
								array_unshift($args, &$code);
								$this->response = call_user_func_array($this->resources[$method][$uri]['callback'], $args);
							}
							catch(Exception $e) {
								$this->set_status('400');
							}
						}
						else {
							$this->response = call_user_func($this->resources[$method][$uri]['callback'], $code, $_REQUEST);
						}
						$this->set_status($code);
					}
					else {
						$this->set_status('500');
					}
				}
				else {
					$this->set_method_not_allowed_status();
				}
			}
			else {
				$this->set_status('404');
			}
		}
		elseif(in_array($method, array('GET', 'HEAD', 'POST', 'PUT', 'DELETE'))) {
			$this->set_method_not_allowed_status();
		}
		else {
      /* 501 (Not Implemented) for any unknown methods */
      header('Allow: ' . implode(', ', $this->supported_methods), true, 501);
		}

	}
	public function send_response(){
		print $this->response;
	}
	/*
	public function method() {
		return $_SERVER['REQUEST_METHOD'];
		/*
		$method = $_SERVER['REQUEST_METHOD'];
		if ($method == 'POST' && $_GET['method'] == 'PUT') {
			$method = 'PUT';
		} elseif ($method == 'POST' && $_GET['method'] == 'DELETE') {
			$method = 'DELETE';
		}
		return $method;
	}
*/
  private function set_method_not_allowed_status() {
    /* 405 (Method Not Allowed) */
    header('Allow: ' . implode(', ', $this->supported_methods), true, 405);
  }
}
?>
