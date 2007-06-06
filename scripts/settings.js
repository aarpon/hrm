// form processing functions

var snitch;

function process() {
    document.forms["select"].elements["OK"].value = "OK";
    document.forms["select"].submit();
}

function release() {
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
           e.disabled = false;
           if (e.value == 'multi_XYZ')
                e.checked = true;
        }
        if (e.name == 'NumberOfChannels') {
           e.readonly = false;
        }
    }
    document.forms["select"].elements["geometry"].style.color = "black";
    document.forms["select"].elements["channels"].style.color = "black";
}

function forceGeometry() {
    release();
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
           e.disabled = true;
           e.checked = false;
        }
    }
    document.forms["select"].elements["geometry"].style.color = "grey";
}

function forceChannel() {
    release();
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'NumberOfChannels') {
           e.readonly = true;
           if (e.value == '1')
                e.checked = true;
        }
    }
    document.forms["select"].elements["channels"].style.color = "grey";
}

function seek(channel) {
    var url = "select_psf_popup.php?channel=" + channel;
    var name = "snitch";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 280";
    snitch = window.open(url, name, options);
    snitch.focus();
}

window.onunload = function() {if (snitch != null) snitch.close()};
