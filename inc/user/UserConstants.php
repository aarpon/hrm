<?php
/**
 * UserConstants
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user;

/**
 * Defines some constants to be used in User management.
 *
 * @package hrm
 */
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
     * This is the one and only HRM super administrator.
     */
    const ROLE_SUPERADMIN = 0;

    /**
     * The user is an HRM instance administrator.
     */
    const ROLE_ADMIN = 1;

    /**
     * The user is an HRM institution manager.
     */
    const ROLE_MANAGER = 2;

    /**
     * The user is an HRM super user.
     */
    const ROLE_SUPERUSER = 3;

    /**
     * The user is an HRM standard user.
     */
    const ROLE_USER = 4;

    /**
     * The password hashing algorithm.
     */
    const HASH_ALGORITHM = PASSWORD_BCRYPT;

    /**
     * The password hashing algorithm cost.
     */
    const HASH_ALGORITHM_COST = 15;

}
