<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// === OMERO Tree Loader ===
// This file is handling the requests sent by jqTree when an on-demand node
// gets expanded by the user. It ensures the user is logged in, has a valid
// OMERO connection and finally asks the connector for the JSON data.

require_once("inc/OmeroConnection.php");

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

// fetch the child nodes and return the JSON:
print($omeroConnection->getChildren($node_id));

?>
