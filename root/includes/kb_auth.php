<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_auth.php 437 2010-02-01 15:16:57Z softphp $
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

// KB Auth Class
// All credit to the phpBB Group as this is copy/paste then edit of auth.php
class kb_auth
{
	var $acl = array();
	var $acl_options = array();
	var $kb_auth_options = array();
	var $role_cache = array();
	
	/** Setup permissions **/
	function acl(&$userdata, &$auth)
	{
		global $db, $cache;
		
		$this->acl = $this->acl_options = array();
		$this->_fill_kb_auth_options();
		
		if (($this->acl_options = $cache->get('_kb_acl_options')) === false)
		{
			$sql = 'SELECT auth_option_id, auth_option, is_global, is_local
				FROM ' . ACL_OPTIONS_TABLE . '
				WHERE ' . $db->sql_in_set('auth_option', $this->kb_auth_options) . '
				ORDER BY auth_option_id';
			$result = $db->sql_query($sql);

			$global = $local = 0;
			$this->acl_options = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$this->acl_options['local'][$row['auth_option']] = $local++;
				$this->acl_options['id'][$row['auth_option']] = (int) $row['auth_option_id'];
				$this->acl_options['option'][(int) $row['auth_option_id']] = $row['auth_option'];
			}
			$db->sql_freeresult($result);

			$cache->put('_kb_acl_options', $this->acl_options);
		}
		
		if (!trim($userdata['user_kb_permissions']))
		{
			$this->acl_cache($userdata);
		}

		// Fill ACL array
		$this->_fill_acl($userdata['user_kb_permissions']);

		// Verify bitstring length with options provided...
		$renew = false;
		$local_length = sizeof($this->acl_options['local']);

		// Specify comparing length (bitstring is padded to 31 bits)
		$local_length = ($local_length % 31) ? ($local_length - ($local_length % 31) + 31) : $local_length;

		// You thought we are finished now? Noooo... now compare them.
		foreach ($this->acl as $forum_id => $bitstring)
		{
			if (($forum_id && strlen($bitstring) != $local_length))
			{
				$renew = true;
				break;
			}
		}

		// If a bitstring within the list does not match the options, we have a user with incorrect permissions set and need to renew them
		if ($renew)
		{
			$this->acl_cache($userdata);
			$this->_fill_acl($userdata['user_kb_permissions']);
		}

		// Fill auth cache with these settings
		foreach($this->acl as $f => $auth_setting)
		{
			foreach($this->acl_options['local'] as $opt => $i)
			{
				if ($f != 0 && isset($this->acl_options['local'][$opt]))
				{
					if (isset($this->acl[$f]) && isset($this->acl[$f][$this->acl_options['local'][$opt]]))
					{
						$auth->cache[$f][$opt] = $this->acl[$f][$this->acl_options['local'][$opt]];
					}
				}
			}
		}
		
		return;
	}
	
	// Fill kb_auth_options with the KB auth options
	function _fill_kb_auth_options()
	{
		$this->kb_auth_options = array(
			'u_', 'u_kb_add', 'u_kb_add_co', 'u_kb_add_op', 
			'u_kb_add_wa', 'u_kb_attach', 'u_kb_bbcode', 
			'u_kb_comment', 'u_kb_delete', 'u_kb_download', 
			'u_kb_edit', 'u_kb_flash', 'u_kb_icons', 
			'u_kb_img', 'u_kb_rate', 'u_kb_read', 
			'u_kb_request', 'u_kb_search', 'u_kb_sigs',
			'u_kb_smilies', 'u_kb_types', 'u_kb_view',
			'u_kb_viewhistory');
	}
	
	/**
	* Fill ACL array with relevant bitstrings from user_permissions column
	* @access private
	*/
	function _fill_acl($user_permissions)
	{
		$this->acl = array();
		$user_permissions = explode("\n", $user_permissions);

		foreach ($user_permissions as $f => $seq)
		{
			if ($seq)
			{
				$i = 0;

				if (!isset($this->acl[$f]))
				{
					$this->acl[$f] = '';
				}

				while ($subseq = substr($seq, $i, 6))
				{
					// We put the original bitstring into the acl array
					$this->acl[$f] .= str_pad(base_convert($subseq, 36, 2), 31, 0, STR_PAD_LEFT);
					$i += 6;
				}
			}
		}
	}
	
	function acl_cache(&$userdata)
	{
		global $db;

		// Empty user_permissions
		$userdata['user_permissions'] = '';

		$hold_ary = $this->acl_raw_data_single_user($userdata['user_id']);
		$hold_str = $this->build_bitstring($hold_ary);

		if ($hold_str)
		{
			$userdata['user_kb_permissions'] = $hold_str;

			$sql = 'UPDATE ' . USERS_TABLE . "
				SET user_kb_permissions = '" . $db->sql_escape($userdata['user_kb_permissions']) . "',
					user_perm_from = 0
				WHERE user_id = " . $userdata['user_id'];
			$db->sql_query($sql);
		}

		return;
	}
	
	/**
	* Get raw acl data based on user for caching user_permissions
	* This function returns the same data as acl_raw_data(), but without the user id as the first key within the array.
	*/
	function acl_raw_data_single_user($user_id)
	{
		global $db, $cache;

		// Check if the role-cache is there
		if (($this->role_cache = $cache->get('_role_cache')) === false)
		{
			$this->role_cache = array();

			// We pre-fetch roles
			$sql = 'SELECT *
				FROM ' . ACL_ROLES_DATA_TABLE . '
				ORDER BY role_id ASC';
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$this->role_cache[$row['role_id']][$row['auth_option_id']] = (int) $row['auth_setting'];
			}
			$db->sql_freeresult($result);

			foreach ($this->role_cache as $role_id => $role_options)
			{
				$this->role_cache[$role_id] = serialize($role_options);
			}

			$cache->put('_role_cache', $this->role_cache);
		}

		$hold_ary = array();

		// Grab user-specific permission settings
		$sql = 'SELECT forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . KB_ACL_USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// If a role is assigned, assign all options included within this role. Else, only set this one option.
			if ($row['auth_role_id'])
			{
				$hold_ary[$row['forum_id']] = (empty($hold_ary[$row['forum_id']])) ? unserialize($this->role_cache[$row['auth_role_id']]) : $hold_ary[$row['forum_id']] + unserialize($this->role_cache[$row['auth_role_id']]);
			}
			else
			{
				$hold_ary[$row['forum_id']][$row['auth_option_id']] = $row['auth_setting'];
			}
		}
		$db->sql_freeresult($result);

		// Now grab group-specific permission settings
		$sql = 'SELECT a.forum_id, a.auth_option_id, a.auth_role_id, a.auth_setting
			FROM ' . KB_ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g
			WHERE a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				AND ug.user_id = ' . $user_id;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if (!$row['auth_role_id'])
			{
				$this->_set_group_hold_ary($hold_ary[$row['forum_id']], $row['auth_option_id'], $row['auth_setting']);
			}
			else if (!empty($this->role_cache[$row['auth_role_id']]))
			{
				foreach (unserialize($this->role_cache[$row['auth_role_id']]) as $option_id => $setting)
				{
					$this->_set_group_hold_ary($hold_ary[$row['forum_id']], $option_id, $setting);
				}
			}
		}
		$db->sql_freeresult($result);

		return $hold_ary;
	}
	
	/**
	* Get raw group based permission settings
	*/
	function acl_group_raw_data($group_id = false, $opts = false, $forum_id = false)
	{
		global $db;

		$sql_group = ($group_id !== false) ? ((!is_array($group_id)) ? 'group_id = ' . (int) $group_id : $db->sql_in_set('group_id', array_map('intval', $group_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = '';
		$hold_ary = $sql_ary = array();

		if ($opts !== false)
		{
			$this->build_auth_option_statement('ao.auth_option', $opts, $sql_opts);
		}

		// Grab group settings - non-role specific...
		$sql_ary[] = 'SELECT a.group_id, a.forum_id, a.auth_setting, a.auth_option_id, ao.auth_option
			FROM ' . KB_ACL_GROUPS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = 0
				AND a.auth_option_id = ao.auth_option_id ' .
				(($sql_group) ? 'AND a.' . $sql_group : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		// Now grab group settings - role specific...
		$sql_ary[] = 'SELECT a.group_id, a.forum_id, r.auth_setting, r.auth_option_id, ao.auth_option
			FROM ' . KB_ACL_GROUPS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = r.role_id
				AND r.auth_option_id = ao.auth_option_id ' .
				(($sql_group) ? 'AND a.' . $sql_group : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		foreach ($sql_ary as $sql)
		{
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$hold_ary[$row['group_id']][$row['forum_id']][$row['auth_option']] = $row['auth_setting'];
			}
			$db->sql_freeresult($result);
		}

		return $hold_ary;
	}
	
	/**
	* Get raw user based permission settings
	*/
	function acl_user_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		global $db;

		$sql_user = ($user_id !== false) ? ((!is_array($user_id)) ? 'user_id = ' . (int) $user_id : $db->sql_in_set('user_id', array_map('intval', $user_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = '';
		$hold_ary = $sql_ary = array();

		if ($opts !== false)
		{
			$this->build_auth_option_statement('ao.auth_option', $opts, $sql_opts);
		}

		// Grab user settings - non-role specific...
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, a.auth_setting, a.auth_option_id, ao.auth_option
			FROM ' . KB_ACL_USERS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = 0
				AND a.auth_option_id = ao.auth_option_id ' .
				(($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		// Now the role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, r.auth_option_id, r.auth_setting, r.auth_option_id, ao.auth_option
			FROM ' . KB_ACL_USERS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = r.role_id
				AND r.auth_option_id = ao.auth_option_id ' .
				(($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		foreach ($sql_ary as $sql)
		{
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$hold_ary[$row['user_id']][$row['forum_id']][$row['auth_option']] = $row['auth_setting'];
			}
			$db->sql_freeresult($result);
		}

		return $hold_ary;
	}
	
	/**
	* Get raw acl data based on user/option/forum
	*/
	function acl_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		global $db;

		$sql_user = ($user_id !== false) ? ((!is_array($user_id)) ? 'user_id = ' . (int) $user_id : $db->sql_in_set('user_id', array_map('intval', $user_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = $sql_opts_select = $sql_opts_from = '';
		$hold_ary = array();

		if ($opts !== false)
		{
			$sql_opts_select = ', ao.auth_option';
			$sql_opts_from = ', ' . ACL_OPTIONS_TABLE . ' ao';
			$this->build_auth_option_statement('ao.auth_option', $opts, $sql_opts);
		}

		$sql_ary = array();

		// Grab non-role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, a.auth_setting, a.auth_option_id' . $sql_opts_select . '
			FROM ' . KB_ACL_USERS_TABLE . ' a' . $sql_opts_from . '
			WHERE a.auth_role_id = 0 ' .
				(($sql_opts_from) ? 'AND a.auth_option_id = ao.auth_option_id ' : '') .
				(($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		// Now the role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, r.auth_option_id, r.auth_setting, r.auth_option_id' . $sql_opts_select . '
			FROM ' . KB_ACL_USERS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r' . $sql_opts_from . '
			WHERE a.auth_role_id = r.role_id ' .
				(($sql_opts_from) ? 'AND r.auth_option_id = ao.auth_option_id ' : '') .
				(($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		foreach ($sql_ary as $sql)
		{
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$option = ($sql_opts_select) ? $row['auth_option'] : $this->acl_options['option'][$row['auth_option_id']];
				$hold_ary[$row['user_id']][$row['forum_id']][$option] = $row['auth_setting'];
			}
			$db->sql_freeresult($result);
		}

		$sql_ary = array();

		// Now grab group settings - non-role specific...
		$sql_ary[] = 'SELECT ug.user_id, a.forum_id, a.auth_setting, a.auth_option_id' . $sql_opts_select . '
			FROM ' . KB_ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g' . $sql_opts_from . '
			WHERE a.auth_role_id = 0 ' .
				(($sql_opts_from) ? 'AND a.auth_option_id = ao.auth_option_id ' : '') . '
				AND a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				' . (($sql_user) ? 'AND ug.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		// Now grab group settings - role specific...
		$sql_ary[] = 'SELECT ug.user_id, a.forum_id, r.auth_setting, r.auth_option_id' . $sql_opts_select . '
			FROM ' . KB_ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g, ' . ACL_ROLES_DATA_TABLE . ' r' . $sql_opts_from . '
			WHERE a.auth_role_id = r.role_id ' .
				(($sql_opts_from) ? 'AND r.auth_option_id = ao.auth_option_id ' : '') . '
				AND a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				' . (($sql_user) ? 'AND ug.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		foreach ($sql_ary as $sql)
		{
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$option = ($sql_opts_select) ? $row['auth_option'] : $this->acl_options['option'][$row['auth_option_id']];

				if (!isset($hold_ary[$row['user_id']][$row['forum_id']][$option]) || (isset($hold_ary[$row['user_id']][$row['forum_id']][$option]) && $hold_ary[$row['user_id']][$row['forum_id']][$option] != ACL_NEVER))
				{
					$hold_ary[$row['user_id']][$row['forum_id']][$option] = $row['auth_setting'];

					// If we detect ACL_NEVER, we will unset the flag option (within building the bitstring it is correctly set again)
					if ($row['auth_setting'] == ACL_NEVER)
					{
						$flag = substr($option, 0, strpos($option, '_') + 1);

						if (isset($hold_ary[$row['user_id']][$row['forum_id']][$flag]) && $hold_ary[$row['user_id']][$row['forum_id']][$flag] == ACL_YES)
						{
							unset($hold_ary[$row['user_id']][$row['forum_id']][$flag]);

/*							if (in_array(ACL_YES, $hold_ary[$row['user_id']][$row['forum_id']]))
							{
								$hold_ary[$row['user_id']][$row['forum_id']][$flag] = ACL_YES;
							}
*/
						}
					}
				}
			}
			$db->sql_freeresult($result);
		}

		return $hold_ary;
	}
	
	/**
	* Get assigned roles
	*/
	function acl_role_data($user_type, $role_type, $ug_id = false, $forum_id = false)
	{
		global $db;

		$roles = array();

		$sql_id = ($user_type == 'user') ? 'user_id' : 'group_id';

		$sql_ug = ($ug_id !== false) ? ((!is_array($ug_id)) ? "AND a.$sql_id = $ug_id" : 'AND ' . $db->sql_in_set("a.$sql_id", $ug_id)) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? "AND a.forum_id = $forum_id" : 'AND ' . $db->sql_in_set('a.forum_id', $forum_id)) : '';

		// Grab assigned roles...
		$sql = 'SELECT a.auth_role_id, a.' . $sql_id . ', a.forum_id
			FROM ' . (($user_type == 'user') ? KB_ACL_USERS_TABLE : KB_ACL_GROUPS_TABLE) . ' a, ' . ACL_ROLES_TABLE . " r
			WHERE a.auth_role_id = r.role_id
				AND r.role_type = '" . $db->sql_escape($role_type) . "'
				$sql_ug
				$sql_forum
			ORDER BY r.role_order ASC";
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$roles[$row[$sql_id]][$row['forum_id']] = $row['auth_role_id'];
		}
		$db->sql_freeresult($result);

		return $roles;
	}
	
	/**
	* Private function snippet for setting a specific piece of the hold_ary
	*/
	function _set_group_hold_ary(&$hold_ary, $option_id, $setting)
	{
		if (!isset($hold_ary[$option_id]) || (isset($hold_ary[$option_id]) && $hold_ary[$option_id] != ACL_NEVER))
		{
			$hold_ary[$option_id] = $setting;

			// If we detect ACL_NEVER, we will unset the flag option (within building the bitstring it is correctly set again)
			if ($setting == ACL_NEVER)
			{
				$flag = substr($this->acl_options['option'][$option_id], 0, strpos($this->acl_options['option'][$option_id], '_') + 1);
				$flag = (int) $this->acl_options['id'][$flag];

				if (isset($hold_ary[$flag]) && $hold_ary[$flag] == ACL_YES)
				{
					unset($hold_ary[$flag]);

/*					This is uncommented, because i suspect this being slightly wrong due to mixed permission classes being possible
					if (in_array(ACL_YES, $hold_ary))
					{
						$hold_ary[$flag] = ACL_YES;
					}*/
				}
			}
		}
	}
	
	/**
	* Build bitstring from permission set
	*/
	function build_bitstring(&$hold_ary)
	{
		$hold_str = '';

		if (sizeof($hold_ary))
		{
			ksort($hold_ary);

			$last_f = 0;

			foreach ($hold_ary as $f => $auth_ary)
			{
				$ary_key = (!$f) ? 'global' : 'local';

				$bitstring = array();
				foreach ($this->acl_options[$ary_key] as $opt => $id)
				{
					if (isset($auth_ary[$this->acl_options['id'][$opt]]))
					{
						$bitstring[$id] = $auth_ary[$this->acl_options['id'][$opt]];

						$option_key = substr($opt, 0, strpos($opt, '_') + 1);

						// If one option is allowed, the global permission for this option has to be allowed too
						// example: if the user has the a_ permission this means he has one or more a_* permissions
						if ($auth_ary[$this->acl_options['id'][$opt]] == ACL_YES && (!isset($bitstring[$this->acl_options[$ary_key][$option_key]]) || $bitstring[$this->acl_options[$ary_key][$option_key]] == ACL_NEVER))
						{
							$bitstring[$this->acl_options[$ary_key][$option_key]] = ACL_YES;
						}
					}
					else
					{
						$bitstring[$id] = ACL_NEVER;
					}
				}

				// Now this bitstring defines the permission setting for the current forum $f (or global setting)
				$bitstring = implode('', $bitstring);

				// The line number indicates the id, therefore we have to add empty lines for those ids not present
				$hold_str .= str_repeat("\n", $f - $last_f);

				// Convert bitstring for storage - we do not use binary/bytes because PHP's string functions are not fully binary safe
				for ($i = 0, $bit_length = strlen($bitstring); $i < $bit_length; $i += 31)
				{
					$hold_str .= str_pad(base_convert(str_pad(substr($bitstring, $i, 31), 31, 0, STR_PAD_RIGHT), 2, 36), 6, 0, STR_PAD_LEFT);
				}

				$last_f = $f;
			}
			unset($bitstring);

			$hold_str = rtrim($hold_str);
		}

		return $hold_str;
	}
	
	/**
	* Fill auth_option statement for later querying based on the supplied options
	*/
	function build_auth_option_statement($key, $auth_options, &$sql_opts)
	{
		global $db;

		if (!is_array($auth_options))
		{
			if (strpos($auth_options, '%') !== false)
			{
				$sql_opts = "AND $key " . $db->sql_like_expression(str_replace('%', $db->any_char, $auth_options));
			}
			else
			{
				$sql_opts = "AND $key = '" . $db->sql_escape($auth_options) . "'";
			}
		}
		else
		{
			$is_like_expression = false;

			foreach ($auth_options as $option)
			{
				if (strpos($option, '%') !== false)
				{
					$is_like_expression = true;
				}
			}

			if (!$is_like_expression)
			{
				$sql_opts = 'AND ' . $db->sql_in_set($key, $auth_options);
			}
			else
			{
				$sql = array();

				foreach ($auth_options as $option)
				{
					if (strpos($option, '%') !== false)
					{
						$sql[] = $key . ' ' . $db->sql_like_expression(str_replace('%', $db->any_char, $option));
					}
					else
					{
						$sql[] = $key . " = '" . $db->sql_escape($option) . "'";
					}
				}

				$sql_opts = 'AND (' . implode(' OR ', $sql) . ')';
			}
		}
	}
}
?>