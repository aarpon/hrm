<?php

// php page: footer.inc.php

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

session_start();

$loggedIn = ( isset($_SESSION['user'] ) && $_SESSION['user']->isLoggedIn( ) );

?>

    <div id="footer">
        created 2004 by
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:volker.baecker@mri.cnrs.fr">Volker Baecker</a>';
			} else
				echo "<span onmouseover=\"Tip('Login to see contact information.' )\"
					onmouseout=\"UnTip()\">Volker Baecker</span>";
		?>
		<br />modified 2006-2010 by
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:asheesh.gulati@epfl.ch">Asheesh Gulati</a>';
			} else
				echo "<span onmouseover=\"Tip('Login to see contact information.' )\"
					onmouseout=\"UnTip()\">Asheesh Gulati</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:alessandra.griffa@epfl.ch">Alessandra Griffa</a>';
			} else
				echo "<span onmouseover=\"Tip('Login to see contact information.' )\"
					onmouseout=\"UnTip()\">Alessandra Griffa</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:jose@svi.nl">Jos&eacute; Vi&ntilde;a</a>';
			} else
				echo "<span onmouseover=\"Tip('Login to see contact information.' )\"
					onmouseout=\"UnTip()\">Jos&eacute; Vi&ntilde;a</span>";
		?> &amp;
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:aaron.ponti@fmi.ch">Aaron Ponti</a>';
			} else
				echo "<span onmouseover=\"Tip('Login to see contact information.' )\"
					onmouseout=\"UnTip()\">Aaron Ponti</span>";
		?>
	</div>
    
    <div id="validation">
        <a href="http://validator.w3.org/" onclick="clean()">
            <img style="border:0;width:88px;height:31px" src="images/valid-xhtml10.png" alt="Valid XHTML 1.0 Strict" />
        </a>
		<a href="http://jigsaw.w3.org/css-validator/" onclick="clean()">
			<img style="border:0;width:88px;height:31px" src="images/valid-css21.png" alt="Valid CSS!" />
		</a>
    </div>
    
</div> <!-- basket -->

</body>

</html>
