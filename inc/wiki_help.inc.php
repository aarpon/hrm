<?php

/*!
  \brief Generate HTML code to link to a specific page in the SVI wiki.

  Assemble an HTML link opening a new window/tab that points to a certain page
  in the SVI wiki. The link is enclosed in "list-item" tags and has the default
  label "Help" unless the linkname parameter is specified.

  \param  $pagename  The title of the page in the SVI wiki.
  \param  $linkname  The string used for the link in the HTML.
 */
function wiki_link($pagename, $linkname="Help") {
        echo '<li>';
        echo '<a href="http://www.svi.nl/' . $pagename . '" target="_blank">';
        echo '<img src="images/help.png" alt="help" />';
        echo '&nbsp;' . $linkname;
        echo '</a>';
        echo '</li>';
}
?>
