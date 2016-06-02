<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

namespace hrm\user\mngm;

use hrm\DatabaseConnection;
use hrm\user\User;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * Class InternalUserManager
 *
 * Manages the HRM users without relying on any external authentication or
 * management solution.
 *
 * @package hrm
 */
 class InternalUserManager extends AbstractUserManager {

    /**
     * Return true since the HRM internal user management system can create
     * and delete users.
     * @return bool Always true.
     */
    public static function canCreateUsers() { return true; }

    /**
     * Return true since the HRM internal user management system can modify users.
     * @return bool Always true.
     */
    public static function canModifyUsers() { return true; }

    /**
     * Checks whether a user with a given seed exists in the database.
     * @param string $seed Seed to be compared.
     * @return bool True if a user with given seed exists, false otherwise.
     */
    public function existsUserRequestWithSeed($seed) {
        $db = new DatabaseConnection();
        return ($db->existsUserRequestWithSeed($seed));
    }

    /**
     * Stores (updates) the user information in the database.
     * @param User $user User to store or update in the database.
     * @return void.
     */
    public function storeUser(User $user) {

        // Make sure the user is in the database, otherwise return immediately!
        if (! $this->existsInHRM($user)) {
            return;
        }

        // Update the user information
        $db = new DatabaseConnection();
        $db->updateUserNoPassword($user->name(), $user->emailAddress(),
            $user->userGroup());

        // Update last access time
        $db->updateLastAccessDate($user->name());
    }

    /**
     * Updates an existing user in the database.
     *
     * @todo This will need some refactoring (together with the corresponding
     * @todo DatabaseConnection::updateExistingUser() method.)
     *
     * @param bool $isAdmin True if the user is the admin.
     * @param string $username The name of the user.
     * @param string $password The password (plain, not encrypted).
     * @param string $email E-mail address.
     * @param string $group Research group.
     * @return bool True if the update was successful, false otherwise.
     */
    public function updateUser($isAdmin, $username, $password, $email, $group) {

        // Update the user: in case $isAdmin is true, only the password can
        // be changed; all other settings will be ignored.
        $db = new DatabaseConnection();
        return ($db->updateExistingUser($isAdmin, $username, $password,
            $email, $group));

    }

    /**
     * Accepts user with given username.
     * @param string $username Name of the user to accept.
     * @return bool True if the user could be accepted; false otherwise.
     */
    public function acceptUser($username) {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, 'a'));
    }

    /**
     * Enables user with given username.
     * @param string $username Name of the user to enable.
     * @return bool True if the user could be enabled; false otherwise.
     */
    public function enableUser($username) {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, 'a'));
    }

    /**
     * Enables all users.
     * @return bool True if all users could be enabled, false otherwise.
     */
    public function enableAllUsers() {
        $db = new DatabaseConnection();
        return ($db->updateAllUsersStatus('a'));
    }

    /**
     * Disables user with given username.
     * @param string $username Name of the user to disable.
     * @return bool True if the user could be disabled; false otherwise.
     */
    public function disableUser($username) {
        $db = new DatabaseConnection();
        return ($db->updateUserStatus($username, 'd'));
    }

    /**
     * Disables all users.
     * @return  bool True if all users could be disabled; false otherwise.
    */
    public function disableAllUsers() {
        $db = new DatabaseConnection();
        return ($db->updateAllUsersStatus('d'));
    }

    /**
     * Deletes a user from the database.
     * @param string $username Name of the user to be deleted.
     * @return bool True if success; false otherwise.
    */
    public function deleteUser($username) {

        // Delete the user
        $db = new DatabaseConnection();
        if ($db->deleteUser($username)) {

            // Delete the user folders
            $this->deleteUserFolders($username);

            return True;
        }

        return False;
    }

    /**
     * Returns all user rows from the database (sorted by user name).
     * @return array Array of user rows sorted by user name.
    */
    public function getAllUserDBRows() {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username ORDER BY name");
        return $rows;
    }

    /**
     * Returns all active user rows from the database (sorted by user name).
     * @return array Array of active user rows sorted by user name.
    */
    public function getAllActiveUserDBRows() {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE status = 'a' ORDER BY name");
        return $rows;
    }

    /**
     * Returns all user rows from the database for user names starting by a
     * given letter (sorted by user name).
     * @param string $c First letter
     * @return array Array of user rows filtered by first letter and sorted by
     * user name.
    */
    public function getAllUserDBRowsByInitialLetter($c) {
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
    public function getTotalNumberOfUsers() {
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
    public function getNumberCountPerInitialLetter() {

        // Open database connection
        $db = new DatabaseConnection();

        // Initialize array of counts
        $counts = array();

        // Query and store the counts
        for ($i = 0; $i < 26; $i++) {

            // Initial letter (filter)
            $c = chr(97 + $i);

            // Get users with name staring by $c
            $query = "SELECT * FROM username WHERE name LIKE '$c%' AND name != 'admin' AND (status = 'a' OR status = 'd')";
            $result = $db->query($query);

            // Store the count
            $counts[$c] = count($result);

        }

        return $counts;
    }
};
