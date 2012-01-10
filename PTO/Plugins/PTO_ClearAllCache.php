<?php

/**
 * PTO_ClearAllCache
 * Permite eliminar la cache generada por la PTO
 * Dado que esta no es una operacion que se realiza
 * cada vez que se carga una plantilla, creo que este
 * es el lugar adecuado y no gastar memoria y CPU
 * por la misma
 *
 * @param string $dirname
 * @param PTO $pto
 */
function pto_clearallcache($dirname = NULL, $pto = NULL)
{
    if (func_num_args() == 1) {
        $pto = $dirname;
        $dirname = NULL;
    }
    if (NULL != ($cacheDir = $pto->getConfig('Cache'))) {
        $basedir = basename($pto->getConfig('Template'));
        if (NULL != $dirname) {
            _pto_remove_entry($cacheDir . $basedir . DIRECTORY_SEPARATOR . $dirname);
        } else {
            _pto_remove_entry($cacheDir . $basedir);
        }
    }
}

/**
 * Se encarga de remover la estructura de directorios
 * @param string $dirname
 */
function _pto_remove_entry($dirname)
{
    if(file_exists($dirname) && ($_handle = opendir($dirname))) {
        while (FALSE !== ($_entry = readdir($_handle))) {
            if ($_entry != '.' && $_entry != '..') {
                $_entry = $dirname . DIRECTORY_SEPARATOR . $_entry;
                if (file_exists($_entry)) {
                    if (is_dir($_entry)) {
                        _pto_remove_entry($_entry);
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