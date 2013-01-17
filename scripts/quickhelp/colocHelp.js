// Quick help for the Capturing Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "perform" ] =
  '<p>By performing <b>Colocalization Analysis</b> on an image you can ' +
  'objectively quantify and visualize the degree of overlap between two ' +
  'channels of the image. </p>' +
  '<p>The choice to run <b>Colocalization Analysis</b> ' +
  'can be activated and deactivated from this drop-down menu.</p>' +
  '<p>When activated, HRM will, after the <b>restoration</b>, perform ' +
  'Colocalization Analysis on the restored images. ' +
  '<p>The colocalization results will be available ' +
  'on the <b>detailed results</b> page of this run.</p>';


window.helpText[ "channels" ] =
  '<p>HRM can run <b>Colocalization Analysis</b> on any two channels of ' +
  'the image.</p><p>Here you can select which channels (at least two) ' +
  'should be analyzed. The analysis will be done on all the ' +
  'two-channel combinations of this selection.</p><p>The ' +
  'colocalization results of different two-channel combinations will ' +
  'be displayed separately. <p>The analysis will also be executed ' +
  'on each <b>time frame</b> of the dataset.</p>' ;
 
window.helpText[ "coeff" ] =
  '<p>In this section the colocalization coefficients most widely used ' +
  'in the <b>scientific literature</b> can be selected for the analysis.</p> ' +
  '<p>The <b>selected coefficients</b> will by ' + 
  'computed and their value displayed ' + 
  'for further reference along with other colocalization results. </p>' +
  '<p>Please click on the <img src="images/help.png" alt ="?" width="20"> ' +
  'icon of this section to read more about which coefficients best fit ' + 
  'your research needs.</p>';

window.helpText[ "threshold" ] =
  '<p>The influence of the image background on the ' +
  'colocalization coefficients can be removed by applying a ' +
  'background threshold before the coefficients are calculated.</p>' +
  '<p>The <b>Automatic</b> option estimates and removes the background ' +
  'optimally for all the image channels.<p>The <b>Percentage</b> option ' +
  'allows you to specify a percentage of the intensity range for each ' + 
  'channel which will be removed from the image for the computation ' +
  'of colocalization coefficients.</p><p>Please notice that the saved ' +
  'restoration results are not affected by these threshold values.</p>';

window.helpText[ "maps" ] =
  '<p>The colocalization results will show a <b>MIP</b> of the two channels ' +
  'analyzed.</p><p>The degree of overlap between the two channels can be ' +
  '<b>visualized</b> in a <b>colocalization map</b> displayed next to the ' +
  'MIP.</p><p>The shown colocalization map will be based on the coefficient ' +
  'selected out of the 4 available options.</p><p>For more information ' +
  'on colocalization maps please click on the ' +
  '<img src="images/help.png" alt ="?" width="20"> icon of this section.</p>';

window.helpText[ "default" ] =
  '<p>On this page you can specify whether you would like to perform ' +
  'colocalization analysis on the deconvolved images.</p>' +
  '<p>You can select the channels to be taken into account for colocalization ' +
  'analysis, as well as the colocalization coefficients and maps.</p>';
