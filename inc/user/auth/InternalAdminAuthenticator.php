<?php
/**
 * InternalAdminAuthenticator
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\auth;

require_once dirname(__FILE__) . '/../../bootstrap.php';

/**
 * Class InternalAdminAuthenticator
 *
 * Manages authentication against the internal HRM user database.
 *
 * @package hrm
 */
class InternalAdminAuthenticator extends InternalAuthenticator {

    /**
     * InternalAdminAuthenticator constructor: instantiates an AdminAuthenticator
     * object. No parameters are passed to the constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Returns the admin e-mail address from the configuration file.
     * @param string $username The user name is ignored since the admin username
     * is currently fixed.
     * @return String Admin e-mail address.
     */
    public function getEmailAddress($username = "ignored") {
        global $email_admin;
        return $email_admin;
    }

    /**
     * Returns the group of the admin (curretly hardcoded to 'admin').
     * @param string $username The user name is ignored since the admin username
     * is currently fixed.
     * @return string Always "admin".
    */
    public function getGroup($username = "ignored") {
        return "admin";
    }

};
