<?php

require './PTO.php';
require './functions.php';
require './Locales/en.php';
$layout = new PTO(array(
    'Path'=>'Test/Layouts',
    //'Cache'=>'Test/Cache',
    'SendHeaders'=> FALSE
));

$view = new PTO(array(
  'Path'=> 'Test/Views',
  //'Cache'=>'Test/Cache',
  'SendHeaders'=> FALSE
));

if (isset($_GET['clear'])) {
    $layout->clearAllCache();
    $view->clearAllCache();
}
$layout->lang = 'en';
$layout->title = 'home_title';
$layout->css   = 'home.css';
$layout->module = 'home';
$layout->https  = '';
$layout->translate = array('title'=>'', 'lang'=>'es', 'url'=>'');
$layout->view = new PTO(array(
  'Path'=> 'Test/Views',
  //'Cache'=>'Test/Cache',
  'SendHeaders'=> FALSE
));
$layout->year = date('Y');
$layout->render('default');