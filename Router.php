<?php



class Wave_Router {

	private static $root;
	
	public $request_method;
	public $request_uri;
	public $profile;
	
	public $response_method;
	
	public function __construct($host){
		self::$root = $this->loadRoutesCache($host);
	}
	
	public static function init($host = null){
		Wave_Hook::triggerAction('router.before_init', array(&$host));
		if($host === null){
			$host = $_SERVER['HTTP_HOST'];
		}
		$instance = new self($host);
		Wave_Hook::triggerAction('router.after_init', array(&$instance));
		return $instance;
	}
	
	public function route($url = null, $method = null, $data = array()){
		
		if($url === null){
			if(isset($_SERVER['PATH_INFO']))
				$this->request_uri = substr($_SERVER['PATH_INFO'], strpos($_SERVER['PATH_INFO'], '.php/'));
			else
				$this->request_uri = $_SERVER['REQUEST_URI'];
		}
		else 
			$this->request_uri = $url;
		
		// trim off any query string parameters etc
		$qs = strpos($this->request_uri, '?');
		if($qs !== false){
			$this->request_uri = substr($this->request_uri, 0, $qs);
		}
		
		// remove the trailing slash and replace with the default response method if required
		if(substr($this->request_uri, -1, 1) == '/'){
			$trimmed = substr($this->request_uri, 0, -1);
			$this->request_uri =  $trimmed . '.' . Wave_Config::get('wave')->controller->default_response;
		}
		
		// deduce the response method
		$path = pathinfo($this->request_uri);
		if(isset($path['extension']) && in_array($path['extension'], Wave_Response::$ALL)){
			$this->response_method = $path['extension'];
			// remove the response method from the url, we dont need it here
			$this->request_uri = substr($this->request_uri, 0, -(strlen($this->response_method)+1));
		}
		else $this->response_method = Wave_Config::get('wave')->controller->default_response;
		
		if(Wave_Exception::$_response_method === null)
			Wave_Exception::$_response_method = $this->response_method;
		
		if($method === null)
			$this->request_method = $_SERVER['REQUEST_METHOD'];
		else
			$this->request_method = $method;
			
		Wave_Hook::triggerAction('router.before_routing', array(&$this, &$data));
		return $this->findRoute($this->request_method.$this->request_uri, $data);
	}

	public function findRoute($url, $data = array()){
		
		$var_stack = $data;
		
		$node = self::$root->findChild($url, $var_stack);
		
		if($node instanceof Wave_Router_Node && $action = $node->getAction()){
			
			if(!$action->canRespondWith($this->response_method)){
				throw new Wave_Exception(
					'The requested action '.$action->getAction().
					' can not respond with '.$this->response_method.
					'. (Accepts: '.implode(', ', $action->getRespondsWith()).')');
			}
			elseif(!$action->checkRequiredLevel($var_stack)){
					
				$auth_obj = Wave_Auth::getIdentity();
				$auth_class = Wave_Auth::getHandlerClass();
				
				if(!in_array('Wave_IAuthable', class_implements($auth_class)))
					throw new Wave_Exception('A valid Wave_IAuthable class is required to use RequiresLevel annotations', 500);
				
				if(!$auth_class::noAuthAction(array(
					'destination' => $action,
					'auth_obj' => $auth_obj,
					'args' => $var_stack
				)))
					throw new Wave_Exception(
						'The current user does not have the required level to access this page', 403);
			}
			
			return Wave_Controller::invoke($action->getAction(), $var_stack, $this);
		}
		else
			throw new Wave_Exception('The requested URL '.$url.' does not exist', 404);
	}
	
	public function loadRoutesCache($host){
		$profiles = Wave_Config::get('deploy')->profiles;
		$cache_name = self::getCacheName($host);
		
		$host_profile = $host;	
		$routes = Wave_Cache::load($cache_name);
		if($routes == null){
			$defaultdomain = $profiles->default->baseurl;
			$host_profile = $defaultdomain;	
			$routes = Wave_Cache::load(self::getCacheName($defaultdomain));
		}
		
		if($routes == null)
			throw new Wave_Exception('Could not load routes for domain: '.$host.' nor default domain: '.$defaultdomain);
		else {
			foreach($profiles as $name => $profile){
				if($profile->baseurl == $host_profile){
					$this->profile = $name;
					break;	
				}
			}
			return $routes;	
		}
		
	}
	
	public static function getCacheName($host){
		return 'routes/'.md5($host);
	}

}

?>