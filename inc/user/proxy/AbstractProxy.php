<?php
/**
 * AbstractProxy
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

use hrm\user\UserManager;
use hrm\user\UserConstants;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Abstract base proxy class that provides an interface for concrete
 * classes to establish communication with external user management systems
 * such as Microsoft Active Director, LDAP, Auth0.
 *
 * The User class expects concrete proxy classes to extend this class
 * and implement all of its abstract methods.
 *
 * @package hrm
 */
abstract class AbstractProxy {

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     *
     * Something like 'integrated', 'active directory', 'generic ldap', 'auth0'.
     * @return string Friendly name.
     */
    abstract public function friendlyName();

    /**
     * Return whether the proxy allows changing the e-mail address.
     * @return bool True if the e-mail address can be changed, false otherwise.
     */
    public function canModifyEmailAddress() { return false; }

    /**
     * Return whether the proxy allows changing the group.
     * @return bool True if the group can be changed, false otherwise.
     */
    public function canModifyGroup() { return false; }

    /**
     * Return whether the proxy allows changing the password.
     * @return bool True if the password can be changed, false otherwise.
     */
    public function canModifyPassword() { return false; }

    /**
     * Return whether the User must exist in the database before first
     * authentication is allowed. If false, the User will be created on
     * first successful authentication (to the external backend).
     * @return bool True if the User must exist, false otherwise.
     */
    public function usersMustExistBeforeFirstAuthentication() { return false; }

    /**
     * Authenticates the User with given username and password.
     * @param string $username Username for authentication.
     * @param string $password Password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    abstract public function authenticate($username, $password);

    /**
     * Returns the email address of user with given username.
     * @param string $username Username for which to query the email address.
     * @return string|null Email address or null.
     */
    abstract public function getEmailAddress($username);

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @return string Group or "" if not found.
     */
    abstract public function getGroup($username);

    /**
     * Checks whether a request from the user with given name was accepted by
     * the administrator and his active.
     * @param string $username Username for which to query the active status.
     * @return bool True if the user is active, false otherwise.
     * @override
     */
    public function isActive($username) {
        return (UserManager::getUserStatus($username) == UserConstants::STATUS_ACTIVE ||
            UserManager::getUserStatus($username) == UserConstants::STATUS_OUTDATED);
    }

    /**
     * Checks whether the user with given name was disabled by the administrator.
     *
     * @param string $username String Username for which to query the status.
     * @return bool True if the user was disabled, false otherwise.
     */
    public function isDisabled($username) {
        return (UserManager::getUserStatus($username) == UserConstants::STATUS_DISABLED);
    }

    /**
     * Checks whether the user with given name must be updated before use (e.g.
     * in need of a password rehash).
     *
     * Inheriting classes might need to re-implement this.
     *
     * @param string $username User name.
     * @return bool True if the User must be updated, false otherwise.
     */
    public function isOutdated($username = "ignored") { return false; }


    /**
     * Set the User status to active.
     * @param string $username User name.
     */
    public function setActive($username) {
        UserManager::setUserStatus($username, UserConstants::STATUS_ACTIVE);
    }

    /**
     * Set the User status to disabled.
     * @param string $username User name.
     */
    public function setDisabled($username) {
        UserManager::setUserStatus($username, UserConstants::STATUS_DISABLED);
    }

    /**
     * Set the User status to outdated (e.g. in need of a password update).
     *
     * Inheriting classes might need to reimplement this.
     *
     * @param string $username User name.
     * @return void.
     */
    public function setOutdated($username = "ignored") { }

};
