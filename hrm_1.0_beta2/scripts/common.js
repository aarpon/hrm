// common functions

var popup;

function clean() {
    if (popup != null) popup.close();
}

function warn(form) {
    if (confirm("Do you really want to delete this user?")) {
        form.elements["annihilate"].value = "yes";
        form.submit();
    }
}

function openWindow(url) {
    var name = "";
    //var features = "directories = no, menubar = no, scrollbars = yes, status = no, outerWidth = 800, outerHeight = 800";
    var features = "";
    var win = window.open(url, name, features);
    win.focus();
}

function openPopup(target) {
    var url = target + "_popup.php";
    var name = "popup";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 280";
    popup = window.open(url, name, options);
    popup.focus();
}
