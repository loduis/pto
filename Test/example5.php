<?php

require './autoload.php';


$template = new PTO(array(
	'Template' => 'layouts',
	'Cache'    => 'cache',
	'CacheControl'=> 'private,max-age=30', # DEBE DURAR 30 SEGUNDOS EN LA CACHE DEL NAVEGADOR
	'Expire'=> 30 # TIEMPO PARA EL CUAL EL ARCHIVO LOCAL ES VALIDO
));

// PLANTILLAS QUE CAMBIAN EN EL TIEMPO
if (!$template->isCached('default', 'example5')) {
	$template->hello = 'Soy la plantilla ejemplo 5 <br />con la diferencia que hay cache privado http<br />'. 
		'SI ME VUELVE A RECARGAR SE DARAN CUENTA DEL Status: 304<br />'. 
		'Estoy siendo cacheada con Last-Modified y soy estatica si no eliminan el archivo<br />' . 
		'No envio nuevo contenido' . 
		'Esta siendo cachead solo por los navegadores web<br />' . 
		'Tambien tengo un tiempo en el cual el navegador deberia ir a revisar la cache<br />' . 
		'Pero algunos navegadores no respetan [safari lo provee en window]<br/>' . 
		'Expired: ' . $_SERVER['REQUEST_TIME'] . '<br/>' . 
		'Atento cuando hay cambio en el tiempo de expiracion significa que se creo una nueva cache<br />' . 
		'Del archivo<br/> Esto es util para cuando hay plantillas que cambian en el tiempo<br/>' . 
		'Pero no con mucha frequencia mayor a 1 minuto.<br />' . 
		'<a href="example5.php">Recargarme.</a>'
		;
	$template->assign('from', '<b>Desde PTO</b>');
}
// Envia el contenido del template a la plantilla
$template->render();