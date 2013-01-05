<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: info_mcp_kb.php 425 2010-01-20 15:28:07Z softphp $
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
	'MCP_KB'					=> 'Knowledge Base',
	'MCP_KB_ARTICLES'			=> 'Approved articles',
	'MCP_KB_ARTICLES_EXPLAIN'	=> 'Here is a list of articles which have been approved, all old chat with the authors is logged here.',
	'MCP_KB_QUEUE'				=> 'Articles awaiting approval',
	'MCP_KB_QUEUE_EXPLAIN'		=> 'Here is a list of articles that needs approval to be shown publicly. You can communicate with the article author here and edit the articles at your will.',
));

?>