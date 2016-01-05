<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include adLDAP.php and the AbstractAuthenticator.
require_once(dirname(__FILE__) . "/AbstractAuthenticator.inc.php");
require_once(dirname(__FILE__) . "/../extern/adLDAP4/src/adLDAP.php");

/*!
  \class	ActiveDirectoryAuthenticator
  \brief	Manages Active Directory connections through the adLDAP library.

  The configuration file for the ActiveDirectoryAuthenticator class is
  config/active_directory_config.inc. A sample configuration file is
  config/samples/active_directory_config.inc.sample.
  A user with read-access to Active Directory must be set up in the
  configuration file for queries to be possible.
 */

class ActiveDirectoryAuthenticator extends AbstractAuthenticator {

    /*!
      \var      $m_AdLDAP
      \brief	The adLDAP object
     */
    private $m_AdLDAP;

    /*!
      \var      $m_ValidGroups
      \brief	Array of valid groups

      If $m_ValidGroups is not empty, the groups array returned by
      adLDAP->user_groups will be compared with $m_ValidGroups and
      only the first group in the intersection will be returned
      (ideally, the intersection should contain only one group).
     */
    private $m_ValidGroups;

    /*!
      \var      $m_ValidGroups
      \brief	Array of authorized groups

      If $m_AuthorizedGroups is not empty, the groups array returned by
      adLDAP->user_groups will be intersected with $m_AuthorizedGroups.
      If the intersection is empty, the user will not be allowed to log in.
     */
    private $m_AuthorizedGroups;

    /*!
    \var    $m_UsernameSuffix
    \brief  TODO Complete
    */
    private $m_UsernameSuffix;

    /*!
    \var    $m_UsernameSuffixReplaceMatch
    \brief  TODO Complete
    */
    private $m_UsernameSuffixReplaceMatch;

    /*!
    \var    $m_UsernameSuffixReplaceString
    \brief  TODO Complete
    */
    private $m_UsernameSuffixReplaceString;

    /*!
      \brief	Constructor: instantiates an ActiveDirectoryAuthenticator object with
      the settings specified in the configuration file. No
      parameters are passed to the constructor.
     */
    public function __construct() {

        global $ACCOUNT_SUFFIX, $AD_PORT, $BASE_DN, $DOMAIN_CONTROLLERS,
               $AD_USERNAME, $AD_PASSWORD, $REAL_PRIMARY_GROUP, $USE_SSL,
               $USE_TLS, $RECURSIVE_GROUPS, $AUTHORIZED_GROUPS, $VALID_GROUPS,
               $AD_USERNAME_SUFFIX, $AD_USERNAME_SUFFIX_PATTERN,
               $AD_USERNAME_SUFFIX_REPLACE;


        // Include configuration file
        include(dirname(__FILE__) . "/../../config/active_directory_config.inc");

        // Set up the adLDAP object
        $options = array(
            'account_suffix'      => $ACCOUNT_SUFFIX,
            'ad_port'             => $AD_PORT,
            'base_dn'             => $BASE_DN,
            'domain_controllers'  => $DOMAIN_CONTROLLERS,
            'admin_username'      => $AD_USERNAME,
            'admin_password'      => $AD_PASSWORD,
            'real_primarygroup'   => $REAL_PRIMARY_GROUP,
            'use_ssl'             => $USE_SSL,
            'use_tls'             => $USE_TLS,
            'recursive_groups'    => $RECURSIVE_GROUPS);

        // Check group filters
        if ($VALID_GROUPS === null) {
            report('VALID_GROUPS not set for Active Directory authentication!', 0);
            $VALID_GROUPS = array();
        }
        if ($AUTHORIZED_GROUPS === null) {
            report('AUTHORIZED_GROUPS not set for Active Directory authentication!', 0);
            $AUTHORIZED_GROUPS = array();
        }
        if (count($VALID_GROUPS) == 0 && count($AUTHORIZED_GROUPS) > 0) {
            // Copy the array
            $VALID_GROUPS = $AUTHORIZED_GROUPS;
        }
        $this->m_ValidGroups      =  $VALID_GROUPS;
        $this->m_AuthorizedGroups =  $AUTHORIZED_GROUPS;

        $this->m_UsernameSuffix = $AD_USERNAME_SUFFIX;
        $this->m_UsernameSuffixReplaceMatch = $AD_USERNAME_SUFFIX_PATTERN;
        $this->m_UsernameSuffixReplaceString = $AD_USERNAME_SUFFIX_REPLACE;

        try {
            $this->m_AdLDAP = new adLDAP($options);
        } catch (adLDAPException $e) {
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
      \brief	Destructor. Closes the connection started by the adLDAP object.
     */
    public function __destruct() {
        // We ask the adLDAP object to close the connection. A check whether a
        // connection actually exists will be made by the adLDAP object itself.
        // This is a fallback to make sure to close any open sockets when the
        // object is deleted, since all methods of this class that access the
        // adLDAP object explicitly close the connection when done.
        $this->m_AdLDAP->close();
    }

    /*!
    \brief  Authenticates the User with given username and password against
            Active Directory.
    \param  $username String Username for authentication.
    \param  $password String Password for authentication.
    \return boolean: True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password) {

        // Make sure the user is active
        if (!$this->isActive($username)) {
            return false;
        }

        // Authenticate against AD
        $b = $this->m_AdLDAP->user()->authenticate(
            strtolower($username), $password);

        // Do we need to check for group authorization?
        if (count($this->m_AuthorizedGroups) == 0) {

            // No, we don't
            $this->m_AdLDAP->close();
            return $b;

        } else {

            // If authentication failed, we can return here.
            if ($b === false) {

                $this->m_AdLDAP->close();
                return false;
            }

            // We need to retrieve the groups and compare them.

            // If needed, process the user name suffix for subdomains
            $username .= $this->m_UsernameSuffix;
            if ($this->m_UsernameSuffixReplaceMatch != '') {
                $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
                $username = preg_replace($pattern,
                    $this->m_UsernameSuffixReplaceString,
                    $username);
            }

            // Get the user groups from AD
            $userGroups = $this->m_AdLDAP->user()->groups($username);
            $this->m_AdLDAP->close();

            // Test for intersection
            if (count($this->m_ValidGroups) > 0) {
                $b = count(array_intersect(
                        $userGroups, $this->m_AuthorizedGroups)) > 0;
                if ($b === true) {
                    report("User $username: group authentication succeeded.", 0);
                } else {
                    report("User $username: user rejected by failed group authentication.", 0);
                }
                return $b;
            }
        }
    }

    /*!
    \brief  Return the email address of user with given username.
    \param  $username String Username for which to query the email address.
    \return String email address or NULL
    */
    public function getEmailAddress($username) {

        // If needed, process the user name suffix for subdomains
        $username .= $this->m_UsernameSuffix;
        if ($this->m_UsernameSuffixReplaceMatch != '') {
            $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
            $username = preg_replace($pattern,
                                     $this->m_UsernameSuffixReplaceString,
                                     $username);
        }

        // Get the email from AD
        $info = $this->m_AdLDAP->user()->infoCollection(
            $username, array("mail"));

        $this->m_AdLDAP->close();
        if (!$info) {
            report('No email address found for username "' . $username . '"', 2);
            return "";
        }
        report('Email for username "' . $username . '": ' . $info->mail, 2);
        return $info->mail;
    }

    /*!
    \brief Return the group the user with given username belongs to.
    \param $username String Username for which to query the group.
    \return String Group or "" if not found.
    */
    public function getGroup($username) {

        // If needed, process the user name suffix for subdomains
        $username .= $this->m_UsernameSuffix;
        if ($this->m_UsernameSuffixReplaceMatch != '') {
            $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
            $username = preg_replace($pattern,
                                     $this->m_UsernameSuffixReplaceString,
                                     $username);
        }

        // Get the user groups from AD
        $userGroups = $this->m_AdLDAP->user()->groups($username);
        $this->m_AdLDAP->close();

        // If no groups found, return ""
        if (!$userGroups) {
            report('No groups found for username "' . $username . '"', 0);
            return "";
        }

        // Make sure to work on an array
        if (!is_array($userGroups)) {
            $userGroups = array($userGroups);
        }

        // If the list of valid groups is not empty, find the intersection
        // with the returned group list; otherwise, keep working with the
        // original array.
        if (count($this->m_ValidGroups) > 0) {
            $userGroups = array_values(array_intersect(
                $userGroups, $this->m_ValidGroups));
        }

        // Now return the first entry
        if (count($userGroups) == 0) {
            report("Group for username $username not found in the list of valid groups!", 0);
            $group = "";
        } else {
            $group = $userGroups[0];
        }

        report('Group for username "' . $username . '": ' . $group, 2);
        return $group;

    }

}
?>
