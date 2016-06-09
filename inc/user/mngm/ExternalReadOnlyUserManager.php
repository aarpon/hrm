<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

namespace hrm\user\mngm;

use hrm\DatabaseConnection;
use hrm\user\User;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Manages the HRM users relying on an external authentication mechanism.
 *
 * No user-related information can be modified using this Manager; for
 * instance, user password or e-mail address cannot be modified from the HRM.
 *
 * @package hrm
 */
class ExternalReadOnlyUserManager extends AbstractUserManager {

    /**
     * Returns false since the external, read only manager can not create or
     * delete users.
     * @return bool Always false.
     */
    public static function canCreateUsers() { return false; }

    /**
     * Returns false since the external, read only manager can not modify users.
     * @return bool Always false.
     */
    public static function canModifyUsers() { return false; }

    /**
     * Stores (updates) the user information in the database.
     * @param User $user User to store or update in the database.
     * @return void
     */
    public function storeUser(User $user) {

        // Make sure the user is in the database, otherwise add it
        if (! $this->existsInHRM($user)) {
            $randomPasswd = substr(md5(microtime()), rand(0, 26), 12);
            $this->createUser($user->name(), $randomPasswd,
                $user->emailAddress(), $user->userGroup(), 'a');
            return;
        }

        // Update the user information
        $db = new DatabaseConnection();
        $db->updateUserNoPassword($user->name(), $user->emailAddress(),
            $user->userGroup());

        // Update last access time
        $db->updateLastAccessDate($user->name());
    }
};

