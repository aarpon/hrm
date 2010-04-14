// user management functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

function disableUsers() {
    document.forms["user_management"].elements["action"].value = "disable";
    document.forms["user_management"].submit();
}

function enableUsers() {
    document.forms["user_management"].elements["action"].value = "enable";
    document.forms["user_management"].submit();
}
