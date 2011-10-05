<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// session_start();

$loggedIn = ( isset($_SESSION['user'] ) && $_SESSION['user']->isLoggedIn( ) );

?>

    <div id="footer">
        created 2004 by
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:volker.baecker@mri.cnrs.fr">' .
                    'Volker Baecker</a>';
			} else
				echo "<span onmouseover=\"" .
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Volker Baecker</span>";
		?>
		<br />extended 2006-2011 by
		<?php
			if ( $loggedIn == true ) {
				echo 'Asheesh Gulati';  // Former developer - no email info
			} else
				echo "<span onmouseover=\"" .
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Asheesh Gulati</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo 'Alessandra Griffa';  // Former developer - no email info
			} else
				echo "<span onmouseover=\"".
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Alessandra Griffa</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo 'Jos&eacute; Vi&ntilde;a';  // Former developer - no email info
			} else
				echo "<span onmouseover=\"".
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Jos&eacute; Vi&ntilde;a</span>";
		?>,
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:daniel@svi.nl">Daniel Sevilla</a>';
			} else
				echo "<span onmouseover=\"" .
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Daniel Sevilla</span>";
		?> &amp;
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:aaron.ponti@fmi.ch">Aaron Ponti</a>';
			} else
				echo "<span onmouseover=\"" .
                    "Tip('Login to see contact information.' )\"" .
					"onmouseout=\"UnTip()\">Aaron Ponti</span>";
		?>
	</div>
    
    <div id="validation">
        <a href="http://validator.w3.org/" onclick="clean()">
            <img style="border:0;width:88px;height:31px"
                src="images/valid-xhtml10.png" alt="Valid XHTML 1.0 Strict" />
        </a>
		<a href="http://jigsaw.w3.org/css-validator/" onclick="clean()">
			<img style="border:0;width:88px;height:31px"
                src="images/valid-css21.png" alt="Valid CSS!" />
		</a>
    </div>
    
</div> <!-- basket -->

</body>

</html>
