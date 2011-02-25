// Quick help for the Microscope Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "type" ] =
  '<p>It is important for Huygens to know the type of the microscope that ' +
  'was used for acquisition, since this strongly influences the shape and ' +
  'size of the PSF.</p>';

window.helpText[ "NA" ] =
  '<p>The numerical aperture (NA) of the objective characterizes the maximum ' +
  'angle of light that can be captured by the lens. ' +
  'The larger the NA value, the higher the resolving power and the smaller ' +
  'the focal depth.</p>';

window.helpText[ "wavelengths" ] =
  '<p>Wavelength is the distance between two consecutive crests of a wave. ' +
  'In fluorescence microscopy, two wavelengths are important: the ' +
  'excitation wavelength and the emission wavelength. ' +
  'Lower wavelengths result in higher resolution.</p>';

window.helpText[ "objective" ] =
  '<p>The lens immersion medium is the medium in which the microscope ' +
  'objective is immersed. Your selection will define the refractive index ' +
  'that will be used for calculations.</p>';

window.helpText[ "sample" ] =
  '<p>The specimen embedding medium is the medium in which the specimen ' +
  'being measured is embedded. If none of the presets fits your experimental ' +
  'setup, you can specify the refractive index of your embedding medium ' +
  'in the last field.</p>';

window.helpText[ "default" ] =
  '<p>On this page you specify the parameters for the optical setup ' +
  'of your experiment.</p>' +
  '<p>These parameters comprise the microscope type, ' +
  'the numerical aperture of the objective, the wavelength of the used ' +
  'fluorophores, and the refractive indices of the sample medium and of ' +
  'the objective-embedding medium.</p>';