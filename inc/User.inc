<?php
// Module User.inc

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

require_once "Database.inc";
require_once "Setting.inc";
require_once "hrm_config.inc";
require_once "Ldap.inc";
//!---------------------------------------------------------
// @class    Owner
// @desc     Represents an owner of a setting. Superclass of
//           class user.
//!---------------------------------------------------------
Class Owner {
  var $name;          // @var name          String    The owners name might be a job id or a user's login name

  //!---------------------------------------------------------
  // @function    Owner::Owner
  // @desc        Konstruktor. Creates a new owner.
  // @return      void
  //!---------------------------------------------------------
  function Owner() {
    $this->name = '';
  } 

  //!---------------------------------------------------------
  // @function    Owner::name
  // @desc        Answer the name of the owner
  // @return      String
  //!---------------------------------------------------------
  function name() {
    return $this->name;
  }

  //!---------------------------------------------------------
  // @function    Owner::setName
  // @desc        Set the name of the owner. Might be the name
  //              of a person or the id of a job.
  // @param       name    String    The name of the owner
  // @return      void
  //!---------------------------------------------------------
  function setName($name) {
    $this->name = $name;
  } 

} // end of class Owner

//!---------------------------------------------------------
// @class    User
// @desc     Represents a user. Keeps track of the user's  
//           state (is the user logged in, etc).
//!---------------------------------------------------------
Class User extends Owner{ 
  var $isLoggedIn;    // @var isLoggedIn    Boolean   True if the user is logged in.
  var $lastActivity;  // @var lastActivity  Timestamp Time of the last activity of the user
  var $ip;            // @var ip            String    The user's current ip address

  //!---------------------------------------------------------
  // @function    User::User
  // @desc        Konstruktor. Creates a new empty user.
  // @return      void
  //!---------------------------------------------------------
  function User() { 
    $this->isLoggedIn = False;
    $this->lastActivity = time();
    $this->ip = '';
  } 

  //!---------------------------------------------------------
  // @function    User::isLoggedIn
  // @desc        Answer true if the user is logged in.
  // @return      Boolean
  //!---------------------------------------------------------
  function isLoggedIn() {
    return $this->isLoggedIn;
  } 

  //!---------------------------------------------------------
  // @function    User::encrypt
  // @desc        Answer an encrypted version of string
  // @param       string     String    The string to be encrypted
  // @return      String
  //!---------------------------------------------------------  
  function encrypt($string, $seed) {
    global $useDESEncryption;
    if ($useDESEncryption) {
      $result = crypt($string, $seed);
    } else { 
      $result = md5($string);
    } 
    return $result;
  }

  //!---------------------------------------------------------
  // @function    User::logIn
  // @desc        Try to log the user in. 
  //              If log in is successful, name, ip and last 
  //              activity are registered. The result is true in
  //              this case.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user
  // @param       ip       String    The ip address of the user
  // @return      boolean
  //!---------------------------------------------------------  
  function logIn($name, $password, $ip) { 
    $this->setName($name);
    $this->isLoggedIn = False;
    $result = $this->checkLogin($name, $password);
    if ($result) {
      $this->isLoggedIn = True;
      $this->lastActivity = time();
      $this->name = $name;
      $this->ip = $ip;
    }
    return $result;
  }

  //!---------------------------------------------------------
  // @function    User::logOut
  // @desc        Log the user out. isLoggedIn will be false
  //              afterwards.
  // @return      void
  //!---------------------------------------------------------  
  function logOut() { 
    $this->isLoggedIn = False;
  }

  //!---------------------------------------------------------
  // @function    User::password
  // @desc        Answer the users encrypted password.
  // @param       name     String    The login name of the user
  // @return      String
  //!---------------------------------------------------------  
  function password($name) {
    $db = new DatabaseConnection();
    $password = $db->queryLastValue($db->passwordQueryString($name));
    return $password; 
  }

  //!---------------------------------------------------------
  // @function    User::passwordFromReservationSystem
  // @desc        Answer the users encrypted password from
  //              the microscope reservation system. 
  // @param       name     String    The login name of the user
  // @return      String
  //!---------------------------------------------------------  
  function passwordFromReservationSystem($name) {
    $db = new DatabaseConnection();
    $password = $db->passwordFromReservationSystem($name);
    return $password;
  } 

  //!---------------------------------------------------------
  // @function    User::checkLogin
  // @desc        Answer true if the login is granted.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user 
  // @return      Boolean
  //!---------------------------------------------------------    
  function checkLogin($name, $password) {
    global $allow_reservation_users;
    global $use_ldap;
	
    $dbPassword = NULL;
    if ($use_ldap) {
	$ldap = new Ldap();
	$result = $ldap->authenticate(strtolower($name), $password);
	return $result;
    }			
    if ($allow_reservation_users) {
      $dbPassword = $this->passwordFromReservationSystem($name);
    } 
    if ($dbPassword == NULL) { 
      $dbPassword = $this->password($name);
    } 
    if (!$dbPassword) return False; 
    $result = False;
    if ($dbPassword === ($this->encrypt($password, substr($dbPassword, 0, 2)))) {
      $result = True;
    } 
    return $result;
  }

  //!---------------------------------------------------------
  // @function    User::emailAddress
  // @desc        Answer the user's email address.
  // @return      String
  //!---------------------------------------------------------    
  function emailAddress() {
    global $allow_reservation_users;

    $db = new DatabaseConnection();
    $result = NULL;
    if ($allow_reservation_users) {
      $result = $db->emailAddressFromReservationSystem($this->name);
    } 
    if ($result == NULL) { 
      $result = $db->emailAddress($this->name);
    } 
    return $result;
  } 
} 
?>
