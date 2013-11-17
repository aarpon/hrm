<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once "Database.inc.php";
require_once "Setting.inc.php";
require_once "hrm_config.inc.php";
require_once "System.inc.php";

global $authenticateAgainst;
global $use_ldaps;
global $email_admin;

if ( $authenticateAgainst == "ACTIVE_DIR" ) {
    require_once "ActiveDirectory.inc.php";
}

if ( $authenticateAgainst == "LDAP" ) {
    require_once "Ldap.inc.php";
}

/*!
  \class   Owner
  \brief	Represents the owner of a setting.
*/
class Owner {

    /*!
    \var    $name
    \brief  Name of the owner: could be a job id or a user's login name
    */
    protected $name;

    /*!
    \brief  Constructor. Creates a new Owner.
    */
    public function __construct() {
        $this->name = '';
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

} // end of class Owner

/*!
  \class   User
  \brief   Manages a user and its state
*/
class User extends Owner{

    /*!
      \var    $isLoggedIn
      \brief  True if the user is logged in
    */
    private $isLoggedIn;

    /*!
      \var    $lastActivity
      \brief  Timestamp of the last activity of the user
    */
    private $lastActivity;

    /*!
      \var    $ip
      \brief  The user's current ip address
    */
    private $ip;
  
    /*!
      \var    $authMode
      \brief  Authentication mode, one of "MYSQL", "LDAP", or "ACTIVE_DIR"
    */
    private $authMode;

    /*!
      \brief  Constructor. Creates a new User.
    */
    function __construct() {

        global $authenticateAgainst;
        $this->isLoggedIn = False;
        $this->lastActivity = time();
        $this->ip = '';
        if (!(
                ( $authenticateAgainst == "MYSQL" ) ||
                ( $authenticateAgainst == "LDAP" ) ||
                ( $authenticateAgainst == "ACTIVE_DIR" ) )) {
            throw new Exception("Bad value $authenticateAgainst.");
        }

        $this->authMode = $authenticateAgainst;

        // Call the parent constructor too.
        parent::__construct();
    }

    /*!
      \brief  Sets the user current IP address
      \param  $ip User's IP address
    */
    public function setIp($ip) {
        $this->ip = $ip;
    }

    /*!
      \brief  Returns the user current IP address
      \return the user's IP address
    */
    public function ip() {
        return $this->ip;
    }

    /*!
      \brief  Checks whether the user is logged in
      \return true if the user is logged in
    */
    public function isLoggedIn() {
        return $this->isLoggedIn;
    }

    /*!
      \brief  encrypts a string either with md5 or DES

      The encryption algorithm used is defined by the $useDESEncryption
      variable in the HRM configuration files,

      \param  $string The string to be encrypted
      \param  $seed The seed (this is used only by the DES algorithm)
      \return the encrypted string
    */
    public function encrypt($string, $seed) {
        global $useDESEncryption;
        if ($useDESEncryption) {
            $result = crypt($string, $seed);
        } else {
            $result = md5($string);
        }
        return $result;
    }

    /*!
      \brief  Logs in the user with given user name and password

      This function will use different authentication modes depending on the 
      value of the global configuration variable $authenticateAgainst.

      If $authenticateAgainst is:
        'MYSQL', the user will be authenticated against the HRM user management
        'LDAP', the user will be authenticated against an LDAP server
        'ACTIVE_DIR', the user will be authenticated against ACTIVE DIRECTORY

      \param  $name     User name
      \param  $password Password (plain)
      \param  $ip       IP address
      \return true if the user could be logged in, false otherwise
    */
    public function logIn($name, $password, $ip) {
        $this->setName($name);
        $this->isLoggedIn = False;
        $result = $this->checkLogin($name, $password);
        if ($result) {

            // Update the last access in the database
            $this->updateLastAccessDate();

            // Store the entry in the log
            report("User " . $name . " logged on.", 1);

            // Update the User
            $this->isLoggedIn = True;
            $this->lastActivity = time();
            $this->name = $name;
            $this->ip = $ip;
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
      \brief  Check whether a new user request has been accepted by the
              administrator

      This can only be used if authentication is against the HRM user management.
    
      \return true if the user has been accepted
    */
    public function isStatusAccepted() {
        $db = new DatabaseConnection();
        $status = $db->getUserStatus($this->name());
        $result = ($status == $this->getAcceptedStatus());
        return $result;
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
      \brief  Checks whether the user has been suspended by the administrator

      This can only be used if authentication is against the HRM user management.
      
      \return true if the user was suspened by the administrator
    */
    public function isSuspended() {
        $db = new DatabaseConnection();
        $status = $db->getUserStatus($this->name());
        $result = ($status == $this->getSuspendedStatus());
        return $result;
    }

    /*!
      \brief  Checks whether the user account exists in the database

      This can only be used if authentication is against the HRM user management.
    
      \return true if the user exists in the database
    */
    public function exists() {
        $db = new DatabaseConnection();
        return $db->checkUser($this->name());
    }

    /*!
      \brief  Returns the User e-mail address
      \return the User e-mail address
    */
    public function emailAddress() {

        global $email_admin;

        if ($this->isAdmin()) {
            return $email_admin;
        }

        switch ($this->authMode) {

            case "LDAP":

                $ldap = new Ldap();
                $result = $ldap->emailAddress($this->name());
                return $result;
                break;

            case "ACTIVE_DIR":

                $activeDir = new ActiveDirectory( );
                $result = $activeDir->emailAddress($this->name());
                return $result;
                break;

            case "MYSQL":

                $db = new DatabaseConnection();
                $result = $db->emailAddress($this->name());
                break;

            default:

                throw new Exception("Bad value for $this->authMode in User::emailAddress().");
        }

        return $result;
    }

    /*!
      \brief  Returns the administrator name
      \return the administrator name
    */
    public function getAdminName() {
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
        switch ($this->authMode) {

            case "LDAP":

                $ldap = new Ldap();
                $result = $ldap->getGroup($this->name());
                return $result;
                break;

            case "ACTIVE_DIR":

                $activeDir = new ActiveDirectory( );
                $result = $activeDir->getGroup($this->name());
                return $result;
                break;

            case "MYSQL":

                $db = new DatabaseConnection();
                $result = $db->getGroup($this->name());
                break;

            default:

                throw new Exception("Bad value for $this->authMode in User::userGroup().");
        }

        return $result;
    }

    /*!
      \brief  Returns the number of jobs currently in the queue for current User
      \return number of jobs in queue
    */
    public function numberOfJobsInQueue() {
        if ($this->name == "") {
            return 0;
        }
        $db = new DatabaseConnection();
        return $db->getNumberOfQueuedJobsForUser($this->name);
    }

    /*!
      \brief  Checks whether a user with a given seed exists in the database

      If a user requests an account, his username is added to the database with
      a random seed as status.

      \return true if a user with given seed exists, false othrwise
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

    /*
                              PRIVATE FUNCTIONS
    */

    /*!
      \brief   Returns the user (encrypted) password

      This method can only be used if the HRM embedded user management is in use
      and will throw an exception if the value of the global configuration
      variable $authenticateAgainst is NOT "MYSQL". Since this method is private,
      it should not be a problem for the users of the class, but the check enforces
      internal consistency.

      \param $name User name
      \return  the encrypted password
    */
    private function password($name) {

        // Make sure this method is called only for the MYSQL authentication mode.
        if (strcasecmp($this->authMode, "MYSQL") !== 0) {
            throw new Exception("Attempt to call User::password() method " .
            "for external authentication methods!");
        }

        // If the user is the admin, we check against the MYSQL DB
        if ($name == $this->getAdminName()) {
            // db code
            $db = new DatabaseConnection();
            $password = $db->queryLastValue($db->passwordQueryString($name));
            return $password;
        }

        // Get and return the password
        $db = new DatabaseConnection();
        $password = $db->queryLastValue($db->passwordQueryString($name));
        return $password;

    }

    /*!
      \brief  Checks the login credentials against the selected mechanism

      This can only be used if authentication is against the HRM user management.
      \param  $name       User name
      \param  $password   User password
      \return true if authentication succeeded, false otherwise
    */
    private function checkLogin($name, $password) {
        $result = false;

        // If the db is outdated and the user is not the admin, we do not allow
        // the login
        if (($this->isLoginRestrictedToAdmin() == true) && 
                (strcmp($name, 'admin') != 0))
            return $result;

        // If the user is the admin, we check the MYSQL DB
        if ($name == $this->getAdminName()) {
            $result = $this->checkLoginAgainstHRMDatabase($name, $password);
            return $result;
        }

        // Check other login names against the chosen authentication mechanism
        switch ($this->authMode) {

            case "LDAP":

                $result = $this->checkLoginAgainstLDAP($name, $password);
                break;

            case "ACTIVE_DIR":

                $result = $this->checkLoginAgainstACTIVEDIR($name, $password);
                break;

            case "MYSQL":

                $result = $this->checkLoginAgainstHRMDatabase($name, $password);
                break;

            default:

                throw new Exception("Bad value for $this->authMode in User::checkLogin().");
        }

        return $result;
    }

    /*!
      \brief  Checks the login credentials against the HRM user management

      \param  $name       User name
      \param  $password   User password
      \return true if authentication succeeded, false otherwise
    */
    private function checkLoginAgainstHRMDatabase($name, $password) {
        // add user management
        if (!$this->isStatusAccepted())
            return false;
        $dbPassword = $this->password($name);
        if (!$dbPassword)
            return false;
        $result = ($dbPassword == 
            ($this->encrypt($password, substr($dbPassword, 0, 2))));
        return $result;
    }

    /*!
      \brief  Checks the login credentials against LDAP

      \param  $name       User name
      \param  $password   User password
      \return true if authentication succeeded, false otherwise
    */
    private function checkLoginAgainstLDAP($name, $password) {
        $ldap = new Ldap();
        $result = $ldap->authenticate(strtolower($name), $password);
        return $result;
    }

    /*!
      \brief  Checks the login credentials against Active Directory
      \param  $name       User name
      \param  $password   User password
      \return true if authentication succeeded, false otherwise
    */
    private function checkLoginAgainstACTIVEDIR($name, $password) {
        $activeDir = new ActiveDirectory( );
        $result = $activeDir->authenticate(strtolower($name), $password);
        return $result;
    }

    /*!
      \brief  Returns the User accepted status 'code'
      \return the User accepted status
    */
    private function getAcceptedStatus() {
        return 'a';
    }

    /*!
      \brief  Returns the User suspended status 'code'
      \return the User suspended status
    */
    private function getSuspendedStatus() {
        return 'd';
    }

    /*!
      \brief  Updates the User last access in the database

      This can only be used if authentication against the HRM user managerment
      is used (with other authentication modes it will have no effect).
    */
    private function updateLastAccessDate() {
        if ($this->authMode == "MYSQL") {
            $db = new DatabaseConnection();
            $db->updateLastAccessDate($this->name());
        }
    }

}

?>
