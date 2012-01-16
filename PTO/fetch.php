<?php
//Es la primera vez que se carga el template no existe cache
$response = array();
$response['status']  = 200;
$response['gzip']    = FALSE;
if (FALSE !== strpos($name, '.')) {
    $filename = $this->_config['Template'] . $name;
    $response['body'] = file_get_contents($filename);
} else {
    $filename = $this->_config['Template'] . $name . '.tpl.php';
    $response['body']    = $this->_getIncludeContents($filename);
}
if ($this->_config['Cache']) {
    $source = '';
    // strip nocache-tags from output
    if (FALSE !== strpos($response['body'], '<nocache>')) {
        if (!$this->_config['IgnoreNoCache']) {
            $source = $response['body'];
            // AGREGAMOS EL NOMBRE DEL ARCHIVO AL TAG NOCACHE
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
    if (FALSE !== ($gzip = gzencode($response['body'], 9, FORCE_GZIP))) {
        $response['gzip'] = TRUE;
        $response['body'] = $gzip;
    }
    // guardamos el validator
    $response['validator'] = $this->_config['Etag'] ?
                                md5($response['body']) :
                                $_SERVER['REQUEST_TIME'];
    //full cache version file name
    if (!isset($cached_file)) {
        $cached_file = $this->_cacheId;
    }
    //guardamos el full cache no hay codigo para compilar
    if (!$source) {
        $this->_setCache($cached_file, $response);
        if ($this->_config['Etag']) {
            if (FALSE !== ($source = file_get_contents($filename))) {
                $this->_setCache($cached_file . '.php', PTO_Minify::PHP($source));
            }
        }
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
        if ($source) {
            $this->_setCache($cached_file . '.php', PTO_Minify::PHP($source));
        }
    }
} elseif (FALSE !== strpos($response['body'], '<nocache>')) {//esto es una vista
    $response['body'] = str_replace('<nocache>', '<nocache file="' . $filename  . '">', $response['body']);
}