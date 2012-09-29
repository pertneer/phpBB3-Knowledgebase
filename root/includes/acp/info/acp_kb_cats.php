<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: acp_kb_cats.php 416 2010-01-12 21:02:01Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_kb_cats_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_kb_cats',
			'title'		=> 'ACP_KB_MANAGEMENT',
			'version'	=> '1.0.1',
			'modes'		=> array(
				'manage'	=> array('title' => 'ACP_MANAGE_CATS', 'auth' => 'acl_a_board', 'cat' => array('ACP_KB')),
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