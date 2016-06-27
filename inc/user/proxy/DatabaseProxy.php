<?php
/**
 * DatabaseProxy
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

use hrm\DatabaseConnection;
use hrm\Log;
use hrm\user\UserConstants;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Manages authentication against the internal HRM  user database.
 *
 * @package hrm
 */
class DatabaseProxy extends AbstractProxy {

    /**
     * Constructor: instantiates an DatabaseProxy object.
     * No parameters are passed to the constructor.
     */
    public function __construct() { }

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     * @return string 'integrated'.
     */
    public function friendlyName()
    {
        return 'Integrated';
    }

    /**
     * Authenticates the User with given username and password against the
     * HRM user database.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password) {

        // Get the User password hash
        $dbPassword = $this->password($username);
        if (!$dbPassword) {
            return false;
        }

        // If the User's status is 'outdated', we need to upgrade its password.
        if ($this->isOutdated($username)) {

            // First try authenticating against the old password
            $success = ($dbPassword ==
                ($this->encrypt($password, substr($dbPassword, 0, 2))));

            if (!$success) {
                return false;
            }

            // Authentication worked. So now we upgrade the password.
            $newHashedPassword = password_hash($password,
                UserConstants::HASH_ALGORITHM,
                array('cost' => UserConstants::HASH_ALGORITHM_COST));
            $this->setPassword($username, $newHashedPassword);

            // Change the status to active
            $this->setActive($username);

            // Now we can return success
            return true;
        }

        // Is the user active?
        if (!$this->isActive($username)) {
            return false;
        }

        // Check the password
        if (password_verify($password, $dbPassword) === false) {
            return false;
        }

        // Re-hash password if necessary
        if (password_needs_rehash($dbPassword,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST)) === true) {

            // Update the password hash
            $newHashedPassword = $this->hashPassword($password);
            $this->setPassword($username, $newHashedPassword);

        }

        // Return successful login
        return true;
    }

    /**
     * Returns the group or groups the user with given username belongs to.
     * @param string $username Username for which to query the group(s).
     * @return string|null Group or Array of groups or NULL if not found.
    */
    public function getEmailAddress($username) {
        $db = new DatabaseConnection();
        $sql = "SELECT email FROM username WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result === false ) {
            Log::error("Could not retrieve e-mail address for user $username.");
            return null;
        }
        $rows = $result->GetRows();
        if (count($rows) != 1) {
            Log::error("Could not retrieve e-mail address for user $username.");
            return null;
        }
        return $rows[0]['email'];
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @return string Group or "" if not found.
    */
    public function getGroup($username) {
        $db = new DatabaseConnection();
        $sql = "SELECT research_group FROM username WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result === false ) {
            Log::error("Could not retrieve research group for user $username.");
            return null;
        }
        $rows = $result->GetRows();
        if (count($rows) != 1) {
            Log::error("Could not retrieve research group for user $username.");
            return null;
        }
        return $rows[0]['research_group'];
    }

    /**
     * Checks whether the user password is outdated.
     *
     * Newer version of HRM rely on the password hashing functionality
     * of PHP >=5.5. If a User's password has not been updated yet, the
     * User's status will be outdated to indicate that the password
     * must be updated (automatically) at the next successful login.
     *
     * @param string $username String Username for which to query the status.
     * @return bool True if the user is outdated, false otherwise.
     */
    public function isOutdated($username) {
        $db = new DatabaseConnection();
        return ($db->getUserStatus($username) == UserConstants::STATUS_OUTDATED);
    }

    /**
     * Set the User status to outdated (in need of a password update).
     * @param string $username User name.
     * @return bool|void True if the status could be updated, false otherwise.
     */
    public function setOutdated($username) {
        $db = new DatabaseConnection();
        return ($db->setUserStatus($username, UserConstants::STATUS_OUTDATED));
    }

    /* ========================= PRIVATE FUNCTIONS ========================== */

    /**
     * Hashes the password using the PHP >= 5.5 hashing mechanisms.
     * @param string $password Plain-text password.
     * @return string Hashed password.
     */
    public function hashPassword($password) {

        // Hash he password
        $hashedPassword = password_hash($password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST));

        // Return the hashed password
        return $hashedPassword;

    }

    /**
     * Sets the hashed password for the user.
     * @param string $username Name of the user.
     * @param string $hashedPassword Hashed password.
     * @return bool True if the new password could be stored, false otherwise.
     */
    private function setPassword($username, $hashedPassword) {
        $db = new DatabaseConnection();
        $sql = "update username set password=? where name=?;";
        $result = $db->connection()->Execute(
            $sql, array($hashedPassword, $username)
        );

        if ($result === false) {
            Log::error("Could not update user $username in the database!");
            return false;
        }

        return true;
    }

    /* ----------------------- DEPRECATED FUNCTIONS ------------------------- */

    /**
     * Returns the user (encrypted) password (deprecated).
     *
     * @param string $name User name.
     * @return string The encrypted password.
     * @deprecated
     */
    private function password($name) {
        $db = new DatabaseConnection();
        return $db->queryLastValue($db->passwordQueryString($name));
    }

    /**
     * Encrypts a string either with md5 or DES (deprecated).
     *
     * The encryption algorithm used is defined by the $useDESEncryption
     * variable in the HRM configuration files.
     *
     * @param string $string The string to be encrypted.
     * @param string $seed The seed (this is used only by the DES algorithm)
     * @return string The encrypted string.
     * @deprecated
     */

    /**
     * Encrypt the password.
     *
     * This uses the encryption algorithm defined in the configuration files.
     *
     * @param string $string Plain-text password to be encrypted.
     * @param string $seed Seed to be used for the crypt() funtion (ignored
     * for md5()).
     * @return string Encrypted password.
     * @deprecated
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

};
