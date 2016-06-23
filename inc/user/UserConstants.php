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
     * The password hashing algorithm
     */
    const HASH_ALGORITHM = PASSWORD_BCRYPT;

    /**
     * The password hashing algorithm cost
     */
    const HASH_ALGORITHM_COST = 15;

}
