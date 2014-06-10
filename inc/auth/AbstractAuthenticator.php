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
    \return boolean: True if authentication succeeded, false otherwise.
    */
    abstract public function authenticate($username, $password);

    /*!
    \brief Return the email address of user with given username.
    \param $username String Username for which to query the email address.
    \return String email address or NULL
    */
    abstract public function getEmailAddress($username);

    /*!
    \brief Return the group or groups the user with given username belongs to.
    \param $username String Username for which to query the group(s).
    \return String Group or Array of groups or NULL if not found.
    */
    abstract public function getGroup($username);

};
