<?php

/**
*
* @author Pertneer
* @package phpbb3-knowledgebase
* @version $Id adm/mods/kb_version.php
* @copyright (c) 2012 pertneer
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

class kb_version
{

	function version()
	{
		return array(
			'author'	=> 'Pertneer',
			'title'		=> 'phpBB3-Knowledgebase',
			'tag'		=> 'kb',
			'version'	=> '1.0.3',
			'file'		=> array('pertneer.net', 'mods', 'knowledgebase.xml'),
		);
	}
}

?>