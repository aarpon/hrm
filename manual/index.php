<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html;charset=ISO-8859-1" http-equiv="Content-Type">
  <title>Huygens Remote Manager - Online manual</title>
  <meta content="Aaron Ponti" name="author">
  <meta http-equiv="CONTENT-TYPE" content="text/html; charset=utf-8">
  <link href="imaging.css" rel="stylesheet" type="text/css">
</head>

<body>
<script language="php">include('header.php');</script>
<br>

<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2">
  <tbody>
    <tr>
      <td style="width: 70px;">
	<a href="http://www.fmi.ch" target="_blank">
		<img style="width: 70px; height: 70px;" alt="Logo" src="img/logomri1.jpg">
	</a>
	</td>
      <td><img style="width: 800px; height: 122px;" alt="Huygens Remote Manager" src="img/mri_logo.jpg"></td>
    </tr>
 
  </tbody>
</table>
<br>

<h1>ONLINE MANUAL</h1>

Huygens remote manager (<span style="font-weight: bold;">HRM</span>) is a web interface to <a href="http://www.svi.nl" target="_blank">Scientific Volume Imaging</a>'s Huygens Professional software, that allows the initiation of batch deconvolutions with up to 300 jobs at a time. 
<br>
<br>
<hr>
<br>
<h2>Introduction<br>

</h2>

<h3>Features and limits of HRM</h3>

<ul>
  <li>HRM runs on a 64-bit, dual-Opteron IBM computer equipped  with 10GB memory. The operating system is SuSE Linux 9.3 64bit.</li>
  <li>Up to 300 jobs can be started at once.</li>
  <li>Several image formats and geometries are supported.</li>
  <li>Settings are stored in a database, and are therefore always ready for further deconvolution runs.</li>
  <li>Currently, the HRM interface does <b>not</b>
allow deconvolution with a measured point-spread function. A synthetic
PSF is calculated from the optical parameters used for imaging. Please
contact us if you want to run deconvolution on your stack with a
measured PSF.</li>
</ul>


<h3>General information about deconvolution</h3>


<a target="_blank" href="http://www.svi.nl/support">SVI support resources</a>
is a collection of documents on deconvolution and 3D microscopy in
general. Some introduction on the topic can also be found in the
Training section of FMI's ImageAccess database.<br>

<h3>Prerequisites for successful deconvolution</h3>

To increase the probability of achieving a successful deconvolution on your data there are several criteria which have to be met. Please download and fill <a href="huygensparameterbatch2.xls" target="_self">this file</a> and contact us before you try to run your first deconvolution. Briefly, make sure that:<br>

<ul>

  <li>The 
<a href="http://www.fmi.ch/html/technical_resources/microscopy/homepage/microcalc/default.htm"
                         target="_same">acquisition</a> was performed optimally, i.e. make sure to:</li>

	<ul>
		<li>optimize objective lens and sample setup
		<li><a href="http://www2.bitplane.com/sampling/index.cfm"
			 target="_same">correctly sample in both XY and Z</a>
		<li>focus sufficiently above and below the specimen
		<li>ensure that the illumination source is stable and uniform
		<li>prevent image saturation
		<li>minimize stage vibration and specimen movement
		<li>correct for any camera or detector defects
	</ul>

  <li>All metadata for the deconvolution is known. For deconvolution in Huygens Professional, the following information is mandatory:</li>
	<ul>
		<li>Sample size along x, y and z
		<li>Microscope type
		<li>Numerical aperture
		<li>Pinhole radius (and distance)
		<li>Lens refractive index
		<li>Medium refractive index
		<li><a href="http://pingu.salk.edu/flow/fluo.html" target="_blank">Excitation wavelength</a>
		<li><a href="http://pingu.salk.edu/flow/fluo.html" target="_blank">Emission wavelength</a>
		<li>Excitation photons
	</ul>

  <li>Your data is saved in one of the <a href="http://huygens.fmi.ch/php/huygens/help/helpImageFormatPage.html#format" target="_blank">supported formats</a></li>

</ul>

<br>
<hr>
<br>

<h2>Step-by-step guide to creating a job in HRM</h2>

The following is a brief tutorial on how to set up a job in HRM. For any questions or comments you can contact us.

<h3>1. Copy your data to \\huygens\huygens_input</h3>

Copy your data into <span style="font-weight: bold;">\\huygens\huygens_input\YOUR_NAME</span>:
we suggest that you create a subfolder in \\huygens\huygens_input where
you the data to be processed. This will maintain the level of chaos within an acceptable range.<br>

<br>

<div style="text-align: center;"><img style="width: 778px; height: 739px;" alt="Copy your data to \\huygens\huygens_input" src="img/hrmd_instructions01.png"><br>
<br>
<div style="text-align: left;"><br>
<h3>2. Start HRM: point your web browser to http://huygens.fmi.ch&nbsp;</h3>
Enter http://huygens.fmi.ch into your web browser and you will be directed&nbsp;to the login page. Use following information:<br>
Name: <span style="font-weight: bold;">pschwarb</span><br style="font-weight: bold;">
Password: <span style="font-weight: bold;">huygens</span><br>

<br>

<div style="text-align: center;"><img style="width: 709px; height: 627px;" alt="Login screen" src="img/hrmd_instructions02.png"></div>
<br>
<h3>3. Step 1: Select or create new Parameter settings &nbsp;</h3>
In the first step of Job creation, you will have to define all relevant parameters
related to your data. All choices in HRM are accompanied by a <img style="width: 16px; height: 16px;" alt="Help button" src="img/help.gif">&nbsp;(help) button: for this reason, we won't discuss all possible selections here.<br>
<br>

<div style="text-align: center;"><img style="width: 707px; height: 677px;" alt="Parameter setting" src="img/hrmd_instructions03.png"></div>
<br>
If you want to use one of the stored settings, simply select it in the <b>available parameter settings list</b> and click on the <span style="font-weight: bold;">OK button</span>.
Alternatively you can create new settings or edit existing ones. As an
example, we will now create a new setting. Enter a name (e.g. <span style="font-style: italic; font-weight: bold;">test</span>) in the <span style="font-weight: bold;">"name of new setting:" field</span> and click on the <span style="font-weight: bold;">"create new setting" button</span>. This will bring you to the new screen (point 3a).<br>
<br>
<h3>3a. Select the format of your data</h3>
For the sake of the example, we will assume here that your data is stored in
the LSM510 file format and that it contains 1 channel only. Moreover, the
data is organized in the XYZ geometry. Click on one of the OK buttons after selection
to continue to the next screen.<br>
<br>

<div style="text-align: center;"><img style="width: 707px; height: 756px;" alt="Image format" src="img/hrmd_instructions04.png"></div>
<br>
<h3>3b. Enter all relevant acquisition parameters</h3>
Detailed help for all entries in this page can be accessed by clicking on the&nbsp;<img style="width: 16px; height: 16px;" alt="Help button" src="img/help.gif"> buttons next to the fields. You should already have filled all the information requested for this page in the 
<a href="huygensparameterbatch2.xls" target="_self">form</a> you sent us.<br>
<br>
<div style="text-align: center;">
<img style="width: 706px; height: 1252px;" alt="Acquisition parameters" src="img/hrmd_instructions05.png"><br></div>
<div style="text-align: left;"></div>
<div style="text-align: left;">
<h3>3c. Sampling parameters</h3>
As usual, detailed help can be obtained by clicking on the&nbsp;<img style="width: 16px; height: 16px;" alt="Help button" src="img/help.gif"> buttons. Regarding the <b>pixel size field</b>, please notice that if you selected "wide-field microscope" in the previous screen (in point 3b), you will need to enter the <em>actual</em> pixel size of the camera chip and not of the final image. This means that for a widefield microscope you will be entering a pixel size of - let's say - 6500 nm (because internally this value will then be divided by the magnification, e.g. 100x, to get to the pixel size in the image), whereas for a "confocal microscope" it will be something like 90 nm (magnification already considered). This will be changed in the future.<br>
<br>
<div style="text-align: center;"><img style="width: 706px; height: 511px;" alt="Sampling parameters" src="img/hrmd_instructions06.png"><br>
<div style="text-align: left;"><br>
Clicking the <b>OK button</b>, you will be brought back to the <b>Step 1: Select Parameter Setting</b> screen (point 3). Make sure to select the newly created <b><i>test</i></b> setting and click again on <b>OK</b> to continue to Step 2.<br>
<h3>4. Step 2: Select or create new Task settings &nbsp;</h3>
In Step 2, we specify what operations are to be performed on the data. Again, as an example we create a new set of settings <span style="font-weight: bold; font-style: italic;">test</span>. Enter 'test' in the <span style="font-weight: bold;">"name of new setting:" field</span> and click on the <span style="font-weight: bold;">"create new setting" button</span>. Note that the name of the Task settings can be different from the name of the Parameter settings (point 3).<br>
<br>
<div style="text-align: center;"><img style="width: 707px; height: 677px;" alt="Task setting" src="img/hrmd_instructions07.png"><br>
<div style="text-align: left;"><br>
<h3>4a. Task settings &nbsp;</h3>
HRM offers three modes of operation: <span style="font-weight: bold;">full restoration</span>, <span style="font-weight: bold;">remove noise</span>, and <span style="font-weight: bold;">remove background</span>. Only <em>full restoration</em> performs deconvolution. Again, click on the <img style="width: 16px; height: 16px;" alt="Help button" src="img/help.gif"> buttons for help. Some suggestions are also listed in green.<br>
<br>
<div style="text-align: center;">
<img alt="Task settings" src="img/hrmd_instructions08.png"><br>
</div>
<h3>5. Step 3: Select images &nbsp;</h3>
From the <span style="font-weight: bold;">available images field</span>, as many images as needed can be added to the <span style="font-weight: bold;">selected images list</span>.
The available images are those files you (and others...) copied to \\huygens\huygens_input
that match the selected file format (in our example, LSM510 files).
You can add multiple files by CTRL-left clicking on them. When you are done, click on the <span style="font-weight: bold;">green arrow </span>to add files to the <span style="font-weight: bold;">selected images list</span>. The lists can be refreshed at any time via the <span style="font-weight: bold;">update view button</span> (e.g. if files are copied to \\huygens\huygens_input only later). Click <span style="font-weight: bold;">OK</span> when ready.<br>
<br>
<div style="text-align: center;">
<img style="width: 705px; height: 616px;" alt="Select images" src="img/hrmd_instructions09.png"></div>
</div>
<div style="text-align: left;"><br>
As for the Parameter Setting, clicking the <span style="font-weight: bold;">OK button</span> will bring you back to the&nbsp;<span style="font-weight: bold;">Step 2: Select Task Setting</span> screen. Once there, select the newly created <b><i>test</i></b> setting and click on <span style="font-weight: bold;">OK</span> to continue to Step 4.<br>
<br>
<h3>6. Step 4: Review selection and create job &nbsp;</h3>
In this screen you can review your selection, select the <span style="font-weight: bold;">output file format</span> (default is the Imaris file format <i>ims</i>), and (finally!) start the job by clicking on the <span style="font-weight: bold;">create job button</span>.<br>
<b>Remark: the first time(s), you will be tempted to click on Ok: don't do that. If you do not click on <em>create job</em> (at the bottom of the page), your job won't be started.</b><br>
<br>
<div style="text-align: center;"><img style="width: 706px; height: 808px;" alt="Review selection" src="img/hrmd_instructions10.png"><br>
<div style="text-align: left;"><br>
<h3>7. Check that HRM has added the job to the queue</h3>
Click on <span style="font-weight: bold;">queue</span> in the navigation menu at the top of the page to display the Queue Manager.<br>
&nbsp;<br>
<div style="text-align: center;"><img style="width: 708px; height: 127px;" alt="How to get to the queue" src="img/hrmd_instructions11.png"><br>
<div style="text-align: left;">Your job should be listed at the end of the queue.<br>
<br>
<div style="text-align: center;"><img style="width: 706px; height: 690px;" alt="Queue Manager" src="img/hrmd_instructions12.png"><br>
<div style="text-align: left;"><br>
<h3>8. Collect your deconvolved data from \\huygens\huygens_output</h3>
Once the job is finished, the deconvolved data will be put in \\huygens\huygens_output\YOUR_NAME for you to collect.</br>
<b>If this is your first deconvolution, we really suggest that you contact us to discuss the result.</b><br>
<br>
<div style="text-align: center;"><img alt="\\huygens\huygens_output" src="img/hrmd_instructions13.png"><br>
<br>
<h1>Have fun with HRM!</h1></div>
<br>
<script language="php">include('footer.php');</script>
</body>
</html>
