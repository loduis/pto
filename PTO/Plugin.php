<?php

abstract class PTO_Plugin
{
    protected $_PTO = NULL;

    final public function __construct($engine)
    {
        $this->_PTO = $engine;
    }
}