<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \class	Ldap	
  \brief	Manages LDAP connections through built-in PHP LDAP support
  
  The configuration file for the Ldap class is
  config/ldap_config.inc. A sample configuration file is
  config/samples/ldap_config.inc.sample.
  A user with read-access to the LDAP server must be set up in the
  configuration file for queries to be possible.
*/

include( dirname( __FILE__ ) . "/../config/ldap_config.inc" );

class Ldap {
  
  private $connection; 

  /*!
	\brief	Constructor: instantiates an Ldap object with the settings
			specified in the configuration file.
  */
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

  /*!
	\brief	Destructor: closes the connection.
  */
  public function __destruct() {
	if ( $this->isConnected() ) {
	  @ldap_close( $this->connection );
	}
  }

  /*!
	\brief	Returns the e-mail address of a user with given id
	\param	$uid	User name
	\return	e-mail address
  */
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
  
 
  /*!
	\brief	Tries to authenticate a user against LDAP
	\param	$uid			User name
	\param	$userPassword	Password
	\return	true if the user could be authenticated; false otherwise
  */
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

  /*!
	\brief	Returns the group for a given user name (remark: The group for an LDAP user is always hrm!)
	\param	$uid			User name
	\return	user name
	\todo	Get real group from LDAP!
  */
  public function getGroup( $uid ) {
	// TODO Get group from LDAP server!
	if ( ! $this->isConnected() ) {
	  return "";
	}
    return "hrm";
  }
    
  /*!
	\brief	Check whether there is a connection to LDAP
	\return	true if the connection is up, false otherwise
  */
  public function isConnected() {
	return ( $this->connection != null );
  }

  /*!
	\brief	Returns the last occurred error
	\return	last ldap error
  */
  public function lastError() {
	if ( $this->isConnected() ) {
	  return @ldap_error( $this->connection );
	} else {
	  return "";
	}
  }
   
  /*!
	\brief	Binds LDAP with the configured manager for queries to be possible
	\return	true if the manager could bind, false otherwise
  */
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