<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("inc/OmeroConnection.inc.php");

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  $req = $_SERVER['REQUEST_URI'];
  $_SESSION['request'] = $req;
  header("Location: " . "login.php"); exit();
}

$omeroConnection = $_SESSION['omeroConnection'];

// default value to request the base tree (groups and users):
$node_id = 'ROOT';
// override the default if a specific part is requested:
if (isset($_GET['node'])) {
    $node_id = $_GET['node'];
}

print($omeroConnection->getChildren($node_id));

?>
