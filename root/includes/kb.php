<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb.php 514 2010-06-23 12:32:18Z andreas.nexmann@gmail.com $
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

class knowledge_base
{
	var $article_id = 0;
	var $cat_id;
	var $tag = '';
	var $mode = '';
	var $sort = '';
	var $start = 0;
	var $action = '';
	var $config = array();
	var $popup = array();
	var $reverse = false;
	
	// Attachment data
	var $attachment_data = array();
	var $filename_data = array();
	
	// Warning message
	var $warn_msg = array();
	
	/**
	* Constructor
	* Set all variables needed for the kb to run.
	*/
	function knowledge_base($cat_id, $generate_output = true)
	{
		$this->article_id 	= request_var('a', 0);
		$this->cat_id 		= $cat_id;
		$this->tag 			= request_var('t', '', true);
		$this->mode 		= request_var('i', '');
		$this->sort 		= request_var('sort', '');
		$this->dir	 		= request_var('dir', 'DESC');
		$this->start 		= request_var('start', 0);
		$this->action 		= request_var('action', '');
		$this->hilit_words 	= request_var('hilit', '', true);
		$this->config 		= array();
		
		if($generate_output)
		{
			// Try and shorten the links a bit so we avoid a lot of mode calls in the front part
			if($this->mode == '')
			{
				if($this->article_id > 0)
				{
					$this->mode = 'view_article';
				}
				else if($this->cat_id > 0)
				{
					$this->mode = 'view_cat';					
				}
				else if($this->tag !== '')
				{
					$this->mode = 'view_tag';
				}
				else
				{
					$this->mode = 'index';
				}
			}
			
			// Check for popups
			$this->popup = kb_generate_popups();
			
			if ($this->mode != 'view_article')
			{
				generate_menu($this->mode, $this->cat_id);
			}
			
			switch($this->mode)
			{
				// Article viewing
				case 'view_article':
					$this->generate_article_page();
				break;
					
				// View a cat or sort articles by tag or both
				case 'view_cat':
					$this->generate_article_list();
				break;
					
				// Article adding, deletion, etc
				case 'add':
				case 'delete':
				case 'edit':
				case 'popup':
				case 'smilies':
					$this->article_posting();
				break;
				
				// Handles all comment posting, editing, deleting and moderating
				case 'comment':
					$this->comment_posting();
				break;
					
				// Rate article
				case 'rate':
					$this->article_rate();
				break;
				
				// Search articles via query or tag
				case 'search':
					$this->search_articles();
				break;
				
				case 'view_tag':
					$this->view_tag();
				break;
				
				// Popup service
				case 'notify_popup':
					$this->show_notify_popup();
				break;
				
				// Show article history
				case 'history':
					$this->generate_article_history();
				break;
				
				// Handle request posting
				case 'request':
					$this->request_posting();
				break;
				
				case 'feed':
					$this->generate_feed();
				break;
								
				case 'email':
					$this->send_email();
				break;
				
				case 'export':
					$this->export_article();
				break;
				
				// View the kb index screen
				case '':
				case 'index':
				default:
					$this->generate_index_page();					
				break;
			}
		}
	}
	
	/**
	* Export article
	*/
	function export_article()
	{
		global $config;
		
		if(!$config['kb_export_article'])
		{
			trigger_error('KB_DISABLE_EXPORT');
		}
		
		export_data('word', $this->article_id);
	}
	
	/**
	* Send email of article
	*/
	function send_email()
	{
		global $auth, $user, $config, $db, $phpEx, $phpbb_root_path;
		
		$user->add_lang('memberlist');
		
		$starter 		= request_var('starter', '', true);
		$sender			= request_var('sender', '', true);
		$a_t			= request_var('a_t', '', true);
		$email			= request_var('email', '', true);
		
		if (!$config['email_enable'] || !$config['kb_email_article'])
		{
			trigger_error('EMAIL_DISABLED');
		}

		if (!$auth->acl_get('u_sendemail'))
		{
			trigger_error('NO_EMAIL');
		}

		// Are we trying to abuse the facility?
		if (time() - $user->data['user_emailtime'] < $config['flood_interval'])
		{
			trigger_error('FLOOD_EMAIL_LIMIT');
		}
		
		$sql = 'UPDATE ' . USERS_TABLE . '
			SET user_emailtime = ' . time() . '
			WHERE user_id = ' . $user->data['user_id'];
		$db->sql_query($sql);

		$this->finclude('functions_messenger', 'c:messenger');
		$messenger = new messenger(false);
		$email_tpl = 'kb_email_notify';
		
		$messenger->template($email_tpl, $config['default_lang']);
		$messenger->replyto($user->data['user_email']);
		$messenger->to($email);
		
		$notify_type = NOTIFY_EMAIL;

		$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
		$messenger->headers('X-AntiAbuse: User_id - ' . $user->data['user_id']);
		$messenger->headers('X-AntiAbuse: Username - ' . $user->data['username']);
		$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);

		$messenger->assign_vars(array(
			'TO_EMAIL'			=> $email,
			'FROM_EMAIL'		=> $user->data['user_email'],
			'FROM_USERNAME'		=> htmlspecialchars_decode($user->data['username']),
			'ARTICLE_NAME'		=> htmlspecialchars_decode($a_t),
			'U_ARTICLE_URL'		=> generate_board_url() . "/kb.$phpEx?a=" . $this->article_id)
		);

		$messenger->send($notify_type);
		
		meta_refresh(3, kb_append_sid('article', array('id' => $this->article_id, 'title' => $a_t), false, 'kb', true));
		$message = sprintf($user->lang['RETURN_KB_ARTICLE'], '<a href="' . kb_append_sid('article', array('id' => $this->article_id, 'title' => $a_t)) . '">', '</a>');
		trigger_error($user->lang['EMAIL_SENT'] . '<br /><br />' . $message);
	}
	
	/**
	* Compile feed here
	*/
	function generate_feed()
	{
		$feed 			= request_var('feed', 'RSS');
		$feed_type		= request_var('feed_type', '');
		$feed_cat_id	= request_var('feed_cat_id', 0);
		$feed_cat_name	= request_var('feed_cat_name', '', true);
		$feed_user_id	= request_var('feed_user_id', 0);
		$feed_username	= request_var('feed_username', '', true);
		
		$feed_data = array(
			'CAT_ID'		=> $feed_cat_id,
			'CAT_NAME'		=> $feed_cat_name,
			'USER_ID'		=> $feed_user_id,
			'USERNAME'		=> $feed_username,
		);
		
		feed_output($feed, $feed_type, $feed_data);
	}
	
	/**
	* Include function
	*/
	function finclude($file, $exists = '')
	{
		global $phpbb_root_path, $phpEx;
		
		if($exists != '' && !is_array($exists))
		{
			$exists = explode(':', $exists);
			$function = ($exists[0] == 'f') ? 'function_exists' : 'class_exists';
			
			if($function($exists[1]))
			{
				return;
			}
		}
		
		if(is_array($file))
		{
			foreach($file as $n => $filename)
			{
				$path = $phpbb_root_path . 'includes/' . $filename . '.' . $phpEx;
				
				if(isset($exists[$n]) && is_array($exists))
				{
					$exists_check = explode(':', $exists[$n]);
					$function = ($exists_check[0] == 'f') ? 'function_exists' : 'class_exists';
					
					if($function($exists_check[1]))
					{
						continue;
					}
				}
				
				include($path);
			}
		}
		else
		{
			$path = $phpbb_root_path . 'includes/' . $file . '.' . $phpEx;
			include($path);
		}
	}
	
	/** 
	* KB Header
	* Presently only used for popup generation
	*/
	function page_header($page_title)
	{
		global $template, $phpbb_root_path, $phpEx, $config, $user;
		
		page_header($page_title);
		
		if($this->popup['generate'])
		{
			// Generate a popup using phpbb's predefined popup generator, this will be overridden by page_header if there is a new pm.
			$template->assign_vars(array(
				'S_USER_PM_POPUP' 	=> true,
				'S_NEW_PM'			=> 1,
				'UA_POPUP_PM'		=> addslashes(append_sid("{$phpbb_root_path}kb.$phpEx", 'i=notify_popup&amp;a=' . $this->popup['article_id'])),
			));
		}
		
		// Assign other KB related vars to use on all pages
		$template->assign_vars(array(
			'LEFT_MENU_WIDTH'	=> $config['kb_left_menu_width'] . (($config['kb_left_menu_type'] == 0) ? 'px' : '%'),
			'RIGHT_MENU_WIDTH'	=> $config['kb_right_menu_width'] . (($config['kb_right_menu_type'] == 0) ? 'px' : '%'),
		));
	}
	
	/**
	* Article list generation page
	* This page generates a list of articles, based on a category id or tag
	*/
	function generate_article_list()
	{
		global $template, $config, $user, $db, $auth;
		global $phpbb_root_path, $auth, $phpEx, $cache;
		
		$this->finclude('functions_display', 'f:display_forums');
		$user->add_lang('viewtopic');
		
		if(!$this->cat_id)
		{
			trigger_error("KB_NO_CATEGORY");
		}
		
		$sql = "SELECT *
				FROM " . KB_CATS_TABLE . '
				WHERE cat_id = ' . $this->cat_id;
		$result = $db->sql_query($sql);
		$cat_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if(!$cat_data)
		{
			trigger_error("KB_NO_CATEGORY");
		}
		
		if (!$auth->acl_get('u_kb_view', $this->cat_id))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('KB_USER_CANNOT_VIEW');
			}
			
			$red_url = ($this->start > 0) ? "&amp;start=$this->start" : '';
			login_box(append_sid("{$phpbb_root_path}kb.$phpEx", "c=$this->cat_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_VIEW']);
		}		
		
		kb_display_cats($cat_data);
		
		// Show all articles to moderators
		$is_mod = ($auth->acl_get('m_kb_view')) ? '' : ' AND article_status = ' . STATUS_APPROVED;
		
		$sql = 'SELECT COUNT(article_id) AS num_articles
			FROM ' . KB_TABLE . '
			WHERE cat_id = ' . $this->cat_id . $is_mod;
		$result = $db->sql_query($sql);
		$articles_count = (int) $db->sql_fetchfield('num_articles');
		$db->sql_freeresult($result);
		
		// Make sure $start is set to the last page if it exceeds the amount
		if ($this->start < 0 || $this->start > $articles_count)
		{
			$this->start = ($this->start < 0) ? 0 : floor(($articles_count - 1) / $config['kb_articles_per_page']) * $config['kb_articles_per_page'];
		}
		
		// Grab icons
		$icons = $cache->obtain_icons();
		$types = $cache->obtain_article_types();
		
		switch($this->sort)
		{
			// Ability to sort by post time, edit time, name, views, author	
			case 'etime':
				$order_by = 'a.article_last_edit_time ' . $this->dir;
			break;
				
			case 'name':
				$order_by = 'a.article_title ' . $this->dir;
			break;
			
			case 'views':
				$order_by = 'a.article_views ' . $this->dir;
			break;
				
			case 'author':
				$order_by = 'a.article_user_name ' . $this->dir;
			break;
			
			case 'rating': 
				$order_by = 'rating ' . $this->dir;
			break;
			
			case 'time':
			case '':
			default:
				$order_by = 'a.article_time ' . $this->dir;
			break;
		}
		
		// Retrieve all articles
		// Use start to limit shown articles
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'a.*, AVG(r.rating) AS rating',
			'FROM'		=> array(
				KB_TABLE => 'a'),
			'LEFT_JOIN'	=> array(
				array(
					'FROM' => array(KB_RATE_TABLE => 'r'),
					'ON' => 'a.article_id = r.article_id',
				),
			),
			'WHERE'		=> 'a.cat_id = ' . $this->cat_id . $is_mod,
			'GROUP_BY'	=> 'a.article_id',
			'ORDER_BY'  => $order_by,
		));
		
		$result = $db->sql_query_limit($sql, $config['kb_articles_per_page'], $this->start);
		while($row = $db->sql_fetchrow($result))
		{
			// Get article types
			$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
			
			// Send vars to template
			$template->assign_block_vars('articlerow', array(
				'ARTICLE_ID'				=> $row['article_id'],
				'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
				'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
	
				'COMMENTS'					=> $row['article_comments'],
				'VIEWS'						=> $row['article_views'],
				'ARTICLE_TITLE'				=> censor_text($article_type['article_title']),
				'ARTICLE_DESC'				=> ($config['kb_show_desc_cat'] && !$config['kb_disable_desc']) ? generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options']) : '',
				'ARTICLE_FOLDER_IMG'		=> $user->img('topic_read', censor_text($row['article_title'])),
				'ARTICLE_FOLDER_IMG_SRC'	=> $user->img('topic_read', censor_text($row['article_title']), false, '', 'src'),
				'ARTICLE_FOLDER_IMG_ALT'	=> censor_text($row['article_title']),
				'ARTICLE_FOLDER_IMG_WIDTH'  => $user->img('topic_read', '', false, '', 'width'),
				'ARTICLE_FOLDER_IMG_HEIGHT'	=> $user->img('topic_read', '', false, '', 'height'),
				'ARTICLE_UNAPPROVED'		=> ($row['article_status'] != STATUS_APPROVED && $auth->acl_gets('m_kb_view', 'm_kb_status')) ? true : false,
				'U_MCP_QUEUE'				=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&hmode=status&a=' . $row['article_id']),
	
				'ARTICLE_TYPE_IMG'			=> $article_type['type_image']['img'],
				'ARTICLE_TYPE_IMG_WIDTH'	=> $article_type['type_image']['width'],
				'ARTICLE_TYPE_IMG_HEIGHT'	=> $article_type['type_image']['height'],
				'ATTACH_ICON_IMG'			=> ($auth->acl_get('u_kb_download', $this->cat_id) && $row['article_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
				'U_VIEW_ARTICLE'			=> kb_append_sid('article', array('id' => $row['article_id'], 'title' => censor_text($article_type['article_title']))),
			));
		}
		$db->sql_freeresult($result);
		
		$sort_direction = make_direction_select($this->dir);
		$sort_options = make_sort_select('articles', $this->sort);
		
		$template->assign_vars(array(
			'L_AUTHOR'			=> $user->lang['ARTICLE_AUTHOR'],
			'L_ARTICLES_LC' 	=> utf8_strtolower($user->lang['ARTICLES']),
			'S_HAS_SUBCATS' 	=> ($cat_data['left_id'] != $cat_data['right_id'] - 1) ? true : false,
			'PAGINATION'		=> generate_pagination(kb_append_sid('cat', array('id' => $this->cat_id, 'title' => $cat_data['cat_name'], 'extra' => ((strlen($this->sort)) ? "sort=$this->sort" : ''))), $articles_count, $config['kb_articles_per_page'], $this->start),
			'PAGE_NUMBER'		=> on_page($articles_count, $config['kb_articles_per_page'], $this->start),
			'TOTAL_ARTICLES' 	=> $articles_count,			
			'U_ADD_NEW_ARTICLE'	=> ($auth->acl_get('u_kb_add', $this->cat_id)) ? append_sid("{$phpbb_root_path}kb.$phpEx", "c=" . $this->cat_id . '&amp;i=add') : '',			
			'NEW_ARTICLE_IMG'	=> $user->img('button_article_new', 'KB_ADD_ARTICLE'),
			'S_NO_TOPIC'		=> false,
			'S_IN_MAIN'			=> true,
			'S_CAT_STYLE'		=> ($config['kb_layout_style']) ? true : false, // IF 1 then set to true bc. style = special
			'S_COL_WIDTH'		=> ($config['kb_layout_style']) ? (100 / $config['kb_cats_per_row']) : 0,
			'S_SORT_OPTIONS'	=> $sort_options,
			'S_SORT_DIRECTION'	=> $sort_direction,
			'S_ARTICLE_ACTION'	=> kb_append_sid('cat', array('id' => $this->cat_id, 'title' => $cat_data['cat_name'])),
			'UNAPPROVED_IMG'	=> $user->img('icon_topic_unapproved', 'ARTICLE_UNAPPROVED'),
		));
		
		// Build Navigation Links
		generate_kb_nav('', $cat_data);
		
		// Output the page
		$this->page_header($user->lang['VIEW_CAT'] . ' - ' . $cat_data['cat_name']);
		
		$template->set_filenames(array(
			'body' => 'kb/view_cat_list.html')
		);
		
		page_footer();
		
	}
	
	/**
	* Article generation page
	* This function generates a page which views an article.
	*/
	function generate_article_page($module = '')
	{
		global $template, $config, $user, $db, $cache; 
		global $user, $auth, $phpbb_root_path, $phpEx;
		
		$this->finclude('functions_display', 'f:display_forums');
		$user->add_lang('viewtopic');
		$view = request_var('view', '');
		
		if(!$this->article_id)
		{
			trigger_error('KB_NO_ARTICLE');
		}
		
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'a.*, c.cat_id, c.cat_name, c.parent_id, c.left_id, c.right_id, u.*, AVG(r.rating) as article_rating, t.subscribed, t.bookmarked',
			'FROM'		=> array(
				KB_TABLE => 'a'),
			'LEFT_JOIN'	=> array(
				array(
					'FROM' => array(KB_CATS_TABLE => 'c'),
					'ON' => 'a.cat_id = c.cat_id',
				),
				array(
					'FROM' => array(USERS_TABLE => 'u'),
					'ON' => 'a.article_user_id = u.user_id',
				),
				array(
					'FROM' => array(KB_RATE_TABLE => 'r'),
					'ON' => 'a.article_id = r.article_id',
				),
				array(
					'FROM' => array(KB_TRACK_TABLE => 't'),
					'ON' => 'a.article_id = t.article_id AND u.user_id = t.user_id',
				),
			),
			'WHERE'		=> 'a.article_id = ' . $this->article_id,
			'GROUP_BY'	=> 'a.article_id',
		));
		
		$result = $db->sql_query($sql);
		$article_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if(!$article_data)
		{
			trigger_error('KB_NO_ARTICLE');
		}
		
		switch($module)
		{
			case 'ucp':
				$base_url = "{$phpbb_root_path}ucp.$phpEx";
				$base_arg = 'i=kb&amp;mode=articles&amp;ma=comment'; // For comment posting
			break;
			
			case 'mcp':
				$base_url = "{$phpbb_root_path}mcp.$phpEx";
				$base_arg = 'i=kb&amp;mode=queue&amp;hmode=comment'; // Comment posting
			break;
			
			case '':
			default:
				$base_url = "{$phpbb_root_path}kb.$phpEx";
			break;
		}
		
		handle_related_articles($this->article_id, $article_data['article_title'], $article_data['article_title_clean'], $config['kb_related_articles']);
		
		if(($this->cat_id == 0) || ($this->cat_id != $article_data['cat_id']))
		{
			// No category id has been set via GET vars, set it yourself
			$this->cat_id = $article_data['cat_id'];
		}
		
		// Start auth check
		if (!$auth->acl_get('u_kb_read', $this->cat_id) || ($article_data['article_status'] != STATUS_APPROVED && (!$auth->acl_get('m_kb_view') && $user->data['user_id'] != $article_data['article_user_id'])))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('KB_USER_CANNOT_READ');
			}
			
			$red_url = ($this->start > 0) ? "&amp;start=$this->start" : '';
			$red_url = ($this->hilit_words == '') ? $red_url : $red_url . "&amp;hilit=$this->hilit_words";
			login_box(append_sid("{$phpbb_root_path}kb.$phpEx", "c=$this->cat_id&amp;a=$this->article_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_READ']);
		}
		
		// Was a highlight request part of the URI?
		$highlight_match = $highlight = '';
		if ($this->hilit_words)
		{
			foreach (explode('|', trim($this->hilit_words)) as $word)
			{
				if (trim($word))
				{
					$word = str_replace('\*', '\w+?', preg_quote($word, '#'));
					$word = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $word);
					$highlight_match .= (($highlight_match != '') ? '|' : '') . $word;
				}
			}
		
			$highlight = urlencode($this->hilit_words);
		}
		
		// Grab ranks
		$ranks = $cache->obtain_ranks();
		
		// Grab icons
		$icons = $cache->obtain_icons();
		$types = $cache->obtain_article_types();
		
		// Grab extensions
		$extensions = array();
		if ($article_data['article_attachment'])
		{
			$extensions = $cache->obtain_attach_extensions('kb');
		}
		
		// Load custom profile fields
		if ($config['load_cpf_viewtopic'])
		{
			$this->finclude('functions_profile_fields', 'c:custom_profile');
			$cp = new custom_profile();
		
			// Grab all profile fields from users in id cache for later use - similar to the poster cache
			$profile_fields_cache = $cp->generate_profile_fields_template('grab', array($article_data['article_user_id']));
		}
		
		// Set author online to false before this
		$author_online = false;
		
		// Generate online information for user
		if ($config['load_onlinetrack'] && sizeof(array($article_data['article_user_id'])))
		{
			$sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
				FROM ' . SESSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('session_user_id', array($article_data['article_user_id'])) . '
				GROUP BY session_user_id';
			$result = $db->sql_query($sql);
		
			$update_time = $config['load_online_time'] * 60;
			while ($row = $db->sql_fetchrow($result))
			{
				$author_online = (time() - $update_time < $row['online_time'] && (($row['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
			}
			$db->sql_freeresult($result);
		}
		
		// Pull attachment data
		$display_notice = false;
		$attachments = $update_count = array();
		if($article_data['article_attachment'] && $config['kb_allow_attachments'])
		{
			if ($auth->acl_get('u_kb_download', $this->cat_id))
			{
				$sql = 'SELECT *
					FROM ' . KB_ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('article_id', $this->article_id) . '
					ORDER BY filetime DESC, article_id ASC';
				$result = $db->sql_query($sql);
		
				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[] = $row;
				}
				$db->sql_freeresult($result);
		
				// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
				if (!sizeof($attachments))
				{
					$sql = 'UPDATE ' . KB_TABLE . "
						SET article_attachment = 0
						WHERE article_id = " . (int) $this->article_id;
					$db->sql_query($sql);
				}
				else if (!$article_data['article_attachment'])
				{
					// Topic has approved attachments but its flag is wrong
					$sql = 'UPDATE ' . KB_TABLE . '
						SET article_attachment = 1
						WHERE article_id = ' . $this->article_id;
					$db->sql_query($sql);
		
					$article_data['article_attachment'] = 1;
				}
			}
			else
			{
				$display_notice = true;
			}
		}
		
		$bbcode_bitfield = base64_decode($article_data['bbcode_bitfield']);
		// Is a signature attached? Are we going to display it?
		if ($article_data['enable_sig'] && $config['kb_allow_sig'] && $user->optionget('viewsigs'))
		{
			$bbcode_bitfield = $bbcode_bitfield | base64_decode($article_data['user_sig_bbcode_bitfield']);
		}
		
		// Desc
		if ($article_data['article_desc'] != '')
		{
			$bbcode_bitfield = $bbcode_bitfield | base64_decode($article_data['article_desc_bitfield']);
		}
		
		// Instantiate BBCode if need be
		if ($bbcode_bitfield !== '')
		{
			$this->finclude('bbcode', 'c:bbcode');
			$bbcode = new bbcode(base64_encode($bbcode_bitfield));
		}
		
		$article_data['sig'] = '';
		if ($article_data['user_sig'] && $article_data['enable_sig'] && $config['kb_allow_sig'] && $user->optionget('viewsigs'))
		{
			$user_sig = censor_text($article_data['user_sig']);
	
			if ($article_data['user_sig_bbcode_bitfield'])
			{
				$bbcode->bbcode_second_pass($user_sig, $article_data['user_sig_bbcode_uid'], $article_data['user_sig_bbcode_bitfield']);
			}
	
			$user_sig = bbcode_nl2br($user_sig);
			$article_data['sig'] = smiley_text($user_sig);
		}
		
		if($article_data['article_desc'] != '')
		{
			$article_desc = censor_text($article_data['article_desc']);
	
			if ($article_data['article_desc_bitfield'])
			{
				$bbcode->bbcode_second_pass($article_desc, $article_data['article_desc_uid'], $article_data['article_desc_bitfield']);
			}
	
			$article_desc = bbcode_nl2br($article_desc);
			$article_data['article_desc'] = smiley_text($article_desc);
		}
		
		// Parse the message and subject
		$message = censor_text($article_data['article_text']);
		
		// Second parse bbcode here
		if ($article_data['bbcode_bitfield'])
		{
			$bbcode->bbcode_second_pass($message, $article_data['bbcode_uid'], $article_data['bbcode_bitfield']);
		}
	
		$message = bbcode_nl2br($message);
		$message = smiley_text($message);
	
		if (!empty($attachments))
		{
			kb_parse_attachments($message, $attachments, $update_count, false);
		}
	
		// Replace naughty words such as farty pants
		$article_data['article_title'] = censor_text($article_data['article_title']);
	
		// Highlight active words (primarily for search)
		if ($highlight_match)
		{
			$message = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $message);
			$article_data['article_title'] = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $article_data['article_title']);
		}
		
		if ($config['load_cpf_viewtopic'])
		{
			$cp_row = (isset($profile_fields_cache[$article_data['article_user_id']])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$article_data['article_user_id']]) : array();
		}
		
		$article_data = array_merge($article_data, array(
			'rank_title'		=> '',
			'rank_image'		=> '',
			'rank_image_src'	=> '',
		));
		get_user_rank($article_data['user_rank'], $article_data['user_posts'], $article_data['rank_title'], $article_data['rank_image'], $article_data['rank_image_src']);
		
		if (!empty($article_data['user_allow_viewemail']) || $auth->acl_get('a_email'))
		{
			$article_data['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=" . $article_data['article_user_id']) : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $article_data['user_email']);
		}
		else
		{
			$article_data['email'] = '';
		}

		if (!empty($row['user_icq']))
		{
			$article_data['icq'] = 'http://www.icq.com/people/webmsg.php?to=' . $article_data['user_icq'];
			$article_data['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $article_data['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
		}
		else
		{
			$article_data['icq_status_img'] = '';
			$article_data['icq'] = '';
		}
		
		$article_data['age'] = 0;
		$now = getdate(time() + $user->timezone + $user->dst - date('Z'));
		if ($config['allow_birthdays'] && !empty($article_data['user_birthday']))
		{
			list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $article_data['user_birthday']));

			if ($bday_year)
			{
				$diff = $now['mon'] - $bday_month;
				if ($diff == 0)
				{
					$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
				}
				else
				{
					$diff = ($diff < 0) ? 1 : 0;
				}

				$article_data['age'] = (int) ($now['year'] - $bday_year - $diff);
			}
		}
		
		// Process article tags
		$article_tags = '';
		if($article_data['article_tags'] != '')
		{
			$tags = explode(',', $article_data['article_tags']);
			$parsed_tags_container = array();
			foreach($tags as $tag)
			{
				// Strip starting spaces
				$str = $tag;
				if(strpos($str, ' ') === 0)
				{
					$tag = utf8_substr($tag, 1);
				}

				$lc_tag = utf8_strtolower($tag);
				$parsed_tags_container[] = '<a href="' . kb_append_sid('tag', array('id' => $lc_tag, 'title' => $tag)) . '">' . $tag . '</a>';
			}
			
			$article_tags = implode(', ', $parsed_tags_container);
		}
		
		// Any way to exclude this query?
		if ($user->data['user_id'] != $article_data['article_user_id'])
		{
			$sql = "SELECT rating
					FROM " . KB_RATE_TABLE . '
					WHERE article_id = ' . $this->article_id . '
					AND user_id = ' . (int) $user->data['user_id'];
			$result = $db->sql_query($sql);	
			$has_rated = ($db->sql_affectedrows()) ? (int) $db->sql_fetchfield('rating') : 0;
			$can_rate = ($user->data['is_registered'] && $auth->acl_get('u_kb_rate', $this->cat_id)) ? true : false;
			$db->sql_freeresult($result);
		}
		else
		{
			// Can't rate own articles
			$has_rated = 0;
			$can_rate = false;
		}
		$article_data['article_rating'] = round($article_data['article_rating'], 1);
		
		// Get article types
		$article_type = gen_article_type($article_data['article_type'], $article_data['article_title'], $types, $icons);
		$article_desc_re = ($config['kb_show_desc_article'] && !$config['kb_disable_desc']) ? generate_text_for_display($article_data['article_desc'], $article_data['article_desc_uid'], $article_data['article_desc_bitfield'], $article_data['article_desc_options']) : '';
		
		// Show revision his if wanted & authorized
		if($auth->acl_get('u_kb_viewhistory', $this->cat_id) && request_var('view_rev', false))
		{
			$edit_lang_ary = array(
				EDIT_TYPE_TITLE		=> 'EDIT_TYPE_TITLE',
				EDIT_TYPE_DESC		=> 'EDIT_TYPE_DESC',
				EDIT_TYPE_CONTENT	=> 'EDIT_TYPE_CONTENT',
				EDIT_TYPE_TAGS		=> 'EDIT_TYPE_TAGS',
				EDIT_TYPE_TYPE		=> 'EDIT_TYPE_TYPE',
				EDIT_TYPE_CAT		=> 'EDIT_TYPE_CAT',
				EDIT_TYPE_STATUS	=> 'EDIT_TYPE_STATUS',
			);
			
			// Cycle through changes and generate reports
			$edit_type = unserialize($article_data['article_edit_type']);
			foreach($edit_type as $edit)
			{
				$template->assign_block_vars('changerow', array(
					'CHANGE'			=> $user->lang[$edit_lang_ary[$edit]],
				));
			}
		}
		
		// Generate contributions list
		if($auth->acl_get('u_kb_viewhistory', $this->cat_id) && $config['kb_show_contrib'])
		{
			$sql = 'SELECT edit_user_id, edit_user_color, edit_user_name
					FROM ' . KB_EDITS_TABLE . '
					WHERE article_id = ' . $this->article_id . ' 
					AND edit_contribution = 1
					GROUP BY edit_user_id'; // Group so we only get each contributor once
			$result = $db->sql_query($sql);
			
			while($row = $db->sql_fetchrow($result))
			{
				$template->assign_block_vars('contribrow', array(
					'CONTRIBUTOR'		=> get_username_string('full', $row['edit_user_id'], $row['edit_user_name'], $row['edit_user_color']),
				));
			}
			$db->sql_freeresult($result);
		}
		
		// Send vars to template
		$template->assign_vars(array(
			'ARTICLE_COPYRIGHT'		=> ($config['kb_copyright'] != '') ? '&copy; ' . $config['kb_copyright'] : '',
			'ARTICLE_DESC_CLEAN'	=> ($config['kb_show_desc_article'] && !$config['kb_disable_desc']) ? strip_tags($article_desc_re) : '',
			'ARTICLE_DESC'			=> ($config['kb_show_desc_article'] && !$config['kb_disable_desc']) ? $article_desc_re : '',
			'ARTICLE_ID' 			=> $this->article_id,
			'ARTICLE_TITLE' 		=> $article_type['article_title'],
			'ARTICLE_TITLE_CLEAN' 	=> strip_tags($article_type['article_title']),
			'ARTICLE_POSTER'		=> $article_data['article_user_id'],
			//'ARTICLE_CHECKSUM'		=>	$article_data['article_checksum'],
			
			'L_EDIT_ARTICLE'		=> $user->lang['KB_EDIT_ARTICLE'],
			'L_DELETE_ARTICLE'		=> $user->lang['KB_DELETE_ARTICLE'],
			'L_HISTORY'				=> $user->lang['ARTICLE_HISTORY'],
			'L_CONTENT' 			=> $user->lang['ARTICLE_CONTENT'],
			'U_PERM_LINK'			=> generate_board_url() . '/' . kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_type['article_title']), true),			
			'U_LOCAL_PERM_LINK'		=> '[kb]'. $this->article_id . ', ' . $article_type['article_title'].'[/kb]',			
			
			'U_DLOAD_WORD'			=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=export&amp;a=' . $this->article_id),
			'POSTER_ARTICLES' 		=> $article_data['user_articles'],
			'U_POSTER_ARTICLES'		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=search&amp;author_id=' . $article_data['article_user_id']),
			'ARTICLE_AUTHOR_FULL'	=> get_username_string('full', $article_data['article_user_id'], $article_data['article_user_name'], $article_data['article_user_color']),
			'ARTICLE_TAGS' 			=> $article_tags,
			'ARTICLE_VIEWS'			=> ($article_data['article_views'] == 1) ? sprintf($user->lang['ARTICLES_VIEW'], $article_data['article_views']) : sprintf($user->lang['ARTICLES_VIEWS'], $article_data['article_views']),
			//'PAGINATION' 	=> $pagination,
			//'PAGE_NUMBER' 	=> on_page($total_posts, $config['kb_comments_per_page'], $start),
			
			'QUOTE_IMG' 		=> $user->img('icon_post_quote', 'REPLY_WITH_QUOTE'),
			'EDIT_IMG' 			=> $user->img('icon_post_edit', 'KB_EDIT_ARTICLE'),
			'DELETE_IMG' 		=> $user->img('icon_post_delete', 'KB_DELETE_ARTICLE'),
			'HISTORY_IMG' 		=> $user->img('icon_post_info', 'ARTICLE_HISTORY'),
			'PROFILE_IMG'		=> $user->img('icon_user_profile', 'READ_PROFILE'),
			'SEARCH_IMG' 		=> $user->img('icon_user_search', 'SEARCH_USER_POSTS'),
			'PM_IMG' 			=> $user->img('icon_contact_pm', 'SEND_PRIVATE_MESSAGE'),
			'EMAIL_IMG' 		=> $user->img('icon_contact_email', 'SEND_EMAIL'),
			'WWW_IMG' 			=> $user->img('icon_contact_www', 'VISIT_WEBSITE'),
			'ICQ_IMG' 			=> $user->img('icon_contact_icq', 'ICQ'),
			'AIM_IMG' 			=> $user->img('icon_contact_aim', 'AIM'),
			'MSN_IMG' 			=> $user->img('icon_contact_msnm', 'MSNM'),
			'YIM_IMG' 			=> $user->img('icon_contact_yahoo', 'YIM'),
			'JABBER_IMG'		=> $user->img('icon_contact_jabber', 'JABBER') ,
			'STATUS_IMG'		=> $user->img('icon_post_report', 'CHANGE_STATUS'),
			'UNAPPROVED_IMG'	=> $user->img('icon_topic_unapproved', 'ARTICLE_UNAPPROVED'),
			
/* FOR LATER USE
			'U_PRINT_TOPIC'			=> ($auth->acl_get('f_print', $forum_id)) ? $viewarticle_url . '&amp;view=print' : '',
			'U_EMAIL_TOPIC'			=> ($auth->acl_get('f_email', $forum_id) && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;t=$article_id") : '',
*/
			'U_WATCH_ARTICLE' 		=> ($user->data['is_registered'] && $config['kb_allow_subscribe']) ? (($article_data['subscribed'] > 0) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=kb&amp;mode=subscribed&amp;action=delete&amp;a[' . $this->article_id . ']=1') : append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=kb&amp;mode=subscribed&amp;action=add&amp;a=' . $this->article_id)) : '',
			'L_WATCH_ARTICLE' 		=> ($user->data['is_registered'] && $config['kb_allow_subscribe']) ? (($article_data['subscribed'] > 0) ? $user->lang['STOP_WATCHING_ARTICLE'] : $user->lang['START_WATCHING_ARTICLE']) : '',
			'S_WATCHING_ARTICLE'	=> ($user->data['is_registered'] && $config['kb_allow_subscribe'] && ($article_data['subscribed'] > 0)) ? true : false,
		
			'U_BOOKMARK_ARTICLE'	=> ($user->data['is_registered'] && $config['kb_allow_bookmarks']) ? (($article_data['bookmarked']) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=kb&amp;mode=bookmarks&amp;action=delete&amp;a[' . $this->article_id . ']=1') : append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=kb&amp;mode=bookmarks&amp;action=add&amp;a=' . $this->article_id)) : '',
			'L_BOOKMARK_ARTICLE'	=> ($user->data['is_registered'] && $config['kb_allow_bookmarks'] && $article_data['bookmarked']) ? $user->lang['BOOKMARK_ARTICLE_REMOVE'] : $user->lang['BOOKMARK_ARTICLE'],

			'POST_AUTHOR_FULL'	=> get_username_string('full', $article_data['article_user_id'], $article_data['username'], $article_data['user_colour'], $article_data['username']),
			'U_POST_AUTHOR'		=> get_username_string('profile', $article_data['article_user_id'], $article_data['user_colour'], $article_data['username']),
	
			'RANK_TITLE'		=> $article_data['rank_title'],
			'RANK_IMG'			=> $article_data['rank_image'],
			'RANK_IMG_SRC'		=> $article_data['rank_image_src'],
			'POSTER_JOINED'		=> $user->format_date($article_data['user_regdate']),
			'POSTER_FROM'		=> (!empty($article_data['user_from'])) ? $article_data['user_from'] : '',
			'POSTER_AVATAR'		=> ($user->optionget('viewavatars')) ? get_user_avatar($article_data['user_avatar'], $article_data['user_avatar_type'], $article_data['user_avatar_width'], $article_data['user_avatar_height']) : '',
			'POSTER_WARNINGS'	=> (isset($article_data['user_warnings'])) ? $article_data['user_warnings'] : 0,
			'POSTER_AGE'		=> $article_data['age'],
	
			'ARTICLE_TIME'		=> $user->format_date($article_data['article_time']),
			'ARTICLE_MESSAGE'	=> $message,
			'SIGNATURE'			=> ($article_data['enable_sig']) ? $article_data['sig'] : '',
			'EDITED_MESSAGE'	=> gen_kb_edit_string($article_data['article_id'], $article_data['article_last_edit_id'], $article_data['article_time'], $article_data['article_last_edit_time'], $article_data['article_edit_reason'], $article_data['article_edit_reason_global']),
	
			'MINI_POST_IMG'			=> $user->img('icon_post_target', 'POST'),
			'POST_ICON_IMG'			=> $article_type['type_image']['img'],
			'POST_ICON_IMG_WIDTH'	=> $article_type['type_image']['width'],
			'POST_ICON_IMG_HEIGHT'	=> $article_type['type_image']['height'],
			'ICQ_STATUS_IMG'		=> $article_data['icq_status_img'],
			'ONLINE_IMG'			=> ($article_data['article_user_id'] == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($author_online) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
			'S_ONLINE'				=> ($article_data['article_user_id'] == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($author_online) ? true : false),
	
			'U_EDIT'			=> (!$user->data['is_registered']) ? '' : (($user->data['user_id'] == $article_data['article_user_id'] && $auth->acl_get('u_kb_edit', $this->cat_id)) || $auth->acl_get('m_kb_edit') || ($article_data['article_open'] && $auth->acl_get('u_kb_add_co', $this->cat_id))) ? append_sid("{$phpbb_root_path}kb.$phpEx", "i=edit&amp;c=$this->cat_id&amp;a=$this->article_id") : '',
			'U_STATUS'			=> ($auth->acl_get('m_kb_status')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=kb&amp;&amp;hmode=status&amp;a=" . $this->article_id) : '',
			'U_DELETE'			=> (!$user->data['is_registered']) ? '' : (($user->data['user_id'] == $article_data['article_user_id'] && $auth->acl_get('u_kb_edit', $this->cat_id)) || $auth->acl_get('m_kb_delete')) ? append_sid("{$phpbb_root_path}kb.$phpEx", "i=delete&amp;c=$this->cat_id&amp;a=$this->article_id") : '',
			'U_HISTORY'			=> (!$auth->acl_get('u_kb_viewhistory', $this->cat_id)) ? '' : append_sid("{$phpbb_root_path}kb.$phpEx", "i=history&amp;a=" . $this->article_id),
	
			'S_RELATED_ARTICLES' => ($config['kb_related_articles']) ? true : false,
			'S_KB_EMAIL'	=> ($config['kb_email_article'] && $config['email_enable'] && $auth->acl_get('u_sendemail')) ? true : false,
			'U_KB_EMAIL'	=> append_sid("{$phpbb_root_path}kb.$phpEx", "i=email&amp;starter=" . $article_data['article_user_name'] . '&amp;sender=' . $user->data['username'] . '&amp;a=' . $this->article_id . '&amp;a_t= ' . $article_data['article_title']),
			'U_RSS_AUTHOR'	=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=feed&amp;feed_type=user&amp;feed_user_id=' . $article_data['article_user_id'] . '&amp;feed_username=' . $article_data['article_user_name']),
			'U_PROFILE'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=" . $article_data['article_user_id']),
			'U_PM'			=> ($article_data['article_user_id'] != ANONYMOUS && $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($article_data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose') : '',
			'U_EMAIL'		=> $article_data['email'],
			'U_WWW'			=> $article_data['user_website'],
			'U_ICQ'			=> $article_data['icq'],
			'U_AIM'			=> ($article_data['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=" . $article_data['article_user_id']) : '',
			'U_MSN'			=> ($article_data['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=" . $article_data['article_user_id']) : '',
			'U_YIM'			=> ($article_data['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($article_data['user_yim']) . '&amp;.src=pg' : '',
			'U_JABBER'		=> ($article_data['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=" . $article_data['article_user_id']) : '',
	
			'S_HAS_ATTACHMENTS'		=> (!empty($attachments)) ? true : false,
			'S_ARTICLE_UNAPPROVED'	=> ($article_data['article_status'] == STATUS_APPROVED) ? false : true,
			'S_DISPLAY_NOTICE'		=> $display_notice && $article_data['article_attachment'],
			'S_CUSTOM_FIELDS'		=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
			'S_IS_MCP'				=> ($module == 'mcp') ? true : false,
			'S_IS_UCP'				=> ($module == 'ucp') ? true : false,
			
			// Rating vars
			// First the ones if ajax rating is disabled
			/*
			'U_RATE_1' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(1)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=1"),
			'U_RATE_2' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(2)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=2"),
			'U_RATE_3' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(3)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=3"),
			'U_RATE_4' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(4)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=4"),
			'U_RATE_5' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(5)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=5"),
			'U_RATE_6' 				=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(6)' : append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&amp;a=$this->article_id&amp;rating=6"),
			
			'U_RATE' 				=> append_sid("kb.$phpEx", "i=rate&a=$this->article_id"), // No phpbb_root_path ! Important !
			'RATING' 				=> $article_data['article_rating'],
			'RATING_PX' 			=> $article_data['article_rating'] * 25, // Used for style purposes
			'S_HAS_RATED' 			=> ($auth->acl_get('u_kb_rate', $this->cat_id)) ? $has_rated : true,
			'L_CURRENT_RATING' 		=> sprintf(($article_data['article_votes'] == 1) ? $user->lang['CURRENT_RATING_S'] : $user->lang['CURRENT_RATING_P'], $article_data['article_rating'], $article_data['article_votes']),
			*/
			// New rating var (moved to functions powered by customization db code)
			'RATING_STARS'			=> get_rating_stars($this->article_id, $this->cat_id, $has_rated, $can_rate, $article_data['article_rating'], $article_data['article_votes']),
			'L_CURRENT_RATING' 		=> sprintf(($article_data['article_votes'] == 1) ? $user->lang['CURRENT_RATING_S'] : $user->lang['CURRENT_RATING_P'], (($article_data['article_votes'] == 0) ? 0 : $article_data['article_rating']), $article_data['article_votes']),
			
			'DISABLE_LEFT_MENU'		=> ($config['kb_disable_left_menu']) ? true : false,
			'DISABLE_RIGHT_MENU'	=> ($config['kb_disable_right_menu']) ? true : false,
			'S_EXTENDED_HEADER'		=> ($config['kb_ext_article_header']) ? true : false,
			'S_SOCIAL_BOOKMARKS'	=> ($config['kb_soc_bookmarks']) ? true : false,
			'S_EXPORT_OPTIONS'		=> ($config['kb_export_article']) ? true : false,
		));
		
		if (isset($cp_row['row']) && sizeof($cp_row['row']))
		{
			$template->assign_vars($cp_row['row']);
		}
	
		// Dump vars into template
		if (!empty($cp_row['blockrow']))
		{
			foreach ($cp_row['blockrow'] as $field_data)
			{
				$template->assign_block_vars('custom_fields', $field_data);
			}
		}
		
		// Display not already displayed Attachments for this post, we already parsed them. ;)
		if (!empty($attachments))
		{
			foreach ($attachments as $attachment)
			{
				$template->assign_block_vars('attachment', array(
					'DISPLAY_ATTACHMENT'	=> $attachment)
				);
			}
		}
		
		// Update topic view and if necessary attachment view counters ... but only for humans and if this is the first 'page view'
		if (isset($user->data['session_page']) && !$user->data['is_bot'] && strpos($user->data['session_page'], '&a=' . $this->article_id) === false && $user->data['user_id'] != $article_data['article_user_id'])
		{
			$sql = 'UPDATE ' . KB_TABLE . '
				SET article_views = article_views + 1
				WHERE article_id = ' . $this->article_id;
			$db->sql_query($sql);
		
			// Update the attachment download counts
			if (sizeof($update_count))
			{
				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . '
					SET download_count = download_count + 1
					WHERE ' . $db->sql_in_set('attach_id', array_unique($update_count));
				$db->sql_query($sql);
			}
		}
		
		// Unset subscription for this user
		$sql = 'UPDATE ' . KB_TRACK_TABLE . '
				SET subscribed = 1
				WHERE subscribed > 1
				AND user_id = ' . $user->data['user_id'] . '
				AND article_id = ' . $this->article_id;
		$db->sql_query($sql);
		
		// Sorting also applies for comments
		// Comments TO DO:
		// - read / unread
		// Default for comments sorting is ASC and not DESC as for everything else, overwrite, $this->dir.
		$this->dir = request_var('dir', 'ASC');
		switch($this->sort)
		{
			// Ability to sort by post time, edit time, name, views, author	
			case 'etime':
				$order_by = 'c.comment_edit_time ' . $this->dir;
			break;
				
			case 'name':
				$order_by = 'c.comment_title ' . $this->dir;
			break;
				
			case 'author':
				$order_by = 'c.comment_user_name ' . $this->dir;
			break;
			
			case 'time':
			case '':
			default:
				$order_by = 'c.comment_time ' . $this->dir;
			break;
		}
		
		// Get comments data, save them in attach_list, rowset and build user cache
		$attach_list = $rowset = $user_cache = $id_cache = $comment_ids = $attachments = $update_count = array();
		$bbcode_bitfield = '';
		$has_attachments = $display_notice = false;
		$comments_count = 0;
		
		// Get all comments, place them in id placeholder, the sort order is used here.
		$sql = 'SELECT c.comment_id 
				FROM ' . KB_COMMENTS_TABLE . ' c
				WHERE c.article_id = ' . $this->article_id . '
				AND c.comment_type = ' . (($module != '') ? COMMENT_MOD : COMMENT_GLOBAL) . '
				ORDER BY ' . $order_by;
		$result = $db->sql_query($sql);
		while($row = $db->sql_fetchrow($result))
		{
			$comment_ids[] = $row['comment_id'];
			$comments_count++;
		}
		$db->sql_freeresult($result);
		
		// Make sure $start is set to the last page if it exceeds the amount
		if ($this->start < 0 || $this->start > $comments_count)
		{
			$this->start = ($this->start < 0) ? 0 : floor(($comments_count - 1) / $config['kb_comments_per_page']) * $config['kb_comments_per_page'];
		}
		
		// Build the actual query
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'u.*, z.friend, z.foe, c.*',
		
			'FROM'		=> array(
				USERS_TABLE		=> 'u',
				KB_COMMENTS_TABLE => 'c',
			),
		
			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(ZEBRA_TABLE => 'z'),
					'ON'	=> 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = c.comment_user_id'
				)
			),
		
			'WHERE'		=> 'c.article_id = ' . $this->article_id . '
						AND c.comment_user_id = u.user_id
						AND c.comment_type = ' . (($module != '') ? COMMENT_MOD : COMMENT_GLOBAL),
									
			'ORDER_BY'  => $order_by,
		));
		$result = $db->sql_query_limit($sql, $config['kb_comments_per_page'], $this->start);
		
		while($row = $db->sql_fetchrow($result))
		{
			$poster_id = $row['user_id'];
			
			// Does post have an attachment? If so, add it to the list
			if ($row['comment_attachment'] && $config['kb_allow_attachments'])
			{
				$attach_list[] = $row['comment_id'];
				$has_attachments = true;
			}
			
			$rowset[$row['comment_id']] = array(
				'hide_post'				=> ($row['foe'] && ($view != 'show')) ? true : false,
												
				'comment_id'			=> $row['comment_id'],
				'comment_time'			=> $row['comment_time'],
				'user_id'				=> $row['user_id'],
				'username'				=> $row['username'],
				'comment_user_name'		=> $row['comment_user_name'],
				'user_colour'			=> $row['user_colour'],
				'article_id'			=> $row['article_id'],
				'comment_title'			=> $row['comment_title'],
				'comment_edit_id'		=> $row['comment_edit_id'],
				'comment_edit_time'		=> $row['comment_edit_time'],
				'comment_edit_color'	=> $row['comment_edit_color'],
				'comment_edit_name'		=> $row['comment_edit_name'],
		
				'comment_attachment'	=> $row['comment_attachment'],
				'comment_text'			=> $row['comment_text'],
				'bbcode_uid'			=> $row['bbcode_uid'],
				'bbcode_bitfield'		=> $row['bbcode_bitfield'],
				'enable_smilies'		=> $row['enable_smilies'],
				'enable_sig'			=> $row['enable_sig'],
				
				'friend'				=> $row['friend'],
				'foe'					=> $row['foe'],
			);
		
			// Define the global bbcode bitfield, will be used to load bbcodes
			$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);
		
			// Is a signature attached? Are we going to display it?
			if ($row['enable_sig'] && $config['kb_allow_sig'] && $user->optionget('viewsigs'))
			{
				$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
			}
		
			// Cache various user specific data ... so we don't have to recompute
			// this each time the same user appears on this page
			if (!isset($user_cache[$poster_id]))
			{
				$user_sig = '';
	
				// We add the signature to every posters entry because enable_sig is post dependant
				if ($row['user_sig'] && $config['kb_allow_sig'] && $user->optionget('viewsigs'))
				{
					$user_sig = $row['user_sig'];
				}
	
				$id_cache[] = $poster_id;
	
				$user_cache[$poster_id] = array(	
					'joined'				=> $user->format_date($row['user_regdate']),
					'articles'				=> $row['user_articles'],
					'warnings'				=> (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,
					'from'					=> (!empty($row['user_from'])) ? $row['user_from'] : '',
	
					'sig'					=> $user_sig,
					'sig_bbcode_uid'		=> (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
					'sig_bbcode_bitfield'	=> (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',
	
					'viewonline'			=> $row['user_allow_viewonline'],
					'allow_pm'				=> $row['user_allow_pm'],
	
					'avatar'				=> ($user->optionget('viewavatars')) ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']) : '',
					'age'					=> '',
	
					'rank_title'			=> '',
					'rank_image'			=> '',
					'rank_image_src'		=> '',
	
					'username'				=> $row['username'],
					'user_colour'			=> $row['user_colour'],
	
					'online'				=> false,
					'profile'				=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=$poster_id"),
					'www'					=> $row['user_website'],
					'aim'					=> ($row['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=$poster_id") : '',
					'msn'					=> ($row['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=$poster_id") : '',
					'yim'					=> ($row['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($row['user_yim']) . '&amp;.src=pg' : '',
					'jabber'				=> ($row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
					'search'				=> ($auth->acl_get('u_kb_search', $this->cat_id)) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',
				);
	
				get_user_rank($row['user_rank'], $row['user_posts'], $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
	
				if (!empty($row['user_allow_viewemail']) || $auth->acl_get('a_email'))
				{
					$user_cache[$poster_id]['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=$poster_id") : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $row['user_email']);
				}
				else
				{
					$user_cache[$poster_id]['email'] = '';
				}
	
				if (!empty($row['user_icq']))
				{
					$user_cache[$poster_id]['icq'] = 'http://www.icq.com/people/webmsg.php?to=' . $row['user_icq'];
					$user_cache[$poster_id]['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $row['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
				}
				else
				{
					$user_cache[$poster_id]['icq_status_img'] = '';
					$user_cache[$poster_id]['icq'] = '';
				}
	
				if ($config['allow_birthdays'] && !empty($row['user_birthday']))
				{
					list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $row['user_birthday']));
	
					if ($bday_year)
					{
						$diff = $now['mon'] - $bday_month;
						if ($diff == 0)
						{
							$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
						}
						else
						{
							$diff = ($diff < 0) ? 1 : 0;
						}
	
						$user_cache[$poster_id]['age'] = (int) ($now['year'] - $bday_year - $diff);
					}
				}
			}
		}
		$db->sql_freeresult($result);
		
		// Generate online information for user
		if ($config['load_onlinetrack'] && sizeof($id_cache))
		{
			$sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
				FROM ' . SESSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('session_user_id', $id_cache) . '
				GROUP BY session_user_id';
			$result = $db->sql_query($sql);
		
			$update_time = $config['load_online_time'] * 60;
			while ($row = $db->sql_fetchrow($result))
			{
				$user_cache[$row['session_user_id']]['online'] = (time() - $update_time < $row['online_time'] && (($row['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
			}
			$db->sql_freeresult($result);
		}
		unset($id_cache);
		
		// Pull attachment data
		if (sizeof($attach_list))
		{
			if ($auth->acl_get('u_kb_download', $this->cat_id))
			{
				$sql = 'SELECT *
					FROM ' . KB_ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('comment_id', $attach_list) . '
					ORDER BY filetime DESC, comment_id ASC';
				$result = $db->sql_query($sql);
		
				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[$row['comment_id']][] = $row;
				}
				$db->sql_freeresult($result);
		
				// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
				if (!sizeof($attachments))
				{
					$sql = 'UPDATE ' . KB_COMMENTS_TABLE . '
						SET comment_attachment = 0
						WHERE ' . $db->sql_in_set('comment_id', $attach_list);
					$db->sql_query($sql);
				}
			}
			else
			{
				$display_notice = true;
			}
		}
		
		unset($bbcode);
		// Instantiate BBCode if need be
		if ($bbcode_bitfield !== '')
		{
			$this->finclude('bbcode', 'c:bbcode');
			$bbcode = new bbcode(base64_encode($bbcode_bitfield));
		}
		
		// Output the posts
		$i_total = sizeof($rowset) - 1;
		for ($i = 0, $end = sizeof($comment_ids); $i < $end; ++$i)
		{
			// A non-existing rowset only happens if there was no user present for the entered poster_id
			// This could be a broken comments table.
			if (!isset($rowset[$comment_ids[$i]]))
			{
				continue;
			}
		
			$row =& $rowset[$comment_ids[$i]];
			$poster_id = $row['user_id'];
			$comment_id = $row['comment_id'];
		
			// End signature parsing, only if needed
			if ($user_cache[$poster_id]['sig'] && $row['enable_sig'] && empty($user_cache[$poster_id]['sig_parsed']))
			{
				$user_cache[$poster_id]['sig'] = censor_text($user_cache[$poster_id]['sig']);
		
				if ($user_cache[$poster_id]['sig_bbcode_bitfield'])
				{
					$bbcode->bbcode_second_pass($user_cache[$poster_id]['sig'], $user_cache[$poster_id]['sig_bbcode_uid'], $user_cache[$poster_id]['sig_bbcode_bitfield']);
				}
		
				$user_cache[$poster_id]['sig'] = bbcode_nl2br($user_cache[$poster_id]['sig']);
				$user_cache[$poster_id]['sig'] = smiley_text($user_cache[$poster_id]['sig']);
				$user_cache[$poster_id]['sig_parsed'] = true;
			}
		
			// Parse the message and subject
			$message = censor_text($row['comment_text']);
		
			// Second parse bbcode here
			if ($row['bbcode_bitfield'])
			{
				$bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
			}
		
			$message = bbcode_nl2br($message);
			$message = smiley_text($message);
		
			if (!empty($attachments[$row['comment_id']]))
			{
				kb_parse_attachments($message, $attachments[$row['comment_id']], $update_count, false);
			}
		
			// Replace naughty words such as farty pants
			$row['comment_title'] = censor_text($row['comment_title']);
		
			// Highlight active words (primarily for search)
			if ($highlight_match)
			{
				$message = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $message);
				$row['comment_title'] = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">\1</span>', $row['comment_title']);
			}
		
			$cp_row = array();
			//
			if ($config['load_cpf_viewtopic'])
			{
				$cp_row = (isset($profile_fields_cache[$poster_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$poster_id]) : array();
			}
			
			$l_edited_by = '';
			if($row['comment_edit_id'] > 0)
			{
				$l_edited_by = sprintf($user->lang['KB_COMMENT_EDITED_BY'], get_username_string('full', $row['comment_edit_id'], $row['comment_edit_name']), $user->format_date($row['comment_edit_time'], false, true));
			}
			
			//
			$postrow = array(
				'POST_AUTHOR_FULL'		=> get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['comment_user_name']),
				'POST_AUTHOR_COLOUR'	=> get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['comment_user_name']),
				'POST_AUTHOR'			=> get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['comment_user_name']),
				'U_POST_AUTHOR'			=> get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['comment_user_name']),
		
				'RANK_TITLE'			=> $user_cache[$poster_id]['rank_title'],
				'RANK_IMG'				=> $user_cache[$poster_id]['rank_image'],
				'RANK_IMG_SRC'			=> $user_cache[$poster_id]['rank_image_src'],
				'POSTER_JOINED'			=> $user_cache[$poster_id]['joined'],
				'POSTER_ARTICLES'		=> $user_cache[$poster_id]['articles'],
				'POSTER_FROM'			=> $user_cache[$poster_id]['from'],
				'POSTER_AVATAR'			=> $user_cache[$poster_id]['avatar'],
				'POSTER_WARNINGS'		=> $user_cache[$poster_id]['warnings'],
				'POSTER_AGE'			=> $user_cache[$poster_id]['age'],
		
				'POST_DATE'				=> $user->format_date($row['comment_time']),
				'POST_SUBJECT'			=> $row['comment_title'],
				'COMMENT_MESSAGE'		=> $message,
				'SIGNATURE'				=> ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
				'EDITED_MESSAGE'		=> $l_edited_by,
		
				'MINI_POST_IMG'			=> $user->img('icon_post_target', 'POST'),
				'ICQ_STATUS_IMG'		=> $user_cache[$poster_id]['icq_status_img'],
				'ONLINE_IMG'			=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($user_cache[$poster_id]['online']) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
				'S_ONLINE'				=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),
		
				'U_EDIT'				=> (!$user->data['is_registered']) ? '' : ((($user->data['user_id'] == $poster_id && $auth->acl_get('u_kb_comment', $this->cat_id)) || $auth->acl_get('m_kb_edit') || ($article_data['article_open'] && $auth->acl_get('u_kb_add_co', $this->cat_id))) ? append_sid($base_url, (($module != '') ? $base_arg . "&amp;action=edit&amp;comment_id=$comment_id" : "i=comment&amp;action=edit&amp;comment_id=$comment_id")) : ''),
				'U_QUOTE'				=> ($auth->acl_get('u_kb_comment', $this->cat_id)) ? append_sid($base_url, (($module != '') ? $base_arg . "action=add&amp;a=$this->article_id&amp;quote=$comment_id" : "i=comment&amp;action=add&amp;a=$this->article_id&amp;quote=$comment_id")) : '',
				'U_DELETE'				=> (!$user->data['is_registered']) ? '' : (($user->data['user_id'] == $poster_id && $auth->acl_get('u_kb_comment', $this->cat_id)) || $auth->acl_get('m_kb_delete')) ? append_sid($base_url, (($module != '') ? $base_arg . "&amp;action=delete&amp;comment_id=$comment_id" : "i=comment&amp;action=delete&amp;comment_id=$comment_id")) : '',
		
				'U_PROFILE'				=> $user_cache[$poster_id]['profile'],
				'U_SEARCH'				=> $user_cache[$poster_id]['search'],
				'U_PM'					=> ($poster_id != ANONYMOUS && $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user_cache[$poster_id]['allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", '') : '',
				'U_EMAIL'				=> $user_cache[$poster_id]['email'],
				'U_WWW'					=> $user_cache[$poster_id]['www'],
				'U_ICQ'					=> $user_cache[$poster_id]['icq'],
				'U_AIM'					=> $user_cache[$poster_id]['aim'],
				'U_MSN'					=> $user_cache[$poster_id]['msn'],
				'U_YIM'					=> $user_cache[$poster_id]['yim'],
				'U_JABBER'				=> $user_cache[$poster_id]['jabber'],
	
				//'U_NEXT_POST_ID'	=> ($i < $i_total && isset($rowset[$post_list[$i + 1]])) ? $rowset[$post_list[$i + 1]]['post_id'] : '',
				//'U_PREV_POST_ID'	=> $prev_post_id,
		
				//'POST_ID'			=> $row['post_id'],
				'POSTER_ID'				=> $poster_id,
		
				'S_HAS_ATTACHMENTS'		=> (!empty($attachments[$row['comment_id']])) ? true : false,
				'S_DISPLAY_NOTICE'		=> $display_notice && $row['comment_attachment'],
				'S_FRIEND'				=> ($row['friend']) ? true : false,
				//'S_UNREAD_POST'		=> $post_unread,
				//'S_FIRST_UNREAD'		=> $s_first_unread,
				'S_CUSTOM_FIELDS'		=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
				'S_ARTICLE_POSTER'		=> ($article_data['article_user_id'] == $poster_id) ? true : false,
		
				'S_IGNORE_POST'			=> ($row['hide_post']) ? true : false,
				'L_IGNORE_POST'			=> ($row['hide_post']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour']), '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", 'a=$this->article_id&amp;view=show') . '">', '</a>') : '',
			);
		
			if (isset($cp_row['row']) && sizeof($cp_row['row']))
			{
				$postrow = array_merge($postrow, $cp_row['row']);
			}
		
			// Dump vars into template
			$template->assign_block_vars('postrow', $postrow);
		
			if (!empty($cp_row['blockrow']))
			{
				foreach ($cp_row['blockrow'] as $field_data)
				{
					$template->assign_block_vars('postrow.custom_fields', $field_data);
				}
			}
		
			// Display not already displayed Attachments for this post, we already parsed them. ;)
			if (!empty($attachments[$row['comment_id']]))
			{
				foreach ($attachments[$row['comment_id']] as $attachment)
				{
					$template->assign_block_vars('postrow.attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment)
					);
				}
			}
		
			$prev_post_id = $row['comment_id'];
		
			unset($rowset[$comment_ids[$i]]);
			unset($attachments[$row['comment_id']]);
		}
		unset($rowset, $user_cache);
		
		// Update topic view and if necessary attachment view counters ... but only for humans and if this is the first 'page view'
		if (isset($user->data['session_page']) && !$user->data['is_bot'] && strpos($user->data['session_page'], '&a=' . $this->article_id) === false)
		{
			// Update the attachment download counts
			if (sizeof($update_count))
			{
				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . '
					SET download_count = download_count + 1
					WHERE ' . $db->sql_in_set('attach_id', array_unique($update_count));
				$db->sql_query($sql);
			}
		}
		
		$sort_direction = make_direction_select($this->dir);
		$sort_options = make_sort_select('comments', $this->sort);
		
		// Generate Pagination
		$info_extra = (strlen($this->sort)) ? "&amp;sort=$this->sort" : '';
		$template->assign_vars(array(
			'PAGINATION'		=> kb_generate_pagination(kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_data['article_title'], 'extra' => $info_extra)), $comments_count, $config['kb_comments_per_page'], $this->start, 'start', false, ''),
			'PAGE_NUMBER'		=> on_page($comments_count, $config['kb_comments_per_page'], $this->start),
			'TOTAL_COMMENTS' 	=> $comments_count,
			'L_COMMENTS_LC' 	=> utf8_strtolower($user->lang['COMMENTS']),
			'U_ADD_COMMENT' 	=> append_sid($base_url, (($module != '') ? $base_arg . "&amp;action=add&amp;a=$this->article_id" : "i=comment&amp;action=add&amp;a=$this->article_id")),
			'NEW_COMMENT_IMG'	=> $user->img('button_comment_new', 'KB_ADD_COMMENT'),
			'S_IN_ARTICLE'		=> true,
			'S_SORT_OPTIONS'	=> $sort_options,
			'S_SORT_DIRECTION'	=> $sort_direction,
		));
		
		generate_menu($this->mode, $this->cat_id);
		
		if($module == '')
		{
			// Build Navigation Links
			generate_kb_nav($article_data['article_title'], $article_data);
			
			// Output the page
			$this->page_header($user->lang['VIEW_ARTICLE'] . ' - ' . $article_data['article_title']);
			
			$template->set_filenames(array(
				'body' => 'kb/view_article.html')
			);
			
			page_footer();
		}
		
		return $user->lang['VIEW_ARTICLE'] . ' - ' . $article_data['article_title'];
	}
	
	/**
	* Index generation
	* This function generates the index page overviewing the entire knowledge base
	*/
	function generate_index_page()
	{
		global $template, $config, $user, $db;
		global $phpbb_root_path, $auth, $phpEx;
		
		$this->finclude('functions_display', 'f:display_forums');
		$user->add_lang('viewtopic');
		
		kb_display_cats();
		
		// Build Navigation Links
		generate_kb_nav();
		
		$template->assign_vars(array(
			'S_IN_MAIN'		=> true,
			'S_NO_TOPIC'	=> true,
			'S_ON_INDEX'	=> true,
			'S_CAT_STYLE'	=> ($config['kb_layout_style']) ? true : false, // IF 1 then set to true bc. style = special
			'S_COL_WIDTH'	=> ($config['kb_layout_style']) ? (100 / $config['kb_cats_per_row']) : 0,
		));
		
		// Output the page
		$this->page_header($user->lang['KB_INDEX']);
		
		$template->set_filenames(array(
			'body' => 'kb/view_cat_list.html')
		);
		
		page_footer();
	}
	
	/**
	* Article posting
	* Handles all edits, deletes and adds to the article base
	*/
	function article_posting()
	{
		global $db, $template, $user, $auth;
		global $config, $phpbb_root_path, $phpEx;
		
		$this->finclude(array('functions_posting', 'functions_display', 'message_parser'), array('f:generate_smilies', 'f:display_forums', 'c:message_parser'));
		$user->add_lang(array('posting', 'mcp', 'viewtopic'));
		
		$submit		= (isset($_POST['post'])) ? true : false;
		$preview	= (isset($_POST['preview'])) ? true : false;
		$refresh	= (isset($_POST['add_file']) || isset($_POST['delete_file'])) ? true : false;
		$old_cat	= request_var('old_cat', 0);
		
		$error = $article_data = array();
		$current_time = time();	
		
		// We need to obtain basic category and post info
		switch($this->mode)
		{
			case 'add':
				$sql = "SELECT *
						FROM " . KB_CATS_TABLE . '
						WHERE cat_id = ' . $this->cat_id;
				break;
				
			case 'edit':
			case 'delete':
				if(!$this->article_id)
				{
					trigger_error('KB_NO_ARTICLE');
				}
				
				if($submit && !$preview && $old_cat != $this->cat_id)
				{
					// New category alter sql slightly
					$where_sql = ' AND c.cat_id = ' . $this->cat_id; 
				}
				else
				{
					$where_sql = ' AND c.cat_id = a.cat_id';
				}
				
				$sql = 'SELECT c.*, a.*, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_sig_bbcode_bitfield, u.user_colour
						FROM ' . KB_CATS_TABLE . ' c, ' . KB_TABLE . ' a, ' . USERS_TABLE . ' u
						WHERE a.article_id = ' . $this->article_id . '
							AND u.user_id = a.article_user_id' . $where_sql;
				break;
			
			case 'smilies':
				$sql = '';
				kb_generate_smilies('window');
			break;
				
			case 'popup':
				kb_upload_popup();
				return;
			break;
		
			default:
				$sql = '';
			break;
		}
		
		if (!$sql)
		{
			trigger_error('NO_POST_MODE');
		}
		
		$result = $db->sql_query($sql);
		$article_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if($this->cat_id == 0)
		{
			if($this->mode == 'edit' || $this->mode == 'delete')
			{
				$this->cat_id = $article_data['cat_id'];
			}
			else
			{
				trigger_error('KB_NO_CATEGORY');
			}
		}
		$db->sql_freeresult($result);
		
		if (!$article_data)
		{
			trigger_error('KB_NO_ARTICLE');
		}
		
		// Check permissions
		if ($user->data['is_bot'])
		{
			redirect(append_sid("{$phpbb_root_path}kb.$phpEx"));
		}
		
		// Is the user able to read within this forum?
		if (!$auth->acl_get('u_kb_view', $this->cat_id))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('KB_USER_CANNOT_VIEW');
			}
			
			$red_url = ($this->mode == 'edit' || $this->mode == 'delete') ? "&amp;a=$this->article_id" : '';
			login_box(append_sid("{$phpbb_root_path}kb.$phpEx", "i=$this->mode&amp;c=$this->cat_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_' . strtoupper($this->mode)]);
		}
		
		// Permission to do the action asked?
		$is_authed = false;
		switch ($this->mode)
		{
			case 'add':
				if ($user->data['is_registered'] && $auth->acl_get('u_kb_add', $this->cat_id))
				{
					$is_authed = true;
				}
			break;
		
			case 'edit':
				if ($user->data['is_registered'] && $auth->acl_gets(array('u_kb_edit', 'u_kb_add_co', 'm_kb_edit'), $this->cat_id))
				{
					$is_authed = true;
				}
			break;
		
			case 'delete':
				if ($user->data['is_registered'] && $auth->acl_gets(array('u_kb_delete', 'm_kb_delete'), $this->cat_id))
				{
					$is_authed = true;
				}
			break;
		}
		
		if (!$is_authed)
		{
			if ($user->data['is_registered'])
			{
				trigger_error('KB_USER_CANNOT_' . strtoupper($this->mode));
			}
		
			$red_url = ($this->mode == 'edit' || $this->mode == 'delete') ? "&amp;a=$this->article_id" : '';
			login_box(append_sid("{$phpbb_root_path}kb.$phpEx", "i=$this->mode&amp;c=$this->cat_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_' . strtoupper($this->mode)]);
		}
		
		// Handle delete mode...
		if ($this->mode == 'delete')
		{
			article_delete($this->article_id, $this->cat_id, $article_data);
			return;
		}
		
		if ($this->mode == 'edit' && !$auth->acl_get('m_kb_edit') && !$auth->acl_get('u_kb_add_co', $this->cat_id))
		{
			if ($user->data['user_id'] != $article_data['article_user_id'])
			{
				trigger_error('KB_USER_CANNOT_EDIT');
			}
		}
		
		$message_parser = new parse_message();
		$desc_parser = new parse_message();

		if (isset($article_data['article_text']))
		{
			$message_parser->message = &$article_data['article_text'];
			unset($article_data['article_text']);
		}
		
		if (isset($article_data['article_desc']))
		{
			$desc_parser->message = &$article_data['article_desc'];
			unset($article_data['article_desc']);
		}
		
		// Set some default variables
		$uninit = array('article_attachment' => 0, 'article_user_id' => $user->data['user_id'], 'enable_magic_url' => 0, 'article_status' => 0, 'article_title' => '', 'article_desc' => '', 'article_time' => 0, 'article_type' => 0, 'article_tags' => '', 'article_edit_reason' => '', 'article_edit_reason_global' => 0, 'request_id' => 0, 'article_contribution' => false, 'article_open' => false);
		foreach ($uninit as $var_name => $default_value)
		{
			if (!isset($article_data[$var_name]))
			{
				$article_data[$var_name] = $default_value;
			}
		}
		unset($uninit);
		
		// Get attachment info and check to see if valid info has been submitted
		$this->get_submitted_attachment_data($article_data['article_user_id']);
		if ($article_data['article_attachment'] && !$submit && !$refresh && !$preview && $this->mode == 'edit')
		{
			// Do not change to SELECT *
			$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
				FROM ' . KB_ATTACHMENTS_TABLE . "
				WHERE article_id = $this->article_id
					AND is_orphan = 0
				ORDER BY filetime DESC";
			$result = $db->sql_query($sql);
			$this->attachment_data = array_merge($this->attachment_data, $db->sql_fetchrowset($result));
			$db->sql_freeresult($result);
		}
		
		// Set some content variables
		$article_data['enable_urls'] = $article_data['enable_magic_url'];
		if ($this->mode != 'edit')
		{
			$article_data['enable_sig']		= ($config['kb_allow_sig'] && $user->optionget('attachsig')) ? true: false;
			$article_data['enable_smilies']	= ($config['kb_allow_smilies'] && $user->optionget('smilies')) ? true : false;
			$article_data['enable_bbcode']		= ($config['kb_allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
			$article_data['enable_urls']		= true;
		}
		$article_data['enable_magic_url'] = false;
		
		$check_value = (($article_data['enable_bbcode']+1) << 8) + (($article_data['enable_smilies']+1) << 4) + (($article_data['enable_urls']+1) << 2) + (($article_data['enable_sig']+1) << 1);
		
		// Do we want to edit our post ?
		if ($this->mode == 'edit' && ($article_data['bbcode_uid'] || $article_data['article_desc_uid']))
		{
			$message_parser->bbcode_uid = $article_data['bbcode_uid'];
			$desc_parser->bbcode_uid = $article_data['article_desc_uid'];
		}
		
		// HTML, BBCode, Smilies, Images and Flash status
		$bbcode_status	= ($config['kb_allow_bbcode'] && $auth->acl_get('u_kb_bbcode', $this->cat_id)) ? true : false;
		$smilies_status	= ($bbcode_status && $config['kb_allow_smilies'] && $auth->acl_get('u_kb_smilies', $this->cat_id)) ? true : false;
		$img_status		= ($bbcode_status && $auth->acl_get('u_kb_img', $this->cat_id)) ? true : false;
		$url_status		= ($config['kb_allow_post_links']) ? true : false;
		$flash_status	= ($bbcode_status && $auth->acl_get('u_kb_flash', $this->cat_id) && $config['kb_allow_post_flash']) ? true : false;
																								 
		if ($submit || $preview || $refresh)
		{
			// Create old data for edit type check
			if($this->mode == 'edit')
			{
				$edit_data = array(
					'article_title_clean'	=> $article_data['article_title_clean'],
					'article_desc'			=> $desc_parser->message,
					'article_tags'			=> $article_data['article_tags'],
					'article_type'			=> $article_data['article_type'],
					'cat_id'				=> $article_data['cat_id'],
					'article_status'		=> $article_data['article_status'],
				);
			}
			
			$article_data['article_title']				= utf8_normalize_nfc(request_var('title', '', true));
			$message_parser->message					= utf8_normalize_nfc(request_var('message', '', true));
			$desc_parser->message						= utf8_normalize_nfc(request_var('desc', '', true));
			$article_data['article_old_tags']			= $article_data['article_tags']; // Save old tags
			$article_data['article_tags'] 				= utf8_normalize_nfc(request_var('tags', '', true));
			$article_data['article_edit_reason'] 		= utf8_normalize_nfc(request_var('reason', '', true));
			$article_data['article_edit_reason_global'] = request_var('reason_global', false);
			$article_data['article_old_open']			= $article_data['article_open'];
			$article_data['article_open']				= request_var('article_open', false);
			$article_data['article_contribution']		= request_var('article_contribution', false);
			$article_data['article_type']				= request_var('type', 0);
			$article_data['request_id']					= request_var('r', 0);
		
			$article_data['enable_bbcode']		= (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
			$article_data['enable_smilies']		= (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
			$article_data['enable_urls']		= (isset($_POST['disable_magic_url'])) ? 0 : 1;
			$article_data['enable_sig']			= (!$config['kb_allow_sig'] || !$auth->acl_get('u_kb_sigs', $this->cat_id)) ? false : ((isset($_POST['attach_sig']) && $user->data['is_registered']) ? true : false);
			
			if ($submit)
			{
				$status_switch = (($article_data['enable_bbcode']+1) << 8) + (($article_data['enable_smilies']+1) << 4) + (($article_data['enable_urls']+1) << 2) + (($article_data['enable_sig']+1) << 1);
				$status_switch = ($status_switch != $check_value);
			}
			else
			{
				$status_switch = 1;
			}
			
			// Parse Attachments - before checksum is calculated
			$message_parser->message = $this->parse_attachments('fileupload', $this->cat_id, $submit, $preview, $refresh, $message_parser->message, 'mode');
			if(sizeof($this->warn_msg))
			{
				$error[] = implode('<br />', $this->warn_msg);
			}
		
			// Grab md5 'checksum' of new message
			$message_md5 = md5($message_parser->message);
		
			// Check checksum ... don't re-parse message if the same
			$update_message = ($this->mode != 'edit' || $message_md5 != $article_data['article_checksum'] || $status_switch || strlen($article_data['bbcode_uid']) < BBCODE_UID_LEN) ? true : false;
			
			// Parse message
			if ($update_message)
			{
				if (sizeof($message_parser->warn_msg))
				{
					$error[] = fix_error_vars('article', implode('<br />', $message_parser->warn_msg));
					$message_parser->warn_msg = array();
				}
		
				$message_parser->parse($article_data['enable_bbcode'], ($config['kb_allow_post_links']) ? $article_data['enable_urls'] : false, $article_data['enable_smilies'], $img_status, $flash_status, true, $config['kb_allow_post_links']);
		
				// On a refresh we do not care about message parsing errors
				if (sizeof($message_parser->warn_msg) && $refresh)
				{
					$message_parser->warn_msg = array();
				}
			}
			else
			{
				$message_parser->bbcode_bitfield = $article_data['bbcode_bitfield'];
			}
			
			if (sizeof($desc_parser->warn_msg))
			{
				$error[] = fix_error_vars('desc', implode('<br />', $desc_parser->warn_msg));
				$desc_parser->warn_msg = array();
			}
	
			$desc_parser->parse($article_data['enable_bbcode'], ($config['kb_allow_post_links']) ? $article_data['enable_urls'] : false, $article_data['enable_smilies'], false, false, true, $config['kb_allow_post_links']);
	
			// On a refresh we do not care about message parsing errors
			if (sizeof($desc_parser->warn_msg) && $refresh)
			{
				$desc_parser->warn_msg = array();
			}
			
			// check form
			if (($submit || $preview) && !check_form_key('posting'))
			{
				$error[] = $user->lang['FORM_INVALID'];
			}
			
			// Parse subject
			if (!$preview && !$refresh && utf8_clean_string($article_data['article_title']) === '' && ($this->mode == 'add' || $this->mode == 'edit'))
			{
				$error[] = $user->lang['KB_EMPTY_TITLE'];
			}
			
			if (sizeof($message_parser->warn_msg))
			{
				$error[] = fix_error_vars('article', implode('<br />', $message_parser->warn_msg));
			}
			
			// Make our own parser for the desc length so we can control it
			$desc_parser->warn_msg = array();
			if (!$config['kb_disable_desc'] && ($config['kb_desc_min_chars'] > 0 || $config['kb_desc_max_chars'] > 0))
			{
				$msg_len = utf8_strlen($desc_parser->message);
				
				if ($config['kb_desc_min_chars'] > 0 && $config['kb_desc_min_chars'] > $msg_len)
				{
					$desc_parser->warn_msg[] = $user->lang['TOO_FEW_CHARS'];
				}
				
				if($config['kb_desc_max_chars'] > 0 && $config['kb_desc_max_chars'] < $msg_len)
				{
					$desc_parser->warn_msg[] = sprintf($user->lang['TOO_MANY_CHARS_POST'], $msg_len, $config['kb_desc_max_chars']);
				}
			}
			
			if (sizeof($desc_parser->warn_msg))
			{
				$error[] = fix_error_vars('desc', implode('<br />', $desc_parser->warn_msg));
			}
			
			// DNSBL check
			if ($config['check_dnsbl'] && !$refresh)
			{
				if (($dnsbl = $user->check_dnsbl('post')) !== false)
				{
					$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
				}
			}
			
			// Check to see if we want to update tags
			$create_tags = false;
			if(isset($article_data['article_tags']))
			{
				// Truncate tags
				$tags = truncate_string($article_data['article_tags']);
				if($this->mode == 'edit')
				{
					if($tags != $article_data['article_old_tags'] || $tags != '')
					{
						$create_tags = true;
					}
				}
				else
				{	
					if($tags != '')
					{
						$create_tags = true;
					}
				}
			}
			
			// Check to see if we need to update article time
			$article_time = request_var('time', 0);
			if($article_time > 0 && $auth->acl_get('m_kb_time') && $this->mode == 'edit')
			{
				switch($article_time)
				{
					case 1:
						// Update to current time
						$article_data['article_time'] = $current_time;
					break;
					
					case 2:
						// Create custom date and time
						$day = request_var('day', 0);
						$month = request_var('month', 0);
						$year = request_var('year', 0);
						$hour = request_var('hour', 0);
						$min = request_var('min', 0);
						
						// Add leading 0's where needed
						$day = ($day < 10) ? 0 . $day : $day;
						$month = ($month < 10) ? 0 . $month : $month;
						$hour = ($hour < 10) ? 0 . $hour : $hour;
						$min = ($min < 10) ? 0 . $min : $min;
						
						// Convert to timestamp, format: mm/dd/yyyy hh:mm, remember to take care of user timezone + dst
						$local_time = strtotime($month . '/' . $day . '/' . $year . ' ' . $hour . ':' . $min, $current_time);
						$article_data['article_time'] = $local_time + $user->timezone + $user->dst;
					break;
				}
			}
			
			// Check if there is a change of author
			$article_author = request_var('author', 0);
			if($article_author > 0 && $auth->acl_get('m_kb_author') && $this->mode == 'edit')
			{
				switch($article_author)
				{
					case 1:
						// Use this user
						// Update count
						if($article_data['article_status'] == STATUS_APPROVED)
						{
							$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles - 1
								WHERE user_articles > 0
								AND user_id = ' . $article_data['article_user_id'];
							$db->sql_query($sql);
							
							$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles + 1
								WHERE user_id = ' . $user->data['user_id'];
							$db->sql_query($sql);
						}
						
						$article_data['article_user_id'] = $user->data['user_id'];
						$article_data['username'] = $user->data['username'];
						$article_data['user_color'] = $user->data['user_colour'];
						
						// Update attachments to the new user 
						if($article_data['article_attachment'])
						{
							$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . '
									SET poster_id = ' . (int) $article_data['article_user_id'] . '
									WHERE article_id = ' . $this->article_id;
							$db->sql_query($sql);
						}
					break;
					
					case 2:
						// Custom author
						$sql = 'SELECT user_id, username, user_colour
								FROM ' . USERS_TABLE . "
								WHERE username_clean = '" . $db->sql_escape(utf8_clean_string(request_var('username', '', true))) . "'";
						$result = $db->sql_query($sql);
						$user_data = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);
				
						if (!$user_data)
						{
							continue;
						}
						
						// Update count (from old till new)
						if($article_data['article_status'] == STATUS_APPROVED)
						{
							$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles - 1
								WHERE user_articles > 0
								AND user_id = ' . $article_data['article_user_id'];
							$db->sql_query($sql);
							
							$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles + 1
								WHERE user_id = ' . $user_data['user_id'];
							$db->sql_query($sql);
						}
						
						$article_data['article_user_id'] = $user_data['user_id'];
						$article_data['username'] = $user_data['username'];
						$article_data['user_colour'] = $user_data['user_colour'];
						
						// Update attachments to the new user 
						if($article_data['article_attachment'])
						{
							$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . '
									SET poster_id = ' . (int) $article_data['article_user_id'] . '
									WHERE article_id = ' . $this->article_id;
							$db->sql_query($sql);
						}
					break;
				}
			}
			
			// Check for contribution and override user choices (if not allowed)
			if($this->mode == 'edit')
			{
				if($article_data['article_contribution'])
				{
					// User can contribute if properly authorized
					if(!$auth->acl_get('u_kb_add_co', $this->cat_id))
					{
						$article_data['article_contribution'] = false;
					}
				}
				else if(!$auth->acl_get('m_kb_edit') && $auth->acl_get('u_kb_add_co') && $article_data['article_user_id'] == $user->data['user_id'])
				{
					// Force contribution
					$article_data['article_contribution'] = true;
				}
			}
			
			// Check for open article
			if($article_data['article_open'])
			{
				if(!$auth->acl_get('u_kb_add_op', $this->cat_id))
				{
					$article_data['article_open'] = false;
				}
			}
			else if($this->mode == 'edit' && ($article_data['article_open'] == false) && ($article_data['article_old_open'] == true) && !$auth->acl_get('u_kb_add_op', $this->cat_id))
			{
				$article_data['article_open'] = true;
			}
			
			if ($submit && !sizeof($error))
			{
				$data = array(
					'article_title'					=> $article_data['article_title'],
					'article_attachment'			=> (isset($article_data['article_attachment'])) ? (int) $article_data['article_attachment'] : 0,
					'article_id'					=> (int) $this->article_id,
					'cat_id'						=> (int) $this->cat_id,
					'article_type'					=> (int) $article_data['article_type'],
					'article_contribution'			=> (bool) $article_data['article_contribution'],
					'article_open'					=> (bool) $article_data['article_open'],
					'article_user_id'				=> (int) ($this->mode == 'edit') ? $article_data['article_user_id'] : $user->data['user_id'],
					'article_user_name'				=> ($this->mode == 'edit') ? $article_data['username'] : $user->data['username'],
					'article_user_color'			=> ($this->mode == 'edit') ? $article_data['user_colour'] : $user->data['user_colour'],
					'article_desc'					=> $desc_parser->message,
					'article_desc_options'  		=> 7,
					'article_desc_bitfield' 		=> $desc_parser->bbcode_bitfield,
					'article_desc_uid'				=> $desc_parser->bbcode_uid,
					'article_tags'					=> (string) $article_data['article_tags'],
					'article_views'					=> (int) ($this->mode == 'edit') ? $article_data['article_views'] : 0,
					'article_time'					=> ($this->mode == 'edit') ? $article_data['article_time'] : $current_time,
					'enable_sig'					=> (bool) $article_data['enable_sig'],
					'enable_bbcode'					=> (bool) $article_data['enable_bbcode'],
					'enable_smilies'				=> (bool) $article_data['enable_smilies'],
					'enable_urls'					=> (bool) $article_data['enable_urls'],
					'message_md5'					=> (string) $message_md5,
					'article_checksum'				=> (isset($article_data['article_checksum'])) ? (string) $article_data['article_checksum'] : '',
					'article_last_edit_id'			=> ($this->mode == 'edit') ? $article_data['article_last_edit_id'] : 0,
					'article_last_edit_time'		=> ($this->mode == 'edit') ? $current_time : 0,
					'article_edit_reason'			=> (string) $article_data['article_edit_reason'],
					'article_edit_reason_global'	=> (bool) $article_data['article_edit_reason_global'],
					'bbcode_bitfield'				=> $message_parser->bbcode_bitfield,
					'bbcode_uid'					=> $message_parser->bbcode_uid,
					'message'						=> $message_parser->message,
					'attachment_data'				=> $this->attachment_data,
					'filename_data'					=> $this->filename_data,
					'article_status'				=> (int) ($this->mode == 'edit') ? $article_data['article_status'] : (($auth->acl_get('u_kb_add_wa', $this->cat_id)) ? STATUS_APPROVED : STATUS_UNREVIEW),
					'create_tags'					=> $create_tags,
					'current_time'					=> $current_time,
					'request_id'					=> (int) $article_data['request_id'],
					'edit_data'						=> $edit_data,
				);
				// Edit type has already been set upon submission
				$data['edit_type'] = ($this->mode == 'edit') ? check_edit_type($data, $edit_data, $update_message) : array();
				$article_id = article_submit($this->mode, $data, $update_message, $this->article_id);
				
				// Update table info for posting without approval
				if($auth->acl_get('u_kb_add_wa', $this->cat_id) && $this->mode == 'add')
				{
					// Assign request to this article
					if($data['request_id'])
					{
						$sql = 'UPDATE ' . KB_REQ_TABLE . '
								SET article_id = ' . $article_id . ', request_status = ' . STATUS_ADDED . '
								WHERE request_id = ' . $data['request_id'];
						$db->sql_query($sql);
					}
					
					// handle latest article list for cat
					$late_articles = array(
						'article_id'		=> $article_id,
						'article_title'		=> $data['article_title'],
					);
					handle_latest_articles('add', $data['cat_id'], $late_articles, $config['kb_latest_articles_c']);
				
					set_config('kb_last_updated', time(), true);
					
					$sql = 'UPDATE ' . KB_CATS_TABLE . '
							SET cat_articles = cat_articles + 1
							WHERE cat_id = ' . $data['cat_id'];
					$db->sql_query($sql);
					
					$sql = 'UPDATE ' . USERS_TABLE . '
							SET user_articles = user_articles + 1
							WHERE user_id = ' . $data['article_user_id'];
					$db->sql_query($sql);
					
					set_config('kb_last_article', $article_id, true);
					set_config('kb_total_articles', $config['kb_total_articles'] + 1, true);
				}
				
				// Handle notifications upon edit
				if($this->mode == 'edit')
				{
					$notify_on = ($update_message) ? array(NOTIFY_EDIT_CONTENT, NOTIFY_EDIT_ALL) : NOTIFY_EDIT_ALL;
					kb_handle_notification($this->article_id, $article_data['article_title'], $notify_on);
					
					if($data['article_status'] == STATUS_APPROVED)
					{
						// handle latest article list for cat
						$late_articles = array(
							'article_id'		=> $article_id,
							'article_title'		=> $data['article_title'],
						);
						handle_latest_articles('edit', $data['cat_id'], $late_articles, $config['kb_latest_articles_c']);
					
						if($this->cat_id != $old_cat) // Update latest articles when moving article
						{
							handle_latest_articles('delete', $old_cat, $late_articles, $config['kb_latest_articles_c']);
						}
						set_config('kb_last_updated', time(), true);
					}
				}
				
				$redirect_url = ($auth->acl_get('u_kb_add_wa', $this->cat_id)) ? kb_append_sid('article', array('id' => $article_id, 'title' => $data['article_title']), false, 'kb', true) : append_sid("{$phpbb_root_path}ucp.{$phpEx}", 'i=kb&amp;mode=articles&amp;ma=view&amp;a=' . $article_id);
				$message = ($this->mode == 'edit') ? 'EDITED' : (($auth->acl_get('u_kb_add_wa', $this->cat_id)) ? 'ADDED_WA' : 'ADDED');
				$message = sprintf($user->lang['KB_' . $message], '<a href="' . $redirect_url . '">', '</a>');
				$message .= '<br /><br />' . sprintf($user->lang['RETURN_KB_CAT'], '<a href="' . kb_append_sid('cat', array('id' => $this->cat_id, 'title' => $article_data['cat_name'])) . '">', '</a>') . '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid($phpbb_root_path . 'kb.' . $phpEx) . '">', '</a>');
				meta_refresh(5, $redirect_url);
				trigger_error($message);
			}
		}
		
		// Preview
		if (!sizeof($error) && $preview)
		{
			$article_data['article_time'] = ($this->mode == 'edit') ? $article_data['article_time'] : $current_time;
		
			$preview_message = $message_parser->format_display($article_data['enable_bbcode'], $article_data['enable_urls'], $article_data['enable_smilies'], false);
		
			$preview_signature = ($this->mode == 'edit') ? $article_data['user_sig'] : $user->data['user_sig'];
			$preview_signature_uid = ($this->mode == 'edit') ? $article_data['user_sig_bbcode_uid'] : $user->data['user_sig_bbcode_uid'];
			$preview_signature_bitfield = ($this->mode == 'edit') ? $article_data['user_sig_bbcode_bitfield'] : $user->data['user_sig_bbcode_bitfield'];
		
			// Signature
			if ($article_data['enable_sig'] && $config['kb_allow_sig'] && $preview_signature && $auth->acl_get('u_kb_sigs', $this->cat_id))
			{
				$parse_sig = new parse_message($preview_signature);
				$parse_sig->bbcode_uid = $preview_signature_uid;
				$parse_sig->bbcode_bitfield = $preview_signature_bitfield;
		
				// Not sure about parameters for bbcode/smilies/urls... in signatures
				$parse_sig->format_display($config['allow_sig_bbcode'], true, $config['allow_sig_smilies']);
				$preview_signature = $parse_sig->message;
				unset($parse_sig);
			}
			else
			{
				$preview_signature = '';
			}
		
			$preview_subject = censor_text($article_data['article_title']);
			
			// Attachment Preview
			if (sizeof($this->attachment_data))
			{
				$template->assign_var('S_HAS_ATTACHMENTS', true);
		
				$update_count = array();
				$attachment_data = $this->attachment_data;
		
				kb_parse_attachments($preview_message, $attachment_data, $update_count, true);
		
				foreach ($attachment_data as $i => $attachment)
				{
					$template->assign_block_vars('attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment)
					);
				}
				unset($attachment_data);
			}
		
			if (!sizeof($error))
			{
				$template->assign_vars(array(
					'PREVIEW_SUBJECT'		=> $preview_subject,
					'PREVIEW_MESSAGE'		=> $preview_message,
					'PREVIEW_SIGNATURE'		=> $preview_signature,
		
					'S_DISPLAY_PREVIEW'		=> true)
				);
			}
		}
		
		// Article posting page begins here
		// Decode text for message display
		$article_data['bbcode_uid'] = $message_parser->bbcode_uid;
		$article_data['article_desc_uid'] = $desc_parser->bbcode_uid;
		$message_parser->decode_message($article_data['bbcode_uid']);
		$desc_parser->decode_message($article_data['article_desc_uid']);
		
		$attachment_data = $this->attachment_data;
		$filename_data = $this->filename_data;
		$article_data['article_text'] = $message_parser->message;
		$article_data['article_desc'] = $desc_parser->message;
		
		// Generate smiley listing
		kb_generate_smilies('inline');
		
		// Generate inline attachment select box
		posting_gen_inline_attachments($attachment_data);
		
		$s_article_types = '';
		if ($auth->acl_get('u_kb_types', $this->cat_id)) // this permission variable now applies to article types
		{
			$s_article_types = make_article_type_select($article_data['article_type']);
		}
		
		// Only do requests when adding an article, not on edit
		if($this->mode == 'add')
		{
			$options = make_req_select();
		}
		
		$bbcode_checked		= (isset($article_data['enable_bbcode'])) ? !$article_data['enable_bbcode'] : (($config['kb_allow_bbcode']) ? !$user->optionget('bbcode') : 1);
		$smilies_checked	= (isset($article_data['enable_smilies'])) ? !$article_data['enable_smilies'] : (($config['kb_allow_smilies']) ? !$user->optionget('smilies') : 1);
		$urls_checked		= (isset($article_data['enable_urls'])) ? !$article_data['enable_urls'] : 0;
		$sig_checked		= $article_data['enable_sig'];
		
		// Page title & action URL, include session_id for security purpose
		$s_action = append_sid("{$phpbb_root_path}kb.$phpEx", "i=$this->mode&amp;c=$this->cat_id", true, $user->session_id);
		$s_action .= ($this->article_id) ? "&amp;a=$this->article_id" : '';
		
		switch ($this->mode)
		{
			case 'add':
				$page_title = $user->lang['KB_ADD_ARTICLE'];
			break;
		
			case 'delete':
			case 'edit':
				$page_title = $user->lang['KB_EDIT_ARTICLE'];
			break;
		}
		
		// Build Navigation Links
		generate_kb_nav($page_title, $article_data);
		
		$s_hidden_fields = build_hidden_fields(array(
			'old_cat'	=> $article_data['cat_id'], // Use this rather than $this->cat_id to get the real db value
		));
		
		$form_enctype = (@ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off' || !$config['kb_allow_attachments'] || !$auth->acl_get('u_kb_attach', $this->cat_id)) ? '' : ' enctype="multipart/form-data"';
		add_form_key('posting');
		
		// Old edit reasons are written into the db so get the new one here
		$edit_reason = utf8_normalize_nfc(request_var('reason', '', true));
		$reason_global = request_var('reason_global', false);
		
		// Make cat select
		$cat_select = make_cat_select($this->cat_id);
		$time_select = make_time_selects($article_data['article_time']);
	
		// Boxes for open and contributions
		$open_allowed = ($auth->acl_get('u_kb_add_op', $this->cat_id)) ? true : false;
		$open_checked = ($article_data['article_open']) ? ' checked="checked"' : '';
		$contrib_allowed = ($this->mode == 'edit' && $auth->acl_get('u_kb_add_co', $this->cat_id)) ? true : false;
		$contrib_checked = (($article_data['article_user_id'] != $user->data['user_id']) ? ($auth->acl_get('m_kb_edit') ? ' checked="checked"' : ' checked="checked" disabled="disabled"') : '');
	
		// Start assigning vars for main posting page ...
		$template->assign_vars(array(
			'L_POST_A'				=> $page_title,
			'L_ICON'				=> $user->lang['KB_ICON'],
			'L_EDIT_REASON'			=> $user->lang['KB_EDIT_REASON'],
			'L_EDIT_REASON_GLOBAL'	=> $user->lang['KB_EDIT_REASON_GLOBAL'],
		
			// Time and author variables for changing these
			'S_CAN_MOD_TIME'		=> ($this->mode == 'edit' && $auth->acl_get('m_kb_time')) ? true : false,
			'DATE_OPTIONS'			=> $time_select['day'],
			'MONTH_OPTIONS'			=> $time_select['month'],
			'YEAR_OPTIONS'			=> $time_select['year'],
			'HOUR_OPTIONS'			=> $time_select['hour'],
			'MIN_OPTIONS'			=> $time_select['min'],
			'S_CAN_MOD_AUTHOR'		=> ($this->mode == 'edit' && $auth->acl_get('m_kb_author')) ? true : false,
			'U_FIND_USERNAME'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=postform&amp;field=username&amp;select_single=true'),
		
			'CAT_NAME'				=> $article_data['cat_name'],
			'ARTICLE_TITLE'			=> $article_data['article_title'],
			'DESC'					=> $article_data['article_desc'],
			'TEXT_MESSAGE'			=> $article_data['article_text'],
			'TAGS'					=> $article_data['article_tags'],
			'EDIT_REASON'			=> $edit_reason,
			'REASON_GLOBAL'			=> ($reason_global) ? 'checked="checked" ' : '',
			'S_EDIT_REASON'			=> ($this->mode == 'edit') ? true : false,
			'BBCODE_STATUS'			=> ($bbcode_status) ? sprintf($user->lang['BBCODE_IS_ON'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>') : sprintf($user->lang['BBCODE_IS_OFF'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>'),
			'IMG_STATUS'			=> ($img_status) ? $user->lang['IMAGES_ARE_ON'] : $user->lang['IMAGES_ARE_OFF'],
			'FLASH_STATUS'			=> ($flash_status) ? $user->lang['FLASH_IS_ON'] : $user->lang['FLASH_IS_OFF'],
			'SMILIES_STATUS'		=> ($smilies_status) ? $user->lang['SMILIES_ARE_ON'] : $user->lang['SMILIES_ARE_OFF'],
			'URL_STATUS'			=> ($bbcode_status && $url_status) ? $user->lang['URL_IS_ON'] : $user->lang['URL_IS_OFF'],
			'POST_DATE'				=> ($article_data['article_time']) ? $user->format_date($article_data['article_time']) : '',
			'ERROR'					=> (sizeof($error)) ? implode('<br />', $error) : '',
			'U_PROGRESS_BAR'		=> append_sid("{$phpbb_root_path}kb.$phpEx", "i=popup&amp;c=$this->cat_id"),
			'UA_PROGRESS_BAR'		=> addslashes(append_sid("{$phpbb_root_path}kb.$phpEx", "i=popup&amp;c=$this->cat_id")),
		
			'S_CLOSE_PROGRESS_WINDOW'	=> (isset($_POST['add_file'])) ? true : false,
			'S_SHOW_ARTICLE_TYPES'		=> $s_article_types,
			'S_BBCODE_ALLOWED'			=> $bbcode_status,
			'S_BBCODE_CHECKED'			=> ($bbcode_checked) ? ' checked="checked"' : '',
			'S_SMILIES_ALLOWED'			=> $smilies_status,
			'S_SMILIES_CHECKED'			=> ($smilies_checked) ? ' checked="checked"' : '',
			'S_SIG_ALLOWED'				=> ($auth->acl_get('u_kb_sigs', $this->cat_id) && $config['kb_allow_sig'] && $user->data['is_registered']) ? true : false,
			'S_SIGNATURE_CHECKED'		=> ($sig_checked) ? ' checked="checked"' : '',
			'S_LINKS_ALLOWED'			=> $url_status,
			'S_MAGIC_URL_CHECKED'		=> ($urls_checked) ? ' checked="checked"' : '',
			'S_FORM_ENCTYPE'			=> $form_enctype,
			'S_REQUEST_OPTIONS'			=> ($this->mode == 'add') ? $options : '',
			'S_CAT_SELECT'				=> $cat_select,
			
			'S_OPEN_ALLOWED'			=> $open_allowed,
			'S_OPEN_CHECKED'			=> $open_checked,
			'S_CONTRIB_ALLOWED'			=> $contrib_allowed,
			'S_CONTRIB_CHECKED'			=> $contrib_checked,
			
			'S_SHOW_DESC_FIELD'			=> ($config['kb_disable_desc']) ? false : true,
			
			'S_BBCODE_IMG'			=> $img_status,
			'S_BBCODE_URL'			=> $url_status,
			'S_BBCODE_FLASH'		=> $flash_status,
		
			'S_POST_ACTION'			=> $s_action,
			'S_HIDDEN_FIELDS'		=> $s_hidden_fields,
			'S_IS_ARTICLE'			=> true,
		));
		
		// Build custom bbcodes array
		display_custom_bbcodes();
		
		// Show attachment box for adding attachments if true
		$allowed = ($auth->acl_get('u_kb_attach', $this->cat_id) && $config['kb_allow_attachments'] && $form_enctype);
		
		// Attachment entry
		kb_posting_gen_attachment_entry($attachment_data, $filename_data, $allowed);
		
		// Output page ...
		$this->page_header($page_title);
		
		$template->set_filenames(array(
			'body' => 'kb/posting_body.html')
		);
		
		page_footer();
	}
	
	/**
	* Comment posting
	* Handles all edits, deletes and adds of comments
	*/
	function comment_posting($module = '')
	{
		global $db, $template, $user, $auth;
		global $config, $phpbb_root_path, $phpEx;
		
		$this->finclude(array('functions_posting', 'functions_display', 'message_parser'), array('f:generate_smilies', 'f:display_forums', 'c:message_parser'));
		$user->add_lang(array('posting', 'mcp', 'viewtopic'));
		
		$submit		= (isset($_POST['post'])) ? true : false;
		$preview	= (isset($_POST['preview'])) ? true : false;
		$refresh	= (isset($_POST['add_file']) || isset($_POST['delete_file'])) ? true : false;
		$quote		= request_var('quote', 0);
		
		$error = $article_data = array();
		$current_time = time();
		$comment_id = request_var('comment_id', 0);
		
		// Set some vars dependant on module
		switch($module)
		{
			case 'mcp':
				$base_url = "{$phpbb_root_path}mcp.$phpEx";
				$base_arg = 'i=kb&amp;hmode=comment';
			break;
			
			case 'ucp':
				$base_url = "{$phpbb_root_path}ucp.$phpEx";
				$base_arg = 'i=kb&amp;mode=articles&amp;ma=comment';
			break;
			
			case '':
			default:
				$base_url = "{$phpbb_root_path}kb.$phpEx";
				$base_arg = 'i=comment';
			break;
		}
		
		// We need to obtain basic category and post info
		switch($this->action)
		{
			case 'add':
				if(!$this->article_id)
				{
					trigger_error('KB_NO_ARTICLE');
				}
				$sql = 'SELECT a.*, c.cat_name, c.parent_id, c.left_id, c.right_id
						FROM ' . KB_TABLE . ' a, ' . KB_CATS_TABLE . ' c
						WHERE article_id = ' . $this->article_id . '
						AND a.cat_id = c.cat_id';
				break;
				
			case 'edit':
			case 'delete':
				if(!$comment_id)
				{
					trigger_error('KB_NO_COMMENT');
				}
				
				$sql = 'SELECT c.*, ca.cat_name, ca.parent_id, ca.left_id, ca.right_id, a.cat_id, a.article_id, a.article_title, a.article_user_id, a.article_status, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_sig_bbcode_bitfield, u.user_colour
						FROM ' . KB_COMMENTS_TABLE . ' c, ' . KB_TABLE . ' a, ' . USERS_TABLE . ' u, ' . KB_CATS_TABLE . ' ca
						WHERE a.article_id = c.article_id
						AND u.user_id = c.comment_user_id
						AND ca.cat_id = a.cat_id
						AND c.comment_id = ' . $comment_id;
				break;
			
			case 'smilies':
				$sql = '';
				kb_generate_smilies('window');
			break;
				
			case 'popup':
				kb_upload_popup();
				return;
			break;
		
			default:
				$sql = '';
			break;
		}
		
		if (!$sql)
		{
			trigger_error('NO_POST_MODE');
		}
		
		$result = $db->sql_query($sql);
		$comment_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if($this->article_id == 0)
		{
			if($this->action == 'edit' || $this->action == 'delete')
			{
				$this->article_id = $comment_data['article_id'];
			}
			else
			{
				trigger_error('KB_NO_ARTICLE');
			}
		}
		
		// We don't want the user to comment unapproved articles by writing url in browser
		if($module == '' && $comment_data['article_status'] != STATUS_APPROVED)
		{
			trigger_error('KB_NOT_COMMENT_UNAPPROVED');
		}
		
		if (!$comment_data)
		{
			trigger_error('KB_NO_COMMENT');
		}
		
		// Check permissions
		if ($user->data['is_bot'])
		{
			redirect(append_sid("{$phpbb_root_path}kb.$phpEx"));
		}
		
		// Is the user able to read within this forum?
		if (!$auth->acl_get('u_kb_view', $comment_data['cat_id']))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('KB_USER_CANNOT_VIEW');
			}
			
			$red_url = ($this->action == 'edit' || $this->action == 'delete') ? "&amp;comment_id=$comment_id" : '';
			login_box(append_sid($base_url, $base_arg . "&amp;action=$this->action&amp;a=$this->article_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_COMMENT_' . strtoupper($this->action)]);
		}
		
		// Permission to do the action asked?
		$is_authed = false;
		switch ($this->action)
		{
			case 'add':
				if ($auth->acl_get('u_kb_comment', $comment_data['cat_id']))
				{
					$is_authed = true;
				}
			break;
		
			case 'edit':
			case 'delete':
				if ($user->data['is_registered'] && $auth->acl_gets(array('u_kb_comment', 'm_kb_comment'), $comment_data['cat_id']))
				{
					$is_authed = true;
				}
			break;
		}
		
		if (!$is_authed)
		{
			if ($user->data['is_registered'])
			{
				trigger_error('KB_USER_CANNOT_COMMENT_' . strtoupper($this->action));
			}
		
			$red_url = ($this->action == 'edit' || $this->action == 'delete') ? "&amp;comment_id=$comment_id" : '';
			login_box(append_sid($base_url, $base_arg . "&amp;action=$this->action&amp;a=$this->article_id" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_COMMENT_' . strtoupper($this->action)]);
		}
		
		// Handle delete mode...
		if ($this->action == 'delete')
		{
			comment_delete($comment_id, $this->article_id, $comment_data['comment_type']);
			return;
		}
		
		if ($this->action == 'edit' && !$auth->acl_get('m_kb_comment'))
		{
			if ($user->data['user_id'] != $comment_data['comment_user_id'])
			{
				trigger_error('KB_USER_CANNOT_COMMENT_EDIT');
			}
		}
		
		$message_parser = new parse_message();

		if (isset($comment_data['comment_text']))
		{
			$message_parser->message = &$comment_data['comment_text'];
			unset($comment_data['comment_text']);
		}
		
		// Set some default variables
		$uninit = array('comment_user_name' => '', 'comment_attachment' => 0, 'comment_user_id' => $user->data['user_id'], 'enable_magic_url' => 0, 'comment_type' => 0, 'comment_time' => 0);
		foreach ($uninit as $var_name => $default_value)
		{
			if (!isset($comment_data[$var_name]))
			{
				$comment_data[$var_name] = $default_value;
			}
		}
		unset($uninit);
		
		// Get attachment info and check to see if valid info has been submitted
		$this->get_submitted_attachment_data($comment_data['comment_user_id']);
		if ($comment_data['comment_attachment'] && !$submit && !$refresh && !$preview && $this->action == 'edit')
		{
			// Do not change to SELECT *
			$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
				FROM ' . KB_ATTACHMENTS_TABLE . '
				WHERE comment_id = ' . $comment_id . '
				AND is_orphan = 0
				ORDER BY filetime DESC';
			$result = $db->sql_query($sql);
			$this->attachment_data = array_merge($this->attachment_data, $db->sql_fetchrowset($result));
			$db->sql_freeresult($result);
		}
		
		// Set some content variables
		if($this->action == 'add')
		{
			$comment_data['comment_title'] = 'RE: ' . censor_text($comment_data['article_title']);
		}
		
		$comment_data['enable_urls'] = $comment_data['enable_magic_url'];
		if ($this->action != 'edit')
		{
			$comment_data['enable_sig']		= ($config['kb_allow_sig'] && $user->optionget('attachsig')) ? true: false;
			$comment_data['enable_smilies']	= ($config['kb_allow_smilies'] && $user->optionget('smilies')) ? true : false;
			$comment_data['enable_bbcode']	= ($config['kb_allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
			$comment_data['enable_urls']	= true;
		}
		$comment_data['enable_magic_url'] = false;
		
		$check_value = (($comment_data['enable_bbcode']+1) << 8) + (($comment_data['enable_smilies']+1) << 4) + (($comment_data['enable_urls']+1) << 2) + (($comment_data['enable_sig']+1) << 1);
		
		// Do we want to edit our post ?
		if ($this->action == 'edit' && $comment_data['bbcode_uid'])
		{
			$message_parser->bbcode_uid = $comment_data['bbcode_uid'];
		}
		
		// HTML, BBCode, Smilies, Images and Flash status
		$bbcode_status	= ($config['kb_allow_bbcode'] && $auth->acl_get('u_kb_bbcode', $comment_data['cat_id'])) ? true : false;
		$smilies_status	= ($bbcode_status && $config['kb_allow_smilies'] && $auth->acl_get('u_kb_smilies', $comment_data['cat_id'])) ? true : false;
		$img_status		= ($bbcode_status && $auth->acl_get('u_kb_img', $comment_data['cat_id'])) ? true : false;
		$url_status		= ($config['kb_allow_post_links']) ? true : false;
		$flash_status	= ($bbcode_status && $auth->acl_get('u_kb_flash', $comment_data['cat_id']) && $config['kb_allow_post_flash']) ? true : false;
																								 
		if ($submit || $preview || $refresh)
		{
			$comment_data['comment_title']		= utf8_normalize_nfc(request_var('title', '', true));
			$message_parser->message			= utf8_normalize_nfc(request_var('message', '', true));
			$comment_data['enable_bbcode']		= (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
			$comment_data['enable_smilies']	= (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
			$comment_data['enable_urls']		= (isset($_POST['disable_magic_url'])) ? 0 : 1;
			$comment_data['enable_sig']		= (!$config['kb_allow_sig'] || !$auth->acl_get('u_kb_sigs', $comment_data['cat_id'])) ? false : ((isset($_POST['attach_sig']) && $user->data['is_registered']) ? true : false);
			
			if ($submit)
			{
				$status_switch = (($comment_data['enable_bbcode']+1) << 8) + (($comment_data['enable_smilies']+1) << 4) + (($comment_data['enable_urls']+1) << 2) + (($comment_data['enable_sig']+1) << 1);
				$status_switch = ($status_switch != $check_value);
			}
			else
			{
				$status_switch = 1;
			}
			
			// Parse Attachments - before checksum is calculated
			$message_parser->message = $this->parse_attachments('fileupload', $comment_data['cat_id'], $submit, $preview, $refresh, $message_parser->message, 'action');
			if(sizeof($this->warn_msg))
			{
				$error[] = implode('<br />', $this->warn_msg);
			}
			
			// Grab md5 'checksum' of new message
			$message_md5 = md5($message_parser->message);
		
			// Check checksum ... don't re-parse message if the same
			$update_message = ($this->action != 'edit' || $message_md5 != $comment_data['comment_checksum'] || $status_switch || strlen($comment_data['bbcode_uid']) < BBCODE_UID_LEN) ? true : false;
			
			// Parse message
			if ($update_message)
			{
				if (sizeof($message_parser->warn_msg))
				{
					$error[] = fix_error_vars('comment', implode('<br />', $message_parser->warn_msg));
					$message_parser->warn_msg = array();
				}
		
				$message_parser->parse($comment_data['enable_bbcode'], ($config['kb_allow_post_links']) ? $comment_data['enable_urls'] : false, $comment_data['enable_smilies'], $img_status, $flash_status, true, $config['kb_allow_post_links']);
		
				// On a refresh we do not care about message parsing errors
				if (sizeof($message_parser->warn_msg) && $refresh)
				{
					$message_parser->warn_msg = array();
				}
			}
			else
			{
				$message_parser->bbcode_bitfield = $comment_data['bbcode_bitfield'];
			}
			
			// check form
			if (($submit || $preview) && !check_form_key('posting'))
			{
				$error[] = $user->lang['FORM_INVALID'];
			}
			
			// Parse subject
			if (!$preview && !$refresh && utf8_clean_string($comment_data['comment_title']) === '' && ($this->action == 'add' || $this->action == 'edit'))
			{
				$error[] = $user->lang['COMMENT_EMPTY_TITLE'];
			}
			
			if (sizeof($message_parser->warn_msg))
			{
				$error[] = fix_error_vars(implode('<br />', $message_parser->warn_msg));
			}
			
			// DNSBL check
			if ($config['check_dnsbl'] && !$refresh)
			{
				if (($dnsbl = $user->check_dnsbl('post')) !== false)
				{
					$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
				}
			}
			
			if ($submit && !sizeof($error))
			{
				$data = array(
					'comment_id'			=> (int) $comment_id,
					'comment_title'			=> $comment_data['comment_title'],
					'comment_attachment'	=> (isset($comment_data['comment_attachment'])) ? (int) $comment_data['comment_attachment'] : 0,
					'article_id'			=> (int) $this->article_id,
					'comment_user_id'		=> (int) ($this->action == 'edit') ? $comment_data['comment_user_id'] : $user->data['user_id'],
					'comment_user_name'		=> ($this->action == 'edit') ? $comment_data['username'] : ($user->data['is_registered'] ? $user->data['username'] : utf8_normalize_nfc(request_var('guest_username', $user->data['username'], true))),
					'comment_user_color'	=> ($this->action == 'edit') ? $comment_data['user_colour'] : $user->data['user_colour'],
					'enable_sig'			=> (bool) $comment_data['enable_sig'],
					'enable_bbcode'			=> (bool) $comment_data['enable_bbcode'],
					'enable_smilies'		=> (bool) $comment_data['enable_smilies'],
					'enable_urls'			=> (bool) $comment_data['enable_urls'],
					'message_md5'			=> (string) $message_md5,
					'comment_checksum'		=> (isset($comment_data['comment_checksum'])) ? (string) $comment_data['comment_checksum'] : '',
					'comment_edit_id'		=> ($this->action == 'edit') ? $user->data['user_id'] : ((isset($comment_data['comment_edit_id'])) ? (int) $comment_data['comment_edit_id'] : 0),
					'comment_edit_time' 	=> ($this->action == 'edit') ? $current_time : 0,
					'comment_edit_name'		=> ($this->action == 'edit') ? $user->data['username'] : '',
					'comment_edit_color'	=> ($this->action == 'edit') ? $user->data['user_colour'] : '',
					'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
					'bbcode_uid'			=> $message_parser->bbcode_uid,
					'message'				=> $message_parser->message,
					'attachment_data'		=> $this->attachment_data,
					'filename_data'			=> $this->filename_data,
					'comment_type'			=> (int) ($this->action == 'edit') ? $comment_data['comment_type'] : (($module == '') ? COMMENT_GLOBAL : COMMENT_MOD),
					'current_time'			=> $current_time,
				);
	
				$comment_id = comment_submit($this->action, $data, $update_message);
				if($module == 'mcp')
				{
					$notify_on = array(NOTIFY_MOD_COMMENT_NOT_GLOBAL);
					kb_handle_notification($this->article_id, $comment_data['article_title'], $notify_on);
					
					$redirect_url = append_sid("{$phpbb_root_path}mcp.{$phpEx}", 'i=kb&amp;hmode=view&amp;a=' . $this->article_id);
					$message = ($this->action == 'edit') ? 'EDITED' : 'ADDED';
					$message = sprintf($user->lang['KB_COMMENT_' . $message], '<a href="' . $redirect_url . '">', '</a>');
					$message .= '<br /><br />' . sprintf($user->lang['RETURN_PAGE'], '<a href="' . append_sid($phpbb_root_path . 'mcp.' . $phpEx, 'i=kb') . '">', '</a>');
				}
				else if($module == 'ucp')
				{
					$redirect_url = append_sid("{$phpbb_root_path}ucp.{$phpEx}", 'i=kb&amp;mode=articles&amp;ma=view&amp;a=' . $this->article_id);
					$message = ($this->action == 'edit') ? 'EDITED' : 'ADDED';
					$message = sprintf($user->lang['KB_COMMENT_' . $message], '<a href="' . $redirect_url . '">', '</a>');
					$message .= '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . append_sid($phpbb_root_path . 'ucp.' . $phpEx, 'i=kb') . '">', '</a>');
				}
				else
				{
					$notify_on = ($auth->acl_get('m_kb_comment')) ? array(NOTIFY_MOD_COMMENT_GLOBAL) : array();
					$notify_on = ($user->data['user_id'] == $comment_data['article_user_id']) ? array_merge($notify_on, array(NOTIFY_AUTHOR_COMMENT, NOTIFY_COMMENT)) : array_merge($notify_on, array(NOTIFY_COMMENT));
					kb_handle_notification($this->article_id, $comment_data['article_title'], $notify_on);
					
					$redirect_url = kb_append_sid('article', array('id' => $this->article_id, 'title' => $comment_data['article_title']), false, 'kb', true);
					$message = ($this->action == 'edit') ? 'EDITED' : 'ADDED';
					$message = sprintf($user->lang['KB_COMMENT_' . $message], '<a href="' . $redirect_url . '">', '</a>');
					$message .= '<br /><br />' . sprintf($user->lang['RETURN_KB_ARTICLE'], '<a href="' . kb_append_sid('article', array('id' => $this->article_id, 'title' => $comment_data['article_title'])) . '">', '</a>') . '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid($phpbb_root_path . 'kb.' . $phpEx) . '">', '</a>');
				}
				
				meta_refresh(5, $redirect_url);
				trigger_error($message);
			}
		}
		
		// Preview
		if (!sizeof($error) && $preview)
		{
			$comment_data['comment_time'] = ($this->action == 'edit') ? $comment_data['comment_time'] : $current_time;
		
			$preview_message = $message_parser->format_display($comment_data['enable_bbcode'], $comment_data['enable_urls'], $comment_data['enable_smilies'], false);
		
			$preview_signature = ($this->action == 'edit') ? $comment_data['user_sig'] : $user->data['user_sig'];
			$preview_signature_uid = ($this->action == 'edit') ? $comment_data['user_sig_bbcode_uid'] : $user->data['user_sig_bbcode_uid'];
			$preview_signature_bitfield = ($this->action == 'edit') ? $comment_data['user_sig_bbcode_bitfield'] : $user->data['user_sig_bbcode_bitfield'];
		
			// Signature
			if ($comment_data['enable_sig'] && $config['kb_allow_sig'] && $preview_signature && $auth->acl_get('u_kb_sigs', $comment_data['cat_id']))
			{
				$parse_sig = new parse_message($preview_signature);
				$parse_sig->bbcode_uid = $preview_signature_uid;
				$parse_sig->bbcode_bitfield = $preview_signature_bitfield;
		
				// Not sure about parameters for bbcode/smilies/urls... in signatures
				$parse_sig->format_display($config['allow_sig_bbcode'], true, $config['allow_sig_smilies']);
				$preview_signature = $parse_sig->message;
				unset($parse_sig);
			}
			else
			{
				$preview_signature = '';
			}
		
			$preview_subject = censor_text($comment_data['comment_title']);
			
			// Attachment Preview
			if (sizeof($this->attachment_data))
			{
				$template->assign_var('S_HAS_ATTACHMENTS', true);
		
				$update_count = array();
				$attachment_data = $this->attachment_data;
		
				kb_parse_attachments($preview_message, $attachment_data, $update_count, true);
		
				foreach ($attachment_data as $i => $attachment)
				{
					$template->assign_block_vars('attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment)
					);
				}
				unset($attachment_data);
			}
		
			if (!sizeof($error))
			{
				$template->assign_vars(array(
					'PREVIEW_SUBJECT'		=> $preview_subject,
					'PREVIEW_MESSAGE'		=> $preview_message,
					'PREVIEW_SIGNATURE'		=> $preview_signature,
		
					'S_DISPLAY_PREVIEW'		=> true)
				);
			}
		}
		
		// Article posting page begins here
		// Decode text for message display
		if($this->action == 'add' && $quote)
		{
			$sql = 'SELECT comment_title, comment_user_name, comment_text, bbcode_uid
					FROM ' . KB_COMMENTS_TABLE . '
					WHERE comment_id = ' . $quote;
			$result = $db->sql_query($sql);
			$quoted_comment = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			$message_parser->message = '[quote="' . $quoted_comment['comment_user_name'] . '"]' . $quoted_comment['comment_text'] . '[/quote]';
			$message_parser->bbcode_uid = $quoted_comment['bbcode_uid'];
			$comment_data['comment_title'] = 'RE: ' . censor_text($quoted_comment['comment_title']);
		}
		
		$comment_data['bbcode_uid'] = $message_parser->bbcode_uid;
		$message_parser->decode_message($comment_data['bbcode_uid']);
		
		$attachment_data = $this->attachment_data;
		$filename_data = $this->filename_data;
		$comment_data['comment_text'] = $message_parser->message;
		
		// Generate smiley listing
		kb_generate_smilies('inline');
		
		// Generate inline attachment select box
		posting_gen_inline_attachments($attachment_data);
		
		$bbcode_checked		= (isset($comment_data['enable_bbcode'])) ? !$comment_data['enable_bbcode'] : (($config['kb_allow_bbcode']) ? !$user->optionget('bbcode') : 1);
		$smilies_checked	= (isset($comment_data['enable_smilies'])) ? !$comment_data['enable_smilies'] : (($config['kb_allow_smilies']) ? !$user->optionget('smilies') : 1);
		$urls_checked		= (isset($comment_data['enable_urls'])) ? !$comment_data['enable_urls'] : 0;
		$sig_checked		= $comment_data['enable_sig'];
		
		// Page title & action URL, include session_id for security purpose
		$s_action = append_sid($base_url, $base_arg . "&amp;action=$this->action&amp;a=$this->article_id", true, $user->session_id);
		$s_action .= ($comment_id) ? "&amp;comment_id=$comment_id" : '';
		
		switch ($this->action)
		{
			case 'add':
				$page_title = $user->lang['KB_ADD_COMMENT'];
			break;
		
			case 'delete':
			case 'edit':
				$page_title = $user->lang['KB_EDIT_COMMENT'];
			break;
		}
		
		// Build Navigation Links
		//generate_kb_nav($page_title, $comment_data);
		
		$s_hidden_fields = '';
		$form_enctype = (@ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off' || !$config['kb_allow_attachments'] || !$auth->acl_get('u_kb_attach', $comment_data['cat_id'])) ? '' : ' enctype="multipart/form-data"';
		add_form_key('posting');
		
		// Start assigning vars for main posting page ...
		$template->assign_vars(array(
			'L_POST_A'				=> $page_title,
			'L_ICON'				=> $user->lang['KB_ICON'],
		
			'CAT_NAME'				=> $comment_data['article_title'],
			'ARTICLE_TITLE'			=> $comment_data['comment_title'],
			'TEXT_MESSAGE'			=> $comment_data['comment_text'],
			'USERNAME'				=> $comment_data['comment_user_name'],
			'BBCODE_STATUS'			=> ($bbcode_status) ? sprintf($user->lang['BBCODE_IS_ON'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>') : sprintf($user->lang['BBCODE_IS_OFF'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>'),
			'IMG_STATUS'			=> ($img_status) ? $user->lang['IMAGES_ARE_ON'] : $user->lang['IMAGES_ARE_OFF'],
			'FLASH_STATUS'			=> ($flash_status) ? $user->lang['FLASH_IS_ON'] : $user->lang['FLASH_IS_OFF'],
			'SMILIES_STATUS'		=> ($smilies_status) ? $user->lang['SMILIES_ARE_ON'] : $user->lang['SMILIES_ARE_OFF'],
			'URL_STATUS'			=> ($bbcode_status && $url_status) ? $user->lang['URL_IS_ON'] : $user->lang['URL_IS_OFF'],
			'POST_DATE'				=> ($comment_data['comment_time']) ? $user->format_date($comment_data['comment_time']) : '',
			'ERROR'					=> (sizeof($error)) ? implode('<br />', $error) : '',
			'U_PROGRESS_BAR'		=> append_sid($base_url, $base_arg . "&amp;a=$this->article_id&amp;action=popup"),
			'UA_PROGRESS_BAR'		=> addslashes(append_sid($base_url, $base_arg . "&amp;a=$this->article_id&amp;action=popup")),
		
			'S_CLOSE_PROGRESS_WINDOW'	=> (isset($_POST['add_file'])) ? true : false,
			'S_BBCODE_ALLOWED'			=> $bbcode_status,
			'S_BBCODE_CHECKED'			=> ($bbcode_checked) ? ' checked="checked"' : '',
			'S_SMILIES_ALLOWED'			=> $smilies_status,
			'S_SMILIES_CHECKED'			=> ($smilies_checked) ? ' checked="checked"' : '',
			'S_SIG_ALLOWED'				=> ($auth->acl_get('u_kb_sigs', $comment_data['cat_id']) && $config['kb_allow_sig'] && $user->data['is_registered']) ? true : false,
			'S_SIGNATURE_CHECKED'		=> ($sig_checked) ? ' checked="checked"' : '',
			'S_LINKS_ALLOWED'			=> $url_status,
			'S_MAGIC_URL_CHECKED'		=> ($urls_checked) ? ' checked="checked"' : '',
			'S_FORM_ENCTYPE'			=> $form_enctype,
			'S_IS_MCP'					=> ($module == 'mcp') ? true : false,
			'S_IS_UCP'					=> ($module == 'ucp') ? true : false,
			'S_SHOW_USERNAME_FIELD'		=> (!$user->data['is_registered'] && $this->action == 'add') ? true : false,
		
			'S_BBCODE_IMG'			=> $img_status,
			'S_BBCODE_URL'			=> $url_status,
			'S_BBCODE_FLASH'		=> $flash_status,
		
			'S_POST_ACTION'			=> $s_action,
			'S_HIDDEN_FIELDS'		=> $s_hidden_fields,
		));
		
		// Build custom bbcodes array
		display_custom_bbcodes();
		
		// Show attachment box for adding attachments if true
		$allowed = ($auth->acl_get('u_kb_attach', $comment_data['cat_id']) && $config['kb_allow_attachments'] && $form_enctype);
		
		// Attachment entry
		kb_posting_gen_attachment_entry($attachment_data, $filename_data, $allowed);
		
		generate_kb_nav($page_title, $comment_data);
		
		// Output page ...
		if($module == '')
		{
			$this->page_header($page_title);
			
			$template->set_filenames(array(
				'body' => 'kb/posting_body.html')
			);
			
			page_footer();
		}
		
		return $page_title;
	}
	
	/**
	* Rate an article
	* Doesn't generate any output, only DB work. All output should be done from view_article
	*/
	function article_rate()
	{
		global $db, $auth, $user, $config, $phpbb_root_path, $phpEx;
		$rating = request_var('rating', 0);
		
		if(!$this->article_id)
		{
			trigger_error('KB_NO_ARTICLE');
		}
		
		// Check if the user has rights, as well as if the user has voted before
		$sql = 'SELECT cat_id , article_user_id, article_title
				FROM ' . KB_TABLE . '
				WHERE article_id = ' . $this->article_id;
		$result = $db->sql_query($sql);
		$article_data = $db->sql_fetchrow($result);
		
		if(!$auth->acl_get('u_kb_rate', $article_data['cat_id']) || $article_data['article_user_id'] == $user->data['user_id'])
		{
			trigger_error('KB_NO_PERM_RATE');
		}
		$db->sql_freeresult($result);
		
		// Any other way to structure this query?
		$sql = "SELECT rating
				FROM " . KB_RATE_TABLE . '
				WHERE article_id = ' . $this->article_id . '
				AND user_id = ' . $user->data['user_id'];
		$result = $db->sql_query($sql);	
		$has_rated = ($db->sql_affectedrows()) ? true : false;	
		$db->sql_freeresult($result);
		
		// Everything is okay, insert vote
		$rating = ($rating > 6) ? 6 : $rating; // Prevent cheating
		$rating = ($rating < 0) ? 0 : $rating; // More prevention
		$sql_data = array(
			'article_id' => $this->article_id,
			'user_id' => $user->data['user_id'],
			'rate_time' => time(),
			'rating' => $rating,
		);
		
		if($rating == 0)
		{
			// Remove rating
			$sql = 'DELETE FROM ' . KB_RATE_TABLE . ' 
					WHERE article_id = ' . $this->article_id . '
					AND user_id = ' . $user->data['user_id'];
			$db->sql_query($sql);
			
			$sql = 'UPDATE ' . KB_TABLE . ' 
					SET article_votes = article_votes - 1
					WHERE article_id = ' . $this->article_id;
			$db->sql_query($sql);
		}
		else if($has_rated)
		{
			// Not sure all dbms likes the query if we dont unset these two:
			unset($sql_data['article_id']);
			unset($sql_data['user_id']);
			
			// Update rating
			$sql = 'UPDATE ' . KB_RATE_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . '
					WHERE article_id = ' . $this->article_id . ' 
					AND user_id = ' . $user->data['user_id'];
			$db->sql_query($sql);
		}
		else
		{
			// New votes
			$sql = 'INSERT INTO ' . KB_RATE_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data);
			$db->sql_query($sql);
			
			$sql = 'UPDATE ' . KB_TABLE . ' 
					SET article_votes = article_votes + 1
					WHERE article_id = ' . $this->article_id;
			$db->sql_query($sql);
		}
		
		if(!$config['kb_ajax_rating'])
		{
			$redirect_url = kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_data['article_title']), false, 'kb', true);
			$message = $user->lang['RATED_THANKS'] . '<br /><br />' . sprintf($user->lang['RETURN_KB_ARTICLE'], '<a href="' . kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_data['article_title'])) . '">', '</a>') . '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid($phpbb_root_path . 'kb.' . $phpEx) . '">', '</a>');
			meta_refresh(5, $redirect_url);
			trigger_error($message);
		}
	}
	
	/**
	* Search Articles
	*/
	function search_articles()
	{
		global $db, $template, $user, $auth, $cache;
		global $config, $phpbb_root_path, $phpEx;
		
		if (isset($config['kb_search_enable']) && !$config['kb_search_enable'])
		{
			trigger_error('SEARCH_NOT_ENABLED', E_USER_ERROR);
		}
		
		$user->add_lang('search');
		
		// Variables concerning search query
		$search_query = array(); // We will build the SQL query in this array
		$keywords = utf8_normalize_nfc(request_var('q', '', true));
		$add_keywords = utf8_normalize_nfc(request_var('add_keywords', '', true));
		$author = request_var('author', '', true);
		$author_id = request_var('author_id', 0);
		$cat_ids = request_var('cat_ids', array(0 => 0));
		$search_in = request_var('search_in', 0);
		$search_terms = request_var('search_terms', 'all');
		$submit = (isset($_POST['submit'])) ? true : false;
			
		// Variables concerning result layout
		$time_limit = request_var('time', 0);
		$edit_time = (isset($_POST['etime'])) ? true : false;
			
		if($author || $author_id || $submit || $keywords)
		{
			// Handle sorting and time limit
			$where_time = (($time_limit > 0) ? (($edit_time) ? 'a.article_last_edit_time' : 'a.article_time') . ' > ' . time() - (3600 * 24 * $time_limit) : '');
			switch($this->sort)
			{
				case 'author':
					$order_by = 'a.article_user_id ' . $this->dir;
				break;
				
				case 'etime':
					$order_by = 'a.article_last_edit_time ' . $this->dir;
				break;
				
				case 'cat':
					$order_by = 'a.cat_id ' . $this->dir;
				break;
				
				case 'title':
					$order_by = 'a.article_title ' . $this->dir;
				break;
				
				case 'atime':
				default:
					$order_by = 'a.article_time ' . $this->dir;
				break;
			}	
			
			$author_id_ary = array();
			if($author_id)
			{
				$author_id_ary[] = $author_id;
			}
			else if($author)
			{
				if ((strpos($author, '*') !== false) && (utf8_strlen(str_replace(array('*', '%'), '', $author)) < $config['min_search_author_chars']))
				{
					trigger_error(sprintf($user->lang['TOO_FEW_AUTHOR_CHARS'], $config['min_search_author_chars']));
				}

				$sql_where = (strpos($author, '*') !== false) ? ' username_clean ' . $db->sql_like_expression(str_replace('*', $db->any_char, utf8_clean_string($author))) : " username_clean = '" . $db->sql_escape(utf8_clean_string($author)) . "'";
		
				$sql = 'SELECT user_id
					FROM ' . USERS_TABLE . "
					WHERE $sql_where
						AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
				$result = $db->sql_query_limit($sql, 100);
		
				while ($row = $db->sql_fetchrow($result))
				{
					$author_id_ary[] = (int) $row['user_id'];
				}
				$db->sql_freeresult($result);
		
				if (!sizeof($author_id_ary))
				{
					trigger_error('NO_SEARCH_RESULTS');
				}
			}
			
			if(sizeof($author_id_ary))
			{
				// Build query to obtain number of articles found first
				$sql = 'SELECT COUNT(a.article_id) as num_results
						FROM ' . KB_TABLE . ' a
						WHERE ' . $db->sql_in_set('a.article_user_id', $author_id_ary) . ((!$where_time == '') ? ' AND ' . $where_time : '') . ' AND a.article_status = ' . STATUS_APPROVED;
				$result = $db->sql_query($sql);
				$articles_found = $db->sql_fetchfield('num_results');
				$db->sql_freeresult($result);
				
				// Make sure $start is set to the last page if it exceeds the amount
				if ($this->start < 0 || $this->start > $articles_found)
				{
					$this->start = ($this->start < 0) ? 0 : floor(($articles_found - 1) / $config['kb_articles_per_page']) * $config['kb_articles_per_page'];
				}
				
				$search_query = array(
					'SELECT'		=> 'a.*, AVG(r.rating) AS rating',
					'FROM'			=> array(
						KB_TABLE => 'a'),
					'LEFT_JOIN'		=> array(
						array(
							'FROM'	=> array(KB_RATE_TABLE => 'r'),
							'ON'	=> 'a.article_id = r.article_id',
						),
					),
					'WHERE'			=> $db->sql_in_set('a.article_user_id', $author_id_ary) . ((!$where_time == '') ? ' AND ' . $where_time : '') . ' AND a.article_status = ' . STATUS_APPROVED,
					'GROUP_BY'		=> 'a.article_id',
					'ORDER_BY'		=> $order_by,
				);
			}
			else
			{
				if ($add_keywords)
				{
					if ($search_terms == 'all')
					{
						$keywords .= ' ' . $add_keywords;
					}
					else
					{
						$search_terms = 'all';
						$keywords = implode(' |', explode(' ', preg_replace('#\s+#u', ' ', $keywords))) . ' ' .$add_keywords;
					}
				}
				
				$search_cats = (empty($cat_ids)) ? '' : $db->sql_in_set('a.cat_id', $cat_ids);
				switch($search_in)
				{
					case SEARCH_TITLE_TEXT_DESC:
						$where_tpl = "(a.article_title " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . "  OR a.article_title_clean " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . " OR a.article_text " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . " OR a.article_desc " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . ") ";
					break;
					
					case SEARCH_TITLE:
						$where_tpl = "(a.article_title " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . " OR a.article_title_clean " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . ")";
					break;
					
					case SEARCH_DESC:
						$where_tpl = "(a.article_desc " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . ") ";
					break;
					
					case SEARCH_TEXT:
						$where_tpl = "(a.article_text " . $db->sql_like_expression($db->any_char . 'SEARCHED' . $db->any_char) . ") ";
					break;
				}
				
				// Break up search terms and construct search query
				$search_where = ""; // Status approved needs to be applied here
				$term = '';
				$split_query = explode(' ', $keywords);
				foreach ($split_query as $search_term)
				{
					$search_where .= $term . str_replace('SEARCHED', utf8_clean_string($search_term), $where_tpl);
					$term = ($search_terms == 'all') ? 'AND ' : 'OR ';
				}
			
				// Build query to obtain number of articles found first
				$sql = 'SELECT COUNT(a.article_id) as num_results
						FROM ' . KB_TABLE . ' a
						WHERE ' . $search_where . ((!$where_time == '') ? ' AND ' . $where_time : '') . ' AND a.article_status = ' . STATUS_APPROVED;
				$result = $db->sql_query($sql);
				$articles_found = $db->sql_fetchfield('num_results');
				$db->sql_freeresult($result);
				
				// Make sure $start is set to the last page if it exceeds the amount
				if ($this->start < 0 || $this->start > $articles_found)
				{
					$this->start = ($this->start < 0) ? 0 : floor(($articles_found - 1) / $config['kb_articles_per_page']) * $config['kb_articles_per_page'];
				}
				
				// Build query
				$search_query = array(
					'SELECT'		=> 'a.*, AVG(r.rating) AS rating',
					'FROM'			=> array(
						KB_TABLE => 'a'),
					'LEFT_JOIN'		=> array(
						array(
							'FROM'	=> array(KB_RATE_TABLE => 'r'),
							'ON'	=> 'a.article_id = r.article_id',
						),
					),
					'WHERE'			=> $search_where . ((!$where_time == '') ? ' AND ' . $where_time : '') . ' AND a.article_status = ' . STATUS_APPROVED,
					'GROUP_BY'		=> 'a.article_id',
					'ORDER_BY'		=> $order_by,
				);
			}
			
			// Grab icons
			$icons = $cache->obtain_icons();
			$types = $cache->obtain_article_types();
			$user->add_lang('viewtopic');
			
			$sql = $db->sql_build_query('SELECT', $search_query);
			$result = $db->sql_query_limit($sql, $config['kb_articles_per_page'], $this->start);
			
			// define some vars for urls
			$hilit = implode('|', explode(' ', preg_replace('#\s+#u', ' ', str_replace(array('+', '-', '|', '(', ')', '&quot;'), ' ', $keywords))));
			// Do not allow *only* wildcard being used for hilight
			$hilit = (strspn($hilit, '*') === strlen($hilit)) ? '' : $hilit;
			$u_hilit = urlencode(htmlspecialchars_decode(str_replace('|', ' ', $hilit)));
			
			// Build link for this search
			$search = '';
			$search .= ($keywords) ? '&amp;q=' . $keywords : '';
			$search .= ($author) ? '&amp;author=' . $author : '';
			$search .= ($author_id) ? '&amp;author_id=' . $author_id : '';
			$search .= ($cat_ids) ? implode('&amp;cat_ids%5B%5D=', $cat_ids) : '';
			$search .= ($search_in) ? '&amp;search_in=' . $search_in : '';
			$search .= ($search_terms) ? '&amp;search_terms=' . $search_terms : '';
			$search .= ($time_limit) ? '&amp;time=' . $time_limit : '';
			$search .= ($edit_time) ? '&amp;etime=1' : '';
			$search .= '&amp;sort=' . $this->sort;
			$search .= '&amp;dir=' . strtolower($this->dir);
			$u_search = append_sid("{$phpbb_root_path}kb.$phpEx", 'i=search' . $search);
			
			$l_search_matches = ($articles_found == 1) ? sprintf($user->lang['FOUND_SEARCH_MATCH'], $articles_found) : sprintf($user->lang['FOUND_SEARCH_MATCHES'], $articles_found);
			
			$template->assign_vars(array(
				'L_AUTHOR'				=> $user->lang['ARTICLE_AUTHOR'],
				'L_NO_SEARCH_MATCHES'	=> $user->lang['NO_SEARCH_MATCHES'],
				
				'SEARCH_MATCHES'		=> $l_search_matches,
				'SEARCH_WORDS'			=> $keywords,
				
				'U_SEARCH_WORDS'		=> $u_search,
				
				'PAGINATION'			=> generate_pagination($u_search, $articles_found, $config['kb_articles_per_page'], $this->start),
				'PAGE_NUMBER'			=> on_page($articles_found, $config['kb_articles_per_page'], $this->start),
				'S_SHOW_FORM'			=> false,
				'S_IN_MAIN'				=> true,
				'S_SEARCH_ACTION'		=> $u_search,
			));
			
			if ($hilit)
			{
				// Remove bad highlights
				$hilit_array = array_filter(explode('|', $hilit), 'strlen');
				foreach ($hilit_array as $key => $value)
				{
					$hilit_array[$key] = str_replace('\*', '\w*?', preg_quote($value, '#'));
					$hilit_array[$key] = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $hilit_array[$key]);
				}
				$hilit = implode('|', $hilit_array);
			}
			
			while($row = $db->sql_fetchrow($result))
			{
				// Show results
				// Get article types
				$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
				$article_type['article_title'] = (strlen($hilit) ? preg_replace('#(?!<.*)(?<!\w)(' . $hilit . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">$1</span>', $article_type['article_title']) : $article_type['article_title']);
				
				// Send vars to template
				$template->assign_block_vars('articlerow', array(
					'ARTICLE_ID'				=> $row['article_id'],
					'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
					'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
		
					'COMMENTS'					=> $row['article_comments'],
					'VIEWS'						=> $row['article_views'],
					'ARTICLE_TITLE'				=> censor_text($article_type['article_title']),
					'ARTICLE_DESC'				=> ((!$config['kb_disable_desc']) ? ((strlen($hilit) ? preg_replace('#(?!<.*)(?<!\w)(' . $hilit . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">$1</span>', generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options'])) : generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options']))) : ''),
					'ARTICLE_FOLDER_IMG'		=> $user->img('topic_read', censor_text($row['article_title'])),
					'ARTICLE_FOLDER_IMG_SRC'	=> $user->img('topic_read', censor_text($row['article_title']), false, '', 'src'),
					'ARTICLE_FOLDER_IMG_ALT'	=> censor_text($row['article_title']),
					'ARTICLE_FOLDER_IMG_WIDTH'  => $user->img('topic_read', '', false, '', 'width'),
					'ARTICLE_FOLDER_IMG_HEIGHT'	=> $user->img('topic_read', '', false, '', 'height'),
		
					'ARTICLE_TYPE_IMG'			=> $article_type['type_image']['img'],
					'ARTICLE_TYPE_IMG_WIDTH'	=> $article_type['type_image']['width'],
					'ARTICLE_TYPE_IMG_HEIGHT'	=> $article_type['type_image']['height'],
					'ATTACH_ICON_IMG'			=> ($auth->acl_get('u_kb_download', $row['cat_id']) && $row['article_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
					
					'U_VIEW_ARTICLE'			=> kb_append_sid('article', array('id' => $row['article_id'], 'title' => censor_text($row['article_title']), 'extra' => "hilit=$hilit")),
				));
			}
			$db->sql_freeresult($result);
			
			// Build Navigation Links
			generate_kb_nav($user->lang['SEARCH']);
				
			// Output the page
			$this->page_header($user->lang['SEARCH_KB']);
				
			$template->set_filenames(array(
				'body' => 'kb/search_body.html')
			);
				
			page_footer();
		}
		
		// Get all cats and build sort, time and cat select options
		$cat_options = $sort_options = $time_options = $padding = $holding = '';
		$right = $cat_right = 0;
		$pad_store = array('0' => '');
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'c.cat_id, c.cat_name, c.parent_id, c.left_id, c.right_id',
			'FROM'		=> array(
				KB_CATS_TABLE => 'c'),
			'LEFT_JOIN'	=> array(),
	
			'ORDER_BY'	=> 'c.left_id ASC',
		));
		$result = $db->sql_query($sql);
		
		while($row = $db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp; &nbsp;';
				$pad_store[$row['parent_id']] = $padding;
			}
			else if ($row['left_id'] > $right + 1)
			{
				if (isset($pad_store[$row['parent_id']]))
				{
					$padding = $pad_store[$row['parent_id']];
				}
				else
				{
					continue;
				}
			}
		
			$right = $row['right_id'];
		
			$selected = (in_array($row['cat_id'], $cat_ids)) ? ' selected="selected"' : '';
		
			if ($row['left_id'] > $cat_right)
			{
				// make sure we don't forget anything
				$cat_options .= $holding;
				$holding = '';
			}
		
			if ($row['right_id'] - $row['left_id'] > 1)
			{
				$cat_right = max($cat_right, $row['right_id']);
		
				$holding .= '<option value="' . $row['cat_id'] . '"' . $selected . '>' . $padding . $row['cat_name'] . '</option>';
			}
			else
			{
				$cat_options .= $holding . '<option value="' . $row['cat_id'] . '"' . $selected . '>' . $padding . $row['cat_name'] . '</option>';
				$holding = '';
			}
		}
		
		if ($holding)
		{
			$cat_options .= $holding;
		}
		
		$db->sql_freeresult($result);
		unset($pad_store);
		
		// Sort options
		$s_sort_options = array(
			'atime'		=> $user->lang['KB_SORT_ATIME'],
			'etime'		=> $user->lang['KB_SORT_ETIME'],
			'title'		=> $user->lang['KB_SORT_TITLE'],
			'author'	=> $user->lang['KB_SORT_AUTHOR'],
			'cat'		=> $user->lang['KB_SORT_CAT'],
		);
		
		$selected = ' selected="selected"';
		foreach($s_sort_options as $value => $name)
		{
			$sort_options .= '<option value="' . $value . '"' . $selected . '>' . $name . '</option>';
			$selected = '';
		}
		
		$limit_days	= array(
			0 	=> $user->lang['ALL_RESULTS'], 
			1 	=> $user->lang['1_DAY'], 
			7 	=> $user->lang['7_DAYS'], 
			14 	=> $user->lang['2_WEEKS'], 
			30 	=> $user->lang['1_MONTH'], 
			90 	=> $user->lang['3_MONTHS'], 
			180 => $user->lang['6_MONTHS'], 
			365 => $user->lang['1_YEAR']
		);
		$selected = ' selected="selected"';
		foreach($limit_days as $value => $name)
		{
			$time_options .= '<option value="' . $value . '"' . $selected . '>' . $name . '</option>';
			$selected = '';
		}
		
		// Show search form
		$template->assign_vars(array(
			'S_SHOW_FORM'					=> true,
			'S_CAT_OPTIONS'					=> $cat_options,
			'S_SORT_OPTIONS'				=> $sort_options,
			'S_TIME_OPTIONS'				=> $time_options,
			'S_SEARCH_ACTION'				=> append_sid("{$phpbb_root_path}kb.$phpEx"),
			'S_HIDDEN_FIELDS'				=> build_hidden_fields(array('i' => 'search')),
			'S_SEARCH_DESC'					=> ($config['kb_disable_desc']) ? false : true,
			
			'L_SEARCH_KEYWORDS_EXPLAIN'		=> $user->lang['KB_KEYWORDS_EXPLAIN'],
			'L_SEARCH_CATS'					=> $user->lang['KB_SEARCH_CATS'],
			'L_SEARCH_CATS_EXPLAIN'			=> $user->lang['KB_SEARCH_CATS_EXPLAIN'],
			'L_SEARCH_TITLE_TEXT_DESC'		=> $user->lang['KB_SEARCH_TITLE_TEXT_DESC'],
			'L_SEARCH_TITLE'				=> $user->lang['KB_SEARCH_TITLE'],
			'L_SEARCH_TEXT'					=> $user->lang['KB_SEARCH_TEXT'],
			'L_SEARCH_DESC'					=> $user->lang['KB_SEARCH_DESC'],
			'L_SORT_ETIME'					=> $user->lang['KB_SORT_ETIME'],
			
		));
		
		// Build Navigation Links
		generate_kb_nav($user->lang['SEARCH']);
			
		// Output the page
		$this->page_header($user->lang['SEARCH_KB']);
			
		$template->set_filenames(array(
			'body' => 'kb/search_body.html')
		);
			
		page_footer();
	}
	
	/**
	* View tags
	* Page shows a list of articles with the selected tag
	*/
	function view_tag()
	{
		global $db, $user, $template, $config, $cache;
		global $phpbb_root_path, $phpEx, $auth;
		
		$this->finclude('functions_display', 'f:display_forums');
		$user->add_lang('viewtopic');
		
		$sql = 'SELECT article_id, tag_name
				FROM ' . KB_TAGS_TABLE . "
				WHERE tag_name_lc = '" . $db->sql_escape($this->tag) . "'";
		$result = $db->sql_query($sql);
		
		$articles = array();
		$tag_name = '';
		while($row = $db->sql_fetchrow($result))
		{
			$articles[] = $row['article_id'];
			$tag_name = $row['tag_name'];
		}	
		$db->sql_freeresult($result);
		$articles_count = count($articles);
		
		// Make sure $start is set to the last page if it exceeds the amount
		if ($this->start < 0 || $this->start > $articles_count)
		{
			$this->start = ($this->start < 0) ? 0 : floor(($articles_count - 1) / $config['kb_articles_per_page']) * $config['kb_articles_per_page'];
		}
		
		// Grab icons
		$icons = $cache->obtain_icons();
		$types = $cache->obtain_article_types();
		
		switch($this->sort)
		{
			// Ability to sort by post time, edit time, name, views, author	
			case 'etime':
				$order_by = 'a.article_last_edit_time ' . $this->dir;
			break;
				
			case 'name':
				$order_by = 'a.article_title ' . $this->dir;
			break;
			
			case 'views':
				$order_by = 'a.article_views ' . $this->dir;
			break;
				
			case 'author':
				$order_by = 'a.article_user_name ' . $this->dir;
			break;
			
			case 'rating': 
				$order_by = 'rating ' . $this->dir;
			break;
			
			case 'time':
			case '':
			default:
				$order_by = 'a.article_time ' . $this->dir;
			break;
		}
		
		// Show all articles to moderators
		$is_mod = ($auth->acl_get('m_kb_view')) ? '' : ' AND article_status = ' . STATUS_APPROVED;
		
		// Retrieve all articles
		// Use start to limit shown articles
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> 'a.*, AVG(r.rating) AS rating',
			'FROM'		=> array(
				KB_TABLE => 'a'),
			'LEFT_JOIN'	=> array(
				array(
					'FROM' => array(KB_RATE_TABLE => 'r'),
					'ON' => 'a.article_id = r.article_id',
				),
			),
			'WHERE'		=> $db->sql_in_set('a.article_id', $articles) . $is_mod,
			'GROUP_BY'	=> 'a.article_id',
			'ORDER_BY'  => $order_by,
		));
		
		$result = $db->sql_query_limit($sql, $config['kb_articles_per_page'], $this->start);
		while($row = $db->sql_fetchrow($result))
		{
			if (!$auth->acl_get('u_kb_view', $row['cat_id']) || !$auth->acl_get('u_kb_read', $row['cat_id']))
			{
				continue;
			}
			
			// Get article types
			$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
			
			// Send vars to template
			$template->assign_block_vars('articlerow', array(
				'ARTICLE_ID'				=> $row['article_id'],
				'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
				'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
	
				'COMMENTS'					=> $row['article_comments'],
				'VIEWS'						=> $row['article_views'],
				'ARTICLE_TITLE'				=> censor_text($article_type['article_title']),
				'ARTICLE_DESC'				=> ($config['kb_show_desc_cat'] && !$config['kb_disable_desc']) ? generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options']) : '',
				'ARTICLE_FOLDER_IMG'		=> $user->img('topic_read', censor_text($row['article_title'])),
				'ARTICLE_FOLDER_IMG_SRC'	=> $user->img('topic_read', censor_text($row['article_title']), false, '', 'src'),
				'ARTICLE_FOLDER_IMG_ALT'	=> censor_text($row['article_title']),
				'ARTICLE_FOLDER_IMG_WIDTH'  => $user->img('topic_read', '', false, '', 'width'),
				'ARTICLE_FOLDER_IMG_HEIGHT'	=> $user->img('topic_read', '', false, '', 'height'),
				'ARTICLE_UNAPPROVED'		=> ($row['article_status'] != STATUS_APPROVED && $auth->acl_gets('m_kb_view', 'm_kb_status')) ? true : false,
				'U_MCP_QUEUE'				=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&hmode=status&a=' . $row['article_id']),
	
				'ARTICLE_TYPE_IMG'			=> $article_type['type_image']['img'],
				'ARTICLE_TYPE_IMG_WIDTH'	=> $article_type['type_image']['width'],
				'ARTICLE_TYPE_IMG_HEIGHT'	=> $article_type['type_image']['height'],
				'ATTACH_ICON_IMG'			=> ($auth->acl_get('u_kb_download', $row['cat_id']) && $row['article_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
				
				'U_VIEW_ARTICLE'			=> kb_append_sid('article', array('id' => $row['article_id'], 'title' => censor_text($row['article_title']))),
			));
		}
		$db->sql_freeresult($result);
		
		$sort_direction = make_direction_select($this->dir);
		$sort_options = make_sort_select('articles', $this->sort);
		
		$template->assign_vars(array(
			'L_AUTHOR'			=> $user->lang['ARTICLE_AUTHOR'],
			'L_ARTICLES_LC' 	=> utf8_strtolower($user->lang['ARTICLES']),
			'S_HAS_SUBCATS' 	=> false,
			'PAGINATION'		=> generate_pagination(kb_append_sid('tag', array('id' => $this->tag, 'title' => $this->tag, 'extra' => strlen($this->sort) ? "sort=$this->sort" : '')), $articles_count, $config['kb_articles_per_page'], $this->start),
			'PAGE_NUMBER'		=> on_page($articles_count, $config['kb_articles_per_page'], $this->start),
			'TOTAL_ARTICLES' 	=> $articles_count,					
			'S_NO_TOPIC'		=> false,
			'S_IN_MAIN'			=> true,
			'S_SORT_OPTIONS'	=> $sort_options,
			'S_SORT_DIRECTION'	=> $sort_direction,
			'S_ARTICLE_ACTION'	=> append_sid("{$phpbb_root_path}kb.$phpEx", 't=' . $this->tag),
			'UNAPPROVED_IMG'	=> $user->img('icon_topic_unapproved', 'ARTICLE_UNAPPROVED'),
			'IN_TAG'			=> true,
		));
		
		// Build Navigation Links
		generate_kb_nav($user->lang['VIEW_TAG']);
		
		// Output the page
		$this->page_header($user->lang['VIEW_TAG'] . ' - ' . $tag_name);
		
		$template->set_filenames(array(
			'body' => 'kb/view_cat_list.html')
		);
		
		page_footer();
	}
	
	/**
	* Article history
	* Page shows edit history for the article
	*/
	function generate_article_history()
	{
		global $db, $user, $template, $cache;
		global $phpbb_root_path, $phpEx, $auth, $config;
		
		$this->finclude('functions_display', 'f:display_forums');
		
		$edit_id = request_var('e', 0);
		$diff_from = request_var('df', 0);
		$diff_to = request_var('dt', '');
		
		$icons = $cache->obtain_icons();
		$types = $cache->obtain_article_types();
		
		// Build sql queries depending on mode
		$diff_exists = false;
		$mod_where = (!$auth->acl_get('m_kb_view')) ? ' AND e.edit_moderated = 0' : '';
		if($edit_id)
		{
			// View an edit, still need current article info to build menu's etc.
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'e.*, e.edit_cat_id, c.cat_name, a.article_title, a.article_type, a.article_tags, a.cat_id',
				'FROM'		=> array(
					KB_EDITS_TABLE => 'e'),
				'LEFT_JOIN'	=> array(
					array(
						'FROM' => array(KB_TABLE => 'a'),
						'ON' => 'e.article_id = a.article_id',
					),
					// Old category information
					array(
						'FROM' => array(KB_CATS_TABLE => 'c'),
						'ON' => 'e.edit_cat_id = c.cat_id',
					),
				),
				'WHERE'		=> "e.edit_id = $edit_id" . $mod_where,
				'GROUP_BY'	=> 'e.article_id',
			));
			$result = $db->sql_query($sql);
			
			if(!$data = $db->sql_fetchrow($result))
			{
				trigger_error('KB_NO_ARTICLE');
			}
			$db->sql_freeresult($result);
			
			if($this->cat_id == 0)
			{
				$this->cat_id = $data['cat_id'];
			}
			$this->article_id = $data['article_id'];
			
			// Authenticate
			if(!$auth->acl_get('u_kb_viewhistory', $this->cat_id))
			{
				if ($user->data['user_id'] != ANONYMOUS)
				{
					trigger_error('KB_NO_PERM_HISTORY');
				}
				
				login_box(append_sid("{$phpbb_root_path}kb.$phpEx", 'i=history&amp;a=' . $this->article_id . '&amp;e=' . $edit_id), $user->lang['KB_LOGIN_EXPLAIN_HISTORY']);
			}
			
			// Process article tags
			$article_tags = '';
			if($data['article_tags'] != '')
			{
				$tags = explode(',', $data['article_tags']);
				$parsed_tags_container = array();
				foreach($tags as $tag)
				{
					// Strip starting spaces
					$str = $tag;
					if(strpos($str, ' ') === 0)
					{
						$tag = utf8_substr($tag, 1);
					}
	
					$lc_tag = utf8_strtolower($tag);
					$parsed_tags_container[] = '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", "t=$lc_tag") . '">' . $tag . '</a>';
				}
				
				$article_tags = implode(', ', $parsed_tags_container);
			}
			
			$bbcode_bitfield = base64_decode($data['edit_bbcode_bitfield']);
			
			// Desc
			if ($data['edit_article_desc'] != '')
			{
				$bbcode_bitfield = $bbcode_bitfield | base64_decode($data['edit_article_desc_bitfield']);
			}
			
			// Instantiate BBCode if need be
			if ($bbcode_bitfield !== '')
			{
				$this->finclude('bbcode', 'c:bbcode');
				$bbcode = new bbcode(base64_encode($bbcode_bitfield));
			}
			
			if($data['edit_article_desc'] != '' && !$config['kb_disable_desc'])
			{
				$article_desc = censor_text($data['edit_article_desc']);
		
				if ($data['edit_article_desc_bitfield'])
				{
					$bbcode->bbcode_second_pass($article_desc, $data['edit_article_desc_uid'], $data['edit_article_desc_bitfield']);
				}
		
				$article_desc = bbcode_nl2br($article_desc);
				$data['edit_article_desc'] = smiley_text($article_desc);
			}
			
			// Parse the message and subject
			$message = censor_text($data['edit_article_text']);
		
			// Second parse bbcode here
			if ($data['edit_bbcode_bitfield'])
			{
				$bbcode->bbcode_second_pass($message, $data['edit_bbcode_uid'], $data['edit_bbcode_bitfield']);
			}
		
			$message = bbcode_nl2br($message);
			$message = smiley_text($message);
		
			// Replace naughty words such as farty pants
			$data['edit_article_title'] = censor_text($data['edit_article_title']);
			
			// Lang var for status edits
			$article_status_ary = array(
				STATUS_APPROVED		=> 'KB_STATUS_APPROVED',
				STATUS_UNREVIEW		=> 'KB_STATUS_UNREVIEW',
				STATUS_DISAPPROVED	=> 'KB_STATUS_DISAPPROVED',
				STATUS_ONHOLD		=> 'KB_STATUS_ONHOLD',
			);
			
			$edit_lang_ary = array(
				EDIT_TYPE_TITLE		=> 'EDIT_TYPE_TITLE',
				EDIT_TYPE_DESC		=> 'EDIT_TYPE_DESC',
				EDIT_TYPE_CONTENT	=> 'EDIT_TYPE_CONTENT',
				EDIT_TYPE_TAGS		=> 'EDIT_TYPE_TAGS',
				EDIT_TYPE_TYPE		=> 'EDIT_TYPE_TYPE',
				EDIT_TYPE_CAT		=> 'EDIT_TYPE_CAT',
				EDIT_TYPE_STATUS	=> 'EDIT_TYPE_STATUS',
			);
			
			// Cycle through changes and generate reports
			$edit_type = unserialize($data['edit_type']);
			foreach($edit_type as $edit)
			{
				$template->assign_block_vars('changerow', array(
					'CHANGE'			=> $user->lang[$edit_lang_ary[$edit]],
				));
			}
			
			$template->assign_vars(array(
				// Current article info
				'ARTICLE_TITLE' 		=> sprintf($user->lang['VIEWING_ARTICLE_HISTORY'], $data['article_title']),
				
				// Edit info
				'EDIT_TIME'				=> $user->format_date($data['edit_time']),
				'EDIT_TITLE'			=> $data['edit_article_title'],
				'EDIT_AUTHOR'			=> get_username_string('full', $data['edit_user_id'], $data['edit_user_name'], $data['edit_user_color']),
				'CAT_NAME'				=> $data['cat_name'],
				'EDIT_TYPE'				=> (isset($types[$data['edit_article_type']]['title'])) ? $types[$data['edit_article_type']]['title'] : '',
				'EDIT_TAGS'				=> $data['edit_article_tags'],
				'EDIT_STATUS'			=> $user->lang[$article_status_ary[$data['edit_article_status']]],
				'EDIT_REASON'			=> $data['edit_reason'],
				'EDIT_REASON_GLOBAL'	=> ($data['edit_reason_global']) ? true : false,
				
				'ARTICLE_MESSAGE'		=> $message,
				'ARTICLE_DESC'			=> ($config['kb_disable_desc']) ? '' : $data['edit_article_desc'],
				'L_CONTENT'				=> $user->lang['ARTICLE_CONTENT'],
				
				'S_VIEW_EDIT'			=> true,
			));
		}
		else if(($diff_from || $diff_from === -1) && $diff_to)
		{
			// Diff between 2 edit's content (title, desc, content)
			// This is a diff between the preparsed content, i.e. the content you put into a textarea, not the generated output!
			// Get basic information about article for menu's
			if(!$this->article_id)
			{
				trigger_error('KB_NO_ARTICLE');
			}
			
			$user->add_lang('install');
			$this->finclude(array('diff/engine', 'diff/diff', 'diff/renderer'));
			
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'a.*, c.cat_id, c.cat_name, c.parent_id, u.*, AVG(r.rating) as article_rating, t.subscribed, t.bookmarked',
				'FROM'		=> array(
					KB_TABLE => 'a'),
				'LEFT_JOIN'	=> array(
					array(
						'FROM' => array(KB_CATS_TABLE => 'c'),
						'ON' => 'a.cat_id = c.cat_id',
					),
					array(
						'FROM' => array(USERS_TABLE => 'u'),
						'ON' => 'a.article_user_id = u.user_id',
					),
					array(
						'FROM' => array(KB_RATE_TABLE => 'r'),
						'ON' => 'a.article_id = r.article_id',
					),
					array(
						'FROM' => array(KB_TRACK_TABLE => 't'),
						'ON' => 'a.article_id = t.article_id AND u.user_id = t.user_id',
					),
				),
				'WHERE'		=> "a.article_id = $this->article_id",
				'GROUP_BY'	=> 'a.article_id',
			));
			$result = $db->sql_query($sql);
			
			if(!$data = $db->sql_fetchrow($result))
			{
				trigger_error('KB_NO_ARTICLE');
			}
			$db->sql_freeresult($result);
			
			if($this->cat_id == 0)
			{
				$this->cat_id = $data['cat_id'];
			}
			
			// Authenticate
			if(!$auth->acl_get('u_kb_viewhistory', $this->cat_id))
			{
				if ($user->data['user_id'] != ANONYMOUS)
				{
					trigger_error('KB_NO_PERM_HISTORY');
				}
				
				login_box(append_sid("{$phpbb_root_path}kb.$phpEx", 'i=history&amp;a=' . $this->article_id . '&amp;df=' . $diff_from . '&amp;dt=' . $diff_to), $user->lang['KB_LOGIN_EXPLAIN_HISTORY']);
			}
			
			$diff = array();
			if($diff_from !== -1)
			{
				$sql = $db->sql_build_query('SELECT', array(
					'SELECT'	=> 'e.edit_id, e.edit_article_title, e.edit_article_desc, e.edit_article_text, e.edit_bbcode_uid',
					'FROM'		=> array(
						KB_EDITS_TABLE => 'e'),
					'WHERE'		=> "e.edit_id = $diff_from" . $mod_where,
				));
				$result = $db->sql_query($sql);
				
				if(!$diff['f'] = $db->sql_fetchrow($result))
				{
					trigger_error('KB_NO_ARTICLE');
				}
				$db->sql_freeresult($result);
			}
			
			if ($diff_to == 'a_' . $this->article_id)
			{	
				//decode uid
				decode_message($data['article_text'], $data['bbcode_uid']);
				$diff['t']['message'] = $data['article_text'];			
			}
			else
			{			
				$sql = $db->sql_build_query('SELECT', array(
					'SELECT'	=> 'e.edit_id, e.edit_article_title, e.edit_article_desc, e.edit_article_text, e.edit_bbcode_uid',
					'FROM'		=> array(
						KB_EDITS_TABLE => 'e'),
					'WHERE'		=> "e.edit_id = $diff_to" . $mod_where,
				));
				
				$result = $db->sql_query($sql);
				if(!$diff['t'] = $db->sql_fetchrow($result))
				{
					trigger_error('KB_NO_ARTICLE');
				}
				$db->sql_freeresult($result);
				
				//decode uid
				decode_message($diff['t']['edit_article_text'], $diff['t']['edit_bbcode_uid']);
				$diff['t']['message'] = $diff['t']['edit_article_text'];
			}			
			
			//decode uid
			decode_message($diff['f']['edit_article_text'], $diff['f']['edit_bbcode_uid']);
			$diff['f']['message'] = $diff['f']['edit_article_text'];
						
			$diff_tool = new diff($diff['f']['message'], $diff['t']['message'], true);
			// Now the correct renderer
			$render_class = 'diff_renderer_side_by_side';
			$renderer = new $render_class();
			
			$template->assign_vars(array(
				'DIFF_CONTENT'			=> $renderer->get_diff_content($diff_tool),
				'S_VIEW_DIFF'			=> true,
				'S_IN_ARTICLE'			=> true,
			));
			
			unset($diff_tool, $renderer);
		}
		else
		{
			// View a list over all edits
			if(!$this->article_id)
			{
				trigger_error('KB_NO_ARTICLE');
			}
			
			// First retrieve current article info for menus etc
			// Then retrieve all edits
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'a.article_id, a.article_title, a.cat_id, a.article_user_id, a.article_user_name, a.article_user_color, a.article_last_edit_time, a.article_last_edit_id, a.article_type, a.article_edit_reason, a.article_edit_reason_global, a.article_status, a.article_edit_contribution',
				'FROM'		=> array(
					KB_TABLE => 'a'),
				'WHERE'		=> "a.article_id = $this->article_id" . (($auth->acl_get('m_kb_view')) ? '' : ' AND a.article_status = ' . STATUS_APPROVED),
				'GROUP_BY'	=> 'a.article_id',
			));
			$result = $db->sql_query($sql);
			
			if(!$data = $db->sql_fetchrow($result))
			{
				trigger_error('KB_NO_ARTICLE');
			}
			$db->sql_freeresult($result);
			
			if($this->cat_id == 0)
			{
				$this->cat_id = $data['cat_id'];
			}
			
			// Authenticate
			if(!$auth->acl_get('u_kb_viewhistory', $this->cat_id))
			{
				if ($user->data['user_id'] != ANONYMOUS)
				{
					trigger_error('KB_NO_PERM_HISTORY');
				}
				
				login_box(append_sid("{$phpbb_root_path}kb.$phpEx", 'i=history&amp;a=' . $this->article_id), $user->lang['KB_LOGIN_EXPLAIN_HISTORY']);
			}
			
			// Unfortunately we need the total count in the first loop as well, so we need this extra query
			$sql = 'SELECT COUNT(e.edit_id) as total_edits
					FROM ' . KB_EDITS_TABLE . ' e
					WHERE e.article_id = ' . $this->article_id . $mod_where;
			$result = $db->sql_query($sql);
			$total_edits = $db->sql_fetchfield('total_edits');
			$db->sql_freeresult($result);
			
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'e.edit_id, e.article_id, e.parent_id, e.edit_user_id, e.edit_user_name, e.edit_user_color, e.edit_time, e.edit_article_title, e.edit_article_status, e.edit_reason, e.edit_reason_global, e.edit_type, e.edit_contribution',
				'FROM'		=> array(
					KB_EDITS_TABLE => 'e'),
				'WHERE'		=> "e.article_id = $this->article_id" . $mod_where,
				'ORDER_BY'	=> "e.parent_id DESC",
			));
			
			$result = $db->sql_query($sql);
			$rowset = array();
			$i = $total_edits;
			$diff_from_old = false;
			while($row = $db->sql_fetchrow($result))
			{
				// Build them into rowset for use when listing them
				$row['edit_type'] = unserialize($row['edit_type']);
				$row['no_diff'] = true;
				
				if(in_array(EDIT_TYPE_CONTENT, $row['edit_type']))
				{
					$row['no_diff'] = false;
					$diff_from_old = true;
				}
				
				// -1 here to prevent from having 2 revisions that are the same
				$i--;
				$rowset[$i] = $row;
			}
			$db->sql_freeresult($result);
			
			// Lang var for status edits
			$article_status_ary = array(
				STATUS_APPROVED		=> 'KB_STATUS_APPROVED',
				STATUS_UNREVIEW		=> 'KB_STATUS_UNREVIEW',
				STATUS_DISAPPROVED	=> 'KB_STATUS_DISAPPROVED',
				STATUS_ONHOLD		=> 'KB_STATUS_ONHOLD',
			);
			
			$num = 0;
			
			// Get article types
			$main_article_type = gen_article_type($data['article_type'], $data['article_title'], $types, $icons);
			
			// Generate an entry which is the current article
			$template->assign_block_vars('editrow', array(
				'ARTICLE_TITLE'		=> censor_text($main_article_type['article_title']),
				'EDIT_BY'			=> sprintf($user->lang['EDITED_BY'], get_username_string('full', $data['article_user_id'], $data['article_user_name'], $data['article_user_color'])),
				'S_IS_CONTRIB'		=> $data['article_edit_contribution'],
				'S_CAN_DIFF_F'		=> false,
				'S_CAN_DIFF_T'		=> (in_array(EDIT_TYPE_CONTENT, $rowset[count($rowset) - 1]['edit_type'])) ? true : false,
				'S_NEWEST'			=> true,
				'EDIT_TIME'			=> $user->format_date($data['article_last_edit_time'], false, true),
				'EDIT_ID'			=> 'a_' . $this->article_id,
				'EDIT_STATUS'		=> $user->lang[$article_status_ary[$data['article_status']]],
				'U_EDIT'			=> ($data['article_status'] == STATUS_APPROVED) ? kb_append_sid('article', array('id' => $this->article_id, 'title' => $data['article_title'], 'extra' => 'view_rev=true')) : append_sid("{$phpbb_root_path}ucp.{$phpEx}", 'i=kb&amp;mode=articles&amp;ma=view&amp;a=' . $this->article_id),
				'U_VIEW_REV'		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=history&amp;e=' . $rowset[count($rowset) - 1]['edit_id']),
				
				// Numbers for the checkbox
				'NUM'				=> $num,
				'UM_MINUS'			=> 0, // Doesn't matter in this row
				'NUM_PLUS'			=> $num + 1,
			));
			
			// Unfortunately we need to store them in a variable as we sometimes need to jump forth and back
			$can_diff_from = false;
			foreach($rowset as $parent_id => $edit)
			{
				$num++;
				
				if($edit['edit_article_title'] == '')
				{
					$article_type = $main_article_type;
				}
				else
				{
					// Get article types
					$article_type = gen_article_type($data['article_type'], $edit['edit_article_title'], $types, $icons);
				}
				
				$template->assign_block_vars('editrow', array(
					'ARTICLE_TITLE'		=> censor_text($article_type['article_title']),
					'EDIT_BY'			=> sprintf($user->lang['EDITED_BY'], get_username_string('full', $edit['edit_user_id'], $edit['edit_user_name'], $edit['edit_user_color'])),
					'S_IS_CONTRIB'		=> ($edit['edit_contribution']) ? true : false,
					'S_CAN_DIFF_F'		=> ($can_diff_from && (!$rowset[$parent_id - 1]['no_diff'] || ($diff_from_old && $num == $total_edits))) ? true : false,
					'S_CAN_DIFF_T'		=> ($rowset[$parent_id - 1]['no_diff'] || $num == $total_edits) ? false : true,
					'S_ORG'				=> ($num == $total_edits) ? true : false,
					'EDIT_TIME'			=> $user->format_date($edit['edit_time'], false, true),
					'EDIT_ID'			=> $edit['edit_id'],
					'EDIT_STATUS'		=> $user->lang[$article_status_ary[$edit['edit_article_status']]],
					'U_EDIT'			=> '',
					'U_VIEW_REV'		=> ($parent_id !== 0) ? append_sid("{$phpbb_root_path}kb.$phpEx", 'i=history&amp;e=' . $rowset[$parent_id - 1]['edit_id']) : '',
					
					// Numbers for the checkbox
					'NUM'				=> $num,
					'NUM_MINUS'			=> $num - 1,
					'NUM_PLUS'			=> $num + 1,
				));
				
				if($rowset[$parent_id - 1]['no_diff'] && $can_diff_from)
				{
					$can_diff_from = false;
				}
				
				if(!$edit['no_diff'])
				{
					$can_diff_from = true;
				}
			}
			
			$template->assign_vars(array(
				'L_TITLE'			=> $user->lang['KB_VIEW_HISTORY'],
				'L_EDITS'			=> $user->lang['KB_EDITS'],
				'L_DIFF_FROM'		=> $user->lang['KB_DIFF_FROM'],
				'L_DIFF_TO'			=> $user->lang['KB_DIFF_TO'],
				'L_EDIT_TIME'		=> $user->lang['EDIT_TIME'],
				'L_STATUS'			=> $user->lang['ARTICLE_STATUS'],
				
				// Form stuff
				'U_DIFF_ACTION'		=> append_sid("{$phpbb_root_path}kb.$phpEx"),
				'ARTICLE_ID'		=> $this->article_id, 
				'L_VIEW_DIFF'		=> $user->lang['KB_VIEW_DIFF'],
			));
		}
		
		generate_kb_nav($user->lang['KB_VIEW_HISTORY']);
		
		$this->page_header($user->lang['KB_VIEW_HISTORY']);
		
		$template->set_filenames(array(
			'body'		=> 'kb/view_history.html',
		));
		
		page_footer();
	}
	
	/**
	* Generate article request page, action is either view or list
	* These are supposed to be very simple and unfancy pages
	*/
	function generate_request_page()
	{
		global $db, $template, $user, $phpEx;
		global $config, $phpbb_root_path, $auth;
		
		if($this->action == 'view')
		{
			$request_id = request_var('r', 0);
			if(!$request_id)
			{
				trigger_error('KB_NO_REQUEST');
			}
			
			$sql = 'SELECT * 
					FROM ' . KB_REQ_TABLE . "
					WHERE request_id = $request_id";
			$result = $db->sql_query($sql);
			if(!$request_data = $db->sql_fetchrow($result))
			{
				trigger_error('KB_NO_REQUEST');
			}
			$db->sql_freeresult($result);
			
			// Instantiate BBCode if need be
			$bbcode_bitfield = $request_data['bbcode_bitfield'];
			if ($bbcode_bitfield !== '')
			{
				$this->finclude('bbcode', 'c:bbcode');
				$bbcode = new bbcode(base64_encode($bbcode_bitfield));
			}
			
			// Parse the message and subject
			$message = censor_text($request_data['request_text']);
			
			// Second parse bbcode here
			if ($request_data['bbcode_bitfield'])
			{
				$bbcode->bbcode_second_pass($message, $request_data['bbcode_uid'], $request_data['bbcode_bitfield']);
			}
		
			$message = bbcode_nl2br($message);
			$message = smiley_text($message);
		
			// Replace naughty words such as farty pants
			$request_data['request_title'] = censor_text($request_data['request_title']);
			
			// Check status and acceptance
			switch($request_data['request_status'])
			{
				// Has been added retrieve article and userdata
				case STATUS_ADDED:
					$title = '[' . $user->lang['STATUS_ADDED'] . '] ' . $request_data['request_title'];
					
					$sql = $db->sql_build_query('SELECT', array(
						'SELECT'	=> 'a.article_id, a.article_title, a.article_user_id, a.article_user_name, a.article_user_color',
						'FROM'		=> array(
							KB_TABLE => 'a'
						),
						'WHERE'		=> "a.article_id = {$request_data['article_id']} AND a.article_status = " . STATUS_APPROVED,
					));
					
					$result = $db->sql_query($sql);
					$article_data = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					
					$explain = sprintf($user->lang['STATUS_ADDED_EXPLAIN'], get_username_string('full', $article_data['article_user_id'], $article_data['article_user_name'], $article_data['article_user_color'], $article_data['article_user_name']), '<a href="' . kb_append_sid('article', array('id' => $article_data['article_id'], 'title' => $article_data['article_title'])) . '">' . censor_text($article_data['article_title']) . '</a>');
				break;
				
				// Has been accepted by someone, retrieve userdata
				case STATUS_PENDING:
					$title = '[' . $user->lang['STATUS_PENDING'] . '] ' . $request_data['request_title'];
					
					$sql = $db->sql_build_query('SELECT', array(
						'SELECT'	=> 'u.user_id, u.username, u.user_colour',
						'FROM'		=> array(
							USERS_TABLE => 'u'
						),
						'WHERE'		=> "u.user_id = {$request_data['request_accepted']}",
					));
					
					$result = $db->sql_query($sql);
					$user_data = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					
					$explain = sprintf($user->lang['STATUS_PENDING_EXPLAIN'], get_username_string('full', $user_data['user_id'], $user_data['username'], $user_data['user_colour'], $user_data['username']));
				break;
				
				case STATUS_REQUEST:
				default:
					$title = '[' . $user->lang['STATUS_REQUEST'] . '] ' . $request_data['request_title'];
					$take_on = (($user->data['is_registered']) ? sprintf($user->lang['STATUS_REQUEST_TAKE_ON'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=accept&amp;r=' . $request_id) . '">', '</a>'): '');
					$explain = $user->lang['STATUS_REQUEST_EXPLAIN'] . ' ' . $take_on;
				break;
			}
			
			$s_mod = (($auth->acl_get('u_kb_request') && $request_data['request_user_id'] == $user->data['user_id']) || $auth->acl_get('m_kb_req_edit')) ? true : false;
			
			// Nothing changes on new years day, I will be with you again oh template vars
			$template->assign_vars(array(
				'REQUEST_TITLE'		=> $title,
				'EXPLAIN'			=> $explain,
				'MESSAGE'			=> $message,
				'POST_AUTHOR_FULL'	=> get_username_string('full', $request_data['request_user_id'], $request_data['request_user_name'], $request_data['request_user_color'], $request_data['request_user_name']),
				'POST_DATE'			=> $user->format_date($request_data['request_time']),
				'S_IN_MAIN'			=> true,
				
				'U_EDIT'			=> ($s_mod) ? append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=edit&amp;r=' . $request_id) : '',
				'U_DELETE'			=> ($s_mod) ? append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=delete&amp;r=' . $request_id) : '',
				
				'EDIT_IMG' 			=> $user->img('icon_post_edit', 'KB_EDIT_REQUEST'),
				'DELETE_IMG' 		=> $user->img('icon_post_delete', 'KB_DELETE_REQUEST'),
			));
		}
		else
		{
			show_request_list(false, 15, $this->start);
		}
		
		// Build Navigation Links
		generate_kb_nav($user->lang['KB_REQUEST_' . strtoupper($this->action)]);
		
		// Output page ...
		$this->page_header($user->lang['KB_REQUEST_' . strtoupper($this->action)]);
		
		$template->set_filenames(array(
			'body' => 'kb/kb_request_' . strtolower($this->action) . '.html')
		);
		
		page_footer();
	}
	
	/**
	* Article requests are handled here
	*/
	function request_posting()
	{
		global $db, $template, $user, $auth;
		global $config, $phpbb_root_path, $phpEx;
		
		if (!$config['kb_request_list_enable'])
		{
			trigger_error('REQUEST_NOT_ENABLED');
		}
		
		$this->finclude(array('functions_posting', 'functions_display', 'message_parser'), array('f:generate_smilies', 'f:display_forums', 'c:message_parser'));
		$user->add_lang(array('posting', 'mcp', 'viewtopic'));
		
		$submit			= (isset($_POST['post'])) ? true : false;
		$error 			= array();
		$current_time 	= time();
		$request_id 	= request_var('r', 0);
		
		// We need to obtain basic category and post info
		// This function also passes the user unto the view and alterations functions
		switch($this->action)
		{
			case 'view':
			case 'list':
				$this->generate_request_page();
			break;
			
			case 'accept':
				if(!$request_id || !$user->data['is_registered'])
				{
					trigger_error('KB_NO_REQUEST');
				}
				
				if(confirm_box(true))
				{
					// Accept it
					$sql = 'UPDATE ' . KB_REQ_TABLE . '
							SET request_accepted = ' . $user->data['user_id'] . ', request_status = ' . STATUS_PENDING . '
							WHERE request_id = ' . $request_id;
					$db->sql_query($sql);
					
					$meta_info = append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=view&amp;r=' . $request_id);
					$message = $user->lang['REQUEST_ACCEPTED'] . '<br /><br />' . sprintf($user->lang['RETURN_REQ'], '<a href="' . $meta_info . '">', '</a>') . '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx") . '">', '</a>');
					meta_refresh(5, $meta_info);
					trigger_error($message);
				}
				else
				{
					// Show confirm box
					$s_hidden_fields = build_hidden_fields(array(
						'r'		=> $request_id
					));
					confirm_box(false, 'ACCEPT_REQUEST', $s_hidden_fields);
				}
				
				redirect(append_sid("{$phpbb_root_path}kb.$phpEx", "i=request&amp;action=view&amp;r=$request_id"));
			break;
			
			case 'add':
				$sql = '';
			break;
				
			case 'edit':
			case 'delete':
				if(!$request_id)
				{
					trigger_error('KB_NO_REQUEST');
				}
				
				$sql = 'SELECT r.*, u.username, u.user_colour
						FROM ' . KB_REQ_TABLE . ' r,  ' . USERS_TABLE . ' u
						WHERE u.user_id = r.request_user_id
						AND r.request_id = ' . $request_id;
			break;
		// add case for completing the article
		//this will need to mark topic complete and provide a link to new article
			default:
				$sql = '';
			break;
		}
		
		if($this->action != 'add')
		{
			if (!$sql)
			{
				trigger_error('NO_POST_MODE');
			}
			
			$result = $db->sql_query($sql);
			$request_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			if (!$request_data)
			{
				trigger_error('KB_NO_REQUEST');
			}
		}
		
		// Check permissions
		if ($user->data['is_bot'])
		{
			redirect(append_sid("{$phpbb_root_path}kb.$phpEx"));
		}
		
		// Permission to do the action asked?
		$is_authed = false;
		switch ($this->action)
		{
			case 'add':
				if ($auth->acl_get('u_kb_request'))
				{
					$is_authed = true;
				}
			break;
		
			case 'edit':
			case 'delete':
				if ($user->data['is_registered'] && $auth->acl_gets('u_kb_request', 'm_kb_edit_req'))
				{
					$is_authed = true;
				}
			break;
		}
		
		if (!$is_authed)
		{
			if ($user->data['is_registered'])
			{
				trigger_error('KB_USER_CANNOT_REQUEST_' . strtoupper($this->action));
			}
		
			$red_url = ($this->action == 'edit' || $this->action == 'delete') ? "&amp;r=$request_id" : '';
			login_box(append_sid("{$phpbb_root_path}kb.$phpEx", "i=request&amp;action=$this->action" . $red_url), $user->lang['KB_LOGIN_EXPLAIN_REQUEST_' . strtoupper($this->action)]);
		}
		
		// Handle delete mode...
		if ($this->action == 'delete')
		{
			request_delete($request_id);
			return;
		}
		
		if ($this->action == 'edit' && !$auth->acl_get('m_kb_edit_req'))
		{
			if ($user->data['user_id'] != $request_data['request_user_id'])
			{
				trigger_error('KB_USER_CANNOT_REQUEST_EDIT');
			}
		}
		
		$message_parser = new parse_message();
		if (isset($request_data['request_text']))
		{
			$message_parser->message = &$request_data['request_text'];
			unset($request_data['request_text']);
		}
		
		// Set some default variables
		$uninit = array('request_user_id' => $user->data['user_id'], 'request_title' => '', 'request_text' => '');
		foreach ($uninit as $var_name => $default_value)
		{
			if (!isset($request_data[$var_name]))
			{
				$request_data[$var_name] = $default_value;
			}
		}
		unset($uninit);
		
		// Do we want to edit our post ?
		if ($this->action == 'edit' && $request_data['bbcode_uid'])
		{
			$message_parser->bbcode_uid = $request_data['bbcode_uid'];
		}
		
		// HTML, BBCode, Smilies, Images and Flash status
		$bbcode_status	= ($config['kb_allow_bbcode']) ? true : false;
		$smilies_status	= ($bbcode_status && $config['kb_allow_smilies']) ? true : false;
		$img_status		= ($bbcode_status) ? true : false;
		$url_status		= ($config['kb_allow_post_links']) ? true : false;
																								 
		if ($submit)
		{
			$request_data['request_title']		= utf8_normalize_nfc(request_var('title', '', true));
			$message_parser->message			= utf8_normalize_nfc(request_var('message', '', true));
			
			// Grab md5 'checksum' of new message
			$message_md5 = md5($message_parser->message);
		
			// Check checksum ... don't re-parse message if the same
			$update_message = ($this->action != 'edit' || $message_md5 != $request_data['request_checksum'] || strlen($request_data['bbcode_uid']) < BBCODE_UID_LEN) ? true : false;
			
			// Parse message
			if ($update_message)
			{
				if (sizeof($message_parser->warn_msg))
				{
					$error[] = fix_error_vars('request', implode('<br />', $message_parser->warn_msg));
					$message_parser->warn_msg = array();
				}
		
				$message_parser->parse($bbcode_status, $url_status, $smilies_status, $img_status, false, true, $url_status);
			}
			else
			{
				$message_parser->bbcode_bitfield = $request_data['bbcode_bitfield'];
			}
			
			// check form
			if ($submit && !check_form_key('posting'))
			{
				$error[] = $user->lang['FORM_INVALID'];
			}
			
			// Parse subject
			if (utf8_clean_string($request_data['request_title']) === '' && ($this->action == 'add' || $this->action == 'edit'))
			{
				$error[] = $user->lang['REQUEST_EMPTY_TITLE'];
			}
			
			if (sizeof($message_parser->warn_msg))
			{
				$error[] = fix_error_vars('request', implode('<br />', $message_parser->warn_msg));
			}
			
			// DNSBL check
			if ($config['check_dnsbl'])
			{
				if (($dnsbl = $user->check_dnsbl('post')) !== false)
				{
					$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
				}
			}
			
			if (!sizeof($error))
			{
				$data = array(
					'request_id'			=> (int) $request_id,
					'article_id'			=> (int) ($this->action == 'edit') ? $request_data['article_id'] : 0,
					'request_accepted'		=> (int) ($this->action == 'edit') ? $request_data['request_accepted'] : 0,
					'request_title'			=> $request_data['request_title'],
					'request_text'			=> $message_parser->message,
					'message_md5'			=> (string) $message_md5,
					'request_status'		=> (int) ($this->action == 'edit') ? $request_data['request_status'] : 0,
					'request_user_id'		=> (int) ($this->action == 'edit') ? $request_data['request_user_id'] : $user->data['user_id'],
					'request_user_name'		=> ($this->action == 'edit') ? $request_data['username'] : ($user->data['is_registered'] ? $user->data['username'] : utf8_normalize_nfc(request_var('guest_username', $user->data['username'], true))),
					'request_user_color'	=> ($this->action == 'edit') ? $request_data['user_colour'] : $user->data['user_colour'],
					'request_time'			=> $current_time,
					'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
					'bbcode_uid'			=> $message_parser->bbcode_uid,
				);
	
				$request_id = request_submit($this->action, $data, $update_message);
				
				$redirect_url = append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=view&amp;r=' . $request_id);
				$message = ($this->action == 'edit') ? 'EDITED' : 'ADDED';
				$message = sprintf($user->lang['KB_REQUEST_' . $message], '<a href="' . $redirect_url . '">', '</a>');
				$message .= '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid($phpbb_root_path . 'kb.' . $phpEx) . '">', '</a>');
				meta_refresh(5, $redirect_url);
				trigger_error($message);
			}
		}
		
		$request_data['bbcode_uid'] = $message_parser->bbcode_uid;
		$message_parser->decode_message($request_data['bbcode_uid']);
		$request_data['request_text'] = $message_parser->message;
		
		// Generate smiley listing
		kb_generate_smilies('inline');
		
		// Page title & action URL, include session_id for security purpose
		$s_action = append_sid("{$phpbb_root_path}kb.$phpEx", "i=request&amp;action=$this->action", true, $user->session_id);
		$s_action .= ($request_id) ? "&amp;r=$request_id" : '';
		
		switch ($this->action)
		{
			case 'add':
				$page_title = $user->lang['KB_ADD_REQUEST'];
			break;
		
			case 'delete':
			case 'edit':
				$page_title = $user->lang['KB_EDIT_REQUEST'];
			break;
		}
		
		// Build Navigation Links
		generate_kb_nav($page_title, array());
		
		$s_hidden_fields = '';
		$form_enctype = '';
		add_form_key('posting');
		
		// Start assigning vars for main posting page ...
		$template->assign_vars(array(
			'L_POST_A'				=> $page_title,

			'REQUEST_TITLE'			=> $request_data['request_title'],
			'TEXT_MESSAGE'			=> $request_data['request_text'],
			'BBCODE_STATUS'			=> ($bbcode_status) ? sprintf($user->lang['BBCODE_IS_ON'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>') : sprintf($user->lang['BBCODE_IS_OFF'], '<a href="' . append_sid("{$phpbb_root_path}faq.$phpEx", 'mode=bbcode') . '">', '</a>'),
			'IMG_STATUS'			=> ($img_status) ? $user->lang['IMAGES_ARE_ON'] : $user->lang['IMAGES_ARE_OFF'],
			'FLASH_STATUS'			=> $user->lang['FLASH_IS_OFF'],
			'SMILIES_STATUS'		=> ($smilies_status) ? $user->lang['SMILIES_ARE_ON'] : $user->lang['SMILIES_ARE_OFF'],
			'URL_STATUS'			=> ($bbcode_status && $url_status) ? $user->lang['URL_IS_ON'] : $user->lang['URL_IS_OFF'],
			'ERROR'					=> (sizeof($error)) ? implode('<br />', $error) : '',		
			
			'S_FORM_ENCTYPE'		=> $form_enctype,
			'S_POST_ACTION'			=> $s_action,
			'S_HIDDEN_FIELDS'		=> $s_hidden_fields,
			'S_BBCODE_ALLOWED'		=> $bbcode_status,
			'S_SMILIES_ALLOWED'		=> $smilies_status,
			'S_SIG_ALLOWED'			=> ($config['kb_allow_sig'] && $user->data['is_registered']) ? true : false,
			'S_LINKS_ALLOWED'		=> $url_status,
			'S_BBCODE_IMG'			=> $img_status,
			'S_BBCODE_URL'			=> $url_status,
			'S_BBCODE_FLASH'		=> false,
			'S_SHOW_USERNAME_FIELD'	=> (!$user->data['is_registered'] && $this->action == 'add') ? true : false,
		));
		
		// Build custom bbcodes array
		display_custom_bbcodes();
		
		// Output page ...
		$this->page_header($page_title);
		
		$template->set_filenames(array(
			'body' => 'kb/post_request.html')
		);
		
		page_footer();
	}
	
	/**
	* Show popup for notification on article
	*/
	function show_notify_popup()
	{
		global $db, $user, $template, $config;
		global $phpbb_root_path, $phpEx;
		
		if(!$this->article_id || !$user->data['is_registered'])
		{
			trigger_error('NO_ARTICLE');
		}
		
		$sql = 'SELECT article_title
				FROM ' . KB_TABLE . '
				WHERE article_id = ' . $this->article_id . ' 
				AND article_status = ' . STATUS_APPROVED;
		$result = $db->sql_query($sql);
		
		if(!$article_data = $db->sql_fetchrow($result))
		{
			trigger_error('NO_ARTICLE');
		}
		$db->sql_freeresult($result);
		
		$template->assign_vars(array(
			'MESSAGE'			=> sprintf($user->lang['KB_NOTIFY_POPUP_EXPLAIN'], $article_data['article_title']),
			'S_NOT_LOGGED_IN'	=> false,
			'CLICK_TO_VIEW'		=> sprintf($user->lang['CLICK_VIEW_ARTICLE'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", 'a=' . $this->article_id) . '" onclick="jump_to_inbox(this.href); return false;">', '</a>'),
			'U_INBOX'			=> kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_data['article_title'])),
			'UA_INBOX'			=> ($config['kb_seo']) ? kb_append_sid('article', array('id' => $this->article_id, 'title' => $article_data['article_title'])) : append_sid("{$phpbb_root_path}kb.$phpEx", 'a=' . $this->article_id, false))
		);
		
		// Clear popup notification from db
		$sql = 'UPDATE ' . KB_TRACK_TABLE . '
				SET subscribed = 1
				WHERE user_id = ' . $user->data['user_id'] . '
				AND article_id = ' . $this->article_id;
		$db->sql_query($sql);
		
		$this->page_header($user->lang['KB_NOTIFY_POPUP']);
		
		$template->set_filenames(array(
			'body'		=> 'ucp_pm_popup.html',
		));
		
		page_footer();
	}
	
	/**
	* Parse Attachments
	* Parses attachments for the kb
	* Modified phpBB function
	*/
	function parse_attachments($form_name, $cat_id, $submit, $preview, $refresh, $message, $mode)
	{
		global $config, $auth, $user, $phpbb_root_path, $phpEx, $db;

		$error = array();
		$mode = $this->$mode;
		$num_attachments = sizeof($this->attachment_data);
		$this->filename_data['filecomment'] = utf8_normalize_nfc(request_var('filecomment', '', true));
		$upload_file = (isset($_FILES[$form_name]) && $_FILES[$form_name]['name'] != 'none' && trim($_FILES[$form_name]['name'])) ? true : false;

		$add_file		= (isset($_POST['add_file'])) ? true : false;
		$delete_file	= (isset($_POST['delete_file'])) ? true : false;

		// First of all adjust comments if changed
		$actual_comment_list = utf8_normalize_nfc(request_var('comment_list', array(''), true));

		foreach ($actual_comment_list as $comment_key => $comment)
		{
			if (!isset($this->attachment_data[$comment_key]))
			{
				continue;
			}

			if ($this->attachment_data[$comment_key]['attach_comment'] != $actual_comment_list[$comment_key])
			{
				$this->attachment_data[$comment_key]['attach_comment'] = $actual_comment_list[$comment_key];
			}
		}

		$cfg = array();
		$cfg['max_attachments'] = $config['max_attachments'];

		if ($submit && in_array($mode, array('add', 'edit')) && $upload_file)
		{
			if ($num_attachments < $cfg['max_attachments'] || $auth->acl_get('a_') || $auth->acl_get('m_kb_view'))
			{
				$filedata = kb_upload_attachment($form_name, $cat_id, false, '');
				$error = $filedata['error'];

				if ($filedata['post_attach'] && !sizeof($error))
				{
					$sql_ary = array(
						'physical_filename'	=> $filedata['physical_filename'],
						'attach_comment'	=> $this->filename_data['filecomment'],
						'real_filename'		=> $filedata['real_filename'],
						'extension'			=> $filedata['extension'],
						'mimetype'			=> $filedata['mimetype'],
						'filesize'			=> $filedata['filesize'],
						'filetime'			=> $filedata['filetime'],
						'thumbnail'			=> $filedata['thumbnail'],
						'is_orphan'			=> 1,
						'poster_id'			=> $user->data['user_id'],
					);

					$db->sql_query('INSERT INTO ' . KB_ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

					$new_entry = array(
						'attach_id'		=> $db->sql_nextid(),
						'is_orphan'		=> 1,
						'real_filename'	=> $filedata['real_filename'],
						'attach_comment'=> $this->filename_data['filecomment'],
					);

					$this->attachment_data = array_merge(array(0 => $new_entry), $this->attachment_data);
					$message_parser->message = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "'[attachment='.(\\1 + 1).']\\2[/attachment]'", $message_parser->message);

					$this->filename_data['filecomment'] = '';

					// This Variable is set to false here, because Attachments are entered into the
					// Database in two modes, one if the id_list is 0 and the second one if post_attach is true
					// Since post_attach is automatically switched to true if an Attachment got added to the filesystem,
					// but we are assigning an id of 0 here, we have to reset the post_attach variable to false.
					//
					// This is very relevant, because it could happen that the post got not submitted, but we do not
					// know this circumstance here. We could be at the posting page or we could be redirected to the entered
					// post. :)
					$filedata['post_attach'] = false;
				}
			}
			else
			{
				$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $cfg['max_attachments']);
			}
		}

		if ($preview || $refresh || sizeof($error))
		{
			// Perform actions on temporary attachments
			if ($delete_file)
			{
				$this->finclude('functions_admin', 'f:phpbb_unlink');

				$index = array_keys(request_var('delete_file', array(0 => 0)));
				$index = (!empty($index)) ? $index[0] : false;

				if ($index !== false && !empty($this->attachment_data[$index]))
				{
					// delete selected attachment
					if ($this->attachment_data[$index]['is_orphan'])
					{
						$sql = 'SELECT attach_id, physical_filename, thumbnail
							FROM ' . KB_ATTACHMENTS_TABLE . '
							WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id'] . '
								AND is_orphan = 1
								AND poster_id = ' . $user->data['user_id'];
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if ($row)
						{
							phpbb_unlink($row['physical_filename'], 'file');

							if ($row['thumbnail'])
							{
								phpbb_unlink($row['physical_filename'], 'thumbnail');
							}

							$db->sql_query('DELETE FROM ' . KB_ATTACHMENTS_TABLE . ' WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id']);
						}
					}
					else
					{
						kb_delete_attachments('attach', array(intval($this->attachment_data[$index]['attach_id'])));
					}

					unset($this->attachment_data[$index]);
					$message = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "(\\1 == \$index) ? '' : ((\\1 > \$index) ? '[attachment=' . (\\1 - 1) . ']\\2[/attachment]' : '\\0')", $message);

					// Reindex Array
					$this->attachment_data = array_values($this->attachment_data);
				}
			}
			else if (($add_file || $preview) && $upload_file)
			{
				if ($num_attachments < $cfg['max_attachments'] || $auth->acl_gets('m_', 'a_'))
				{
					$filedata = kb_upload_attachment($form_name, $cat_id, false, '');
					$error = array_merge($error, $filedata['error']);

					if (!sizeof($error))
					{
						$sql_ary = array(
							'physical_filename'	=> $filedata['physical_filename'],
							'attach_comment'	=> $this->filename_data['filecomment'],
							'real_filename'		=> $filedata['real_filename'],
							'extension'			=> $filedata['extension'],
							'mimetype'			=> $filedata['mimetype'],
							'filesize'			=> $filedata['filesize'],
							'filetime'			=> $filedata['filetime'],
							'thumbnail'			=> $filedata['thumbnail'],
							'is_orphan'			=> 1,
							'poster_id'			=> $user->data['user_id'],
						);

						$db->sql_query('INSERT INTO ' . KB_ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

						$new_entry = array(
							'attach_id'		=> $db->sql_nextid(),
							'is_orphan'		=> 1,
							'real_filename'	=> $filedata['real_filename'],
							'attach_comment'=> $this->filename_data['filecomment'],
						);

						$this->attachment_data = array_merge(array(0 => $new_entry), $this->attachment_data);
						$message = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "'[attachment='.(\\1 + 1).']\\2[/attachment]'", $message);
						$this->filename_data['filecomment'] = '';
					}
				}
				else
				{
					$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $cfg['max_attachments']);
				}
			}
		}

		foreach ($error as $error_msg)
		{
			$this->warn_msg[] = $error_msg;
		}
		
		return $message;
	}
	
	/**
	* Article attachment function
	* The kb needs its own functions to handle attachment, hopefully we can reuse the filespec class
	* Modified phpBB function
	*/
	function get_submitted_attachment_data($check_user_id = false)
	{
		global $user, $db, $phpbb_root_path, $phpEx, $config;

		$this->filename_data['filecomment'] = utf8_normalize_nfc(request_var('filecomment', '', true));
		$attachment_data = (isset($_POST['attachment_data'])) ? $_POST['attachment_data'] : array();
		$this->attachment_data = array();

		$check_user_id = ($check_user_id === false) ? $user->data['user_id'] : $check_user_id;

		if (!sizeof($attachment_data))
		{
			return;
		}

		$not_orphan = $orphan = array();

		foreach ($attachment_data as $pos => $var_ary)
		{
			if ($var_ary['is_orphan'])
			{
				$orphan[(int) $var_ary['attach_id']] = $pos;
			}
			else
			{
				$not_orphan[(int) $var_ary['attach_id']] = $pos;
			}
		}

		// Regenerate already posted attachments
		if (sizeof($not_orphan))
		{
			// Get the attachment data, based on the poster id...
			$sql = 'SELECT attach_id, is_orphan, real_filename, attach_comment
				FROM ' . KB_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($not_orphan)) . '
					AND poster_id = ' . $check_user_id;
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$pos = $not_orphan[$row['attach_id']];
				$this->attachment_data[$pos] = $row;
				set_var($this->attachment_data[$pos]['attach_comment'], $_POST['attachment_data'][$pos]['attach_comment'], 'string', true);

				unset($not_orphan[$row['attach_id']]);
			}
			$db->sql_freeresult($result);
		}

		if (sizeof($not_orphan))
		{
			trigger_error('NO_ACCESS_ATTACHMENT', E_USER_ERROR);
		}

		// Regenerate newly uploaded attachments
		if (sizeof($orphan))
		{
			$sql = 'SELECT attach_id, is_orphan, real_filename, attach_comment
				FROM ' . KB_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($orphan)) . '
					AND poster_id = ' . $user->data['user_id'] . '
					AND is_orphan = 1';
			$result = $db->sql_query($sql);
			
			while ($row = $db->sql_fetchrow($result))
			{
				$pos = $orphan[$row['attach_id']];
				$this->attachment_data[$pos] = $row;
				set_var($this->attachment_data[$pos]['attach_comment'], $_POST['attachment_data'][$pos]['attach_comment'], 'string', true);

				unset($orphan[$row['attach_id']]);
			}
			$db->sql_freeresult($result);
		}

		if (sizeof($orphan))
		{
			trigger_error('NO_ACCESS_ATTACHMENT', E_USER_ERROR);
		}

		ksort($this->attachment_data);
	}
}

?>