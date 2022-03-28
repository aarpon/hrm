// form processing functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

var snitch;

/**
   Requires jQuery.

   Sets the value "OK" to the hidden input element in the page form. Used
   for submitting a form via a normal button.
 */
function process() {
    var el = $("form input[name='OK']:hidden");
    if (null === el) {
        return;
    }
    var fr = $("form#select");
    if (null === fr) {
        return;
    }
    if ($.contains(fr.get(0), el.get(0))) {
        el.val("OK");
        fr.submit();
    }
}

function release() {
    var channelsFirst = true;
    for (var i = 0; i < document.forms["select"].elements.length; i++) {
        var e = document.forms["select"].elements[i];
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
    element = document.getElementById('channels');
    element.style.color = 'black';
}

function seek(channel,type) {
    var url = "select_" + type + "_popup.php?channel=" + channel;
    var name = "snitch";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 400";
    snitch = window.open(url, name, options);
    snitch.onload = function () {
        var head = $(snitch.document).find("head");
        // Retrieve stored theme
        var css_title = localStorage.getItem('user_hrm_theme');
        if (null === css_title) {

            // Set to default
            css_title = "dark";

            // Store default in the session storage
            localStorage.setItem('user_hrm_theme', css_title);
        }
        // Get links in <head>
        var links = head.find("link");

        $.each(links, function (key, value) {
            if (value.rel.indexOf("stylesheet") !== -1 &&
                (value.title.toLowerCase() === "dark" ||
                    value.title.toLowerCase() === "light")) {

                // Disable stylesheet
                value.disabled = true;
                value.rel = "alternate stylesheet";

                // Enable the selected one
                if (value.title.toLowerCase() === css_title) {

                    // Enable selected stylesheet
                    value.disabled = false;
                    value.rel = "stylesheet";

                    // Store selection in the session storage
                    localStorage.setItem('user_hrm_theme', css_title);
                }
            }
        });
    };
    snitch.focus();
}

function hpcReset() {
    var select = document.getElementById("select");
    select.elements["hpc"].value = "";
}

function flatfieldReset() {
    var form = document.getElementById("stitch");
    form.elements["StitchVignettingFlatfield"].value = "";
}

function darkframeReset() {
    var form = document.getElementById("stitch");
    form.elements["StitchVignettingDarkframe"].value = "";
}


window.onunload = function() {if (snitch != null) snitch.close()};

function switchSnrMode(algorithm, channel) {    
    if (algorithm.value === "cmle") {
        $('#cmle-snr-' + channel).show();
        $('#gmle-snr-' + channel).hide();
        $('#qmle-snr-' + channel).hide();
        $('#skip-snr-' + channel).hide();
    } else if (algorithm.value === "gmle") {
        $('#cmle-snr-' + channel).hide();
        $('#gmle-snr-' + channel).show();
        $('#qmle-snr-' + channel).hide();
        $('#skip-snr-' + channel).hide();
    } else if (algorithm.value === "qmle") {                
        $('#cmle-snr-' + channel).hide();
        $('#gmle-snr-' + channel).hide();
        $('#qmle-snr-' + channel).show();
        $('#skip-snr-' + channel).hide();
    } else if (algorithm.value === "skip") {
        $('#cmle-snr-' + channel).hide();
        $('#gmle-snr-' + channel).hide();
        $('#qmle-snr-' + channel).hide();
        $('#skip-snr-' + channel).show();
    }
}

// Requires jQuery
function switchColocMode() {
    if ($('#ColocAnalysis').val() == 1) {
        $('#ColocChannelSelectionDiv').show();
        $('#ColocCoefficientSelectionDiv').show();
        $('#ColocThresholdSelectionDiv').show();
        $('#ColocMapSelectionDiv').show();
    } else {
        $('#ColocChannelSelectionDiv').hide();
        $('#ColocCoefficientSelectionDiv').hide();
        $('#ColocThresholdSelectionDiv').hide();
        $('#ColocMapSelectionDiv').hide();
    }
}

function switchTStabilizationMode() {
    if ($('#TStabilization').val() == 1) {
        $('#TStabilizationMethodDiv').show();
        $('#TStabilizationRotationDiv').show();
        $('#TStabilizationCroppingDiv').show();
    } else {
        $('#TStabilizationMethodDiv').hide();
        $('#TStabilizationRotationDiv').hide();
        $('#TStabilizationCroppingDiv').hide();
    }
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
    // Select fields
    $("select").each( function() {
        window.sessionStorage.setItem( $(this).attr("name"), $(this).val());
    } );
    // Text input
    $("input[type=text]").each( function() {
        // IE8 work-around
        if ( $(this).val() != "" ) {
            window.sessionStorage.setItem( $(this).attr("id"),  $(this).val());
        } else {
            window.sessionStorage.setItem( $(this).attr("id"), "" );
        }
    } );
    // Radio  buttons
    $("input[type=radio]").each( function() {
        if ( $(this).prop("checked") == true ) {
            window.sessionStorage.setItem( $(this).attr("id"), '1' );
        } else {
            window.sessionStorage.setItem( $(this).attr("id"), '0' );
        }
    } );
}

// Requires jQuery
function retrieveValues( ignore ) {
    if (!window.sessionStorage) {
      return;
    }
    // Make sure 'ignore' is an array if not undefined
    if ( !( ignore === undefined ) ) {
        if ( !( ignore instanceof Array ) ) {
            ignore = new Array( ignore );
        }
    }
    // Select fields
    $("select").each( function() {
        if ( jQuery.inArray( $(this).attr("name"), ignore ) != -1 ) {
            window.sessionStorage.removeItem( $(this).attr("name") );
        } else {
            var c = window.sessionStorage.getItem( $(this).attr("name") );
            if ( c != null ) {
                $(this).val( c );
            }
        }
    } );
    // Text input
    $("input[type=text]").each( function() {
        if ( jQuery.inArray( $(this).attr("name"), ignore ) != -1 ) {
            window.sessionStorage.removeItem( $(this).attr("id") );
        } else {
            var c = window.sessionStorage.getItem( $(this).attr("id") );
            if ( c != null ) {
                $(this).val( c );
            } else {
                $(this).val( "" );
            }
        }
    } );
    // Radio buttons
    $("input[type=radio]").each( function() {
        if ( jQuery.inArray( $(this).attr("name"), ignore ) != -1 ) {
            window.sessionStorage.removeItem( $(this).attr("id") );
        } else {
            var c = window.sessionStorage.getItem( $(this).attr("id") );
            if ( c == '1' ) {
                $(this).prop('checked', true);
            } else {
                $(this).prop('checked', false);
            }
        }
    } );
}

// Requires jQuery
function deleteValues( idArray ) {
    if (!window.sessionStorage) {
      return;
    }
    $("select").each(
        function() {
            window.sessionStorage.removeItem( $(this).attr("name") );
        } );
    $( $.merge( $("input[type=text]"), $("input[type=radio]") ) ).each(
        function() {
            window.sessionStorage.removeItem( $(this).attr("id") );
        } );
}

function storeValuesAndRedirect(page) {
    storeValues( );
    window.location = page;
}

function storeValuesAndRedirectExtern(page) {
    storeValues( );
    var win = window.open(page, "", "");
    win.focus();
}

function deleteValuesAndProcess() {
    deleteValues();
    process();
}

function deleteValuesAndRedirect(page) {
    deleteValues();
    window.location = page;
}


// ------------- Functions for importing parameters. ----------------


function hu2template(type) {

    control = document.getElementById('actions').innerHTML;
    action = 'upload';
    upsubmitted = false;

    if (type == "micr") {
        var msg = 'Upload a Huygens microscopy template '
            + ' (extension <b>.hgsm</b>).'
    } else if (type == "decon") {
        var msg = 'Upload a Huygens deconvolution template '
            + ' (extension <b>.hgsd</b>).'
    } else {
        return;
    }
    
    changeDiv('actions','');
    changeDiv('upMsg', msg 
              + '<div id="up_form">'
              + '<form id="uploadForm" enctype="multipart/form-data" '
              + 'action="?folder=src&upload=1" method="POST"'
              + 'onsubmit="return confirmUpload()">'
              + '<input type="hidden" name="uploadForm" value="1"> '
              + '<div id="upload_list">'
              + '<div id="upfile_0"></div>'
              + '</div>'
              + '<div id="buttonUpload">'
              + '<input name="huTotemplate" type="submit" value="" '
              + 'class="icon apply" '
              + 'onmouseover="Tip(\'Upload selected files\')" '
              + 'onmouseout="UnTip()"/>'
              + '<input type="button" class="icon cancel" onclick="UnTip(); '
              + 'cancelFileSelection()" '
              + 'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/></div>'
              + ' </form>'
              + '</div>');

    addTemplateFile();

    changeDiv('upfile', '');
}

function image2template(selectedFiles) {
    
    control = document.getElementById('actions').innerHTML;

    changeDiv('upMsg', 'Select a file to create an image template from');
    changeDiv('actions',
              '<input type="hidden" name="imageTotemplate" /> '
              +  createImageSelection((selectedFiles))
              + '<div id="buttonUpload">'
              + '<input name="submit" type="submit" value="" '
              + 'class="icon apply" onclick="UnTip(); '
              + 'onmouseover="Tip(\'Submit\')" onmouseout="UnTip()"/>'
              + '<input type="button" class="icon cancel" onclick="UnTip();'
              + 'cancelFileSelection()" '
              + 'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/>'
              + '</div>');

    changeDiv('upfile', '');
}

function createImageSelection(fileList) {

    html = '<div><select id="fileselection" name="fileselection" width="253" '
        + 'class="selection">'
        + '<option>Choose a file</option>';

    for (i = 0; i < fileList.length; i++) {
        html += '<option>' + fileList[i] + '</option>';
    }

    html += '</select></div>';
    
    return html;
}


function addTemplateFile() {
    content = '<div class="inputFile" name="inputFile">'
        + '<input type="file" class="selection" name="upfile[]" size="3">'
        + '</div>';

    changeDiv('upfile_0', content);
}

function cancelFileSelection() {
    action = '';
    changeDiv('actions', control);
    changeDiv('upMsg', '');
}
