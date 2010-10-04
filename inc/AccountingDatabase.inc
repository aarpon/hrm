<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Location of /adodb/adodb.inc.php
$adodb = dirname(__FILE__) . "/extern/adodb5/adodb.inc.php";

require_once "hrm_config.inc";
require_once $adodb;
require_once "accounting_db_config.inc";
require_once "Database.inc";
require_once "CreditOwner.inc";

Class AccountingDatabaseConnection extends DatabaseConnection {
  //!---------------------------------------------------------
  // @function    DatabaseConnection::DatabaseConnection
  // @desc        Konstruktor. Creates a database connection
  // @return      void
  //!---------------------------------------------------------
  function AccountingDatabaseConnection() {
    global $accounting_db_type;
    global $accounting_db_host;
    global $accounting_db_name;
    global $accounting_db_user;
    global $accounting_db_password;
    $this->connection = ADONewConnection($accounting_db_type);
    $this->connection->Connect($accounting_db_host, $accounting_db_user, $accounting_db_password, $accounting_db_name);
  } 

  function isReachable() {
    global $accounting_db_type;
    global $accounting_db_host;
    global $accounting_db_name;
    global $accounting_db_user;
    global $accounting_db_password;
    $connection = ADONewConnection($accounting_db_type);
    $result = $connection->Connect($accounting_db_host, $accounting_db_user, $accounting_db_password, $accounting_db_name);    
    return $result;
  } 
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::type
  // @desc        Answer the type of the database (mysql, ...)
  // @return      String
  //!---------------------------------------------------------
  function type() {
  	global $accounting_db_type;
    return $accounting_db_type;
  }  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::host
  // @desc        Answer the name of the database host
  // @return      String
  //!---------------------------------------------------------
  function host() {
  	global $accounting_db_host;
    return $accounting_db_host;
  }
  //!---------------------------------------------------------
  // @function    DatabaseConnection::name
  // @desc        Answer the name of the database
  // @return      String
  //!---------------------------------------------------------  
  function name() {
  	global $accounting_db_name;
    return $accounting_db_name;
  }
  //!---------------------------------------------------------
  // @function    DatabaseConnection::user
  // @desc        Answer the name of the database user
  // @return      String
  //!---------------------------------------------------------   
  function user() {
  	global $accounting_db_user;
    return $accounting_db_user;
  }
  //!---------------------------------------------------------
  // @function    DatabaseConnection::password
  // @desc        Answer the password of the database user
  // @return      String
  //!---------------------------------------------------------  
  function password() {
  	global $accounting_db_password;
    return $accounting_db_password;
  }

  function superGroupsOfQueryString($id) {
    $result = "select group_id from membership where member='$id'";
    return $result;
  } 

  function loadGroupQueryString($id) {
    $result = "select credit from groups where identifier = '$id'";
    return $result;
  }

  function loadCreditOwnerFromUserTableQueryString($id) {
    $result = "select credit from user where identifier = '$id'";
    return $result;
  } 

  function tariffAndCategoryFromResourceQueryString($id) {
    $result = "select tariff, category from resource where name='$id'";
    return $result;
  } 

  function tariffFromResourceCategoryQueryString($id) {
    $result = "select tariff from resource_category where id='$id'";
    return $result;
  } 

  function groupsOf($creditOwner) {
    $id = $creditOwner->id();
    $query = $this->superGroupsOfQueryString($id);
    $queryResult = $this->query($query);
    if ($queryResult==NULL) {
      return array();
    }
    $queryResult = $this->flatten($queryResult);
    $result = array();
    foreach ($queryResult as $credit) {
      $creditOwner = new CreditOwner($credit);
      $creditOwner->load();
      $result[] = $creditOwner;
    } 
    return $result;
  }

  function getCreditsFor($owner) {
    $credits = array();
    $theOwner = $owner->load();
    $credits[$owner->id()] = $theOwner;
    $credits = $this->addAllCreditsFor($credits);
    if ($credits==NULL) { 
      $credits = array();
    }
    $credits[] = $theOwner;  
    return $credits;
  }

  function addAllCreditsFor($credits) {
    if (count($credits)>0) { 
      $localCredits = $credits;
      foreach ($localCredits as $credit) { 
	$groups = $this->groupsOf($credit);
	$credits = $this->addAllCreditsFor($groups);
	foreach ($groups as $group) {
	  $credits[$group->id()] = $group;
	}
      }
      return $credits;
    }
  } 
  
  function loadCreditOwner($id) {
    $result = null;
    $query = $this->loadGroupQueryString($id);
    $resultSet = $this->query($query);
    if ($resultSet) {
      $result = $resultSet[0]['credit'];
      return $result;
    } 
    $query = $this->loadCreditOwnerFromUserTableQueryString($id);
    $resultSet = $this->query($query);
    if ($resultSet) {
      $result = $resultSet[0]['credit'];
    }
    return $result;
  }

  function tariffForResource($id) {
    $query = $this->tariffAndCategoryFromResourceQueryString($id);
    $resultSet = $this->query($query);
    $tariff = $resultSet[0]['tariff'];
    $category = $resultSet[0]['category'];
    if($tariff>0) {
      return $tariff;
    }
    $query = $this->tariffFromResourceCategoryQueryString($category);
    $resultSet = $this->query($query);
    $tariff = $resultSet[0]['tariff'];
    return $tariff;
  } 
}
?>
