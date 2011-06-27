<?php


interface PTO_Cache_Interface
{
    public function set($key, $value);
    public function get($key);
    public function delete($key);
}