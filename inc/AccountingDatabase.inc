<?php
// Module AccountingDatabase.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

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