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
    public static function isStringSanitized(string $string): bool
    {
        // Clean the string
        $tmp = htmlspecialchars($string);

        // Check if the input passed all tests
        return (strcmp($tmp, $string) == 0);
    }

    /**
     * Validates the username.
     *
     * The username is forced to be lowercase. Only single words are accepted.
     * @param string $inputUserName Username input through some login form.
     * @return bool True if the username is valid, false otherwise.
     */
    public static function isUserNameValid(string $inputUserName): bool
    {
        // Force the username to be lowercase
        $inputUserName = strtolower($inputUserName);

        // Clean the string
        $tmp = htmlspecialchars($inputUserName);

        // No spaces
        if (str_contains($tmp, ' ')) {
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
     * @return bool True if the e-mail is valid, false otherwise.
     */
    public static function isEmailValid(string $inputEmail): bool
    {
        return (filter_var($inputEmail, FILTER_VALIDATE_EMAIL) != false);
    }

    /**
     * Validates the group name.
     *
     * The group name can be any (sane) string and can contain blank spaces.
     * @param string $inputGroupName Group name input through some login form.
     * @return bool True if the group name is valid, false otherwise.
     */
    public static function isGroupNameValid(string $inputGroupName): bool
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
    public static function isPasswordValid(string $inputPassword): bool
    {
        // Make sure the password is not empty
        if ($inputPassword == '') {
            return false;
        }

        // Clean the string
        $tmp = htmlspecialchars($inputPassword);

        // No spaces
        if (str_contains($tmp, ' ')) {
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
    public static function isNoteValid(string $inputNote): bool
    {
        return self::isStringSanitized($inputNote);
    }
}
