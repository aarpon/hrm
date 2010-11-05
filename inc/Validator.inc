<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

//!-----------------------------------------------------------------------------
// @class    Validator (static class)
// @desc     Validates and sanitizes relevant user input
//           Here, not SQL escape functions are called. Those are taken care
//           of in the Database class.
//!-----------------------------------------------------------------------------

Class Validator {

  // Generic function (private) that checks whether the string is sanitized
  private static function isStringSanitized( $string ) {

    // Clean the string
    $tmp = filter_var( $string, FILTER_SANITIZE_STRING );

    // Check if the input passed all tests
    return ( strcmp( $tmp, $string ) == 0 );

  }
  
  // Validates the user name
  public static function isUserNameValid( $inputUserName ) {

    // Clean the string
    $tmp = filter_var( $inputUserName, FILTER_SANITIZE_STRING );

    // Only lowercase characters
    $tmp = strtolower( $inputUserName );

    // No spaces
    if ( strstr( $tmp, " " ) ) {
        return false;
    }

    // Check if the input passed all tests
    return ( strcmp( $tmp, $inputUserName ) == 0 );

  }
		
  // Validates the email address
  public static function isEmailValid( $inputEmail ) {

    return ( filter_var( $inputEmail, FILTER_VALIDATE_EMAIL ) );

  }

  // Validates the group name (must simply be a valid string)
  public static function isGroupNameValid( $inputGroupName ) {

    return self:: isStringSanitized( $inputGroupName );

  }

  // Validates the password
  public static function isPasswordValid( $inputPassword ) {

    // Clean the string
    $tmp = filter_var( $inputPassword, FILTER_SANITIZE_STRING );

    // No spaces
    if ( strstr( $tmp, " " ) ) {
        return false;
    }

    // Check if the input passed all tests
    return ( strcmp( $tmp, $inputPassword ) == 0 );

  }

  // Validates the request note for new users (must simply be a valid string)
  public static function isNoteValid( $inputNote ) {

    return self:: isStringSanitized( $inputNote );

  }
  
};

?>