<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: info_acp_kb_permissions.php 425 2010-01-20 15:28:07Z softphp $
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
	'ACP_KB_PERMISSIONS'			=> 'Category permissions',
	'ACP_KB_PERMISSIONS_EXPLAIN'	=> 'You can alter localized permissions here for your knowledge base mod.',
	'ACP_KB_ROLES'					=> 'Knowledge Base Roles',
	'ACP_KB_ROLES_EXPLAIN'			=> 'You can manage permission roles for your knowledge base mod here.',
));

?>