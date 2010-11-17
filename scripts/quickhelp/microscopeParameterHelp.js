// Quick help for the Microscope Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "type" ] =
  '<p>Type: fill in, please.</p>';

window.helpText[ "NA" ] =
  '<p>NA: fill in, please.</p>';

window.helpText[ "wavelengths" ] =
  '<p>Wavelengths: fill in, please.</p>';

window.helpText[ "objective" ] =
  '<p>Objective: fill in, please.</p>';

window.helpText[ "sample" ] =
  '<p>Sample: fill in, please.</p>';

window.helpText[ "default" ] =
  '<p>On this page you specify the parameters for the optical setup ' +
  'of your experiment.</p>' +
  '<p>These parameters comprise the microscope type, ' +
  'the numerical aperture of the objective, the wavelenght of the used ' +
  'fluorophores, and the refractive indices of the sample medium and of ' +
  'the objective-embedding medium.</p>';