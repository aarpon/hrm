<?php
/**
 * InternalUserManager
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\user\mngm;

use hrm\DatabaseConnection;
use hrm\Log;
use hrm\user\UserConstants;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Manages the HRM users without relying on any external authentication or
 * management solution.
 *
 * @package hrm
 */
 class IntegratedUserManager extends UserManager {

    /**
     * Return true since the HRM internal user management system can create
     * and delete users.
     * @return bool Always true.
     */
    public static function canModifyEmailAddress() { return true; }

    /**
     * Return true since the HRM internal user management system can modify users.
     * @return bool Always true.
     */
    public static function canModifyUserGroup() { return true; }

     /**
      * Creates a new user.
      * @param string $username User login name.
      * @param string $password User password in plain text.
      * @param string $emailAddress User e-mail address.
      * @param string $group User group.
      * @param string $role User role (optional, default is 'user').
      * @param string $status User status (optional, the user is activated by
      * default).
      * @return True if the User could be created, false otherwise.
      */
     public function createUser($username, $password, $emailAddress, $group,
                                $role = 'user',
                                $status = UserConstants::STATUS_ACTIVE) {

         // Hash the password
         $password = password_hash($password,
             UserConstants::HASH_ALGORITHM,
             array('cost' => UserConstants::HASH_ALGORITHM_COST));

         // Add the User
         $db = new DatabaseConnection();
         $record["name"] = $username;
         $record["password"] = $password;
         $record["email"] = $emailAddress;
         $record["research_group"] = $group;
         $record["role"] = $role;
         $record["status"] = UserConstants::STATUS_ACTIVE;
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
      * Update the user.
      *
      * This function does not change the password!
      * @see changeUserPassword
      *
      * @param string $username
      * @param string $emailAddress
      * @param string $group
      * @return bool True if the user could be updated, false otherwise,
      */
     public function updateUser($username, $emailAddress, $group) {

         $sql = "UPDATE username SET email=?, research_group=? WHERE name=?;";
         $db = new DatabaseConnection();
         $result = $db->connection()->Execute($sql,
             array($emailAddress, $group, $username));
         if ($result === false) {
             return false;
         }
         return true;
     }

     /**
      * Change the user password.
      *
      * @param string $username User name.
      * @param string $password New password (plain text).
      * @return bool True if the user password could be changed, false otherwise.
      */
     public function changeUserPassword($username, $password)
     {
         // Hash the password
         $hashPassword = password_hash($password,
             UserConstants::HASH_ALGORITHM,
             array('cost' => UserConstants::HASH_ALGORITHM_COST));

         $sql = "UPDATE username SET password=? WHERE name=?;";
         $db = new DatabaseConnection();
         $result = $db->connection()->Execute($sql,
             array($hashPassword, $username));
         if ($result === false) {
             return false;
         }
         return true;
     }
 };
