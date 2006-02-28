<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +-------------------------------------------------------------------+
// | WiFiDog Authentication Server                                     |
// | =============================                                     |
// |                                                                   |
// | The WiFiDog Authentication Server is part of the WiFiDog captive  |
// | portal suite.                                                     |
// +-------------------------------------------------------------------+
// | PHP version 5 required.                                           |
// +-------------------------------------------------------------------+
// | Homepage:     http://www.wifidog.org/                             |
// | Source Forge: http://sourceforge.net/projects/wifidog/            |
// +-------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or     |
// | modify it under the terms of the GNU General Public License as    |
// | published by the Free Software Foundation; either version 2 of    |
// | the License, or (at your option) any later version.               |
// |                                                                   |
// | This program is distributed in the hope that it will be useful,   |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of    |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     |
// | GNU General Public License for more details.                      |
// |                                                                   |
// | You should have received a copy of the GNU General Public License |
// | along with this program; if not, contact:                         |
// |                                                                   |
// | Free Software Foundation           Voice:  +1-617-542-5942        |
// | 59 Temple Place - Suite 330        Fax:    +1-617-542-2652        |
// | Boston, MA  02111-1307,  USA       gnu@gnu.org                    |
// |                                                                   |
// +-------------------------------------------------------------------+

/**
 * Displays the portal page
 *
 * @package    WiFiDogAuthServer
 * @author     Philippe April
 * @author     Benoit Gregoire <bock@step.polymtl.ca>
 * @author     Max Horvath <max.horvath@maxspot.de>
 * @copyright  2004-2006 Philippe April
 * @copyright  2004-2006 Benoit Gregoire, Technologies Coeus inc.
 * @copyright  2006 Max Horvath, maxspot GmbH
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required files
 */
require_once('../include/common.php');

require_once('include/common_interface.php');
require_once('classes/Node.php');
require_once('classes/MainUI.php');
require_once('classes/Session.php');

/*
 * Check for missing URL switch
 */
if (isset($_REQUEST['missing']) && $_REQUEST['missing'] == "url") {
    $ui = new MainUI();
    $ui->displayError(_('For some reason, we were unable to determine the web site you initially wanted to see.  You should now enter a web address in your URL bar.'), false);
    exit;
}

// Init values
$node = null;
$show_more_link = false;

// Init session
$session = new Session();

// Get the current user
$current_user = User::getCurrentUser();

/*
 * Start general request parameter processing section
 */
if (!empty($_REQUEST['gw_id'])) {
    try {
        $node = Node::getObject($_REQUEST['gw_id']);
        $network = $node->getNetwork();
    }

    catch (Exception $e) {
        $ui = new MainUI();
        $ui->displayError($e->getMessage());
        exit;
    }
} else {
    $ui = new MainUI();
    $ui->displayError(_("No Hotspot specified!"));
    exit;
}

/*
 * If this node has a custom portal defined, and the network config allows it,
 * redirect to the custom portal
 */
$custom_portal_url = $node->getCustomPortalRedirectUrl();

if (!empty($custom_portal_url) && $network->getCustomPortalRedirectAllowed()) {
    header("Location: {$custom_portal_url}");
}

$node_id = $node->getId();
$portal_template = $node_id.".html";
Node::setCurrentNode($node);

if (isset($session)) {
    if (!empty($_REQUEST['gw_id'])) {
        $session->set(SESS_GW_ID_VAR, $_REQUEST['gw_id']);
    }
}

$current_node = Node::getCurrentNode();
$current_node_id = $current_node->getId();

// Init ALL smarty SWITCH values
$smarty->assign('sectionTOOLCONTENT', false);
$smarty->assign('sectionMAINCONTENT', false);

// Init ALL smarty values
$smarty->assign('currentNode', null);
$smarty->assign('numOnlineUsers', 0);
$smarty->assign('onlineUsers', array());
$smarty->assign('userIsAtHotspot', false);
$smarty->assign('noUrl', true);
$smarty->assign('url', "");
$smarty->assign('accountValidation', false);
$smarty->assign('validationTime', 20);
$smarty->assign('hotspotNetworkUrl', "");
$smarty->assign('hotspotNetworkName', "");
$smarty->assign('networkLogoBannerUrl', "");
$smarty->assign('networkContents', false);
$smarty->assign('networkContentArray', array());
$smarty->assign('nodeHomepage', false);
$smarty->assign('nodeURL', "");
$smarty->assign('nodeName', "");
$smarty->assign('nodeContents', false);
$smarty->assign('nodeContentArray', array());
$smarty->assign('userContents', false);
$smarty->assign('userContentArray', array());

/*
 * Tool content
 */

// Set section of Smarty template
$smarty->assign('sectionTOOLCONTENT', true);

// Set details about node
$smarty->assign('currentNode', $current_node);

// Set details about onlineusers
$online_users = $current_node->getOnlineUsers();
$num_online_users = count($online_users);

foreach ($online_users as $online_user) {
    $roles = array();

    if ($current_node->isOwner($online_user)) {
        $roles[] = _("owner");
    }

    if ($current_node->isTechnicalOfficer($online_user)) {
        $roles[] = _("technical officer");
    }

    if ($roles) {
        $rolenames = join($roles, ",");
    }

    $online_user_array[] = array('Username' => $online_user->getUsername(), 'showRoles' => count($roles) > 0, 'roles' => $rolenames);
}

$smarty->assign('numOnlineUsers', $num_online_users);

if ($num_online_users > 0) {
    $smarty->assign('onlineUsers', $online_user_array);
}

// Check for requested URL and if user is at a hotspot
$original_url_requested = $session->get(SESS_ORIGINAL_URL_VAR);

$smarty->assign('userIsAtHotspot', Node::getCurrentRealNode() != null ? true : false);

if (empty($original_url_requested)) {
    $smarty->assign('noUrl', true);
    $smarty->assign('url', "?missing=url");
} else {
    $smarty->assign('noUrl', true);
    $smarty->assign('url', $original_url_requested);
}

// Compile HTML code
$tool_html = $smarty->fetch("templates/sites/portal.tpl");

/*
 * Main content
 */

// Reset ALL smarty SWITCH values
$smarty->assign('sectionTOOLCONTENT', false);
$smarty->assign('sectionMAINCONTENT', false);

// Set section of Smarty template
$smarty->assign('sectionMAINCONTENT', true);

// While in validation period, alert user that he should validate his account ASAP
if ($current_user && $current_user->getAccountStatus() == ACCOUNT_STATUS_VALIDATION) {
    $smarty->assign('accountValidation', true);
    $smarty->assign('validationTime', ($current_user->getNetwork()->getValidationGraceTime() / 60));
}

/*
 * Network section
 */

// Set network details
$smarty->assign('hotspotNetworkUrl', $network->getHomepageURL());
$smarty->assign('hotspotNetworkName', $network->getName());
$smarty->assign('networkLogoBannerUrl', COMMON_CONTENT_URL . NETWORK_LOGO_BANNER_NAME);

// Get all network content and EXCLUDE user subscribed content
if ($current_user) {
    $contents = Network::getCurrentNetwork()->getAllContent(true, $current_user);
} else {
    $contents = Network::getCurrentNetwork()->getAllContent();
}

if ($contents) {
    foreach ($contents as $content) {
        $contentArray[] = array('isDisplayableAt' => $content->isDisplayableAt($node), 'userUI' => $content->getUserUI());
    }

    // Set all content of current node
    $smarty->assign('networkContents', true);
    $smarty->assign('networkContentArray', $contentArray);
}

/*
 * Node section
 */

// Get all node content and EXCLUDE user subscribed content
if ($current_user) {
    $contents = $node->getAllContent(true, $current_user);
} else {
    $contents = $node->getAllContent();
}

// Set homepage details of node
$node_homepage = $node->getHomePageURL();
if (!empty($node_homepage)) {
    $smarty->assign('nodeHomepage', true);
    $smarty->assign('nodeURL', $node_homepage);
    $smarty->assign('nodeName', $node->getName());
}

if ($contents) {
    foreach ($contents as $content) {
        // Check for content requirements to show the "Show all contents" link
        if (!$show_more_link) {
            if ($content->getObjectType() == "ContentGroup") {
                if (method_exists($content, "isArtisticContent") && method_exists($content, "isLocativeContent")) {
                    if ($content->isArtisticContent() && $content->isLocativeContent()) {
                        $show_more_link = true;
                    }
                }
            }
        }

        $contentArray[] = array('isDisplayableAt' => $content->isDisplayableAt($node), 'userUI' => $content->getUserUI());
    }

    // Set all content of current node
    $smarty->assign('nodeContents', true);
    $smarty->assign('nodeContentArray', $contentArray);
}

/*
 * User section
 */

if ($current_user) {
    $contents = User::getCurrentUser()->getAllContent();

    if ($contents) {
        foreach ($contents as $content) {
            $contentArray[] = array('userUI' => $content->getUserUI());
        }

        // Set all content of current node
        $smarty->assign('userContents', true);
        $smarty->assign('userContentArray', $contentArray);
    }
}

// Hyperlinks to full content display page
if ($show_more_link) {
    $smarty->assign('showMoreLink', true);
    $smarty->assign('currentNodeId', $current_node_id);
}

// Compile HTML code
$html_body = $smarty->fetch("templates/sites/portal.tpl");

/*
 * Render output
 */

$ui = new MainUI();
$ui->setToolContent($tool_html);
$ui->setMainContent($html_body);
$ui->display();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>