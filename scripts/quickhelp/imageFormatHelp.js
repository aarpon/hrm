// Quick help for the Image Format page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "format" ] =
  '<p>This is the file format (or the file extension) of the files to ' +
  'deconvolve. Most microscope vendors have their own proprietary file ' +
  'formats.</p>';

window.helpText[ "geometry" ] =
  '<p>The image geometry defines the subset of parameters required to ' +
  'describe the dataset. For instance, the time interval is only relevant ' +
  'if a time series is chosen.</p>';

window.helpText[ "channels" ] =
  '<p>The number of channels represent the number of colors (i.e. ' +
  'wavelengths) in your dataset.</p>';

window.helpText[ "PSF" ] =
  '<p>Deconvolution can be performed with either a theoretical PSF ' +
  'calculated from the optical parameters, or with a measured PSFs that ' +
  'can be obtained by distilling images of sub-resolution beads in Huygens. ' +
  'Please click on the help icons to get additional information on this ' +
  'topic.</p>';

window.helpText[ "default" ] =
  '<p>Here you are asked to provide information on format and geometry for ' +
  'the files you want to restore.</p>' +
  '<p>Moreover, you must define whether you want to use a ' +
  'theoretical PSF, or if you instead want to use a measured PSF ' +
  'you distilled with the Huygens software.</p>';
