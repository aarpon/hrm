<?php

// Author: Aaron Ponti

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
	if ( $ldap_use_ssl == true ) {
	  $ds = @ldap_connect( "ldaps://" . $ldap_host, $ldap_port );
	} else {
	  $ds = @ldap_connect( $ldap_host, $ldap_port );
	}
	if ( $ds ) {
	  
	  // Set protocol
	  @ldap_set_option( $this->connection, LDAP_OPT_PROTOCOL_VERSION, 3 );
	  
	  if ( $ldap_use_tls ) {
        ldap_start_tls( $ds );
      }
	  
 	  // Set the connection
	  $this->connection = $ds;
	  
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

	// Searching for user $uid
    $filter = "(uid=" . $uid . ")";
	$searchbase = $ldap_user_search_DN . "," . $ldap_root;
	$sr = @ldap_search( $this->connection, $searchbase, $filter, array('uid') );
	if ( !$sr ) {
	  return false;
	}
	if ( @ldap_count_entries( $this->connection, $sr ) != 1 ) {
	  return false;
	}
	
	// Now we try to bind with the found dn
	$result = @ldap_get_entries( $this->connection, $sr );
	if ( $result[ 0 ] ) {
	  if (@ldap_bind( $this->connection, $result[0]['dn'], $userPassword ) ) {
		return true;
	  } else {
		return false;
	  }
	} else {
	  return false;
	}
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