<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: acp_kb_permissions.php $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package module_install
*/
class acp_kb_permissions_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_kb_permissions',
			'title'		=> 'ACP_KB_PERMISSIONS',
			'version'	=> '1.0.3',
			'modes'		=> array(
				'set_permissions'	=> array('title' => 'ACP_KB_PERMISSIONS',	'auth' => 'acl_a_kb_perm',	'cat' => array('ACP_KB')),
				'set_roles'			=> array('title' => 'ACP_KB_ROLES',	'auth' => 'acl_a_kb_roles',	'cat' => array('ACP_KB')),
				),
			);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}
?>