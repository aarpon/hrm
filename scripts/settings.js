// form processing functions

var snitch;

function process() {
    document.forms["select"].elements["OK"].value = "OK";
    document.forms["select"].submit();
}

function release() {
    var geometryFirst = true;
    var channelsFirst = true;
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
            e.disabled = false;
            if (geometryFirst) {
                e.checked = true;
                geometryFirst = false;
            }
        }
        if (e.name == 'NumberOfChannels') {
            e.disabled = false;
            if (channelsFirst) {
                e.checked = true;
                channelsFirst = false;
            }
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

function fixGeometry(geometry) {
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
            e.disabled = true;
            if (e.value == geometry)
                e.checked = true;
        }
    }
    document.forms["select"].elements["geometry"].style.color = "grey";
}

function fixChannels(channels) {
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'NumberOfChannels') {
            e.disabled = true;
            if (e.value == channels)
                e.checked = true;
        }
    }
    document.forms["select"].elements["channels"].style.color = "grey";
}

function fixGeometryAndChannels(geometry, channels) {
    release();
    fixGeometry(geometry);
    fixChannels(channels);
}

function seek(channel) {
    var url = "select_psf_popup.php?channel=" + channel;
    var name = "snitch";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 280";
    snitch = window.open(url, name, options);
    snitch.focus();
}

window.onunload = function() {if (snitch != null) snitch.close()};

function fixCoverslip( state ) {
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'CoverslipRelativePosition') {
            e.disabled = state;
        }
    }
}
