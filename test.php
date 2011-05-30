<?php

require './PHPTemplate.php';
$layout = new PHPTemplate(array(
    'Path'=>'Test/Layouts',
    'Cache'=>'Test/Cache',
    'SendHeaders'=> TRUE
));

$view = new PHPTemplate(array(
  'Path'=> 'Test/Views',
  'Cache'=>'Test/Cache',
  'SendHeaders'=> FALSE
));
$view->test = 'Loduis Madariaga Barrios';
if (isset($_GET['clear'])) {
    $layout->clearAllCache();
    $view->clearAllCache();
}
$layout->test = 'Esto es una variable';
$layout->view =  $view;

$layout->render('test');