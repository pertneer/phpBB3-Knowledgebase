<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: acp_kb.php 416 2010-01-12 21:02:01Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_kb_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_kb',
			'title'		=> 'ACP_KB_MANAGEMENT',
			'version'	=> '1.0.1',
			'modes'		=> array(
				'settings'		=> array('title' => 'ACP_KB_SETTINGS', 'auth' => 'acl_a_board', 'cat' => array('ACP_KB')),
				'health_check'	=> array('title' => 'ACP_KB_HEALTH_CHECK', 'auth' => 'acl_a_board', 'cat' => array('ACP_KB')),
				'plugins'		=> array('title' => 'ACP_KB_PLUGINS', 'auth' => 'acl_a_board', 'cat' => array('ACP_KB')),
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