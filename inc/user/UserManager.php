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

use Exception;
use hrm\Log;
use hrm\DatabaseConnection;
use hrm\System;
use hrm\user\proxy\ProxyFactory;

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
    public static function canModifyEmailAddress(UserV2 $user)
    {
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
    public static function canModifyGroup(UserV2 $user)
    {
        return $user->proxy()->canModifyGroup();
    }

    /**
     * Return true if the UserManager can modify a User's password in the
     * backing user management system (e.g. Integrated, Active Directory,
     * LDAP, or Auth0).
     *
     * @param UserV2 $user User to be queried.
     * @return bool True if the UserManager can modify tue User's password
     * in the backing user management system, false otherwise.
     */
    public static function canModifyPassword(UserV2 $user)
    {
        return $user->proxy()->canModifyPassword();
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
        $db = DatabaseConnection::get();
        $query = "select status from username where lower(name) = lower('$username')";
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
     * @throws Exception If instantiating the UserV2 object failed.
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
     * Generates a random (plain!) password.
     *
     * This plain password is sent to the user when a new account is created.
     * The version stored in the database must still be encrypted first!
     *
     * @return string Plain text password.
     */
    public static function generateRandomPlainPassword()
    {
        // md5() is just to make the password harder
        return (md5(UserManager::generateUniqueId()));
    }

    /**
     * Generates a random unique id.
     *
     * @return string Id.
     */
    public static function generateUniqueId()
    {
        return uniqid();
    }

    /**
     * Checks if user login is restricted to the administrator for maintenance
     * (e.g. in case the database has to be updated).
     * @return bool True if the user login is restricted to the administrator.
     */
    public static function isLoginRestrictedToAdmin()
    {
        return !(System::isDBUpToDate());
    }

    /**
     * Checks whether a seed for a user creation request exists.
     *
     * Notice, that the user must also have status = UserConstants::STATUS_NEW_ACCOUNT
     * in the database.
     *
     * @param string $seed Seed to be compared.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public static function existsUserRegistrationRequestWithSeed($seed)
    {
        $db = DatabaseConnection::get();
        $query = "SELECT seedid FROM username WHERE status=='" .
            UserConstants::STATUS_NEW_ACCOUNT . "' AND seedid = '$seed';";
        $value = $db->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ($value == $seed);
        }
    }

    /**
     * Checks whether a seed for a password reset request exists.
     *
     * Notice, that the user must also have status = UserConstants::STATUS_PASSWORD_RESET
     * in the database.
     *
     * This function returns false by default and must be reimplemented for
     * those user management implementations that support this.
     * @param string $username User name that is expected to have the specified seed.
     * @param string $seed Seed to be compared.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public static function existsUserPasswordResetRequestWithSeed($username, $seed)
    {
        $db = DatabaseConnection::get();
        $query = "SELECT seedid FROM username WHERE " . "name='" . $username ."' AND status='" .
            UserConstants::STATUS_PASSWORD_RESET . "' AND seedid='$seed';";
        $value = $db->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ($value == $seed);
        }
    }

    /**
     * Generate and set a random seed for User with given name. This also changes
     * the status of the user to UserConstants::STATUS_PASSWORD_RESET.
     *
     * @param string $name Name of the user for which the seed is to be generated.
     * @return string Generated seed. If the seed could not be set, the function returns "".
     */
    public static function generateAndSetSeed($name)
    {
        $seed = UserManager::generateUniqueId();
        $db = DatabaseConnection::get();
        $query = "UPDATE username SET seedid='$seed' WHERE name = '$name';";
        $value = $db->execute($query);
        if ($value == false) {
            return "";
        }
        UserManager::markUserForPasswordReset($name);
        return $seed;
    }

    /**
     * Resets the seed for User with given name.
     *
     * @param string $name Name of the user for which the seed is to be reset.
     * @return bool True if the seed was reset, false otherwise.
     */
    public static function resetSeed($name)
    {
        $db = DatabaseConnection::get();
        $query = "UPDATE username SET seedid=NULL WHERE name = '$name';";
        $value = $db->execute($query);
        if ($value == false) {
            return false;
        } else {
            true;
        }
        return false;
    }

    /**
     * Return all institution rows.
     */
    public static function getAllInstitutions()
    {
        $db = DatabaseConnection::get();
        $query = "SELECT * FROM institution;";
        $rows = $db->execute($query);
        if ($rows == false) {
            return false;
        } else {
            return $rows->getRows();
        }
    }

    /**
     * Returns the number of jobs currently in the queue for current User.
     * @param string $username User name to query.
     * @return int Number of jobs in queue.
     */
    public static function numberOfJobsInQueue($username)
    {
        $db = DatabaseConnection::get();
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
        $db = DatabaseConnection::get();
        $query = "SELECT COUNT(id) FROM job_queue;";
        $row = $db->execute($query)->FetchRow();
        return $row[0];
    }

    /**
     * Adds a User to the database.
     * @param UserV2 $user
     * @param $password string User password The password is not stored in the User object.
     * Set $password to "" to create a random password (useful for external authentication
     * mechanisms).
     * @throws Exception If user creation failed.
     */
    public static function addUser(UserV2 $user, $password = "")
    {
        // Create a random password if needed
        if ($password == "") {
            $password = self::generateRandomPlainPassword();
        }

        // Call the UserManager::createUser method
        self::createUser(
            $user->name(),
            $password,
            $user->emailAddress(),
            $user->group(),
            $user->institution_id(),
            $user->authenticationMode(),
            $user->role(),
            $user->status(),
            "" // Seed
        );
    }

    /**
     * Creates a new User.
     * @param string $username User login name.
     * @param string $password User password in plain text. Set to "" to create a random one.
     * @param string $emailAddress User e-mail address.
     * @param string $group User group.
     * @param int $institution_id User institution id (default = 1).
     * @param string $authentication User authentication mode (by default, it is the
     * default authentication mode: @see ProxyFactory::getDefaultAuthenticationMode()
     * @param int $role User role (optional, default is UserConstants::ROLE_USER).
     * @param string $status User status (optional, the user is activated by
     * default).
     * @param string $seed Seed used to label a user registration request in the database.
     * @return True if the User could be created, false otherwise.
     * @throws Exception If the authentication mode is not supported.
     */
    public static function createUser(
        $username,
        $password,
        $emailAddress,
        $group,
        $institution_id = 1,
        $authentication = "",
        $role = UserConstants::ROLE_USER,
        $status = UserConstants::STATUS_ACTIVE,
        $seed = ""
    ) {
        // Create a random password if needed
        if ($password == "") {
            $password = self::generateRandomPlainPassword();
        }

        if ($authentication == "") {
            $authentication = ProxyFactory::getDefaultAuthenticationMode();
        }

        // Check that the authentication is supported.
        if (! array_key_exists($authentication, ProxyFactory::getAllConfiguredAuthenticationModes())) {
            throw new Exception("Authentication mode not configured or not supported!");
        }

        // If the User already exists, return false
        $db = DatabaseConnection::get();
        if ($db->query("select name from username where name='$username'")) {
            return false;
        }

        // Hash the password
        $password = password_hash(
            $password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST)
        );

        // Add the User
        $record["name"] = $username;
        $record["password"] = $password;
        $record["email"] = $emailAddress;
        $record["research_group"] = $group;
        $record["institution_id"] = $institution_id;
        $record["role"] = $role;
        $record["authentication"] = $authentication;
        $record["creation_date"] = date("Y-m-d H:i:s");
        $record["last_access_date"] = $record["creation_date"];
        $record["status"] = $status;
        $record["seedid"] = $seed;
        $table = "username";
        $insertSQL = $db->connection()->GetInsertSQL($table, $record);
        if (!$db->execute($insertSQL)) {
            Log::error("Could not create new user '$username'!");
            return false;
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
     * @return bool True if the User could be updated or created successfully,
     * false otherwise.
     * @throws Exception If user creation failed.
     */
    public static function storeUser(UserV2 $user, $force = false)
    {
        if (!($user->isLoggedIn()) && !($force)) {
            return false;
        }

        // Get the database connection object
        $db = DatabaseConnection::get();

        if (self::findUserByName($user->name()) == null) {
            // Create the User with a random password
            return self::createUser(
                $user->name(),
                self::generateRandomPlainPassword(),
                $user->emailAddress(),
                $user->group(),
                $user->institution_id(),
                $user->authenticationMode(),
                $user->role(),
                $user->status(),
                "" // Seed
            );

        } else {
            // Update the User
            $sql = "UPDATE username SET name=?, email=?, research_group=?, " .
                "institution_id=?, role=?, status=? WHERE id=?;";
            $result = $db->connection()->Execute($sql,
                array(
                    $user->name(),
                    $user->emailAddress(),
                    $user->group(),
                    $user->institution_id(),
                    $user->role(),
                    $user->status(),
                    $user->id())
            );
            if ($result === false) {
                return false;
            }
            return true;
        }
    }

    /**
     * Force a reload from the database for given user.
     *
     * @param UserV2 $user User to update with the database content. The User must exist
     * in the database; if it does not, an Exception is thrown!
     * @return UserV2 Reloaded user.
     * @throws Exception If the user does not exist in the database.
     */
    public static function reload(UserV2 $user)
    {
        if (! self::findUserByName($user->name())) {
            throw new Exception("User $user does not exist!");
        }

        // Force a reload
        $user->setName($user->name());

        // We explicitly return the updated user
        return $user;
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
        $hashPassword = password_hash(
            $password,
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST)
        );

        // Store it in the database
        $db = DatabaseConnection::get();
        $query = "UPDATE username SET password=? WHERE name=?;";
        $result = $db->connection()->Execute(
            $query,
            array($hashPassword, $username)
        );
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
        $db = DatabaseConnection::get();

        // If there are jobs in the queue for the user, we do not delete
        $sql = "SELECT id FROM job_queue WHERE username=?;";
        $result = $db->connection()->Execute($sql, array($username));
        if ($result->EOF == false) {
            return false;
        }

        // Start transaction: if error, everything will be rolled back.
        $db->connection()->StartTrans();

        // Delete the user from the username table
        $sql = "DELETE FROM username WHERE name=?;";
        $db->connection()->Execute($sql, array($username));

        // Delete all references to the uses in the parameters and settings tables
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

        // Delete all references to the uses in the parameters and settings tables
        $sql = "DELETE FROM shared_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM shared_parameter_setting WHERE owner=? OR previous_owner=?;";
        $db->connection()->Execute($sql, array($username, $username));

        $sql = "DELETE FROM shared_task_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM shared_task_setting WHERE owner=? OR previous_owner=?;";
        $db->connection()->Execute($sql, array($username, $username));

        $sql = "DELETE FROM shared_analysis_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM shared_analysis_setting WHERE owner=? OR previous_owner=?;";
        $db->connection()->Execute($sql, array($username, $username));

        // Delete all references to the uses in the job tables
        $sql = "DELETE FROM job_analysis_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_analysis_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_parameter_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_task_parameter WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_task_setting WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_queue WHERE username=?;";
        $db->connection()->Execute($sql, array($username));

        $sql = "DELETE FROM job_files WHERE owner=?;";
        $db->connection()->Execute($sql, array($username));

        // Complete the transaction (or roll back if failed).
        $success = $db->connection()->CompleteTrans();

        if ($success) {
            // Delete the user folders
            UserManager::deleteUserFolders($username);
            return true;
        }
        return false;
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
    public static function setRole($username, $role = UserConstants::ROLE_USER)
    {
        $db = DatabaseConnection::get();
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
     * @param string $username Name of the user.
     * @param string $mode One of the enabled authentication modes. Subset of
     * {'integrated', 'active_dir', 'ldap', 'auth0'}, depending on the
     * configuration.
     * @return bool True if the authentication mode could be set successfully,
     * false otherwise.
     */
    public static function setAuthenticationMode($username, $mode)
    {
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
        $db = DatabaseConnection::get();
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
     * Accepts user with given username.
     * @param string $username Name of the user to accept.
     * @return bool True if the user could be accepted; false otherwise.
     */
    public static function markUserForPasswordReset($username)
    {
        return (self::updateUserStatus($username, UserConstants::STATUS_PASSWORD_RESET));
    }

    /**
     * Returns all user rows from the database (sorted by user name).
     * @return array Array of user rows sorted by user name.
     */
    public static function getAllUserDBRows()
    {
        $db = DatabaseConnection::get();
        $rows = $db->query("SELECT * FROM username ORDER BY name");
        return $rows;
    }

    /**
     * Returns all active user rows from the database (sorted by user name).
     * @return array Array of active user rows sorted by user name.
     */
    public static function getAllActiveUserDBRows()
    {
        $db = DatabaseConnection::get();
        $rows = $db->query("SELECT * FROM username WHERE (status = '" .
            UserConstants::STATUS_ACTIVE . "' OR status = '" .
            UserConstants::STATUS_OUTDATED . "' OR status = '" .
            UserConstants::STATUS_PASSWORD_RESET . "') " .
        "ORDER BY name;");
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
        $db = DatabaseConnection::get();
        $rows = $db->query("SELECT * FROM username WHERE (seedid IS NOT NULL OR length(seedid) > 0)" .
            " AND status='" . UserConstants::STATUS_NEW_ACCOUNT . "' ORDER BY name;");
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
        $db = DatabaseConnection::get();
        $rows = $db->query("SELECT * FROM username WHERE name LIKE '$c%' ORDER BY name;");
        return $rows;
    }

    /**
     * Returns the total number of users independent of their status (and
     * counting the administrator).
     * @return int Number of users.
     */
    public static function getTotalNumberOfUsers()
    {
        $db = DatabaseConnection::get();
        $count = $db->queryLastValue("SELECT count(*) FROM username WHERE TRUE;");
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
        $db = DatabaseConnection::get();

        // Initialize array of counts
        $counts = array();

        // Query and store the counts
        for ($i = 0; $i < 26; $i++) {
            // Initial letter (filter)
            $c = chr(97 + $i);

            // Get users with name starting by $c
            $query = "SELECT * FROM username WHERE name LIKE '$c%' AND " .
                "name != 'admin' ORDER BY NAME;";
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
        // @TODO Use the Shell classes!
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
        // @TODO Use the Shell classes!
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
        $db = DatabaseConnection::get();
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
     * @return boolean True if the user status could be set successfully, false otherwise.
     */
    public static function setUserStatus($name, $status)
    {
        $db = DatabaseConnection::get();
        $query = "update username set status='$status' where name='$name'";
        $result = $db->execute($query);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    //
    // Private methods
    //

    /**
     * Updates the status of an existing user in the database (username is
     * expected to be already validated!).
     *
     * For status UserConstants::STATUS_ACTIVE and UserConstants::STATUS_DISABLE the seed is reset.
     * For status UserConstants::STATUS_PASSWORD_RESET the seed is NOT reset (a valid seed is expected
     * to be stored already:
     * @see UserManager::markUserForPasswordReset();
     *
     * @param string $username The name of the user.
     * @param string $status One of UserConstants::STATUS_ACTIVE, UserConstants::STATUS_DISABLE.
     * @return bool True if user status could be updated successfully; false otherwise.
     */
    private static function updateUserStatus($username, $status)
    {
        $db = DatabaseConnection::get();
        if ($status == UserConstants::STATUS_PASSWORD_RESET) {
            $query = "UPDATE username SET status = '$status' WHERE name = '$username'";
        } else {
            $query = "UPDATE username SET status = '$status', seedid = '' WHERE name = '$username'";
        }
        $result = $db->execute($query);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Updates the status of all non-admin users in the database. It also clears the request seeds!
     * @param string $status UserConstants::STATUS_ACTIVE, UserConstants::STATUS_DISABLE.
     * @return bool True if the status of all users could be updated successfully;
     * false otherwise.
     */
    private static function updateAllUsersStatus($status)
    {
        // Only the super admin is left untouched
        $db = DatabaseConnection::get();
        $role = UserConstants::ROLE_SUPERADMIN;
        $query = "UPDATE username SET status = '$status', seedid = '' WHERE role != $role";
        $result = $db->execute($query);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }
}
