<?php
function wiki_link($pagename, $linkname="Help") {
        echo '<li>';
        echo '<a href="http://www.svi.nl/' . $pagename . '" target="_blank">';
        echo '<img src="images/help.png" alt="help" />';
        echo '&nbsp;' . $linkname;
        echo '</a>';
        echo '</li>';
}
?>
