<?php

require_once dirname(__FILE__) . '/./inc/bootstrap.php';

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

echo $twig->render('developers.twig', array('version' => '4.0', 'year' => '2017'));