// job queue management functions

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
