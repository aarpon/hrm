// job queue management functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

function mark() {
    for (var i = 0; i < document.forms["jobqueue"].elements.length; i++) {
        var e = document.forms["jobqueue"].elements[i];
        if (e.type == 'checkbox') {
           e.checked = true;
        }
    }
}

function unmark() {
    for (var i = 0; i < document.forms["jobqueue"].elements.length; i++) {
        var e = document.forms["jobqueue"].elements[i];
        if (e.type == 'checkbox') {
           e.checked = false;
        }
    }
}
