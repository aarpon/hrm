<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/**
 * Class Nav
 * Commodity class to manage all links and actions of the navigation bar.
 *
 * All items are enclosed in <li> tags.
 */
class Nav
{

    /**
     * Generate HTML code to link to a specific page in the SVI wiki.
     *
     * @param string $pageName The title of the page in the SVI wiki.
     * @param string $text The string used for the link in the HTML.
     * @return string HTML code to link to the requested wiki page.
     */
    public static function linkWikiPage($pageName)
    {
        return self::buildLinkHTMLElement("Help",
            "https://svi.nl/$pageName", "images/help.png",
            "Help", true);

    }

    /**
     * Generate HTML code to link to the HRM project website.
     *
     * @return string HTML code to link to the HRM project website.
     */
    public static function linkProjectWebsite()
    {
        return self::buildLinkHTMLElement("HRM Project Website",
            "http://www.huygens-rm.org", "images/logo_small.png",
            "HRM Project Website", true);
    }

    /**
     * Generate HTML code to link to the HRM mailing list page.
     *
     * @return string HTML code to link to the HRM mailing list page.
     */
    public static function linkMailingList()
    {
        return self::buildLinkHTMLElement("Sign up to HRM news",
            "https://lists.sourceforge.net/lists/listinfo/hrm-list",
            "images/email.png", "Sign up to HRM news", true);
    }

    /**
     * Generate HTML code to link to the SVI wiki.
     *
     * @return string HTML code to link to the SVI wiki.
     */
    public static function linkSVIWiki()
    {
        return self::buildLinkHTMLElement("SVI wiki",
            "http://www.svi.nl/FrontPage",
            "images/wiki.png", "SVI wiki", true);
    }

    /**
     * Generate HTML code to link to the HRM manual.
     *
     * @return string HTML code to link to the HRM manual.
     */
    public static function linkManual()
    {
        return self::buildLinkHTMLElement("HRM manual",
            "http://huygens-remote-manager.readthedocs.org/en/latest/user/index.html",
            "images/manual.png", "HRM manual", true);
    }

    /**
     * Generate HTML code to link to the report issue page.
     *
     * @return string HTML code to link to the report issue page.
     */
    public static function linkReportIssue()
    {
        return self::buildLinkHTMLElement("Bug report",
            "http://hrm.svi.nl:8080/redmine/projects/public/issues/new",
            "images/report_issue.png", "Bug report", true);
    }

    /**
     * Generate HTML code to link to the What's New? page.
     *
     * @return string HTML code to link to the What's new?.
     */
    public static function linkWhatsNew()
    {
        return self::buildLinkHTMLElement("What's new?",
            "https://github.com/aarpon/hrm/releases/latest",
            "images/whatsnew.png",
            "What's new?", true);
    }

    /**
     * Generate HTML code to link to the raw images page.
     *
     * @return string HTML code to link to the raw images page.
     */
    public static function linkRawImages()
    {
        return self::buildLinkHTMLElement("Raw images",
            "file_management.php?folder=src",
            "images/rawdata_small.png",
            "Raw images", false);
    }

    /**
     * Generate HTML code to link to the results page.
     *
     * @return string HTML code to link to the results page.
     */
    public static function linkResults()
    {
        return self::buildLinkHTMLElement("Results",
            "file_management.php?folder=dest",
            "images/results_small.png",
            "Results", false);
    }

    /**
     * Generate HTML code to link to the home page.
     *
     * This function requires the URL of the current page. You can
     * use the getThisPageName() function from Util.inc.php to get it.
     * 
     * @param string $currentPage URL of the current page. 
     * @return string HTML code to link to the home page.
     */
    public static function linkHome($currentPage)
    {
        return self::buildLinkHTMLElement("Home",
            $currentPage . "?home=home",
            "images/home.png",
            "Home", false);
    }

    /**
     * Generate HTML code to the logout link.
     *
     * This function requires the URL of the current page. You can
     * use the getThisPageName() function from Util.inc.php to get it.
     *
     * @param string $currentPage URL of the current page.
     * @return string HTML code to the logout link.
     */
    public static function linkLogOut($currentPage)
    {
        return self::buildLinkHTMLElement("Logout",
            $currentPage . "?exited=exited",
            "images/exit.png",
            "Logout", false);
    }

    /**
     * Generate HTML code to link to the job queue page.
     *
     * @return string HTML code to link to the job queue page.
     */
    public static function linkJobQueue()
    {
        return self::buildLinkHTMLElement("Queue",
            "job_queue.php",
            "images/queue_small.png",
            "Queue", false);
    }

    /**
     * Generate HTML code to display the user name.
     *
     * @param string $userName User name.
     * @return string HTML code to display the user name.
     */
    public static function textUser($userName)
    {
        return "<li><img src=\"images/user.png\" alt=\"User\" />" .
            "&nbsp;" . $userName . "</li>";
    }

    /**
     * Generate HTML code to link back to a specified page.
     *
     * @param string $toURL URL to the page to go back to.
     * @return string HTML code to link to the requested page.
     */
    public static function linkBack($toURL)
    {
        return self::buildLinkHTMLElement("Back", $toURL,
            "images/back_small.png" , "Back", false);
    }

    /**
     * @return string HTML code to link back to the login page.
     */
    public static function exit_to_login()
    {
        return self::buildLinkHTMLElement("Exit", "login.php",
            "images/exit.png" , "Exit", false);
    }

    /**
     * @return string HTML code to trigger the check for updates action.
     */
    public static function actionCheckForUpdates()
    {
        return self::buildActionHTMLElement("Check for updates",
            "images/check_for_update.png" , "Check for updates",
            "checkForUpdates();");
    }


    /**
     * Build <li> HTML element to draw in the navigation for a link.
     * @param string $text Link text.
     * @param string $url Link URL.
     * @param string $img_url Image URL.
     * @param string $altText Alternative text for the image.
     * @param bool $extern True if the link must be opened in another tab/window.
     *                     false otherwise.
     * @return string HTML string to be echoed in the page.
     */
    private static function buildLinkHTMLElement($text, $url, $img_url,
                                                 $altText, $extern = false)
    {
        $onclick = "";
        if ($extern == true) {
            $onclick = 'onclick="this.target=\'_blank\';return true;"';
        }
        $html = '<li><a href="' . $url . '" ' . $onclick . '>' .
            '<img src="' . $img_url . '" ' .
            'alt="' . $altText . '" />&nbsp;' .
            $text . '</a></li>';

        return $html;
    }

    /**
     * Build <li> HTML element to draw in the navigation for an action.
     * @param string $text Link text.
     * @param string $img_url Image URL.
     * @param string $altText Alternative text for the image.
     * @param string $action Javascript call to be inserted in the onclick="" event.
     * @return string HTML string to be echoed in the page.
     */
    private static function buildActionHTMLElement($text, $img_url, $altText,
                                                   $action)
    {
        $onclick = 'onclick="' . $action . '"';
        $html = '<li><a href="#" ' . $onclick . '>' .
            '<img src="' . $img_url . '" ' .
            'alt="' . $altText . '" />&nbsp;' .
            $text . '</a></li>';

        return $html;
    }
}
