<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include the HRM configuration files.
require_once(dirname(__FILE__) . "/../hrm_config.inc.php");

/*!
\class  AuthenticatorFactory
\brief  Return the Authenticator object to be used to manage the user based
on the value of $authenticateAgainst from the configuration files.
*/
class AuthenticatorFactory {

    /*!
    \brief	Return the correct authenticator object depending on the
            value of the $authenticateAgainst variable in the configuration
            files and whether or not the user is the administrator.
    \param  $isAdmin (optional, default is False). True if the user is the
            administrator, False otherwise.
     */
    public static function getAuthenticator($isAdmin = false) {

        global $authenticateAgainst;

        // If the user is the Admin, we currently must return
        // an InternalAuthenticator
        if ($isAdmin) {
            require_once(dirname(__FILE__) ."/InternalAdminAuthenticator.inc.php");
            return new InternalAdminAuthenticator();
        }

        // Initialize the authenticator
        switch ($authenticateAgainst) {

            case "MYSQL":

                require_once(dirname(__FILE__) ."/InternalAuthenticator.inc.php");
                return new InternalAuthenticator();

            case "LDAP":

                require_once(dirname(__FILE__) ."/LDAPAuthenticator.inc.php");
                return new LDAPAuthenticator();

            case "ACTIVE_DIR":

                // Initialize the ActiveDirectoryAuthenticator object
                require_once(dirname(__FILE__) ."/ActiveDirectoryAuthenticator.inc.php");
                return new ActiveDirectoryAuthenticator();

            default:

                // Unknown authentication method.
                throw new Exception("Bad value $authenticateAgainst.");
        }

    }
}
?>
