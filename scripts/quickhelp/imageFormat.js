// Quick help for the Image Format page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "channels" ] =
  '<p>Set the number of channels in your images.</p>';

window.helpText[ "PSF" ] =
  '<p>Use a theoretical PSF calculated from the acquisition parameters ' +
  'or a measured one obtained from distilled bead images in the Huygens ' +
  'Professional software.</p>';

window.helpText[ "default" ] =
  '<p>Here you are asked to define the number of channels in your data and ' +
  'whether you want to use a theoretical PSF or a measured PSF you distilled ' +
  'with the Huygens Software.</p>';
