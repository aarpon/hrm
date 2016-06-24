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
 * A User known to HRM is always stored in the database, no matter what the 
 * user management backend is.
 * 
 * A User relies on a proxy to authenticate and to query information from the 
 * underlying user management system. Some user management systems are read-only
 * for the HRM.
 * 
 * Supported proxies are:
 * 
 *   * DatabaseProxy (read/write): the HRM integrated user management system
 *   * ActiveDirectoryProxy (read only): interface to Microsoft Active Directory
 *   * LDAPProxy (read only): interface to generic LDAP (version 3)
 *   * Auth0Proxy (read only): interface to Auth0.
 * 
 * Moreover, transparently linked to the underlying proxy, HRM offers some
 * UserManagement classes:
 * 
 *   * IntegratedUserManager: uses DatabaseProxy and allows for read/write
 *     operations.
 *   * ExternalReadOnlyUserManager: uses ActiveDirectoryProxy, LDAPProxy and 
 *     Auth0 proxy for read-only operations. 
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
     * User role, one of:
     *     "admin": HRM administrator
     *     "manager: facility manager
     *     "superuser": user with additional rights
     *     "user": standard HRM user
     * @var string
     */
    protected $role;

    /**
     * User status, one of:
     *
     *   * UserConstants::STATUS_ACTIVE
     *   * UserConstants::STATUS_DISABLED
     *   * UserConstants::STATUS_OUTDATED
     *
     * @var string
     */
    protected $status;

    /**
     * Authentication mechanism, one of:
     *     "integrated": integrated authentication
     *     "active_dir": Microsoft Active Directory
     *     "ldap": generic LDAP
     *     "auth0": Auth0
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
     * True if the user is an administrator, false otherwise.
     *
     * The value is cached to prevent an overhead of communication with
     * with the database.
     *
     * @var bool
     */
    private $isAdmin;

    /**
     * Constructor. Creates a new (unnamed) User with default with values.
     */
    public function __construct() {

        // Initialize members to default values

        // The User id is -1 if the User does not exist in the database
        // and/or has not yet been loaded.
        $this->id = -1;

        // By default, the User has no name, e-mail address, or group.
        $this->name = "";
        $this->emailAddress = "";
        $this->group = "";

        // A User is by default a user.
        $this->role = "user";

        // The default authentication mode is the "integrated" one.
        $this->authMode = "integrated";

        // Creation and last access date are not defined yet
        $this->creationDate = null;
        $this->lastAccessDate = null;

        // User status
        $this->status = UserConstants::STATUS_ACTIVE;

        // Initially we do not know if the user is an administrator
        $this->isAdmin = null;

        // The User is not logged in by default.
        $this->isLoggedIn = False;
    }

    /**
     * Returns the User Id
     * @return int User Id.
     */
    public function Id() {
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
     * @param string $name The name of the User.
     */
    public function setName($name) {

        // Set the name
        $this->name = $name;

        // Get the appropriate proxy
        $this->proxy = ProxyFactory::getProxy($name);

    }

    /**
     * Returns the last access date of the User.
     * @return string Last access date of the User.
     */
    public function lastAccessDate() {
        return $this->lastAccessDate;
    }

    /**
     * Sets the e-last access date of the User (and stores it in the database).
     */
    private function setLastAccessDate() {

        if ($this->lastAccessDate == null) {
            $this->lastAccessDate = date("Y-m-d H:i:s");
        }

        // Store it in the database
        $db = new DatabaseConnection();
        $sql = "UPDATE username SET last_access_date=? WHERE name=?;";
        $result = $db->connection()->Execute(
            $sql, array($this->lastAccessDate, $this->name)
        );

        if ($result === false) {
            Log::error("Could not update user $this->name in the database!");
            return false;
        }

        return true;


    }

    /**
     * Returns the role of the User.
     * @return string Role of the User.
     */
    public function role() {
        return $this->role;
    }

    /**
     * Returns the status of the User.
     * @return string Status of the User.
     */
    public function status() {
        return $this->status;
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
     * @param  string $name User name
     * @param  string $password Password (plain)
     * @return bool True if the user could be logged in, false otherwise.
     */
    public function logIn($name, $password) {

        // Set the name
        $this->setName($name);

        // Get the appropriate proxy
        if ($this->proxy == null) {
            $this->proxy = ProxyFactory::getProxy($name);
        }

        // Try authenticating the user
        $this->isLoggedIn = $this->proxy->authenticate($this->name(), $password);

        // Update the user information for a successful login
        if ($this->isLoggedIn == true) {

            // Load User information from relevant sources
            $this->load();

            // Store the last access time
            $this->setLastAccessDate();

            // Store the change in the database
            $this->save();
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
     * Checks whether the user is the administrator.
     * @return bool True if the user is the administrator, false otherwise.
     */
    public function isAdmin() {

        // If needed, retrieve.
        if ($this->isAdmin === null) {

            $db = new DatabaseConnection();
            $sql = "SELECT role FROM username WHERE name=?;";
            $res = $db->connection()->Execute($sql, array($this->name));
            if ($res === false) {
                Log::error("Could not retrieve role for user $this->name.");
                return false;
            }
            $rows = $res->GetRows();

            // Store
            $this->isAdmin = ($rows[0]['role'] == "admin");
        }

        // Return cached version
        return $this->isAdmin;
    }

    /**
     * Returns the user to which the User belongs.
     * @return string Group name.
     */
    public function userGroup() {

        // If the group is already stored in the object, return it; otherwise
        // retrieve it.
        if ($this->group == "") {
            $this->group = $this->proxy->getGroup($this->name());
        }

        return $this->group;

    }

    /**
     * Load the User data from the database.
     *
     * This function does not load the password!
     */
    public function load()
    {
        // Instantiate the database connection
        $db = new DatabaseConnection();

        // Load all information for current user
        $sql = "SELECT * FROM username WHERE name = ?;";
        $result = $db->connection()->Execute($sql, array($this->name));
        $rows = $result->GetRows();
        if (count($rows) != 1)
        {
            Log::error("Could not load data for user $this->name.");
            return;
        }
        $row = $rows[0];

        // Update the User object
        $this->id = intval($row["id"]);
        $this->emailAddress = $this->proxy->getEmailAddress($this->name);
        $this->group = $this->proxy->getGroup($this->name);
        $this->role = $row["role"];
        $this->status = $row["status"];
        // Cache the isAdmin check
        $this->isAdmin = ($this->role == "admin");
    }

    /**
     * Save current state of the User to the database.
     *
     * This is a private method since saving the User data is a
     * delicate operation.
     *
     * The stored entries are:
     *     * e-mail address
     *     * group
     *     * role
     *     * status
     *
     * @return bool True if saving was successful, false otherwise.
     */
    private function save()
    {
        // Not all info needs to be updated
        $db = new DatabaseConnection();
        $sql = "UPDATE username SET email=?, research_group=?, " .
            "role=?, status=? WHERE name=?;";
        $result = $db->connection()->Execute(
            $sql,
            array(
                $this->emailAddress,
                $this->group,
                $this->role,
                $this->status,
                $this->name)
        );

        if ($result === false) {
            Log::error("Could not update user $this->name in the database!");
            return false;
        }

        return true;
    }

};

