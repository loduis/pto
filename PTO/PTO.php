<?php

/**
 * gzdecode is for php 6
 */
if (!function_exists('gzdecode')) {
    function gzdecode($data, $length = 0) {
        return gzinflate(substr(substr($data, 10), 0, -8), $length);
    }
}

/**
 * Definde la ruta donde se encuentra el motor
 *
 * @const string
 */
if (!defined('PTO_DIR')) {
    define('PTO_DIR', dirname(__FILE__) .  DIRECTORY_SEPARATOR);
}

/**
 * PHP Template Object
 *
 * @author Loduis Madariaga
 *
 */
class PTO
{
    /**
     * Mantiene las variable globales
     *
     * @var array
     */
    private static $_GLOBALS = array();

    /**
     * Mantiene la configuracion
     *
     * @var array
     */
    private $_config = array(
        'Template'      => NULL, // directorio donde se guarda la plantillas
        'Cache'         => NULL, // directorio donde se guarda la cache
        'Plugins'       => NULL,  // directorio donde se guardan los plugins
        'CacheControl'  => NULL,  // http cache [private, public, no-store, no-cache]
        'Etag'          => FALSE, // validator etag bueno para contenido que cambian constantemente
        'IgnoreNoCache' => FALSE, // impide que se guarde el archivo compilado cuando hay tag nocache
        'Expire'        => 0   // expiracion del archivo local no del http
    );

    /**
     * Mantiene las functiones u objetos que proveen funcionalidades
     * @var array
     */
    private $_plugins = array();

    private $_cache = array();

    private $_cacheId  = NULL;

	private $_template = NULL;

    private $_isCached = FALSE;

    /**
     * Constructor de la clase
     *
     * @param array $config
     */
    public function  __construct(array $config = array())
    {
        $this->_addSeparator($config, 'Template');
        $this->_addSeparator($config, 'Plugins');
        $this->_addSeparator($config, 'Cache');
        // aseguramos que la cabecera de cache control tenga una fecha de expiracion
        if (!empty($config['CacheControl'])) {
            $this->setConfig('CacheControl', $config['CacheControl']);
            $config['CacheControl'] = $this->_config['CacheControl'];
        }
        // evitamos array_merge dado que nos intereza primero que todo
        // la configuracion que el usuario establece
        $this->_config = $config + $this->_config;
    }

    /**
     * Obtiene el contenido de un template, ya sea desde la cache, o compilada nuevamente
     *
     * @param string $name
     * @param mixed $cache_id
     * @return array
     */
    public function fetch($name)
    {
        $response = array();
        // si tenemos un directorio para la cache
        if ($this->_config['Cache']) {
            // Un validator etag se usa siempre para contenido
            // dinamico
            $is_cached = TRUE;
            if ($this->_config['Etag'] && !$this->_cacheId) {
                $this->isCached($name);
            }
            // es el archivo cacheado
            $cached_file = $this->_cacheId;
            // Es el archivo cacheado compilado, nuevo codigo php
            if (!($is_php = file_exists($cached_file . '.php')) && !$this->_isCached) {
                $cached_file = NULL;
            }
            // Obtenemos el codigo desde la cache
            if ($cached_file) {
                //necesita compilacion
                if ($is_php) {
                    $response['status'] = 200;
                    $response['body']   = $this->_getIncludeContents($cached_file . '.php');
                    $response['gzip']   = FALSE;
                    // si el no-store esta presente en el cache control esto no debe ser
                    // guardado en el archivo
                    $save = FALSE === strpos($this->_config['CacheControl'], 'no-store');
                    if (FALSE !== ($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
                        //si se puede obtener la cache como un arreglo
                        if ($save) {
                            $cache = $this->_cache;
                            if ($cache['gzip'] && $cache['body'] == $gzip) {
                                $response = $cache + $response;
                                $response['status']  = 304;
                                $save = FALSE;
                            } else {
                                $response['body'] = $gzip;
                                $response['gzip'] = TRUE;
                            }
                        } else {
                            $response['body'] = $gzip;
                            $response['gzip'] = TRUE;
                        }
                    }
                    if ($save) {
                        // generamos el nuevo validator
                        $response['validator'] = $this->_config['Etag'] ?
                                                    md5($response['body']) :
                                                    $_SERVER['REQUEST_TIME'];
                        //guardamos el cache
                        $this->_setCache($cached_file, $response);
                    }
                } else {
                    $response = $this->_cache;
                }
            }
        }
        //compilamos el template nuevamente
        if (!$response) {
            // dado que este proceso puede ocurrir una sola vez es mejor
            // separarlo en un archivo para guardar memoria
            require PTO_DIR . 'fetch.php';
        }
        return $response;
    }

    /**
     * Muestra el contenido de un template y aplica cabeceras para cachear
     * y compresion si esta activa la opcion para enviarlas
     *
     * @param string $name
     * @param mixed $cache_id
     * @return void
     */
    public function render($name = NULL)
    {
        if ($name === NULL) {
			$name = $this->_template;
		}
		$response = $this->fetch($name);
		// Negotiate whether to use compression.
		$accept_gzip = (int) isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
                        strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE;
		if ($response['gzip']) {
			if ($accept_gzip) {
                // append header not set
                // fuerza al proxi a guardar dos contenidos uno normal y otro comprimido
                header('Vary: Accept-Encoding', FALSE);
                //impide que el servidor la comprima nuevamente en caso de que halla filtros activo
				apache_setenv('no-gzip', '1');
				// $response['body'] is already gzip'ed, so make sure
				// zlib.output_compression does not compress it once more.
				ini_set('zlib.output_compression', '0');
				//se le dice al cliente que se le esta enviando un contenido comprimido
				header('Content-Encoding: gzip');
			} else {
                // Como todo los contenido se guardan comprimidos
                // para disminuir el I/O
				$response['body'] = gzdecode($response['body']);
			}
		}
        if ($this->_config['Cache'] && $this->_config['CacheControl']) {
            // hay una cache de la plantilla
			if ($response['status'] === 304) {
				// See if the client has provided the required HTTP headers.
				if ($this->_config['Etag']) {
					$client_validator = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
                                           stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) :
                                           FALSE;
					$server_validator = '"' . $response['validator'] . '-' . $accept_gzip . '"';
				} else {
				   $client_validator = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
                                           strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) :
                                           FALSE;
				   $server_validator = $response['validator'];
				}
				if ($client_validator && $client_validator == $server_validator) {
					header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
					// permite modificar la cabecera cache control nuevamente
					// esto es optimo cuando probar una cache por un corto tiempo
					// y despues que todo esta listo puedes aumentar el tiempo
					header('Cache-Control: ' . $this->_config['CacheControl']);
					// no hay envio de contenido
					return ;
				}
			}
			// HTTP/1.0 proxies does not support the Vary header, so prevent any caching
			// by sending an Expires date in the past. HTTP/1.1 clients ignores the
			// Expires header if a Cache-Control: max-age= directive is specified (see RFC 2616, section 14.9.3).
			header('Expires: Thu, 31 Dec 1970 05:00:00 GMT');
			// si se tiene una aplicacion cuyo contenido cambia constantemente
			// creo que los etag son la mejor opcion para validar el cambio
			if ($this->_config['Etag']) {
				// ojo si el archivo no esta comprimido se debe enviar un validator
				// si esta comprimido se debe enviar otro
				header('Etag: "' . $response['validator'] . '-' . $accept_gzip . '"');
			} else {
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $response['validator']) . ' GMT');
			}
			// cache control
			header('Cache-Control: ' . $this->_config['CacheControl']);
        }
        echo $response['body'];
    }

    /**
     * Asigna una variable disponible para todos los template
     *
     * @param mixed $var
     * @param mixed $value
     * @param bool $global
     * @return PTO
     */
    public static function globalAssign($var, $value = NULL)
    {
        if (is_array($var)) {
            foreach ($var as $name=>$value) {
                self::$_GLOBALS[$name] = $value;
            }
        } else {
            self::$_GLOBALS[$var] = $value;
        }
    }

	/**
     * Asigna una variable al objeto
     *
     * @param string $name
     * @param mixed $value
     * @param bool $escape
     * @return PTO
     */
	public function assign($name, $value, $escape = FALSE)
	{
		if ($escape) {
			$value = $this->_escape($value);
		}
		$this->$name = $value;
		return $this;
	}

    /**
     * Verifica si un idenficador de cache existe
     *
     * @param string $cache_id
     * @return bool
     */
    public function isCached($name, $cache_id = NULL)
    {
        $this->_cacheId  = $this->_getCacheId($name, $cache_id);
		$this->_template = $name;
		$this->_cache 	 = array();
        $this->_isCached = FALSE;
        if (file_exists($this->_cacheId)) {
            if (($this->_cache = $this->_getCache($this->_cacheId))) {
                $this->_isCached = TRUE;
                $this->_cache['status'] = 304;
                if (!$this->_config['Etag'] && $this->_config['Expire']) {
                    $time_zone = date_default_timezone_get();
                    date_default_timezone_set('GMT');
                    $expire = strtotime($this->_config['Expire'], $this->_cache['validator']);
                    date_default_timezone_set($time_zone);
                    if ($_SERVER['REQUEST_TIME'] > $expire) {
                        $this->_cache = array();
                        $this->_isCached = FALSE;
                    }
                }
            }
        }

        return $this->_isCached;
    }

    /**
     * Establece el valor de una configuracion
     *
     * @param string $name
     * @param string $value
     *
     * @return PTO
     */
    public function setConfig($name, $value)
    {
        if (array_key_exists($name, $this->_config)) {
            // aseguramos que la cabecera de cache control tenga una fecha de expiracion
            if ($name == 'CacheControl') {
                if (FALSE === strpos($value, 'max-age')) {
                    $value .= ', max-age=0';
                }
                if (FALSE === strpos($value, 'must-revalidate')) {
                    $value .= ', must-revalidate';
                }
            }
            $this->_config[$name] = $value;
        }

        return $this;
    }

    /**
     * Obtiene el valor de una configuracion
     *
     * @param string $name
     * @return mixed
     */
    public function getConfig($name)
    {
        return isset($this->_config[$name]) ? $this->_config[$name] : NULL;
    }

    /**
     * Permite hacer una llamada a los plugins
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (!isset($this->_plugins[$name])) {
            $plugin = $this->_addPlugin($name);
        } else {
            $plugin = $this->_plugins[$name];
        }
        $count = count($args);
        // cuando se crea una clase como plugin esta consume
        // mas memoria que la funcion como plugin
        // aplico este switch para mejorar performan
        if ($plugin instanceof PTO_Plugin) {
            switch ($count) {
                case 0: return $plugin->$name();
                case 1: return $plugin->$name($args[0]);
                case 2: return $plugin->$name($args[0], $args[1]);
                case 3: return $plugin->$name($args[0], $args[1], $args[2]);
                case 4: return $plugin->$name($args[0], $args[1], $args[2], $args[3]);
                default: return call_user_func_array(array($plugin, $name), $args);
            }
        } else {
            switch ($count) {
                case 0: return $plugin($this);
                case 1: return $plugin($args[0], $this);
                case 2: return $plugin($args[0], $args[1], $this);
                case 3: return $plugin($args[0], $args[1], $args[2], $this);
                case 4: return $plugin($args[0], $args[1], $args[2], $args[3], $this);
                default: $args[] = $this; return call_user_func_array($plugin, $args);

            }
        }
    }
    /**
     * Obtiene el valor de una variable global
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset (self::$_GLOBALS[$name])) {
            foreach (self::$_GLOBALS as $var=>$value) {
                if (!isset($this->$var)) {
                    $this->$var = $value;
                }
            }
            return $this->$name;
        } else {
            $debug = debug_backtrace(FALSE);
            trigger_error('Undefined property: ' . $name . ' in ' . $debug[0]['file'] .
                    ' on line ' . $debug[0]['line'], E_USER_ERROR);
        }
    }

    //-----------------------------------
    //          PRIVATE METHOD
    //-----------------------------------

    /**
     * Obtiene el contenido interpretado
     *
     * @param string $filename
     * @return string
     */
    private function _getIncludeContents($filename)
    {
        ob_start();
        require  $filename;
        return ob_get_clean();
    }

    /**
     * Anexa el directory separator a una ruta
     * @param array $config
     * @param string $key
     */
    private function _addSeparator(& $config, $key)
    {
        if (!empty($config[$key]) && substr($config[$key], -1) != DIRECTORY_SEPARATOR) {
            $config[$key] .= '/';
        }
    }

	private function _escape($var)
	{
		return htmlspecialchars(stripslashes($var), ENT_QUOTES, 'UTF-8');
	}

    /**
     * Agrega una function al conjunto de plugins
     *
     * @param string $name
     */
    private function _addPlugin($name)
    {
        $plugins = & $this->_plugins;
        $plugin = NULL;
        if (!isset($plugins[$name])) {
            $class = 'PTO_' . ucfirst($name);
            if (function_exists($function = strtolower($class))) {
                $plugin = $plugins[$name] = $function;
            } elseif (class_exists($class, FALSE)) {
                $plugin = $plugins[$name] = new $class($this);
            } elseif (NULL != $this->_config['Plugins']) {
                require $this->_config['Plugins'] . $class . '.php';
                $plugin = $this->_addPlugin($name);
            }
        } else {
            $plugin = & $plugins[$name];
        }

        return $plugin;
    }

    /**
     * Obtiene un idenficador de cache
     * @param string $name
     * @param string $cache_id
     * @return string
     */
    private function _getCacheId($name, $cache_id)
    {
        $name = basename($name, '.tpl');
        if (NULL !== $cache_id) {
            $cache_id = str_replace('%7C', '/', urlencode($cache_id));
            $cache_id .= '-' . sprintf('%08X', crc32($this->_config['Template'] . $cache_id . $name)) .
                         '-' . $name;
        } else {
            $cache_id = $name;
        }

        $dirname = $this->_config['Cache'] . basename($this->_config['Template']) . '/';
        $cache_id = $dirname . $cache_id . '.tpl';

        return $cache_id;
    }

    /**
     * Guarda el contenido de la cache
     *
     * @param string $filename
     * @param mixed $data
     */
    private function _setCache($filename, $data)
    {
        if (is_array($data)) {
            // No se puede guardar un archivo sin
            // un cache validator o un content
            if (empty($data['body']) || empty($data['validator'])) {
                return FALSE;
            }
            //este valor no sirve para nada almacenarlo
            $body = $data['body'];
            unset ($data['status'], $data['body']);
            $data = serialize($data) . "\n"  . $body;
        }
        $dirname = dirname($filename);
        if (!($is_dir = is_dir($dirname))) {
            $is_dir = mkdir($dirname, 0700, TRUE);
        }
        if ($is_dir) {
            $tmpfile  = $dirname . '/' . uniqid(mt_rand()) . '.tmp';
            if (FALSE === file_put_contents($tmpfile, $data) || !rename($tmpfile, $filename)) {
                // Si existe la cache, debe estar desfazada por lo cual es mejor eliminar.
                unlink($filename);
                // elminamos el archivo temporal si existe para no dejar basura.
                unlink($tmpfile);
            } else {
                chmod($filename, 0600);
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Obtiene el contenido almacenado en la cache como un arreglo
     * y se asegura que los campos necesarios esten presente [validator, body]
     *
     * @param string $filename
     * @return array
     */
    private function _getCache($filename)
    {
        $cache = array();
        if (file_exists($filename) &&
            FALSE !== ($_cache = file_get_contents($filename)) &&
            ($i    = strpos($_cache, "\n")) &&
            ($body = substr($_cache, $i + 1))) {
                $_cache = unserialize(substr($_cache, 0, $i));
                if (is_array($_cache) && !empty($_cache['validator'])) {
                    $_cache['body'] = $body;
                    $cache = & $_cache;
                }
        }
        return $cache;
    }
}