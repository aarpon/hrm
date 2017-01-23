<?php
/**
 * Validator
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Validates and in very rare cases sanitizes relevant user input.
 *
 * This **static** class checks user input through login forms, to avoid
 * attacks. Here, no SQL escape functions are (explicitly) called!
 *
 * This is the initial implementation and might require additional checks.
 * 
 * @package hrm
 */
class Validator
{

    /**
     * Generic function that checks whether the string is sanitized.
     * @param string $string A string coming from a text field or the like that
     * is not meant to be input to the database.
     * @return bool True if the string is sanitized, false otherwise.
     */
    public static function isStringSanitized($string)
    {

        // Clean the string
        $tmp = filter_var($string, FILTER_SANITIZE_STRING);

        // Check if the input passed all tests
        return (strcmp($tmp, $string) == 0);

    }

    /**
     * Validates the user name.
     *
     * The user name is forced to be lowercase. Only single words are accepted.
     * @param string $inputUserName User name input through some login form.
     * @return bool True if the user name is valid, false otherwise.
     */
    public static function isUserNameValid($inputUserName)
    {

        // Force the username to be lowercase
        $inputUserName = strtolower($inputUserName);

        // Clean the string
        $tmp = filter_var($inputUserName, FILTER_SANITIZE_STRING);

        // No spaces
        if (strstr($tmp, " ")) {
            return false;
        }

        // Set the max length to 255 to match the max length in the database
        if (strlen($tmp) > 255) {
            return false;
        }

        // Check if the input passed all tests
        return (strcmp($tmp, $inputUserName) == 0);

    }

    /**
     * Validates the e-mail address.
     *
     * It must be a valid e-mail address.
     * @param string $inputEmail E-mail input through some login form.
     * @return mixed Returns the filtered e-mail, or false.
     */
    public static function isEmailValid($inputEmail)
    {

        return (filter_var($inputEmail, FILTER_VALIDATE_EMAIL));

    }

    /**
     * Validates the group name.
     *
     * The group name can be any (sane) string and can contain blank spaces.
     * @param string $inputGroupName Group name input through some login form.
     * @return bool True if the group name is valid, false otherwise.
     */
    public static function isGroupNameValid($inputGroupName)
    {

        return self::isStringSanitized($inputGroupName);

    }

    /**
     * Validates the password.
     *
     * The password cannot contain spaces.
     * @param string $inputPassword Password input through some login form.
     * @return bool True if the group password is valid, false otherwise.
     */
    public static function isPasswordValid($inputPassword)
    {

        // Clean the string
        $tmp = filter_var($inputPassword, FILTER_SANITIZE_STRING);

        // No spaces
        if (strstr($tmp, " ")) {
            return false;
        }

        // Check if the input passed all tests
        return (strcmp($tmp, $inputPassword) == 0);

    }

    /**
     * Validates the request note for new users.
     *
     * The note can be any (sane) string.
     * @param string $inputNote Generic text input through some login form.
     * @return bool True if the group name is valid, false otherwise.
     */
    public static function isNoteValid($inputNote)
    {

        return self::isStringSanitized($inputNote);

    }

}
