<?php

function pto_autoload($class_name) {
	static $_scan = array(
		'',
		'PTO'
	);
	$root = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
	if (strpos($class_name, '_') !== FALSE) {
		$class_name = str_replace('_', DIRECTORY_SEPARATOR, $class_name);
	}
	$name = DIRECTORY_SEPARATOR . $class_name . '.php';
	foreach ($_scan as $dirname) {
		$filename = $root . $dirname . $name;
		if (file_exists($filename)) {
			require $filename;
			break;
		}
	}
	
}

spl_autoload_register('pto_autoload');