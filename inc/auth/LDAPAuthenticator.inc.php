<?php

// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \class	LDAPAuthenticator
  \brief	Manages LDAP connections through built-in PHP LDAP support

  The configuration file for the LDAPAuthenticator class is config/ldap_config.inc.
  A sample configuration file is config/samples/ldap_config.inc.sample.
  A user with read-access to the LDAP server must be set up in the
  configuration file for queries to be possible.
 */

// Include AbstractAuthenticator and Util.inc.php.
require_once("./AbstractAuthenticator.inc.php");
require_once("../Util.inc.php");

class LDAPAuthenticator extends AbstractAuthenticator {

    /*!
      \var    $m_Connection
      \brief  LDAP connection object
     */
    private $m_Connection;

    /*!
      \var    $m_LDAP_Host
      \brief  Machine on which the ldap server is running
     */
    private $m_LDAP_Host;

    /*!
      \var    $m_LDAP_Port
      \brief  Port for the ldap connection
     */
    private $m_LDAP_Port;

    /*!
      \var    $m_LDAP_Use_SSL
      \brief  Set to true to use SSL (LDAPS)
     */
    private $m_LDAP_Use_SSL;

    /*!
      \var    $m_LDAP_Use_TLS
      \brief  Set to true to use TLS

      If you wish to use TLS you should ensure that $m_LDAP_Use_SSL is
      set to false and vice-versa
     */
    private $m_LDAP_Use_TLS;

    /*!
      \var    $m_LDAP_Root
      \brief  Search root
     */
    private $m_LDAP_Root;

    /*!
      \var    $m_LDAP_Manager_Base_DN
      \brief  Base for the manager DN
     */
    private $m_LDAP_Manager_Base_DN;

    /*!
      \var    $m_LDAP_Manager
      \brief  The ldap manager (user name only!)
     */
    private $m_LDAP_Manager;

    /*!
      \var    $m_LDAP_Password
      \brief  The ldap password
     */
    private $m_LDAP_Password;

    /*!
      \var    $m_LDAP_User_Search_DN
      \brief  User search DN (without ldap root)
     */
    private $m_LDAP_User_Search_DN;

    /*!
      \var    $m_LDAP_Manager_OU
      \brief  LDAPAuthenticator manager OU: used in case the Ldap_Manager is in some
      special OU that distinguishes it from the other users
     */
    private $m_LDAP_Manager_OU;

    /*!
      \var    $m_LDAP_Valid_Groups
      \brief  Array of valid groups to be used to filter the groups to which
      the user belongs
     */
    private $m_LDAP_Valid_Groups;


    /*!
      \brief	Constructor: instantiates an LDAPAuthenticator object with the settings
      specified in the configuration file.
     */
    public function __construct() {

        // Include the configuration file
        include(dirname(__FILE__) . "../../config/ldap_config.inc");

        global $ldap_host, $ldap_port, $ldap_use_ssl, $ldap_use_tls, $ldap_root,
               $ldap_manager_base_DN, $ldap_manager, $ldap_password,
               $ldap_user_search_DN, $ldap_manager_ou, $ldap_valid_groups;

        // Assign the variables
        $this->m_LDAP_Host = $ldap_host;
        $this->m_LDAP_Port = $ldap_port;
        $this->m_LDAP_Use_SSL = $ldap_use_ssl;
        $this->m_LDAP_Use_TLS = $ldap_use_tls;
        $this->m_LDAP_Root = $ldap_root;
        $this->m_LDAP_Manager_Base_DN = $ldap_manager_base_DN;
        $this->m_LDAP_Manager = $ldap_manager;
        $this->m_LDAP_Password = $ldap_password;
        $this->m_LDAP_User_Search_DN = $ldap_user_search_DN;
        $this->m_LDAP_Manager_OU = $ldap_manager_ou;
        $this->m_LDAP_Valid_Groups = $ldap_valid_groups;

        // Set the connection to null
        $this->m_Connection = null;

        // Connect
        if ($this->m_LDAP_Use_SSL == true) {
            $ds = @ldap_connect(
                "ldaps://" . $this->m_LDAP_Host, $this->m_LDAP_Port);
        } else {
            $ds = @ldap_connect($this->m_LDAP_Host, $this->m_LDAP_Port);
        }

        if ($ds) {

            // Set the connection
            $this->m_Connection = $ds;

            // Set protocol (and check)
            if (!ldap_set_option($this->m_Connection,
                LDAP_OPT_PROTOCOL_VERSION, 3)
            ) {
                report("[LDAP] ERROR: Could not set LDAP protocol version to 3.",
                    0);
            }

            if ($this->m_LDAP_Use_TLS) {
                if (!ldap_start_tls($ds)) {
                    report("[LDAP] ERROR: Could not activate TLS.", 0);
                }
            }

        } else {
            report("[LDAP] ERROR: Could not connect to $this->m_LDAP_Host.", 0);
        }
    }

    /*!
      \brief	Destructor: closes the connection.
     */
    public function __destruct() {
        if ($this->isConnected()) {
            @ldap_close($this->m_Connection);
        }
    }

    /*!
    \brief  Return the email address of user with given username.
    \param  $username String Username for which to query the email address.
    \return String email address or NULL
    */
    public function getEmailAddress($uid) {

        // Bind the manager
        if (!$this->bindManager()) {
            return "";
        }

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search(
            $this->m_Connection, $searchbase, $filter, array('uid', 'mail'));
        if (!$sr) {
            return "";
        }
        if (@ldap_count_entries($this->m_Connection, $sr) != 1) {
            return "";
        }
        $info = @ldap_get_entries($this->m_Connection, $sr);
        $email = $info[0]["mail"][0];
        return $email;
    }

    /*!
    \brief  Authenticates the User with given username and password against LDAP.
    \param  $username String Username for authentication.
    \param  $password String Password for authentication.
    \return boolean: True if authentication succeeded, false otherwise.
    */
    public function authenticate($uid, $userPassword) {

        if (!$this->isConnected()) {
            report("[LDAP] ERROR: Authenticate -- not connected!", 0);
            return false;
        }

        // This is a weird behavior of LDAP: if the password is empty, the
        // binding succeeds!
        // Therefore we check in advance that the password is NOT empty!
        if (empty($userPassword)) {
            report("[LDAP] ERROR: Authenticate: empty manager password!", 0);
            return false;
        }

        // Bind the manager -- or we won't be allowed to search for the user
        // to authenticate
        if (!$this->bindManager()) {
            return "";
        }

        // Make sure $uid is lowercase
        $uid = strtolower($uid);

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search(
            $this->m_Connection, $searchbase, $filter, array('uid'));
        if (!$sr) {
            report("[LDAP] ERROR: Authenticate -- search failed! " .
                "Search base: \"$searchbase\"", 0);
            return false;
        }
        if (@ldap_count_entries($this->m_Connection, $sr) != 1) {
            return false;
        }

        // Now we try to bind with the found dn
        $result = @ldap_get_entries($this->m_Connection, $sr);
        if ($result[0]) {
            if (@ldap_bind($this->m_Connection,
                $result[0]['dn'], $userPassword)
            ) {
                return true;
            } else {
                // Wrong password
                return false;
            }
        } else {
            return false;
        }
    }

    /*!
    \brief Return the group the user with given username belongs to.
    \param $username String Username for which to query the group.
    \return String Group or "" if not found.
    */
    public function getGroup($uid) {

        // Bind the manager
        if (!$this->bindManager()) {
            return "";
        }

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search($this->m_Connection, $searchbase, $filter,
            array('uid', 'memberof'));
        if (!$sr) {
            report("[LDAP] WARNING: Group -- no group information found!", 0);
            return "";
        }

        // Get the membership information
        $info = @ldap_get_entries($this->m_Connection, $sr);
        $groups = $info[0]["memberof"];

        // Filter by valid groups?
        if (count($this->m_LDAP_Valid_Groups) == 0) {

            // The configuration did not specify any valid groups
            $groups = array_diff(
                explode(',', strtolower($groups[0])),
                explode(',', strtolower($searchbase)));
            if (count($groups) == 0) {
                return "";
            }
            $groups = $groups[0];
            // Remove ou= or cn= entries
            $matches = array();
            if (!preg_match('/^(OU=|CN=)(.+)/i', $groups, $matches)) {
                return "";
            } else {
                if ($matches[2] == null) {
                    return "";
                }
                return $matches[2];
            }
        } else {

            // The configuration contains a list of valid groups
            for ($i = 0; $i < count($groups); $i++) {
                for ($j = 0; $j < count($this->m_LDAP_Valid_Groups); $j++) {
                    if (strpos($groups[$i], $this->m_LDAP_Valid_Groups[$j])) {
                        return ($this->m_LDAP_Valid_Groups[$j]);
                    }
                }
            }
        }
        return "";
    }

    /*!
      \brief	Check whether there is a connection to LDAP
      \return	true if the connection is up, false otherwise
     */
    public function isConnected() {
        return ($this->m_Connection != null);
    }

    /*!
      \brief	Returns the last occurred error
      \return	last ldap error
     */
    public function lastError() {
        if ($this->isConnected()) {
            return @ldap_error($this->m_Connection);
        } else {
            return "";
        }
    }

    /*!
      \brief	Binds LDAP with the configured manager for queries to
                be possible
      \return	true if the manager could bind, false otherwise
     */

    private function bindManager() {

        if (!$this->isConnected()) {
            return false;
        }

        // Search DN
        $dn = $this->dnStr();

        // Bind
        $r = @ldap_bind($this->m_Connection, $dn, $this->m_LDAP_Password);
        if ($r) {
            return true;
        }

        // If binding failed, we report
        report("[LDAP] ERROR: Binding: binding failed! " .
            "Search DN: \"$dn\"", 0);
        return false;
    }

    /*!
      \brief	Create the search base string
      \return	Search base string
     */

    private function searchbaseStr() {
        return ($this->m_LDAP_User_Search_DN . "," . $this->m_LDAP_Root);
    }

    /*!
      \brief	Create the DN string
      \return	DN string
     */

    private function dnStr() {
        $dn = $this->m_LDAP_Manager_Base_DN . "=" .
            $this->m_LDAP_Manager . "," .
            $this->m_LDAP_Manager_OU . "," .
            $this->m_LDAP_User_Search_DN . "," .
            $this->m_LDAP_Root;
        // Since m_LDAP_Manager_OU can be empty, we make sure not
        // to have double commas
        $dn = str_replace(',,', ',', $dn);
        return $dn;
    }

}

?>
