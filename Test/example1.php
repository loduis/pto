<?php

require './autoload.php';

$template = new PTO(array(
	'Template'=> 'layouts'
));

$template->hello = 'Hola Mundo';
$template->assign('from', '<b>Desde PTO</b>');
// Envia el contenido del template a la plantilla
$template->render('default');