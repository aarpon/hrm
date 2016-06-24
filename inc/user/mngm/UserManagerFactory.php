<?php
/**
 * UserManagerFactory
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\user\mngm;

use hrm\user\proxy\ProxyFactory;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Returns the UserManager object to be used to manage the users based on the
 * authentication backed of a given user.
 *
 * @package hrm
 */
class UserManagerFactory
{

    /**
     * Returns the correct UserManager object depending on the value of the
     * $authenticateAgainst variable in the configuration files and whether
     * or not the user is the administrator.
     *
     * @param string $username Name of the User. Pass "" to retrieve the
     * UserManager for the default authentication mode.
     * @return ExternalReadOnlyUserManager|IntegratedUserManager
     * @throws \Exception If the authentication mode for the User cannot be
     * obtained.
     */
    public static function getUserManager($username)
    {

        if ($username == "") {
            $authMode = ProxyFactory::getDefaultAuthenticationMode();
            return $authMode;
        }

        // Get the UserManager for the required user name
        $authMode = ProxyFactory::getAuthenticationModeForUser($username);

        // Initialize the authenticator
        switch ($authMode) {

            case "integrated":

                return new IntegratedUserManager();

            case "ldap":

                return new ExternalReadOnlyUserManager();

            case "active_dir":

                return new ExternalReadOnlyUserManager();

            case 'auth0':

                return new ExternalReadOnlyUserManager();

            default:

                // This should not happen
                throw new \Exception("Authentication mode not recognized.");
        }

    }

}
