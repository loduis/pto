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
 * Esto permite disminuir memoria
 */
define('REQUEST_TIME', $_SERVER['REQUEST_TIME']);

/**
 * PHP Template Object
 *
 * @author Loduis Madariaga
 *
 */
class PTO
{

    private static $_GLOBALS = array();

    private $_config = array(
        'Path'                => NULL,
        'Cache'               => NULL,
        'SendHeaders'         => FALSE,
        'Plugins'             => NULL,
        'CacheControl'        => 'private',
        'MagAge'              => 0
    );

    private $_plugins = array();

    public function  __construct($config = array())
    {
        if (empty($config['Path'])) {
            $config['Path'] = '.';
        }
        if (!empty($config['Cache'])) {
            if (is_dir($config['Cache'])) {
                $config['Cache'] .= DIRECTORY_SEPARATOR;
            } else {
                trigger_error('The cache dirname no exits', E_USER_ERROR);
            }
        }
        if (($config['Path'] = realpath($config['Path']))) {
            $config['Path'] .= DIRECTORY_SEPARATOR;
        } else{
            trigger_error('The template dirname no exits', E_USER_ERROR);
        }
        if (!empty($config['Plugins'])) {
            if (is_dir($config['Plugins'])) {
                $config['Plugins'] .= DIRECTORY_SEPARATOR;
            } else{
                trigger_error('The plugins dirname no exits', E_USER_ERROR);
            }
        }
        //controlando la cache
        if (isset($config['CacheControl']) &&
          !in_array($config['CacheControl'], array('public', 'private', 'no-cache', 'no-store'  ))) {
            $config['CacheControl'] = 'private';
        }
        //tiempo que debe durar la cache
        if (isset($config['MaxAge'])) {
            $config['MaxAge'] = (int) $config['MaxAge'];
        }
        // evitamos array_merge dado que nos intereza primero que todo la
        // configuracion que el usuario establece
        $this->_config = $config + $this->_config;

        spl_autoload_register('PTO::__autoload');

    }

    public function fetch($name, $cache_id = NULL)
    {
        $response = array();
        if (NULL != $this->_config['Cache']) {
            $_filename = $this->_getCacheId($name, $cache_id);
            if (file_exists($_filename . '.php')) {
                $_filename .= '.php';
            } elseif (!file_exists($_filename)) {
                $_filename = NULL;
            }
            //get from cache
            if (NULL != $_filename) {
                //necesita compilacion
                if (FALSE !== strpos($_filename, '.php')) {
                    $response['status'] = 200;
                    $response['body']  = $this->_getIncludeContents($_filename);
                    $response['gzip']  = FALSE;
                    //es la plantilla cacheada con solo codigo HTML
                    $_filename = str_replace('.php', '', $_filename);
                    //si se puede comprimir el contenido
                    $save = TRUE;
                    if (FALSE !== ($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
                        //si se puede obtener la cache como un arreglo
                        if (($cache = $this->_getCache($_filename))) {
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
                        //cambiamos el tiempo de creacion
                        $response['created'] = REQUEST_TIME;
                        //guaramos el cache
                        $this->_setCache($_filename, $response);
                    }
                } elseif (($cache = $this->_getCache($_filename))) {
                    $response = $cache;
                    $response['status'] = 304;
                }
            }
        }
        //compilamos el template nuevamente
        if (!$response) {
            //Es la primera vez que se carga el template no existe cache
            $response['status']  = 200;
            $response['gzip']    = FALSE;
            $response['created'] = REQUEST_TIME;
            //compilamos el template
            if (FALSE !== strrpos($name, '.')) {
                $filename = $this->_config['Path'] . $name;
                $response['body'] = file_get_contents($filename);
            } else {
                $filename = $this->_config['Path'] . $name . '.tpl.php';
                $response['body']    = $this->_getIncludeContents($filename);
            }
            $source = NULL;
            if ($this->_config['SendHeaders'] || NULL != $this->_config['Cache']) {
                // strip nocache-tags from output
                if (FALSE !== strpos($response['body'], '<nocache')) {
                    $source = $response['body'];
                    $source = str_replace('<nocache>', '<nocache file="' . $filename  . '">', $source);
                    $response['body'] = preg_replace('!(</?nocache([^>]*)>)!s' ,'' ,$response['body']);
                }
                //minifamos el comdigo html
                $response['body'] = PTO_Minify::HTML($response['body']);
                //si se puede comprir el texto
                if (FALSE !==($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
                    $response['gzip'] = TRUE;
                    $response['body'] = $gzip;
                }
            } elseif (!$this->_config['SendHeaders'] && NULL == $this->_config['Cache'] &&
                FALSE !== strpos($response['body'], '<nocache>')) {//esto es una vista
                $response['body'] = str_replace('<nocache>', '<nocache file="' . $filename  . '">',
                  $response['body']);
            }
            //guardamos las respecitvas cache si existe un directorio donde guardarlas
            if (NULL !== $this->_config['Cache']) {
                //full cache version
                if (!isset($_filename)) {
                    $_filename = $this->_getCacheId($name, $cache_id);
                }
                //guardamos el cache
                if (NULL === $source) {
                    $this->_setCache($_filename, $response);
                } elseif (preg_match_all('!<nocache file="([^>]+)">(.*?)</nocache>!ism', $source, $matches)) {
                    //obtemos las coincidencias por archivo
                    $_matches = array();
                    foreach ($matches[0] as $i=> $content) {
                        $file = $matches[1][$i];
                        $_matches[$file][] = $content;
                    }
                    $matches = $_matches;
                    $_matches = array();
                    foreach ($matches as $file=>$content) {
                        if (FALSE !== ($_source = file_get_contents($file))) {
                            if (count($content) ==
                              preg_match_all('!<nocache>(.*?)</nocache>!ism', $_source, $_matches)) {
                                foreach ($content as $i=>$value) {
                                    $source = preg_replace('!' . preg_quote($value) . '!', $_matches[1][$i],
                                      $source, 1);
                                }
                            } else {
                                $source = NULL;
                                break;
                            }
                        } else {
                            $source = NULL;
                            break;
                        }
                    }
                } else {
                    $source = NULL;
                }
                //guaramos las cache
                if (NULL !== $source) {
                    $this->_setCache($_filename . '.php', PTO_Minify::PHP($source));
                }
            }
        }

        return $response;
    }

    /**
     * Muestra el contenido de la plantilla
     *
     * @param string $name
     * @param string $cache_id
     * @return void
     */
    public function render($name, $cache_id = NULL)
    {
        $response = $this->fetch($name, $cache_id);
        if ($this->_config['SendHeaders']) {
            if ($response['status'] === 304 && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                if (gmdate('D, d M Y H:i:s', $response['created']) . ' GMT' == $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
                    return ;
                }
            }
            // HTTP/1.0 proxies does not support the Vary header, so prevent any caching
            // by sending an Expires date in the past. HTTP/1.1 clients ignores the
            // Expires header if a Cache-Control: max-age= directive is specified (see RFC
            // 2616, section 14.9.3).
            header('Expires: Sun, 19 Nov 1978 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $response['created']) . ' GMT');
            header('Cache-Control: ' . $this->_config['CacheControl'] . ', must-revalidate, max-age=' .
              $this->_config['MaxAge']);
            header('Vary: Accept-Encoding', FALSE);
            if ($response['gzip']) {
                if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
                        //impide que el servidor la comprima nuevamente en caso de que halla filtros activo
                        apache_setenv('no-gzip', '1');
                        // $response['body'] is already gzip'ed, so make sure
                        // zlib.output_compression does not compress it once more.
                        ini_set('zlib.output_compression', '0');
                        //se le dice al cliente que se le esta enviando un contenido comprimido
                        header('Content-Encoding: gzip');
                } else {
                    $response['body'] = gzdecode($response['body']);
                }
            }
        } elseif ($response['gzip']) {
            $response['body'] = gzdecode($response['body']);
        }

        echo $response['body'];
    }

    /**
     * Asigna una variable al template
     *
     * @param mixed $var
     * @param mixed $value
     * @param bool $global
     * @return PTO
     */
    public static function assign($var, $value = NULL)
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
     * Verifica si un idenficador de cache existe
     * @param string $cache_id
     * @return bool
     */
    public function isCached($name, $cache_id = NULL)
    {
        $filename = $this->_getCacheId($name, $cache_id);
        return file_exists($filename);
    }

    /**
     *
     * @param type $dirname
     */
    public function clearAllCache($dirname = NULL)
    {
        if (NULL != $this->_config['Cache']) {
            $basedir = basename($this->_config['Path']);
            if (NULL != $dirname) {
                $this->_removeEntry($this->_config['Cache'] . $basedir . DIRECTORY_SEPARATOR . $dirname);
            } else {
                $this->_removeEntry($this->_config['Cache'] . $basedir);
            }
        }
    }

    public function cacheControl($value, $maxAge = NULL)
    {
        static $_options = array(
            'public',
            'private',
            'no-cache',
            'no-store'
        );
        if (in_array($value, $_options)) {
            $value ='private';

        }
        $this->_config['CacheControl'] = $value;
        if ('no-store' == $value) {
            $maxAge = 0;
        }
        if (NULL != $maxAge) {
            $this->_config['MaxAge'] = (int) $maxAge;
        }
    }

    /**
     * Agrega una function al conjunto de plugins
     *
     * @param string $name
     */
    public function addPlugin($name)
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
                $plugin = $this->addPlugin($name);
            }
        } else {
            $plugin = & $plugins[$name];
        }

        return $plugin;
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
            $plugin = $this->addPlugin($name);
        } else {
            $plugin = $this->_plugins[$name];
        }
        $count = count($args);
        // cuando se crea una clase como plugin esta consume
        // mas memoria que la funcion como plugin
        if ($plugin instanceof PTO_Plugin) {
            if (0 == $count) {
                return $plugin->$name();
            } elseif (1 == $count) {
                return $plugin->$name($args[0]);
            } elseif (2 == $count) {
                return $plugin->$name($args[0], $args[1]);
            } elseif (3 == $count) {
                return $plugin->$name($args[0], $args[1], $args[2]);
            } elseif (4 == $count) {
                return $plugin->$name($args[0], $args[1], $args[2], $args[3]);
            } else {
                return call_user_func_array(array($plugin, $name, $args));
            }
        } else {
            if (0 == $count) {
                return $plugin($this);
            } elseif (1 == $count) {
                return $plugin($args[0], $this);
            } elseif (2 == $count) {
                return $plugin($args[0], $args[1], $this);
            } elseif (3 == $count) {
                return $plugin($args[0], $args[1], $args[2], $this);
            } elseif (4 == $count) {
                return $plugin($args[0], $args[1], $args[2], $args[3], $this);
            } else {
                $args[] = $this;
                return call_user_func_array($plugin, $args);
            }
        }
    }

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
            trigger_error('Undefined property: ' . $name . ' in ' . $debug[0]['file'] . ' on line ' . $debug[0]['line']);
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
     * Obtiene un idenficador de cache
     * @param string $name
     * @param string $cache_id
     * @return string
     */
    private function _getCacheId($name, $cache_id)
    {
        if (NULL !== $cache_id) {
            $cache_id = str_replace('%7C', DIRECTORY_SEPARATOR, urlencode($cache_id));
            $_crc32 = sprintf('%08X', crc32($this->_config['Path'] . $cache_id . $name));
            $cache_id .= '%%' . $_crc32 . '%%' . basename($name, '.tpl');
        } else {
            $cache_id = basename($name, '.tpl');
        }

        $dirname = $this->_config['Cache'] . basename($this->_config['Path']) . DIRECTORY_SEPARATOR;



        return $dirname . $cache_id . '.tpl';
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
            //este valor no sirve para nada almacenarlo
            $body = $data['body'];
            unset ($data['status'], $data['body']);
            $data = serialize($data) . "\n"  . $body;
        }
        $dirname = dirname($filename);
        if (!file_exists($dirname)) {
            mkdir($dirname, 0700, TRUE);
        }
        if (file_exists($dirname) &&
          (FALSE === file_put_contents(($tmpFile  = $dirname . '/' . uniqid(mt_rand()) . '.tmp'), $data) ||
          !rename($tmpFile, $filename) ||
          (FALSE === ($content = file_get_contents($filename)) ||
          $content !== $data))) {
            unlink($filename);
            return FALSE;
        } else {
            chmod($filename, 0600);
            return TRUE;
        }
    }

    /**
     * Obtiene el contenido almacenado en la cache como un arreglo
     * y se asegura que los campos necesarios esten presente [created, body]
     *
     * @param string $filename
     * @return array
     */
    private function _getCache($filename)
    {
        $cache = array();
        if (file_exists($filename) && FALSE !== ($_cache = file_get_contents($filename))) {
            if (FALSE !== ($i = strpos($_cache, "\n"))) {
                $body = substr($_cache, $i + 1);
                $_cache        = unserialize(substr($_cache, 0, $i));
                $_cache['body'] = $body;
            }
            if (is_array($_cache) && !empty($_cache['created']) && !empty($_cache['body'])) {
                $cache = $_cache;
            }
        }
        return $cache;
    }

    /**
     * Remueve entradas en la cache ya sear archivo o directorio
     *
     * @param string $dirname
     */
    private function _removeEntry($dirname)
    {
        if(file_exists($dirname) && ($_handle = opendir($dirname))) {
            while (FALSE !== ($_entry = readdir($_handle))) {
                if ($_entry != '.' && $_entry != '..') {
                    $_entry = $dirname . DIRECTORY_SEPARATOR . $_entry;
                    if (file_exists($_entry)) {
                        if (is_dir($_entry)) {
                            $this->_removeEntry($_entry);
                        } else {
                            unlink($_entry);
                        }
                    }
                }
            }
            closedir($_handle);
            if (file_exists($dirname)) {
                rmdir($dirname);
            }
        }
    }

    private function __autoload($class)
    {
        if (FALSE !== strpos($class, 'PTO_')) {
            $filename = dirname(dirname(__FILE__)) . '/' . strtr($class, '_', '/') . '.php';
            if (file_exists($filename)) {
                require $filename;
            }
        }
    }
}