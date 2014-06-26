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

}