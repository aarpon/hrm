<script language="php">
// php page: about.php 

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
<TITLE>Huygens Remote Manager - About</TITLE>
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
<h3>About - huygens remote manager</h3>
<pre>
 Copyright: Montpellier RIO Imaging (CNRS) 

 contributors : 
 	     Pierre Travo	(concept)	     
 	     Volker Baecker	(concept, implementation)

 email:
 	pierre.travo@crbm.cnrs.fr
 	volker.baecker@crbm.cnrs.fr

 Web:     www.mri.cnrs.fr

 huygens remote manager is a software that has been developed at 
 Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
 Baecker. It allows running image restoration jobs that are processed 
 by 'Huygens professional' from SVI. Users can create and manage parameter 
 settings, apply them to multiple images and start image processing 
 jobs from a web interface. A queue manager component is responsible for 
 the creation and the distribution of the jobs and for informing the user 
 when jobs finished.

 This software is governed by the CeCILL license under French law and
 abiding by the rules of distribution of free software.
 </pre>
<h4><A href="login.php">back</A></h4>  
<DIV id=stuff>
</div>
</DIV>
<DIV id=footer align="center"><small>created 2004 by <a href="mailto:volker.baecker@crbm.cnrs.fr">
Volker Baecker</a></small></DIV>

</DIV>
</BODY>
</HTML>