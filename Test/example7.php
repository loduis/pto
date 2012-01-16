<?php

require './autoload.php';

$template = new PTO(array(
	'Template' => 'layouts',
	'Cache'    => 'cache',
    'CacheControl'=>'private',
    'Etag'     => TRUE
));
if (!$template->isCached('etag2', 'my_cache_id')) {
    $template->content = 'Esto es una plantilla, que utilza a etag como validator<br/>'
        . ' En este tipo de plantillas guardamos dos archivos en cache un .tpl y un .tpl.php';
}
$template->assign('from', ' PTO ' . date('H:i'));
$template->render();
