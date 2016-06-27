<?php
/**
 * ActiveDirectoryProxy
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

use adLDAP\adLDAP;
use adLDAP\adLDAPException;
use hrm\Log;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Manages Active Directory connections through the adLDAP library.
 *
 * The configuration file for the ActiveDirectoryAuthenticator class is
 * config/active_directory_config.inc. A sample configuration file is
 * config/samples/active_directory_config.inc.sample.
 * A user with read-access to Active Directory must be set up in the
 * configuration file for queries to be possible.
 *
 * @package hrm
 */
class ActiveDirectoryProxy extends AbstractProxy {

    /**
     * The adLDAP object.
     * @var adLDAP
     */
    private $m_AdLDAP;

    /**
     * Array of valid groups.
     *
     * If $m_ValidGroups is not empty, the groups array returned by
     * adLDAP->user_groups will be compared with $m_ValidGroups and
     * only the first group in the intersection will be returned
     * (ideally, the intersection should contain only one group).
     *
     * @var array|null
     */
    private $m_ValidGroups;

    /**
     * Array of authorized groups.
     *
     * If $m_AuthorizedGroups is not empty, the groups array returned by
     * adLDAP->user_groups will be intersected with $m_AuthorizedGroups.
     * If the intersection is empty, the user will not be allowed to log in.
     *
     * @var array|null
     */
    private $m_AuthorizedGroups;

    /**
     * Append this to usernames in AD-forests to request the email address.
     *
     * Suffix that should be appended to usernames for retrieving the email
     * address in an ActiveDirectory FOREST (multi-domain setup). See the
     * configuration file "active_directory_config.inc" for a detailed example.
     *
     * @var string
    */
    private $m_UsernameSuffix;

    /**
     * Matching string for username processing in an AD-forest.
     *
     * The matching string for the match-replace operation on usernames in an
     * ActiveDirectory FOREST (multi-domain setup) for retrieving the email
     * address. See the configuration file "active_directory_config.inc" for a
     * detailed example.
     *
     * @var string
     */
    private $m_UsernameSuffixReplaceMatch;

    /**
     * Replacement string for username processing in an AD-forest.
     *
     * The replacement string for the match-replace operation on usernames in an
     * ActiveDirectory FOREST (multi-domain setup) for retrieving the email
     * address. See the configuration file "active_directory_config.inc" for a
     * detailed example.
     *
     * @var string
     */
    private $m_UsernameSuffixReplaceString;

    /**
     * ActiveDirectoryProxy constructor: instantiates an
     * ActiveDirectoryProxy object with the settings specified in
     * the configuration file.
     *
     * No parameters are passed to the constructor.
     */
    public function __construct() {

        global $ACCOUNT_SUFFIX, $AD_PORT, $BASE_DN, $DOMAIN_CONTROLLERS,
               $AD_USERNAME, $AD_PASSWORD, $REAL_PRIMARY_GROUP, $USE_SSL,
               $USE_TLS, $RECURSIVE_GROUPS, $AUTHORIZED_GROUPS, $VALID_GROUPS,
               $AD_USERNAME_SUFFIX, $AD_USERNAME_SUFFIX_PATTERN,
               $AD_USERNAME_SUFFIX_REPLACE;


        // Include the configuration file
        $conf = dirname(__FILE__) . "/../../../config/active_directory_config.inc";
        if (! is_file($conf)) {
            $msg = "The Active Directory configuration file " .
                "'active_directory_config.inc' is missing!";
            Log::error($msg);
            throw new \Exception($msg);
        }
        /** @noinspection PhpIncludeInspection */
        include($conf);

        // Set up the adLDAP object
        $options = array(
            'account_suffix'      => $ACCOUNT_SUFFIX,
            'ad_port'             => intval($AD_PORT),
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
            Log::warning('VALID_GROUPS not set for Active Directory authentication!');
            $VALID_GROUPS = array();
        }
        if ($AUTHORIZED_GROUPS === null) {
            Log::warning('AUTHORIZED_GROUPS not set for Active Directory authentication!');
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

        // Check if we have conflicting username settings
        if (!empty($ACCOUNT_SUFFIX) && !empty($AD_USERNAME_SUFFIX)) {
            Log::error('$AD_USERNAME_SUFFIX and $ACCOUNT_SUFFIX are both set!');
        }

        try {
            $this->m_AdLDAP = new adLDAP($options);
        } catch (adLDAPException $e) {
            // Make sure to clean stack traces
            $pos = stripos($e, 'AD said:');
            if ($pos !== false) {
                $e = substr($e, 0, $pos);
            }
            echo $e;
            exit();
        }
    }

    /**
     * Destructor. Closes the connection started by the adLDAP object.
     */
    public function __destruct() {
        // We ask the adLDAP object to close the connection. A check whether a
        // connection actually exists will be made by the adLDAP object itself.
        // This is a fallback to make sure to close any open sockets when the
        // object is deleted, since all methods of this class that access the
        // adLDAP object explicitly close the connection when done.
        if ($this->m_AdLDAP !== null) {
            $this->m_AdLDAP->close();
        }
    }

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     * @return string 'active directory'.
     */
    public function friendlyName()
    {
        return 'Active Directory';
    }

    /**
     * Authenticates the User with given username and password against Active
     * Directory.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password) {

        // Make sure the user is active
        if (!$this->isActive($username)) {
            Log::info("User '$username': account is INACTIVE!");
            return false;
        }

        // Authenticate against AD
        $b = $this->m_AdLDAP->user()->authenticate(
            strtolower($username), $password);

        // If authentication failed, we can return here.
        if ($b === false) {
            Log::info("User '$username': authentication FAILED!");
            $this->m_AdLDAP->close();
            return false;
        }

        // If if succeeded, do we need to check for group authorization?
        if (count($this->m_AuthorizedGroups) == 0) {
            // No, we don't.
            return true;
        }

        // We need to retrieve the groups and compare them.

        // Get the user groups from AD
        $userGroups = $this->m_AdLDAP->user()->groups($username);
        $this->m_AdLDAP->close();

        // Test for intersection
        $b = count(array_intersect($userGroups, $this->m_AuthorizedGroups)) > 0;
        if ($b === true) {
            Log::info("User '$username': group authentication succeeded.");
        } else {
            Log::info("User '$username': user rejected by failed group authentication.");
        }
        return $b;
    }

    /**
     * Returns the email address of user with given username.
     * @param string $username Username for which to query the email address.
     * @return string email address or "".
    */
    public function getEmailAddress($username) {

        // If needed, process the user name suffix for subdomains
        $username .= $this->m_UsernameSuffix;
        if ($this->m_UsernameSuffixReplaceMatch != '') {
            $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
            $replace = $this->m_UsernameSuffixReplaceString;
            Log::info("getEmailAddress(): preg_replace($pattern, $replace, $username)");
            $username = preg_replace($pattern, $replace, $username);
            Log::info("Processed AD user name: '$username'");
        }

        // Get the email from AD
        $info = $this->m_AdLDAP->user()->infoCollection($username, array("mail"));

        $this->m_AdLDAP->close();
        if (!$info) {
            Log::warning('No email address found for username "' . $username . '"');
            return "";
        }
        Log::info('Email for username "' . $username . '": ' . $info->mail);
        return $info->mail;
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @return string Group or "" if not found.
    */
    public function getGroup($username) {

        // If needed, process the user name suffix for subdomains
        $username .= $this->m_UsernameSuffix;
        if ($this->m_UsernameSuffixReplaceMatch != '') {
            $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
            $replace = $this->m_UsernameSuffixReplaceString;
            Log::info("getEmailAddress(): preg_replace($pattern, $replace, $username)");
            $username = preg_replace($pattern, $replace, $username);
            Log::info("Processed AD user name: '$username'");
        }

        // Get the user groups from AD
        $userGroups = $this->m_AdLDAP->user()->groups($username);
        $this->m_AdLDAP->close();

        // If no groups found, return ""
        if (!$userGroups) {
            Log::info('No groups found for username "' . $username . '"');
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
            Log::info("Group for username $username not found in the list of valid groups!");
            $group = "";
        } else {
            $group = $userGroups[0];
        }

        Log::info('Group for username "' . $username . '": ' . $group);
        return $group;

    }

};
