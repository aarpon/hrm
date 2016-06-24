<?php
/**
 * ExternalReadOnlyUserManager
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
 * Manages the HRM users relying on an external authentication mechanism.
 *
 * No user-related information can be modified using this Manager; for
 * instance, user password or e-mail address cannot be modified from the HRM.
 *
 * @package hrm
 */
class ExternalReadOnlyUserManager extends UserManager {

    /**
     * Returns false since the external, read only manager can not create or
     * delete users.
     * @return bool Always false.
     */
    public static function canModifyEmailAddress() { return false; }

    /**
     * Returns false since the external, read only manager can not modify users.
     * @return bool Always false.
     */
    public static function canModifyUserGroup() { return false; }

    /**
     * Creates a new (externally managed) user.
     *
     * A password will be created for the user to prevent a password-less
     * user account in case the authentication mode is swithced to 'integrated'.
     * In that case, the user will request a password reset or the administrator
     * will change it,
     *
     * @param string $username User login name.
     * @param string $password This is ignored.
     * @param string $emailAddress This is ignored.
     * @param string $group This is ignored.
     * @param string $authentication User authentication mode.
     * @param string $role User role (optional, default is 'user').
     * @param string $status User status (optional, the user is activated by
     * default).
     * @return True if the User could be created, false otherwise.
     */
    public function createUser($username,
                               $password = "ignored",
                               $emailAddress = "ignored",
                               $group = "ignored",
                               $authentication,
                               $role = 'user',
                               $status = UserConstants::STATUS_ACTIVE) {

        // We make sure that there is a password set for the User
        // (even if it will not be used fr authentication), to
        // prevent easy log in in case the authentication mode is
        // later changed to 'integrated'.
        $password = password_hash(uniqid(),
            UserConstants::HASH_ALGORITHM,
            array('cost' => UserConstants::HASH_ALGORITHM_COST));

        // Add the User
        $db = new DatabaseConnection();
        $record["name"] = $username;
        $record["password"] = $password;
        $record["role"] = $role;
        $record["authentication"] = $authentication;
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
     * @return bool This function always throws an Exception because
     * it should not be called.
     * @throws \Exception This UserManager does not support updating the User.
     */
    public function updateUser($username, $emailAddress, $group)
    {
        throw new \Exception("This UserManager does not support updating " .
            "the User from HRM.");
    }

    /**
     * Change the user password.
     *
     * @param string $username User name.
     * @param string $password New password (plain text).
     * @return bool|void This function always throws an Exception because
     * it should not be called.
     * @throws \Exception This UserManager does not support changing the
     * User password.
     */
    public function changeUserPassword($username, $password)
    {
        throw new \Exception("This UserManager does not support changing " .
            "the User password from HRM.");
    }
};

