<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: seo.php 397 2009-12-08 17:09:11Z tom.martin60@btinternet.com $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* Credit to EXreaction, Lithium Studios for the idea fix here

* If you are confused at why this page is here, it is to trick the SEO Urls.
* I want my SEO urls to be like (PLACE)/(TITLE)/a(article_id).html
* If I were to just have that and use the main kb.php file, the $phpbb_root_path would work for the relative path for PHP files, but would not
*  work when it tells the browser the relative path for links (so the page would be broken).  So this is just a trick to make the relative paths work.
*/

define('PHPBB_ROOT_PATH', './../../');
include(PHPBB_ROOT_PATH . 'kb.' . substr(strrchr(__FILE__, '.'), 1));
?>