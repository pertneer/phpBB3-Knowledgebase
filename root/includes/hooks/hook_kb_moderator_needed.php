<?php
/**
* @package phpBB phpBB phpBB3-Knowledgebase Mod (KB)
* @copyright (c) 2011 Rich McGirr
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * This hook displays moderator messages to moderators
 *
 * @param hook_kbmoderator_needed $hook
 * @return void
 */

 // Make install is complete before using this hook: contributed by wintstar
if (isset($config['kb_mod_notify']) && $config['kb_mod_notify'] == 1)
{
	function hook_kb_moderator_needed(&$hook)
	{
		global $auth, $cache, $db, $template, $user, $phpEx, $phpbb_root_path, $table_prefix;


		if ($auth->acl_getf_global('m_'))
		{
			// needed language
			$user->add_lang('mods/kb_moderator_needed');
			include_once($phpbb_root_path . 'includes/constants_kb.' . $phpEx);

			if ($auth->acl_getf_global('m_approve') || $auth->acl_getf_global('m_report'))
			{
				if (!function_exists('get_forum_list'))
				{
					include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
				}
				// we need global announcements which don't have any forum id assigned to them
				$global_forum = array(0);

				 // Unapproved KB articles - Start
				  $sql = 'SELECT COUNT(article_id) AS total_articles
					 FROM ' . KB_TABLE . "
					 WHERE article_status = '0'";
				  $result = $db->sql_query($sql);
				  $total_kbunapproved = (int) $db->sql_fetchfield('total_articles');
				  $db->sql_freeresult($result);

				  $l_unapproved_kbs_count = $total_kbunapproved ? (($total_kbunapproved == 1) ? $user->lang['MODERATOR_NEEDED_APPROVE_KB'] : $user->lang['MODERATOR_NEEDED_APPROVE_KBS']) : '';
				  $l_unapproved_kbs = sprintf($l_unapproved_kbs_count, $total_kbunapproved);
				  // Unapproved KB articles - End

				   // Requested KB articles - Start
				  $sql = 'SELECT COUNT(article_id) AS total_articles
					 FROM ' . KB_REQ_TABLE . "
					 WHERE request_status = '0'";
				  $result = $db->sql_query($sql);
				  $total_kbrequested = (int) $db->sql_fetchfield('total_articles');
				  $db->sql_freeresult($result);

				  $l_requested_kbs_count = $total_kbrequested ? (($total_kbrequested == 1) ? $user->lang['MODERATOR_NEEDED_REQUESTED_KB'] : $user->lang['MODERATOR_NEEDED_REQUESTED_KBS']) : '';
				  $l_requested_kbs = sprintf($l_requested_kbs_count, $total_kbrequested);
				  // Requested KB articles - End

				  $template->assign_vars(array(
				  // Unapproved KB articles - Start
				  //   <!-- IF TOTAL_KB_APPROVE --> &bull; <a href="{U_KB_APPROVE}">{TOTAL_KB_APPROVE}</a><!-- ENDIF -->
					 'U_TOTAL_KB_APPROVE'   => $l_unapproved_kbs,
					 'U_KB_APPROVE'      => append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&amp;mode=queue', true, $user->session_id),
				  // Unapproved KB articles - End

				  // Requested KB articles - Start
			  //   <!-- IF TOTAL_KB_REQUEST --> &bull; <a href="{U_KB_REQUEST}">{TOTAL_KB_REQUEST}</a><!-- ENDIF -->
				 'U_TOTAL_KB_REQUEST'   => $l_requested_kbs,
				 'U_KB_REQUEST'      => append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=list', true, $user->session_id),
			  // Requested KB articles - End
				  ));
			}
		}
		else
		{
			return;
		}
	}

	/**
	 * Only register the hook for normal pages, not administration pages.
	 */
	if (!defined('ADMIN_START'))
	{
		$phpbb_hook->register(array('template', 'display'), 'hook_kb_moderator_needed');
	}
}