<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_forum_bot.php 462 2010-04-17 14:30:38Z softphp $
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
	if (!function_exists('make_forum_select'))
	{
		include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
	}

	$acp_options['legend1'] 					= 'ARTICLE_POST_BOT';
	$acp_options['kb_forum_bot_enable'] 		= array('lang' => 'ENABLE_FORUM_BOT',			'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_forum_bot_user'] 			= array('lang' => 'ARTICLE_POST_BOT_USER',		'validate' => 'int',	'type' => 'text:3:5', 		'explain' 	=> true);
	$acp_options['kb_forum_bot_forum_id'] 		= array('lang' => 'FORUM_ID',					'validate' => 'int',	'type' => 'select',			'function'	=> 'make_forum_select',		'params'	=> array(isset($config['kb_forum_bot_forum_id']) ? $config['kb_forum_bot_forum_id'] : false, false, true, true),	'explain' 	=> true);
	$acp_options['kb_forum_bot_subject'] 		= array('lang' => 'ARTICLE_POST_BOT_SUB',		'validate' => 'string',	'type' => 'text:30:50', 	'explain' 	=> true);
	$acp_options['kb_forum_bot_message'] 		= array('lang' => 'ARTICLE_POST_BOT_MSG',		'validate' => 'string',	'type' => 'textarea:10:12', 'explain' 	=> true);
		
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_BOT',
		'PLUGIN_DESC'			=> 'PLUGIN_BOT_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> NO_MENU,
		'PLUGIN_PAGE_PERM'		=> array('add'),
		'PLUGIN_PAGES'			=> array('add'),
	);
}

/**
* Extra details for the mod does it need to call functions on certain areas?
*/
$on_article_post[] = 'post_new_article';
$on_article_approve[] = 'post_new_article';

// Doesn't need to pharse anything but we need it or errors will appear
function forum_bot($cat_id = 0)
{
	global $config;
	
	if (!$config['kb_forum_bot_enable'])
	{
		return;
	}
}

function forum_bot_versions()
{
	global $user;

	$versions = array(
		'0.0.1'	=> array(			
			'config_add'	=> array(
				array('kb_forum_bot_enable', 1),
			),
		),
		
		'0.0.2'	=> array(			
			'config_add'	=> array(
				array('kb_forum_bot_user', $user->data['user_id']),
				array('kb_forum_bot_message', $user->lang['ARTICLE_POST_BOT_MSG_EX']),
			),
		),
		
		'0.0.3'	=> array(			
			'config_add'	=> array(
				array('kb_forum_bot_subject', $user->lang['ARTICLE_POST_BOT_SUB_EX']),
			),
		),
		
		'0.0.4'	=> array(			
			'config_add'	=> array(
				array('kb_forum_bot_forum_id', ''),
			),
		),
		
		//Major release
		'1.0.0'	=> array(	
		),
	);

	return $versions;
}

function append_to_kb_options()
{
	global $user, $phpEx;

	$content = "<table cellspacing=\"1\">
		<caption>" . $user->lang['AVAILABLE'] . ' ' . $user->lang['VARIABLE'] . "</caption>
		<col class=\"col1\" /><col class=\"col2\" /><col class=\"col1\" />
			<thead>
				<tr>
					<th>" . $user->lang['NAME'] . "</th>
					<th>" . $user->lang['VARIABLE'] . "</th>
					<th>" . $user->lang['EXAMPLE'] . "</th>
				</tr>
			</thead>

			<tbody>
				<tr>
					<td>" . $user->lang['ARTICLE_AUTHOR'] . "</td>
					<td><b>{AUTHOR}</b></td>
					<td><b>" . $user->data['username'] . "</b></td>
				</tr>
				<tr>
					<td>" . $user->lang['ARTICLE_TITLE'] . "</td>
					<td><b>{TITLE}</b></td>
					<td><b>" . $user->lang['ARTICLE_TITLE_EX'] . "</b></td>
				</tr>
				<tr>
					<td>" . $user->lang['ARTICLE_DESC'] . "</td>
					<td><b>{DESC}</b></td>
					<td><b>" . $user->lang['ARTICLE_DESC_EX'] . "</b></td>
				</tr>
				<tr>
					<td>" . $user->lang['ARTICLE_TIME'] . "</td>
					<td><b>{TIME}</b></td>
					<td><b>" . $user->format_date(time()) . "</b></td>
				</tr>
				<tr>
					<td>" . $user->lang['ARTICLE_LINK'] . "</td>
					<td><b>{LINK}</b></td>
					<td><b>" . generate_board_url() . '/kb.' . $phpEx . "?a=1</b></td>
				</tr>
			</tbody>
		</table>
	";

	return $content;
}

function change_auth($user_id, $mode = 'replace', $data = false)
{
	global $user, $auth, $db;
	
	switch($mode)
	{
		// in 3.0.6 auths are no longer a concern thanks to "force_approved_state"
		case 'replace':
				$data = array(
						'user_backup'   => $user->data,
				);
										
				// sql to get the bots info
				$sql = 'SELECT *
						FROM ' . USERS_TABLE . '
						WHERE user_id = ' . (int) $user_id;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// reset the current users info to that of the bot      
				$user->data = array_merge($user->data, $row);			
				unset($row);
				
				return $data;                                      
		break;
		
		// now we restore the users stuff
		case 'restore':
				$user->data = $data['user_backup'];

				unset($data);
		break;
	}
}

function post_new_article($data)
{
	global $config, $user, $phpbb_root_path, $phpEx;

	if (!function_exists('user_notification'))
	{
		include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
	}
	
	if (($config['kb_forum_bot_forum_id'] == '' || $config['kb_forum_bot_forum_id'] == 0)
		|| ($config['kb_forum_bot_user'] == '' || $config['kb_forum_bot_user'] == 0)
		|| $config['kb_forum_bot_subject'] == '' 
		|| $config['kb_forum_bot_message'] == '')
	{
		return;
	}
	
	$vars = array(
		'{AUTHOR}'		=> '[url=' . generate_board_url() . '/memberlist.' . $phpEx . '?mode=viewprofile&u=' . $data['article_user_id'] . '][color=#' . $data['article_user_color'] . ']' . $data['article_user_name'] . '[/color][/url]',
		'{TITLE}'		=> $data['article_title'],
		'{DESC}'		=> $data['article_desc'],
		'{TIME}'		=> $user->format_date($data['article_time']),
		'{LINK}'		=> generate_board_url() . '/kb.' . $phpEx . '?a=' . $data['article_id'],
	);
	
	//Get post bots permissions
	$perms = change_auth($config['kb_forum_bot_user']);

	//Parse the text with the bbcode parser and write into $text
	$subject	= utf8_normalize_nfc($config['kb_forum_bot_subject']);
	$message	= utf8_normalize_nfc($config['kb_forum_bot_message']);
	
	$message = str_replace(array_keys($vars), array_values($vars), $message);
	$subject = str_replace(array_keys($vars), array_values($vars), $subject);
	
	// variables to hold the parameters for submit_post
	$poll = $uid = $bitfield = $options = ''; 
	generate_text_for_storage($subject, $uid, $bitfield, $options, false, false, false);
	generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);

	$data = array( 
		'forum_id'				=> $config['kb_forum_bot_forum_id'],
		'icon_id'				=> false,
	
		'enable_bbcode'			=> true,
		'enable_smilies'		=> true,
		'enable_urls'			=> true,
		'enable_sig'			=> true,

		'message'				=> $message,
		'post_checksum'			=> '',
		'message_md5'			=> '',
					
		'bbcode_bitfield'		=> $bitfield,
		'bbcode_uid'			=> $uid,

		'post_edit_locked'		=> 0,
		'topic_title'			=> $subject,
		'notify_set'			=> false,
		'notify'				=> false,
		'post_time' 			=> 0,
		'forum_name'			=> '',
		'enable_indexing'		=> true,
		
		'force_approved_state'  => true,
	);

	kb_submit_post('post', $subject, '', POST_NORMAL, $poll, $data);	
	
	//Restore user permissions
	change_auth('', 'restore', $perms);	
}

/**
* Copy of standard Submit Post function taken from 3.0.6 done so to stop conflicts with other mods that have changed it and stopped it working
* @todo Split up and create lightweight, simple API for this.
*/
function kb_submit_post($mode, $subject, $username, $topic_type, &$poll, &$data, $update_message = true, $update_search_index = true)
{
	global $db, $auth, $user, $config, $phpEx, $template, $phpbb_root_path;

	$current_time = time();

	$post_mode = 'post';
	$update_message = true;

	// First of all make sure the subject and topic title are having the correct length.
	// To achieve this without cutting off between special chars we convert to an array and then count the elements.
	$subject = truncate_string($subject);
	$data['topic_title'] = truncate_string($data['topic_title']);

	// Collect some basic information about which tables and which rows to update/insert
	$sql_data = $topic_row = array();
	$poster_id = ($mode == 'edit') ? $data['poster_id'] : (int) $user->data['user_id'];

	// Mods are able to force approved/unapproved posts. True means the post is approved, false the post is unapproved
	if (isset($data['force_approved_state']))
	{
		$post_approval = ($data['force_approved_state']) ? 1 : 0;
	}

	// Start the transaction here
	$db->sql_transaction('begin');

	$sql_data[POSTS_TABLE]['sql'] = array(
		'forum_id'			=> ($topic_type == POST_GLOBAL) ? 0 : $data['forum_id'],
		'poster_id'			=> (int) $user->data['user_id'],
		'icon_id'			=> $data['icon_id'],
		'poster_ip'			=> $user->ip,
		'post_time'			=> $current_time,
		'post_approved'		=> $post_approval,
		'enable_bbcode'		=> $data['enable_bbcode'],
		'enable_smilies'	=> $data['enable_smilies'],
		'enable_magic_url'	=> $data['enable_urls'],
		'enable_sig'		=> $data['enable_sig'],
		'post_username'		=> (!$user->data['is_registered']) ? $username : '',
		'post_subject'		=> $subject,
		'post_text'			=> $data['message'],
		'post_checksum'		=> $data['message_md5'],
		'post_attachment'	=> (!empty($data['attachment_data'])) ? 1 : 0,
		'bbcode_bitfield'	=> $data['bbcode_bitfield'],
		'bbcode_uid'		=> $data['bbcode_uid'],
		'post_postcount'	=> ($auth->acl_get('f_postcount', $data['forum_id'])) ? 1 : 0,
		'post_edit_locked'	=> $data['post_edit_locked']
	);

	$post_approved = $sql_data[POSTS_TABLE]['sql']['post_approved'];
	$topic_row = array();

	$sql_data[TOPICS_TABLE]['sql'] = array(
		'topic_poster'				=> (int) $user->data['user_id'],
		'topic_time'				=> $current_time,
		'topic_last_view_time'		=> $current_time,
		'forum_id'					=> ($topic_type == POST_GLOBAL) ? 0 : $data['forum_id'],
		'icon_id'					=> $data['icon_id'],
		'topic_approved'			=> $post_approval,
		'topic_title'				=> $subject,
		'topic_first_poster_name'	=> (!$user->data['is_registered'] && $username) ? $username : (($user->data['user_id'] != ANONYMOUS) ? $user->data['username'] : ''),
		'topic_first_poster_colour'	=> $user->data['user_colour'],
		'topic_type'				=> $topic_type,
		'topic_time_limit'			=> ($topic_type == POST_STICKY || $topic_type == POST_ANNOUNCE) ? ($data['topic_time_limit'] * 86400) : 0,
		'topic_attachment'			=> (!empty($data['attachment_data'])) ? 1 : 0,
	);

	$sql_data[USERS_TABLE]['stat'][] = "user_lastpost_time = $current_time" . (($auth->acl_get('f_postcount', $data['forum_id']) && $post_approval) ? ', user_posts = user_posts + 1' : '');

	if ($topic_type != POST_GLOBAL)
	{
		if ($post_approval)
		{
			$sql_data[FORUMS_TABLE]['stat'][] = 'forum_posts = forum_posts + 1';
		}
		$sql_data[FORUMS_TABLE]['stat'][] = 'forum_topics_real = forum_topics_real + 1' . (($post_approval) ? ', forum_topics = forum_topics + 1' : '');
	}

	$sql = 'INSERT INTO ' . TOPICS_TABLE . ' ' .
		$db->sql_build_array('INSERT', $sql_data[TOPICS_TABLE]['sql']);
	$db->sql_query($sql);

	$data['topic_id'] = $db->sql_nextid();

	$sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
		'topic_id' => $data['topic_id'])
	);
	unset($sql_data[TOPICS_TABLE]['sql']);

	// Submit new post
	$sql = 'INSERT INTO ' . POSTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[POSTS_TABLE]['sql']);
	$db->sql_query($sql);
	$data['post_id'] = $db->sql_nextid();

	$sql_data[TOPICS_TABLE]['sql'] = array(
		'topic_first_post_id'		=> $data['post_id'],
		'topic_last_post_id'		=> $data['post_id'],
		'topic_last_post_time'		=> $current_time,
		'topic_last_poster_id'		=> (int) $user->data['user_id'],
		'topic_last_poster_name'	=> (!$user->data['is_registered'] && $username) ? $username : (($user->data['user_id'] != ANONYMOUS) ? $user->data['username'] : ''),
		'topic_last_poster_colour'	=> $user->data['user_colour'],
		'topic_last_post_subject'	=> (string) $subject,
	);
	unset($sql_data[POSTS_TABLE]['sql']);

	// Update the topics table
	if (isset($sql_data[TOPICS_TABLE]['sql']))
	{
		$sql = 'UPDATE ' . TOPICS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[TOPICS_TABLE]['sql']) . '
			WHERE topic_id = ' . $data['topic_id'];
		$db->sql_query($sql);
	}

	// Update the posts table
	if (isset($sql_data[POSTS_TABLE]['sql']))
	{
		$sql = 'UPDATE ' . POSTS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $sql_data[POSTS_TABLE]['sql']) . '
			WHERE post_id = ' . $data['post_id'];
		$db->sql_query($sql);
	}

	$sql_data[FORUMS_TABLE]['stat'][] = 'forum_last_post_id = ' . $data['post_id'];
	$sql_data[FORUMS_TABLE]['stat'][] = "forum_last_post_subject = '" . $db->sql_escape($subject) . "'";
	$sql_data[FORUMS_TABLE]['stat'][] = 'forum_last_post_time = ' . $current_time;
	$sql_data[FORUMS_TABLE]['stat'][] = 'forum_last_poster_id = ' . (int) $user->data['user_id'];
	$sql_data[FORUMS_TABLE]['stat'][] = "forum_last_poster_name = '" . $db->sql_escape((!$user->data['is_registered'] && $username) ? $username : (($user->data['user_id'] != ANONYMOUS) ? $user->data['username'] : '')) . "'";
	$sql_data[FORUMS_TABLE]['stat'][] = "forum_last_poster_colour = '" . $db->sql_escape($user->data['user_colour']) . "'";

	// Update total post count, do not consider moderated posts/topics
	if ($post_approval)
	{
		set_config_count('num_topics', 1, true);
		set_config_count('num_posts', 1, true);
	}

	// Update forum stats
	$where_sql = array(POSTS_TABLE => 'post_id = ' . $data['post_id'], TOPICS_TABLE => 'topic_id = ' . $data['topic_id'], FORUMS_TABLE => 'forum_id = ' . $data['forum_id'], USERS_TABLE => 'user_id = ' . $poster_id);

	foreach ($sql_data as $table => $update_ary)
	{
		if (isset($update_ary['stat']) && implode('', $update_ary['stat']))
		{
			$sql = "UPDATE $table SET " . implode(', ', $update_ary['stat']) . ' WHERE ' . $where_sql[$table];
			$db->sql_query($sql);
		}
	}

	// Committing the transaction before updating search index
	$db->sql_transaction('commit');

	// Index message contents
	if ($update_search_index && $data['enable_indexing'])
	{
		// Select the search method and do some additional checks to ensure it can actually be utilised
		$search_type = basename($config['search_type']);

		if (!file_exists($phpbb_root_path . 'includes/search/' . $search_type . '.' . $phpEx))
		{
			trigger_error('NO_SUCH_SEARCH_MODULE');
		}

		if (!class_exists($search_type))
		{
			include("{$phpbb_root_path}includes/search/$search_type.$phpEx");
		}

		$error = false;
		$search = new $search_type($error);

		if ($error)
		{
			trigger_error($error);
		}

		$search->index($mode, $data['post_id'], $data['message'], $subject, $poster_id, ($topic_type == POST_GLOBAL) ? 0 : $data['forum_id']);
	}

	// Topic Notification, do not change if moderator is changing other users posts...
	if ($user->data['user_id'] == $poster_id)
	{
		if (!$data['notify_set'] && $data['notify'])
		{
			$sql = 'INSERT INTO ' . TOPICS_WATCH_TABLE . ' (user_id, topic_id)
				VALUES (' . $user->data['user_id'] . ', ' . $data['topic_id'] . ')';
			$db->sql_query($sql);
		}
		else if (($config['email_enable'] || $config['jab_enable']) && $data['notify_set'] && !$data['notify'])
		{
			$sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . '
				WHERE user_id = ' . $user->data['user_id'] . '
					AND topic_id = ' . $data['topic_id'];
			$db->sql_query($sql);
		}
	}

	// Mark this topic as posted to
	markread('post', $data['forum_id'], $data['topic_id'], $data['post_time']);

	// Mark this topic as read
	// We do not use post_time here, this is intended (post_time can have a date in the past if editing a message)
	markread('topic', (($topic_type == POST_GLOBAL) ? 0 : $data['forum_id']), $data['topic_id'], time());

	//
	if ($config['load_db_lastread'] && $user->data['is_registered'])
	{
		$sql = 'SELECT mark_time
			FROM ' . FORUMS_TRACK_TABLE . '
			WHERE user_id = ' . $user->data['user_id'] . '
				AND forum_id = ' . (($topic_type == POST_GLOBAL) ? 0 : $data['forum_id']);
		$result = $db->sql_query($sql);
		$f_mark_time = (int) $db->sql_fetchfield('mark_time');
		$db->sql_freeresult($result);
	}
	else if ($config['load_anon_lastread'] || $user->data['is_registered'])
	{
		$f_mark_time = false;
	}

	if (($config['load_db_lastread'] && $user->data['is_registered']) || $config['load_anon_lastread'] || $user->data['is_registered'])
	{
		// Update forum info
		if ($topic_type == POST_GLOBAL)
		{
			$sql = 'SELECT MAX(topic_last_post_time) as forum_last_post_time
				FROM ' . TOPICS_TABLE . '
				WHERE forum_id = 0';
		}
		else
		{
			$sql = 'SELECT forum_last_post_time
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . $data['forum_id'];
		}
		$result = $db->sql_query($sql);
		$forum_last_post_time = (int) $db->sql_fetchfield('forum_last_post_time');
		$db->sql_freeresult($result);

		update_forum_tracking_info((($topic_type == POST_GLOBAL) ? 0 : $data['forum_id']), $forum_last_post_time, $f_mark_time, false);
	}

	// Send Notifications
	if ($mode != 'edit' && $mode != 'delete' && $post_approval)
	{
		user_notification($mode, $subject, $data['topic_title'], $data['forum_name'], $data['forum_id'], $data['topic_id'], $data['post_id']);
	}

	$params = $add_anchor = '';

	if ($post_approval)
	{
		$params .= '&amp;t=' . $data['topic_id'];

		if ($mode != 'post')
		{
			$params .= '&amp;p=' . $data['post_id'];
			$add_anchor = '#p' . $data['post_id'];
		}
	}
	else if ($mode != 'post' && $post_mode != 'edit_first_post' && $post_mode != 'edit_topic')
	{
		$params .= '&amp;t=' . $data['topic_id'];
	}

	$url = (!$params) ? "{$phpbb_root_path}viewforum.$phpEx" : "{$phpbb_root_path}viewtopic.$phpEx";
	$url = append_sid($url, 'f=' . $data['forum_id'] . $params) . $add_anchor;

	return $url;
}

?>