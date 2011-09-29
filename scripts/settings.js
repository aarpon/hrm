// form processing functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

var snitch;

function process() {
    document.forms["select"].elements["OK"].value = "OK";
    document.forms["select"].submit();
}

function imageFormatProcess(e, value) {
    if ( e != "ImageFileFormat" ) {
        return;
    }
    release( );
    if ( value == "lsm-single" || value == "tiff-single") {
        fixGeometry( 'multi_XY - time' );
    } else if ( value == "tiff-series" ) {
        fixGeometryAndChannels('multi_XYZ', '1');
    } else {
        setGeometry( 'multi_XYZ' );
    }
}

function release() {
    var geometryFirst = true;
    var channelsFirst = true;
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
            if ( e.disabled == true ) {
                e.disabled = false;
                if (geometryFirst) {
                    e.checked = true;
                    geometryFirst = false;
                }
            }
        }
        if (e.name == 'NumberOfChannels') {
            if ( e.disabled == true ) {
                e.disabled = false;
                if (channelsFirst) {
                    e.checked = true;
                    channelsFirst = false;
                }
            }
        }
        
    }

    var element = document.getElementById('geometry');
    element.style.color = 'black';
    var element = document.getElementById('channels');
    element.style.color = 'black';
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

function setGeometry(geometry) {
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
        if (e.name == 'ImageGeometry') {
            if (e.value == geometry)
                e.checked = true;
        }
    }
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

    var element = document.getElementById('geometry');
    element.style.color = 'grey';
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
    var element = document.getElementById('channels');
    element.style.color = 'grey';
}

function fixGeometryAndChannels(geometry, channels) {
    release();
    fixGeometry(geometry);
    fixChannels(channels);
}

function seek(channel) {
    var url = "select_psf_popup.php?channel=" + channel;
    var name = "snitch";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 400";
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

function switchSnrMode() {
    if ( changeVisibility('cmle-snr') != "none" ) {
        window.restorationMode = 'cmle';
    }
    if ( changeVisibility('qmle-snr') != "none") {
        window.restorationMode = 'qmle';
    }
    //changeVisibility('cmle-it');
}

function switchCorrection() {
    var element = document.getElementById('PerformAberrationCorrection');
    if (element.selectedIndex == 1) {
        show('CoverslipRelativePositionDiv');
        show('AberrationCorrectionModeDiv');
        switchAdvancedCorrection();
    }
    else {
        hide('CoverslipRelativePositionDiv');
        hide('AberrationCorrectionModeDiv');
        hide('AdvancedCorrectionOptionsDiv');
    }
    switchAdvancedCorrectionScheme()
}

function switchAdvancedCorrection() {
    var element = document.getElementById('AberrationCorrectionMode');
    var chosenoption = element.options[element.selectedIndex];
    if (chosenoption.value == 'advanced') {
        show('AdvancedCorrectionOptionsDiv');
    }
    else {
        hide('AdvancedCorrectionOptionsDiv');
    }
    switchAdvancedCorrectionScheme();
}

function switchAdvancedCorrectionScheme() {
    var element = document.getElementById('AdvancedCorrectionOptions');
    var chosenoption = element.options[element.selectedIndex];
    if (chosenoption.value == 'user') {
        show('PSFGenerationDepthDiv');
    }
    else {
        hide('PSFGenerationDepthDiv');
    }
}

// Requires jQuery
function storeValues() {
    if (!window.sessionStorage) {
      return;
    }
    $('input[type=text]').each( function() {
        window.sessionStorage.setItem( $(this).attr("name"), $(this).attr("value") );
    } );
}

// Requires jQuery
function retrieveValues( ignore ) {
    if (!window.sessionStorage) {
      return;
    }
    $('input[type=text]').each( function() {
        if ( $(this).attr("name") == ignore ) {
            window.sessionStorage.removeItem( $(this).attr("name") );
        } else {
            var c = window.sessionStorage.getItem( $(this).attr("name") );
            if ( c != null ) {
                $(this).val( c );
            }
        }
    } );
}

// Requires jQuery
function deleteValues( idArray ) {
    if (!window.sessionStorage) {
      return;
    }
    $('input[type=text]').each( function() {
        window.sessionStorage.removeItem( $(this).attr("name") );
    } );
}

function storeValuesAndRedirect(page) {
    storeValues( );
    window.location = page;
}

function deleteValuesAndProcess() {
    deleteValues();
    process();
}

function deleteValuesAndRedirect(page) {
    deleteValues();
    window.location = page;
}
