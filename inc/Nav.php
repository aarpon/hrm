<?php
/**
 * Nav
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

/**
 * Commodity class to manage all links and actions of the navigation bar.
 *
 * All items are enclosed in <li> tags.
 *
 * @package hrm
 */
class Nav
{

    /**
     * Generate HTML code to link to a specific page in the SVI wiki.
     *
     * @param string $pageName The title of the page in the SVI wiki.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the requested wiki page.
     */
    public static function linkWikiPage($pageName, $wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Help",
            "https://svi.nl/$pageName",
            "images/help.png",
            "Help",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the HRM project website.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the HRM project website.
     */
    public static function linkProjectWebsite($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "HRM Project Website",
            "http://www.huygens-rm.org",
            "images/logo_small.png",
            "HRM Project Website",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the HRM mailing list page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the HRM mailing list page.
     */
    public static function linkMailingList($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Sign up to HRM news",
            "https://lists.sourceforge.net/lists/listinfo/hrm-list",
            "images/email.png",
            "Sign up to HRM news",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the SVI wiki.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the SVI wiki.
     */
    public static function linkSVIWiki($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "SVI wiki",
            "http://www.svi.nl/FrontPage",
            "images/wiki.png",
            "SVI wiki",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the HRM manual.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the HRM manual.
     */
    public static function linkManual($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "HRM manual",
            "http://huygens-remote-manager.readthedocs.io/en/latest/user/index.html",
            "images/manual.png",
            "HRM manual",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the report issue page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the report issue page.
     */
    public static function linkReportIssue($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Feedback",
            "http://hrm.svi.nl:8080/redmine/projects/public/issues/new",
            "images/report_issue.png",
            "Bug report",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the What's New? page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the What's new? page.
     */
    public static function linkWhatsNew($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "What's new?",
            "https://github.com/aarpon/hrm/releases/latest",
            "images/whatsnew.png",
            "What's new?",
            true,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the Credits page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the Credits page.
     */
    public static function linkCredits($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Credits",
            "credits.php",
            "images/credits.png",
            "Credits",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the raw images page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the raw images page.
     */
    public static function linkRawImages($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Raw images",
            "file_management.php?folder=src",
            "images/rawdata_small.png",
            "Raw images",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the results page.

     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the results page.
     */
    public static function linkResults($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Results",
            "file_management.php?folder=dest",
            "images/results_small.png",
            "Results",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the home page.
     *
     * This function requires the URL of the current page. You can
     * use the Util::getThisPageName() to get it.
     *
     * @param string $currentPage URL of the current page.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the home page.
     */
    public static function linkHome($currentPage, $wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Home",
            $currentPage . "?home=home",
            "images/home.png",
            "Home",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to the login link.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @param bool $showSignUp True if the sign up button should be displayed, false otherwise.
     * @param string $req Request.
     * @return string HTML code to the login link.
     */
    public static function linkLogIn($wrapInLiElement = true, $showSignUp = true, $req = "")
    {
        $openingLi = "";
        $closingLi = "";
        if ($wrapInLiElement == true) {
            $openingLi = "<li>";
            $closingLi = "</li>";
        }

        if ($showSignUp == true) {
            $signUpElement = <<<EOT
<button type="button" class="button" value="signup" onclick="location.href = 'registration.php';">Sign up</button>
EOT;
        } else {
            $signUpElement = "";
        }

        $html = <<<EOT
<div id = "login">
<img src="images/user.png" alt="Login"/>
<form method="post" action="login.php">
<label for="username">User name</label>
<input id="username" name="username" type="text" class="textfield" tabindex="1"/>
<label for="password">Password</label>
<input id="password" name="password" type="password" class="textfield" tabindex="2"/>
<input type="hidden" name="request" value="$req"/>
<button type="submit" class="dark" value="login">Log in</button>
$signUpElement
<button type="button" class="button" value="reset" onclick="location.href = 'reset_password.php';">Reset</button>
</form>
</div>
EOT;
        return $openingLi . $html . $closingLi;
    }

    /**
     * Generate HTML code to the logout link.
     *
     * This function requires the URL of the current page. You can
     * use the Util::getThisPageName() to get it.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @param string $currentPage URL of the current page.
     * @return string HTML code to the logout link.
     */
    public static function linkLogOut($currentPage, $wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Logout",
            $currentPage . "?exited=exited",
            "images/exit.png",
            "Logout",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link to the job queue page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the job queue page.
     */
    public static function linkJobQueue($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Queue",
            "job_queue.php",
            "images/queue_small.png",
            "Queue",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to display the user name.
     *
     * @param string $userName User name.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to display the user name.
     */
    public static function textUser($userName, $wrapInLiElement = true)
    {
        $openingLi = "";
        $closingLi = "";
        if ($wrapInLiElement == true) {
            $openingLi = "<li>";
            $closingLi = "</li>";
        }

        return $openingLi . "<img src=\"images/user.png\" alt=\"User\" />" .
        "&nbsp;" . $userName . $closingLi;
    }

    /**
     * Generate HTML code to link back to a specified page.
     *
     * @param string $toURL URL to the page to go back to.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link to the requested page.
     */
    public static function linkBack($toURL, $wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Back",
            $toURL,
            "images/back_small.png",
            "Back",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to link back (exit) to the login page.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to link back to the login page.
     */
    public static function exitToLogin($wrapInLiElement = true)
    {
        return self::buildLinkHTMLElement(
            "Exit",
            "login.php",
            "images/exit.png",
            "Exit",
            false,
            $wrapInLiElement,
            ""
        );
    }

    /**
     * Generate HTML code to display the theme selector.
     *
     * @return string HTML code to display the theme selector.
     */
    public static function actionStyleToggle()
    {
        $html = <<<EOT
<li>
<div class="theme_div">
<form>
<label>Theme:</label>
<input type="submit" class="theme_name" onclick="switch_style('dark');return false;" name="theme" value="dark">
<input type="submit" class="theme_name" onclick="switch_style('light');return false;" name="theme" value="light">
</form>
</div>
</li>
EOT;
        return $html;
    }

    /**
     * Generate HTML code to trigger the check for updates action.
     *
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML code to trigger the check for updates action.
     */
    public static function actionCheckForUpdates($wrapInLiElement = true)
    {
        return self::buildActionHTMLElement(
            "Check for updates",
            "images/check_for_update.png",
            "Check for updates",
            "checkForUpdates();",
            $wrapInLiElement
        );
    }

    /**
     * Generate HTML code for a dropdown menu with the external support links to various documentation entries.
     *
     * @return string HTML code to render the documentation pull-down menu.
     */
    public static function externalSupportLinks()
    {
        $linkToSVIWiki = Nav::linkSVIWiki($wrapInLiElement = false);
        $linkToProjectWebsite = Nav::linkProjectWebsite($wrapInLiElement = false);
        $linkToMailingList = Nav::linkMailingList($wrapInLiElement = false);
        $linkToWhatsNew = Nav::linkWhatsNew($wrapInLiElement = false);
        $linkToCredits = Nav::linkCredits($wrapInLiElement = false);

        $html = <<<EOT
<li>
    <div class="dropdown">
        <img src="images/resources.png" alt="Resources" />
        <button onclick="expandExternalSupportLinksDropdownMenu()" class="dropbtn">Resources</button>
        <div id="supportDropdownMenu" class="dropdown-content">
            $linkToSVIWiki
            $linkToProjectWebsite
            $linkToMailingList
            $linkToWhatsNew
            $linkToCredits
        </div>
    </div>
</li>
<!-- Ajax functions -->
<script type="text/javascript">

    /* When the user clicks on the button, toggle between hiding and showing the dropdown content */
    function expandExternalSupportLinksDropdownMenu() {
        $("#supportDropdownMenu").toggle()
    }

    // Close the dropdown menu if the user clicks outside of it
    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {

            var dropdowns = document.getElementsByClassName("dropdown-content");
            var i;
            for (i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

</script>
EOT;
        return $html;
    }

    /**
     * Build HTML element to draw in the navigation for a link.
     *
     * Please notice, that it might still need to be wrapped in a <li></li> element.
     * @param string $text Link text.
     * @param string $url Link URL.
     * @param string $img_url Image URL.
     * @param string $altText Alternative text for the image.
     * @param bool $extern True if the link must be opened in another tab/window.
     *                     false otherwise.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @param string $onclick Add string defining javascript call for an onclick event.
     *                        The 'onclick=' fragment must be omitted!
     * @return string HTML string to be echoed in the page.
     */
    private static function buildLinkHTMLElement(
        $text,
        $url,
        $img_url,
        $altText,
        $extern = false,
        $wrapInLiElement = true,
        $onclick = ""
    ) {
        $openingLi = "";
        $closingLi = "";
        if ($wrapInLiElement == true) {
            $openingLi = "<li>";
            $closingLi = "</li>";
        }

        if ($onclick != "") {
            if ($extern == true) {
                $onclick = 'onclick=' . $onclick . ";this.target=\'_blank\';return true;";
            } else {
                $onclick = 'onclick=' . $onclick;
            }
        } else {
            if ($extern == true) {
                $onclick = 'onclick="this.target=\'_blank\';return true;"';
            }
        }
        $html = $openingLi . '<a href="' . $url . '" ' . $onclick . '>' .
            '<img src="' . $img_url . '" ' .
            'alt="' . $altText . '" />&nbsp;' .
            $text . '</a>' . $closingLi;

        return $html;
    }

    /**
     * Build <li> HTML element to draw in the navigation for an action.
     * @param string $text Link text.
     * @param string $img_url Image URL.
     * @param string $altText Alternative text for the image.
     * @param string $action Javascript call to be inserted in the onclick="" event.
     * @param bool $wrapInLiElement Wrap the link in a <li></li> element.
     *             Optional, default is true.
     * @return string HTML string to be echoed in the page.
     */
    private static function buildActionHTMLElement(
        $text,
        $img_url,
        $altText,
        $action,
        $wrapInLiElement = true
    ) {
        $openingLi = "";
        $closingLi = "";
        if ($wrapInLiElement == true) {
            $openingLi = "<li>";
            $closingLi = "</li>";
        }

        $onclick = 'onclick="' . $action . '"';
        $html = $openingLi . '<a href="#" ' . $onclick . '>' .
            '<img src="' . $img_url . '" ' .
            'alt="' . $altText . '" />&nbsp;' .
            $text . '</a>' . $closingLi;

        return $html;
    }
}
