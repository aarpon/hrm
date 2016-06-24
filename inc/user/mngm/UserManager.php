<?php
/**
 * AbstractUserManager
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\user\mngm;

use hrm\Log;
use hrm\DatabaseConnection;
use hrm\System;
use hrm\user\UserConstants;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/../../bootstrap.php';


/**
 * Abstract base UserManager class that provides an interface for concrete
 * classes to implement.
 *
 * @package hrm
 */
abstract class UserManager
{

    /**
     * Return true if the UserManager can modify a User's e-mail address
     * in the backing user management system (e.g. Integrated, Active Directory,
     * LDAP, or Auth0).
     *
     * @return bool True if the UserManager can modify tue User's e-mail
     * address in the backing user management system, false otherwise.
     */
    public static function canModifyEmailAddress()
    {
        return false;
    }

    /**
     * Return true if the UserManager can modify a User's group in the
     * backing user management system (e.g. Integrated, Active Directory,
     * LDAP, or Auth0).
     *
     * @return bool True if the UserManager can modify tue User's e-mail
     * address in the backing user management system, false otherwise.
     */
    public static function canModifyUserGroup()
    {
        return false;
    }

    /**
     * Checks if user login is restricted to the administrator for maintenance
     * (e.g. in case the database has to be updated).
     * @return bool True if the user login is restricted to the administrator.
     */
    public static function isLoginRestrictedToAdmin()
    {
        $result = !(System::isDBUpToDate());
        return $result;
    }

    /**
     * Checks whether a seed for a user creation request exists.
     *
     * This function returns false by default and must be reimplemented for
     * those user management implementations that support this.
     * @param string $seed Seed to be compared.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public function existsUserRequestWithSeed($seed)
    {
        $db = new DatabaseConnection();
        return ($db->existsUserRequestWithSeed($seed));
    }

    /**
     * Check whether the user exists in the User table already.
     * @param UserV2 $user User for which to check for existence.
     * @return bool True if the user exists; false otherwise.
     */
    public function existsUser(UserV2 $user)
    {
        $db = new DatabaseConnection();
        return ($db->checkUser($user->name()));
    }

    /**
     * Find and return a User by name.
     * @param string $username Name of the User to be retrieved.
     * @return UserV2/null Requested User or null if not found.
     */
    public function findUserByName($username)
    {
        $db = new DatabaseConnection();
        if ($db->checkUser($username)) {
            $user = new UserV2();
            $user->setName($username);
            $user->load();
            return $user;
        } else {
            return null;
        }
    }

    /**
     * Returns the number of jobs currently in the queue for current User.
     * @param string $username User name to query.
     * @return int Number of jobs in queue.
     */
    public function numberOfJobsInQueue($username)
    {
        $db = new DatabaseConnection();
        return $db->getNumberOfQueuedJobsForUser($username);
    }

    /**
     * Creates a new user.
     * @param string $username User login name.
     * @param string $password User password in plain text.
     * @param string $emailAddress User e-mail address.
     * @param string $group User group.
     * @param string $authentication User authentication mode.
     * @param string $role User role (optional, default is 'user').
     * @param string $status User status (optional, the user is activated by
     * default).
     * @return True if the User could be created, false otherwise.
     */
    public abstract function createUser($username, $password, $emailAddress,
                                        $group, $authentication, $role,
                                        $status);


    /**
     * Update the user.
     *
     * This function does not change the password!
     * @see changeUserPassword
     *
     * @param string $username User name.
     * @param string $emailAddress User e-mail address.
     * @param string $group user group.
     * @return bool True if the user could be updated, false otherwise,
     */
    public abstract function updateUser($username, $emailAddress, $group);

    /**
     * Change the user password.
     *
     * @param string $username User name.
     * @param string $password New password (plain text).
     * @return bool True if the user password could be changed, false otherwise.
     */
    public abstract function changeUserPassword($username, $password);


    /**
     * Deletes a user from the database.
     * @param string $username Name of the user to be deleted.
     * @return bool True if success; false otherwise.
     */
    public function deleteUser($username)
    {

        // Delete the user
        $db = new DatabaseConnection();

        // Start transaction: if error, everything will be rolled back.
        $db->connection()->StartTrans();

        // Delete the user from the username table
        $sql = "DELETE FROM username WHERE name=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM parameter_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM task_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM task_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM analysis_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM analysis_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        // Complete the transaction (or roll back if failed).
        $success = $db->connection()->CompleteTrans();

        if ($success) {

            // Delete the user folders
            $this->deleteUserFolders($username);

            return True;
        }

        return False;
    }

    /**
     * Sets the user role.
     * @param string $username Name of the user to modify.
     * @param string $role Role, one of UserConstants::STATUS_ACTIVE,
     * UserConstants::STATUS_DISABLED, UserConstants::STATUS_OUTDATED.
     * @return bool True if the user role could be changed; false otherwise.
     */
    public function setRole($username, $role)
    {
        if ($role != UserConstants::STATUS_ACTIVE &&
            $role != UserConstants::STATUS_DISABLED &&
            $role != UserConstants::STATUS_OUTDATED
        ) {
            return false;
        }
        $db = new DatabaseConnection();
        $sql = "UPDATE username SET role=? WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($role, $username));
        if ($result === false) {
            return false;
        }
        return true;
    }

    /**
     * Accepts user with given username.
     * @param string $username Name of the user to accept.
     * @return bool True if the user could be accepted; false otherwise.
     */
    public function acceptUser($username)
    {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Enables user with given username.
     * @param string $username Name of the user to enable.
     * @return bool True if the user could be enabled; false otherwise.
     */
    public function enableUser($username)
    {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Enables all users.
     * @return bool True if all users could be enabled, false otherwise.
     */
    public function enableAllUsers()
    {
        $db = new DatabaseConnection();
        return ($db->updateAllUsersStatus(UserConstants::STATUS_ACTIVE));
    }

    /**
     * Disables user with given username.
     * @param string $username Name of the user to disable.
     * @return bool True if the user could be disabled; false otherwise.
     */
    public function disableUser($username)
    {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, UserConstants::STATUS_DISABLED));
    }

    /**
     * Disables all users.
     * @return  bool True if all users could be disabled; false otherwise.
     */
    public function disableAllUsers()
    {
        $db = new DatabaseConnection();
        return ($db->updateAllUsersStatus(UserConstants::STATUS_DISABLED));
    }

    /**
     * Returns all user rows from the database (sorted by user name).
     * @return array Array of user rows sorted by user name.
     */
    public function getAllUserDBRows()
    {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username ORDER BY name");
        return $rows;
    }

    /**
     * Returns all active user rows from the database (sorted by user name).
     * @return array Array of active user rows sorted by user name.
     */
    public function getAllActiveUserDBRows()
    {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE status = '" .
            UserConstants::STATUS_ACTIVE . "' ORDER BY name");
        return $rows;
    }

    /**
     * Returns all user rows from the database for user names starting by a
     * given letter (sorted by user name).
     * @param string $c First letter
     * @return array Array of user rows filtered by first letter and sorted by
     * user name.
     */
    public function getAllUserDBRowsByInitialLetter($c)
    {
        $c = strtolower($c);
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE name LIKE '$c%' ORDER BY name");
        return $rows;
    }

    /**
     * Returns the total number of users independent of their status (and
     * counting the administrator).
     * @return int Number of users.
     */
    public function getTotalNumberOfUsers()
    {
        $db = new DatabaseConnection();
        $count = $db->queryLastValue(
            "SELECT count(*) FROM username WHERE TRUE");
        return $count;
    }

    /**
     * Returns a vector of counts of how many users have names starting with
     * each of the letters of the alphabet.
     * @return array Array of counts.
     */
    public function getNumberCountPerInitialLetter()
    {

        // Open database connection
        $db = new DatabaseConnection();

        // Initialize array of counts
        $counts = array();

        // Query and store the counts
        for ($i = 0; $i < 26; $i++) {

            // Initial letter (filter)
            $c = chr(97 + $i);

            // Get users with name staring by $c
            $query = "SELECT * FROM username WHERE name LIKE '$c%' AND " .
                "name != 'admin' AND (status = '" .
                UserConstants::STATUS_ACTIVE . "' OR status = '" .
                UserConstants::STATUS_DISABLED . "' OR status = '" .
                UserConstants::STATUS_OUTDATED . "')";
            $result = $db->query($query);

            // Store the count
            $counts[$c] = count($result);

        }

        return $counts;
    }

    /**
     * Creates the user data folders.
     * @param string $username
     */
    public function createUserFolders($username)
    {

        // TODO Use the Shell classes!

        Log::info("Creating directories for '" . $username . "'.");
        global $userManagerScript;
        Log::info(shell_exec($userManagerScript . " create " . $username));
    }

    /**
     * Deletes the user data folders.
     * @param string $username User name for which to create the folders.
     */
    public function deleteUserFolders($username)
    {

        // TODO Use the Shell classes!

        Log::info("Removing directories for '" . $username . "'.");
        global $userManagerScript;
        Log::info(shell_exec($userManagerScript . " delete " . $username));
    }

}
