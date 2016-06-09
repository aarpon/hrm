<?php
/**
 * User
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user;

use hrm\DatabaseConnection;
use hrm\user\auth\AuthenticatorFactory;

require_once dirname(__FILE__) . '/../bootstrap.php';


/**
 * Manages a user and its state.
 *
 * @package hrm
 */
class User {

    /**
     * Name of the user.
     * @var string
     */
    protected $name;

    /**
     * E-mail address of the user.
     * @var string $emailAddress
     */
    protected $emailAddress;

    /**
     * Group of the user.
     * @var string
     */
    protected $group;

    /**
     * True if the user is logged in; false otherwise.
     * @var bool
     */
    private $isLoggedIn;

    /**
     * Constructor. Creates a new (unnamed) User.
     */
    function __construct() {

        // Initialize members to empty
        $this->name = "";
        $this->emailAddress = "";
        $this->group = "";
        $this->isLoggedIn = False;
    }

    /**
     * Returns the name of the User.
     * @return string The name of the User.
     */
    public function name() {
        return $this->name;
    }

    /**
     * Sets the name of the User.
     * @param string $name The name of the User.
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * Checks whether the user is logged in.
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLoggedIn() {
        return $this->isLoggedIn;
    }

    /**
     * Logs in the user with given user name and password
     *
     * This function will use different authentication modes depending on the
     * value of the global configuration variable $authenticateAgainst.
     *
     * @param  string $name User name
     * @param  string $password Password (plain)
     * @return bool True if the user could be logged in, false otherwise.
     */
    public function logIn($name, $password) {

        // Set the name
        $this->setName($name);

        // Try authenticating the user against the appropriate mechanism
        $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
        $this->isLoggedIn = $authenticator->authenticate($this->name(), $password);

        return $this->isLoggedIn;
    }

    /**
     * Logs out the user
     */
    function logOut() {
        $this->isLoggedIn = False;
    }

    /**
     * Check whether a new user request has been accepted by the
     * administrator
     *
     * This should only be used if authentication is against the HRM user
     * management.
     *
     * @return bool True if the user has been accepted; false otherwise.
     */
    public function isAccepted() {

        if ($this->isAdmin()) {
            return true;
        }

        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isAccepted($this->name());
    }

    /**
     * Checks whether the user has been suspended by the administrator
     *
     * This should only be used if authentication is against the HRM user
     * management.
     *
     * @return bool True if the user was suspended by the administrator;
     * false otherwise.
     */
    public function isSuspended() {

        if ($this->isAdmin()) {
            return false;
        }

        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isSuspended($this->name());
    }

    /**
     * Returns the User e-mail address.
     * @return string The User e-mail address.
     */
    public function emailAddress() {

        // If the email is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->emailAddress == "") {
            $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
            $this->emailAddress = $authenticator->getEmailAddress($this->name());
        }

        return $this->emailAddress;

    }

    /**
     * Returns the administrator name.
     * @return string The administrator name.
     */
    static public function getAdminName() {
        return 'admin';
    }

    /**
     * Reload info from the source.
     */
    public function reload() {

        // Reload e-mail address
        $this->emailAddress = "";
        $this->emailAddress();

        // Reload group
        $this->group = "";
        $this->userGroup();

    }

    /**
     * Checks whether the user is the administrator.
     * @return bool True if the user is the administrator, false otherwise.
     */
    public function isAdmin() {
        return $this->name() == $this->getAdminName();
    }

    /**
     * Returns the user to which the User belongs.
     * @return string Group name.
     */
    public function userGroup() {

        // If the group is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->group == "") {
            $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
            $this->group = $authenticator->getGroup($this->name());
        }

        return $this->group;

    }

    /**
     * Returns the number of jobs currently in the queue for current User.
     * @return int Number of jobs in queue.
     */
    public function numberOfJobsInQueue() {
        if ($this->name() == "") {
            return 0;
        }
        $db = new DatabaseConnection();
        return $db->getNumberOfQueuedJobsForUser($this->name);
    }

    /**
     * Checks whether a user with a given seed exists in the database.
     *
     * If a user requests an account, his username is added to the database with
     * a random seed as status.
     *
     * @param string $seed Seed to be looked for in the database.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public function existsUserRequestWithSeed($seed) {
        $query = "SELECT status FROM username WHERE status = '$seed';";
        $db = new DatabaseConnection();
        $value = $db->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ( $value == $seed );
        }
    }

}

