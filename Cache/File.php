<?php

class PTO_Cache_File implements PTO_Cache_Interface
{
    private $_dirname = NULL;
    
    public function  __construct($dirname)
    {
        $this->_dirname = $dirname;
    }

    public function set($key, $value)
    {
        
    }

    public function get($key)
    {
        
    }

    public function delete($key)
    {
        
    }
}