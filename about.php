<?php

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

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="login.php">back</a></li>
        </ul>
    </div>

<div id="content">

	<div id="about">

		<h2>About HRM version <?php readfile("version"); ?></h2>
		<p><strong>Important: </strong>The HRM requires <strong>Huygens Core v3.3.1 and above</strong> for full functionality.</p>

		<h3>Copyright</h3>
		<p>2004 - 2009, Montpellier RIO Imaging (CNRS)</p>

		<h3>Original concept</h3>
		<p><strong>Pierre Travo<sup>1</sup></strong> (concept);<br />
		<strong>Volker Bäcker<sup>1</sup></strong> (concept and implementation).</p>
		<p><sup><strong>1</strong></sup> Montpellier RIO Imaging (CNRS).</p>

		<h3>Further development</h3>
		<p><strong>Patrick Schwarb<sup>2</sup></strong> (initiator);<br />
		<strong>Aaron Ponti<sup>2</sup></strong> (concept and implementation);<br />
		<strong>Asheesh Gulati<sup>3</sup></strong> (concept and implementation);<br />
		<strong>Alessandra Griffa<sup>3</sup></strong> (concept and implementation);<br />
		<strong>José Viña<sup>4</sup></strong> (concept and implementation).</p>
		
		<p><sup><strong>2</strong></sup> Friedrich Miescher Institute, Basel (FMI);<br />
		<sup><strong>3</strong></sup> École Polytechnique Fédérale de Lausanne (EPFL);<br />
		<sup><strong>4</strong></sup> Scientific Volume Imaging, Hilversum (SVI).</p>
		
		<h3>Web</h3>
		<p><a href="javascript:openWindow('http://hrm.sourceforge.net')">http://hrm.sourceforge.net</a></p>

		<p>The Huygens Remote Manager (HRM) is a web-based deconvolution platform primarily targeted to microscopy
		and imaging facilities in academic and industrial environments that must handle large user bases.
		HRM in an interface to the Huygens software from Scientific Volume Imaging.</p>

		<p>HRM was originally developed at Montpellier Rio Imaging in 2004 by Pierre Travo and Volker Baecker.</p>

		<p>Now, HRM is a joint project of Montpellier Rio Imaging, the Friedrich Miescher Institute in Basel, the 
		BioImaging and Optics platform at the Ecole Polytechnique Fédérale de Lausanne and Scientific Volume 
		Imaging.</p>

		<p>This software is governed by the CeCILL license under French law and abiding by the rules of distribution of free software.</p>

		<h3>Third-party software</h3>

		<p>Huygens Remote Manager uses the <a href="javascript:openWindow('http://adodb.sourceforge.net/')">ADOdb Database Abstraction Library</a>.</p>

		<p>Icons in Huygens Remote Manager are taken from the <a href="javascript:openWindow('http://icon-king.com/?p=15')">Nuvola icon theme v1.0 by David Vignoni</a>.</p>
	
	</div> <!-- about -->

</div> <!-- content -->

<?php

include("footer.inc.php");

?>
