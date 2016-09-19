<?php
/**
 * Created by PhpStorm.
 * User: aaron
 * Date: 23/06/16
 * Time: 10:39
 */

namespace hrm\user;


class UserConstants
{
    /**
     * The user is accepted / active / enabled.
     */
    const STATUS_ACTIVE = 'a';

    /**
     * The user is suspended / disabled.
     */
    const STATUS_DISABLED = 'd';

    /**
     * The user is outdated (i.e. in need of a password rehash).
     */
    const STATUS_OUTDATED = 'o';

    /**
     * The user is an HRM instance administrator.
     */
    const ROLE_ADMIN = 0;

    /**
     * The user is an HRM institution manager.
     */
    const ROLE_MANAGER = 1;

    /**
     * The user is an HRM super user.
     */
    const ROLE_SUPERUSER = 2;

    /**
     * The user is an HRM standard user.
     */
    const ROLE_USER = 3;

    /**
     * The password hashing algorithm
     */
    const HASH_ALGORITHM = PASSWORD_BCRYPT;

    /**
     * The password hashing algorithm cost
     */
    const HASH_ALGORITHM_COST = 15;

}
