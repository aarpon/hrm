<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once(dirname(__FILE__) . "/auth/AuthenticatorFactory.inc.php");
require_once(dirname(__FILE__) . "/Database.inc.php");
require_once(dirname(__FILE__) . "/Setting.inc.php");
require_once(dirname(__FILE__) . "/hrm_config.inc.php");
require_once(dirname(__FILE__) . "/System.inc.php");

global $authenticateAgainst;

/*!
  \class   User
  \brief   Manages a user and its state.
*/
class User {

    /*!
    \var    $name
    \brief  Name of the owner: could be a job id or a user's login name.
    */
    protected $name;

    /*!
    \var    $isLoggedIn
    \brief  True if the user is logged in; false otherwise.
    */
    private $isLoggedIn;

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
      \brief  Constructor. Creates a new (unnamed) User.
    */
    function __construct() {

        // Initialize members
        $this->name = '';
        $this->isLoggedIn = False;
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

        global $authenticateAgainst;

        // Set the name
        $this->setName($name);

        // Set the user isLoggedIn status to false;
        $this->isLoggedIn = False;

        // Try authenticating the user against the appropriate mechanism
        $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
        $result = $authenticator->authenticate($this->name(), $password);

        // In case of successful authentication, update the user information.
        if ($result) {

            // Set isLoggedIn status to true
            $this->isLoggedIn = True;

            // Update the last access in the database if using the
            // internal HRM user management.
            // \TODO Later, this will be extended to all authentication mechanisms.
            if ($authenticateAgainst == 'MYSQL') {
                $authenticator->updateLastAccessDate($this->name());
            }

            // Store the entry in the log
            report("User " . $this->name() . " logged on.", 1);

        }
        return $result;
    }

    /*!
      \brief  Logs out the user
    */
    function logOut() {
        $this->isLoggedIn = False;
        $this->name = "";
    }

    /*!
      \brief  Checks if user login is restricted to the administrator for
              maintenance (in case the database has to be updated)
      \return true if the user login is restricted to the administrator
    */
    public function isLoginRestrictedToAdmin() {
        $result = !( System::isDBUpToDate() );
        return $result;
    }

    /*!
      \brief  Returns the User e-mail address
      \return the User e-mail address
    */
    public function emailAddress() {

        // Get the user's email address
        $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
        return $authenticator->getEmailAddress($this->name());
    }

    /*!
      \brief  Returns the administrator name
      \return the administrator name
    */
    static public function getAdminName() {
        return 'admin';
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

        // Get the user's group.
        $authenticator = AuthenticatorFactory::getAuthenticator($this->isAdmin());
        return $authenticator->getGroup($this->name());

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

}

?>
