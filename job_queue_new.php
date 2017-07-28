<?php

require_once dirname(__FILE__) . '/./inc/bootstrap.php';

use hrm\job\JobQueue;
use hrm\Nav;
use hrm\Util;

session_start();

$queue = new JobQueue();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
    !strstr($_SERVER['HTTP_REFERER'], 'job_queue')
) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_POST['delete'])) {
    if (isset($_POST['jobs_to_kill'])) {
        $queue->markJobsAsRemoved($_POST['jobs_to_kill'],
            $_SESSION['user']->name(), $_SESSION['user']->isAdmin());
    }
} else if (isset($_POST['update']) && $_POST['update'] == 'update') {
    // nothing to do
}

// Retrieve the queue
$rows = $queue->getContents();

// Render the template
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

echo $twig->render('queue.twig',
    array(
        'version' => '4.0',
        'queue' => $rows,
        'username' =>  $_SESSION['user']->name(),
        'isAdmin' => $_SESSION['user']->isAdmin()
    ));