<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

namespace hrm\user\mngm;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * Class UserManagerFactory
 *
 * Returns the UserManager object to be used to manage the users based on the
 * value of $authenticateAgainst from the configuration files.
 *
 * @package hrm
 */
class UserManagerFactory {

    /**
     * Returns the correct UserManager object depending on the value of the
     * $authenticateAgainst variable in the configuration files and whether
     * or not the user is the administrator.
     *
     * @param bool $isAdmin (optional, default is False). True if the user is
     * the administrator, False otherwise.
     * @return ExternalReadOnlyUserManager|InternalUserManager
     * @throws \Exception If the value of $authenticateAgainst is invalid.
     */
    public static function getUserManager($isAdmin = false) {

        global $authenticateAgainst;

        // If the user is the Admin, we currently must return
        // an InternalAuthenticator
        if ($isAdmin) {
            require_once(dirname(__FILE__) . "/InternalUserManager.php");
            return new InternalUserManager();
        }

        // Initialize the authenticator
        switch ($authenticateAgainst) {

            case "MYSQL":

                return new InternalUserManager();

            case "LDAP":

                return new ExternalReadOnlyUserManager();

            case "ACTIVE_DIR":

                return new ExternalReadOnlyUserManager();

            default:

                // Unknown authentication method.
                throw new \Exception("Bad value $authenticateAgainst.");
        }

    }
}
