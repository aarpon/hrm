<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once(dirname(__FILE__) . "/AbstractUserManager.inc.php");
require_once(dirname(__FILE__) . "/../Database.inc.php");
require_once(dirname(__FILE__) . "/../hrm_config.inc.php");
require_once(dirname(__FILE__) . "/../Mail.inc.php");

global $hrm_url;
global $email_sender;
global $email_admin;
global $image_host;
global $image_folder;
global $image_source;
global $userManager;

/*!
  \class   InternalUserManager
  \brief   Manages the HRM users without relying on any external authentication
           or management solution.
*/

class InternalUserManager extends AbstractUserManager {

    /*!
    \brief Return true since the HRM internal user management system
           can create and delete users.
    \return always true.
    */
    public static function canCreateUsers() { return true; }

    /*!
    \brief Return true since the HRM internal user management system
           can modify users.
    \return always true.
     */
    public static function canModifyUsers() { return true; }

    /*!
    \brief  Checks whether a user with a given seed exists in the database.

    \param User $user User to be checked for existing seed.
    \param String $seed Seed to be compared.

    If a user requests an account, his username is added to the database with
    a random seed as status.

    \return true if a user with given seed exists, false otherwise.
    */
    public function existsUserRequestWithSeed($seed) {
        $db = new DatabaseConnection();
        return ($db->existsUserRequestWithSeed($seed));
    }

    /*!
    \brief Store (update) the user information.
    \param User $user User to store in the database.
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

    /*!
    \brief Updates an existing user in the database

    \TODO   This will need some refactoring (together with the corresponding
            DatabaseConnection::updateExistingUser() method.

    \param	$username  	The name of the user
    \param	$password  	Password (plain, not encrypted)
    \param	$email  	E-mail address
    \param	$group  	Research group
    \return	$success	True if success; false otherwise
    */
    public function updateUser($isAdmin, $username, $password, $email, $group) {

        // Update the user: in case $isAdmin is true, only the password can
        // be changed; all other settings will be ignored.
        $db = new DatabaseConnection();
        return ($db->updateExistingUser($isAdmin, $username, $password,
            $email, $group));

    }

    /*!
    \brief Deletes a user from the database
    \param	User $user User to be deleted.
    \return	bool True if success; false otherwise
    */
    public function deleteUser(User $user) {

        // Delete the user
        $db = new DatabaseConnection();
        if ($db->deleteUser($user->name())) {

            // Delete the user folders
            $this->deleteUserFolders($user);

            return True;
        }

        return False;
    }

    /*!
    \brief Return all user rows from the database (sorted by user name).
    \return Array of user rows sorted by user name.
    */
    public function getAllUserDBRows() {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username ORDER BY name");
        return $rows;
    }

    /*!
    \brief Return all active user rows from the database (sorted by user name).
    \return Array of active user rows sorted by user name.
    */
    public function getAllActiveUserDBRows() {
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE status = 'a' ORDER BY name");
        return $rows;
    }

    /*!
    \brief Return all user rows from the database for user names starting
           by a given letter (sorted by user name).
    \param $c First letter
    \return Array of user rows filtered by first letter and sorted by user name.
    */
    public function getAllUserDBRowsByInitialLetter($c) {
        $c = strtolower($c);
        $db = new DatabaseConnection();
        $rows = $db->query("SELECT * FROM username WHERE name LIKE '$c%' ORDER BY name");
        return $rows;
    }

    /*!
     \brief Return the total number of users independent of their status (and counting
            the administrator).
     \return Number of users
     */
    public function getTotalNumberOfUsers() {
        $db = new DatabaseConnection();
        $count = $db->queryLastValue(
            "SELECT count(*) FROM username WHERE 1");
        return $count;
    }

    /*!
     \brief Return a vector of counts of how many users have names starting with
            each of the letters of the alphabet.
     \return array of counts
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
}
