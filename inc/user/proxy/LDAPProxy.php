<?php
/**
 * LDAPProxy
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

use Exception;
use hrm\Log;

/**
 * Manages LDAP connections through built-in PHP LDAP support
 *
 * The configuration file for the LDAPProxy class is config/ldap_config.inc.
 * A sample configuration file is config/samples/ldap_config.inc.sample.
 * A user with read-access to the LDAP server must be set up in the
 * configuration file for queries to be possible.
 *
 * @package hrm
 */
class LDAPProxy extends AbstractProxy
{
    /**
     * LDAP connection object
     * @var resource
     */
    private $m_Connection;

    /**
     * Machine on which the ldap server is running.
     * @var string
     */
    private $m_LDAP_Host;

    /**
     * Port for the ldap connection.
     * @var int
     */
    private $m_LDAP_Port;

    /**
     * Set to true to use SSL (LDAPS).
     * @var bool
     */
    private $m_LDAP_Use_SSL;

    /**
     * Set to true to use TLS.
     *
     * If you wish to use TLS you should ensure that $m_LDAP_Use_SSL is
     * set to false and vice-versa
     *
     * @var bool
     */
    private $m_LDAP_Use_TLS;

    /**
     * Search root.
     * @var string
     */
    private $m_LDAP_Root;

    /**
     * Base for the manager DN.
     * @var string
     */
    private $m_LDAP_Manager_Base_DN;

    /**
     * The ldap manager (user name only!).
     * @var string
     */
    private $m_LDAP_Manager;

    /**
     * The ldap password.
     * @var string
     */
    private $m_LDAP_Password;

    /**
     * User search DN (without ldap root).
     * @var string
     */
    private $m_LDAP_User_Search_DN;

    /**
     * LDAPProxy manager OU: used in case the Ldap_Manager is in some
     * special OU that distinguishes it from the other users.
     *
     * @var string
     */
    private $m_LDAP_Manager_OU;

    /**
     * Array of valid groups to be used to filter the groups to which the user
     * belongs or null to disable filtering.
     * @var array|null
     */
    private $m_LDAP_Valid_Groups;

    /**
     * Array of authorized groups or null to disable group authorization.
     *
     * If $m_LDAP_Authorized_Groups is not empty, the user groups array will
     * be intersected with $m_LDAP_Authorized_Groups. If the intersection is
     * empty, the user will not be allowed to log in.
     *
     * @var array|null
     */
    private $m_LDAP_Authorized_Groups;

    /**
     * LDAPProxy constructor: : instantiates an LDAPProxy object
     * with the settings specified in the configuration file.
     * @throws Exception If the LDAP configuration file could not be found.
     */
    public function __construct()
    {
        global $ldap_host, $ldap_port, $ldap_use_ssl, $ldap_use_tls, $ldap_root,
               $ldap_manager_base_DN, $ldap_manager, $ldap_password, $ldap_user_search_DN,
               $ldap_manager_ou, $ldap_valid_groups, $ldap_authorized_groups;

        // Include the configuration file
        $conf = dirname(__FILE__) . "/../../../config/ldap_config.inc";
        if (! is_file($conf)) {
            $msg = "The LDAP configuration file 'ldap_config.inc' is missing!";
            Log::error($msg);
            throw new Exception($msg);
        }
        /** @noinspection PhpIncludeInspection */
        include($conf);

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

        // Check group filters
        if ($ldap_valid_groups === null) {
            Log::warning('ldap_valid_groups not set for LDAP authentication!');
            $ldap_valid_groups = array();
        }
        if ($ldap_authorized_groups === null) {
            Log::warning('ldap_authorized_groups not set for LDAP authentication!');
            $ldap_authorized_groups = array();
        }
        if (count($ldap_valid_groups) == 0 && count($ldap_authorized_groups) > 0) {
            // Copy the array
            $ldap_valid_groups = $ldap_authorized_groups;
        }
        $this->m_LDAP_Valid_Groups =  $ldap_valid_groups;
        $this->m_LDAP_Authorized_Groups =  $ldap_authorized_groups;

        // Set the connection to null
        $this->m_Connection = null;

        // Connect
        if ($this->m_LDAP_Use_SSL == true) {
            $ds = @ldap_connect("ldaps://" . $this->m_LDAP_Host, $this->m_LDAP_Port);
        } else {
            $ds = @ldap_connect($this->m_LDAP_Host, $this->m_LDAP_Port);
        }

        if ($ds) {
            // Set the connection
            $this->m_Connection = $ds;

            // Set protocol (and check)
            if (!ldap_set_option($this->m_Connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                Log::error("[LDAP] ERROR: Could not set LDAP protocol version to 3.");
            }

            if ($this->m_LDAP_Use_TLS) {
                if (!ldap_start_tls($ds)) {
                    Log::error("[LDAP] ERROR: Could not activate TLS.");
                }
            }
        } else {
            Log::error("[LDAP] ERROR: Could not connect to $this->m_LDAP_Host.");
        }
    }

    /**
     * Destructor: closes the connection.
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            @ldap_close($this->m_Connection);
        }
    }

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     * @return string 'generic ldap'.
     */
    public function friendlyName()
    {
        return 'Generic LDAP';
    }

    /**
     * Return the email address of user with given username.
     * @param string $uid Username for which to query the email address.
     * @return string|null email address or null.
    */
    public function getEmailAddress($uid)
    {

        // Bind the manager
        if (!$this->bindManager()) {
            return "";
        }

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search($this->m_Connection, $searchbase, $filter, array('uid', 'mail'));
        if (!$sr) {
            return "";
        }
        if (@ldap_count_entries($this->m_Connection, $sr) != 1) {
            return "";
        }
        $info = @ldap_get_entries($this->m_Connection, $sr);
        return $info[0]["mail"][0];
    }

    /**
     * Authenticates the User with given username and password against LDAP.
     * @param string $uid Username for authentication.
     * @param string $userPassword Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($uid, $userPassword)
    {
        if (!$this->isConnected()) {
            Log::error("[LDAP] ERROR: Authenticate -- not connected!");
            return false;
        }

        // This is a weird behavior of LDAP: if the password is empty, the
        // binding succeeds!
        // Therefore we check in advance that the password is NOT empty!
        if (empty($userPassword)) {
            Log::error("[LDAP] ERROR: Authenticate: empty manager password!");
            return false;
        }

        // Bind the manager -- or we won't be allowed to search for the user
        // to authenticate
        if (!$this->bindManager()) {
            return false;
        }

        // Make sure $uid is lowercase
        $uid = strtolower($uid);

        // Is the user active?
        if (!$this->isActive($uid)) {
            return false;
        }

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search($this->m_Connection, $searchbase, $filter, array('uid', 'memberof'));
        if (!$sr) {
            Log::error("[LDAP] ERROR: Authenticate -- search failed! " .
                "Search base: \"$searchbase\"");
            return false;
        }
        if (@ldap_count_entries($this->m_Connection, $sr) != 1) {
            Log::error("[LDAP] ERROR: Authenticate -- user not found!");
            return false;
        }

        // Now we try to bind with the found dn
        $result = @ldap_get_entries($this->m_Connection, $sr);
        if ($result[0]) {
            // If this succeeds, the user is authenticated
            $b = @ldap_bind($this->m_Connection, $result[0]['dn'], $userPassword);

            // If authentication failed, we can return here.
            if ($b === false) {
                return false;
            }

            // If it succeeded, fo we need to check for group authorization?
            if (count($this->m_LDAP_Authorized_Groups) == 0) {
                // No, we don't
                return $b;
            }

            // Test whether at least one of the user groups is contained in
            // the list of authorize groups.
            $groups = $result[0]["memberof"];
            for ($i = 0; $i < count($groups); $i++) {
                for ($j = 0; $j < count($this->m_LDAP_Authorized_Groups); $j++) {
                    if (strpos($groups[$i], $this->m_LDAP_Authorized_Groups[$j])) {
                        Log::info("User $uid: group authentication succeeded.");
                        return true;
                    }
                }
            }

            // Not found
            Log::info("User $uid: user rejected by failed group authentication.");
            return false;
        }
        return false;
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $uid Username for which to query the group.
     * @return string Group or "" if not found.
    */
    public function getGroup($uid)
    {
        // Bind the manager
        if (!$this->bindManager()) {
            return "";
        }

        // Searching for user $uid
        $filter = "(uid=" . $uid . ")";
        $searchbase = $this->searchbaseStr();
        $sr = @ldap_search($this->m_Connection, $searchbase, $filter, array('uid', 'memberof'));
        if (!$sr) {
            Log::warning("[LDAP] WARNING: Group -- no group information found!");
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
                explode(',', strtolower($searchbase))
            );
            if (count($groups) == 0) {
                return "";
            }
            // Return the first group
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

    /**
     * Checks whether there is a connection to LDAP.
     * @return bool True if the connection is up, false otherwise.
     */
    public function isConnected()
    {
        return ($this->m_Connection != null);
    }

    /**
     * Returns the last occurred error.
     * @return string Last LDAP error.
     */
    public function lastError()
    {
        if ($this->isConnected()) {
            return @ldap_error($this->m_Connection);
        } else {
            return "";
        }
    }

    /**
     * Binds LDAP with the configured manager for queries to be possible.
     * @return bool True if the manager could bind, false otherwise.
     */

    private function bindManager()
    {
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
        Log::error("[LDAP] ERROR: Binding: binding failed! " .
            "Search DN: \"$dn\"");
        return false;
    }

    /**
     * Creates the search base string.
     * @return string Search base string.
     */
    private function searchbaseStr()
    {
        return ($this->m_LDAP_User_Search_DN . "," . $this->m_LDAP_Root);
    }

    /**
     * Creates the DN string.
     * @return string DN string.
     */
    private function dnStr()
    {
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
