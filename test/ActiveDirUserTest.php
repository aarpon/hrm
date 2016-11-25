<?php

/**
 * IntegratedUserTest.php
 *
 * This test suite checks the User class and database table.
 */

// Bootstrap
use hrm\user\UserConstants;
use hrm\user\UserManager;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

/*
    Configuration file for the test. Copy:

        ./test_active_dir.conf.sample

    to:

        ./test_active_dir.conf

    and edit it.
*/

require_once dirname(__FILE__) . '/./test_active_dir.conf';


class ActiveDirUserTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var string userID: the ID we will be using in the test,
     *                     in case the database already contains
     *                     data.
     */
    protected $userID;

    /**
     * Test instantiation of the \hrm\User class
     */
    public function testInstantiation()
    {
        $user = new UserV2();
        $this->assertTrue($user !== null);
    }

    /**
     * Test login before the User is created
     */
    public function testLoginBeforeCreation()
    {

        global $TEST_ACTIVE_DIR_SETTINGS;

        // New User
        $user = new UserV2();
        $user->setName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        // Log in
        $this->assertTrue($user->logIn($TEST_ACTIVE_DIR_SETTINGS["password"]));

        // Store the User
        $this->assertTrue($user);

        // Check that the User now exists
        $this->assertTrue(
            UserManager::findUserByName(
                $TEST_ACTIVE_DIR_SETTINGS["username"]) != null);
    }

    /**
     * Test adding a user to the database.
     */
    public function testAddUser()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Create a new user
        $this->assertTrue(UserManager::createUser(
            $TEST_ACTIVE_DIR_SETTINGS["username"],
            UserManager::generateRandomPlainPassword(),
            $TEST_ACTIVE_DIR_SETTINGS["email"],
            $TEST_ACTIVE_DIR_SETTINGS["group"],
            $TEST_ACTIVE_DIR_SETTINGS["institution"],
            "active_dir",
            UserConstants::ROLE_ADMIN,
            UserConstants::STATUS_ACTIVE));
    }

    /**
     * Test adding a user with duplicate name to the database.
     *
     * The name column is unique, therefore this must fail.
     *
     */
    public function testDuplicateUser()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Create a new user with the same name as an existing one
        $this->assertFalse(UserManager::createUser(
            $TEST_ACTIVE_DIR_SETTINGS["username"],
            UserManager::generateRandomPlainPassword(),
            $TEST_ACTIVE_DIR_SETTINGS["email"],
            $TEST_ACTIVE_DIR_SETTINGS["group"],
            $TEST_ACTIVE_DIR_SETTINGS["institution"],
            "active_dir",
            UserConstants::ROLE_ADMIN,
            UserConstants::STATUS_ACTIVE));
    }

    /**
     * Test retrieving the added user
     */
    public function testRetrieveUserByName()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);
        $this->assertTrue($user != null);
    }

    /**
     * Test logging in a given User.
     */
    public function testLoginUser()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        /** @var UserV2 $user */
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        # Now try logging the User in
        $this->assertTrue($user->logIn($TEST_ACTIVE_DIR_SETTINGS["password"]) === true);
    }

    /**
     * Several tests related to the User's e-mail address.
     */
    public function testUserLoadedProperlyOnRetrieval()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        # Make sure the User is loaded properly
        $this->assertTrue($user->name() == $TEST_ACTIVE_DIR_SETTINGS["username"]);
        $this->assertTrue($user->emailAddress() == $TEST_ACTIVE_DIR_SETTINGS["email_from_activedir"]);
        $this->assertTrue($user->group() == $TEST_ACTIVE_DIR_SETTINGS["group_from_activedir"]);
        $this->assertTrue($user->authenticationMode() == "active_dir");
        $this->assertTrue($user->institution() == $TEST_ACTIVE_DIR_SETTINGS["institution"]);
        $this->assertTrue($user->role() == UserConstants::ROLE_ADMIN);
    }

    /**
     * Test changing the role of the User.
     */
    public function testChangingUserRole()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        # Make the User an admin
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_ADMIN)
        );

        # Make sure the User is an admin
        $user->load();
        $this->assertTrue($user->role() == UserConstants::ROLE_ADMIN);

        # Make the User a manager
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_MANAGER)
        );

        # Make sure the User is a manager
        $user->load();
        $this->assertTrue($user->role() == UserConstants::ROLE_MANAGER);

        # Make the User a superuser
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_SUPERUSER)
        );

        # Make sure the User is a superuser
        $user->load();
        $this->assertTrue($user->role() == UserConstants::ROLE_SUPERUSER);

        # Make the User a user
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_USER)
        );

        # Make sure the User is a user
        $user->load();
        $this->assertTrue($user->role() == UserConstants::ROLE_USER);
    }

    /**
     * Test changing the User e-mail address.
     */
    public function testChangingUserEmailAddress()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        $this->assertFalse(UserManager::canModifyEmailAddress($user));
        $this->assertFalse(UserManager::canModifyGroup($user));
        $this->assertFalse(UserManager::userMustExistBeforeFirstAuthentication($user));
    }

    /**
     * Test changing the status of the User.
     */
    public function testChangingUserStatus()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Find and load the User.
        $user = UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]);

        # Accept the User
        $this->assertTrue(
            UserManager::acceptUser($user->name())
        );

        # Make sure the User is active
        $user->load();
        $this->assertTrue($user->status() == UserConstants::STATUS_ACTIVE);

        # Enable the User
        $this->assertTrue(
            UserManager::enableUser($user->name())
        );

        # Make sure the User is active
        $user->load();
        $this->assertTrue($user->status() == UserConstants::STATUS_ACTIVE);

        # Disable the User
        $this->assertTrue(
            UserManager::disableUser($user->name())
        );

        # Make sure the User is disabled
        $user->load();
        $this->assertTrue($user->status() == UserConstants::STATUS_DISABLED);
    }

    /**
     * Delete the test user
     */
    public function testDeleteUser()
    {
        global $TEST_ACTIVE_DIR_SETTINGS;

        # Delete the user
        $this->assertTrue(UserManager::deleteUser($TEST_ACTIVE_DIR_SETTINGS["username"]));

        # Search for it (should return null)
        $this->assertTrue(UserManager::findUserByName($TEST_ACTIVE_DIR_SETTINGS["username"]) == null);
    }
}
