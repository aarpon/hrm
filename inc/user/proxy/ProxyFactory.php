<?php
/**
 * ProxyFactory
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

// Include the HRM configuration files.
use hrm\DatabaseConnection;
use hrm\Log;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Returns the Proxy object to be used to manage the user based on the
 * value of $authenticateAgainst from the configuration files.
 *
 * @package hrm
 */
class ProxyFactory {

    /**
     * Return the default authentication mode for all users.
     * @return string Default authentication mode (one of 'integrated',
     * 'active_dir', 'ldap', auth0).
     */
    public static function getDefaultAuthenticationMode() {

        global $authenticateAgainst;

        // Fall back
        if (!is_array($authenticateAgainst)) {
            $authMode = $authenticateAgainst;
        } else {
            $authMode = $authenticateAgainst[0];
        }

        // Initialize the authenticator
        switch ($authMode) {

            case "integrated":
            case "MYSQL":
                return "integrated";

            case "active_dir":
            case "ACTIVE_DIR":
                return "active_dir";

            case "ldap":
            case "LDAP":
                return "ldap";

            default:
                Log::error("Unrecognized authentication mode! " .
                    "Returning default authentication mode.");
                return "integrated";
        }
    }

    /**
     * Return the authentication mode for the user.
     *
     * The authentication method specified in the database is returned;
     * if none is found, the default one specified in the configurati
     * files. One of 'integrated', 'active_dir', 'ldap'.
     *
     * @param string $username name of the User to query.
     * @return string Authentication mechanism.
     */
    public static function getAuthenticationModeForUser($username) {

        // Retrieve the information from the database
        $db = new DatabaseConnection();

        $sql = "SELECT authentication FROM username WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result === false) {
            return self::getDefaultAuthenticationMode();
        }
        $rows = $result->GetRows();
        $authMode = null;
        if (count($rows) == 0) {
            return self::getDefaultAuthenticationMode();
        } else if (count($rows) == 1) {
            $authMode = $rows[0]['authentication'];
        } else {
            Log::error("Unexpected number of entries retrieved for " .
                "the authentication mode of user $username.");
            return self::getDefaultAuthenticationMode();
        }
        return $authMode;
    }

    /**
     * Get the configured proxy for the requested user name.
     *
     * If the User with given name does not exist, or no authentication
     * mode is specified, return the default authenticator.
     * @param string $username Name of the User to query.
     * @return DatabaseProxy|ActiveDirectoryProxy|LDAPProxy Proxy.
     */
    public static function getProxy($username)
    {
        // Get the authentication mode
        $authMode = self::getAuthenticationModeForUser($username);

        // Return the requested proxy
        switch ($authMode) {

            case 'integrated':
                return new DatabaseProxy();
                break;

            case 'active_dir':
                return new ActiveDirectoryProxy();
                break;

            case 'ldap':
                return new LDAPProxy();
                break;

            default:
                // This should not happen.
                // @see getAuthenticationModeForUser()
                Log::error("Unrecognized authentication mode! " .
                    "Returning default proxy.");
                return new DatabaseProxy();
        }
    }

    /**
     * Get the default proxy from the configuration files.
     * @return AbstractProxy Proxy.
     */
    public static function getDefaultProxy()
    {
        // Get the default authentication mode
        $authMode = self::getDefaultAuthenticationMode();

        // Return the proxy
        switch ($authMode) {

            case "integrated":
                return new DatabaseProxy();

            case "active_dir":
                return new ActiveDirectoryProxy();

            case "ldap":
                return new LDAPProxy();

            default:
                // This shouldn't happen.
                // @see getDefaultAuthenticationMode()
                Log::error("Unrecognized authentication mode! " .
                    "Returning default authenticator.");
                return new DatabaseProxy();
        }
    }

    /**
     * Returns the list of all configured authentication methods.
     *
     * The array keys correspond to the actual authentication mode
     * as encoded in the configuration, where the corresponding values
     * are the human-friendly names (for display purposes).
     *
     * @return array List of configured authentication modes.
     */
    public static function getAllConfiguredAuthenticationModes() {

        global $authenticateAgainst;

        // Do not modify the original variable

        // Fall back
        if (!is_array($authenticateAgainst)) {
            $allAuthModes  = array($authenticateAgainst);
        } else {
            $allAuthModes = array();
            foreach($authenticateAgainst as $mode) {
                $allAuthModes[] = $mode;
            }
        }

        // Now build the final output with the new, official nomenclature
        $authModeMap = array();
        for ($i = 0; $i < count($allAuthModes); $i++) {

            if ($allAuthModes[$i] == "MYSQL" ||
                $allAuthModes[$i] == "integrated") {

                $authModeMap["integrated"] = "Integrated authentication";

            } else if ($allAuthModes[$i] == "ACTIVE_DIR" ||
                $allAuthModes[$i] == "active_dir") {

                $authModeMap["active_dir"] = "Active Directory authentication";

            } else if ($allAuthModes[$i] == "LDAP" ||
                $allAuthModes[$i] == "ldap") {

                $authModeMap["ldap"] = "Generic LDAP authentication";

            } else {
                Log::error("Unknown authentication mode $allAuthModes[$i].");
            }
        }

        return $authModeMap;
    }
};
