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
use hrm\System;
use hrm\user\UserManager;
use hrm\user\UserConstants;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Manages authentication against the internal HRM  user database.
 *
 * @package hrm
 */
class DatabaseProxy extends AbstractProxy
{
    /**
     * Constructor: instantiates an DatabaseProxy object.
     * No parameters are passed to the constructor.
     */
    public function __construct()
    {
    }

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     * @return string 'integrated'.
     */
    public function friendlyName()
    {
        return 'Integrated';
    }

    /**
     * Return whether the proxy allows changing the e-mail address.
     * @return bool True if the e-mail address can be changed, false otherwise.
     */
    public function canModifyEmailAddress()
    {
        return true;
    }

    /**
     * Return whether the proxy allows changing the group.
     * @return bool True if the group can be changed, false otherwise.
     */
    public function canModifyGroup()
    {
        return true;
    }

    /**
     * Return whether the proxy allows changing the password.
     * @return bool True if the password can be changed, false otherwise.
     */
    public function canModifyPassword()
    {
        return true;
    }

    /**
     * Authenticates the User with given username and password against the
     * HRM user database.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
    */
    public function authenticate($username, $password)
    {
        // Get the User password hash
        $dbPassword = $this->retrievePassword($username);
        if (!$dbPassword) {
            return false;
        }

        // If the User's status is 'outdated', we need to upgrade its password.
        if ($this->isOutdated($username)) {
            // First try authenticating against the old md5-encrypted password
            $success = ($dbPassword == md5($password));

            if (!$success) {
                return false;
            }

            // Authentication worked. So now we upgrade the password.
            // The database check is for the corner case where the admin
            // logs in to upgrade the database from revision 14 to 15!
            // @TODO Remove this at next database revision 16.
            if (System::getDBCurrentRevision() >= 15) {
                $newHashedPassword = password_hash(
                    $password,
                    UserConstants::HASH_ALGORITHM,
                    array('cost' => UserConstants::HASH_ALGORITHM_COST)
                );
                $this->setPassword($username, $newHashedPassword);
            }

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
        if (
            password_needs_rehash(
                $dbPassword,
                UserConstants::HASH_ALGORITHM,
                array('cost' => UserConstants::HASH_ALGORITHM_COST)
            ) === true
        ) {
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
    public function getEmailAddress($username)
    {
        $db = DatabaseConnection::get();
        $sql = "SELECT email FROM username WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result === false) {
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
    public function getGroup($username)
    {
        $db = DatabaseConnection::get();
        $sql = "SELECT research_group FROM username WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result === false) {
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
    public function isOutdated($username)
    {
        // Workaround for a corner case: when the admin tries to
        // log in to upgrade the database between revision 14
        // and 15, his information is OUTDATED, but this cannot
        // yet be obtained from UserManager;;getUserStatus method!
        // @TODO Remove this in the future.
        if (System::getDBCurrentRevision() < 15) {
            return true;
        }
        return (UserManager::getUserStatus($username) == UserConstants::STATUS_OUTDATED);
    }

    /**
     * Set the User status to outdated (in need of a password update).
     * @param string $username User name.
     * @return void.
     */
    public function setOutdated($username)
    {
        UserManager::setUserStatus($username, UserConstants::STATUS_OUTDATED);
    }

    /* ========================= PRIVATE FUNCTIONS ========================== */

    /**
     * Hashes the password using the PHP >= 5.5 hashing mechanisms.
     * @param string $password Plain-text password.
     * @return string Hashed password.
     */
    public function hashPassword($password)
    {
        // Hash he password
        $hashedPassword = password_hash(
            $password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST)
        );

        // Return the hashed password
        return $hashedPassword;
    }

    /**
     * Sets the hashed password for the user.
     * @param string $username Name of the user.
     * @param string $hashedPassword Hashed password.
     * @return bool True if the new password could be stored, false otherwise.
     */
    private function setPassword($username, $hashedPassword)
    {
        $db = DatabaseConnection::get();
        $sql = "update username set password=? where name=?;";
        $result = $db->connection()->Execute($sql, array($hashedPassword, $username));

        if ($result === false) {
            Log::error("Could not update user $username in the database!");
            return false;
        }

        return true;
    }

    /**
     * Returns the user (encrypted) password (deprecated).
     *
     * @param string $name User name.
     * @return string The encrypted password.
     */
    private function retrievePassword($name)
    {
        $db = DatabaseConnection::get();
        return $db->queryLastValue("select password from username where name='$name'");
    }

    /**
     * Mark password reset.
     * @param string $username Name of the user to mark.
     * @return string|void Return seed.
     * @throws \Exception if the method is not reimplemented.
     */
    public function markPasswordReset($username)
    {
        throw new \Exception("Re-implement this method!");
    }
}
