<?php
/**
*
* moderator_needed [English]
* Modified for phpBB phpBB3-Knowledgebase Mod 
* @package language
* @version $Id: moderator_needed.php,v 1.0.1 2009/09/29 06:50:00 rmcgirr83 Exp $
* @copyright (c) 2009 Richard McGirr
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

$lang = array_merge($lang, array(
	// Submitted by victory1
	'MODERATOR_NEEDED_APPROVE_KB'		=> '<strong style="color:#FF0000;">%d</strong> Article needs approval',
	'MODERATOR_NEEDED_APPROVE_KBS'		=> '<strong style="color:#FF0000;">%d</strong> Articles need approval',
	'MODERATOR_NEEDED_REPORTED_KB'		=> '<strong style="color:#FF0000;">%d</strong> Article is reported',
	'MODERATOR_NEEDED_REPORTED_KBS'		=> '<strong style="color:#FF0000;">%d</strong> Articles are reported',
	'MODERATOR_NEEDED_REQUESTED_KB'		=> '<strong style="color:#FF0000;">%d</strong> Article Request',
	'MODERATOR_NEEDED_REQUESTED_KBS'	=> '<strong style="color:#FF0000;">%d</strong> Articles Requested',
));

?>
