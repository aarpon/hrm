<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once "Database.inc";
require_once "Setting.inc";
require_once "hrm_config.inc";

if ( $authenticateAgainst == "ACTIVE_DIR" ) {
  require_once "ActiveDirectory.inc";
}

if ( $authenticateAgainst == "LDAP" ) {
  require_once "Ldap.inc";
}

global $authenticateAgainst;
global $use_ldaps;

//!---------------------------------------------------------
// @class    Owner
// @desc     Represents an owner of a setting. Superclass of
//           class user.
//!---------------------------------------------------------
Class Owner {
  public $name;          // @public name          String    The owners name might be a job id or a user's login name
 
  //!---------------------------------------------------------
  // @function    Owner::Owner
  // @desc        Constructor. Creates a new owner.
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
  public $isLoggedIn;   // @public isLoggedIn    Boolean   True if the user is logged in.
  public $lastActivity; // @public lastActivity  Timestamp Time of the last activity of the user
  public $ip;           // @public ip            String    The user's current ip address
  public $authMode;     // @public authMode      String    One of "MYSQL", "LDAP", or "ACTIVE_DIR"
  
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
    if ( !(
           ( $authenticateAgainst == "MYSQL"      ) ||
           ( $authenticateAgainst == "LDAP"       ) ||
           ( $authenticateAgainst == "ACTIVE_DIR" ) )) {
      throw new Exception( "Bad value $authenticateAgainst." );
    }
    
    $this->authMode  = $authenticateAgainst;
      
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

    // If the user is the admin, we check against the MYSQL DB
    if ( $name == $this->getAdminName() ) {
        // db code
        $db = new DatabaseConnection();
        $password = $db->queryLastValue($db->passwordQueryString($name));
        return $password; 
    }

    switch ( $this->authMode ) {

      case "LDAP":
          
        // ldap code
        $my_ldap = new Ldap();
        $my_ldap->connectForReading();
        $user_data = $my_ldap->loadUser($name, $password);
        return $user_data["password"];
        break;

    case "ACTIVE_DIR":
      
      // This function must not be called for ACTIVE_DIR.
      echo "User::password() cannot be called for ACTIVE_DIR!\n";
      exit( 1 );
    
    case "MYSQL":
      
          // db code
          $db = new DatabaseConnection();
          $password = $db->queryLastValue($db->passwordQueryString($name));
          return $password; 
    
      break;
    
    default:

      throw new Exception("Bad value for $authMode in User::password().");

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

    // If the user is the admin, we check the MYSQL DB
    if ( $name == $this->getAdminName() ) {
      $result = $this->checkLoginAgainstHRMDatabase($name, $password);
      return $result;
    }
    
    // Check other login names against the chosen authentication mechanism
    switch ( $this->authMode ) {

      case "LDAP":
 
        $result = $this->checkLoginAgainstLDAP($name, $password);
        break;
      
      case "ACTIVE_DIR":
        
        $result = $this->checkLoginAgainstACTIVEDIR($name, $password);
        break;
        
      case "MYSQL":

        $result = $this->checkLoginAgainstHRMDatabase($name, $password);
        break;

      default:
      
        throw new Exception("Bad value for $authMode in User::checkLogin().");
    }
    
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
  // @function    User::checkLoginAgainstACTIVEDIR
  // @desc        Answer true if the login is granted.
  // @param       name     String    The login name of the user
  // @param       password String    The password of the user 
  // @return      Boolean
  //!---------------------------------------------------------    
  function checkLoginAgainstACTIVEDIR($name, $password) {
    $activeDir = new ActiveDirectory( );
    $result = $activeDir->authenticate( strtolower($name), $password );
	return $result;
  }
  
  //!---------------------------------------------------------
  // @function    User::emailAddress
  // @desc        Answer the user's email address.
  // @return      String
  //!---------------------------------------------------------    
  function emailAddress() {

	$result = "";
    
    switch ( $this->authMode ) {

      case "LDAP":

        $ldap = new Ldap();
        $result = $ldap->emailAddress($this->name());
        return $result;
        break;

      case "ACTIVE_DIR":

        $activeDir = new ActiveDirectory( );
        $result = $activeDir->emailAddress($this->name());
        return $result;
        break;
        
      case "MYSQL":

        $db = new DatabaseConnection(); 
        $result = $db->emailAddress($this->name);
        break;

      default:

        throw new Exception("Bad value for $authMode in User::emailAddress().");
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
  // @function    User::group
  // @desc        Answer the group name from the database or
  //			  the default group name if ldap is used.
  // @return      String
  //!--------------------------------------------------------- 
  function userGroup() {
    switch ( $this->authMode ) {

      case "LDAP":

        $ldap = new Ldap();
        $result = $ldap->getGroup($this->name());
        return $result;
        break;

      case "ACTIVE_DIR":

        $activeDir = new ActiveDirectory( );
        $result = $activeDir->getGroup( $this->name() );
        return $result;
        break;
      
      case "MYSQL":

  		$db = new DatabaseConnection();
  		$result = $db->getGroup($this->name());
        break;

      default:

        throw new Exception("Bad value for $authMode in User::userGroup().");
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

} 



?>
