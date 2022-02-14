// Quick help for the Capturing Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "chromatic" ] =
    '<p>For the estimation of the chromatic aberration parameters it is ' +
    'recommended to open an image of multi channel beads in the ' +
    '<b>Chromatic Aberration Corrector</b> in <b>Huygens Professional</b> ' +
    'or <b>Huygens Essential</b>. The values estimated by Huygens can ' +
    'be used in this table to correct the images for chromatic aberration ' +
    'in batch mode.</p><p>To <b>skip</b> this step just leave the table ' +
    'fields empty.';
// TODO
window.helpText[ "default" ] =
    '<p>On this page you specify the parameters of those ' +
    'restoration operations to be carried out on the deconvolved ' +
    'image.';

window.helpText[ "tstabilization" ] =
    '<p>Choose method <b>Cross correlation</b> for general x-y-z translations ' +
    'and axial rotations. Adjacent time frames will be compared. The software ' +
    'will try to find the best alignment by maximizing structural overlap.' +
    '</p><p>Choose method <b>Model based</b> if the geometry of the imaged ' +
    'object did not change much during the acquisition.</p>' +
    '<p>The <b>CM</b> method works best if the image contains a single large ' +
    'object. No objects should cross the image borders.</p>' +
    '<p>The cropping method <b>Full</b> will preserve the size of the ' +
    'stabilized data. Method <b>Tight</b> will crop away large background ' +
    'regions.';
