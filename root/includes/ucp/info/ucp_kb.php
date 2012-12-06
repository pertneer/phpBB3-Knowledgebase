<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: ucp_kb.php $
* @copyright (c) 2009 Andreas Nexmann
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class ucp_kb_info
{
	function module()
	{
		return array(
			'filename'	=> 'ucp_kb',
			'title'		=> 'UCP_KB',
			'version'	=> '1.0.1',
			'modes'		=> array(
				'front'			=> array('title' => 'UCP_KB_FRONT', 'auth' => '', 'cat' => array('UCP_KB')),
				'subscribed'	=> array('title' => 'UCP_KB_SUBSCRIBED', 'auth' => 'cfg_kb_allow_subscribe', 'cat' => array('UCP_KB')),
				'bookmarks'		=> array('title' => 'UCP_KB_BOOKMARKS', 'auth' => 'cfg_kb_allow_bookmarks', 'cat' => array('UCP_KB')),
				'articles'		=> array('title' => 'UCP_KB_ARTICLES', 'auth' => '', 'cat' => array('UCP_KB')),
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