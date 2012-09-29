<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: info_ucp_kb.php 425 2010-01-20 15:28:07Z softphp $
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
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'UCP_KB'					=> 'Knowledge Base',
	'UCP_KB_ARTICLES'			=> 'Article Status',
	'UCP_KB_ARTICLES_EXPLAIN'	=> 'On this page you can view your submitted articles status and chat with moderators about your articles.',
	'UCP_KB_BOOKMARKS'			=> 'Article Bookmarks',
	'UCP_KB_BOOKMARKS_EXPLAIN'	=> 'Here you can view and remove bookmarked articles.',
	'UCP_KB_FRONT'				=> 'Front page',
	'UCP_KB_FRONT_EXPLAIN'		=> 'Welcome to the Knowledge Base part of the User Control Panel. From here you can manage your bookmarked and subscribed articles. You can also keep track of your article status, and look at moderator comments.',
	'UCP_KB_SUBSCRIBED'			=> 'Article Subscriptions',
	'UCP_KB_SUBSCRIBED_EXPLAIN'	=> 'On this page you can view, remove and update your subscription to articles. You can also alter the notification settings.',
));

?>