<?php
//Es la primera vez que se carga el template no existe cache
$response = array();
$response['status']  = 200;
$response['gzip']    = FALSE;
$response['validator'] = $this->_config['Etag'] ?
                            md5($response['body']) :
                            $_SERVER['REQUEST_TIME'];
//si el nombre del template include una extension se busca un archivo por este nombre
if (FALSE !== strrpos($name, '.')) {
    $filename = $this->_config['Template'] . $name;
    $response['body'] = file_get_contents($filename);
} else {
    $filename = $this->_config['Template'] . $name . '.tpl.php';
    $response['body']    = $this->_getIncludeContents($filename);
}
$source = NULL;
if ($this->_config['CacheControl'] || NULL != $this->_config['Cache']) {
    // strip nocache-tags from output
    if (FALSE !== strpos($response['body'], '<nocache>')) {
        if (!$this->_config['IgnoreNoCache']) {
            $source = $response['body'];
            $source = str_replace('<nocache>', '<nocache file="' . $filename  . '">', $source);
        }
        //removemos el tag nocache
        $response['body'] = preg_replace('!(</?nocache([^>]*)>)!s' ,'' ,$response['body']);
        // removemos el tag de eval
        if (FALSE !== strpos($response['body'], '<eval>')) {
            $response['body'] = preg_replace('!(</?eval([^>]*)>)!s' ,'' ,$response['body']);
        }
    }
    //minificamos el codigo html
    $response['body'] = PTO_Minify::HTML($response['body']);
    //si se puede comprir el texto
    if (FALSE !==($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
        $response['gzip'] = TRUE;
        $response['body'] = $gzip;
    }
} elseif (!$this->_config['CacheControl'] && NULL == $this->_config['Cache'] &&
    FALSE !== strpos($response['body'], '<nocache>')) {//esto es una vista
    $response['body'] = str_replace('<nocache>', '<nocache file="' . $filename  . '">', $response['body']);
}
//guardamos las respecitvas cache si existe un directorio donde guardarlas
if (NULL != $this->_config['Cache']) {
    //full cache version file name
    if (!isset($_filename)) {
        $_filename = $this->_cacheId;
    }
    //guardamos el full cache no hay codigo para compilar
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
            //obtenemos el archivo de codigo fuente
            if (FALSE !== ($_source = file_get_contents($file))) {
                // comparamos el numero de tag nocache compiladas vs el numero en el codigo fuente
                if (count($content) ==
                  preg_match_all('!<nocache>(.*?)</nocache>!ism', $_source, $_matches)) {
                    foreach ($content as $i=>$value) {
                        // esto es util en sistemas multi lenguage que permite insertar
                        // el codigo que se necesita transladar directamente en la plantilla
                        $php = $_matches[1][$i];
                        if (FALSE !== strpos($php, '<eval>')) {
                            if (preg_match_all('!<eval>(.*?)</eval>!ism', $php, $eval)) {
                                foreach ($eval[1] as $j=>$_php) {
                                    ob_start();
                                    eval('?>' . $_php);
                                    $_php  = ob_get_clean();
                                    $php = preg_replace('!' . preg_quote($eval[0][$j]) . '!', $_php, $php, 1);
                                }
                            }
                        }
                        $source = preg_replace('!' . preg_quote($value) . '!', $php, $source, 1);
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
        // guardamos el nuevo codigo php compilado
        if (NULL !== $source) {
            $this->_setCache($_filename . '.php', PTO_Minify::PHP($source));
        }
    }
}