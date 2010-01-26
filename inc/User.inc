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

global $authenticateAgainst;
global $use_ldaps;

//!---------------------------------------------------------
// @class    Owner
// @desc     Represents an owner of a setting. Superclass of
//           class user.
//!---------------------------------------------------------
Class Owner {
  var $name;          // @var name          String    The owners name might be a job id or a user's login name
  // add user management
  var $email;         // @var email         String    The owners email address
  var $group;         // @var group         String    The owners group

  //!---------------------------------------------------------
  // @function    Owner::Owner
  // @desc        Konstruktor. Creates a new owner.
  // @return      void
  //!---------------------------------------------------------
  function Owner() {
    $this->name = '';
    // add user management
    $this->email = '';
    $this->group = '';
  }

  //!---------------------------------------------------------
  // @function    Owner::name
  // @desc        Answer the name of the owner
  // @return      String
  //!---------------------------------------------------------
  function name() {
    return $this->name;
  }

  // add user management
  //!---------------------------------------------------------
  // @function    Owner::email
  // @desc        Answer the email address of the owner
  // @return      String
  //!---------------------------------------------------------
  function email() {
    return $this->email;
  }

  // add user management
  //!---------------------------------------------------------
  // @function    Owner::group
  // @desc        Answer the group of the owner
  // @return      String
  //!---------------------------------------------------------
  function group() {
    return $this->group;
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

  // add user management
  //!---------------------------------------------------------
  // @function    Owner::setEmail
  // @desc        Set the email address of the owner
  // @param       email   String    The email address of the owner
  // @return      void
  //!---------------------------------------------------------
  function setEmail($email) {
    $this->email = $email;
  }
  
  // add user management
  //!---------------------------------------------------------
  // @function    Owner::setGroup
  // @desc        Set the group of the owner
  // @param       name    String    The group of the owner
  // @return      void
  //!---------------------------------------------------------
  function setGroup($group) {
    $this->group = $group;
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
  var $use_ldap;      // @var use_ldap      Boolean   If True, the user is authenticated against LDAP, otherwise SQL
  
  //!---------------------------------------------------------
  // @function    User::User
  // @desc        Konstruktor. Creates a new empty user.
  // @return      void
  //!---------------------------------------------------------
  function User() {
    global $authenticateAgainst;
    $this->isLoggedIn = False;
    $this->lastActivity = time();
    $this->ip = '';
    
    // Should we use ldap for authentication?
    if ( $authenticateAgainst == "LDAP" ) {
      $this->use_ldap = true;
    } else {
      $this->use_ldap = false;
    }
    
    // Call the parent constructor too.
    parent::__construct();
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
  // @function    User::ldap_encrypt
  // @desc        Answer an encrypted version of string
  // @param       string     String    The string to be encrypted; form {type}passwd
  // @return      String
  //!---------------------------------------------------------  
  function parse_ldap_password($dbPassword) {
      preg_match("/^\{(.*)\}(.*)$/", $dbPassword, $dbp);
      return array($dbp[2], $dbp[1]);
  }
  
  function ldap_encrypt($string, $dbPasswordType, $dbPassword) {
      if ($dbPasswordType == "crypt") {
          $result = crypt($string, $dbPassword);
      } else if ($dbPasswordType == "md5") {
          $result = md5($string);
      } else {
          $result = "unknown type '$dbPasswordType'";
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
  function password($name, $password) {
      if ($this->use_ldap && $name!=$this->getAdminName()) {
          // ldap code
          $my_ldap = new Ldap();
          $my_ldap->connectForReading();
          $user_data = $my_ldap->loadUser($name, $password);
          return $user_data["password"];
      } else {
          // db code
          $db = new DatabaseConnection();
          $password = $db->queryLastValue($db->passwordQueryString($name));
          return $password; 
      }
  }

  //!---------------------------------------------------------
  // @function    User::isStatusAccepted
  // @desc        Answer true if the subscription of the user 
  //              has been accepted by the administrtator.
  // @param       name     String    The login name of the user 
  // @return      Boolean
  //!---------------------------------------------------------
  function isStatusAccepted() {
  	$result = false;
  	$db = new DatabaseConnection();
  	$status = $db->getUserStatus($this->name());
  	$result = ($status==$this->getAcceptedStatus());
  	return $result;
  }
  
  //!---------------------------------------------------------
  // @function    User::isLoginRestrictedToAdmin
  // @desc        Answer true if the login has been restricted to the
  //              the administrtator.
  // @return      Boolean
  //!---------------------------------------------------------
  function isLoginRestrictedToAdmin() {
  	$result = !( Versions::isDBUpToDate( ) );
  	return $result;
  }
 
  //!---------------------------------------------------------
  // @function    User::isSuspended
  // @desc        Answer true if the user's account has been
  //              suspended by the administrtator.
  // @param       name     String    The login name of the user 
  // @return      Boolean
  //!---------------------------------------------------------
  function isSuspended() {
  	$result = false;
  	$db = new DatabaseConnection();
  	$status = $db->getUserStatus($this->name());
  	$result = ($status==$this->getSuspendedStatus());
  	return $result;
  }
  
  //!---------------------------------------------------------
  // @function    User::exists
  // @desc        Answer true if the user's account exists.
  // @param       name     String    The login name of the user 
  // @return      Boolean
  //!---------------------------------------------------------
  function exists() {
  	$result = false;
  	$db = new DatabaseConnection();
  	return $db->checkUser($this->name());
  }
  
  //!---------------------------------------------------------
  // @function    User::checkLogin
  // @desc        Answer true if the login is granted.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user 
  // @return      Boolean
  //!---------------------------------------------------------    
  function checkLogin($name, $password) {
    $result = false;
    
    // If the db is outdated and the user is not the admin, we do not allow
    // the login
    if ( ($this->isLoginRestrictedToAdmin() == true) && (strcmp($name, 'admin') != 0)  )
      return $result;
    
    if ($this->use_ldap && $name!=$this->getAdminName()) $result = $this->checkLoginAgainstLDAP($name, $password);
    else $result = $this->checkLoginAgainstHRMDatabase($name, $password);
    	
    return $result;
  }

  //!---------------------------------------------------------
  // @function    User::checkLoginAgainstHRMDatabase
  // @desc        Answer true if the login is granted.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user 
  // @return      Boolean
  //!---------------------------------------------------------    
  function checkLoginAgainstHRMDatabase($name, $password) {
  	// add user management
    if (!$this->isStatusAccepted()) return false;
    $dbPassword = $this->password($name, $password);
    if (!$dbPassword) return false;
    $result = ($dbPassword == ($this->encrypt($password, substr($dbPassword, 0, 2))));
    return $result;
  }
  	
  //!---------------------------------------------------------
  // @function    User::checkLoginAgainstLDAP
  // @desc        Answer true if the login is granted.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user 
  // @return      Boolean
  //!---------------------------------------------------------    
  function checkLoginAgainstLDAP($name, $password) {
		$ldap = new Ldap();
		$result = $ldap->authenticate(strtolower($name), $password);
		return $result;
  }

  //!---------------------------------------------------------
  // @function    User::emailAddress
  // @desc        Answer the user's email address.
  // @return      String
  //!---------------------------------------------------------    
  function emailAddress() {
	$result = "";
    if ($this->use_ldap) {
      $ldap = new Ldap();
      $user_data = $ldap->loadUser($this->name());
      $result =  $user_data['mail'];
    } 
    else {
      $db = new DatabaseConnection(); 
  	  $result = $db->emailAddress($this->name);
    } 
    return $result;
  } 
  
  //!---------------------------------------------------------
  // @function    User::getAcceptedStatus
  // @desc        Answer the status that signifies that the user
  // 			  is accepted in the hrm-database
  // @return      String
  //!---------------------------------------------------------    
  function getAcceptedStatus() {
  	return 'a';
  }
  
  //!---------------------------------------------------------
  // @function    User::getSuspendedStatus
  // @desc        Answer the status that signifies that the
  //              user's account is suspended
  // @return      String
  //!---------------------------------------------------------    
  function getSuspendedStatus() {
  	return 'd';
  }

  //!---------------------------------------------------------
  // @function    User::getAdminName
  // @desc        The name of the system administrator account
  // @return      String
  //!---------------------------------------------------------  
  function getAdminName() {
  	return 'admin';
  }
  
  //!---------------------------------------------------------
  // @function    User::isAdmin
  // @desc        Answer wether the receiver is the system 
  // 			  administrator.
  // @return      String
  //!---------------------------------------------------------  
  function isAdmin() {
  	return $this->name()==$this->getAdminName();
  }
  
  //!---------------------------------------------------------
  // @function    User::load
  // @desc        load email and group of the user
  // @return      
  //!--------------------------------------------------------- 
  function load() {
  	global $email_admin;
  	if ($this->isAdmin()) {
  	  $db = new DatabaseConnection();
      $this->setEmail($email_admin);
      $db->updateMail($this->name(), $email_admin);
    }
    else {
      $this->setEmail($this->emailAddress());
    }
    // get user group
    $this->setGroup($this->group());
  }
  
  //!---------------------------------------------------------
  // @function    User::group
  // @desc        Answer the group name from the database or
  //			  the default group name if ldap is used.
  // @return      String
  //!--------------------------------------------------------- 
  function userGroup() {
  	$result = $this->defaultGroupName();
  	if (!$this->use_ldap) {
  		$db = new DatabaseConnection();
  		$result = $db->getGroup($this->name());
  	}
    return $result; 
  }
  
  //!---------------------------------------------------------
  // @function    User::updateLastAccessDate
  // @desc        update the last accesss date in the database
  // @return      
  //!--------------------------------------------------------- 
  function updateLastAccessDate() {
  	// TODO: user will not exist in db when ldap is used
  	$db = new DatabaseConnection();
  	$db->updateLastAccessDate($this->name());
  }
  
  function defaultGroupName() {
  	return 'hrm';
  }
} 



?>
