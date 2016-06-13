<?php
/**
 * AbstractAuthenticator
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\auth;

// Include the HRM configuration files.
require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Abstract base Authenticator class that provides an interface for concrete
 * classes to implement.
 *
 * The User class expects concrete Authenticator classes to extend this class
 * and implement all of its abstract methods.
 *
 * @package hrm
 */
abstract class AbstractAuthenticator {

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
     * the administrator.
     * @param string $username Username for which to query the accepted status.
     * @return bool True if the user was accepted, false otherwise.
     */
    public function isAccepted($username = "ignored") {
        return true;
    }

    /**
     * Checks whether the user with given name was suspended by the administrator.
     *
     * Please notice that the base implementation always returns false.
     * Reimplement if needed!
     *
     * @param string $username Username for which to query the suspended status.
     * @return bool True if the user was suspended, false otherwise.
    */
    public function isSuspended($username = "ignored") {
        return false;
    }

    /**
     * Checks whether the user with given name is active.
     *
     * Please notice that the base implementation always returns true.
     * Reimplement if needed!
     *
     * @param string $username Username for which to query the active status.
     * @return bool True if the user is active, false otherwise.
    */
    public function isActive($username) {
        return $this->isAccepted($username) & !$this->isSuspended($username);
    }
};
