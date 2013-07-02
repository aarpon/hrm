// Quick help for the Capturing Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "voxel" ] =
  '<p>Each voxel (or 3D pixel) in a 3D digital image has a given volume given ' +
  'by its x and y (usually equal) and z dimensions. The x and y dimensions of the ' +
  'voxel are actually the distances between the centers of two adjacent ' +
  'pixels in those two directions (pixel size), and the z dimension is given by the ' +
  'distance of adjacent planes in z directions (z step). In this ' +
  'context, the voxel size relates to the sampling distance and ' +
  'is inversely proportional to the sampling frequency. The ' +
  'Nyquist-Shannon sampling theorem states that when sampling a ' +
  'signal, the sampling frequency must be greater than twice the bandwidth ' +
  'of the input signal in order to be able to reconstruct the original ' + 
  'perfectly from the sampled version. Meeting the Nyquist rate is an ' +
  'important requisite for successful deconvolution. The ' +
  '<span style="background-color:yellow">highlighted values</span> are ' +
  'the ideal voxel size for your parameters. The HRM will not try to stop ' +
  'you from running a deconvolution on under-sampled data (i.e. with a ' +
  'sampling rate much larger than the ideal), but do not expect meaningful ' +
  'results! Set the z-step to <b>0</b> for 2D datasets.</p>';

window.helpText[ "time" ] =
  '<p>The time interval is the (temporal) distance between two frames in a ' +
  'time series, and relates to the temporal sampling. Set the time interval ' +
  'to <b>0</b> if you are not deconvoling time series.</p>';

window.helpText[ "pinhole_radius" ] =
  '<p>The pinhole of confocal microscopes is a small hole used to get rid ' +
  'of out-of-focus light, thus allowing the recording of real 3D images. ' +
  'Huygens needs this information to properly calculate the PSF of the '  +
  'system. It is important to realize, however, that the Huygens software ' +
  'expects the back-projected pinhole size, and not its physical size. ' +
  'Please use the calculator to obtain the correct size for your microscope.</p>';

window.helpText[ "pinhole_spacing" ] =
  '<p>The pinhole spacing (in &#956;m) is the distance between the ' +
  'pinholes in a spinning disk. As is the case for the pinhole radius, ' +
  'also for the pinhole spacing the back-projected value is expected.</p>';

window.helpText[ "default" ] =
  '<p>Here you have to enter the voxel size as it was set during the ' +
  'image acquisition. Depending on the microscope type and the dataset ' +
  'geometry, you might have to enter additional parameters, such as the ' +
  'back-projected pinhole size and spacing, and the time interval for time ' +
  'series. For microscope type that use cameras (such as widefield and ' +
  'spinning disk confocal), you have the possibility to calculate the image ' +
  'pixel size from the camera pixel size, total magnification, and binning.</p>';

  