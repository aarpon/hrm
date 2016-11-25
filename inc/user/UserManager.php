<?php
/**
 * UserManager
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\user;

use hrm\Log;
use hrm\DatabaseConnection;
use hrm\System;
use hrm\user\proxy\ProxyFactory;

require_once dirname(__FILE__) . '/../bootstrap.php';


/**
 * Manages Users.
 *
 * @package hrm
 */
class UserManager
{
    /**
     * Return true if the UserManager can modify a User's e-mail address
     * in the backing user management system (e.g. Integrated, Active Directory,
     * LDAP, or Auth0).
     *
     * @param UserV2 $user User to be queried.
     * @return bool True if the UserManager can modify tue User's e-mail
     * address in the backing user management system, false otherwise.
     */
    public static function canModifyEmailAddress(UserV2 $user) {
        return $user->proxy()->canModifyEmailAddress();
    }

    /**
     * Return true if the UserManager can modify a User's group in the
     * backing user management system (e.g. Integrated, Active Directory,
     * LDAP, or Auth0).
     *
     * @param UserV2 $user User to be queried.
     * @return bool True if the UserManager can modify tue User's group
     * address in the backing user management system, false otherwise.
     */
    public static function canModifyGroup(UserV2 $user) {
        return $user->proxy()->canModifyGroup();
    }

    /**
     * Return true if the UserManager must add new Users to the database
     * before the first authentication is possible.
     *
     * If this method returns false, new Users will be automatically added to
     * the database whenever the (external) authentication system the
     * successfully accepts their login credentials. If it returns true, the
     * Users must exist in the database before they may attempt a login.
     *
     * Since different Users might be configured to authenticate against
     * different authentication backends, the User of interest must be passed
     * as argument.
     *
     * @param UserV2 $user User to be queried.
     * @return bool True if the UserManager can add new Users to the database,
     * false otherwise.
     */
    public static function userMustExistBeforeFirstAuthentication(UserV2 $user) {
        return $user->proxy()->usersMustExistBeforeFirstAuthentication();
    }

    /**
     * Check whether the user exists in the User table already.
     * @param UserV2 $user User for which to check for existence.
     * @return bool True if the user exists; false otherwise.
     */
    public static function existsUser(UserV2 $user)
    {
        return self::existsUserWithName($user->name());
    }

    /**
     * Check whether the user with given name exists in the User table already.
     * @param string $username Name of the User.
     * @return bool True if the user exists; false otherwise.
     */
    public static function existsUserWithName($username)
    {
        $db = new DatabaseConnection();
        $query = "select status from username where name = '$username'";
        $result = $db->queryLastValue($query);
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * Find and return a User by name.
     * @param string $username Name of the User to be retrieved.
     * @return UserV2/null Requested User or null if not found.
     */
    public static function findUserByName($username)
    {
        if (self::existsUserWithName($username)) {
            $user = new UserV2();
            $user->setName($username);
            return $user;
        } else {
            return null;
        }
    }

    /**
     * Generates a random (plain) password of given length.
     *
     * The password returned should still be encrypted (by whatever means)
     * before it is stored into a database.
     *
     * @return string Plain text password.
     */
    public static function generateRandomPlainPassword() {

        return (md5(uniqid()));
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
    public static function existsUserRequestWithSeed($seed)
    {
        $db = new DatabaseConnection();
        $query = "SELECT status FROM username WHERE status = '$seed';";
        $value = $db->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ($value == $seed);
        }
    }

    /**
     * Returns the number of jobs currently in the queue for current User.
     * @param string $username User name to query.
     * @return int Number of jobs in queue.
     */
    public static function numberOfJobsInQueue($username)
    {
        $db = new DatabaseConnection();
        $query = "SELECT COUNT(id) FROM job_queue WHERE username = '" . $username . "';";
        $row = $db->execute($query)->FetchRow();
        return $row[0];
    }

    /**
     * Returns the total number of jobs currently in the queue.
     * @return int Total number of jobs in queue.
     */
    public static function getTotalNumberOfQueuedJobs()
    {
        $db = new DatabaseConnection();
        $query = "SELECT COUNT(id) FROM job_queue;";
        $row = $db->execute($query)->FetchRow();
        return $row[0];
    }

    /**
     * Creates a new user.
     * @param string $username User login name.
     * @param string $password User password in plain text.
     * @param string $emailAddress User e-mail address.
     * @param string $group User group.
     * @param string $institution User institution.
     * @param string $authentication User authentication mode (ignored, since
     * it is always integrated).
     * @param int $role User role (optional, default is UserConstants::ROLE_USER).
     * @param string $status User status (optional, the user is activated by
     * default).
     * @return True if the User could be created, false otherwise.
     * @throws \Exception If an empty password is passed.
     */
    public static function createUser($username,
                               $password,
                               $emailAddress,
                               $group,
                               $institution = '',
                               $authentication = "integrated",
                               $role = UserConstants::ROLE_USER,
                               $status = UserConstants::STATUS_ACTIVE) {

        // The password MUST exist, even if a different authentication
        // system than the integrated one will be used.
        if ($password == "") {
            throw new \Exception("The password must not be empty!");
        }

        // If the User already exists, return false
        $db = new DatabaseConnection();
        if ($db->query("select name from username where name='$username'")) {
            return false;
        }

        // Hash the password
        $password = password_hash($password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST));

        // Add the User
        $record["name"] = $username;
        $record["password"] = $password;
        $record["email"] = $emailAddress;
        $record["research_group"] = $group;
        $record["institution"] = $institution;
        $record["role"] = $role;
        $record["authentication"] = $authentication;
        $record["creation_date"] = date("Y-m-d H:i:s");
        $record["last_access_date"] = null;
        $record["status"] = $status;
        $table = "username";
        $insertSQL = $db->connection()->GetInsertSQL($table, $record);
        if(!$db->execute($insertSQL)) {
            Log::error("Could not create new user '$username'!");
            return False;
        }

        // Return success
        return true;
    }

    /**
     * Update or create the User after a successful login.
     *
     * If the User is not logged in, the method returns false immediately
     * unless the argument $force is set to True (default is False).
     *
     * Otherwise, if the User exists, it is updated. If it does not exist,
     * it is created with a random password.
     *
     * @param UserV2 $user User to be updated in the database!
     * @param bool $force User to be updated in the database!
     * @return bool True if the User could be updated or creted successfully,
     * false otherwise.
     */
    public static function storeUser(UserV2 $user, $force=false)
    {
        if (!($user->isLoggedIn()) && !($force)) {
            return false;
        }

        $db = new DatabaseConnection();

        if (self::findUserByName($user->name()) == null) {

            // Create the User with a random password
            return self::createUser(
                $user->name(),
                self::generateRandomPlainPassword(),
                $user->emailAddress(),
                $user->group(),
                $user->institution(),
                $user->authenticationMode(),
                $user->role(),
                $user->status()
            );

        } else {

            // Update the User
            $sql = "UPDATE username SET name=?, email=?, research_group=?, " .
                "institution=?, role=?, status=? WHERE id=?;";
            $result = $db->connection()->Execute($sql,
                array(
                    $user->name(),
                    $user->emailAddress(),
                    $user->group(),
                    $user->institution(),
                    $user->role(),
                    $user->status(),
                    $user->id()));
            if ($result === false) {
                return false;
            }
            return true;
        }
    }

    /**
     * Change the user password.
     *
     * @param string $username User name.
     * @param string $password New password (plain text).
     * @return bool True if the user password could be changed, false otherwise.
     */
    public static function changeUserPassword($username, $password)
    {
        // Hash the password
        $hashPassword = password_hash($password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST));

        // Store it in the database
        $db = new DatabaseConnection();
        $query = "UPDATE username SET password=? WHERE name=?;";
        $result = $db->connection()->Execute($query,
            array($hashPassword, $username));
        if ($result === false) {
            return false;
        }
        return true;
    }

    /**
     * Deletes a user from the database.
     * @param string $username Name of the user to be deleted.
     * @return bool True if success; false otherwise.
     */
    public static function deleteUser($username)
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
            UserManager::deleteUserFolders($username);

            return True;
        }

        return False;
    }

    /**
     * Sets the user role in the database.
     *
     * Notice that the User itself is not changed, to update the User
     * after a database change, use:
     *
     *     $user->load();
     *
     * @param string $username Name of the user to modify.
     * @param int $role Role, one of UserConstants::ROLE_* (default
     * UserConstants::ROLE_USER).
     * @return bool True if the user role could be changed; false otherwise.
     */
    public static function setRole($username, $role=UserConstants::ROLE_USER)
    {
        $db = new DatabaseConnection();
        $sql = "UPDATE username SET role=? WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($role, $username));
        if ($result === false) {
            return false;
        }
        return true;
    }

    /**
     * Sets the authentication mode for the user with given name in the database.
     *
     * Notice that the User itself is not changed, to update the User
     * after a database change, use:
     *
     *     $user->load();
     *
     * @param string $username Name of the user.
     * @param string $mode One of the enabled authentication modes. Subset of
     * {'integrated', 'active_dir', 'ldap', 'auth0'}, depending on the
     * configuration.
     * @return bool True if the authentication mode could be set successfully,
     * false otherwise.
     */
    public static function setAuthenticationMode($username, $mode) {

        // Get all configured authentication modes
        $allAuthModes = ProxyFactory::getAllConfiguredAuthenticationModes();

        // Check that the requested mode is one of the configured ones
        $keys = array_keys($allAuthModes);
        if (! in_array($mode, $keys)) {
            Log::error("The authentication mode $mode is not supported in " .
                "this configuration!");
            return false;
        }

        // Try updating the user
        $db = new DatabaseConnection();
        $sql = "UPDATE username SET authentication=? WHERE name=?;";
        $result = $db->connection()->Execute($sql, array($mode, $username));
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
    public static function acceptUser($username)
    {
        return (self::updateUserStatus($username, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Enables user with given username.
     * @param string $username Name of the user to enable.
     * @return bool True if the user could be enabled; false otherwise.
     */
    public static function enableUser($username)
    {
        return (self::updateUserStatus($username, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Enables all users.
     * @return bool True if all users could be enabled, false otherwise.
     */
    public static function enableAllUsers()
    {
        return (self::updateAllUsersStatus(UserConstants::STATUS_ACTIVE));
    }

    /**
     * Disables user with given username.
     * @param string $username Name of the user to disable.
     * @return bool True if the user could be disabled; false otherwise.
     */
    public static function disableUser($username)
    {
        return (self::updateUserStatus($username, UserConstants::STATUS_DISABLED));
    }

    /**
     * Disables all users.
     * @return  bool True if all users could be disabled; false otherwise.
     */
    public static function disableAllUsers()
    {
        return (self::updateAllUsersStatus(UserConstants::STATUS_DISABLED));
    }

    /**
     * Returns all user rows from the database (sorted by user name).
     * @return array Array of user rows sorted by user name.
     */
    public static function getAllUserDBRows()
    {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username ORDER BY name");
        return $rows;
    }

    /**
     * Returns all active user rows from the database (sorted by user name).
     * @return array Array of active user rows sorted by user name.
     */
    public static function getAllActiveUserDBRows()
    {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE status = '" .
            UserConstants::STATUS_ACTIVE . "' ORDER BY name");
        return $rows;
    }

    /**
     * Returns all rows for users with pending requests from the database
     * (sorted by user name).
     * @return array Array of rows of users with pending requests sorted by
     * user name.
     */
    public static function getAllPendingUserDBRows()
    {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE status != '" .
            UserConstants::STATUS_ACTIVE . "' AND status != '" .
            UserConstants::STATUS_DISABLED . "' AND status != '" .
            UserConstants::STATUS_OUTDATED . "' ORDER BY name");
        return $rows;
    }

    /**
     * Returns all user rows from the database for user names starting by a
     * given letter (sorted by user name).
     * @param string $c First letter
     * @return array Array of user rows filtered by first letter and sorted by
     * user name.
     */
    public static function getAllUserDBRowsByInitialLetter($c)
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
    public static function getTotalNumberOfUsers()
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
    public static function getNumberCountPerInitialLetter()
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
    public static function createUserFolders($username)
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
    public static function deleteUserFolders($username)
    {

        // TODO Use the Shell classes!

        Log::info("Removing directories for '" . $username . "'.");
        global $userManagerScript;
        Log::info(shell_exec($userManagerScript . " delete " . $username));
    }

    /**
     * Get the status of a User.
     *
     * By definition (not to prevent logging in against an external
     * authentication system), if a User does not exist, its status is active.
     *
     * @param string $name Name of the user.
     * @return string status ('a', 'd', ...).
     */
    public static function getUserStatus($name)
    {
        $db = new DatabaseConnection();
        $query = "select status from username where name = '$name'";
        $result = $db->queryLastValue($query);
        if ($result === false) {
            // User not found. Return 'active'.
            return UserConstants::STATUS_ACTIVE;
        }
        return $result;
    }

    /**
     * Set the status of a User.
     * @param string $name Name of the user.
     * @param string $status ('a', 'd', ...).
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False
     */
    public static function setUserStatus($name, $status)
    {
        $db = new DatabaseConnection();
        $query = "update username set status='$status' where name='$name'";
        $result = $db->execute($query);
        return $result;
    }

    //
    // Private methods
    //

    /**
     * Updates the status of an existing user in the database (username is
     * expected to be already validated!)
     * @param string $username The name of the user.
     * @param string $status One of 'd', 'a', ...
     * @return bool True if user status could be updated successfully; false
     * otherwise.
     */
    public static function updateUserStatus($username, $status)
    {
        $db = new DatabaseConnection();
        $query = "UPDATE username SET status = '$status' WHERE name = '$username'";
        $result = $db->execute($query);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Updates the status of all non-admin users in the database.
     * @param string $status One of 'd', 'a', ...
     * @return bool True if the status of all users could be updated successfully;
     * false otherwise.
     */
    public static function updateAllUsersStatus($status)
    {
        $db = new DatabaseConnection();
        $query = "UPDATE username SET status = '$status' WHERE name NOT LIKE 'admin'";
        $result = $db->execute($query);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

}
