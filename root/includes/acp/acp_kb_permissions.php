<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: acp_kb_permissions.php $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
* Created from acp_permissions.php and acp_permission_roles.php from the phpBB package.
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
*/
class acp_kb_permissions
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache, $table_prefix;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/kb_auth.' . $phpEx);
		include_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		include_once($phpbb_root_path . 'includes/acp/auth.' . $phpEx);

		$auth_admin = new auth_admin();
		$kb_auth = new kb_auth();
		$kb_auth->acl($user->data, $auth);

		$user->add_lang('acp/permissions');
		add_permission_language();

		add_form_key('acp_kb');
		$user->add_lang('mods/kb');
		switch ($mode)
		{

			case 'set_roles':
				$this->tpl_name = 'acp_permission_roles';

				$submit = (isset($_POST['submit'])) ? true : false;
				$role_id = request_var('role_id', 0);
				$action = request_var('action', '');
				$action = (isset($_POST['add'])) ? 'add' : $action;

				$permission_type = 'u_kb_';
				$dummy_type = 'u_'; // Create dummy so we don't get stupid weird rows
				$this->page_title = 'ACP_KB_ROLES';

				$template->assign_vars(array(
					'L_TITLE'		=> $user->lang[$this->page_title],
					'L_FORUM'		=> $user->lang['ARTICLE_CAT'],
					'L_EXPLAIN'		=> $user->lang[$this->page_title . '_EXPLAIN'])
				);

				// Take action... admin submitted something
				if ($submit || $action == 'remove')
				{
					switch ($action)
					{
						case 'remove':

							if (!$role_id)
							{
								trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							$sql = 'SELECT *
								FROM ' . ACL_ROLES_TABLE . '
								WHERE role_id = ' . $role_id;
							$result = $db->sql_query($sql);
							$role_row = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);

							if (!$role_row)
							{
								trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							if (confirm_box(true))
							{
								$this->remove_role($role_id, $permission_type);

								$role_name = (!empty($user->lang[$role_row['role_name']])) ? $user->lang[$role_row['role_name']] : $role_row['role_name'];
								add_log('admin', 'LOG_' . strtoupper($permission_type) . 'ROLE_REMOVED', $role_name);
								trigger_error($user->lang['ROLE_DELETED'] . adm_back_link($this->u_action));
							}
							else
							{
								confirm_box(false, 'DELETE_ROLE', build_hidden_fields(array(
									'i'			=> $id,
									'mode'		=> $mode,
									'role_id'	=> $role_id,
									'action'	=> $action,
								)));
							}

						break;

						case 'edit':
							if (!$role_id)
							{
								trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							// Get role we edit
							$sql = 'SELECT *
								FROM ' . ACL_ROLES_TABLE . '
								WHERE role_id = ' . $role_id;
							$result = $db->sql_query($sql);
							$role_row = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);

							if (!$role_row)
							{
								trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

						// no break;

						case 'add':

							if (!check_form_key('acp_kb'))
							{
								trigger_error($user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
							}

							$role_name = utf8_normalize_nfc(request_var('role_name', '', true));
							$role_description = utf8_normalize_nfc(request_var('role_description', '', true));
							$auth_settings = request_var('setting', array('' => 0));

							if (!$role_name)
							{
								trigger_error($user->lang['NO_ROLE_NAME_SPECIFIED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							if (utf8_strlen($role_description) > 4000)
							{
								trigger_error($user->lang['ROLE_DESCRIPTION_LONG'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							// if we add/edit a role we check the name to be unique among the settings...
							$sql = 'SELECT role_id
								FROM ' . ACL_ROLES_TABLE . "
								WHERE role_type = '" . $db->sql_escape($permission_type) . "'
									AND role_name = '" . $db->sql_escape($role_name) . "'";
							$result = $db->sql_query($sql);
							$row = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);

							// Make sure we only print out the error if we add the role or change it's name
							if ($row && ($mode == 'add' || ($mode == 'edit' && $role_row['role_name'] != $role_name)))
							{
								trigger_error(sprintf($user->lang['ROLE_NAME_ALREADY_EXIST'], $role_name) . adm_back_link($this->u_action), E_USER_WARNING);
							}

							$sql_ary = array(
								'role_name'			=> (string) $role_name,
								'role_description'	=> (string) $role_description,
								'role_type'			=> (string) $permission_type,
							);

							if ($action == 'edit')
							{
								$sql = 'UPDATE ' . ACL_ROLES_TABLE . '
									SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
									WHERE role_id = ' . $role_id;
								$db->sql_query($sql);
							}
							else
							{
								// Get maximum role order for inserting a new role...
								$sql = 'SELECT MAX(role_order) as max_order
									FROM ' . ACL_ROLES_TABLE . "
									WHERE role_type = '" . $db->sql_escape($permission_type) . "'";
								$result = $db->sql_query($sql);
								$max_order = (int) $db->sql_fetchfield('max_order');
								$db->sql_freeresult($result);

								$sql_ary['role_order'] = $max_order + 1;

								$sql = 'INSERT INTO ' . ACL_ROLES_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
								$db->sql_query($sql);

								$role_id = $db->sql_nextid();
							}

							// Now add the auth settings
							$auth_admin->acl_set_role($role_id, $auth_settings);

							$role_name = (!empty($user->lang[$role_name])) ? $user->lang[$role_name] : $role_name;
							add_log('admin', 'LOG_' . strtoupper($permission_type) . 'ROLE_' . strtoupper($action), $role_name);

							trigger_error($user->lang['ROLE_' . strtoupper($action) . '_SUCCESS'] . adm_back_link($this->u_action));

						break;
					}
				}

				// Display screens
				switch ($action)
				{
					case 'add':

						$options_from = request_var('options_from', 0);

						$role_row = array(
							'role_name'			=> utf8_normalize_nfc(request_var('role_name', '', true)),
							'role_description'	=> utf8_normalize_nfc(request_var('role_description', '', true)),
							'role_type'			=> $permission_type,
						);

						if ($options_from)
						{
							$sql = 'SELECT p.auth_option_id, p.auth_setting, o.auth_option
								FROM ' . ACL_ROLES_DATA_TABLE . ' p, ' . ACL_OPTIONS_TABLE . ' o
								WHERE o.auth_option_id = p.auth_option_id
									AND p.role_id = ' . $options_from . '
								ORDER BY p.auth_option_id';
							$result = $db->sql_query($sql);

							$auth_options = array();
							while ($row = $db->sql_fetchrow($result))
							{
								$auth_options[$row['auth_option']] = $row['auth_setting'];
							}
							$db->sql_freeresult($result);
						}
						else
						{
							$sql = 'SELECT auth_option_id, auth_option
								FROM ' . ACL_OPTIONS_TABLE . "
								WHERE auth_option " . $db->sql_like_expression($permission_type . $db->any_char) . "
									AND auth_option <> '" . $db->sql_escape($permission_type) . "'
									AND auth_option <> '" . $db->sql_escape($dummy_type) . "'
									AND auth_option <> 'u_kb_request'
								ORDER BY auth_option_id";
							$result = $db->sql_query($sql);

							$auth_options = array();
							while ($row = $db->sql_fetchrow($result))
							{
								$auth_options[$row['auth_option']] = ACL_NO;
							}
							$db->sql_freeresult($result);
						}

					// no break;

					case 'edit':
						if ($action == 'edit')
						{
							if (!$role_id)
							{
								trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							$sql = 'SELECT *
								FROM ' . ACL_ROLES_TABLE . '
								WHERE role_id = ' . $role_id;
							$result = $db->sql_query($sql);
							$role_row = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);

							$sql = 'SELECT p.auth_option_id, p.auth_setting, o.auth_option
								FROM ' . ACL_ROLES_DATA_TABLE . ' p, ' . ACL_OPTIONS_TABLE . ' o
								WHERE o.auth_option_id = p.auth_option_id
									AND p.role_id = ' . $role_id . "
									AND o.auth_option <> '" . $db->sql_escape($dummy_type) . "'
									AND o.auth_option <> 'u_kb_request'
								ORDER BY p.auth_option_id";
							$result = $db->sql_query($sql);

							$auth_options = array();
							while ($row = $db->sql_fetchrow($result))
							{
								$auth_options[$row['auth_option']] = $row['auth_setting'];
							}
							$db->sql_freeresult($result);

						}

						if (!$role_row)
						{
							trigger_error($user->lang['NO_ROLE_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						$template->assign_vars(array(
							'S_EDIT'			=> true,

							'U_ACTION'			=> $this->u_action . "&amp;action={$action}&amp;role_id={$role_id}",
							'U_BACK'			=> $this->u_action,

							'ROLE_NAME'			=> $role_row['role_name'],
							'ROLE_DESCRIPTION'	=> $role_row['role_description'],
							'L_ACL_TYPE'		=> $user->lang['ACL_TYPE_' . strtoupper($permission_type)],
							)
						);

						// We need to fill the auth options array with ACL_NO options ;)
						$sql = 'SELECT auth_option_id, auth_option
							FROM ' . ACL_OPTIONS_TABLE . "
							WHERE auth_option " . $db->sql_like_expression($permission_type . $db->any_char) . "
								AND auth_option <> '" . $db->sql_escape($permission_type) . "'
								AND auth_option <> '" . $db->sql_escape($dummy_type) . "'
								AND auth_option <> 'u_kb_request'
							ORDER BY auth_option_id";
						$result = $db->sql_query($sql);

						while ($row = $db->sql_fetchrow($result))
						{
							if (!isset($auth_options[$row['auth_option']]))
							{
								$auth_options[$row['auth_option']] = ACL_NO;
							}
						}
						$db->sql_freeresult($result);

						// Unset global permission option
						unset($auth_options[$permission_type]);

						// Display auth options
						$this->display_auth_options($auth_options);

						// Get users/groups/categories using this preset...
						if ($action == 'edit')
						{
							$hold_ary = $this->get_role_mask($role_id);

							if (sizeof($hold_ary))
							{
								$role_name = (!empty($user->lang[$role_row['role_name']])) ? $user->lang[$role_row['role_name']] : $role_row['role_name'];

								$template->assign_vars(array(
									'S_DISPLAY_ROLE_MASK'	=> true,
									'L_ROLE_ASSIGNED_TO'	=> sprintf($user->lang['ROLE_ASSIGNED_TO'], $role_name))
								);

								$this->display_role_mask($hold_ary);
							}
						}

						return;
					break;

					case 'move_up':
					case 'move_down':

						$order = request_var('order', 0);
						$order_total = $order * 2 + (($action == 'move_up') ? -1 : 1);
						$order = array($order, (($action == 'move_up') ? $order - 1 : $order + 1));

						$sql = 'UPDATE ' . ACL_ROLES_TABLE . '
							SET role_order = ' . $order_total . " - role_order
							WHERE role_type = '" . $db->sql_escape($permission_type) . "'
							AND " . $db->sql_in_set('role_order', $order);
						$db->sql_query($sql);
					break;
				}

				// By default, check that role_order is valid and fix it if necessary
				$sql = 'SELECT role_id, role_order
					FROM ' . ACL_ROLES_TABLE . "
					WHERE role_type = '" . $db->sql_escape($permission_type) . "'
					ORDER BY role_order ASC";
				$result = $db->sql_query($sql);

				if ($row = $db->sql_fetchrow($result))
				{
					$order = 0;
					do
					{
						$order++;
						if ($row['role_order'] != $order)
						{
							$db->sql_query('UPDATE ' . ACL_ROLES_TABLE . " SET role_order = $order WHERE role_id = {$row['role_id']}");
						}
					}
					while ($row = $db->sql_fetchrow($result));
				}
				$db->sql_freeresult($result);

				// Display assigned items?
				$display_item = request_var('display_item', 0);

				// Select existing roles
				$sql = 'SELECT *
					FROM ' . ACL_ROLES_TABLE . "
					WHERE role_type = '" . $db->sql_escape($permission_type) . "'
					ORDER BY role_order ASC";
				$result = $db->sql_query($sql);

				$s_role_options = '';
				while ($row = $db->sql_fetchrow($result))
				{
					$role_name = (!empty($user->lang[$row['role_name']])) ? $user->lang[$row['role_name']] : $row['role_name'];

					$template->assign_block_vars('roles', array(
						'ROLE_NAME'				=> $role_name,
						'ROLE_DESCRIPTION'		=> (!empty($user->lang[$row['role_description']])) ? $user->lang[$row['role_description']] : nl2br($row['role_description']),

						'U_EDIT'			=> $this->u_action . '&amp;action=edit&amp;role_id=' . $row['role_id'],
						'U_REMOVE'			=> $this->u_action . '&amp;action=remove&amp;role_id=' . $row['role_id'],
						'U_MOVE_UP'			=> $this->u_action . '&amp;action=move_up&amp;order=' . $row['role_order'],
						'U_MOVE_DOWN'		=> $this->u_action . '&amp;action=move_down&amp;order=' . $row['role_order'],
						'U_DISPLAY_ITEMS'	=> ($row['role_id'] == $display_item) ? '' : $this->u_action . '&amp;display_item=' . $row['role_id'] . '#assigned_to')
					);

					$s_role_options .= '<option value="' . $row['role_id'] . '">' . $role_name . '</option>';

					if ($display_item == $row['role_id'])
					{
						$template->assign_vars(array(
							'L_ROLE_ASSIGNED_TO'	=> sprintf($user->lang['ROLE_ASSIGNED_TO'], $role_name))
						);
					}
				}
				$db->sql_freeresult($result);

				$template->assign_vars(array(
					'S_ROLE_OPTIONS'		=> $s_role_options)
				);

				if ($display_item)
				{
					$template->assign_vars(array(
						'S_DISPLAY_ROLE_MASK'	=> true)
					);

					$hold_ary = $this->get_role_mask($display_item);
					$this->display_role_mask($hold_ary);
				}
			break;

		// Here we set all kb permissions
		case 'set_permissions':

			$this->tpl_name = 'acp_permissions';
			$this->page_title = $user->lang['ACP_KB_PERMISSIONS'];

			$template->assign_vars(array(
				'U_FIND_USERNAME'			=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=add_user&amp;field=username&amp;select_single=true'),
			));

			$action = request_var('action', array('' => 0));
			$action = key($action);
			$action = (isset($_POST['psubmit'])) ? 'apply_permissions' : $action;
			$permission_type = 'u_kb_';

			$all_forums = request_var('all_forums', 0);
			$subforum_id = request_var('subforum_id', 0);
			$forum_id = request_var('forum_id', array(0));

			$username = request_var('username', array(''), true);
			$usernames = request_var('usernames', '', true);
			$user_id = request_var('user_id', array(0));

			$group_id = request_var('group_id', array(0));
			$select_all_groups = request_var('select_all_groups', 0);

			// Map usernames to ids and vice versa
			if ($usernames)
			{
				$username = explode("\n", $usernames);
			}
			unset($usernames);

			if (sizeof($username) && !sizeof($user_id))
			{
				user_get_id_name($user_id, $username);

				if (!sizeof($user_id))
				{
					trigger_error($user->lang['SELECTED_USER_NOT_EXIST'] . adm_back_link($this->u_action), E_USER_WARNING);
				}
			}
			unset($username);

			// Build forum ids (of all forums are checked or subforum listing used)
			if ($all_forums)
			{
				$sql = 'SELECT cat_id
					FROM ' .  KB_CATS_TABLE . '
					ORDER BY left_id';
				$result = $db->sql_query($sql);

				$forum_id = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$forum_id[] = (int) $row['cat_id'];
				}
				$db->sql_freeresult($result);
			}
			else if ($subforum_id)
			{
				$forum_id = array();
				foreach (kb_get_cat_children($subforum_id) as $row)
				{
					$forum_id[] = (int) $row['cat_id'];
				}
			}

			// Setting permissions screen
			$s_hidden_fields = build_hidden_fields(array(
				'user_id'		=> $user_id,
				'group_id'		=> $group_id,
				'forum_id'		=> $forum_id,
				)
			);

			switch($action)
			{
				case 'apply_all_permissions':
					$this->set_all_permissions(false, $permission_type, $auth_admin, $user_id, $group_id);
				break;

				case 'apply_permissions':
					$this->set_permissions($mode, $permission_type, $auth_admin, $user_id, $group_id);
				break;

				case 'delete':
					if (!check_form_key('acp_kb'))
					{
						trigger_error($user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
					}
					// All users/groups selected?
					$all_users = (isset($_POST['all_users'])) ? true : false;
					$all_groups = (isset($_POST['all_groups'])) ? true : false;

					if ($all_users || $all_groups)
					{
						$items = $this->retrieve_defined_user_groups($permission_scope, $forum_id, $permission_type);

						if ($all_users && sizeof($items['user_ids']))
						{
							$user_id = $items['user_ids'];
						}
						else if ($all_groups && sizeof($items['group_ids']))
						{
							$group_id = $items['group_ids'];
						}
					}

					if (sizeof($user_id) || sizeof($group_id))
					{
						$this->remove_permissions($mode, $permission_type, $auth_admin, $user_id, $group_id, $forum_id);
					}
					else
					{
						trigger_error($user->lang['NO_USER_GROUP_SELECTED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
				break;
			}

			if(sizeof($user_id) || sizeof($group_id))
			{
				$cat_data = array();

				// Get me some categories
				$sql = 'SELECT cat_id, cat_name, parent_id, left_id, right_id
					FROM ' . KB_CATS_TABLE . '
					ORDER BY left_id ASC';

				$result = $db->sql_query($sql);
				while($row = $db->sql_fetchrow($result))
				{
					$cat_data[$row['cat_id']] = array(
						'forum_name'	=> $row['cat_name'],
						'forum_id'		=> $row['cat_id'],
						'disabled'		=> false,
						'padding' 		=> '',
					);
				}
				$db->sql_freeresult($result);

				$hold_ary = $this->get_mask('set', (sizeof($user_id)) ? $user_id : false, (sizeof($group_id)) ? $group_id : false, (sizeof($forum_id)) ? $forum_id : false, $permission_type, 'local', ACL_NO);
				$auth_admin->display_mask('set', $permission_type, $hold_ary, ((sizeof($user_id)) ? 'user' : 'group'), true , true, $cat_data);

				$template->assign_vars(array(
					'S_SETTING_PERMISSIONS'	=> true,
					'U_ACTION'				=> $this->u_action,
					'S_HIDDEN_FIELDS'		=> $s_hidden_fields,
				));
			}
			else if($all_forums or sizeof($forum_id) or $subforum_id)
			{
				$items = $this->retrieve_defined_user_groups('local', $forum_id, $permission_type);
				$template->assign_vars(array(
					'L_TITLE'					=> $user->lang['ACP_KB_PERMISSIONS'],
					'L_EXPLAIN'					=> $user->lang['ACP_KB_PERMISSIONS_EXPLAIN'],
					'S_SELECT_USERGROUP'		=> true,
					'S_CAN_SELECT_USER'			=> true,
					'S_CAN_SELECT_GROUP'		=> true,
					'S_SELECT_VICTIM' 			=> true,
					'S_DEFINED_USER_OPTIONS'	=> $items['user_ids_options'],
					'S_DEFINED_GROUP_OPTIONS'	=> $items['group_ids_options'],
					'S_ADD_GROUP_OPTIONS'		=> group_select_options(false, $items['group_ids'], false),	// Show all groups
					'S_HIDDEN_FIELDS'			=> $s_hidden_fields,

				));
			}
			else
			{
				$template->assign_vars(array(
					'L_TITLE'							=> $user->lang['ACP_KB_PERMISSIONS'],
					'L_EXPLAIN'							=> $user->lang['ACP_KB_PERMISSIONS_EXPLAIN'],
					'L_LOOK_UP_FORUM'					=> $user->lang['LOOK_UP_CATEGORY'],
					'L_LOOK_UP_FORUMS_EXPLAIN'			=> $user->lang['LOOK_UP_FORUMS_EXPLAIN'],
					'L_ALL_FORUMS'						=> $user->lang['ALL_CATEGORIES'],
					'L_SELECT_FORUM_SUBFORUM_EXPLAIN'	=> $user->lang['SELECT_SUBCAT_EXPLAIN'],
					'U_ACTION'							=> $this->u_action,
					'S_PERMISSION_C_MASK'				=> true,
					'S_FORUM_OPTIONS'					=> make_cat_select(false, false, true),
					'S_SUBFORUM_OPTIONS'				=> make_cat_select(false, false, true),
					'S_FORUM_MULTIPLE'					=> true,
					'S_FORUM_ALL'						=> true,
					'S_SELECT_FORUM'					=> true,
					'S_SELECT_VICTIM' 					=> true,
					'S_HIDDEN_FIELDS'					=> $s_hidden_fields,
				));
			}
		}
	}

	/**
	* Display permission settings able to be set
	*/
	function display_auth_options($auth_options)
	{
		global $template, $user;

		$content_array = $categories = array();
		$key_sort_array = array(0);
		$auth_options = array(0 => $auth_options);

		// Making use of auth_admin method here (we do not really want to change two similar code fragments)
		auth_admin::build_permission_array($auth_options, $content_array, $categories, $key_sort_array);

		$content_array = $content_array[0];

		$template->assign_var('S_NUM_PERM_COLS', sizeof($categories));

		// Assign to template
		foreach ($content_array as $cat => $cat_array)
		{
			$template->assign_block_vars('auth', array(
				'CAT_NAME'	=> $user->lang['permission_cat'][$cat],

				'S_YES'		=> ($cat_array['S_YES'] && !$cat_array['S_NEVER'] && !$cat_array['S_NO']) ? true : false,
				'S_NEVER'	=> ($cat_array['S_NEVER'] && !$cat_array['S_YES'] && !$cat_array['S_NO']) ? true : false,
				'S_NO'		=> ($cat_array['S_NO'] && !$cat_array['S_NEVER'] && !$cat_array['S_YES']) ? true : false)
			);

			foreach ($cat_array['permissions'] as $permission => $allowed)
			{
				$template->assign_block_vars('auth.mask', array(
					'S_YES'		=> ($allowed == ACL_YES) ? true : false,
					'S_NEVER'	=> ($allowed == ACL_NEVER) ? true : false,
					'S_NO'		=> ($allowed == ACL_NO) ? true : false,

					'FIELD_NAME'	=> $permission,
					'PERMISSION'	=> $user->lang['acl_' . $permission]['lang'])
				);
			}
		}
	}

	/**
	* Remove role
	*/
	function remove_role($role_id, $permission_type)
	{
		global $db;

		$auth_admin = new auth_admin();

		// Get complete auth array
		$sql = 'SELECT auth_option, auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option " . $db->sql_like_expression($permission_type . $db->any_char);
		$result = $db->sql_query($sql);

		$auth_settings = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$auth_settings[$row['auth_option']] = ACL_NO;
		}
		$db->sql_freeresult($result);

		// Get the role auth settings we need to re-set...
		$sql = 'SELECT o.auth_option, r.auth_setting
			FROM ' . ACL_ROLES_DATA_TABLE . ' r, ' . ACL_OPTIONS_TABLE . ' o
			WHERE o.auth_option_id = r.auth_option_id
				AND r.role_id = ' . $role_id;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$auth_settings[$row['auth_option']] = $row['auth_setting'];
		}
		$db->sql_freeresult($result);

		// Get role assignments
		$hold_ary = $this->get_role_mask($role_id);

		// Re-assign permissions
		foreach ($hold_ary as $forum_id => $forum_ary)
		{
			if (isset($forum_ary['users']))
			{
				$auth_admin->acl_set('user', $forum_id, $forum_ary['users'], $auth_settings, 0, false);
			}

			if (isset($forum_ary['groups']))
			{
				$auth_admin->acl_set('group', $forum_id, $forum_ary['groups'], $auth_settings, 0, false);
			}
		}

		// Remove role from users and groups just to be sure (happens through acl_set)
		$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . '
			WHERE auth_role_id = ' . $role_id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . KB_ACL_GROUPS_TABLE . '
			WHERE auth_role_id = ' . $role_id;
		$db->sql_query($sql);

		// Remove role data and role
		$sql = 'DELETE FROM ' . ACL_ROLES_DATA_TABLE . '
			WHERE role_id = ' . $role_id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . ACL_ROLES_TABLE . '
			WHERE role_id = ' . $role_id;
		$db->sql_query($sql);

		$auth_admin->acl_clear_prefetch();
	}

	/**
	* Remove permissions
	*/
	function remove_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id, &$forum_id)
	{
		global $user, $db;

		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';

		$this->acl_delete($ug_type, $auth_admin, (($ug_type == 'user') ? $user_id : $group_id), (sizeof($forum_id) ? $forum_id : false), $permission_type);

		trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
	}

	/**
	* Remove local permission
	*/
	function acl_delete($mode, &$auth_admin, $ug_id = false, $forum_id = false, $permission_type = false)
	{
		global $db;

		if ($ug_id === false && $forum_id === false)
		{
			return;
		}

		$option_id_ary = array();
		$table = ($mode == 'user') ? KB_ACL_USERS_TABLE : KB_ACL_GROUPS_TABLE;
		$id_field = $mode . '_id';

		$where_sql = array();

		if ($forum_id !== false)
		{
			$where_sql[] = (!is_array($forum_id)) ? 'forum_id = ' . (int) $forum_id : $db->sql_in_set('forum_id', array_map('intval', $forum_id));
		}

		if ($ug_id !== false)
		{
			$where_sql[] = (!is_array($ug_id)) ? $id_field . ' = ' . (int) $ug_id : $db->sql_in_set($id_field, array_map('intval', $ug_id));
		}

		// There seem to be auth options involved, therefore we need to go through the list and make sure we capture roles correctly
		if ($permission_type !== false)
		{
			// Get permission type
			$sql = 'SELECT auth_option, auth_option_id
				FROM ' . ACL_OPTIONS_TABLE . "
				WHERE auth_option " . $db->sql_like_expression($permission_type . $db->any_char);
			$result = $db->sql_query($sql);

			$auth_id_ary = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$option_id_ary[] = $row['auth_option_id'];
				$auth_id_ary[$row['auth_option']] = ACL_NO;
			}
			$db->sql_freeresult($result);

			// First of all, lets grab the items having roles with the specified auth options assigned
			$sql = "SELECT auth_role_id, $id_field, forum_id
				FROM $table, " . ACL_ROLES_TABLE . " r
				WHERE auth_role_id <> 0
					AND auth_role_id = r.role_id
					AND r.role_type = '" . $db->sql_escape($permission_type) . "'
					AND " . implode(' AND ', $where_sql) . '
				ORDER BY auth_role_id';
			$result = $db->sql_query($sql);

			$cur_role_auth = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$cur_role_auth[$row['auth_role_id']][$row['forum_id']][] = $row[$id_field];
			}
			$db->sql_freeresult($result);

			// Get role data for resetting data
			if (sizeof($cur_role_auth))
			{
				$sql = 'SELECT ao.auth_option, rd.role_id, rd.auth_setting
					FROM ' . ACL_OPTIONS_TABLE . ' ao, ' . ACL_ROLES_DATA_TABLE . ' rd
					WHERE ao.auth_option_id = rd.auth_option_id
						AND ' . $db->sql_in_set('rd.role_id', array_keys($cur_role_auth));
				$result = $db->sql_query($sql);

				$auth_settings = array();
				while ($row = $db->sql_fetchrow($result))
				{
					// We need to fill all auth_options, else setting it will fail...
					if (!isset($auth_settings[$row['role_id']]))
					{
						$auth_settings[$row['role_id']] = $auth_id_ary;
					}
					$auth_settings[$row['role_id']][$row['auth_option']] = $row['auth_setting'];
				}
				$db->sql_freeresult($result);

				// Set the options
				foreach ($cur_role_auth as $role_id => $auth_row)
				{
					foreach ($auth_row as $f_id => $ug_row)
					{
						$this->acl_set($mode, $f_id, $ug_row, $auth_settings[$role_id], 0, false);
					}
				}
			}
		}

		// Now, normally remove permissions...
		if ($permission_type !== false)
		{
			$where_sql[] = $db->sql_in_set('auth_option_id', array_map('intval', $option_id_ary));
		}

		$sql = "DELETE FROM $table
			WHERE " . implode(' AND ', $where_sql);
		$db->sql_query($sql);

		$auth_admin->acl_clear_prefetch();
	}

	/**
	* Apply permissions
	*/
	function set_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id)
	{
		global $user, $auth;

		$psubmit = request_var('psubmit', array(0 => array(0 => 0)));

		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';
		$ug_id = $forum_id = 0;

		// We loop through the auth settings defined in our submit
		list($ug_id, ) = each($psubmit);
		list($forum_id, ) = each($psubmit[$ug_id]);

		if (empty($_POST['setting']) || empty($_POST['setting'][$ug_id]) || empty($_POST['setting'][$ug_id][$forum_id]) || !is_array($_POST['setting'][$ug_id][$forum_id]))
		{
			trigger_error('WRONG_PERMISSION_SETTING_FORMAT', E_USER_WARNING);
		}

		// We obtain and check $_POST['setting'][$ug_id][$forum_id] directly and not using request_var() because request_var()
		// currently does not support the amount of dimensions required. ;)
		//		$auth_settings = request_var('setting', array(0 => array(0 => array('' => 0))));
		$auth_settings = array_map('intval', $_POST['setting'][$ug_id][$forum_id]);

		// Do we have a role we want to set?
		$assigned_role = (isset($_POST['role'][$ug_id][$forum_id])) ? (int) $_POST['role'][$ug_id][$forum_id] : 0;

		// Do the admin want to set these permissions to other items too?
		$inherit = request_var('inherit', array(0 => array(0)));

		$ug_id = array($ug_id);
		$forum_id = array($forum_id);

		if (sizeof($inherit))
		{
			foreach ($inherit as $_ug_id => $forum_id_ary)
			{
				// Inherit users/groups?
				if (!in_array($_ug_id, $ug_id))
				{
					$ug_id[] = $_ug_id;
				}

				// Inherit forums?
				$forum_id = array_merge($forum_id, array_keys($forum_id_ary));
			}
		}

		$forum_id = array_unique($forum_id);

		// If the auth settings differ from the assigned role, then do not set a role...
		if ($assigned_role)
		{
			if (!$this->check_assigned_role($assigned_role, $auth_settings))
			{
				$assigned_role = 0;
			}
		}

		// Update the permission set...
		$this->acl_set($ug_type, $forum_id, $ug_id, $auth_settings, $assigned_role);

		trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
	}

	/**
	* Display permission mask for roles
	*/
	function display_role_mask(&$hold_ary)
	{
		global $db, $template, $user, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		if (!sizeof($hold_ary))
		{
			return;
		}

		// Get forum names
		$sql = 'SELECT cat_id, cat_name
			FROM ' . KB_CATS_TABLE . '
			WHERE ' . $db->sql_in_set('cat_id', array_keys($hold_ary)) . '
			ORDER BY left_id';
		$result = $db->sql_query($sql);

		// If the role is used globally, then reflect that
		$cat_names = (isset($hold_ary[0])) ? array(0 => '') : array();
		while ($row = $db->sql_fetchrow($result))
		{
			$cat_names[$row['cat_id']] = $row['cat_name'];
		}
		$db->sql_freeresult($result);

		foreach ($cat_names as $cat_id => $cat_name)
		{
			$auth_ary = $hold_ary[$cat_id];

			$template->assign_block_vars('role_mask', array(
				'NAME'				=> ($cat_id == 0) ? $user->lang['GLOBAL_MASK'] : $cat_name,
				'FORUM_ID'			=> $cat_id)
			);

			if (isset($auth_ary['users']) && sizeof($auth_ary['users']))
			{
				$sql = 'SELECT user_id, username
					FROM ' . USERS_TABLE . '
					WHERE ' . $db->sql_in_set('user_id', $auth_ary['users']) . '
					ORDER BY username_clean ASC';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$template->assign_block_vars('role_mask.users', array(
						'USER_ID'		=> $row['user_id'],
						'USERNAME'		=> $row['username'],
						'U_PROFILE'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u={$row['user_id']}"))
					);
				}
				$db->sql_freeresult($result);
			}

			if (isset($auth_ary['groups']) && sizeof($auth_ary['groups']))
			{
				$sql = 'SELECT group_id, group_name, group_type
					FROM ' . GROUPS_TABLE . '
					WHERE ' . $db->sql_in_set('group_id', $auth_ary['groups']) . '
					ORDER BY group_type ASC, group_name';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$template->assign_block_vars('role_mask.groups', array(
						'GROUP_ID'		=> $row['group_id'],
						'GROUP_NAME'	=> ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'],
						'U_PROFILE'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=group&amp;g={$row['group_id']}"))
					);
				}
				$db->sql_freeresult($result);
			}
		}
	}

	/**
	* Set a user or group ACL record
	*/
	function acl_set($ug_type, $forum_id, $ug_id, $auth, $role_id = 0, $clear_prefetch = true)
	{
		global $db;

		$auth_admin = new auth_admin();
		// One or more forums
		if (!is_array($forum_id))
		{
			$forum_id = array($forum_id);
		}

		// One or more users
		if (!is_array($ug_id))
		{
			$ug_id = array($ug_id);
		}

		$ug_id_sql = $db->sql_in_set($ug_type . '_id', array_map('intval', $ug_id));
		$forum_sql = $db->sql_in_set('forum_id', array_map('intval', $forum_id));

		// Instead of updating, inserting, removing we just remove all current settings and re-set everything...
		$table = ($ug_type == 'user') ? KB_ACL_USERS_TABLE : KB_ACL_GROUPS_TABLE;
		$id_field = $ug_type . '_id';

		// Get any flags as required
		reset($auth);
		$flag = key($auth);
		$flag = substr($flag, 0, strpos($flag, '_') + 4); // Allow for u_kb_ rather than just u_

		// This ID (the any-flag) is set if one or more permissions are true... using static u_ rather than u_kb_ as that will delete forum permissions
		$any_option_id = (int) $auth_admin->acl_options['id']['u_'];

		// Remove any-flag from auth ary
		if (isset($auth[$flag]))
		{
			unset($auth[$flag]);
		}

		// Remove current auth options...
		$auth_option_ids = array((int)$any_option_id);
		foreach ($auth as $auth_option => $auth_setting)
		{
			$auth_option_ids[] = (int) $auth_admin->acl_options['id'][$auth_option];
		}

		$sql = "DELETE FROM $table
			WHERE $forum_sql
				AND $ug_id_sql
				AND " . $db->sql_in_set('auth_option_id', $auth_option_ids);
		$db->sql_query($sql);

		// Remove those having a role assigned... the correct type of course...
		$sql = 'SELECT role_id
			FROM ' . ACL_ROLES_TABLE . "
			WHERE role_type = '" . $db->sql_escape($flag) . "'";
		$result = $db->sql_query($sql);

		$role_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$role_ids[] = $row['role_id'];
		}
		$db->sql_freeresult($result);

		if (sizeof($role_ids))
		{
			$sql = "DELETE FROM $table
				WHERE $forum_sql
					AND $ug_id_sql
					AND auth_option_id = 0
					AND " . $db->sql_in_set('auth_role_id', $role_ids);
			$db->sql_query($sql);
		}

		// Ok, include the any-flag if one or more auth options are set to yes...
		$flag = 'u_';
		foreach ($auth as $auth_option => $setting)
		{
			if ($setting == ACL_YES && (!isset($auth[$flag]) || $auth[$flag] == ACL_NEVER))
			{
				$auth[$flag] = ACL_YES;
			}
		}

		$sql_ary = array();
		foreach ($forum_id as $forum)
		{
			$forum = (int) $forum;

			if ($role_id)
			{
				foreach ($ug_id as $id)
				{
					$sql_ary[] = array(
						$id_field			=> (int) $id,
						'forum_id'			=> (int) $forum,
						'auth_option_id'	=> 0,
						'auth_setting'		=> 0,
						'auth_role_id'		=> (int) $role_id,
					);
				}
			}
			else
			{
				foreach ($auth as $auth_option => $setting)
				{
					$auth_option_id = (int) $auth_admin->acl_options['id'][$auth_option];

					if ($setting != ACL_NO)
					{
						foreach ($ug_id as $id)
						{
							$sql_ary[] = array(
								$id_field			=> (int) $id,
								'forum_id'			=> (int) $forum,
								'auth_option_id'	=> (int) $auth_option_id,
								'auth_setting'		=> (int) $setting
							);
						}
					}
				}
			}
		}

		$db->sql_multi_insert($table, $sql_ary);

		if ($clear_prefetch)
		{
			$auth_admin->acl_clear_prefetch();
		}
	}

	/**
	* Apply all permissions
	*/
	function set_all_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id)
	{
		global $user, $auth;

		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';

		$auth_settings = (isset($_POST['setting'])) ? $_POST['setting'] : array();
		$auth_roles = (isset($_POST['role'])) ? $_POST['role'] : array();
		$ug_ids = $forum_ids = array();

		// We need to go through the auth settings
		foreach ($auth_settings as $ug_id => $forum_auth_row)
		{
			$ug_id = (int) $ug_id;
			$ug_ids[] = $ug_id;

			foreach ($forum_auth_row as $forum_id => $auth_options)
			{
				$forum_id = (int) $forum_id;
				$forum_ids[] = $forum_id;

				// Check role...
				$assigned_role = (isset($auth_roles[$ug_id][$forum_id])) ? (int) $auth_roles[$ug_id][$forum_id] : 0;

				// If the auth settings differ from the assigned role, then do not set a role...
				if ($assigned_role)
				{
					if (!$this->check_assigned_role($assigned_role, $auth_options))
					{
						$assigned_role = 0;
					}
				}

				// Update the permission set...
				$this->acl_set($ug_type, $forum_id, $ug_id, $auth_options, $assigned_role, false);
			}
		}

		$auth_admin->acl_clear_prefetch();

		//$this->log_action($mode, 'add', $permission_type, $ug_type, $ug_ids, $forum_ids);
		if ($mode == 'setting_forum_local' || $mode == 'setting_mod_local')
		{
			trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action . '&amp;forum_id[]=' . implode('&amp;forum_id[]=', $forum_ids)));
		}
		else
		{
			trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
		}
	}

	/**
	* Compare auth settings with auth settings from role
	* returns false if they differ, true if they are equal
	*/
	function check_assigned_role($role_id, &$auth_settings)
	{
		global $db;

		$sql = 'SELECT o.auth_option, r.auth_setting
			FROM ' . ACL_OPTIONS_TABLE . ' o, ' . ACL_ROLES_DATA_TABLE . ' r
			WHERE o.auth_option_id = r.auth_option_id
				AND r.role_id = ' . $role_id;
		$result = $db->sql_query($sql);

		$test_auth_settings = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$test_auth_settings[$row['auth_option']] = $row['auth_setting'];
		}
		$db->sql_freeresult($result);

		// We need to add any ACL_NO setting from auth_settings to compare correctly
		foreach ($auth_settings as $option => $setting)
		{
			if ($setting == ACL_NO)
			{
				$test_auth_settings[$option] = $setting;
			}
		}

		if (sizeof(array_diff_assoc($auth_settings, $test_auth_settings)))
		{
			return false;
		}

		return true;
	}

	/**
	* Get permission mask
	* This function only supports getting permissions of one type (for example a_)
	*
	* @param set|view $mode defines the permissions we get, view gets effective permissions (checking user AND group permissions), set only gets the user or group permission set alone
	* @param mixed $user_id user ids to search for (a user_id or a group_id has to be specified at least)
	* @param mixed $group_id group ids to search for, return group related settings (a user_id or a group_id has to be specified at least)
	* @param mixed $forum_id forum_ids to search for. Defining a forum id also means getting local settings
	* @param string $auth_option the auth_option defines the permission setting to look for (a_ for example)
	* @param local|global $scope the scope defines the permission scope. If local, a forum_id is additionally required
	* @param ACL_NEVER|ACL_NO|ACL_YES $acl_fill defines the mode those permissions not set are getting filled with
	*/
	function get_mask($mode, $user_id = false, $group_id = false, $categorie_id = false, $auth_option = false, $scope = false, $acl_fill = ACL_NEVER)
	{
		global $db, $user, $auth_admin, $kb_auth;
		$auth_admin = new auth_admin();
		$kb_auth = new kb_auth();

		$hold_ary = array();
		$view_user_mask = ($mode == 'view' && $group_id === false) ? true : false;

		if ($auth_option === false || $scope === false)
		{
			return array();
		}

		$acl_user_function = ($mode == 'set') ? 'acl_user_raw_data' : 'acl_raw_data';

		if (!$view_user_mask)
		{
			if ($categorie_id !== false)
			{
				$hold_ary = ($group_id !== false) ? $kb_auth->acl_group_raw_data($group_id, $auth_option . '%', $categorie_id) : $kb_auth->$acl_user_function($user_id, $auth_option . '%', $categorie_id);
			}
			else
			{
				$hold_ary = ($group_id !== false) ? $kb_auth->acl_group_raw_data($group_id, $auth_option . '%', ($scope == 'global') ? 0 : false) : $kb_auth->$acl_user_function($user_id, $auth_option . '%', ($scope == 'global') ? 0 : false);
			}
		}

		// Make sure hold_ary is filled with every setting (prevents missing categories/users/groups)
		$ug_id = ($group_id !== false) ? ((!is_array($group_id)) ? array($group_id) : $group_id) : ((!is_array($user_id)) ? array($user_id) : $user_id);
		$categorie_ids = ($categorie_id !== false) ? ((!is_array($categorie_id)) ? array($categorie_id) : $categorie_id) : (($scope == 'global') ? array(0) : array());

		// Only those options we need
		$compare_options = array_diff(preg_replace('/^((?!' . $auth_option . ').+)|(' . $auth_option . ')$/', '', array_keys($auth_admin->acl_options[$scope])), array(''));

		// If categorie_ids is false and the scope is local we actually want to have all categories within the array
		if ($scope == 'local' && !sizeof($categorie_ids))
		{
			$sql = 'SELECT cat_id
				FROM ' . KB_CATEGORIE_TABLE;
			$result = $db->sql_query($sql, 120);

			while ($row = $db->sql_fetchrow($result))
			{
				$categorie_ids[] = (int) $row['cat_id'];
			}
			$db->sql_freeresult($result);
		}

		if ($view_user_mask)
		{
			$auth2 = null;
			$kb_auth2 = null;

			$sql = 'SELECT user_id, user_kb_permissions, user_type
				FROM ' . USERS_TABLE . '
				WHERE ' . $db->sql_in_set('user_id', $ug_id);
			$result = $db->sql_query($sql);

			while ($userdata = $db->sql_fetchrow($result))
			{
				if ($user->data['user_id'] != $userdata['user_id'])
				{
					$auth2 = new auth();
					$kb_auth2 = new kb_auth();
					$auth2->acl($userdata);
					$kb_auth2->acl($userdata, $auth2);
				}
				else
				{
					global $auth;
					$auth2 = &$auth;
				}


				$hold_ary[$userdata['user_id']] = array();
				foreach ($categorie_ids as $f_id)
				{
					$hold_ary[$userdata['user_id']][$f_id] = array();
					foreach ($compare_options as $option)
					{
						$hold_ary[$userdata['user_id']][$f_id][$option] = $auth2->acl_get($option, $f_id);
					}
				}
			}
			$db->sql_freeresult($result);

			unset($userdata);
			unset($auth2);
			unset($kb_auth2);
		}

		foreach ($ug_id as $_id)
		{
			if (!isset($hold_ary[$_id]))
			{
				$hold_ary[$_id] = array();
			}

			foreach ($categorie_ids as $f_id)
			{
				if (!isset($hold_ary[$_id][$f_id]))
				{
					$hold_ary[$_id][$f_id] = array();
				}
			}
		}

		// Now, we need to fill the gaps with $acl_fill. ;)

		// Now switch back to keys
		if (sizeof($compare_options))
		{
			$compare_options = array_combine($compare_options, array_fill(1, sizeof($compare_options), $acl_fill));
		}

		// Defining the user-function here to save some memory
		$return_acl_fill = create_function('$value', 'return ' . $acl_fill . ';');

		// Actually fill the gaps
		if (sizeof($hold_ary))
		{
			foreach ($hold_ary as $ug_id => $row)
			{
				foreach ($row as $id => $options)
				{
					// Do not include the global auth_option
					unset($options[$auth_option]);

					// Not a "fine" solution, but at all it's a 1-dimensional
					// array_diff_key function filling the resulting array values with zeros
					// The differences get merged into $hold_ary (all permissions having $acl_fill set)
					$hold_ary[$ug_id][$id] = array_merge($options,

						array_map($return_acl_fill,
							array_flip(
								array_diff(
									array_keys($compare_options), array_keys($options)
								)
							)
						)
					);
				}
			}
		}
		else
		{
			$hold_ary[($group_id !== false) ? $group_id : $user_id][(int) $categorie_id] = $compare_options;
		}

		return $hold_ary;
	}

	/**
	* Get already assigned users/groups
	*/
	function retrieve_defined_user_groups($permission_scope, $forum_id, $permission_type)
	{
		global $db, $user;

		$sql_forum_id = ($permission_scope == 'global') ? 'AND a.forum_id = 0' : ((sizeof($forum_id)) ? 'AND ' . $db->sql_in_set('a.forum_id', $forum_id) : 'AND a.forum_id <> 0');

		// Permission options are only able to be a permission set... therefore we will pre-fetch the possible options and also the possible roles
		$option_ids = $role_ids = array();

		$sql = 'SELECT auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . '
			WHERE auth_option ' . $db->sql_like_expression($permission_type . $db->any_char);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$option_ids[] = (int) $row['auth_option_id'];
		}
		$db->sql_freeresult($result);

		if (sizeof($option_ids))
		{
			$sql = 'SELECT DISTINCT role_id
				FROM ' . ACL_ROLES_DATA_TABLE . '
				WHERE ' . $db->sql_in_set('auth_option_id', $option_ids);
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$role_ids[] = (int) $row['role_id'];
			}
			$db->sql_freeresult($result);
		}

		if (sizeof($option_ids) && sizeof($role_ids))
		{
			$sql_where = 'AND (' . $db->sql_in_set('a.auth_option_id', $option_ids) . ' OR ' . $db->sql_in_set('a.auth_role_id', $role_ids) . ')';
		}
		else if (sizeof($role_ids))
		{
			$sql_where = 'AND ' . $db->sql_in_set('a.auth_role_id', $role_ids);
		}
		else if (sizeof($option_ids))
		{
			$sql_where = 'AND ' . $db->sql_in_set('a.auth_option_id', $option_ids);
		}

		// Not ideal, due to the filesort, non-use of indexes, etc.
		$sql = 'SELECT DISTINCT u.user_id, u.username, u.username_clean, u.user_regdate
			FROM ' . USERS_TABLE . ' u, ' . KB_ACL_USERS_TABLE . " a
			WHERE u.user_id = a.user_id
				$sql_forum_id
				$sql_where
			ORDER BY u.username_clean, u.user_regdate ASC";
		$result = $db->sql_query($sql);

		$s_defined_user_options = '';
		$defined_user_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$s_defined_user_options .= '<option value="' . $row['user_id'] . '">' . $row['username'] . '</option>';
			$defined_user_ids[] = $row['user_id'];
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT DISTINCT g.group_type, g.group_name, g.group_id
			FROM ' . GROUPS_TABLE . ' g, ' . KB_ACL_GROUPS_TABLE . " a
			WHERE g.group_id = a.group_id
				$sql_forum_id
				$sql_where
			ORDER BY g.group_type DESC, g.group_name ASC";
		$result = $db->sql_query($sql);

		$s_defined_group_options = '';
		$defined_group_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$s_defined_group_options .= '<option' . (($row['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '') . ' value="' . $row['group_id'] . '">' . (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
			$defined_group_ids[] = $row['group_id'];
		}
		$db->sql_freeresult($result);

		return array(
			'group_ids'			=> $defined_group_ids,
			'group_ids_options'	=> $s_defined_group_options,
			'user_ids'			=> $defined_user_ids,
			'user_ids_options'	=> $s_defined_user_options
		);
	}

	/**
	* Get permission mask for roles
	* This function only supports getting masks for one role
	*/
	function get_role_mask($role_id)
	{
		global $db;

		$hold_ary = array();

		// Get users having this role set...
		$sql = 'SELECT user_id, forum_id
			FROM ' . KB_ACL_USERS_TABLE . '
			WHERE auth_role_id = ' . $role_id . '
			ORDER BY forum_id';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$hold_ary[$row['forum_id']]['users'][] = $row['user_id'];
		}
		$db->sql_freeresult($result);

		// Now grab groups...
		$sql = 'SELECT group_id, forum_id
			FROM ' . KB_ACL_GROUPS_TABLE . '
			WHERE auth_role_id = ' . $role_id . '
			ORDER BY forum_id';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$hold_ary[$row['forum_id']]['groups'][] = $row['group_id'];
		}
		$db->sql_freeresult($result);

		return $hold_ary;
	}
}

?>