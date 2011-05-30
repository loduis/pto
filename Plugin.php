<?php

abstract class PHPTemplate_Plugin
{
    protected $_engine = NULL;

    final public function __construct($engine)
    {
        $this->_engine = $engine;
    }
}