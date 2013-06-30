<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include adLDAP.php
require_once( dirname(__FILE__) . "/extern/adLDAP4/src/adLDAP.php" );

/*!
  \class	ActiveDirectory
  \brief	Manages active directory connections through the adLDAP library

  The configuration file for the ActiveDirectory class is
  config/active_directory_config.inc. A sample configuration file is
  config/samples/active_directory_config.inc.sample.
  A user with read-access to Active Directory must be set up in the
  configuration file for queries to be possible.
 */

class ActiveDirectory {

    /*!
      \var      $m_AdLDAP
      \brief	The adLDAP object
     */
    private $m_AdLDAP;

    /*!
      \var      $m_GroupIndex
      \brief	Index (level) of the group to consider

      Users usually belong to several groups, m_GroupIndex defines which
      level of the hierarchy to consider.
      If $m_GroupIndex is -1 and the $m_ValidGroups array is empty,
      ActiveDirectory::getGroup( ) will return an array with all groups.
     */
    private $m_GroupIndex;

    /*!
      \var      $m_ValidGroups
      \brief	Array of valid groups

      If $m_GroupIndex is set to -1 and $m_ValidGroups is not empty,
      the groups array returned by adLDAP->user_groups will be compared
      with $m_ValidGroups and only the first group in the intersection
      will be returned (ideally, the intersection should contain only
      one group).
     */
    private $m_ValidGroups;

    /*!
      \brief	Constructor: instantiates an ActiveDirectory object with
      the settings specified in the configuration file. No
      parameters are passed to the constructor.
     */
    public function __construct( ) {

        // Include configuration file
        include( dirname( __FILE__ ) . "/../config/active_directory_config.inc" );

        // Set up the adLDAP object
        $options = array(
            'account_suffix'     => $ACCOUNT_SUFFIX,
            'base_dn'            => $BASE_DN,
            'domain_controllers' => $DOMAIN_CONTROLLERS,
            'admin_username'     => $AD_USERNAME,
            'admin_password'     => $AD_PASSWORD,
            'real_primarygroup'  => $REAL_PRIMARY_GROUP,
            'use_ssl'            => $USE_SSL,
            'use_tls'            => $USE_TLS,
            'recursive_groups'   => $RECURSIVE_GROUPS);

        $this->m_GroupIndex      =  $GROUP_INDEX;
        $this->m_ValidGroups     =  $VALID_GROUPS;

        try {
            $this->m_AdLDAP = new adLDAP( $options );
        } catch ( adLDAPException $e ) {
            // Make sure to clean stack traces
            $pos = stripos($e, 'AD said:');
            if ($pos !== false) {
                $e = substr($e, 0, $pos);
            }
            echo $e;
            exit();
        }
    }

    /*!
      \brief	Tries to authenticate a user against Active Directory.
      \param	$username	User name
      \param	$password	Password
      \return	true if the user could be authenticated; false otherwise
     */
    public function authenticate( $username, $password ) {
        return $this->m_AdLDAP->user( )->authenticate( $username, $password );
    }

    /*!
      \brief	Returns the e-mail address for an existing user
      \param	$username	User name
      \return	The e-mail address
     */
    public function emailAddress( $username ) {
        $info = $this->m_AdLDAP->user( )->infoCollection(
            $username, array( "mail" ) );
        if ( !$info ) {
            return "";
        }
        return $info->mail;
    }

    /*!
      \brief	Returns the group(s) to which a user belongs.
      \param	$username	User name
      \return	One or more groups
     */
    public function getGroup( $username ) {
        $userGroups = $this->m_AdLDAP->user( )->groups( $username );
        if ( !$userGroups ) {
            return "";
        }
        if ( $this->m_GroupIndex == -1 ) {
            // Should we check against the $VALID_GROUP array?
            if ( !is_array( $userGroups ) ) {
                $userGroups = array( $userGroups );
            }
            if ( count( $this->m_ValidGroups ) > 0 ) {
                $groups = array_values( array_intersect( $userGroups,
                    $this->m_ValidGroups ) );
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