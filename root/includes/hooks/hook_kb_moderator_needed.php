<?php
/**
* @package phpBB
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
 * @param hook_kb_moderator_needed $hook
 * @return void
 */
function hook_kb_moderator_needed(&$hook)
{
	global $auth, $cache, $db, $template, $user, $phpEx, $phpbb_root_path;
			
		// Submitted by victory1
	    // KB addon  - Start
       if ($auth->acl_get('m_report_kb'))
       {
          // Reportered KB articles - Start
          $sql = 'SELECT COUNT(report_id) AS total_reports
             FROM ' . KB_REPORTS_TABLE . '
             WHERE report_closed = ' . 0;
          $result = $db->sql_query($sql);
          $total_kbreports = (int) $db->sql_fetchfield('total_reports');
          $db->sql_freeresult($result);

          $l_reported_kbs_count = $total_kbreports ? (($total_kbreports == 1) ? $user->lang['MODERATOR_NEEDED_REPORTED_KB'] : $user->lang['MODERATOR_NEEDED_REPORTED_KBS']) : '';
          $l_reported_kbs = sprintf($l_reported_kbs_count, $total_kbreports);
          // Reportered KB articles - End

          // Unapproved KB articles - Start
          $sql = 'SELECT COUNT(article_id) AS total_articles
             FROM ' . KB_ARTICLE_TABLE . "
             WHERE activ = '0'";
          $result = $db->sql_query($sql);
          $total_kbunapproved = (int) $db->sql_fetchfield('total_articles');
          $db->sql_freeresult($result);

          $l_unapproved_kbs_count = $total_kbunapproved ? (($total_kbunapproved == 1) ? $user->lang['MODERATOR_NEEDED_APPROVE_KB'] : $user->lang['MODERATOR_NEEDED_APPROVE_KBS']) : '';
          $l_unapproved_kbs = sprintf($l_unapproved_kbs_count, $total_kbunapproved);
          // Unapproved KB articles - End

          $template->assign_vars(array(
          // Reportered KB articles - Start
          //   <!-- IF TOTAL_KB_REPORTS --> &bull; <a href="{U_KB_REPORTS}">{TOTAL_KB_REPORTS}</a><!-- ENDIF -->
             'TOTAL_KB_REPORTS'   => $l_reported_kbs,
             'U_KB_REPORTS'      => append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&amp;mode=kb_reports', true, $user->session_id),
          // Reportered KB articles - End
          // Unapproved KB articles - Start
          //   <!-- IF TOTAL_KB_APPROVE --> &bull; <a href="{U_KB_APPROVE}">{TOTAL_KB_APPROVE}</a><!-- ENDIF -->
             'TOTAL_KB_APPROVE'   => $l_unapproved_kbs,
             'U_KB_APPROVE'      => append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&amp;mode=kb_activate', true, $user->session_id),
          // Unapproved KB articles - End
          ));
       }
       // KB addon  - End


/**
 * Only register the hook for normal pages, not administration pages.
 */
if (!defined('ADMIN_START'))
{
	$phpbb_hook->register(array('template', 'display'), 'hook_kb_moderator_needed');
}
