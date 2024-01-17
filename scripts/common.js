// common functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

var popup;
var generated = [];
var debug = '';
var control = '';
var filemenu = '<div class="inputFile" name="inputFile"><input type="file" name="upfile" size="30" accept=" .HGSM,.hgsm" ></div>';

function clean() {
    if (popup != null) {
        popup.close();
    }
}

function warn(form, msg, value) {
    // Value is an optional parameter. It is the index of a selected item in
    // the form element related to the 'delete' icon. If it is not defined,
    // the confirm dialog will show no matter what. If it is, it can be either
    // -1 (and then the function won't do anything) or anything else, and then
    // the confirm dialog will show up.
    if (value === undefined) {
      value = 0;
    }
    if (value == -1) {
      return;
    }
    if (confirm(msg)) {
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
    var options = "directories = no, menubar = no, status = no, width = 560, height = 380";
    popup = window.open(url, name, options);
    popup.focus();
}

function openTool(url) {
    var name = "popupTool";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 480";
    popup = window.open(url, name, options);
    popup.focus();
}

function changeDiv(div, html) {
    // try to update the inner HTML for a specific <div> element
    try { document.getElementById(div).innerHTML= html; } catch(err) {}
}


function SetOpacity(elem, opacityAsInt)
{
    var opacityAsDecimal = opacityAsInt;

    if (opacityAsInt > 100)
        opacityAsInt = opacityAsDecimal = 100;
    else if (opacityAsInt < 0)
        opacityAsInt = opacityAsDecimal = 0;

    opacityAsDecimal /= 100;
    if (opacityAsInt < 1)
        opacityAsInt = 1; // IE7 bug, text smoothing cuts out if 0

    elem.style.opacity = (opacityAsDecimal);
    // This doesn't work very well for IE, at least with div's. Maybe it works
    // with images.
    elem.style.filter  = "alpha(opacity=" + 100 + ")";
}

function FadeOpacity(elem, fromOpacity, toOpacity, time, fps)
{
    try {
        var steps = Math.ceil(fps * (time / 1000));
        var delta = (toOpacity - fromOpacity) / steps;

        FadeOpacityStep(elem, 0, steps, fromOpacity,
                delta, (time / steps));
    } catch (err) {}
}

function FadeOpacityStep(elem, stepNum, steps, fromOpacity,
        delta, timePerStep)
{
    var e = document.getElementById(elem);
    SetOpacity(e,
            Math.round(parseInt(fromOpacity) + (delta * stepNum)));

    if (stepNum < steps)
        setTimeout("FadeOpacityStep('" + elem + "', " + (stepNum+1)
                + ", " + steps + ", " + fromOpacity + ", "
                + delta + ", " + timePerStep + ");",
                timePerStep);
}

// This function calls for a div replacement only if it hasn't been replaced
// yet. This is flagged with a window.DivCondition variable.
function smoothChangeDivCond(condition, div, html, time) {

    if (window.divCondition == condition) return;

    smoothChangeDiv(div, html, time);
    window.divCondition = condition;

}

function smoothChangeDiv(div, html, time) {

    var tout = time / 4.0;
    var tin = 3 * time / 4.0;
    var t2 = tout * 1.05;
    var t3 = tout * 1.05;

    var elem = document.getElementById(div);
    if (null === elem) {
        return;
    }

    if (undefined === elem.style.opacity) {
        // fading a <div> in IE doesn't work very well.
        // FadeOpacity(div, 100, 0, tout, 12);
        changeDiv(div,html);
    } else {
        FadeOpacity(div, 100, 0, tout, 12);
        setTimeout(changeDiv, t2, div, html);
        setTimeout(FadeOpacity, t3, div, 0, 100, tin, 12);
    }
}

function isChromaticTableEmpty( ) {
    var allEmpty = true;

    var tableTag   = "ChromaticAberrationTable";
    var channelTag = "ChromaticAberration";

    table = document.getElementById(tableTag);

    channelCnt = table.rows.length - 1;
    componentCnt = table.rows[0].cells.length - 1;

    for (var chan = 0; chan < channelCnt; chan++) {
        for (var component = 0; component < componentCnt; component++) {
            var id = channelTag + "Ch";
            id = id.concat(chan);
            id += "_";
            id = id.concat(component);
            inputElement = document.getElementById(id);

            if (inputElement.value != "") {
                allEmpty = false;
                break;
            }
        }
        if (allEmpty == false) {
            break;
        }
    }

    return allEmpty;
}

function initChromaticChannelReference() {

    var tableTag   = "ChromaticAberrationTable";

    table = document.getElementById(tableTag);

    channelCnt = table.rows.length - 1;

    if (channelCnt == 1) {
        return;
    }

    emptyTable = isChromaticTableEmpty();

    if (emptyTable == true) {
        chan = 0;
    } else {
        chan = searchChromaticChannelReference();
    }

    clearChromaticChannelReference();
    setChromaticChannelReference( chan );
}

function searchChromaticChannelReference( ) {
    var tableTag   = "ChromaticAberrationTable";
    var channelTag = "ChromaticAberration";

    table = document.getElementById(tableTag);

    channelCnt = table.rows.length - 1;
    componentCnt = table.rows[0].cells.length - 1;

    for (var chan = 0; chan < channelCnt; chan++) {
        var isReference = false;

        for (var component = 0; component < componentCnt; component++) {
            var id = channelTag + "Ch";
            id = id.concat(chan);
            id += "_";
            id = id.concat(component);
            inputElement = document.getElementById(id);

            if (inputElement.value != 0 && component < 4) {
                break;
            }
            if (inputElement.value == 1 && component == 4) {
                isReference = true;
                break;
            }
        }
        if (isReference == true) {
            break;
        }
    }

    if (isReference == true) {
        return chan;
    }
}

// The reference always contains values 0, 0, 0, 0, 1. */
function setChromaticChannelReference( chan ) {
    var tableTag   = "ChromaticAberrationTable";
    var channelTag = "ChromaticAberration";

    table = document.getElementById(tableTag);

    componentCnt = table.rows[0].cells.length - 1;

    for (var component = 0; component < componentCnt; component++) {
        var id = channelTag + "Ch";
        id = id.concat(chan);
        id += "_";
        id = id.concat(component);
        
        inputElement = document.getElementById(id);
        inputElement.readOnly = true;
        inputElement.style.backgroundColor="#888";

        if (component == 4) {
            inputElement.value = 1;
        } else {
            inputElement.value = 0;
        }
    }
    
    var id = channelTag + "DiscardOtherCh" + chan;
    buttonElement = document.getElementById(id);
    buttonElement.setAttribute('hidden', true);
    
    // todo: make sure it is "reset" in the case of 14 parameters.
    
    var tag = "ReferenceChannel";
    inputElement = document.getElementById(tag);
    inputElement.value = chan;
}

function clearChromaticChannelReference( ) {
    var tableTag   = "ChromaticAberrationTable";
    var channelTag = "ChromaticAberration";

    table = document.getElementById(tableTag);

    channelCnt = table.rows.length - 1;
    componentCnt = table.rows[0].cells.length - 1;

    for (var chan = 0; chan < channelCnt; chan++) {

        // If there are 14 parameters known for this chanenl don't make
        // it editable.
        var id = channelTag + "DiscardOtherCh";
        id = id.concat(chan);
        inputElement = document.getElementById(id);
        var nonEditable14params = !inputElement.getAttribute('hidden');
        
        for (var component = 0; component < componentCnt; component++) {
            var id = channelTag + "Ch";
            id = id.concat(chan);
            id += "_";
            id = id.concat(component);
            inputElement = document.getElementById(id);

            if (nonEditable14params) {
                inputElement.readOnly = true;
                inputElement.style.backgroundColor="#666";
            } else {
                inputElement.readOnly = false;
                inputElement.style.backgroundColor="";
            }
        }
    }
}

function changeChromaticChannelReference(selectObj) {
    clearChromaticChannelReference( );
    setChromaticChannelReference( selectObj.value );
}

function editChromaticChannelWith14Params(channel) {
    // Hide the discard button.
    var tag = "ChromaticAberrationDiscardOtherCh" + channel;
    butElement = document.getElementById(tag);
    butElement.setAttribute('hidden', true);
    clearChromaticChannelReference( );

    scaleElement = document.getElementById("ChromaticAberrationScaleTitle");
    scaleElement.innerHTML = 'Scale<br/>(ratio)';

    // Round the values to 5 decimals. This both makes it easier to edit and
    // ensures the new values are saved properly. 
    var tableTag   = "ChromaticAberrationTable";
    var channelTag = "ChromaticAberration";
    table = document.getElementById(tableTag);
    channelCnt = table.rows.length - 1;
    componentCnt = table.rows[0].cells.length - 1;
    for (var component = 0; component < componentCnt; component++) {
        var id = channelTag + "Ch" + channel + "_" + component;
        inputElement = document.getElementById(id);
        if (component == 4) {
            var rounded = Math.round(Math.pow(10, inputElement.value / 10)
                                     * 100000) / 100000;
        } else {
            var rounded = Math.round(inputElement.value * 100000) / 100000;
        }
        inputElement.value = rounded;
    }

    // Call setChromaticChannelReference to properly redo the layout.
    var tag = "ReferenceChannel";
    refElement = document.getElementById(tag);
    setChromaticChannelReference( refElement.value );
}


// Grey out selected input fields when the decon algorithm is set to 'skip'.
function updateDeconEntryProperties( ) {
    var deconTag             =  "DeconvolutionAlgorithm";

    var paramAllChanTagArray = ["QualityChangeStoppingCriterion",
                                "NumberOfIterations"];

    var paramChanTagArray    = ["SignalNoiseRatioCMLE",
                                "SignalNoiseRatioQMLE",
                                "SignalNoiseRatioGMLE",
                                "SignalNoiseRatioSKIP",
                                "Acuity",
                                "BackgroundOffsetPercent",
                                "q",
                                "it"];

    var skipAllChannels = true;

    // First, the channel-dependent parameters.
    for (var chan = 0; chan < 6; chan++) {
        var deconChanTag = deconTag.concat(chan);

        var deconInputElement = document.getElementsByName(deconChanTag);
        var deconAlgorithm    = deconInputElement[0];

        if (deconAlgorithm === undefined) continue;
        if (deconAlgorithm.value !== "skip") skipAllChannels = false;

        // Show the input box relevant for the current algorithm.
        switchSnrMode(deconAlgorithm, chan);

        for (var tagIdx = 0; tagIdx < paramChanTagArray.length; tagIdx++) {
            var tag = paramChanTagArray[tagIdx];
            var id = tag.concat(chan);

            if (document.getElementById(id) == null) continue;
            var paramInputElement = document.getElementById(id);

            if ( deconAlgorithm.value === "skip" ) {
                paramInputElement.readOnly = true;
                paramInputElement.style.backgroundColor="#888";
            } else {
                paramInputElement.readOnly = false;
                paramInputElement.style.backgroundColor="";
            }
        }
    }

    // Then, the channel-independent parameters. These are changed when all
    // channels are skipped.
    for (var tagIdx = 0; tagIdx < paramAllChanTagArray.length; tagIdx++) {
        var id = paramAllChanTagArray[tagIdx];

        var paramInputElement = document.getElementById(id);

        if (skipAllChannels) {
            paramInputElement.readOnly = true;
            paramInputElement.style.backgroundColor="#888";
        } else {
            paramInputElement.readOnly = false;
            paramInputElement.style.backgroundColor="";
        }
    }
}


function copySnrToOtherAlgorithms(channel, inputObj) {

    var tagArray = ["SignalNoiseRatioCMLE",
                    "SignalNoiseRatioGMLE",
                    "SignalNoiseRatioQMLE",
                    "SignalNoiseRatioSKIP"];

    for (var i = 0; i < tagArray.length; i++) {
        var tag = tagArray[i];
        var id = tag.concat(channel);

        if (inputObj.name == id) {
            continue;
        }
        document.getElementById(id).value = inputObj.value;
    }
}


// Grey out the STED input fields of a specific channel if the
// corresponding depletion mode is set to 'confocal'.
function changeStedEntryProperties(selectObj, channel) {
    var tagArray = ["StedSaturationFactor",
                    "StedWavelength",
                    "StedImmunity",
                    "Sted3D"];


    for (var i = 0; i < tagArray.length; i++) {
        var tag = tagArray[i];
        var id = tag.concat(channel);

        if (document.getElementById(id) == null) continue;

        var inputElement = document.getElementById(id);

        if ( selectObj.value == 'off-confocal' ) {
            inputElement.readOnly = true;
            inputElement.style.backgroundColor="#888";
        } else {
            inputElement.readOnly = false;
            inputElement.style.backgroundColor="";
        }
    }
}


function setStedEntryProperties(chanCnt) {
    var tag = "StedDepletionMode";

    for (var chan = 0; chan < chanCnt; chan++) {
        var name = tag.concat(chan);

        var inputElement = document.getElementsByName(name);
        changeStedEntryProperties(inputElement[0], chan);
    }
}

// Grey out the SPIM input fields of a specific channel if the
// corresponding excitation mode is set to 'Gaussian'.
function changeSpimEntryProperties(selectObj, channel) {

    if (selectObj === undefined) {
        return;
    }

    // Declare element
    var element;

    // SpimNA
    element = $("#SpimNA" + channel);
    if (selectObj.value == 'gauss' || selectObj.value == 'gaussMuVi') {
        element.attr('readonly', true);
        element.css('background-color', "#888");
    } else {
        element.attr('readonly', false);
        element.css('background-color', "");
    }

    // SpimFill
    element = $("#SpimFill" + channel);
    if (selectObj.value == 'gauss' || selectObj.value == 'gaussMuVi') {
        element.attr('readonly', true);
        element.css('background-color', "#888");
    } else {
        element.attr('readonly', false);
        element.css('background-color', "");
    }

    // SpimGaussWidth
    element = $("#SpimGaussWidth" + channel);
    if (selectObj.value == 'gauss' || selectObj.value == 'gaussMuVi') {
        element.attr('readonly', false);
        element.css('background-color', "");
    } else {
        element.attr('readonly', true);
        element.css('background-color', "#888");
    }

    // SpimDir
    var options = $("#SpimDir" + channel + " option");
    $.each(options, function() {

        var val = $(this).text().trim();
        switch (val) {

            case "From left":
            case "From right":
            case "From top":
            case "From bottom":
                if (selectObj.value == 'gaussMuVi') {
                    $(this).hide();
                } else {
                    $(this).show();
                }
                break;
            case "Right + left":
            case "Top + bottom":
                if (selectObj.value == 'gaussMuVi') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
        }
    });

    // If the previous value is still visible, we keep it; otherwise, we reset
    // the SpimDir selection to the first visible entry
    if ($("#SpimDir" + channel).find(":selected").css('display') == 'none') {
        $.each(options, function() {
            if ($(this).css('display') != 'none') {
                $(this).prop("selected", true);
                return false;
            }
        });
    }
}



function setSpimEntryProperties( ) {
    var tag = "SpimExcMode";

    for (var chan = 0; chan < 6; chan++) {
        var name = tag.concat(chan);

        inputElement = document.getElementsByName(name);
        changeSpimEntryProperties(inputElement[0], chan);
    }
}

function checkAgainstFormat(file, selectedFormat) {

    if (selectedFormat == 'all') {
        return true;
    }

        // Both variables as in the 'file_extension' table.
    var fileFormat    = '';
    var fileExtension = '';

    // Pattern ome.tiff        = (\.([^\..]+))*
    // Pattern file extension: = \.([A-Za-z0-9]+)
    // Pattern lif, czi subimages:  = (\s\(.*\))*

    var nameDivisions;
    nameDivisions = file.match(/(\.([^\..]+))*\.([A-Za-z0-9]+)(\s\(.*\))*$/);

        // A first check on the file extension.
    if (nameDivisions != null) {

        // Specific to ome-tiff.
        if (typeof nameDivisions[2] !== 'undefined') {
            if (nameDivisions[2] == 'ome') {
                fileExtension = nameDivisions[2] + '.';
            }
        }

        // Main extension.
        if (typeof nameDivisions[3] !== 'undefined') {
            fileExtension += nameDivisions[3];
        }

        fileExtension = fileExtension.toLowerCase();

        switch (fileExtension) {
            case 'dv':
            case 'ims':
            case 'lif':
            case 'lof':
            case 'lsm':
            case 'oif':
            case 'vsi':
            case 'pic':
            case 'r3d':
            case 'stk':
            case 'zvi':
            case 'czi':
            case 'nd2':
            case 'nd':
            case 'tf2':
            case 'tf8':
            case 'btf':
                fileFormat = fileExtension;
                break;
            case 'h5':
                fileFormat    = 'hdf5';
                break;
            case 'tif':
            case 'tiff':
                fileFormat    = 'tiff-generic';
                fileExtension = "tiff";
                break;
            case 'ome.tif':
            case 'ome.tiff':
                fileFormat    = 'ome-tiff';
                fileExtension = "ome.tiff";
                break;
            case 'ome':
                fileFormat    = 'ome-xml';
                break;
            case 'ics':
                fileFormat    = 'ics2';
                break;
            default:
                fileFormat    = '';
                fileExtension = '';
        }
    }

        // Control over tiffs: this structure corresponds to Leica tiffs.
    if ((file.match(/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.\w+/)) != null) {
        if (fileExtension == 'tiff' || fileExtension == 'tif') {
            fileFormat = 'tiff-leica';
        }
    }

        // Control over stks: redundant.
    if ((file.match(/[^_]+_(T|t)[0-9]+\.\w+/)) != null) {
        if (fileExtension == 'stk') {
            fileFormat = 'stk';
        }
    }

        // Control over ics's, no distinction between ics and ics2.
    if (fileExtension == 'ics') {
        if (selectedFormat == 'ics' || selectedFormat == 'ics2') {
            fileFormat = selectedFormat;
        }
    }

    if (selectedFormat != '' && selectedFormat == fileFormat) {
        return true;
    } else if (selectedFormat == 'big-tiff' && fileFormat == 'tf2') {
        return true;
    } else if (selectedFormat == 'big-tiff' && fileFormat == 'tf8') {
        return true;
    } else if (selectedFormat == 'big-tiff' && fileFormat == 'btf') {
        return true;
    } else {
        return false;
    }
}

function changeOpenerDiv (div, html) {
    window.opener.document.getElementById(div).innerHTML= html;
}


function setPrevGen(index, mode) {
    window.opener.generated[index] = mode;
    window.generated[index] = mode;
}

function setActionToUpdate() {
    action = 'update';
}

function setActionToCalcSNR() {
    action = 'calcSNR';
}

function deleteImages() {

    changeDiv('omeroSelection','');
    if (!checkSelection()) {
        changeDiv('upMsg', 'Select one or more images to delete.');
        return;
    }

    control = document.getElementById('selection').innerHTML;
    action = 'delete';
    changeDiv('selection', 'Selected files will be deleted, please confirm:'
       + '<br />'
       + '<input name="delete" type="submit" value="" class="icon delete" '
       +     'onmouseover="Tip(\'Confirm deletion\')" onmouseout="UnTip()"/>'
       + '<input type="button" class="icon abort" '
       +     'onclick="UnTip(); cancelSelection()" '
       +     'onmouseover="Tip(\'Do not delete the file!\')" onmouseout="UnTip()"/>');

}


function checkSelection() {

    sel = document.getElementsByTagName('select');
    if (sel[0].selectedIndex == -1) {
        // Nothing selected.
        return false;
    }
    return true;

}

function confirmSubmit() {

    if (action != '') {
        changeDiv('actions', 'Please wait...<input type="hidden" name="'+action+'" value="1">');
        // Make the message vanish after a reasonable time.
        setTimeout(smoothChangeDiv,6000,'actions','',10000);
    } else {
        changeDiv('actions', '');
    }
    if (action != 'upload' && action != 'update') {
        changeDiv('selection', control);
    }
    action = '';
    return true;
}

function confirmUpload() {

    if (upsubmitted) {
        // Do not avoid resubmitting: it is sometimes necessary with Safari !!!
        // alert('Form already submitted, please wait');
        // return false;
    }

    upsubmitted = true;


    //sel = document.uploadForm.elements;
    form = document.getElementById("uploadForm");

    if ( form.elements[0].value == '' ) {
        alert("Please choose a file to upload, or cancel.");
        return false;
    }

    disableAddMore();
    changeDiv('upMsg', 'Please wait until your browser finishes the file transfer: do not reload or go away from this page.');

    spin =  '<center><img src="images/spin.gif" '
        +     'alt="busy"><br />Please wait...</center>';
    changeDiv('info', spin);
    changeDiv('actions', '');
    changeDiv('buttonUpload',
       '<input name="upload" type="submit" value="" '
       + 'class="icon upload" '
       +   'onmouseover="Tip(\'Upload selected files\')" onmouseout="UnTip()"/>'
       );

    /* pause

    var date = new Date();
    var curDate = null;

    do { curDate = new Date(); }
    while(curDate-date < 3000);

    alert('returning');
    */

    return true;
}

function removeFile(file) {

    changeDiv('upfile_'+file, '');
    UnTip();

    sel = document.getElementsByName('inputFile');
    cnt =  sel.length;
    if (cnt == 0) {
        cancelSelection();
    }
}

function addFileEntry() {

    /* flist = document.getElementById('upload_list').innerHTML;
    sel = document.getElementsByName('inputFile');
    newFile =  sel.length;
    */

    c = '<div class="inputFile" name="inputFile"><input type="file" name="upfile[]" size="30" onchange="handleAddMore()">&nbsp;<a onclick="removeFile('+fileInputs+')" class="removeFile" onmouseover="Tip(\'Remove this file\')" onmouseout="UnTip()"><img src="images/cancel_help.png" width="11"></a></div>';

    changeDiv('upfile_'+fileInputs, c);
    fileInputs = fileInputs + 1;
    disableAddMore();
}

function handleAddMore() {
    if (fileInputs > 19) {
        return;
    }

    sel = document.getElementsByTagName('input');
    for (i=0; i<sel.length; i++) {
        if ( sel[i].name != 'upfile\[\]') { continue; }
        if ( sel[i].value == '' ) {
            disableAddMore();
            return;
        }

    }
    enableAddMore();
}


function enableAddMore() {
    changeDiv('addanotherfile',
              '<a onclick="addFileEntry()">Add another file</a>');
}
function disableAddMore() {
    changeDiv('addanotherfile', '');
}

function uploadImages(maxFile, maxPost, archiveExt) {
    // + '<iframe id="target_upload" name="target_upload" src="" style="width:1px;height:1px;border:0"></iframe>'

    cancelOmeroSelection();

    control = document.getElementById('selection').innerHTML;
    action = 'upload';
    upsubmitted = false;
    changeDiv('selection','');
    changeDiv('message', '');
    changeDiv('upMsg', 'Select a file to upload. Multiple files in a series '
    + 'can also be uploaded in a single archive ('+archiveExt+'). '
    + 'Maximum single file size is <b>' + maxFile
    +'</b>, maximum total transfer size is <b>' + maxPost + '</b>. '
    +'<br /><br /><img alt =\"Warning!\" src=\"./images/note.png\" /> '
    +'<b>If you upload .ics files, do not forget the matching .ids</b>!' );
    changeDiv('up_form',
        '<form id="uploadForm" enctype="multipart/form-data" action="?folder=src&upload=1" method="POST" onsubmit="return confirmUpload()" >'
        + '<input type="hidden" name="uploadForm" value="1"> '
        + '<div id="upload_list">'
        +      '<div id="upfile_0"></div>'
        +      '<div id="upfile_1"></div>'
        +      '<div id="upfile_2"></div>'
        +      '<div id="upfile_3"></div>'
        +      '<div id="upfile_4"></div>'
        +      '<div id="upfile_5"></div>'
        +      '<div id="upfile_6"></div>'
        +      '<div id="upfile_7"></div>'
        +      '<div id="upfile_8"></div>'
        +      '<div id="upfile_9"></div>'
        +      '<div id="upfile_10"></div>'
        +      '<div id="upfile_11"></div>'
        +      '<div id="upfile_12"></div>'
        +      '<div id="upfile_13"></div>'
        +      '<div id="upfile_14"></div>'
        +      '<div id="upfile_15"></div>'
        +      '<div id="upfile_16"></div>'
        +      '<div id="upfile_17"></div>'
        +      '<div id="upfile_18"></div>'
        +      '<div id="upfile_19"></div>'
        +      '<div id="upfile_20"></div>'
        + '<div id="addanotherfile"></div></div>'
        +  '<div id="buttonUpload">'
        +  '<input name="upload" type="submit" value="" '
        + 'class="icon upload" '
        +   'onmouseover="Tip(\'Upload selected files\')" onmouseout="UnTip()"/>'
        + '<input type="button" class="icon abort" onclick="UnTip(); cancelSelection()" '
        +        'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/></div>'
        + ' </form>' );

    fileInputs = 0;

    addFileEntry();

}

/**
 * Display the new uploader based on FineUploader (http://fineuploader.com/).
 */
function uploadImagesAlt() {

    // Cancel the OMERO selection
    cancelOmeroSelection();

    // Show the uploader
    var upFormID = $("#up_form");

    // Clear the div
    upFormID.show();

}

function downloadImages() {

    changeDiv('omeroSelection','');
    changeDiv('message', '');

    if (!checkSelection()) {
        changeDiv('upMsg', 'Select one or more images to download.');
        return;
    }

    control = document.getElementById('selection').innerHTML;
    action = 'download';
    changeDiv('selection', 'Selected files will be packed for downloading '
       +  '(that may take a while).<br /><br />Please confirm and wait:'
       + '<br /><input name="download" type="submit" value="" '
       + 'class="icon apply" '
       +     'onmouseover="Tip(\'Confirm download\')" onmouseout="UnTip()"/>'
       + '<input type="button" class="icon cancel" onclick="UnTip(); cancelSelection()" '
       +        'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/>');

}

function cancelSelection() {
    action = '';
    changeDiv('message', '');
    changeDiv('upMsg', '');
    changeDiv('omeroSelection','');
    changeDiv('actions', '');
    changeDiv('up_form', '');
    changeDiv('selection', control);
}

function changeFileSelectionValueAt(index, newName) {
    document.getElementById('fileSelection')[index].value = newName;
    document.getElementById('fileSelection')[index].text = newName;
    setActionToUpdate();
    document.getElementById('file_browser').submit();
    
}

function imgPrev(infile, mode, gen, sanitized, compare, index,
                 dir, referer, data) {
    var file = unescape(infile);
    var tip, html, link, onClick = "";
    var regexpRes;
    
    if (mode == 0 && gen == 1) {
        try
        {
            if (generated[index] == 2) {
                mode = 2;
            }
            if (generated[index] == 3) {
                mode = 3;
            }
        }
        catch (err)
        {
            mode = 0;
        }
    }
    
    switch (mode)
    {
        case 0:
           if ( gen == 0 ) {
           // Preview doesn't exist
           html = "<img src=\"images/no_preview_button.png\" alt=\"No preview\">"
                  + "<br />No preview available";

           } else {

           // Preview doesn't exist, but you can create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir
                   + "&data=" + data + '&index=' + index;
           
           onClick =  '<center><img src=\\\'images/spin.gif\\\' '
                +                           'alt=\\\'busy\\\'><br />'
                + '<small>Generating preview in another window.<br />'
                + 'Please wait...</small></center>';

               html = '<input type="button" name="genPreview" value="" '
                   +    'class="icon noPreview" '
                   +    'onclick="'
                   +        'changeDiv(\'info\',\'' + onClick + '\'); '
                   +        'openTool(\'' + link + '\'); '
                   +    '"'
                   + '>'
                   + '<br />'
                   + '<div class="expandedView" '
                   +    'onclick="'
                   +        'changeDiv(\'info\',\'' + onClick + '\'); '
                   +        'openTool(\'' + link + '\'); ';
               if ( infile != sanitized ) {
                   html = html + 'changeFileSelectionValueAt(\''
                       + index + '\', \'' + unescape(sanitized) + '\'); ';
               }
               html = html +    '"'
                   + '>'
                   + '<img src="images/eye.png"> '
                   + 'Click to generate preview';

               // Check if the (base)names of the sanitized and original name
               // are the same, otherwise mention that it will rename the file.
               if ( infile != sanitized ) {
                   regexpRes =  /^(.+)\ \(.+\)$/g.exec(file);
                   if (regexpRes && sanitized.startsWith(regexpRes[1])) {
                       // It is not a subimage or the basename is the same.
                   } else {
                       html = html + ' (the file will be renamed)';
                   }
               }
               html = html + '</div>';
           }

           break;
        case 2:
           // 2D Preview exists
           tip = '<i>2D image preview:</i><br>'+file;
           html = '<img id="ithumb" src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir
                  + '" alt="Preview" onmouseover="Tip(\''
                  + tip + '\')" '
                  + ' onmouseout="UnTip()">';
           break;
        case 3:
           // 3D Preview exists
           tip = '<i>3D image XY preview:</i><br>'+file;
           html = '<img id="ithumba" src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir
                  + '" alt="XY preview" onmouseover="Tip(\''
                  + tip + '\')" '
                  + ' onmouseout="UnTip()" >';
           tip = '<i>3D image XZ preview:</i><br>'+file;
           html = html + '<img id="ithumbb" '
                  + 'src="file_management.php?getThumbnail='
                  + infile + '.preview_xz.jpg&dir=' + dir
                  + '" alt="XZ preview" onmouseover="Tip(\''
                  + tip + '\')" '
                  + ' onmouseout="UnTip()">';
           break;

    }

    if ( gen == 1 && mode > 1 && dir == "src" ) {

           // Preview exists, and you can re-create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir
                  + "&data=" + data + '&index=' + index;

           onClick =  '<center><img src=\\\'images/spin.gif\\\' '
                +                     'alt=\\\'busy\\\'><br />'
                + '<small>Generating preview in another window.<br />'
                + 'Please wait...</small></center>';

           html = '<br /><a '
                  +    'onclick="'
                  +        'changeDiv(\'info\',\'' + onClick + '\'); '
                  +        'openTool(\'' + link + '\'); '
                  +    '"'
                  + '>'
                  + '<div class="expandedView">'
                  + '<img src="images/eye.png"> Re-create preview'
                  + '</div>'
                  + html + '</a>';
    }
    if ( compare > 0 ) {
           link = "file_management.php?compareResult=" + infile
                  + "&size=" + compare + "&op=close";

           html = '<br /><a href="' + link + '">'
                  + '<div class="expandedView">'
                  + '<img src="images/eye.png">&nbsp;&nbsp;'
                  + 'Click for detailed results:'
                  + '</div>'
                  + html + '</a>' ;
    }

    html = '<h3>Preview</h3>' + html;

    // changeDiv('info', html);
    // smoothChangeDiv2('info','ithumb', 'ithumb2', html, 200);
    smoothChangeDiv('info',html, 200);
    window.infoShown = false;
    window.previewSelected = html;
}

function showInstructions() {
    if (window.infoShown) return;
    smoothChangeDiv('info',window.pageInstructions, 200);
    window.infoShown = true;
}

function showPreview() {
    if (!window.infoShown) return;
    if (window.previewSelected == -1) return;
    smoothChangeDiv('info',window.previewSelected, 200);
    window.infoShown = false;
}

function changeVisibility(id) {
    blockElement = document.getElementById(id);
    if (blockElement.style.display == "none")
        blockElement.style.display = "block";
    else if (blockElement.style.display == "")
        blockElement.style.display = "block";
    else if (blockElement.style.display == "block")
        blockElement.style.display = "none";
    return blockElement.style.display;
}

function hide(id) {
    blockElement = document.getElementById(id);
    blockElement.style.display = "none";
}

function show(id) {
    blockElement = document.getElementById(id);
    blockElement.style.display = "block";
}
