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

class IntegratedUserTest extends PHPUnit_Framework_TestCase
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
     * Test adding a user to the database.
     */
    public function testAddUser()
    {
        # Create a new user
        $this->assertTrue(UserManager::createUser("TestUser", "TestPassword",
            "test@email.com", "TestGroup", 1, "integrated",
            UserConstants::ROLE_MANAGER, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Test adding a user with duplicate name to the database.
     *
     * The name column is unique, therefore this must fail.
     *
     */
    public function testDuplicateUser()
    {
        # Create a new user with the same name as an existing one
        $this->assertFalse(UserManager::createUser("TestUser", "TestPassword",
            "test@email.com", "TestGroup", 1, "integrated",
            UserConstants::ROLE_MANAGER, UserConstants::STATUS_ACTIVE));
    }

    /**
     * Test retrieving the added user
     */
    public function testRetrieveUserByName()
    {
        # Find and load the User.
        $user = UserManager::findUserByName("TestUser");
        $this->assertTrue($user != null);
    }

    /**
     * Test logging in a given User.
     */
    public function testLoginUser()
    {
        # Find and load the User.
        /** @var UserV2 $user */
        $user = UserManager::findUserByName("TestUser");

        # Now try logging the User in
        $this->assertTrue($user->logIn("TestPassword") === true);
    }

    /**
     * Several tests related to the User's e-mail address.
     */
    public function testUserLoadedProperlyOnRetrieval()
    {
        # Find and load the User.
        $user = UserManager::findUserByName("TestUser");

        # Make sure the User is loaded properly
        $this->assertTrue($user->name() == "TestUser");
        $this->assertTrue($user->emailAddress() == "test@email.com");
        $this->assertTrue($user->group() == "TestGroup");
        $this->assertTrue($user->authenticationMode() == "integrated");
        $this->assertTrue($user->institution_id() == 1);
        $this->assertTrue($user->role() == UserConstants::ROLE_MANAGER);
    }

    /**
     * Test changing the role of the User.
     */
    public function testChangingUserRole()
    {
        # Find and load the User.
        $user = UserManager::findUserByName("TestUser");

        # Make the User a super admin
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_SUPERADMIN)
        );

        # Make sure the User is an admin
        $user = UserManager::reload($user);
        $this->assertTrue($user->role() == UserConstants::ROLE_SUPERADMIN);

        # Make the User an admin
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_ADMIN)
        );

        # Make sure the User is an admin
        $user = UserManager::reload($user);
        $this->assertTrue($user->role() == UserConstants::ROLE_ADMIN);

        # Make the User a manager
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_MANAGER)
        );

        # Make sure the User is a manager
        $user = UserManager::reload($user);
        $this->assertTrue($user->role() == UserConstants::ROLE_MANAGER);

        # Make the User a superuser
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_SUPERUSER)
        );

        # Make sure the User is a superuser
        $user = UserManager::reload($user);
        $this->assertTrue($user->role() == UserConstants::ROLE_SUPERUSER);

        # Make the User a user
        $this->assertTrue(
            UserManager::setRole($user->name(), UserConstants::ROLE_USER)
        );

        # Make sure the User is a user
        $user = UserManager::reload($user);
        $this->assertTrue($user->role() == UserConstants::ROLE_USER);
    }

    /**
     * Test changing the User e-mail address.
     */
    public function testChangingUserEmailAddress()
    {
        # Find and load the User.
        $user = UserManager::findUserByName("TestUser");

        $this->assertTrue(UserManager::canModifyEmailAddress($user));
        $this->assertTrue(UserManager::canModifyGroup($user));
    }

    /**
     * Test changing the status of the User.
     */
    public function testChangingUserStatus()
    {
        # Find and load the User.
        $user = UserManager::findUserByName("TestUser");

        # Accept the User
        $this->assertTrue(
            UserManager::acceptUser($user->name())
        );

        # Make sure the User is active
        $user = UserManager::reload($user);
        $this->assertTrue($user->status() == UserConstants::STATUS_ACTIVE);

        # Enable the User
        $this->assertTrue(
            UserManager::enableUser($user->name())
        );

        # Make sure the User is active
        $user = UserManager::reload($user);
        $this->assertTrue($user->status() == UserConstants::STATUS_ACTIVE);

        # Disable the User
        $this->assertTrue(
            UserManager::disableUser($user->name())
        );

        # Make sure the User is disabled
        $user = UserManager::reload($user);
        $this->assertTrue($user->status() == UserConstants::STATUS_DISABLED);
    }

    /**
     * Delete the test user
     */
    public function testDeleteUser()
    {
        # Delete the user
        $this->assertTrue(UserManager::deleteUser("TestUser"));

        # Search for it (should return null)
        $this->assertTrue(UserManager::findUserByName("TestUser") == null);
    }
}
