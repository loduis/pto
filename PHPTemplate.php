<?php
/**
 * Esta funciones actua como los bloques de smarty
 * Esta se encarga de abrir el bloque
 */
function nocache() {
    echo '{nocache}';
}

/**
 * Esta funcion cierra el bloque que no se debe cachear
 */
function endnocache() {
    echo '{/nocache}';
}

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
 * Esta clase manipulas plantillas
 */
class PHPTemplate
{

    private static $_GLOBALS = array();

    private $_config = array(
        'Path'                => NULL,
        'Cache'               => NULL,
        'SendHeaders'         => TRUE,
        'Plugins'             => array(),
    );

    public function  __construct($config = array())
    {
        if (empty($config['Path'])) {
            $config['Path'] = '.';
        }
        if (!empty($config['Cache'])) {
            if (($config['Cache'] = realpath($config['Cache']))) {
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
        // evitamos array_merge dado que nos intereza primero que todo la
        // configuracion que el usuario establece
        $this->_config = $config + $this->_config;

        spl_autoload_register('PHPTemplate::__autoload');

    }

    public function fetch($name, $cache_id = NULL)
    {
        $response = array();
        //get from cache
        if (NULL !== ($_filename = $this->_getCachedResource($name, $cache_id))) {
            //necesita compilacion
            if (FALSE !== strpos($_filename, '.php')) {
                $response['status'] = 200;
                $response['body']  = $this->_getIncludeContents($_filename);
                //es la plantilla cacheada con solo codigo HTML
                $_filename = str_replace('.php', '', $_filename);
                //si se puede comprimir el contenido
                $save = TRUE;
                if (FALSE !== ($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
                    //si se puede obtener la cache como un arreglo
                    if (($cache = $this->_getCacheAsArray($_filename))) {
                        if (!empty($cache['gzip']) && $cache['body'] == $gzip) {
                            $response = $cache + $response;
                            $response['status']  = 304;
                            $save = FALSE;
                        } else {
                            $response['body'] = $gzip;
                            $response['gzip'] = TRUE;
                        }
                    }
                }
                if ($save) {
                    //cambiamos el tiempo de creacion
                    $response['created'] = REQUEST_TIME;
                    //guaramos el cache
                    $this->_saveCache($_filename, $response);
                }
            } elseif (($cache = $this->_getCacheAsArray($_filename))) {
                $response = $cache;
                $response['status'] = 304;
            }
        }
        //compilamos el template nuevamente
        if (!$response) {
            //obtenemos la plantilla
            $filename = $this->_config['Path'] . $name . '.tpl.php';
            //Es la primera vez que se carga el template no existe cache
            $response['status']  = 200;
            $response['created'] = REQUEST_TIME;
            $response['body']    = $this->_getIncludeContents($filename);
            $source              = NULL;
            // strip nocache-tags from output
            if (FALSE !== strpos($response['body'], '{nocache}')) {
                $source = $response['body'];
                $response['body'] = preg_replace('!(\{/?nocache\})!sm' ,'' ,$response['body']);
            }
            //minifamos el comdigo html
            $response['body'] = PHPTemplate_Minify::HTML($response['body']);
            //si se puede comprir el texto
            if (FALSE !==($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
                $response['gzip'] = TRUE;
                $response['body'] = $gzip;
            }
            //guardamos las respecitvas cache si existe un directorio donde guardarlas
            if ($this->_config['Cache']) {
                //full cache version
                if (!isset($_filename)) {
                    $_filename = $this->_getCacheId($name, $cache_id);
                }
                $this->_saveCache($_filename, $response);
                //precompile cache version
                if (NULL !== $source && // codigo de la pagina con los tag nocache
                  FALSE !== ($_source = file_get_contents($filename)) && // es el codigo fuente orignal
                  preg_match_all('!<\?php nocache\(\);\s?\?>(.+)<\?php endnocache\(\);\s?\?>!s', $_source, $matches) && // obtien el invoke a la funcion nocahce
                  preg_match_all('!{nocache}(.*){/nocache}!is', $source, $_matches) && //obtiene los tag nocache
                  count($matches[0]) == count($_matches[0])) {
                    $_source = $source;
                    foreach ($matches[1] as $i=>$match) {
                        $source = str_replace($_matches[0][$i], $match, $source);
                    }
                    if ($_source != $source) {
                        $this->_saveCache($_filename . '.php', PHPTemplate_Minify::PHP($source));
                    }
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
                if ($response['created'] == strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
                    return ;
                } else {
                    $response['created'] = REQUEST_TIME;
                    $this->_saveCache($this->_getCacheId($name, $cache_id), $response);
                }
            }
            //la pagina no esta comprimida por alguna razon
            if (!isset($response['gzip'])) {
                $response['gzip'] = FALSE;
            }
            // HTTP/1.0 proxies does not support the Vary header, so prevent any caching
            // by sending an Expires date in the past. HTTP/1.1 clients ignores the
            // Expires header if a Cache-Control: max-age= directive is specified (see RFC
            // 2616, section 14.9.3).
            header('Expires: Sun, 19 Nov 1978 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s O', $response['created']));
            header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Vary: Accept-Encoding', FALSE);
            //revisando si el cliente
            if ($response['gzip']) {
                if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
                        //impide que el servidor la comprima nuevamente en caso de que halla filtros activo
                        apache_setenv('no-gzip', '1');
                        // $response['body'] is already gzip'ed, so make sure
                        // zlib.output_compression does not compress it once more.
                        ini_set('zlib.output_compression', '0');
                        //se le dice al cliente que se le esta enviando un contenido comprimido
                        header('Content-Encoding: gzip');
                } elseif ($response['gzip']) {
                    $response['body'] = gzdecode($response['body']);
                }
            }
        } elseif (!empty($response['gzip'])) {
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
     * @return PHPTemplate
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
        return NULL !== $this->_getCachedResource($name, $cache_id);
    }

    /**
     *
     * @param type $name
     * @param type $cache_id
     */
    public function clearCache($name, $cache_id = NULL)
    {
        $filename = $this->_getCacheId($name, $cache_id);
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     *
     * @param type $dirname
     */
    public function clearAllCache($dirname = NULL)
    {
        if ($this->_config['Cache']) {
            if (NULL !== $dirname) {
                $this->_removeEntry($this->_config['Cache'] . $dirname);
            } else {
                $this->_removeEntry($this->_config['Cache']);
                mkdir($this->_config['Cache'], 0700);
            }
            clearstatcache();
        }
    }

    /**
     * Establce una variable de configuracion
     *
     * @param string $name
     * @param mixed $value
     * @return PHPTemplate
     */
    public function setConfig($name, $value)
    {
        if (isset($this->_config[$name])) {
            $this->_config[$name] = $value;
        }

        return $this;
    }

    /**
     * Escapa el valor de una variable
     *
     * @param string $string
     * @return string
     */
    public function escape($string)
    {
        return htmlspecialchars(stripslashes($string), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Agrega una function al conjunto de plugins
     *
     * @param string $name
     */
    public function addPlugin($name)
    {
        $function = 'phptemplate_plugin_' . $name;
        if (function_exists($function)) {
            $this->_config['Plugins'][$name] = $function;
        }
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
        $plugins = & $this->_config['Plugins'];
        $plugin  = NULL;
        if (!in_array($name, $plugins)) {
            $dirname = dirname(__FILE__);
            $filename =  $dirname . '/Plugin/' . $name . '.php';
            if (file_exists($filename)) {
                require $filename;
                $class = 'PHPTemplate_Plugin_' . ucfirst($name);
                if (function_exists($function = strtolower ($class))) {
                    $plugin = $function;
                    $plugins[$name] = $function;
                } else {
                    $plugin = new $class($this);
                    $plugins[$name] = $plugin;
                }
            }

        } else {
            $plugin  = & $plugins[$name];
        }
        $count = count($args);
        // cuando se crea una clase como plugin esta consume
        // mas memoria que la funcion como plugin
        if ($plugin instanceof PHPTemplate_Plugin) {
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

    //-----------------------------------
    //          PRIVATE METHOD
    //-----------------------------------

    /**
     * Devuelve el path de un recurso cachedo
     * @param string $name
     * @param string $cache_id
     * @return string
     */
    private function _getCachedResource($name, $cache_id)
    {
        $path = NULL;
        if ($this->_config['Cache']) {
            $filename = $this->_getCacheId($name, $cache_id);
            if (file_exists($filename . '.php')) {
                $path = $filename . '.php';
            } elseif (file_exists($filename)) {
                $path = $filename;
            }
        }

        return $path;
    }

    /**
     * Obtiene un idenficador de cache
     * @param string $name
     * @param string $cache_id
     * @return string
     */
    private function _getCacheId($name, $cache_id)
    {
        return $this->_config['Cache'] . basename($this->_config['Path']) . '/' .
               md5(NULL === $cache_id ? $name : $cache_id . '/' . $name)
               . '.tpl';
    }

    /**
     * Obtiene el contenido interpretado
     *
     * @param string $filename
     * @return string
     */
    private function _getIncludeContents($filename)
    {
        if (file_exists($filename)) {
            ob_start();
            //anexamos las variables globales
            foreach (self::$_GLOBALS as $var=>$value) {
                if (!isset($this->$var)) {
                    $this->$var = $value;
                }
            }
            include  $filename;
            return ob_get_clean();
        } else {
            trigger_error('No exists file: ' . $filename, E_USER_ERROR);
        }
    }

    /**
     * Guarda el contenido de la cache
     *
     * @param string $filename
     * @param mixed $data
     */
    private function _saveCache($filename, $data)
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
          (FALSE === file_put_contents(($tmpFile  = $dirname . '/temp.' . uniqid('wrt')), $data) ||
          !rename($tmpFile, $filename) ||
          !chmod($filename, 0600) ||
          (FALSE === ($content = file_get_contents($filename)) || $content !== $data))) {
            unlink($filename);
        }
    }

    /**
     * Obtiene el contenido almacenado en la cache como un arreglo
     * y se asegura que los campos necesarios esten presente [created, body]
     *
     * @param string $filename
     * @return array
     */
    private function _getCacheAsArray($filename)
    {
        $cache = array();
        if (file_exists($filename) && FALSE !== ($_cache = file_get_contents($filename))) {
            if (FALSE !== ($i = strpos($_cache, "\n"))) {
                $body = substr($_cache, $i + 1);
                $_cache        = unserialize(substr($_cache, 0, $i));
                $_cache['body'] = $body;
            }
            if (is_array($_cache) && !empty($_cache['created']) &&
              is_numeric($_cache['created']) && !empty($_cache['body'])) {
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
        if (FALSE !== strpos($class, 'PHPTemplate_')) {
            $filename = dirname(dirname(__FILE__)) . '/' . strtr($class, '_', '/') . '.php';
            if (file_exists($filename)) {
                require $filename;
            }
        }
    }
}