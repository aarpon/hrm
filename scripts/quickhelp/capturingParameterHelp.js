// Quick help for the Capturing Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "voxel" ] =
  '<p>Voxel: fill in, please.</p>';

window.helpText[ "time" ] =
  '<p>Time interval: fill in, please.</p>';

window.helpText[ "pinhole_radius" ] =
  '<p>Pinhole radius: fill in, please.</p>';

window.helpText[ "pinhole_spacing" ] =
  '<p>Pinhole spacing: fill in, please.</p>';

window.helpText[ "default" ] =
  '<p>Here you have to enter the voxel size as it was set during the ' +
  'image acquisition. Remember that the closer the acquisition sampling ' +
  'is to the Nyquist <b>ideal sampling rate</b>, the better both the input ' +
  'and the deconvolved images will be!</p> ' +
  '<p>The Huygens Remote Manager will not try to stop you from running a ' +
  'deconvolution on undersampled data (i.e. with a sampling rate much ' +
  'larger than the ideal), but do not expect meaningful results!</p>';
  