<?php

if(!defined(PATH_GREGORY)) define('PATH_GREGORY',dirname(__FILE__));
if(!defined(PATH_ZEND)) define('PATH_ZEND',PATH_GREGORY);

//Class Autoloader
function autoloadZend($class) {
	if(strtolower(substr($class,0,4)) == 'zend') {
		$file = PATH_ZEND.'/'.str_replace('_','/',$class).'.php';
		if (!file_exists($file)) return false;
		include $file;
	} else {
		return false;
	}
}
spl_autoload_register('autoloadZend');



class Gregory {
	
	protected static $_app;
	
	protected $_config = array();
	protected $_routes = array();
	
	protected $_plugins = array();
	protected $_pluginsBootstrap = array();
	protected $_pluginsStandby = array();
	
	protected $_page;
	protected $_content;
	protected $_data;
	
	public function __construct($config = array()) {
		
		$this->setConfig(array_merge($this->_config,$config));
		
	}
	
	
	public function bootstrap($modules = array()) {
		
		$this->bootstrapPlugins();
		
	}
	
	public function run($url = null) {
		
		$url = !isset($url) ? $_SERVER['REQUEST_URI']:$url;
		
		//Route
		$route = $this->route($_SERVER['REQUEST_URI']);
		if(is_array($route) && sizeof($route['route'])) {
			
			if(isset($route['route']['page'])) {
				$this->setPage($route['route']['page']);
			}
			
			if(isset($route['route']['layout'])) {
				$this->setConfig('layout', $route['route']['layout']);
			}
			
		} else if($route === false) {
			
			$this->error404();
			
		}
		
		if($page = $this->getPage()) {
			
			ob_start();
			include	$page;
			$content = ob_get_clean();
			
			if(isset($content) && !empty($content)) $this->setContent($content);
			
		}
		
	}
	
	public function render() {
		
		$data = $this->getData();
		$data['content'] = $this->getContent();
		
		
		if($layout = $this->getConfig('layout')) {
			$content = Gregory::template($layout,$data);
		} else {
			$content = $data['content'];
		}
		
		echo $content;
	}
	
	/*
	 *
	 * Config
	 *
	 */
	public function setConfig($config, $value = null) {
		if(!isset($value) && is_array($config)) $this->_config = $config;
		elseif(isset($value) && !is_array($config)) $this->_config[$config] = $value;
		
	}
	
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
	
	
	/*
	 *
	 * Page
	 *
	 */
	public function setPage($page) {
		$path = $this->getConfig('pagesPath').'/';
		$filename = Gregory::nameToFilename($page);
		if(file_exists($filename)) $this->_page = $filename;
		else if(file_exists($path.$filename)) $this->_page = $path.$filename;
	}
	
	public function getPage() {
		return $this->_page;
	}
	
	public function setContent($content) {
		$this->_content = $content;
	}
	
	public function getContent() {
		return $this->_content;
	}
	
	public function setData($data) {
		$this->_data = $data;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	
	/*
	 *
	 * Routes
	 *
	 */
	public function route($url,$defaults = array()) {
			
		$routes = $this->getRoutes();
		$url = '/'.trim($url,'/');
		$url = strpos($url,'?') !== false ? substr($url,0,strpos($url,'?')):$url;
		
		if(isset($routes) && sizeof($routes)) {
			foreach($routes as $regex => $route) {
				
				$regex = preg_quote($regex,'/');
				if(strpos($regex,'.') === false && strpos($regex,'?') === false) $regex .= '\/?';
				
				if(preg_match('/^'.$regex.'$/i',$url,$matches)) {
					
					$return = array(
						'url' => $url,
						'regex' => $regex,
						'route' => (is_array($route) ? $route:array('page'=>$route))
					);
					if(isset($matches[1]) && sizeof($matches[1])) $return['params'] = $matches[1];
					
					return $return;
					
				}
			}
			return false;
		}
		
		return null;
			
	}
	
	public function getRoutes() {
		return $this->_routes;
	}
	
	public function addRoute($routes,$value = null) {
		$routes = is_array($routes) ? $routes:array($routes=>$value);
		
		foreach($routes as $regex => $route) {
			$this->_routes[$regex] = $route;
		}
	}
	
	public function clearRoute() {
		$this->_routes = array();
	}
	
	
	 /*
     *
     * Méthodes relatives aux plugins
     *
     */
    public function addPlugin($name, $file = null, $standby = true) {
		
		$path = $this->getConfig('pluginsPath');
		
		$plugin = array();
		if($file === null) $plugin['file'] = $path.'/'.$name.'.php';
		else $plugin['file'] = $path.'/'.$file;
		
        if($standby) $this->_pluginsStandby[$name] = $plugin;
		else $this->_pluginsBootstrap[$name] = $plugin;
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
			$plugin = include $this->_pluginsStandby[$name]['file'];
			unset($this->_pluginsStandby[$name]);
			return $plugin;
		}
		
		return null;
	}
	
	public function bootstrapPlugins() {
		if(isset($this->_pluginsBootstrap) && sizeof($this->_pluginsBootstrap)) {
			foreach($this->_pluginsBootstrap as $name => $plugin) {
				$plugin = include $plugin['file'];
				unset($this->_pluginsBootstrap[$name]);
				$this->_plugins[$name] = $plugin;
			}
		}
	}
	
	
	
	
	
	public function error404() {
		
		//header("HTTP/1.0 404 Not Found");
		header('Content-type: text/html; charset="utf-8"');
		
		echo file_get_contents($this->getConfig('error.404'));
		
		exit();
		
	}
	
	

    /*
     *
     * Méthodes magiques
     *
     */
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
     * Méthodes statiques utilitaire
     *
     */
	public static function template($layout, $data = array()) {
		if(strlen($layout) < 1024 && file_exists($layout)) {
			ob_start();
			include $layout;
			$layout = ob_get_clean();	
		}
		$html = $layout;
		foreach($data as $key => $content) {
			$html = str_replace('%{'.strtoupper($key).'}',$content,$html);
		}
		$html = preg_replace('/\%\{[^\}]+\}/','',$html);
		
		return $html;		
	}
	
    public static function nameToFilename($name) {
    	if(strpos($name,'.') === false) return $name.'.php';	
		else return $name;
    }
		
}