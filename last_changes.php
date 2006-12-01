<script language="php">
// php page: last_changes.php 

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.
</script>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3c.org/TR/1999/REC-html401-19991224/loose.dtd">
<HTML xmlns="http://www.w3.org/1999/xhtml">
<HEAD>
<TITLE>Huygens Remote Manager - Last Changes</TITLE>
<link rel="stylesheet" type="text/css" href="huygens_style.css">
<style type="text/css">
<!--
  ... page specific style ...
-->
</style>
<META http-equiv=Content-Type content="text/html; charset=windows-1252">
<META content="MSHTML 6.00.2800.1400" name=GENERATOR></HEAD>
<BODY>
<DIV id=basket>
<div id=title>
<script language="php">
include('./inc/logos.inc'); 
</script>
</div>
<UL id=nav>
  <LI><A href="login.php">back</A>
</UL>
<DIV id=content>
<h2>Huygens Remote Manager</H2>
<h3>Version
<script language="php">
	$version = readfile("version"); 	 
</script>	
</h3>
<h3>Changes</h3>
<ul>
<li>use ideal z sampling size according to the nyquist criterium for 2D images
<li>the default output file format for the two photon microscope is ims except for 4D images
<li>the check of the two photon microscope parameters has been changed to use the excitation wavelength
<li>for a two photon microscope the pixel size is asked
<li>removed the output file format from the settings display on the create task page
<li>change owner of result files if set in config 
<li>fixed a bug that occured when the imagesBrutes folder was empty
<li>fixed a bug that occured when exactly one range parameter was used
<li>start jobs as quick as possible
<li>sub-jobs for compound jobs are created immedeately
<li>allowed DES encryption for igh
<li>a bug that some long running jobs got stuck has been finally solved
<li>a text file containing the parameter is written along with the image
<li>4 dimensional images can be saved as ics or ims
<li>ics images are automatically saved with the lowest bitrate needed to represent all values  
<li>use full image name including the path for the result images for multichannel
<li>use full image name including the path for the result images
<li>user login always loads users parameters even if an other user is still logged in 
<li>single tiff is loaded as single image even if it belongs to a series
<li>corrected bug in background offset for multi channel
<li>for single point confocal the pixel size has to be entered now 
<li>switched of the backstore for undo operations in huygens script
</ul>
<h4><A href="login.php">back</A></h4>  
<DIV id=stuff>
</div>
</DIV>
<DIV id=footer align="center"><small>created 2004 by <a href="mailto:volker.baecker@crbm.cnrs.fr">
Volker Baecker</a></small></DIV>

</DIV>
</BODY>
</HTML>