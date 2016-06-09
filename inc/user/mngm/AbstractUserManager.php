<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

namespace hrm\user\mngm;;

use hrm\user\auth\AuthenticatorFactory;
use hrm\DatabaseConnection;
use hrm\System;
use hrm\user\User;

require_once dirname(__FILE__) . '/../../bootstrap.php';


/**
 * Class AbstractUserManager
 *
 * Abstract base UserManager class that provides an interface for concrete
 * classes to implement.
 *
 * @package hrm
 */
abstract class AbstractUserManager {

    /**
     * Return true if the UserManager can create users in the backing user
     * management system (e.g. Active Directory or LDAP).
     *
     * If false, users will exist in the HRM as soon they are authenticated the
     * first time (e.g. by Active Directory). If true, they will be considered
     * existing users only if they are stored in the HRM database.
     * @return bool True if the UserManager can create and delete users, false
     * otherwise.
     */
    public static function canCreateUsers() { return false; }

    /**
     * Return true if the UserManager can delete users.
     * @return bool True if the UserManager can delete users, false otherwise.
     */
    public static function canModifyUsers()  { return false; }

    /**
     * Store or update the User in the database.
     * @param User $user User to be stored (updated) in the database.
     * @return bool True if storing the User was successful; false otherwise.
     */
    abstract public function storeUser(User $user);

    /**
     * Checks if user login is restricted to the administrator for maintenance
     * (e.g. in case the database has to be updated).
     * @return bool True if the user login is restricted to the administrator.
     */
    public function isLoginRestrictedToAdmin() {
        $result = !(System::isDBUpToDate());
        return $result;
    }

    /**
     * Checks whether the user has been suspended by the administrator.
     * @param User $user User to be checked.
     * @return bool True if the user was suspended by the administrator;
     * false otherwise.
     * @throws \Exception
     */
    public function isSuspended(User $user)  {

        // The administrator is never suspended
        if ($user->isAdmin()) {
            return false;
        }

        // Get the authenticator and check the state
        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isSuspended($user->name());
    }

    /**
     * Checks whether the user has been accepted by the administrator.
     * @param User $user User to be checked.
     * @return bool True if the user was suspended by the administrator;
     * false otherwise.
     * @throws \Exception
     */
    public function isAccepted(User $user)  {

        // The administrator is always accepted
        if ($user->isAdmin()) {
            return true;
        }

        // Get the authenticator and check the state
        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isAccepted($user->name());
    }

    /**
     * Checks whether a seed for a user creation request exists.
     *
     * This function returns false by default and must be reimplemented for
     * those user management implementations that support this.
     * @param string $seed Seed to be compared.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public function existsUserRequestWithSeed($seed = "ignored") {
        return false;
    }

    /**
     * Check whether the user exists in HRM.
     * @param User $user User for which to check for existence.
     * @return bool True if the user exists; false otherwise.
     */
    public function existsInHRM(User $user) {
        $db = new DatabaseConnection();
        return ($db->checkUser($user->name()));
    }

    /**
     * Creates a new user.
     * @param string $username User login name.
     * @param string $password User password.
     * @param string $email User e-mail address.
     * @param string $group User group.
     * @param string $status User group (currently ignored: $status is always 'a')
     * @todo Check why the status is ignored.
     */
    public function createUser($username, $password, $email, $group, $status) {
        $db = new DatabaseConnection();
        $db->addNewUser($username, $password, $email, $group, 'a');
    }

    /**
     * Creates the user data folders.
     * @param string $username
     */
    public function createUserFolders($username) {

        // TODO Use the Shell classes!

        report("Creating directories for '" . $username . "'.", 1);
        global $userManagerScript;
        report(shell_exec($userManagerScript . " create " . $username), 1);
    }

    /**
     * Deletes the user data folders.
     * @param string $username User name for which to create the folders.
     */
    public function deleteUserFolders($username) {

        // TODO Use the Shell classes!

        report("Removing directories for '" . $username . "'.", 1);
        global $userManagerScript;
        report(shell_exec($userManagerScript . " delete " . $username), 1);
    }
};
