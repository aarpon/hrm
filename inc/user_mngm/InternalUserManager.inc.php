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

} 