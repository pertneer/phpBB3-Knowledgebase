<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: permissions_kb.php 461 2010-04-15 14:10:05Z softphp $
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

$lang['permission_cat']['kb'] = 'Knowledge Base'; // Unsets all other permission cats if not defined this way
$lang['permission_cat']['read'] = 'Read permissions';
$lang['permission_cat']['write'] = 'Write permissions';
$lang['permission_type']['kb_'] = 'Knowledge Base';

$lang = array_merge($lang, array(
	'acl_m_kb_author'		=> array(
		'lang'				=> 'Can change article author',
		'cat'				=> 'kb',
	),

	'acl_m_kb_comment'		=> array(
		'lang'				=> 'Can post moderator comments and edit all comments',
		'cat'				=> 'kb',
	),

	'acl_m_kb_delete'		=> array(
		'lang'				=> 'Can delete articles',
		'cat'				=> 'kb',
	),

	'acl_m_kb_edit'			=> array(
		'lang'				=> 'Can edit articles',
		'cat'				=> 'kb',
	),

	'acl_m_kb_req_edit'		=> array(
		'lang'				=> 'Can edit and delete requests',
		'cat'				=> 'kb',
	),

	'acl_m_kb_status'		=> array(
		'lang'				=> 'Can change status of articles',
		'cat'				=> 'kb',
	),

	'acl_m_kb_time'			=> array(
		'lang'				=> 'Can change article time',
		'cat'				=> 'kb',
	),

	'acl_m_kb_view'			=> array(
		'lang'				=> 'Can view unapproved articles',
		'cat'				=> 'kb',
	),

	'acl_u_kb_add'			=> array(
		'lang'				=> 'Can add articles',
		'cat'				=> 'write',
	),

	'acl_u_kb_add_co'		=> array(
		'lang'				=> 'Can contribute to an open article',
		'cat'				=> 'write',
	),

	'acl_u_kb_add_op'		=> array(
		'lang'				=> 'Can add articles open for contributions',
		'cat'				=> 'write',
	),

	'acl_u_kb_add_wa'		=> array(
		'lang'				=> 'Can add articles without needing approval',
		'cat'				=> 'write',
	),

	'acl_u_kb_attach'		=> array(
		'lang'				=> 'Can use attachments',
		'cat'				=> 'write',
	),

	'acl_u_kb_bbcode'		=> array(
		'lang'				=> 'Can use bbcodes',
		'cat'				=> 'write',
	),

	'acl_u_kb_comment'		=> array(
		'lang'				=> 'Can post comments',
		'cat'				=> 'write',
	),

	'acl_u_kb_delete'		=> array(
		'lang'				=> 'Can delete own articles',
		'cat'				=> 'write',
	),

	'acl_u_kb_download'		=> array(
		'lang'				=> 'Can download attachments',
		'cat'				=> 'read',
	),

	'acl_u_kb_edit'			=> array(
		'lang'				=> 'Can edit own articles',
		'cat'				=> 'write',
	),

	'acl_u_kb_flash'		=> array(
		'lang'				=> 'Can post flash',
		'cat'				=> 'write',
	),

	'acl_u_kb_icons'		=> array(
		'lang'				=> 'Can use article types',
		'cat'				=> 'write',
	),

	'acl_u_kb_img'			=> array(
		'lang'				=> 'Can post images',
		'cat'				=> 'write',
	),

	'acl_u_kb_rate'			=> array(
		'lang'				=> 'Can rate articles',
		'cat'				=> 'read',
	),

	'acl_u_kb_read'			=> array(
		'lang'				=> 'Can read articles in this category',
		'cat'				=> 'read',
	),

	'acl_u_kb_request'		=> array(
		'lang'				=> 'Can request articles',
		'cat'				=> 'kb',
	),

	'acl_u_kb_search'		=> array(
		'lang'				=> 'Can search this category',
		'cat'				=> 'read',
	),

	'acl_u_kb_sigs'			=> array(
		'lang'				=> 'Can attach signature',
		'cat'				=> 'write',
	),

	'acl_u_kb_smilies'		=> array(
		'lang'				=> 'Can post smiles',
		'cat'				=> 'write',
	),

	'acl_u_kb_types'		=> array(
		'lang'				=> 'Can specify articletype when posting',
		'cat'				=> 'write',
	),

	'acl_u_kb_view'			=> array(
		'lang'				=> 'Can view category and a list of articles in it',
		'cat'				=> 'read',
	),

	'acl_u_kb_viewhistory'	=> array(
		'lang'				=> 'Can view the history of an article',
		'cat'				=> 'read',
	),
));

?>