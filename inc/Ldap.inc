<?php

//!--------------------------------------------------------------------
// @class    Ldap
// @desc     Manage ldap connections
//!--------------------------------------------------------------------

include "ldap_config.inc";

Class Ldap {
  
  private $connection; 

  public function __construct ( ) {

    global $ldap_host;
    global $ldap_port;
    global $ldap_root;
	
	// Set to connection to null
	$this->connection = null;

	// Connect
	$ds = @ldap_connect( $ldap_host, $ldap_port );
	if ( $ds ) {
	  
	  // Set the connection
	  $this->connection = $ds;
	  
	  // Set protocol
	  @ldap_set_option( $this->connection, LDAP_OPT_PROTOCOL_VERSION, 3 );
	  //@ldap_set_option($con, LDAP_OPT_REFERRALS, 0);
	}
  
  }
  
  public function __destruct() {
	if ( $this->isConnected() ) {
	  @ldap_close( $this->connection );
	}
  }

  public function emailAddress( $uid ) {
	
	global $ldap_user_search_DN;
	global $ldap_root;

	// Bind the manager
	if ( ! $this->bindManager( ) ) {
	  return "";
	}
	
	// Searching for user $uid
    $filter = "(uid=" . $uid . ")";
    $searchbase = $ldap_user_search_DN . "," . $ldap_root;
	$sr = @ldap_search( $this->connection, $searchbase, $filter, array('uid','mail') );
    if ( !$sr ) {
      return "";
	}
	if ( @ldap_count_entries( $this->connection, $sr ) != 1 ) {
	  return "";
	}
    $info = @ldap_get_entries( $this->connection, $sr );
	$email = $info[ 0 ][ "mail" ][ 0 ];
	return $email;
  }
  
  public function authenticate( $uid, $userPassword ) {

	global $ldap_user_search_DN;
    global $ldap_root;

	if ( ! $this->isConnected( ) ) {
	  return false;
	}
	
	// This is a weird behavior: if the password is empty, the binding succeds!
	// Therefore we check in advance that the password is NOT empty!
	if ( empty( $userPassword ) ) {
	  return false;
	}
	
	// Create the dn	  
	$dn = "uid=" . $uid . ", " .$ldap_user_search_DN . "," . $ldap_root;
	
	// Bind the user (and not the manager)
	$r = @ldap_bind( $this->connection, $dn, $userPassword );
    if ( $r ) {
  	  return true;
    }
    return false;
  }

  public function getGroup( $uid ) {
	// TODO Get group from Ldap server!
	if ( ! $this->isConnected() ) {
	  return "";
	}
    return "hrm";
  }
    
  public function isConnected() {
	return ( $this->connection != null );
  }

  public function lastError() {
	if ( $this->isConnected() ) {
	  return @ldap_error( $this->connection );
	} else {
	  return "";
	}
  }
   
  private function bindManager( ) {
	
	global $ldap_manager;
	global $ldap_root;
	global $ldap_password;
	
	if ( ! $this->isConnected( ) ) {
		return false;
	}
	
	$dn = "cn=$ldap_manager" . "," . $ldap_root;
	$r = @ldap_bind( $this->connection, $dn, $ldap_password );
    if ( $r ) {
      return true;
    }

  }
  
}

?>
