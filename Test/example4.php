<?php

require './autoload.php';


$template = new PTO(array(
	'Template' => 'layouts',
	'Cache'    => 'cache',
	'CacheControl'=> 'private,max-age=60'
));

if (!$template->isCached('default', 'example4')) {
	$template->hello = 'Soy la plantilla ejemplo 4 <br />con la diferencia que hay cache privado http<br />'. 
		'SI ME VUELVE A RECARGAR SE DARAN CUENTA DEL Status: 304<br />'. 
		'Estoy siendo cacheada con Last-Modified y soy estatica si no eliminan el archivo<br />' . 
		'No envio nuevo contenido' . 
		'Esta siendo cachead solo por los navegadores web<br />' . 
		'Tambien tengo un tiempo en el cual el navegador deberia ir a revisar la cache<br />' . 
		'Pero algunos navegadores no respetan [safari lo provee en window]<br/>' . 
		'<a href="example4.php">Recargarme.</a>'
		;
	$template->assign('from', '<b>Desde PTO</b>');
}
// Envia el contenido del template a la plantilla
$template->render();
// recarga la pagina dando en el enlace recargar
// y revisa las entradas en el archivo
file_put_contents('./cache/example4.log', date('Y-m-d H:i:s') . "\n", FILE_APPEND);