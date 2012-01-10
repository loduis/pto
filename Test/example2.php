<?php

require './autoload.php';


$template = new PTO(array(
	'Template' => 'layouts',
	'Cache'    => 'cache' # hace la diferencia
));
// necesario para comenzar el cache
if (!$template->isCached('default', 'example2')) {
	$template->hello = 'Soy la plantilla ejemplo 2<br />' . 
		'Hago una cache de este archivo y si no me eliminan, cualquier cambio<br/>' . 
		'En el fuente no sera enviado de nuevo al navegador<br />' . 
		'La cache se guarda en el folder cache que se encuentra en este directorio<br />'. 
		'Solo hago una cache en el sistema de archivo no envio cabeceras http<br />' . 
		'para que los navegadores hagan una cache tambien'
		;
	$template->assign('from', ' <b>Desde PTO</b>');
}
// Envia el contenido del template a la plantilla
$template->render();