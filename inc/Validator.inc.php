<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
    \class  Validator
    \brief  Validates and in very rare cases sanitizes relevant user input

    This <b>static</b> class checks user input through login forms, to avoid
    attacks. Here, no SQL escape functions are (explicitly) called!

    This is the initial implementation and might require additional checks.
*/
class Validator {

  /*!
    \brief  Generic private function that checks whether the string is sanitized
    \param  $string A string coming from a text field or the like that is not
            meant to be input to the database.
  */
  private static function isStringSanitized($string) {

    // Clean the string
    $tmp = filter_var($string, FILTER_SANITIZE_STRING);

    // Check if the input passed all tests
    return (strcmp($tmp, $string) == 0);

  }

  /*!
    \brief  Validates the user name
    \param  $inputUserName  User name input through some login form

    The user name is forced to be lowercase. Only single words are accepted.
  */
  public static function isUserNameValid($inputUserName) {

    // Force the username to be lowercase
    $inputUserName = strtolower($inputUserName);

    // Clean the string
    $tmp = filter_var($inputUserName, FILTER_SANITIZE_STRING);

    // No spaces
    if (strstr($tmp, " ")) {
        return false;
    }

    // Check if the input passed all tests
    return (strcmp($tmp, $inputUserName) == 0);

  }

  /*!
    \brief  Validates the e-mail address
    \param  $inputEmail E-mail input through some login form

    It must be a valid e-mail address.
  */
  public static function isEmailValid($inputEmail) {

    return (filter_var($inputEmail, FILTER_VALIDATE_EMAIL));

  }

  /*!
    \brief  Validates the group name
    \param  $inputGroupName Group name input through some login form

    The group name can be any (sane) string and can contain blank spaces.
  */
  public static function isGroupNameValid($inputGroupName) {

    return self::isStringSanitized($inputGroupName);

  }

  /*!
    \brief  Validates the password
    \param  $inputPassword  Password input through some login form

    The password cannot contain spaces.
  */
  public static function isPasswordValid($inputPassword) {

    // Clean the string
    $tmp = filter_var($inputPassword, FILTER_SANITIZE_STRING);

    // No spaces
    if (strstr($tmp, " ")) {
        return false;
    }

    // Check if the input passed all tests
    return (strcmp($tmp, $inputPassword) == 0);

  }

  /*!
    \brief  Validates the request note for new users
    \param  $inputNote  Generic text input through some login form

    The note can be any (sane) string.
  */
  public static function isNoteValid($inputNote) {

    return self::isStringSanitized($inputNote);

  }

};

?>