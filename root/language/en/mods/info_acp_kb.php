<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: info_acp_kb.php 425 2010-01-20 15:28:07Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ « » “ ” …
//

$lang = array_merge($lang, array(
	'ACL_TYPE_LOCAL_U_KB_'					=> 'Knowledge Base',
	'ACP_KB'								=> 'Knowledge Base Configuration',
	'ACP_KB_ARTICLE_TYPES'					=> 'Article Types Management',
	'ACP_KB_FEED_SETTINGS'					=> 'KB Feed settings',
	'ACP_KB_HEALTH_CHECK'					=> 'Mod Maintenance',
	'ACP_KB_HEALTH_CHECK_EXPLAIN'			=> 'This is where you can check that the knowledge base is up to date as well as run check to make sure it is running properly. You can also uninstall the Knowledge Base from here.',
	'ACP_KB_MANAGEMENT'						=> 'Knowledge Base Management',
	'ACP_KB_MENU_SETTINGS'					=> 'Menu settings',
	'ACP_KB_PLUGINS'						=> 'Plugins',
	'ACP_KB_PLUGIN_MENU'					=> 'Plugin Menu',
	'ACP_KB_POST_SETTINGS'					=> 'Article posting settings',
	'ACP_KB_SETTINGS'						=> 'General settings',
	'ACP_KB_SETTINGS_EXPLAIN'				=> 'Customise your knowledge base settings here',
	'ACP_MANAGE_CATS'						=> 'Manage Categories',
	'ACP_MANAGE_KB_TYPES'					=> 'Manage Article Types',

	'DISABLE_LEFT_MENU'						=> 'Disable left menu',
	'DISABLE_LEFT_MENU_EXPLAIN'				=> 'This will completely remove the left menu and disable all plugins shown in it. It will move any non plugin related information to the center menu.',
	'DISABLE_RIGHT_MENU'					=> 'Disable right menu',
	'DISABLE_RIGHT_MENU_EXPLAIN'			=> 'This will completely remove the right menu and disable all plugins shown in it. It will move any non plugin related information to the center menu.',

	'KB_ALLOW_SOCBOOK'						=> 'Enable social bookmarks',
	'KB_ALLOW_SOCBOOK_EXPLAIN'				=> 'Enable/disable all social bookmarks. If this is set to "no" the below option are irrelevant',
	'KB_ALLOW_BLOGGER'						=> 'Blogger',
	'KB_ALLOW_DELICIOUS'					=> 'Delicious',
	'KB_ALLOW_DIGG'							=> 'Digg',
	'KB_ALLOW_FACEBOOK'						=> 'Facebook',
	'KB_ALLOW_FRIEND'						=> 'Friend Feed',
	'KB_ALLOW_GOOGLE'						=> 'Google',
	'KB_ALLOW_LINKED_IN'					=> 'Linked in',
	'KB_ALLOW_LIVE'							=> 'Live',
	'KB_ALLOW_MIXX'							=> 'Mixx',
	'KB_ALLOW_MYSPACE'						=> 'Myspace',
	'KB_ALLOW_NETVIBES'						=> 'Netvibes',
	'KB_ALLOW_REDDIT'						=> 'Reddit',
	'KB_ALLOW_STUMBLE'						=> 'Stumble Upon',
	'KB_ALLOW_TECHNORATI'					=> 'Technorati',
	'KB_ALLOW_TWITTER'						=> 'Twitter',
	'KB_ALLOW_WORDPRESS'					=> 'Wordpress',

	'KB_ACP_LATEST_ART'						=> 'Show latest articles',
	'KB_ACP_LATEST_ART_EXPLAIN'				=> 'Specify how many articles you want listed on the category listing page. Use 0 to disable.',
	'KB_AJAX_RATING'						=> 'Enable ajax stars for rating',
	'KB_AJAX_RATING_EXPLAIN'				=> 'If you enable this option the user will not have to update the page to rate and article. Warning: This might not work on all servers.',
	'KB_CATS_PER_ROW'						=> 'Categories per row',
	'KB_CATS_PER_ROW_EXPLAIN'				=> 'Determine how many categories you want per row when using the modern look.',
	'KB_COLOR_ADDED'						=> 'Added color',
	'KB_COLOR_ADDED_EXPLAIN'				=> 'Hexidecimal color for the added request status',
	'KB_COLOR_PENDING'						=> 'Pending Color',
	'KB_COLOR_PENDING_EXPLAIN'				=> 'Hexidecimal color for the pending request status',
	'KB_COLOR_REQUEST'						=> 'Request Color',
	'KB_COLOR_REQUEST_EXPLAIN'				=> 'Hexidecimal color for the request request status',
	'KB_CLASSIC_LOOK'						=> 'Classic look',
	'KB_DEFAULT_RATING'						=> 'Default article rating',
	'KB_DESC_ART'							=> 'Show description on article page',
	'KB_DESC_CAT'							=> 'Show description when listing articles',
	'KB_DESC_MAX_CHARS'						=> 'Article description maximum length',
	'KB_DESC_MAX_CHARS_EXPLAIN'				=> 'Specify the maximum length of the article description. Leave as 0 to disable this feature.',
	'KB_DESC_MIN_CHARS'						=> 'Article description minimum length',
	'KB_DESC_MIN_CHARS_EXPLAIN'				=> 'Specify the minimum length of the article description. Leave as 0 to disable this feature.',
	'KB_DISABLE_DESC'						=> 'Disable article description',
	'KB_EMAIL_ARTICLE'						=> 'Enable emailing of articles to a friend',
	'KB_EMAIL_ARTICLE_EXPLAIN'				=> 'This feature requires that emailing is enabled for this phpBB installation.',
	'KB_EXP_ARTICLE'						=> 'Enable export of articles',
	'KB_EXP_ARTICLE_PDF'					=> 'Enable exporting in pdf format',
	'KB_EXP_ARTICLE_PDF_EXPLAIN'			=> '<a href="http://www.fpdf.org/" onclick="window.open(this.href); return false;">fpdf</a> is required to use this feature. <br /> Place the fpdf.php file in the includes directory.',
	'KB_EXT_HEADER'							=> 'Enable extended header on article page',
	'KB_EXT_HEADER_EXPLAIN'					=> 'If enabled an extended header will be shown on the article page, with information such as permanent link and article id.',
	'KB_LAYOUT_STYLE'						=> 'Category listing style',
	'KB_LAYOUT_STYLE_EXPLAIN'				=> 'Determine which layout you want, the modern look is a special designed for this mod, whereas classic is just equivalent to the forum listing of your style.',
	'KB_LEFT_MENU_TYPE'						=> 'Width type',
	'KB_LEFT_MENU_WIDTH'					=> 'Left menu width',
	'KB_LEFT_MENU_WIDTH_EXPLAIN'			=> 'Specify the width of the left column in numbers. Specify below whether it is in pixels or percent. Beware, changing these numbers might break your layout.',
	'KB_LIST_SUBCATS'						=> 'List subcategories on index page',
	'KB_MODERN_LOOK'						=> 'Modern look',
	'KB_MOD_NOTIFY_ENABLE'					=> 'Enable Moderator Notifications',
	'KB_MOD_NOTIFY_ENABLE_EXPLAIN'			=> 'This will notify moderators of article requests and articles awaiting approval',
	'KB_PDF_FORCE_DWNLD'					=> 'Force download of PDF file',
	'KB_PDF_FORCE_DWNLD_EXPLAIN'			=> 'Force the download or open in brower window',
	'KB_PERCENT'							=> 'Percent',
	'KB_PIXELS'								=> 'Pixels',
	'KB_REQUEST_STATUS_COLOR'				=> 'Request status color setting',
	'KB_REQUEST_STATUS_COLOR_ENABLE'		=> 'Enable status colors',
	'KB_RESET_DB'							=> 'The database has been successfully reset.',
	'KB_RESET_PLUGINS'						=> 'Plugins have been successfully reset',
	'KB_RESET_PERMS'						=> 'The permissions have successfully been reset.',
	'KB_RIGHT_MENU_TYPE'					=> 'Width type',
	'KB_RIGHT_MENU_WIDTH'					=> 'Right menu width',
	'KB_RIGHT_MENU_WIDTH_EXPLAIN'			=> 'Specify the width of the right column in numbers. Specify below whether it is in pixels or percent. Beware, changing these numbers might break your layout.',
	'KB_SEO_ENABLE'							=> 'Enable search engine optimization',
	'KB_SEO_ENABLE_EXPLAIN'					=> 'Enable search engine optimization for the Knowledge Base, only for category, tag and article pages. It uses the Apache rewrite module, so please make sure you have edited your .htaccess',
	'KB_SHOW_CONTRIB'						=> 'Show contributions list',
	'KB_SHOW_CONTRIB_EXPLAIN'				=> 'Enable or disable the list of contributors shown on the article page, note that the user needs permission to view article history to see it.',
	'KB_SHOW_RELART'						=> 'Show related articles',
	'KB_SHOW_RELART_EXPLAIN'				=> 'Determine how many related articles you want displayed in the related article box. Use 0 to disable.',
	'KB_SOCBOOK_LIST'						=> 'List of social bookmarks to be shown',
	'KB_SYNC_ARTICLE_COUNT'					=> 'The article syncing process was successful.',
	'KB_UNINSTALL_CONTINUE'					=> 'The uninstallation will continue to the next step within seconds. Please do not close your browser or this window while the page loads. Click %shere%s if the uninstallation doesn\'t automatically continue.',

	'LOG_CAT_ADD'							=> '<strong>Category added</strong><br /> - %1$s',
	'LOG_CAT_DELETE_ARTICLES_DELETE_CATS'	=> '<strong>Deleted category: %1$s</strong><br />Deleted articles and subcategories',
	'LOG_CAT_DELETE_ARTICLES_MOVE_CATS'		=> '<strong>Deleted category: %2$s</strong><br />Deleted articles<br />Moved subcategories to: %1$s',
	'LOG_CAT_EDIT'							=> '<strong>Category edited</strong><br /> - %1$s',
	'LOG_CAT_MOVE_ARTICLES_DELETE_CATS'		=> '<strong>Deleted category: %2$s</strong><br />Moved articles to: %1$s<br />Deleted subcategories',
	'LOG_CAT_MOVE_ARTICLES_MOVE_CATS'		=> '<strong>Deleted category: %3$s</strong><br />Moved articles to: %1$s<br />Moved subcategories to: %2$s',
	'LOG_CAT_MOVE_DOWN'						=> '<strong>Category %1$s moved down below</strong> <br /> - %2$s',
	'LOG_CAT_MOVE_UP'						=> '<strong>Category %1$s moved up above</strong> <br /> - %2$s',
	'LOG_CONFIG_PLUGINS'					=> '<strong>Changed KB Plugin settings</strong>',
	'LOG_CONFIG_SETTINGS'					=> '<strong>Changed Knowledge Base settings</strong>',
	'LOG_KB_INSTALL'						=> '<strong>Knowledge Base Mod installed</strong><br /> - Version %1$s',
	'LOG_KB_RESET_DB'						=> '<strong>Knowledge Base Mod database reset</strong>',
	'LOG_KB_RESET_PERMS'					=> '<strong>Knowledge Base Mod permissions reset</strong',
	'LOG_KB_RESET_PLUGINS'					=> '<strong>Knowledge Base Mod Plugins reset</strong>',
	'LOG_KB_SYNC_ARTICLE_COUNT'				=> '<strong>Knowledge Base Mod Article count synchronized</strong>',
	'LOG_KB_UNINSTALL'						=> '<strong>Knowledge Base Mod removed</strong>',
	'LOG_KB_UPDATED'						=> '<strong>Knowledge Base Mod updated</strong><br /> - From version %2$s to %1$s',
	'LOG_TYPE_ADD'							=> '<strong>Article type added</strong><br /> - %1$s ',
	'LOG_TYPE_DELETE'						=> '<strong>Article type deleted </strong>',
	'LOG_TYPE_EDIT'							=> '<strong>Article type edited</strong><br /> - %1$s ',
	'LOG_TYPE_MOVE_DOWN'					=> '<strong>Article type %1$s moved down</strong>',
	'LOG_TYPE_MOVE_UP'						=> '<strong>Article type %1$s moved up </strong>',
	'LOG_U_KB_ROLE_ADD'						=> '<strong>KB role added</strong><br /> - %1$s',
	'LOG_U_KB_ROLE_EDIT'					=> '<strong>KB role edited</strong><br /> - %1$s',
	'LOG_U_KB_ROLE_REMOVED'					=> '<strong>KB role removed</strong><br /> - %1$s',

	'RESET_DB'								=> 'Reset Database',
	'RESET_DB_CONFIRM'						=> '<span style="color:#ff0000"><b>Warning: This cannot be undone</b></span>. <br />This will reset the Knowledge Base database, not removing the tables, but emptying them so you have a fresh install ready to go. It will also remove all attachments associated with the mod.',
	'RESET_PERMS'							=> 'Reset Permissions',
	'RESET_PERMS_CONFIRM'					=> '<span style="color:#ff0000"><b>Warning: This cannot be undone</b></span>. <br />This will reset all Knowledge Base permissions and roles back to their original installation settings.',
	'RESET_PLUGINS'							=> 'Reset Plugins',
	'RESET_PLUGINS_CONFIRM'					=> '<span style="color:#ff0000"><b>Warning: This cannot be undone</b></span>. <br />This will reset all Knowledge Base plugins back to their original installation settings.',

	'SYNC_ARTICLE_COUNT'					=> 'Synchronize Article Count',
	'SYNC_ARTICLE_COUNT_CONFIRM'			=> '<span style="color:#ff0000"><b>Warning: This cannot be undone</b></span>. <br />This will synchronize the article count of users with articles they have written currently in the database.',
	

	'UNINSTALL_CONFIRM'						=> '<span style="color:#ff0000"><b>Warning: This cannot be undone</b></span>. <br />This will uninstall the mod completely, removing both data, permissions, configs and tables.',
));

?>