<?php

require './autoload.php';


$template = new PTO(array(
	'Template' => 'layouts',
	'Cache'    => 'cache',
	'CacheControl'=> 'private'
));

if (!$template->isCached('default', 'example3')) {
	$template->hello = 'Soy la plantilla ejemplo 3 <br />con la diferencia que hay cache privado http<br />'. 
		'SI ME VUELVE A RECARGAR SE DARAN CUENTA DEL Status: 304<br />'. 
		'Estoy siendo cacheada con Last-Modified y soy estatica si no eliminan el archivo<br />' . 
		'No envio nuevo contenido';
	$template->assign('from', '<b>Desde PTO</b>');
}
// Envia el contenido del template a la plantilla
$template->render();