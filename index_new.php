<?php

require_once dirname(__FILE__) . '/./inc/bootstrap.php';

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

echo $twig->render('base.html', array());
