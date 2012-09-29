<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: mcp_kb.php 416 2010-01-12 21:02:01Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class mcp_kb_info
{
	function module()
	{
		return array(
			'filename'	=> 'mcp_kb',
			'title'		=> 'MCP_KB',
			'version'	=> '1.0.1',
			'modes'		=> array(
				'queue'			=> array('title' => 'MCP_KB_QUEUE', 'auth' => 'acl_m_kb_status', 'cat' => array('MCP_KB')),
				'articles'		=> array('title' => 'MCP_KB_ARTICLES', 'auth' => 'acl_m_kb_status', 'cat' => array('MCP_KB')),
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