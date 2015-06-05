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

print($omeroConnection->getSubTree());

?>
