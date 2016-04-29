<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

namespace hrm\auth;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * Class InternalAuthenticator
 *
 * Manages authentication against the internal HRM  user database.
 *
 * @package hrm\auth
 */
class InternalAuthenticator extends AbstractAuthenticator {

    const STATUS_ACCEPTED = 'a';
    const STATUS_SUSPENDED = 'd';

    /**
     * Constructor: instantiates an InternalAuthenticator object.
     * No parameters are passed to the constructor.
     */
    public function __construct() {
        // Nothing to do.
    }

    /**
     * Authenticates the User with given username and password against the
     * HRM user database.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password) {

        // Is the user active?
        if (!$this->isActive($username)) {
            return false;
        }

        // Get the encrypted user password
        $dbPassword = $this->password($username);
        if (!$dbPassword) {
            return false;
        }

        // Now compare with the submitted (to be encrypted) password
        return ($dbPassword ==
            ($this->encrypt($password, substr($dbPassword, 0, 2))));
    }

    /**
     * Returns the group or groups the user with given username belongs to.
     * @param string $username Username for which to query the group(s).
     * @return string|null Group or Array of groups or NULL if not found.
    */
    public function getEmailAddress($username) {
        $db = new DatabaseConnection();
        return $db->emailAddress($username);
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @return string Group or "" if not found.
    */
    public function getGroup($username) {
        $db = new DatabaseConnection();
        return $db->getGroup($username);
    }

    /**
     * Updates the User last access in the database.
     * @param string $username Username for which to update the last access time.
     */
    public function updateLastAccessDate($username) {
        $db = new DatabaseConnection();
        $db->updateLastAccessDate($username);
    }


    /**
     * Checks whether a request from the user with given name was accepted by
     * the administrator.
     *
     * Please notice that the base implementation always returns true.
     * Reimplement if needed!
     *
     * @param string $username Username for which to query the accepted status.
     * @return bool True if the user was accepted, false otherwise.
    */
    public function isAccepted($username) {
        $db = new DatabaseConnection();
        return ($db->getUserStatus($username) == self::STATUS_ACCEPTED);
    }

    /**
     * Checks whether the user with given name was suspended by the administrator.
     *
     * Please notice that the base implementation always returns false.
     * Reimplement if needed!
     *
     * @param string $username String Username for which to query the suspended
     * status.
     * @return bool True if the user was suspended, false otherwise.
    */
    public function isSuspended($username) {
        $db = new DatabaseConnection();
        return ($db->getUserStatus($username) == self::STATUS_SUSPENDED);
    }

    /* ========================= PRIVATE FUNCTIONS ========================== */


    /**
     * Encrypts a string either with md5 or DES.
     *
     * The encryption algorithm used is defined by the $useDESEncryption
     * variable in the HRM configuration files.
     *
     * @param string $string The string to be encrypted.
     * @param string $seed The seed (this is used only by the DES algorithm)
     * @return string The encrypted string.
    */
    private function encrypt($string, $seed) {
        global $useDESEncryption;
        if ($useDESEncryption) {
            $result = crypt($string, $seed);
        } else {
            $result = md5($string);
        }
        return $result;
    }

    /**
     * Returns the user (encrypted) password.
     *
     * @param string $name User name.
     * @return string The encrypted password.
    */
    private function password($name) {
        $db = new DatabaseConnection();
        return $db->queryLastValue($db->passwordQueryString($name));
    }

};
