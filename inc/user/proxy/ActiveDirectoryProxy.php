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

use Adldap;
use Exception;
use hrm\Log;

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
class ActiveDirectoryProxy extends AbstractProxy
{
    /**
     * The adLDAP class.
     * @var adLDAP
     */
    private $m_AdLDAP;

    
    /**
     * The adLDAP provider class.
     * @var adLDAP_provider
     */
    private $m_AdLDAP_provider;
    

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
     * Append this to usernames. Similar to $m_UsernameSuffix, if both are present 
     * the m_AccountSuffix has precedence.
     *
     * @var string
    */
    private $m_AccountSuffix;

    
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
     * Tweak to get the real primary group from Active Directory. It works if
     * the user's primary group is domain users.
     * http://support.microsoft.com/?kbid=321360
     *
     * @var bool
     */
    private $m_RecursiveGroups;

    /**
     * When querying group memberships, do it recursively.
     * @var bool
     */
    private $m_RealPrimaryGroup;

    /**
     * ActiveDirectoryProxy constructor: instantiates an
     * ActiveDirectoryProxy object with the settings specified in
     * the configuration file.
     *
     * No parameters are passed to the constructor.
     *
     * @throws Exception if config/active_directory_config.inc file could not be found.
     */
    public function __construct()
    {
        global $ACCOUNT_SUFFIX, $AD_PORT, $BASE_DN, $DOMAIN_CONTROLLERS,
               $AD_USERNAME, $AD_PASSWORD, $REAL_PRIMARY_GROUP, $USE_SSL,
               $USE_TLS, $AUTHORIZED_GROUPS, $VALID_GROUPS, $RECURSIVE_GROUPS,
               $AD_USERNAME_SUFFIX, $AD_USERNAME_SUFFIX_PATTERN,
               $AD_USERNAME_SUFFIX_REPLACE;

        // Include the configuration file
        $conf = dirname(__FILE__) . "/../../../config/active_directory_config.inc";
        if (! is_file($conf)) {
            $msg = "The Active Directory configuration file " .
                "'active_directory_config.inc' is missing!";
            Log::error($msg);
            throw new Exception($msg);
        }
        /** @noinspection PhpIncludeInspection */
        include($conf);

        // Make sure that $AD_USERNAME contains the suffix
        if (! str_contains($AD_USERNAME, $ACCOUNT_SUFFIX)) {
            $username = $AD_USERNAME . $ACCOUNT_SUFFIX;
        } else {
            $username = $AD_USERNAME;
        }

        // Set up the adLDAP options.
        $options = array(
            'account_suffix'      => $ACCOUNT_SUFFIX,
            'port'                => intval($AD_PORT),
            'base_dn'             => $BASE_DN,
            'hosts'               => $DOMAIN_CONTROLLERS,
            'username'            => $username,
            'password'            => $AD_PASSWORD,
            'use_ssl'             => $USE_SSL,
            'use_tls'             => $USE_TLS);

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

        $this->m_AccountSuffix = $ACCOUNT_SUFFIX;
        
        $this->m_UsernameSuffix = $AD_USERNAME_SUFFIX;
        $this->m_UsernameSuffixReplaceMatch = $AD_USERNAME_SUFFIX_PATTERN;
        $this->m_UsernameSuffixReplaceString = $AD_USERNAME_SUFFIX_REPLACE;

        $this->m_RecursiveGroups = $RECURSIVE_GROUPS;
        $this->m_RealPrimaryGroup = $REAL_PRIMARY_GROUP;

        // Check if we have conflicting username settings.
        if (!empty($ACCOUNT_SUFFIX) && !empty($AD_USERNAME_SUFFIX)) {
            Log::error('$AD_USERNAME_SUFFIX and $ACCOUNT_SUFFIX are both set!');
        }

        try {
            // Start AdLDAP class instance.
            $this->m_AdLDAP = new Adldap\Adldap();
        
            // Add a provider.
            $this->m_AdLDAP->addProvider($options);
            
            // Connect to the provider.
            $this->m_AdLDAP_provider = $this->m_AdLDAP->connect();
            
        } catch (Adldap\Auth\BindException $e) {
            echo $e;
            Log::Error("Failed starting active_dir authentication, got: " . $e);
            exit();
        }
    }

    
    /**
     * Destructor.
     */
    public function __destruct()
    {
        // The adldap2 package used (v10.5.0 or higher) should never have
        // persistant connections. 
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
     * Fetches and returns a user from the server based on a username.
     * @param string $username Username to fetch.
     * @return the user model instance or false if unsuccessful.
    */
    function getUser($username)
    {
        // Try retrieving the user in with the SAM Account Name
        $user = $this->m_AdLDAP_provider->search()->whereEquals("samaccountname", $username)->get()->first();
        if ($user != null) {
            return $user;
        }

        // Since it failed, try with the User Principal Name
        if (!empty($this->m_AccountSuffix)) {

            // Append the account suffix
            $username .= $this->m_AccountSuffix;

            if(!empty($this->m_UsernameSuffix)) {
                Log::error('$AD_USERNAME_SUFFIX and $ACCOUNT_SUFFIX are both set!');
            }

        } elseif (!empty($this->m_UsernameSuffix)) {

            // Append the username suffix
            $username .= $this->m_UsernameSuffix;

            if ($this->m_UsernameSuffixReplaceMatch != '') {
                $pattern = "/$this->m_UsernameSuffixReplaceMatch/";
                $replace = $this->m_UsernameSuffixReplaceString;
                Log::info("getUser(): preg_replace($pattern, $replace, $username)");
                $username = preg_replace($pattern, $replace, $username);
                Log::info("Processed AD user name: '$username'");
            }
        }

        $user = $this->m_AdLDAP_provider->search()->whereEquals("userprincipalname", $username)->get()->first();
        if ($user == null) {
            Log::error("Failed to get user '$username' in server.");
            return false;
        }
        return $user;
    }

    /**
     * Authenticates the User with given username and password against Active
     * Directory.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password)
    {
        // Make sure the user is active
        if (!$this->isActive($username)) {
            Log::info("User '$username': account is INACTIVE!");
            return false;
        }

        // Authenticate against AD.
        try {
            $b = $this->m_AdLDAP_provider->auth()->attempt(strtolower($username), $password);
        } catch (Adldap\Auth\UsernameRequiredException $e) {
            Log::Error("No username supplied, got: " . $e);
            return false;
        } catch (Adldap\Auth\PasswordRequiredException $e) {
            Log::Error("No password supplied, got: " . $e);
            return false;
        }
        
        
        // If authentication failed, we can return here.
        if ($b === false) {
            Log::info("User '$username': authentication FAILED!");
            return false;
        }
        Log::info("User '$username': authentication SUCCESS!");

        // If if succeeded, do we need to check for group authorization?
        if (count($this->m_AuthorizedGroups) == 0) {
            // No, we don't.
            return true;
        }

        // We need to retrieve the groups and compare them.
        $group = $this->getGroup($username, false);
        if ($group != "") {
            Log::info("User '$username': group authentication succeeded.");
            return true;
        }
        
        Log::info("User '$username': user rejected by failed group authentication.");
        return false;
    }

    /**
     * Returns the email address of user with given username.
     * @param string $username Username for which to query the email address.
     * @return string email address or "".
    */
    public function getEmailAddress($username)
    {
        // Attempt to get the user data with the username.
        $user = $this->getUser($username);
        if (!$user) {
            return false;
        }

        // Attempt to get the mail data for the user.
        $mail = $user->getEmail();
        if (!$mail) {
            Log::warning('No email address found for username "' . $username . '"');
            return "";
        }
        Log::info('Email for username "' . $username . '": ' . $mail);
        return $mail;
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @param bool $verbose Set the verbosity of this function (to reproduce old log behavior).
     * @return string Group or "" if not found.
    */
    public function getGroup($username, $verbose = true)
    {
        // Attempt to get the user data with the username.
        $user = $this->getUser($username);
        if (!$user) {
            return "";
        }
        
        if ($this->m_RealPrimaryGroup) {
            $group = $user->getPrimaryGroup()->getName();
            if (in_array($group, $this->m_AuthorizedGroups)) {
                if ($verbose) {
                    Log::info('Group for username "' . $username . '": ' . $group);
                }
                return $group;
            }
        } else {
            foreach ($this->m_AuthorizedGroups as $group) {
                if ($user->inGroup($group, $recursive=$this->m_RecursiveGroups)) {
                    if ($verbose) {
                        Log::info('Group for username "' . $username . '": ' . $group);
                    }
                    return $group;
                }
            }
        }
        if ($verbose) {
            Log::info("Group for username $username not found in the list of valid groups!");
        }
        return "";
    }
}
