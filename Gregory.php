<?php
/**
 *
 * Gregory.php
 *
 * Single file webapp framework written in PHP.
 * http://gentlegreg.org
 *
 * @author David Mongeau-Petitpas <dmp@commun.ca>
 * @package Gregory
 * @version 0.1
 *
 */

/**
 *
 * Define the path where the Gregory.php file is located.
 * This constants is used in the Gregory class.
 *
 * @name PATH_GREGORY
 *
 */
define('PATH_GREGORY',dirname(__FILE__));

/**
 *
 * Class Gregory
 *
 * Main class for Gregory framework. The class should be instantiated and can be used statically
 * to retrieve current instance from anywhere in your code.
 *
 * Minimal example
 * <code>
 * require Gregory.php;
 * 
 * $config = array(
 *     'path' => array(
 *         'pages' => dirname(__FILE__).'/pages'
 *     )
 * );
 * $Gregory = new Gregory($config);
 * 
 * // Gregory will look for these php files in $config['path']['pages']
 * $Gregory->addRoutes(array(
 *    '/' => 'home.php',
 *    '/about.html' => 'about.php'
 * ));
 * 
 * $Gregory->boostrap();
 * $Gregory->run();
 * $Gregory->render();
 * </code>
 *
 * @package Gregory
 * @version 0.1
 *
 */
class Gregory {
	
	/**
	 * Static variable the contain the current instance of Gregory
	 * @var Gregory
	 * @access protected
	 * @static
	 */
	protected static $_app;
	/**
	 * Boolean that indicate if Gregory has already been statically initialized
	 * @var boolean
	 * @access protected
	 * @static
	 */
	protected static $_initialized = false;
	/**
	 * Shared memory
	 * @access protected
	 * @static
	 */
	protected static $_sharedMemory;
	/**
	 * Array that caches path for the absolutePath method
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $_paths;
	
	
	/**
	 * Default configuration that is extended when you instantiate a new Gregory object
	 * @var array
	 * @access public
	 * @static
	 */
	public static $defaultConfig = array(
		'layout' => null,
		'path' => array(
			'pages' => null,
			'plugins' => null
		),
		'route' => array(
			'path' => '/',
			'wildcard' => '*',
			'urlDelimiter' => '/',
			'paramsPrefix' => ':'
		),
		'debug' => array(
			'stats' => true
		),
		'error' => array(
			'404' => null,
			'500' => null
		),
	);
	
	
	/**
	 * Boolean that indicates if the current instance of Gregory is initialized
	 * @var boolean
	 * @access protected
	 */
	protected $_bootstrapped = false;
	/**
	 * The config array for the current instance
	 * @var array
	 * @access protected
	 */
	protected $_config = array();
	
	
	/**
	 * Route for the current request
	 * @var array
	 * @access protected
	 */
	protected $_route;
	/**
	 * Routes for the current instance
	 * @var array
	 * @access protected
	 */
	protected $_routes = array();
	/**
	 * Params for the current request
	 * @var array
	 * @access protected
	 */
	protected $_params = array();
	
	
	/**
	 * Contains all the errors for to the current instance
	 * @var array|null
	 * @access protected
	 */
	protected $_errors = null;
	/**
	 * Contains all the messages for to the current instance
	 * @var array
	 * @access protected
	 */
	protected $_messages = array();
	/**
	 * Contains the statistics for the current instance
	 * @var array
	 * @access protected
	 */
	protected $_stats = array();
	
	
	/**
	 * Contains all actions (hooks)
	 * @var array
	 * @access protected
	 */
	protected $_actions;
	/**
	 * Contains action hooks
	 * @var array
	 * @access protected
	 */
	protected $_filters;
	
	
	/**
	 * Contains path to bootstrap scripts added to the current instance
	 * @var array
	 * @access protected
	 */
	protected $_bootstraps = array();
	
	
	/**
	 * Contains all plugins added to the current instance
	 * @var array
	 * @access protected
	 */
	protected $_plugins = array();
	/**
	 * Contains all plugins that need to be initialized in bootstrap method
	 * @var array
	 * @access protected
	 */
	protected $_pluginsBootstrap = array();
	/**
	 * Contains all plugins that are initialized when used
	 * @var array
	 * @access protected
	 */
	protected $_pluginsStandby = array();
	
	
	/**
	 * Contains the path to the current layout file
	 * @var string
	 * @access protected
	 */
	protected $_layout;
	/**
	 * Contains the path to the current page file
	 * @var string
	 * @access protected
	 */
	protected $_page;
	/**
	 * Contains all data that will be parsed into views when rendering the page
	 * @var array
	 * @access protected
	 */
	protected $_data = array();
	/**
	 * Contains all javascript files that will be included in current page
	 * @var array
	 * @access protected
	 */
	protected $_scripts = array();
	/**
	 * Contains all css files that will be included in current page
	 * @var array
	 * @access protected
	 */
	protected $_stylesheets = array();
	
	
	/**
     * Constructor function
     *
     * Load the configuration and initialize static Gregory
     *
     * @param string|null $config The config array for the current instance (will extend $_defaultConfig)
     */
	public function __construct($config = array()) {
		
		try {
			
			//Put instance in static property
			self::set($this);
			
			
			//Execution time
			$this->_setStats('startTime',(float) array_sum(explode(' ',microtime())));
			
			
			//Set global configuration
			$config = array_merge(self::$defaultConfig,$config);
			$this->setConfig($config);
			if($this->getConfig('layout')) {
				$this->setLayout($this->getConfig('layout'));
			}
			
			//Retrieve errors from session
			$this->_errors = $this->session('errors');
			$this->_messages = $this->session('messages');
			
			//Clean errors from session
			$this->session('errors',null);
			$this->session('messages',null);
			
			//Initialize static Gregory
			self::init();
			
			
			//Update usage stats
			$this->_refreshUsageStats();
			
		} catch(Exception $e) {
			$this->catchError($e);
		}
		
	}
	
	
	/**
	 * This is a static method to initialize Gregory class.
	 * 
	 * It should be used to initialize things that need to be globally available
	 * to all Gregory instances
	 */
	public static function init() {
		
		try {
		
			if(!self::$_initialized) {
				
				
				self::_bootstrapSharedMemory();
				
				
				self::$_initialized = true;
			}
		
		} catch(Exception $e) {
			self::error(500);
		}
	}
	
	/**
     * Initialize the current instance of Gregory. It will bootstrap plugins and user defined
     * bootstrap scripts.
     */
	public function bootstrap() {
		
		try {
			
			$this->_bootstrapPlugins();
			
			foreach($this->_bootstraps as $bootstrap) {
				include $bootstrap;	
			}
			
			$this->doAction('bootstrap');
			$this->_bootstrapped = true;
			
			$this->_refreshUsageStats();
		} catch(Exception $e) {
			$this->catchError($e);
		}
	}
	
	/**
     * Route the current request and run the associated controller
     *
     * This method will find wich route match the current url. Then it will run the page associated
     * to this route
     * 
     * Minimal example
	 * <code>
	 * $app->run();
	 * //Will use $_SERVER['REQUEST_URI'] for routing
	 * </code>
	 * 
     * Example usage when specifying the current url
	 * <code>
	 * $app->run('/about.html');
	 * </code>
     *
     * @param string $url The url of the current request. If not specified, will use $_SERVER['REQUEST_URI']
     */
	public function run($url = null) {
		
		try {
			$url = !isset($url) ? $_SERVER['REQUEST_URI']:$url;
			
			//Route
			if($this->hasRoutes()) {
				$route = $this->route($url);
				$this->setRoute($route);
				$params = array();
				if(is_array($route) && sizeof($route['route'])) {
					
					if(isset($route['params']) && sizeof($route['params'])) {
						$this->setParams($route['params']);
						$params = $route['params'];
					}
					
					if(isset($route['route']['layout'])) {
						$this->setLayout($route['route']['layout']);
					}
					
					if(isset($route['route']['function'])) {
						$return = call_user_func_array($route['route']['function'],array($route));
						if($return === false) $this->error(404);
					}
					
					if(isset($route['route']['page'])) {
						$this->setPage($route['route']['page']);
						$this->runPage();
					}
					
				} else if($route === false) {
					
					$this->error(404);
					
				}
			}
			
			$this->doAction('run');
			
			$this->_refreshUsageStats();
			
		} catch(Exception $e) {
			$this->catchError($e);
		}
		
	}
	
	/**
     * Render the current page
     *
     * This method will take the layout(if you specified one) and insert the contents in it. It will
     * also replace any template variables in your views.
     * 
     * Minimal example
	 * <code>
	 * $app->render();
	 * </code>
	 * 
     * Example usage when first parameter is set to true
	 * <code>
	 * $return = $app->render(true);
	 * echo $return;
	 * </code>
     *
     * @param boolean $return If set to true, the function will return the rendered content instead of echoing.
     * @return mixed If the parameter $return is set to true, it returns the rendered content
     */
	public function render($return = false) {
		
		try {
			
			$data = $this->getData();
			$data['head'] = $this->dofilter('render.head',$this->getHead());
			$data['scripts'] = $this->dofilter('render.scripts',$this->getScriptsAsHTML());
			$data['stylesheets'] = $this->dofilter('render.stylesheets',$this->getStylesheetsAsHTML());
			$content = $this->dofilter('render.content',$this->getContent());
			
			
			if($layout = $this->getLayout()) {
				$content = self::template($layout,array_merge($this->getData(),array('content'=>$content)),false);
				$content = self::template($content,$data);
			}
			
			$this->doAction('render');
			
			$this->_refreshUsageStats();
			
			if(!$return) echo $content;
			else return $content;
			
			if($this->getConfig('debug.stats') === true) $this->printStats();
			
		} catch(Exception $e) {
			$this->catchError($e);
		}
	}
	
	/**
	 * Update Gregory config
	 *
	 * When only one parameter is specified and it is an array, it will be used to set the config
	 * array for the current instance. If to parameters are specified and the first is a string
	 * it will be used to specify a single config property.
	 *
	 * Here is an example for setting the whole config array
	 * <code>
	 * $app->setConfig(array('layout'=>'/layout.html'));
	 * </code>
	 *
	 * Here is an exmaple for setting a single property
	 * <code>
	 * $app->setConfig('layout','/layout.html');
	 * </code>
	 *
	 * @param string|array $config If this is the only parameters and it is an array, it will be used to override the config of the current instance
	 * @param mixed $value The value for the config property specified in $config param
	 */
	public function setConfig($config, $value = null) {
		if(!isset($value) && is_array($config)) $this->_config = $config;
		elseif(isset($value) && !is_array($config)) $this->_config[$config] = $value;
		
	}
	
	/**
	 * Get Gregory config
	 *
	 * When no parameter is specified, this method will return the whole config array. If parameter
	 * one is a string it will return the value of the specified property.
	 *
	 * Here is an example for getting the whole config array
	 * <code>
	 * $config = $app->getConfig();
	 * //$config contains the whole $config array
	 * </code>
	 *
	 * Here is an exmaple for getting a single property
	 * <code>
	 * $config = $app->getConfig('layout');
	 * //$config contains '/layout.html'
	 *
	 * $config = $app->getConfig('route.path');
	 * //$config contains '/'
	 *
	 * //This is equivalent to $app->getConfig('route.path');
	 * $config = $app->getConfig();
	 * $config = $config['route']['path'];
	 * //$config contains '/'
	 * </code>
	 *
	 * @param string $key The key for a single property in config array. You can use the «.» to navigate trough array level (see examples)
	 * @return mixed The config array or the value of the specified property. If the specified property is not found, return null
	 */
	public function getConfig($key = null) {
		if(!isset($key)) return $this->_config;
		elseif(isset($this->_config[$key])) return $this->_config[$key];
		elseif(!empty($key) && strpos($key,'.') !== false) {
			$parts = explode('.',$key);
			$lastPart = $this->_config;
			for($i = 0; $i < sizeof($parts); $i++) {
				if(isset($lastPart[$parts[$i]])) $lastPart = $lastPart[$parts[$i]];
				else return null;
			}
			return $lastPart;
		}
		return null;
	}
	
	
	/**
	 *
	 * Set current page file
	 *
	 */
	public function setPage($page, $run = false) {
		$path = $this->getConfig('path.pages').'/';
		$filename = self::nameToFilename($page);
		$this->_page = self::absolutePath($filename,array($path));
		if($run) $this->runPage();
	}
	
	/**
	 *
	 * Get current page file
	 *
	 */
	public function getPage() {
		return $this->_page;
	}
	
	/**
	 *
	 * Set content
	 *
	 */
	public function setContent($value) {
		$this->_content = $value;
	}
	
	/**
	 *
	 * Get content
	 *
	 */
	public function getContent() {
		return $this->_content;
	}
	
	/**
	 *
	 * Set Layout
	 *
	 */
	public function setLayout($value) {
		$this->_layout = $value;
	}
	
	/**
	 *
	 * Get Layout
	 *
	 */
	public function getLayout() {
		return $this->_layout;
	}
	
	public function setData($data, $value = null) {
		if(!is_array($data) && isset($value)) $this->_data[$data] = $value;
		else if(is_array($data)) $this->_data = $data;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	/**
	 *
	 * Execute the php scrit of the current page
	 *
	 */
	public function runPage() {
		
		$page = $this->dofilter('run.page',$this->getPage());
		
		$data = $this->getData();
		
		$content = self::renderFile($page,array('data'=>$data));
		
		$data = array_merge($data,$this->getData());
		
		if(isset($content) && !empty($content)) {
			$content = self::template($content,$data);
			$this->setContent($this->dofilter('run.content',$content));
		}	
	}
	
	public function renderFile($file,$vars = array()) {
		
		if(sizeof($vars) && is_array($vars)) extract($vars);
		
		ob_start();
		require	$file;
		$content = ob_get_clean();
		
		return $content;
		
	}
	
	public function getHead() {
		return $this->_head;
	}
	
	public function setHead($head) {
		$this->_head = $head;
	}
	
	public function addScript($script) {
		$this->_scripts[] = $script;
	}
	
	public function addStylesheet($stylesheet) {
		$this->_stylesheets[] = $stylesheet;
	}
	
	public function clearScript() {
		$this->_scripts = array();
	}
	
	public function clearStylesheet() {
		$this->_stylesheets = array();
	}
	
	public function getScripts() {
		return $this->_scripts;
	}
	
	public function getStylesheets() {
		return $this->_stylesheets;
	}
	
	public function getScriptsAsHTML() {
		
		$lines = array();
		foreach($this->_scripts as $script) {
			$lines[] = '<script type="text/javascript" src="'.$script.'"></script>';
		}
		return implode("\n",$lines);
	}
	
	public function getStylesheetsAsHTML() {
		
		$lines = array();
		foreach($this->_stylesheets as $stylesheet) {
			$lines[] = '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'"/>';
		}
		return implode("\n",$lines);
	}
	
	
	/*
	 *
	 * Routes
	 *
	 */
	public function route($url,$defaults = array()) {
			
		$routes = $this->getRoutes();
		$delimiter = $this->getConfig('route.urlDelimiter');
		$paramPrefix = $this->getConfig('route.paramsPrefix');
		$routeWildcard = $this->getConfig('route.wildcard');
		
		$url = strpos($url,'?') !== false ? substr($url,0,strpos($url,'?')):$url;
		$url = trim($url,$delimiter);
		
		$path = $this->getConfig('route.path');
		
		if(isset($path) && $path != $delimiter) {
			$path = trim($path,$delimiter);
			if(!empty($path) && substr($url.$delimiter,0,strlen($path)+1) == $path.$delimiter) {
				$url = substr($url.'/',	strlen($path));
				$url = trim($url,$delimiter);
			}
			
		}
		
		$urlParts = explode($delimiter,$url);
		
		if(isset($routes) && sizeof($routes)) {
			foreach($routes as $regex => $route) {
				
				$match = true;
				if(isset($route['params']) && is_array($route['params'])) $params = $route['params'];
				else $params = array();
				
				for($i = 0; $i < sizeof($route['parts']); $i++) {
					
					$wildcard = false;
					$u = isset($urlParts[$i]) ? $urlParts[$i]:null;
					$part = $route['parts'][$i];
					
					if(!isset($u)) {
						$match = false;
					} else if(substr($part,0,1) == $paramPrefix && strlen($part) > 1) {
						if(preg_match('/^\\'.$paramPrefix.'([^\{]+)\{([^\}]+)\}$/',$part,$matches)) {
							if(preg_match('/'.$matches[2].'/',$u)) $params[$matches[1]] = $u;
							else $match = false;
						} elseif(strpos($part,'.') !== false) {
							$pos = strpos($u,'.');
							if($pos === false) $match = false;
							else {
								$uext = strtolower(substr($u,$pos));
								$u = substr($u,0,$pos);
								
								$pos = strrpos($part,'.');
								$ext = strtolower(substr($part,$pos));
								$name = substr($part,1,$pos-1);
								
								if($ext != $uext) $match = false;
								else {
									if(preg_match('/^([^\{]+)\{([^\}]+)\}$/',$name,$matches)) {
										if(preg_match('/'.$matches[2].'/',$u)) $params[$matches[1]] = $u;
										else $match = false;
									} else {
										$params[$name] = $u;
									}
								}
							}
							
						} else {
							if(preg_match('/^\\'.$paramPrefix.'([^\{]+)\{([^\}]+)\}$/',$part,$matches)) {
								if(preg_match('/'.$matches[2].'/',$u)) $params[$matches[1]] = $u;
								else $match = false;
							} else {
								$name = substr($part,1);
								$params[$name] = $u;
							}	
						}
					} else if($part == $routeWildcard) {
						$wildcard = array_slice($urlParts,$i);
						$params['wildcard'] = implode($delimiter,$wildcard);
						$wildcard = true;
					} else if(!preg_match('/^'.preg_quote($part).'$/i',$u,$matches)) {
						$match = false;
					}
					
				}
				
				if(sizeof($route['parts'])  != sizeof($urlParts) && !$wildcard) $match = false;
				
				if($match) {
					
					$return = array(
						'url' => $url,
						'regex' => $regex,
						'route' => $route,
						'params' => $params
					);
					
					return $return;
					
				}
				
			}
			return false;
		}
		
		return null;
			
	}
	
	public function hasRoutes() {
		return !isset($this->_routes) || !sizeof($this->_routes) ? false:true;
	}
	
	public function getRoute() {
		return $this->_route;
	}
	
	public function setRoute($route) {
		$this->_route = $route;
	}
	
	public function getRoutes() {
		return $this->_routes;
	}
	
	public function addRoute($routes,$value = null) {
		$routes = is_array($routes) ? $routes:array($routes=>$value);
		
		$delimiter = $this->getConfig('route.urlDelimiter');
		
		foreach($routes as $regex => $route) {
			$route = (is_array($route) ? $route:array('page'=>$route));
			$route['parts'] = explode($delimiter,trim($regex,$delimiter));
			$this->_routes[$regex] = $route;
		}
	}
	
	public function clearRoute() {
		$this->_routes = array();
	}
	
    public function setParams($name,$value = null) {
        if(is_array($name)) $this->_params = $name;
		else if(isset($value)) $this->_params[$name] = $value;
    }

    public function getParams($name = null) {
        if(!isset($name)) return $this->_params;
		else return isset($this->_params[$name]) ? $this->_params[$name]:null;
    }
	
    public function __get($name) {
		$res = $this->getPlugin($name);
		
        if($res === null && $this->hasPlugin($name)) {
			$res = $this->initPlugin($name);
			$this->setPlugin($name,$res);
		}
		
		return $res;
    }
	
	
	
	
	/*
     *
     * Gregory session
     *
     */
	
	
	public function session($key) {
		
		if(class_exists('Zend_Session')) Zend_Session::start();
		else session_start();
		
		if(func_num_args() == 2) {
			if(class_exists('Zend_Session')) {
				$session = new Zend_Session_Namespace('Gregory');
				$session->$key = func_get_arg(1);
			} else {
				$_SESSION['Gregory_'.$key] = func_get_arg(1);
			}
		} else {
			if(class_exists('Zend_Session')) {
				$session = new Zend_Session_Namespace('Gregory');
				return isset($session->$key) ? $session->$key:null;
			} else {
				return isset($_SESSION['Gregory_'.$key]) ? $_SESSION['Gregory_'.$key]:null;
			}
		}
		
	}
	
	
	/**
	 *
	 * Update bootstrap file
	 *
	 */
	public function addBootstrap($file) {
		
		if(file_exists($file)) $this->_bootstraps[] = $file;
		
	}
	
	
	 /*
     *
     * Méthodes relatives aux plugins
     *
     */
    public function addPlugin($name, $config = array(), $standby = true) {
		
		$plugin = array();
		$plugin['name'] = strpos($name,'/') !== false ? substr($name,0,strpos($name,'/')):$name;
		$plugin['config'] = $config;
		$name = $plugin['name'];
		
		$plugin = $this->doFilter('plugin.add',$plugin);
		
        if($standby) $this->_pluginsStandby[$name] = $plugin;
		else if(!$this->_bootstrapped) $this->_pluginsBootstrap[$name] = $plugin;
		else {
			$plugin = require $this->_getPluginPath($plugin['name']);
			$this->_plugins[$plugin['name']] = $plugin;
		}
		
    }
	
    public function setPlugin($name,$value) {
        $this->_plugins[$name] = $value;
    }

    public function getPlugin($name) {
        return isset($this->_plugins[$name]) ? $this->_plugins[$name]:null;
    }

    public function hasPlugin($name) {
        return isset($this->_plugins[$name]) || isset($this->_pluginsStandby[$name]) ? true:false;
    }
	
	public function initPlugin($name) {
		if(isset($this->_pluginsStandby[$name])) {
			$config = $this->_pluginsStandby[$name]['config'];
			$plugin = require $this->_getPluginPath($this->_pluginsStandby[$name]['name']);
			unset($this->_pluginsStandby[$name]);
			return $plugin;
		}
		
		return null;
	}
	
	protected function _bootstrapPlugins() {
		if(isset($this->_pluginsBootstrap) && sizeof($this->_pluginsBootstrap)) {
			foreach($this->_pluginsBootstrap as $name => $plugin) {
				$config = $this->_pluginsBootstrap[$name]['config'];
				$plugin = require $this->_getPluginPath($plugin['name']);
				unset($this->_pluginsBootstrap[$name]);
				$this->_plugins[$name] = $plugin;
			}
		}
	}
	
	protected function _getPluginPath($name) {
		$path = $this->getConfig('path.plugins');
		return self::absolutePath(self::nameToFilename($name),array($path,$path.'/'.$name));	
	}
	
	
	
	/*
     *
     * Hook system
     *
     */
	
	public function addAction($action, $function, $params = array()) {
		
		if(!isset($this->_actions[$action])) $this->_actions[$action] = array();
		$this->_actions[$action][] = array(
			'function' => $function,
			'params' => $params
		);
		
	}
	
    public function doAction($action,$params = array()) {
		if(isset($this->_actions[$action])) {
			foreach($this->_actions[$action] as $a) {
				//if(sizeof($a['params'])) {
					//call_user_func_array($a['function'],$a['params']);
				if(sizeof($params)) {
					call_user_func_array($a['function'],$params);
				} else {
					call_user_func_array($a['function'],array());
				}
			}
		}
    }
	
	
	public function addFilter($filter, $function) {
		
		if(!isset($this->_filters[$filter])) $this->_filters[$filter] = array();
		$this->_filters[$filter][] = array(
			'function' => $function
		);
		
	}
	
    public function doFilter($filter,$input) {
		if(isset($this->_filters[$filter])) {
			foreach($this->_filters[$filter] as $a) {
				$input = call_user_func($a['function'],$input);
			}
		}
		
		return $input;
    }
	
	/*
     *
     * Messages system
     *
     */
	public function addMessage($message, $category = 'default', $data = array()) {
		
		$this->_messages[$category][] = array_merge(array(
			'category' => $category,
			'message' => $message
		),$data);
		$this->session('messages',$this->_messages);
		
	}
	
	public function getMessages($clean = true) {
		
		$messages = array();
		foreach($this->_messages as $category => $message) {
			$messages[] = $message;
		}
		
		if($clean) $this->cleanMessages();
		
		return $messages;
		
	}
	
	public function getMessagesByCategory($category,$clean = true) {
		
		$messages = array();
		foreach($this->_messages[$category] as $message) {
			$messages[] = $message['message'];
		}
		
		if($clean) $this->cleanMessages($category);
		
		return $messages;
		
	}
	
	public function hasMessages($category = null) {
		
		if(isset($category) && isset($this->_messages[$category]) && sizeof($this->_messages[$category])) {
			return true;
		} else if(!isset($category) && isset($this->_messages) && sizeof($this->_messages)) {
			return true;
		} else return false;
		
	}
	
	public function cleanMessages($category = null) {
		if(!empty($category)) {
			if(isset($this->_messages[$category])) {
				$this->_messages[$category] = array();
				$this->session('messages',$this->_messages);
			}
		} else {
			$this->_messages = array();
			$this->session('messages',$this->_messages);
		}	
	}
	
	public function getMessagesAsHTML($category =  null, $clean = true,$opts = array()) {
		
		if(!$this->hasMessages($category)) return;
		
		$opts = array_merge(array(
			'alwaysList' => false
		),$opts);
		
		$items = isset($category) ? $this->getMessagesByCategory($category,$clean):$this->getMessages($clean);
		
		if(sizeof($items) > 1 || (sizeof($items) == 1 && $opts['alwaysList'])) {
			$html = array();
			foreach($items as $item) $html[] = '<li>'.(isset($category) ? $item:$item['message']).'</li>';
			return '<ul class="'.($item['category'] ? $item['category']:$category).'">'.implode("\n",$html).'</ul>';
		} else {
			
			return isset($category) ? $items[0]:$items[0]['message'];
		}
		
	}
	
	
	/*
     *
     * Errors handling
     *
     */
	
	public function catchError($exception) {
		
		if(is_a($exception,'Zend_Exception') || $exception->getCode() == 500) {
			$this->error(500);
		} else {
			$this->addError($exception->getMessage(), $exception->getCode(), $exception);
		}
		
	}
	
	public function addError($error, $type = null, $exception = null) {
		
		$data = array();
		if($type) $data['type'] = $type;
		if($exception) $data['exception'] = $exception;
		
		$this->addMessage($error,'error',$data);
		
	}
	
	public function getErrors($cleanAfter = true) {
		
		$errors = $this->getMessagesByCategory('error');
		
		if($cleanAfter) {
			$this->cleanMessages('error');
		}
		
		return $errors;
		
	}
	
	public function displayErrors($cleanAfter = true,$opts = array()) {
		
		return $this->getMessagesAsHTML('error',$cleanAfter,$opts);
		
	}
	
	public function hasErrors() {
		
		return $this->hasMessages('error');
		
	}
	
	public function error($code = 500) {
		
		$this->doAction('error.'.$code);
		
		//header("HTTP/1.0 404 Not Found");
		header('Content-type: text/html; charset="utf-8"');
		
		$file = $this->getConfig('error.'.$code);
		//if(file_exists($file)) echo file_get_contents($file);
		//exit();
		
		$this->setPage($file,true);
		
		
	}
	
	/*
     *
     * Redirect with message
     *
     */
	public function redirectWithErrorMessage($url,$message) {
		
		$this->addMessage($message,'error');
		
		Gregory::redirect($url);
	
	}
	
	public function redirectWithSuccessMessage($url,$message) {
		
		$this->addMessage($message,'success');
		
		Gregory::redirect($url);
	
	}

	/*
	 *
	 * Stats
	 *
	 */
	protected function _setStats($data, $value = null) {
		if(!isset($value) && is_array($data)) $this->_stats = $data;
		elseif(isset($value) && !is_array($data)) $this->_stats[$data] = $value;
		
	}
	
	protected function _refreshUsageStats() {
		//$this->_setStats('maxMemory',round(memory_get_peak_usage(true)/(1024*1024),2).' mb');
		//$this->_setStats('maxMemory',memory_get_peak_usage(true).' mb');
		$this->_setStats('maxMemory',round(memory_get_peak_usage()/1024,2).' kb');
		$this->_setStats('endTime',(float) array_sum(explode(' ',microtime())));
		$stats = $this->getStats();
		$this->_setStats('executionTime',round(($stats['endTime'] - $stats['startTime'])*1000,2).' msec.');
	}
	
	public function getStats($key = null) {
		if(!isset($key)) return $this->_stats;
		elseif(isset($this->_stats[$key])) return $this->_stats[$key];
		elseif(!empty($key) && strpos($key,'.') !== false) {
			$parts = explode('.',$key);
			$lastPart = $this->_stats;
			for($i = 0; $i < sizeof($parts); $i++) {
				if(isset($lastPart[$parts[$i]])) $lastPart = $lastPart[$parts[$i]];
				else return null;
			}
			return $lastPart;
		}
		return null;
	}
	
	public function printStats() {
		
		$stats = $this->getStats();
		
		unset($stats['startTime']);
		unset($stats['endTime']);
		
		echo '<!--'."\n\n";
		echo '    Gregory Stats'."\n\n";
		$content = print_r($stats,true);
		echo substr($content,8,strlen($content)-10);
		echo "\n".'-->';
	}
	
	
	
	/*
     *
     * Méthodes statiques pour un accès global à l'application
     *
     */
    public static function get() {
        return self::$_app;
    }

    public static function set(&$app) {
        self::$_app = $app;
    }
	
	
	
	
	/*
	 *
	 * Statics methods for shared memory
	 *
	 */
	protected static function _bootstrapSharedMemory() {
		
		if(!function_exists('shm_attach') || !function_exists('sem_acquire')) return false;
		
		$key = ftok(__FILE__,'g');
		self::$_sharedMemory = array(
			'key' => $key,
			'shm' => shm_attach($key, 50000),
			'mutex' => sem_get($key, 1),
			'data' => array()
		);
		
		self::refreshSharedMemory();
		
	}
	
	protected static function refreshSharedMemory($key = null) {
		
		sem_acquire(self::$_sharedMemory['mutex']);
		$data = @shm_get_var(self::$_sharedMemory['shm'], self::$_sharedMemory['key']);    
		sem_release(self::$_sharedMemory['mutex']);
		
		$data = @unserialize($data);
		
		self::$_sharedMemory['data'] = isset($data) && sizeof($data) ? $data:array();
	}
	
	protected static function getSharedData($key = null) {
		
		if(!isset($key)) return self::$_sharedMemory['data'];
		else if(isset($key) && isset(self::$_sharedMemory['date'][$key])) return self::$_sharedMemory['data'][$key];
		else return null;
	}
	
	protected static function setSharedData($data, $value = null) {
		
		if(isset($value)) {
			self::$_sharedMemory['data'][$data] = $value;
		} else {
			self::$_sharedMemory['data'] = $data;
		}
		
		$data = serialize(self::$_sharedMemory['data']);
		
		sem_acquire(self::$_sharedMemory['mutex']);
		shm_put_var(self::$_sharedMemory['shm'], self::$_sharedMemory['key'], $data);
		sem_release(self::$_sharedMemory['mutex']);
		
		
	}
	
	
	
	/*
     *
     * Méthodes statiques utilitaire
     *
     */
	
	public static function template($layout, $data = array(),$clean = true) {
		if(strlen($layout) < 1024 && file_exists($layout)) {
			$layout = Gregory::get()->renderFile($layout,$data);
		}
		$html = $layout;
		if(isset($data) && is_array($data)) {
			foreach($data as $key => $content) {
				if(is_array($content)) {
					
				} else {
					$html = str_replace('%{'.strtoupper($key).'}',$content,$html);
				}
			}
		}
		if($clean) $html = preg_replace('/\%\{[^\}]+\}/','',$html);
		
		return $html;		
	}
	
    public static function nameToFilename($name, $ext = 'php') {
    	if(strpos($name,'.') === false) return $name.'.'.$ext;	
		else return $name;
    }
	
    public static function absolutePath($file,$paths = array()) {
		
		if(isset(self::$_paths[$file])) return self::$_paths[$file];
		
		$currentPath = dirname(__FILE__);
    	if(!in_array($currentPath, $paths)) $paths[] = $currentPath;
		foreach($paths as $path) {
			$path = rtrim($path,'/');
			$path = $path.'/'.$file;
			if(file_exists($path)) {
				self::$_paths[$file] = $path;
				return $path;
			}
		}
		
		if(file_exists($file)) {
			self::$_paths[$file] = $file;
			return $file;
		}
		
		return false;
    }
	
	
	/*
     *
     * Request functions
     *
     */
	
	public static function redirect($url, $code = 301) {
		
		header('Location: '.$url,true,$code);
		exit();
			
	}
	
	public static function isAJAX() {
		
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return true;
		}
		
		return false;
			
	}
	
	public static function JSON($obj, $return = false) {
		
		$json = json_encode($obj);
		
		if(!$return) {
			header('Content-type: text/plain; charset="utf-8"',true);
			echo $json;
			exit();
		} else {
			return $json;
		}
			
	}
	
	
	
	/*
     *
     * Autoload class
     *
     */
	
	public static function _autoload($class) {
		
		if(strtolower(substr($class,0,4)) == 'zend') {
		
			/*//Zend Framework
			$paths = array();
			if(defined(PATH_ZEND)) $paths[] = PATH_ZEND;
			$path = Gregory::absolutePath('Zend/', $paths);
			
			if($path) $path = trim(str_replace('/Zend', '', $path), '/');
			else return false;
			
			$file = '/'.$path.'/'.str_replace('_','/',$class).'.php';
			if (!file_exists($file)) return false;*/
			$file = str_replace('_','/',$class).'.php';
			require_once $file;
			
		} else {
			return false;
		}	
	}
		
}


/**
 *
 * Define the path to your version of Zend that will be used for autoloading.
 *
 * @name PATH_ZEND
 *
 */
define('PATH_ZEND',PATH_GREGORY);

set_include_path(get_include_path().PATH_SEPARATOR.PATH_ZEND);
spl_autoload_register(array('Gregory','_autoload'));
