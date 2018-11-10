<?php
/**
 * User
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user;

use DateTime;
use hrm\DatabaseConnection;
use hrm\Log;
use hrm\user\proxy\AbstractProxy;
use hrm\user\proxy\ProxyFactory;

require_once dirname(__FILE__) . '/../bootstrap.php';


/**
 * Manages a User and its state and authenticates against the
 * configured authentication mechanism.
 *
 * The User class is fundamentally read-only. Changes to the User properties
 * are performed through a UserManager (see below). Persistence of some of
 * those changes are dependent on the underlying authentication system.
 *
 * A User known to HRM is always stored in the database, no matter what the
 * user management backend is.
 *
 * A User relies on a proxy to authenticate and to query information from the
 * underlying user management system. Some user management systems are read-only
 * for HRM.
 *
 * Supported proxies are:
 *
 *   * DatabaseProxy (read/write): the HRM integrated user management system
 *   * ActiveDirectoryProxy (read only): interface to Microsoft Active Directory
 *   * LDAPProxy (read only): interface to generic LDAP (version 3)
 *   * Auth0Proxy (read only): interface to Auth0.
 *
 * The default authentication mode (and therefore proxy) is defined in the
 * HRM settings.
 *
 * @package hrm
 */
class UserV2 {

    /**
     * Name of the user.
     * @var string
     */
    protected $name;

    /**
     * Id of the user (read from the database).
     * @var int
     */
    protected $id;

    /**
     * E-mail address of the user.
     * @var string $emailAddress
     */
    protected $emailAddress;

    /**
     * Group of the user.
     * @var string
     */
    protected $group;

    /**
     * Institution of the user (id).
     * @var int
     */
    protected $institution_id;

    /**
     * User role, see \hrm\user\UserConstants. One of:
     *    * UserConstants::ROLE_SUPERADMIN : super admin
     *    * UserConstants::ROLE_ADMIN: admin
     *    * UserConstants::ROLE_SUPERUSER: user with additional rights
     *    * UserConstants::ROLE_USER: standard user
     * @var int
     */
    protected $role;

    /**
     * User status, see \hrm\user\UserConstants. One of:
     *    * UserConstants::STATUS_ACTIVE: active user
     *    * UserConstants::STATUS_DISABLED: user disabled by an administrator
     *    * UserConstants::STATUS_OUTDATED: user whose password needs to be upgraded to the new auth system
     *    * UserConstants::STATUS_PASSWORD_RESET: user who requested a password reset
     * @var string
     */
    protected $status;

    /**
     * Authentication mechanism, one of:
     *    * "integrated": integrated authentication
     *    * "active_dir": Microsoft Active Directory
     *    * "ldap": generic LDAP
     *    * "auth0": Auth0
     * @var string
     */
    protected $authMode;

    /**
     * User creation date.
     *
     * Date and time when the User was first stored in the database.
     * @var DateTime
     */
    protected $creationDate;

    /**
     * User last login date.
     *
     * Date and time when the User last logged in to HRM.
     * @var DateTime
     */
    protected $lastAccessDate;

    /**
     * Proxy to the user management backend.
     * @var AbstractProxy
     */
    protected $proxy;

    /**
     * True if the user is logged in; false otherwise.
     * @var bool
     */
    private $isLoggedIn;

    /**
     * True if the user is the super administrator, false otherwise.
     *
     * The value is cached to prevent an overhead of communication with
     * the database.
     *
     * @var bool
     */
    private $isSuperAdmin;

    /**
     * True if the user is an administrator, false otherwise.
     *
     * The value is cached to prevent an overhead of communication with
     * the database.
     *
     * @var bool
     */
    private $isAdmin;

    /**
     * Constructor. Creates a new (unnamed) User with default with values.
     * @param string $name Optional (default = null). Name of the User. If
     * omitted, an empty User with default authentication mode is created.
     * If the name is specified and the User exists, it is loaded from the
     * database. If the User does not exist, only the name is set.
     * @throws \Exception
     */
    public function __construct($name = null) {

        // Initialize members to default values

        // The User id is -1 if the User does not exist in the database
        // and/or has not yet been loaded.
        $this->id = -1;

        // By default, the User has no name, e-mail address, group, or
        // institution (id).
        $this->name = "";
        $this->emailAddress = "";
        $this->institution_id = 1;
        $this->group = "";

        // A User is by default a user.
        $this->role = UserConstants::ROLE_USER;

        // The default authentication mode is defined in the settings.
        $this->authMode = ProxyFactory::getDefaultAuthenticationMode();

        // Creation and last access dates are not known
        $this->creationDate = null;
        $this->lastAccessDate = null;

        // User status
        $this->status = UserConstants::STATUS_ACTIVE;

        // Initially we do not know if the user is an administrator
        $this->isAdmin = null;

        // The User is not logged in by default.
        $this->isLoggedIn = False;

        // If the name is specified, we try to load the User
        if ($name != null) {
            $this->setName($name);
        }
    }

    /**
     * Returns the User id
     * @return int User id.
     */
    public function id() {
        return $this->id;
    }

    /**
     * Returns the name of the User.
     * @return string The name of the User.
     */
    public function name() {
        return $this->name;
    }

    /**
     * Sets the name of the User.
     *
     * If a User with the given name exists in the database, it is loaded.
     * @param string $name The name of the User.
     * @throws \Exception If the authentication mode is not recognized
     * for the specified user.
     */
    public function setName($name) {

        // Set the name
        $this->name = $name;

        // Get the appropriate proxy
        $this->proxy = ProxyFactory::getProxy($name);

        // Load the user
        $this->load();
    }

    /**
     * Returns the creation date of the User.
     * @return string Creation date of the User.
     */
    public function creationDate() {
        return $this->creationDate;
    }

    /**
     * Returns the last access date of the User.
     * @return string Last access date of the User.
     */
    public function lastAccessDate() {
        return $this->lastAccessDate;
    }

    /**
     * Returns the role of the User.
     * @return string Role of the User.
     */
    public function role() {
        return $this->role;
    }

    /**
     * Returns the institution of the User.
     * @return string Institution of the User.
     */
    public function institution_id() {
        return $this->institution_id;
    }

    /**
     * Set the institution Id for the User.
     * @param $institution_id int Institution Id.
     */
    public function set_institution_id($institution_id) {
        $this->institution_id = $institution_id;
    }

    /**
     * Returns the institution of the User.
     * @return string Institution name of the User.
     */
    public function institution_name() {
        $db = new DatabaseConnection();
        $name = $db->queryLastValue("SELECT name FROM institution WHERE id=" . $this->id . ";");
        return $name;
    }

    /**
     * Returns the authentication mode for the User.
     * @return string Authentication mode for the User.
     */
    public function authenticationMode() {
        return $this->authMode;
    }

    /**
     * Sets the authentication mode for the User.
     * @param $authMode string Authentication mode for the User.
     * @throws \Exception If the authentication mode is not configured or recognized.
     */
    public function setAuthenticationMode($authMode) {
        $authModes = ProxyFactory::getAllConfiguredAuthenticationModes();
        if (! in_array($authMode, $authModes)) {
            throw new \Exception("The authentication mode is not configured or recognized.");
        }
        $this->authMode = $authMode;
    }

    /**
     * Returns the status of the User.
     * @return string Status of the User.
     */
    public function status() {
        return $this->status;
    }

    /**
     * Set the status of the User.
     * @param $status int Status of the User: one of UserConstants::STATUS_ACTIVE,
     * UserConstants::STATUS_DISABLED.
     * @throws \Exception If the status is not valid.
     */
    public function setStatus($status) {
        if ($status != UserConstants::STATUS_ACTIVE &&
            $status != UserConstants::STATUS_DISABLED &&
            $status != UserConstants::STATUS_OUTDATED) {
                throw new \Exception("Invalid status!");
        }
        $this->status = $status;
    }

    /**
     * Returns the proxy of the User.
     * @return AbstractProxy Proxy of the User.
     */
    public function proxy() {
        return $this->proxy;
    }

    /**
     * Checks whether the user is logged in.
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLoggedIn() {
        return $this->isLoggedIn;
    }

    /**
     * Logs in the user with given user name and password
     *
     * This function will use different authentication modes depending on the
     * value of the global configuration variable $authenticateAgainst.
     *
     * @param  string $password Password (plain)
     * @return bool True if the user could be logged in, false otherwise.
     * @throws \Exception If the User does not have a name.
     */
    public function logIn($password) {

        // Check the name the name
        if ($this->name == null) {
            throw new \Exception("User does not have a name!");
        }

        // Get the appropriate proxy
        $this->proxy = ProxyFactory::getProxy($this->name());

        // Try authenticating the user
        $this->isLoggedIn = $this->proxy->authenticate($this->name(), $password);

        // Update the user information for a successful login
        if ($this->isLoggedIn == true) {

            // Load all User information (this retrieves data from all
            // relevant sources)
            $this->load();

            // Update the last access date in the database
            $this->setLastAccessDate();

        }

        return $this->isLoggedIn;
    }

    /**
     * Logs out the user
     */
    public function logOut() {
        $this->isLoggedIn = False;
    }

    /**
     * Check whether a new user request has been accepted by the
     * administrator
     *
     * This should only be used if authentication is against the HRM user
     * management.
     *
     * @return bool True if the user has been accepted; false otherwise.
     */
    public function isAccepted() {

        if ($this->isAdmin()) {
            return true;
        }

        return $this->proxy->isActive($this->name());
    }

    /**
     * Checks whether the user has been suspended by the administrator
     *
     * This should only be used if authentication is against the HRM user
     * management.
     *
     * @return bool True if the user was suspended by the administrator;
     * false otherwise.
     */
    public function isDisabled() {

        if ($this->isAdmin()) {
            return false;
        }

        return $this->proxy->isDisabled($this->name());
    }

    /**
     * Returns the User e-mail address.
     * @return string The User e-mail address.
     */
    public function emailAddress() {

        // If the email is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->emailAddress == "") {
            $this->emailAddress = $this->proxy->getEmailAddress($this->name());
        }

        return $this->emailAddress;

    }

    /**
     * Sets the User e-mail address.
     * @param $emailAddress string The User e-mail address.
     * @throws \Exception If the underlying proxy does not allow modifying the e-mail address.
     */
    public function SetEmailAddress($emailAddress) {

        if (! $this->proxy()->canModifyEmailAddress()) {
            throw new \Exception("The e-mail address of this user cannot be modified by the proxy!");
        }

        // If the email is already stored in the object, return it; otherwise
        // retrieve it.
        $this->emailAddress = $emailAddress;

    }

    /**
     * Checks whether the user is an administrator or the super administrator.
     * @return bool True if the user is an administrator, false otherwise.
     */
    public function isAdmin() {

        // If needed, retrieve.
        if ($this->isAdmin === null) {

            $db = new DatabaseConnection();
            $res = $db->connection()->Execute(
                "SELECT role FROM username WHERE name=?;",
                array($this->name));
            if ($res === false) {
                Log::error("Could not retrieve role for user $this->name.");
                return false;
            }
            $rows = $res->GetRows();

            // Store
            $this->isAdmin = ($rows[0]['role'] == UserConstants::ROLE_ADMIN ||
                              $rows[0]['role'] == UserConstants::ROLE_SUPERADMIN);
        }

        // Return cached version
        return $this->isAdmin;
    }

    /**
     * Checks whether the user is the super administrator.
     * @return bool True if the user is the super administrator, false otherwise.
     */
    public function isSuperAdmin() {

        // If needed, retrieve.
        if ($this->isSuperAdmin === null) {

            $db = new DatabaseConnection();
            $res = $db->connection()->Execute(
                "SELECT role FROM username WHERE name=?;", array($this->name));
            if ($res === false) {
                Log::error("Could not retrieve role for user $this->name.");
                return false;
            }
            $rows = $res->GetRows();

            // Store
            $this->isSuperAdmin = ($rows[0]['role'] == UserConstants::ROLE_SUPERADMIN);
        }

        // Return cached version
        return $this->isSuperAdmin;
    }

    /**
     * Returns the user to which the User belongs.
     * @return string Group name.
     */
    public function group() {

        // If the group is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->group == "") {
            $this->group = $this->proxy->getGroup($this->name());
        }

        return $this->group;

    }

    /**
     * Sets the User group.
     * @param $group string The User group.
     * @throws \Exception If the underlying proxy does not allow modifying the group.
     */
    public function SetGroup($group) {

        if (! $this->proxy()->canModifyGroup()) {
            throw new \Exception("The group of this user cannot be modified by the proxy!");
        }

        // If the email is already stored in the object, return it; otherwise
        // retrieve it.
        $this->group = $group;

    }

    /**
     * Load the User data from the database.
     *
     * This function is private because it should only be used internally!
     * This function does not load the password!
     */
    private function load()
    {
        // Instantiate the database connection
        $db = new DatabaseConnection();

        // Load all information for current user
        $result = $db->connection()->Execute(
            "SELECT * FROM username WHERE name = ?;",
            array($this->name));
        $rows = $result->GetRows();
        if (count($rows) == 0)
        {

            // A user with current name does not yet exist: we create it.
            $row = array();
            $row["name"] = $this->name();
            $row["email"] = $this->emailAddress();
            $row["research_group"] = $this->group();
            $row["institution_id"] = $this->institution_id();
            $row["role"] = $this->role();
            $row["authentication"] = $this->authenticationMode();
            $row["creation_date"] = $this->creationDate();
            $row["last_access_date"] = $this->lastAccessDate();
            $row["status"] = $this->status();
            $row["id"] = -1;

        } else {

            $row = $rows[0];
        }

        // User ID
        $this->id = intval($row["id"]);

        // User e-mail address
        if ($this->isLoggedIn) {
            // If the User is logged in, always retrieve the e-mail address
            // via the  correct proxy.
            $this->emailAddress = $this->proxy->getEmailAddress($this->name);
        } else {
            $this->emailAddress = $row["email"];
        }

        // User (research) group
        if ($this->isLoggedIn) {
            // If the User is logged in, always retrieve the research group
            // via the  correct proxy.
            $this->group = $this->proxy->getGroup($this->name);
        } else {
            $this->group = $row["research_group"];
        }

        // User institution ID
        $this->institution_id = $row["institution_id"];

        // User role
        $this->role = $row["role"];

        // User status
        $this->status = $row["status"];

        // Creation date
        $this->creationDate = $row["creation_date"];

        // Last access data
        $this->lastAccessDate = $row["last_access_date"];

        // Authentication mode
        $this->authMode = $row["authentication"];

        // Cache the isAdmin check
        $this->isAdmin = ($this->role == UserConstants::ROLE_ADMIN || $this->role == UserConstants::ROLE_SUPERADMIN);

        // Cache the isSuperAdmin check
        $this->isSuperAdmin = ($this->role == UserConstants::ROLE_SUPERADMIN);

    }

    /**
     * Sets the last access date of the User (and stores it in the database).
     */
    private function setLastAccessDate() {

        // The user might not yet exist in the database. This is not
        // necessarily an error.
        $db = new DatabaseConnection();
        if ($db->queryLastValue("SELECT id FROM username WHERE name='$this->name';") === false) {
            return true;
        }

        // Set the last access date to now
        $this->lastAccessDate = date("Y-m-d H:i:s");

        // Store it in the database

        $query = "UPDATE username SET last_access_date=? WHERE name=?;";
        $result = $db->connection()->Execute($query,
            array($this->lastAccessDate, $this->name)
        );

        if ($result === false) {
            Log::error("Could not update user $this->name in the database!");
            return false;
        }

        return true;
    }

};

