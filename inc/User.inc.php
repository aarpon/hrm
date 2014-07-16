<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once(dirname(__FILE__) . "/auth/AuthenticatorFactory.inc.php");
require_once(dirname(__FILE__) . "/Database.inc.php");
require_once(dirname(__FILE__) . "/Setting.inc.php");
require_once(dirname(__FILE__) . "/hrm_config.inc.php");
require_once(dirname(__FILE__) . "/System.inc.php");

/*!
  \class   User
  \brief   Manages a user and its state.
*/
class User {

    /*!
    \var    $name
    \brief  Name of the user.
    */
    protected $name;

    /*!
    \var    $emailAddress
    \brief  E-mail address of the user.
    */
    protected $emailAddress;

    /*!
    \var    $group
    \brief  Group of the user.
    */
    protected $group;

    /*!
    \var    $isLoggedIn
    \brief  True if the user is logged in; false otherwise.
    */
    private $isLoggedIn;

    /*!
      \brief  Constructor. Creates a new (unnamed) User.
    */
    function __construct() {

        // Initialize members to empty
        $this->name = "";
        $this->emailAddress = "";
        $this->group = "";
        $this->isLoggedIn = False;
    }

    /*!
    \brief  Returns the name of the Owner
    \return the name of the Owner
    */
    public function name() {
        return $this->name;
    }

    /*!
    \brief  Sets the name of the Owner
    \param  $name The name of the Owner
    */
    public function setName($name) {
        $this->name = $name;
    }

    /*!
      \brief  Checks whether the user is logged in
      \return true if the user is logged in
    */
    public function isLoggedIn() {
        return $this->isLoggedIn;
    }

    /*!
      \brief  Logs in the user with given user name and password

      This function will use different authentication modes depending on the
      value of the global configuration variable $authenticateAgainst.

      \param  $name     User name
      \param  $password Password (plain)
      \return true if the user could be logged in, false otherwise
    */
    public function logIn($name, $password) {

        // Set the name
        $this->setName($name);

        // Try authenticating the user against the appropriate mechanism
        $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
        $this->isLoggedIn = $authenticator->authenticate($this->name(), $password);

        return $this->isLoggedIn;
    }

    /*!
      \brief  Logs out the user
    */
    function logOut() {
        $this->isLoggedIn = False;
    }

    /*!
      \brief  Check whether a new user request has been accepted by the
              administrator

      This should only be used if authentication is against the HRM user management.

      \return true if the user has been accepted; false otherwise.
    */
    public function isAccepted() {

        if ($this->isAdmin()) {
            return true;
        }

        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isAccepted($this->name());
    }

    /*!
      \brief  Checks whether the user has been suspended by the administrator

      This should only be used if authentication is against the HRM user management.

      \return true if the user was suspended by the administrator; false otherwise.
    */
    public function isSuspended() {

        if ($this->isAdmin()) {
            return false;
        }

        $authenticator = AuthenticatorFactory::getAuthenticator(false);
        return $authenticator->isSuspended($this->name());
    }

    /*!
      \brief  Returns the User e-mail address
      \return the User e-mail address
    */
    public function emailAddress() {

        // If the email is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->emailAddress == "") {
            $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
            $this->emailAddress = $authenticator->getEmailAddress($this->name());
        }

        return $this->emailAddress;

    }

    /*!
      \brief  Returns the administrator name
      \return the administrator name
    */
    static public function getAdminName() {
        return 'admin';
    }

    /*!
    \brief Reload info from the source.
     */
    public function reload() {

        // Reload e-mail address
        $this->emailAddress = "";
        $this->emailAddress();

        // Reload group
        $this->group = "";
        $this->userGroup();

    }

    /*!
      \brief  Checks whether the user is the administrator
      \return true if the user is the administrator
    */
    public function isAdmin() {
        return $this->name() == $this->getAdminName();
    }

    /*!
      \brief  Returns the user to which the User belongs
      \return group name
    */
    public function userGroup() {

        // If the group is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->group == "") {
            $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
            $this->group = $authenticator->getGroup($this->name());
        }

        return $this->group;

    }

    /*!
      \brief  Returns the number of jobs currently in the queue for current User
      \return number of jobs in queue
    */
    public function numberOfJobsInQueue() {
        if ($this->name() == "") {
            return 0;
        }
        $db = new DatabaseConnection();
        return $db->getNumberOfQueuedJobsForUser($this->name);
    }

    /*!
      \brief  Checks whether a user with a given seed exists in the database

      If a user requests an account, his username is added to the database with
      a random seed as status.

      \return true if a user with given seed exists, false otherwise
    */
    public function existsUserRequestWithSeed($seed) {
        $query = "SELECT status FROM username WHERE status = '" . $seed . "'";
        $db = new DatabaseConnection();
        $value = $db->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ( $value == $seed );
        }
    }

}

?>
