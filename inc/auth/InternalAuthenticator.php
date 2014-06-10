<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Include adLDAP.php
require_once("./AbstractAuthenticator.php");

/*!
  \class	InternalAuthenticator
  \brief	Manages authentication against the internal HRM user database.

 */

class InternalAuthenticator extends AbstractAuthenticator {

    /*!
      \brief	Constructor: instantiates an InternalAuthenticator object..
                No parameters are passed to the constructor.
     */
    public function __construct( ) {

    }

    /*!
    \brief Authenticates the User with given username and password against the
    HRM user database.
    \param $username String Username for authentication.
    \param $password String Password for authentication.
    \return boolean: True if authentication succeeded, false otherwise.
    */
    public function authenticate( $username, $password ) {
        // TODO Implement
        return true;
    }

    /*!
    \brief Return the group or groups the user with given username belongs to.
    \param $username String Username for which to query the group(s).
    \return String Group or Array of groups or NULL if not found.
    */
    public function getEmailAddress( $username ) {
        $db = new DatabaseConnection();
        return $db->emailAddress($username);
    }

    /*!
      \brief	Returns the group(s) to which a user belongs.
      \param	$username	User name
      \return	One or more groups
     */
    public function getGroup($username) {
        $db = new DatabaseConnection();
        return $db->getGroup($username);
    }
}
?>
