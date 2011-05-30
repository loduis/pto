<?php


class PHPTemplate_Plugin_Test extends PHPTemplate_Plugin
{
    public function test($params)
    {
        print_r($params);
        echo 'ESTO ES UNA PRUEBA';

    }
}

/*
function phptemplate_plugin_test($params, $engine)
{
    print_r($params);
    echo 'ESTO ES UNA PRUEBA';
}*/