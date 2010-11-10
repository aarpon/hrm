<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once( dirname( __FILE__ ) . "/extern/adLDAP/adLDAP.php" ); 

//!--------------------------------------------------------------------
// @class    ActiveDirectory
// @desc     Manage active directory connections through adLDAP
//!--------------------------------------------------------------------

Class ActiveDirectory {
  private $m_AdLDAP;      // the adLDAP object
  private $m_GroupIndex;  // users usually belong to several groups, m_GroupIndex 
                          // defines which level of the hierarchy to consider. 
					      // If $m_GroupIndex is -1 and the $m_ValidGroups array
					      // is empty, ActiveDirectory::getGroup( ) will return
					      // an array with all groups.
  private $m_ValidGroups; // contains a list of valid groups. If $m_GroupIndex
                          // is set to -1 and $m_ValidGroups is not empty, the
						  // groups array returned by adLDAP->user_groups will
						  // be compared with $m_ValidGroups and only the first
						  // group in the intersection will be returned (ideally,
						  // the intersection should contain only one group).

  function __construct ( ) {

		include( dirname( __FILE__ ) . "/../config/active_directory_config.inc" );
		
    	// Set up the adLDAP object
		$options = array(
    	  'account_suffix'     => $ACCOUNT_SUFFIX,
		  'base_dn'            => $BASE_DN,
		  'domain_controllers' => $DOMAIN_CONTROLLERS,
		  'ad_username'        => $AD_USERNAME,
		  'ad_password'        => $AD_PASSWORD,
		  'real_primarygroup'  => $REAL_PRIMARY_GROUP,
		  'use_ssl'            => $USE_SSL,
		  'use_tls'            => $USE_TLS,
		  'recursive_groups'   => $RECURSIVE_GROUPS );

		$this->m_GroupIndex    = $GROUP_INDEX;
		$this->m_ValidGroups   = $VALID_GROUPS;
		
		try {
		  $this->m_AdLDAP = new adLDAP( $options );
		} catch (adLDAPException $e) {	
		  echo $e; exit();	
		}
	}
 
  function authenticate( $username, $password ) {
	return $this->m_AdLDAP->authenticate( $username , $password );
  }

  function emailAddress( $username ) {
	$userEmailAddress = $this->m_AdLDAP->user_info( $username, array( "mail" ) );
	if (!$userEmailAddress) {
	  return "";
	}
	return $userEmailAddress[ 0 ][ "mail" ][ 0 ];
  }

  function getGroup( $username ) {
	$userGroups = $this->m_AdLDAP->user_groups( $username );
	if ( count( $userGroups ) == 0 ) {
	  return $userGroups;
	}
	if ( $this->m_GroupIndex == -1 ) {
	  // Should we check against the $VALID_GROUP array?
	  if ( count( $this->m_ValidGroups ) > 0 ) {
		$groups = array_values( array_intersect( $userGroups, $this->m_ValidGroups ) );
		if ( count( $groups ) > 0 ) {
		  return $groups[ 0 ];
		} else {
		  return $userGroups;
		}
	  } else {
		return $userGroups;
	  }
	}
	if ( $this->m_GroupIndex >= 0 &&
		 $this->m_GroupIndex < count( $userGroups ) ) {
	  return $userGroups[ $this->m_GroupIndex ];
	} else {
	  return $userGroups[ 0 ];
	}
  }

}

?>
