<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_email_article.php 420 2010-01-13 14:36:10Z softphp $
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

// Only add these options if in acp
if (defined('IN_KB_PLUGIN'))
{
	$acp_options['legend1'] 			= 'EMAIL_ARTICLE';
	$acp_options['kb_email_article'] 	= array('lang' => 'KB_EMAIL_ARTICLE',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true);
	$acp_options['kb_email_article_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_EMAIL',
		'PLUGIN_DESC'			=> 'PLUGIN_EMAIL_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('view_article'),
	);
}

// Get latest article
function email_article()
{
	global $template;

	// Everything else in kb.php

	$content = kb_parse_template('email_article', 'email_article.html');

	return $content;
}

function email_article_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			'config_add'	=> array(
				//array('kb_email_article', 1),
				array('kb_email_article_menu', RIGHT_MENU),
			),
		),
	);

	return $versions;
}
?>