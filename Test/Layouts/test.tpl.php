<!doctype html>
<html>
    <head>

    </head>
    <body>
        Esto es una pagina de preuba
        <span> HOLA mundo</span>
        <?php echo $this->test;?>
        <?php nocache(); ?>
        <?php $this->view->render('test'); ?>
        <?php endnocache(); ?>
    </body>
</html>
