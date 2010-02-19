<?php
// php page: login.php

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

		include( dirname( __FILE__ ) . "/active_directory_config.inc" );
		
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
