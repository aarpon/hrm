<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \brief Generate HTML code to link to a specific page in the SVI wiki.

  Assemble an HTML link opening a new window/tab that points to a certain page
  in the SVI wiki. The link is enclosed in "list-item" tags and has the default
  label "Help" unless the linkname parameter is specified.

  \param  $pagename  The title of the page in the SVI wiki.
  \param  $linkname  The string used for the link in the HTML.

  \return $html  A string containing the HTML code.
 */
function get_wiki_link($pagename, $linkname="Help") {
        $html = '<li>';
        $html .= '<a href="http://www.svi.nl/' . $pagename . '" ';
        $html .= 'onclick="this.target=\'_blank\';return true;">';
        $html .= '<img src="images/help.png" alt="help" />';
        $html .= '&nbsp;' . $linkname;
        $html .= '</a>';
        $html .= '</li>';
        return $html;
}

/*!
  \brief Print HTML code to link to a specific page in the SVI wiki.

  A wrapper function for get_wiki_link() that directly prints the generated HTML
  code.

  \param  $pagename  The title of the page in the SVI wiki.
  \param  $linkname  The string used for the link in the HTML.
 */
function wiki_link($pagename, $linkname="Help") {
        echo get_wiki_link($pagename, $linkname);
}
?>
