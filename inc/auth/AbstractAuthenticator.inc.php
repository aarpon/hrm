<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \class	AbstractAuthenticator
  \brief	Abstract base Authenticator class that provides an interface for
            concrete classes to implement.

  The User class expects concrete Authenticator classes to extend this class and
  implement all of its abstract methods.
 */
abstract class AbstractAuthenticator {

    /*!
    \brief Authenticates the User with given username and password.
    \param $username String Username for authentication.
    \param $password String Password for authentication.
    \return bool True if authentication succeeded, false otherwise.
    */
    abstract public function authenticate($username, $password);

    /*!
    \brief Return the email address of user with given username.
    \param $username String Username for which to query the email address.
    \return String email address or NULL
    */
    abstract public function getEmailAddress($username);

    /*!
    \brief Return the group the user with given username belongs to.
    \param $username String Username for which to query the group.
    \return String Group or "" if not found.
    */
    abstract public function getGroup($username);

    /*!
    \brief Checks whether a request from the user with given name was
           accepted by the administrator.

    Please notice that the base implementation always returns true. Re-
    implement if needed!

    \param $username String Username for which to query the accepted status.
    \return bool True if the user was accepted, false otherwise.
    */
    public function isAccepted($username = "ignored") {
        return true;
    }

    /*!
    \brief Checks whether the user with given name was suspended by the
           administrator.

    Please notice that the base implementation always returns false. Re-
    implement if needed!

    \param $username String Username for which to query the suspended status.
    \return bool True if the user was suspended, false otherwise.
    */
    public function isSuspended($username = "ignored") {
        return false;
    }

    /*!
    \brief Checks whether the user with given name is active.

    Please notice that the base implementation always returns true. Re-
    implement if needed!

    \param $username String Username for which to query the active status.
    \return bool True if the user is active, false otherwise.
    */
    public function isActive($username) {
        return $this->isAccepted($username) & !$this->isSuspended($username);
    }

};
