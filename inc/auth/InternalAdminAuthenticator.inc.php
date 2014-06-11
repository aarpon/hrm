<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include AbstractAuthenticator and the HRM configuration files.
require_once(dirname(__FILE__) . "/InternalAuthenticator.inc.php");
require_once(dirname(__FILE__) . "/../hrm_config.inc.php");

global $email_admin;

/*!
  \class	InternalAuthenticator
  \brief	Manages authentication against the internal HRM user database.

 */

class InternalAdminAuthenticator extends InternalAuthenticator {

    /*!
      \brief	Constructor: instantiates an AdminAuthenticator object.
                No parameters are passed to the constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /*!
    \brief Return the admin e-mail address from the configuration file.
    \return String Admin e-mail address.
    */
    public function getEmailAddress($username = "ignored") {
        global $email_admin;
        return $email_admin;
    }

    /*!
    \brief Return the group the user with given username belongs to.
    \param $username String Username for which to query the group.
    \return String Group or "" if not found.
    */
    public function getGroup($username = "ignored") {
        return "admin";
    }

}

?>
