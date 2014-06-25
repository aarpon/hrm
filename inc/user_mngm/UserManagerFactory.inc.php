<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include the HRM configuration files.
require_once(dirname(__FILE__) . "/../hrm_config.inc.php");

/*!
\class  UserManagerFactory
\brief  Return the UserManager object to be used to manage the users based
on the value of $authenticateAgainst from the configuration files.
*/
class UserManagerFactory {

    /*!
    \brief	Return the correct UserManager object depending on the
            value of the $authenticateAgainst variable in the configuration
            files and whether or not the user is the administrator.
    \param  $isAdmin (optional, default is False). True if the user is the
            administrator, False otherwise.
     */
    public static function getUserManager($isAdmin = false) {

        global $authenticateAgainst;

        // If the user is the Admin, we currently must return
        // an InternalAuthenticator
        if ($isAdmin) {
            require_once(dirname(__FILE__) ."/InternalUserManager.inc.php");
            return new InternalUserManager();
        }

        // Initialize the authenticator
        switch ($authenticateAgainst) {

            case "MYSQL":

                require_once(dirname(__FILE__) ."/InternalUserManager.inc.php");
                return new InternalUserManager();

            case "LDAP":

                require_once(dirname(__FILE__) . "/ExternalReadOnlyUserManager.inc.php");
                return new ExternalReadOnlyUserManager();

            case "ACTIVE_DIR":

                // Initialize the ActiveDirectoryAuthenticator object
                require_once(dirname(__FILE__) . "/ExternalReadOnlyUserManager.inc.php");
                return new ExternalReadOnlyUserManager();

            default:

                // Unknown authentication method.
                throw new Exception("Bad value $authenticateAgainst.");
        }

    }
}
?>
