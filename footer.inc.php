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
				echo "<span onmouseover=" .
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Volker Baecker</span>";
		?>
        and released under the terms of the 
        <a href="http://huygens-rm.org/home/?q=node/26">CeCILL license</a>
		<br />extended 2006-2012 by
		<?php
			if ( $loggedIn == true ) {
				echo 'Asheesh Gulati';  // Former developer - no email info
			} else
				echo "<span onmouseover=" .
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Asheesh Gulati</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo 'Alessandra Griffa';  // Former developer - no email info
			} else
				echo "<span onmouseover=".
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Alessandra Griffa</span>";
		?>, 
		<?php
			if ( $loggedIn == true ) {
				echo 'Jos&eacute; Vi&ntilde;a';  // Former developer - no email info
			} else
				echo "<span onmouseover=".
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Jos&eacute; Vi&ntilde;a</span>";
		?>,
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:daniel@svi.nl">Daniel Sevilla</a>';
			} else
				echo "<span onmouseover=" .
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Daniel Sevilla</span>";
		?> &amp;
		<?php
			if ( $loggedIn == true ) {
				echo '<a href="mailto:aaron.ponti@fmi.ch">Aaron Ponti</a>';
			} else
				echo "<span onmouseover=" .
                    "\"Tip('Login to see contact information.' )\" " .
					"onmouseout=\"UnTip()\">Aaron Ponti</span>";
		?>
	</div>
    
    <div id="validation">
        <a href="javascript:openWindow(
           'http://validator.w3.org/')" onclick="clean()">
            <img style="border:0;width:88px;height:31px"
                src="images/valid-xhtml10.png" alt="Valid XHTML 1.0 Strict" />
        </a>
		<a href="javascript:openWindow(
           'http://jigsaw.w3.org/css-validator/')" onclick="clean()">
			<img style="border:0;width:88px;height:31px"
                src="images/valid-css21.png" alt="Valid CSS!" />
		</a>
    </div>

    </div> <!-- basket -->
   
    <!-- Include jQuery and jQuery-qTip --> 
    <script type="text/javascript" src="scripts/jquery-1.7.2.min.js"></script>
    <script type="text/javascript" src="scripts/jquery.qtip-1.0.0-rc3.min.js"></script>

    <!-- Tooltips -->
    <?php
        if (isset($tooltips) && is_array($tooltips)) {
    ?>

    <script type="text/javascript">
        $(document).ready( function( ) {
            
    <?php
        $keys = array_keys($tooltips);
        foreach ($keys as $key) {
    ?>
    
        $("#<?php echo $key; ?>").qtip({
            content: '<?php echo $tooltips[$key]; ?>',
            show: 'mouseover',
            hide: 'mouseout',
            style: {
                padding: 5,
                background: '#FFFFE0',
                textAlign: 'center',
                border: {
                    width: 1,
                    radius: 2,
                    color: 'darkgray'
                },
                'font-size': '90%'
            }
        });
        
    <?php
            }
    ?>
        } );
        </script>
    <?php
    }
    ?>

</body>

</html>

    