<?php
/**
 * MyBB 1.0
 * Copyright � 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

function postify($message, $allowhtml="no", $allowmycode="yes", $allowsmilies="yes", $allowimgcode="yes", $archive=0)
{
	global $db, $mybb, $theme, $plugins;

	$message = dobadwords($message);
	if($allowhtml != "yes")
	{
		$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // fix & but allow unicode
		$message = str_replace("<","&lt;",$message);
		$message = str_replace(">","&gt;",$message);
	}
	if($allowsmilies != "no")
	{
		if($archive == "yes")
		{
			$message = dosmilies($message, $mybb->settings['bburl']);
		}
		else
		{
			$message = dosmilies($message);
		}
	}
	if($allowmycode != "no")
	{
		$message = domycode($message, $allowimgcode);
	}
	if($allowimgcode != "yes")
	{
		$message = str_replace("<img","&lt;img",$message);
	}
	$message = $plugins->run_hooks("parse_message", $message);
	$message = nl2br($message);
	return $message;
}

function domycode($message, $allowimgcode="yes")
{
	global $theme, $settings;
	$message = fixjavascript($message);
	$pattern = array("#\[b\](.*?)\[/b\]#si",
					 "#\[i\](.*?)\[/i\]#si",
					 "#\[u\](.*?)\[/u\]#si",
					 "#\[s\](.*?)\[/s\]#si",
					 "#\(c\)#i",
					 "#\(tm\)#i",
					 "#\(r\)#i",
					 "#\[url\]([a-z]+?://)([^\r\n\"\[<]+?)\[/url\]#sei",
					 "#\[url\]([^\r\n\"\[<]+?)\[/url\]#ei",
					 "#\[url=([a-z]+?://)([^\r\n\"\[<]+?)\](.+?)\[/url\]#esi",
					 "#\[url=([^\r\n\"\[<]+?)\](.+?)\[/url\]#esi",
					 "#\[email\](.*?)\[/email\]#ei",
					 "#\[email=(.*?)\](.*?)\[/email\]#ei",
					 "#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#si",
					 "#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#si",
					 "#\[font=([a-z ]+?)\](.+?)\[/font\]#si",
					 "#\[align=(left|center|right|justify)\](.*?)\[/align\]#si");
	$replace = array("<b>$1</b>",
					 "<i>$1</i>",
					 "<u>$1</u>",
					 "<strike>$1</strike>",
				     "&copy;",
					 "&#153;",
					 "&reg;",
					 "doshorturl(\"$1$2\")",
					 "doshorturl(\"$1\")",
					 "doshorturl(\"$1$2\", \"$3\")",
					 "doshorturl(\"$1\", \"$2\")",
					 "doemailurl(\"$1\")",
					 "doemailurl(\"$1\", \"$2\")",
					 "<font color=\"$1\">$2</font>",
					 "<font size=\"$1\">$2</font>",
					 "<font face=\"$1\">$2</font>",
					 "<p align=\"$1\">$2</p>");
	$message = preg_replace($pattern, $replace, $message);
	while(preg_match("#\[list\](.*?)\[/list\]#esi", $message))
	{
		$message = preg_replace("#\[list\](.*?)\[/list\]#esi", "dolist('$1')", $message);
	}
	while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", $message))
	{
		$message = preg_replace("#\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "dolist('$2', '$1')", $message);
	}

	if($allowimgcode)
	{
		$message = preg_replace("#\[img\]([a-z]+?://){1}(.+?)\[/img\]#i", "<img src=\"$1$2\" border=\"0\" alt=\"\" />", $message);
		$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3})\]([a-z]+?://){1}(.+?)\[/img\]#i", "<img src=\"$3$4\" border=\"0\" width=\"$1\" height=\"$2\" alt=\"\" />", $message);
	}
	$message = doquotes($message);
	$message = docode($message);
	$message = doautourl($message);
	$message = str_replace("[hr]", "<hr size=\"1\" />", $message);
	return $message;
}

function dolist($message, $type="")
{
	$message = str_replace('\"', '"', $message);
	$message = preg_replace("#\[\*\]#", "</li><li>", $message);
	$message .= "</li>";

	if($type)
	{
		$list = "<ol type=\"$type\">$message</ol>";
	}
	else
	{
		$list = "<ul>$message</ul>";
	}
	$list = preg_replace("#<(ol type=\"$type\"|ul)>\s*</li>#", "<$1>", $list);
	return $list;
}

function domecode($message, $username)
{
	global $lang;
	$message = preg_replace('#^/me (.*)$#im', "<font color=\"red\">* $username \\1</font>", $message);
	$message = preg_replace('#^/slap (.*)#iem', "'<font color=\"red\">* $username $lang->slaps '.str_replace('<br />', '', '\\1').' $lang->with_trout</font><br />'", $message);
	return $message;
}

function fixjavascript($message)
{
	$message = preg_replace("#javascript:#i", "java script:", $message);
	// this patch provided by Ryan (try to remove XSS Cross-site scripting issues).
	$message = preg_replace("#(a)(lert)#ie", "'&#'.ord($1).';$2'", $message);
	$message = preg_replace("#onmouseover#i", "&#111;nmouseover", $message);
	$message = preg_replace("#onmouseout#i", "&#111;nmouseout", $message);
	$message = preg_replace("#onclick#i", "&#111;nclick", $message);
	$message = preg_replace("#onload#i", "&#111;nload", $message);
	$message = eregi_replace("#onsubmit#i", "&#111;nsubmit", $message);
	return $message;
}

function dobadwords($message)
{
	global $db, $badwordcache, $cache;

	if(!$badwordcache)
	{
		$badwordcache = $cache->read("badwords");
	}

	if(is_array($badwordcache)) {
		reset($badwordcache);
		foreach($badwordcache as $bid => $badword)
		{
			if(!$badword['replacement']) $badword['replacement'] = "*****";
			$badword['badword'] = str_replace("\\", "\\\\", $badword['badword']);
			$message = preg_replace("#".$badword['badword']."#i", $badword['replacement'], $message);
		}
	}
	return $message;
}

function dosmilies($message, $url="")
{
	global $db, $smiliecache, $cache;

	if($url != "")
	{
		if(substr($url, strlen($url) -1) != "/")
		{
			$url = $url."/";
		}
	}
	
	$smiliecache = $cache->read("smilies");
	if(is_array($smiliecache))
	{
		reset($smiliecache);
		foreach($smiliecache as $sid => $smilie)
		{
			$message = str_replace($smilie['find'], "<img src=\"".$url.$smilie['image']."\" align=\"middle\" border=\"0\" alt=\"".$smilie['name']."\" />", $message);
		}
	}
	return $message;
}

function doautourl($message)
{
	$message = " ".$message;
	$message = preg_replace("#([\s\(\)])(https?|ftp|news){1}://([\w\-]+\.([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".doshorturl(\"$2://$3\")", $message);
	$message = preg_replace("#([\s\(\)])(www|ftp)\.(([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".doshorturl(\"$2.$3\", \"$2.$3\")", $message);
	$message = substr($message, 1);
	return $message;
}

function doshorturl($url, $name="")
{
	$fullurl = $url;
	// attempt to make a bit of sense out of their url if they dont type it properly
	if(strpos($url, "www.") === 0)
	{
		$fullurl = "http://".$fullurl;
	}
	if(strpos($url, "ftp.") === 0)
	{
		$fullurl = "ftp://".$fullurl;
	}
	if(!$name)
	{
		$name = $url;
	}
	$name = stripslashes($name);
	$url = stripslashes($url);
	$fullurl = stripslashes($fullurl);
	if($name == $url)
	{
		if(strlen($url) > 55)
		{
			$name = substr($url, 0, 40)."...".substr($url, -10);
		}
	}
	$link = "<a href=\"$fullurl\" target=\"_blank\">$name</a>";
	return $link;
}

function doemailurl($email, $name="") {
	if(!$name)
	{
		$name = $email;
	}
	if(preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email))
	{
		return "<a href=\"mailto:$email\">".$name."</a>";
	}
}


function doquotes($message)
{
	global $lang;
	
	// user sanity check
	$pattern = array("#\[quote=(?:&quot;|\"|')?(.*?)[\"']?(?:&quot;|\"|')?\](.*?)\[\/quote\]#si",
					 "#\[quote\](.*?)\[\/quote\]#si");
	
	$replace = array("</p><div class=\"quote_header\">$1 $lang->wrote</div><div class=\"quote_body\">$2</div><p>",
					 "</p><div class=\"quote_header\">$lang->quote</div><div class=\"quote_body\">$1</div><p>\n");
	
	while (preg_match($pattern[0], $message) or preg_match($pattern[1], $message))
	{
		$message = preg_replace($pattern, $replace, $message);
	}
	$message = str_replace("<div class=\"quote_body\"><br />", "<div class=\"quote_body\">", $message);
	$message = str_replace("<br /></div>", "</div>", $message);
	return $message;
}

function docode($message)
{
	global $lang;
	
	// user sanity check
	$m2 = strtolower($message);
	//$message = str_replace("[php]", "[code]", $message);
	//$message = str_replace("[/php]", "[/code]", $message);
	$opencount = substr_count($m2, "[code]");
	$closedcount = substr_count($m2, "[/code]");
	if($opencount > $closedcount)
	{
		$limit = $closedcount;
	}
	elseif($closedcount > $opencount)
	{
		$limit = $opencount;
	}
	else
	{
		$limit = -1;
	}
	$pattern = array("#\[code\](.*?)#si",
					 "#\[\/code\]#si");

	$replace = array("</p><div class=\"code_header\">$lang->code</div><div class=\"code_body\">",
					 "</div><p>\n");

	$message = preg_replace($pattern, $replace, $message, $limit);
	$message = str_replace("<div class=\"code_body\"><br />", "<div class=\"code_body\">", $message);
	$message = str_replace("<br /></div>", "</div>", $message);
	//$message = preg_replace("#\[php\](.+?)\[/php\]#ies", "dophpcode('\\1')", $message);
	while(preg_match("#\[php\](.+?)\[/php\]#ies", $message, $matches))
	{
		$message = str_replace($matches[0], dophpcode($matches[1]), $message);
	}
	return $message;
}

// New function...
function dophpcode($str)
{
	global $lang;
	
	$str = str_replace('&lt;', '<', $str);
	$str = str_replace('&gt;', '>', $str);
	$str = str_replace('&amp;', '&', $str);
	$str = str_replace("\n", '', $str);
	$original = $str;
	
	if(preg_match("/\A[\s]*\<\?/", $str) === 0)
	{
		$str = "<?php\n".$str;
	}

	if(preg_match("/\A[\s]*\>\?/", strrev($str)) === 0)
	{
		$str = $str."\n?>";
	}
	
	if(substr(phpversion(), 0, 1) >= 4)
	{
		ob_start();
		@highlight_string($str);
		$code = ob_get_contents();
		ob_end_clean();
	}
	else
	{
		$code = $str;
	}

	// Get rid of extra line break at end
	$code = preg_replace("#</font>\n</code>#is", "</font></code>", $code);
	$code = preg_replace("#</font>\n</font></code>#is", "</font></code>", $code);
	
	if(preg_match("/\A[\s]*\<\?/", $original) === 0)
	{
		$code = substr_replace($code, "", strpos($code, "&lt;?php"), strlen("&lt;?php"));
		$code = strrev(substr_replace(strrev($code), "", strpos(strrev($code), strrev("?&gt;")), strlen("?&gt;")));
		$code = str_replace('<br />', '', $code);
	}
	
	// Get rid of other useless code
	//$code = preg_replace("#<code><font color=\"(.+?)\">\n#is", "<code>", $code);
	$code = str_replace('<code><font color=\"#000000"><br />\n', '<code>', $code);
	$code = str_replace('<font color="#0000CC"></font>', '', $code);
	
	// Send back the code all nice and pretty
	return "</p><div class=\"code_header\">$lang->php_code</div><div class=\"code_body\">".$code."</div><p>";
}

function makepostbit($post, $pmprevann=0)
{
	global $db, $altbg, $theme, $settings, $mybb, $mybbuser, $postcounter, $titlescache, $page, $templates, $forumpermissions, $attachcache, $lang, $ismod, $inlinecookie, $inlinecount, $HTTP_COOKIE_VARS, $groupscache, $fid, $plugins;
	if($post['visible'] == 0 && $pmprevann == 0)
	{
		$altbg = "trow_shaded";
	}
	elseif($altbg == "trow1")
	{
		$altbg = "trow2";
	}
	else
	{
		$altbg = "trow1";
	}

	if(!$pmprevann)
	{ // This messgae is neither a pm nor announcement, its a post
		global $forum, $thread, $tid;
		$oldforum = $forum;
	}
	elseif($pmprevann == 1)
	{ // Set the bbcode/smilie parsing option based on the settings as this is a PM
		global $message, $pmid;
		$forum['allowhtml'] = $mybb->settings['pmsallowhtml'];
		$forum['allowmycode'] = $mybb->settings['pmsallowmycode'];
		$forum['allowsmilies'] = $mybb->settings['pmsallowsmilies'];
		$forum['allowimgcode'] = $mybb->settings['pmsallowimgcode'];
	}
	elseif($pmprevann == 2)
	{ // This message is an announcement
		global $announcementarray, $message;
		$forum['allowhtml'] = $announcementarray['allowhtml'];
		$forum['allowmycode'] = $announcementarray['allowmycode'];
		$forum['allowsmilies'] = $announcementarray['allowsmilies'];
		$forum['allowimgcode'] = 'yes';
	}
	if(!$postcounter)
	{ // Used to show the # of the post
		if($page > 1)
		{
			$postcounter = $mybb->settings['postsperpage']*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
	}
	if(!$altbg)
	{ // Define the alternate background colour if this is the first post
		$altbg = "trow1";
	}
	$postcounter++;

	// Format the post date and time using mydate
	$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
	$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);

	// Dont want any little 'nasties' in the subject
	$post['subject'] = htmlspecialchars_uni(dobadwords($post['subject']));

	$post['author'] = $post['uid'];

	// Get the usergroup
	if($post['userusername'])
	{
		if(!$post['displaygroup'])
		{
			$post['displaygroup'] = $post['usergroup'];
		}
		$usergroup = $groupscache[$post['displaygroup']];
	}
	else
	{
		$usergroup = $groupscache[1];
	}
	
	if(!is_array($titlescache))
	{
		// Get user titles (i guess we should improve this, maybe in version3.
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles ORDER BY posts DESC");
		while($usertitle = $db->fetch_array($query))
		{
			$titlescache[$usertitle['posts']] = $usertitle;
		}
		unset($usertitle);
	}
	
	// Work out the usergroup/title stuff
	if(!empty($usergroup['image']))
	{
		if(!empty($mybb->user['language']))
		{
			$language = $mybb->user['language'];
		}
		else
		{
			$language = $mybb->settings['bblanguage'];
		}
		$usergroup['image'] = str_replace("{lang}", $language, $usergroup['image']);
		eval("\$groupimage = \"".$templates->get("postbit_groupimage")."\";");
	}

	if($post['userusername'])
	{ // This post was made by a registered user

		$post['username'] = $post['userusername'];

		if($usergroup['usertitle'] != "")
		{
			$usertitle = $usergroup['usertitle'];
			$stars = $usergroup['stars'];
		}
		elseif(is_array($titlescache))
		{
			reset($titlescache);
			foreach($titlescache as $key => $titleinfo)
			{
				if($post['postnum'] >= $key)
				{
					$usertitle = $titleinfo['title'];
					$stars = $titleinfo['stars'];
					$starimage = $titleinfo['starimage'];
					break;
				}
			}
		}
		
		if(!$starimage)
		{
			$starimage = $usergroup['starimage'];
		}
		for($i = 0; $i < $stars; $i++)
		{
			$userstars .= "<img src=\"$starimage\" border=\"0\" alt=\"*\" />";
		}
		if($userstars && $starimage && $stars)
		{
			$userstars .= "<br />";
		}
		if($post['usertitle'] != "")
		{
			$usertitle = $post['usertitle'];
		}
		$post['postnum'] = mynumberformat($post['postnum']);
		
		// Determine the status to show for the user (Online/Offline/Away)
		$timecut = time() - $mybb->settings['wolcutoff'];
		if($post['lastactive'] > $timecut && ($post['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes") && $post['lastvisit'] != $post['lastactive'])
		{
			eval("\$onlinestatus = \"".$templates->get("postbit_online")."\";");
		}
		else
		{
			if($post['away'] == "yes" && $mybb->settings['allowaway'] != "no")
			{
				eval("\$onlinestatus = \"".$templates->get("postbit_away")."\";");
			}
			else
			{
				eval("\$onlinestatus = \"".$templates->get("postbit_offline")."\";");
			}
		}

		if($post['avatar'] != "" && $mybb->user['showavatars'] != "no")
		{
			$post['avatar'] = htmlspecialchars_uni($post['avatar']);
			eval("\$avatar = \"".$templates->get("postbit_avatar")."\";");
		}
		else
		{
			$avatar = "";
		}
		eval("\$profile = \"".$templates->get("postbit_profile")."\";");
		eval("\$find = \"".$templates->get("postbit_find")."\";");
		eval("\$pm = \"".$templates->get("postbit_pm")."\";");
		if($post['website'] != "")
		{
			$post['website'] = htmlspecialchars_uni($post['website']);
			eval("\$www = \"".$templates->get("postbit_www")."\";");
		}
		else
		{
			$www = "";
		}
		if($post['hideemail'] == "no")
		{
			eval("\$email = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$email = "";
		}
		$regdate = mydate($mybb->settings['regdateformat'], $post['regdate']);

		// Work out the reputation this user has
		if($post['usereputationsystem'] != "no")
		{
			if($mybb->usergroup['cangivereputations'] == "yes")
			{
				if(!$pmprevann)
				{
					$neglink = "<a href=\"javascript:reputation(".$post['pid'].", 'n');\"><img src=\"".$theme['imgdir']."/rep_neg.gif\" border=\"0\"></a>";
					$poslink = "<a href=\"javascript:reputation(".$post['pid'].", 'p');\"><img src=\"".$theme['imgdir']."/rep_pos.gif\" border=\"0\"></a>";
				}
				else
				{
					$neglink = "";
					$poslink = "";
				}
			}
			$reputation = getreputation($post['reputation']);
			eval("\$replink = \"".$templates->get("postbit_reputation")."\";");
		}
	}
	else
	{ // Message was posted by a guest or an unknown user
		$post['username'] = $post['username'];
		if($usergroup['usertitle'])
		{
			$usertitle = $usergroup['usertitle'];
			$usergroup = $lang->na;
		}
		else
		{
			$usertitle = $lang->guest;
		}

	    $regdate = $lang->na;
	    $post['postnum'] = $lang->na;
		$profile = "";
		$email = "";
		$www = "";
		$signature = "";
		$pm = "";
		$find = "";
		$style = "";
		$onlinestatus = $lang->unknown;
		$repbit = "";
	}
	if(!$pmprevann)
	{
		if($post['edituid'] != "" && $post['edittime'] != "" && $post['editusername'] != "")
		{
			$editdate = mydate($mybb->settings['dateformat'], $post['edittime']);
			$edittime = mydate($mybb->settings['timeformat'], $post['edittime']);
			$editnote = sprintf($lang->postbit_edited, $editdate, $edittime);
			eval("\$editedmsg = \"".$templates->get("postbit_editedby")."\";");
		}
		eval("\$edit = \"".$templates->get("postbit_edit")."\";");
		// Quick Delete button
		if((ismod($fid, "candeleteposts") == "yes" || $mybb->user['uid'] == $post['uid']) && $mybb->user['uid'] != 0)
		{
			eval("\$quickdelete = \"".$templates->get("postbit_quickdelete")."\";");
		}
		// Inline moderation stuff
		if($ismod)
		{
			if(strstr($HTTP_COOKIE_VARS[$inlinecookie], "|".$post['pid']."|"))
			{
				$inlinecheck = "checked=\"checked\"";
				$inlinecount++;
			}
			else
			{
				$inlinecheck = "";
			}
			eval("\$inlinecheck = \"".$templates->get("postbit_inlinecheck")."\";");
			if($post['visible'] == 0)
			{
				$invisiblepost = 1;
			}
		}
		else
		{
			$inlinecheck = "";
		}
		eval("\$posturl = \"".$templates->get("postbit_posturl")."\";");
		eval("\$quote = \"".$templates->get("postbit_quote")."\";");
		if($mybb->user['uid'] != "0")
		{
			eval("\$report = \"".$templates->get("postbit_report")."\";");
		}

		if($mybb->settings['logip'] != "no")
		{
			if($mybb->settings['logip'] == "show")
			{
				eval("\$iplogged = \"".$templates->get("postbit_iplogged_show")."\";");
			}
			else if($mybb->settings['logip'] == "hide" && $ismod)
			{
				eval("\$iplogged = \"".$templates->get("postbit_iplogged_hiden")."\";");
			}
			else
			{
				$iplogged = "";
			}
		}
		else
		{
				$iplogged = "";
		}

	}
	if($post['smilieoff'] == "yes")
	{
		$allowsmilies = "no";
	}
	else
	{
		$allowsmilies = $forum['allowsmilies'];
	}
	$post['message'] = postify($post['message'], $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
	// do me code
	if($forum['allowmycode'] != "no")
	{
		$post['message'] = domecode($post['message'], $post['username']);
	}

	if(is_array($attachcache[$post['pid']]))
	{ // This post has 1 or more attachments
		$validationcount = 0;
		while(list($aid, $attachment) = each($attachcache[$post['pid']]))
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['name'] = htmlspecialchars_uni($attachment['name']);
				// Support for [attachment=id] code
				if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					if($attachment['thumbnail'] && $forumpermissions['candlattachments'] == "yes")
					{ // We have a thumbnail to show
						eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
					}
					elseif($attachment['thumbnailsm'] == "yes" && $forumpermissions['candlattachments'] == "yes")
					{ // Image is small enough to show
						eval("\$attbit = \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						$attachment['filesize'] = getfriendlysize($attachment['filesize']);
						$ext = getextention($attachment['filename']);
						$attachment['icon'] = getattachicon($ext);
						eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
					}
					$post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $post['message']);
				}
				else
				{
					if($attachment['thumbnail'] && $forumpermissions['candlattachments'] == "yes")
					{ // We have a thumbnail to show
						eval("\$thumblist .= \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
						if($tcount == 5)
						{
							$thumblist .= "<br />";
							$tcount = 0;
						}
						$tcount++;
					}
					elseif($attachment['thumbnailsm'] == "yes" && $forumpermissions['candlattachments'] == "yes")
					{ // Image is small enough to show
						eval("\$imagelist .= \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						$attachment['filesize'] = getfriendlysize($attachment['filesize']);
						$ext = getextention($attachment['filename']);
						$attachment['icon'] = getattachicon($ext);
						eval("\$attachmentlist .= \"".$templates->get("postbit_attachments_attachment")."\";");
					}
				}
			}
			else
			{
				$validationcount++;
			}
		}
		if($thumblist)
		{
			eval("\$attachedthumbs = \"".$templates->get("postbit_attachments_thumbnails")."\";");
		}
		if($imagelist)
		{
			eval("\$attachedimages = \"".$templates->get("postbit_attachments_images")."\";");
		}
		if($attachmentlist || $thumblist || $imagelist)
		{
			eval("\$attachments = \"".$templates->get("postbit_attachments")."\";");
		}
	}

	if($post['includesig'] != "no" && $post['username'] && $post['signature'] != "" && $mybb->user['showsigs'] != "no")
	{
		$post['signature'] = postify(stripslashes($post['signature']), $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		eval("\$signature = \"".$templates->get("postbit_signature")."\";");
	}

	if($post['iconpath'])
	{
		$icon = "<img src=\"".$post['iconpath']."\" alt=\"".$post['iconname']."\">&nbsp;";
	}
	else
	{
		$icon = "";
	}
	if(stripos($usergroup['namestyle'], "{username}") !== false)
	{
		$post['username'] = formatname($post['username'], $post['usergroup'], $post['displaygroup']); // Set the style for the username
	}
	if($pmprevann != 2)
	{
	}
	if(!$pmprevann)
	{
		eval("\$seperator = \"".$templates->get("postbit_seperator")."\";");
		$plugins->run_hooks("postbit");
	}
	elseif($pmprevann == 1)
	{
		$plugins->run_hooks("postbit_pm");
	}
	elseif($pmprevann == 2)
	{
		$plugins->run_hooks("postbit_announcement");
	}

	eval("\$postbit = \"".$templates->get("postbit")."\";");
	return $postbit;
}
?>