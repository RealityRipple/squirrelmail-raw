<?php

/**
* imap_messages.php
*
* Copyright (c) 1999-2004 The SquirrelMail Project Team
* Licensed under the GNU GPL. For full terms see the file COPYING.
*
* This implements functions that manipulate messages
* NOTE: Quite a few functions in this file are obsolete
*
* @version $Id$
* @package squirrelmail
* @subpackage imap
*/

/**
* Copies specified messages to specified folder
* @param int $imap_stream The resource ID for the IMAP connection
* @param string $start Beginning of range to copy
* @param string $end End of the range to copy
* @param string $mailbox Which box to copy to
* @deprecated This function is obsolete and should not be used
*/
function sqimap_messages_copy ($imap_stream, $start, $end, $mailbox) {
    $read = sqimap_run_command ($imap_stream, "COPY $start:$end " . sqimap_encode_mailbox_name($mailbox), true, $response, $message, TRUE);
}

/**
* copy a range of messages ($id) to another mailbox ($mailbox)
* @param int $imap_stream The resource ID for the IMAP socket
* @param string $id The list of messages to copy
* @param string $mailbox The destination to copy to
* @return void
*/
function sqimap_msgs_list_copy ($imap_stream, $id, $mailbox) {
    $msgs_id = sqimap_message_list_squisher($id);
    $read = sqimap_run_command ($imap_stream, "COPY $msgs_id " . sqimap_encode_mailbox_name($mailbox), true, $response, $message, TRUE);
}

/**
* move a range of messages ($id) to another mailbox. Deletes the originals.
* @param int $imap_stream The resource ID for the IMAP socket
* @param string $id The list of messages to move
* @param string $mailbox The destination to move to
* @return void
*/
function sqimap_msgs_list_move ($imap_stream, $id, $mailbox) {
    $msgs_id = sqimap_message_list_squisher($id);
    $read = sqimap_run_command ($imap_stream, "COPY $msgs_id " . sqimap_encode_mailbox_name($mailbox), true, $response, $message, TRUE);
    $read = sqimap_run_command ($imap_stream, "STORE $msgs_id +FLAGS (\\Deleted)", true, $response,$message, TRUE);
}


/**
* Deletes specified messages and moves them to trash if possible
* @deprecated This function is obsolete and should no longer be used
* @param int $imap_steam The resource ID for the IMAP connection
* @param string $start Start of range
* @param string $end End of range
* @param string $mailbox Mailbox messages are being deleted from
* @return void
*/
function sqimap_messages_delete ($imap_stream, $start, $end, $mailbox, $bypass_trash=false) {
    global $move_to_trash, $trash_folder, $auto_expunge;

    if (($move_to_trash == true) && ($bypass_trash != true) &&
        (sqimap_mailbox_exists($imap_stream, $trash_folder) && ($mailbox != $trash_folder))) {
        sqimap_messages_copy ($imap_stream, $start, $end, $trash_folder);
    }
    sqimap_messages_flag ($imap_stream, $start, $end, "Deleted", true);
}

function sqimap_msgs_list_delete ($imap_stream, $mailbox, $id, $bypass_trash=false) {
    global $move_to_trash, $trash_folder;
    $msgs_id = sqimap_message_list_squisher($id);
    if (($move_to_trash == true) && ($bypass_trash != true) &&
        (sqimap_mailbox_exists($imap_stream, $trash_folder) &&  ($mailbox != $trash_folder)) ) {
        $read = sqimap_run_command ($imap_stream, "COPY $msgs_id " . sqimap_encode_mailbox_name($trash_folder), true, $response, $message, TRUE);
    }
    $read = sqimap_run_command ($imap_stream, "STORE $msgs_id +FLAGS (\\Deleted)", true, $response, $message, TRUE);
}


/**
* Sets the specified messages with specified flag
*/
function sqimap_messages_flag ($imap_stream, $start, $end, $flag, $handle_errors) {
    $read = sqimap_run_command ($imap_stream, "STORE $start:$end +FLAGS (\\$flag)", $handle_errors, $response, $message, TRUE);
}

function sqimap_toggle_flag($imap_stream, $id, $flag, $set, $handle_errors) {
    $msgs_id = sqimap_message_list_squisher($id);
    $set_string = ($set ? '+' : '-');
    $read = sqimap_run_command ($imap_stream, "STORE $msgs_id ".$set_string."FLAGS ($flag)", $handle_errors, $response, $message, TRUE);
}

/** @deprecated */
function sqimap_get_small_header ($imap_stream, $id, $sent) {
    $res = sqimap_get_small_header_list($imap_stream, $id, $sent);
    return $res[0];
}

/**
* Sort the message list and crunch to be as small as possible
* (overflow could happen, so make it small if possible)
*/
function sqimap_message_list_squisher($messages_array) {
    if( !is_array( $messages_array ) ) {
        return $messages_array;
    }

    sort($messages_array, SORT_NUMERIC);
    $msgs_str = '';
    while ($messages_array) {
        $start = array_shift($messages_array);
        $end = $start;
        while (isset($messages_array[0]) && $messages_array[0] == $end + 1) {
            $end = array_shift($messages_array);
        }
        if ($msgs_str != '') {
            $msgs_str .= ',';
        }
        $msgs_str .= $start;
        if ($start != $end) {
            $msgs_str .= ':' . $end;
        }
    }
    return $msgs_str;
}

/**
* Retrieves an array with a sorted uid list. Sorting is done on the imap server
*
* @param resource $imap_stream IMAP socket connection
* @param string $sSortField Field to sort on
* @param bool $reverse Reverse order search
* @return array $id sorted uid list
*/
function sqimap_get_sort_order ($imap_stream, $sSortField = 'UID',$reverse) {
    global  $default_charset,
            $sent_folder;

    $id = array();
    $sort_test = array();
    $sort_query = '';

    if ($sSortField == 'UID') {
        $query = "SEARCH UID 1:*";
        $uids = sqimap_run_command ($imap_stream, $query, true, $response, $message, true);
        if (isset($uids[0])) {
            if (preg_match("/^\* SEARCH (.+)$/", $uids[0], $regs)) {
                $id = preg_split("/ /", trim($regs[1]));
            }
        }
        if (!preg_match("/OK/", $response)) {
            $id = false;
        }
        $id = array_reverse($id);
        return $id;
    }
    if ($sSortField) {
        if ($reverse) {
            $sSortField = 'REVERSE '.$sSortField;
        }
        $query = "SORT ($sSortField) ".strtoupper($default_charset).' ALL';
        $sort_test = sqimap_run_command ($imap_stream, $query, true, $response, $message, TRUE);
    }
    if (isset($sort_test[0])) {
        for ($i=0,$iCnt=count($sort_test);$i<$iCnt;++$i) {
            if (preg_match("/^\* SORT (.+)$/", $sort_test[$i], $regs)) {
                $id = preg_split("/ /", trim($regs[1]));
            break;
            }
        }
    }
    if (!preg_match("/OK/", $response)) {
        return false;
    } else {
        return $id;
    }
}

/**
* Retrieves an array with a sorted uid list. Sorting is done by SquirrelMail
*
* @param resource $imap_stream IMAP socket connection
* @param string $sSortField Field to sort on
* @param bool $reverse Reverse order search
* @param mixed $key UNDOCUMENTED
* @return array $id sorted uid list
*/
function get_squirrel_sort ($imap_stream, $sSortField, $reverse = false) {

    if ($sSortField == 'UID') {
        $query = "SEARCH UID 1:*";
        $uids = sqimap_run_command ($imap_stream, $query, true, $response, $message, true);
        if (isset($uids[0])) {
            if (preg_match("/^\* SEARCH (.+)$/", $uids[0], $regs)) {
                $msgs = preg_split("/ /", trim($regs[1]));
            }
        }
        if (!preg_match("/OK/", $response)) {
            $msgs = false;
        }
    } else if ($sSortField != 'RFC822.SIZE' && $sSortField != 'INTERNALDATE') {
        $msgs = sqimap_get_small_header_list($imap_stream, false, '*',
                                      array($sSortField), array('UID'));
    } else {
        $msgs = sqimap_get_small_header_list($imap_stream, false, '*',
                                      array(), array('UID', $sSortField));
    }
    switch ($sSortField) {
      case 'FROM':
        array_walk($msgs, create_function('&$v,&$k',
              '$from = parseAddress($v["FROM"]);
               $v["FROM"] = ($from[0][1]) ? decodeHeader($from[0][1]):$from[0][0];'));
        foreach ($msgs as $item) {
            $msort["$item[ID]"] = (isset($item['FROM'])) ? $item['FROM'] : '';
        }

        natcasesort($msort);
        $msort = array_keys($msort);
        if ($reverse) {
           array_reverse($msort);
        }
        break;
      case 'TO':
        array_walk($msgs, create_function('&$v,&$k',
              '$from = parseAddress($v["TO"]);
               $v["TO"] = ($from[0][1]) ? decodeHeader($from[0][1]):$from[0][0];'));
        foreach ($msgs as $item) {
            $msort["$item[ID]"] = (isset($item['TO'])) ? $item['TO'] : '';
        }

        natcasesort($msort);
        $msort = array_keys($msort);
        if ($reverse) {
           array_reverse($msort);
        }
        break;

      case 'SUBJECT':
        array_walk($msgs, create_function('&$v,&$k',
              '$v["SUBJECT"] = strtolower(decodeHeader(trim($v["SUBJECT"])));
               $v["SUBJECT"] = (preg_match("/^(vedr|sv|re|aw|\[\w\]):\s*(.*)$/si", $v["SUBJECT"], $matches)) ?
                                  $matches[2] : $v["SUBJECT"];'));
        foreach ($msgs as $item) {
            $msort["$item[ID]"] = $item['SUBJECT'];
        }
        natcasesort($msort);
        $msort = array_keys($msort);
        if ($reverse) {
           array_reverse($msort);
        }
        break;
      case 'DATE':
        array_walk($msgs, create_function('&$v,$k',
            '$v["DATE"] = getTimeStamp(explode(" ",$v["DATE"]));'));
        foreach ($msgs as $item) {
            $msort[$item['ID']] = $item['DATE'];
        }
        if ($reverse) {
            arsort($msort,SORT_NUMERIC);
        } else {
            asort( $msort, SORT_NUMERIC);
        }
        $msort = array_keys($msort);
        break;
      case 'RFC822.SIZE':
      case 'INTERNALDATE':
        //array_walk($msgs, create_function('&$v,$k',
        //    '$v["RFC822.SIZE"] = getTimeStamp(explode(" ",$v["RFC822.SIZE"]));'));
        foreach ($msgs as $item) {
            $msort[$item['ID']] = $item[$sSortField];
        }
        if ($reverse) {
            arsort($msort,SORT_NUMERIC);
        } else {
            asort($msort, SORT_NUMERIC);
        }
        $msort = array_keys($msort);
        break;
      case 'UID':
        $msort = array_reverse($msgs);
        break;
    }
    return $msort;
}

/**
* Returns an indent array for printMessageinfo()
* This represents the amount of indent needed (value),
* for this message number (key)
*/
function get_parent_level ($thread_new) {
    $parent = '';
    $child  = '';
    $cutoff = 0;

    /* loop through the threads and take unwanted characters out
    of the thread string then chop it up
    */
    for ($i=0;$i<count($thread_new);$i++) {
        $thread_new[$i] = preg_replace("/\s\(/", "(", $thread_new[$i]);
        $thread_new[$i] = preg_replace("/(\d+)/", "$1|", $thread_new[$i]);
        $thread_new[$i] = preg_split("/\|/", $thread_new[$i], -1, PREG_SPLIT_NO_EMPTY);
    }
    $indent_array = array();
        if (!$thread_new) {
            $thread_new = array();
        }
    /* looping through the parts of one message thread */

    for ($i=0;$i<count($thread_new);$i++) {
    /* first grab the parent, it does not indent */

        if (isset($thread_new[$i][0])) {
            if (preg_match("/(\d+)/", $thread_new[$i][0], $regs)) {
                $parent = $regs[1];
            }
        }
        $indent_array[$parent] = 0;

    /* now the children, checking each thread portion for
    ),(, and space, adjusting the level and space values
    to get the indent level
    */
        $level = 0;
        $spaces = array();
        $spaces_total = 0;
        $indent = 0;
        $fake = FALSE;
        for ($k=1;$k<(count($thread_new[$i]))-1;$k++) {
            $chars = count_chars($thread_new[$i][$k], 1);
            if (isset($chars['40'])) {       /* testing for ( */
                $level = $level + $chars['40'];
            }
            if (isset($chars['41'])) {      /* testing for ) */
                $level = $level - $chars['41'];
                $spaces[$level] = 0;
                /* if we were faking lets stop, this portion
                of the thread is over
                */
                if ($level == $cutoff) {
                    $fake = FALSE;
                }
            }
            if (isset($chars['32'])) {      /* testing for space */
                if (!isset($spaces[$level])) {
                    $spaces[$level] = 0;
                }
                $spaces[$level] = $spaces[$level] + $chars['32'];
            }
            for ($x=0;$x<=$level;$x++) {
                if (isset($spaces[$x])) {
                    $spaces_total = $spaces_total + $spaces[$x];
                }
            }
            $indent = $level + $spaces_total;
            /* must have run into a message that broke the thread
            so we are adjusting for that portion
            */
            if ($fake == TRUE) {
                $indent = $indent +1;
            }
            if (preg_match("/(\d+)/", $thread_new[$i][$k], $regs)) {
                $child = $regs[1];
            }
            /* the thread must be broken if $indent == 0
            so indent the message once and start faking it
            */
            if ($indent == 0) {
                $indent = 1;
                $fake = TRUE;
                $cutoff = $level;
            }
            /* dont need abs but if indent was negative
            errors would occur
            */
            $indent_array[$child] = abs($indent);
            $spaces_total = 0;
        }
    }
    return $indent_array;
}


/**
* Returns an array with each element as a string representing one
* message-thread as returned by the IMAP server.
*/
function get_thread_sort ($imap_stream) {
    global $thread_new, $sort_by_ref, $default_charset, $server_sort_array, $indent_array;
    if (sqsession_is_registered('thread_new')) {
        sqsession_unregister('thread_new');
    }
    if (sqsession_is_registered('indent_array')) {
        sqsession_unregister('indent_array');
    }
    if (sqsession_is_registered('server_sort_array')) {
        sqsession_unregister('server_sort_array');
    }
    $thread_temp = array ();
    if ($sort_by_ref == 1) {
        $sort_type = 'REFERENCES';
    }
    else {
        $sort_type = 'ORDEREDSUBJECT';
    }
    $query = "THREAD $sort_type ".strtoupper($default_charset)." ALL";
    $thread_test = sqimap_run_command ($imap_stream, $query, true, $response, $message, TRUE);
    if (isset($thread_test[0])) {
        for ($i=0,$iCnt=count($thread_test);$i<$iCnt;++$i) {
        if (preg_match("/^\* THREAD (.+)$/", $thread_test[$i], $regs)) {
            $thread_list = trim($regs[1]);
        break;
        }
        }
    }
    else {
    $thread_list = "";
    }
    if (!preg_match("/OK/", $response)) {
    $server_sort_array = 'no';
    return $server_sort_array;
    }
    if (isset($thread_list)) {
        $thread_temp = preg_split("//", $thread_list, -1, PREG_SPLIT_NO_EMPTY);
    }
    $char_count = count($thread_temp);
    $counter = 0;
    $thread_new = array();
    $k = 0;
    $thread_new[0] = "";
    for ($i=0;$i<$char_count;$i++) {
        if ($thread_temp[$i] != ')' && $thread_temp[$i] != '(') {
                $thread_new[$k] = $thread_new[$k] . $thread_temp[$i];
        }
        elseif ($thread_temp[$i] == '(') {
                $thread_new[$k] .= $thread_temp[$i];
                $counter++;
        }
        elseif ($thread_temp[$i] == ')') {
                if ($counter > 1) {
                        $thread_new[$k] .= $thread_temp[$i];
                        $counter = $counter - 1;
                }
                else {
                        $thread_new[$k] .= $thread_temp[$i];
                        $k++;
                        $thread_new[$k] = "";
                        $counter = $counter - 1;
                }
        }
    }
    sqsession_register($thread_new, 'thread_new');
    $thread_new = array_reverse($thread_new);
    $thread_list = implode(" ", $thread_new);
    $thread_list = str_replace("(", " ", $thread_list);
    $thread_list = str_replace(")", " ", $thread_list);
    $thread_list = preg_split("/\s/", $thread_list, -1, PREG_SPLIT_NO_EMPTY);
    $server_sort_array = $thread_list;

    $indent_array = get_parent_level ($thread_new);
    sqsession_register($indent_array, 'indent_array');

    sqsession_register($server_sort_array, 'server_sort_array');
    return $thread_list;
}


function elapsedTime($start) {
    $stop = gettimeofday();
    $timepassed =  1000000 * ($stop['sec'] - $start['sec']) + $stop['usec'] - $start['usec'];
    return $timepassed;
}

// only used in sqimap_get_small_header_list
function parseString($read,&$i) {
    $char = $read{$i};
    $s = '';
    if ($char == '"') {
        $iPos = ++$i;
        while (true) {
            $iPos = strpos($read,'"',$iPos);
            if (!$iPos) break;
                if ($iPos && $read{$iPos -1} != '\\') {
                    $s = substr($read,$i,($iPos-$i));
                    $i = $iPos;
                    break;
                }
                $iPos++;
                if ($iPos > strlen($read)) {
                    break;
                }
        }
    } else if ($char == '{') {
        $lit_cnt = '';
        ++$i;
        $iPos = strpos($read,'}',$i);
        if ($iPos) {
        $lit_cnt = substr($read, $i, $iPos - $i);
        $i += strlen($lit_cnt) + 3; /* skip } + \r + \n */
        /* Now read the literal */
        $s = ($lit_cnt ? substr($read,$i,$lit_cnt): '');
        $i += $lit_cnt;
        /* temp bugfix (SM 1.5 will have a working clean version)
            too much work to implement that version right now */
        --$i;
        } else { /* should never happen */
            $i += 3; /* } + \r + \n */
            $s = '';
        }
    } else {
        return false;
    }
    ++$i;
    return $s;
}

// only used in sqimap_get_small_header_list
function parseArray($read,&$i) {
    $i = strpos($read,'(',$i);
    $i_pos = strpos($read,')',$i);
    $s = substr($read,$i+1,$i_pos - $i -1);
    $a = explode(' ',$s);
    if ($i_pos) {
        $i = $i_pos+1;
        return $a;
    } else {
        return false;
    }
}

function sqimap_get_small_header_list ($imap_stream, $msg_list, $show_num=false,
    $aHeaderFields = array('Date', 'To', 'Cc', 'From', 'Subject', 'X-Priority', 'Content-Type'),
    $aFetchItems = array('FLAGS', 'UID', 'RFC822.SIZE', 'INTERNALDATE')) {

    global $squirrelmail_language, $color, $data_dir, $username, $imap_server_type;
    global $allow_server_sort;

    $messages = array();
    $read_list = array();

    /* Get the small headers for each message in $msg_list */
    if ($show_num != '999999' && $show_num != '*' ) {
        $msgs_str = sqimap_message_list_squisher($msg_list);
        /*
        * We need to return the data in the same order as the caller supplied
        * in $msg_list, but IMAP servers are free to return responses in
        * whatever order they wish... So we need to re-sort manually
        */
        for ($i = 0; $i < sizeof($msg_list); $i++) {
            $messages["$msg_list[$i]"] = array();
        }
    } else {
        $msgs_str = '1:*';
    }



    /*
     * Create the query
     */

    $internaldate = getPref($data_dir, $username, 'internal_date_sort');
    if (($i = array_search('INTERNALDATE',$aFetchItems,true)) !== false && $internaldate == false) {
        unset($aFetchItems[$i]);
    }
    $sFetchItems = '';
    $query = "FETCH $msgs_str (";
    if (count($aFetchItems)) {
        $sFetchItems = implode(' ',$aFetchItems);
    }
    if (count($aHeaderFields)) {
        $sHeaderFields = implode(' ',$aHeaderFields);
        $sFetchItems .= ' BODY.PEEK[HEADER.FIELDS ('.$sHeaderFields.')]';
    }
    $query .= trim($sFetchItems) . ')';
    $read_list = sqimap_run_command_list ($imap_stream, $query, true, $response, $message, TRUE);
    $i = 0;

    foreach ($read_list as $r) {
        // use unset because we do isset below
        $read = implode('',$r);

        /*
            * #id<space>FETCH<space>(
        */

        /* extract the message id */
        $i_space = strpos($read,' ',2);
        $id = substr($read,2,$i_space-2);
        $fetch = substr($read,$i_space+1,5);
        if (!is_numeric($id) && $fetch !== 'FETCH') {
            set_up_language($squirrelmail_language);
            echo '<br><b><font color=$color[2]>' .
                _("ERROR : Could not complete request.") .
                '</b><br>' .
                _("Unknown response from IMAP server: ") . ' 1.' .
                htmlspecialchars($read) . "</font><br>\n";
                break;
        }
        $i = strpos($read,'(',$i_space+5);
        $read = substr($read,$i+1);
        $i_len = strlen($read);
        $i = 0;
        while ($i < $i_len && $i !== false) {
            /* get argument */
            $read = trim(substr($read,$i));
            $i_len = strlen($read);
            $i = strpos($read,' ');
            $arg = substr($read,0,$i);
            ++$i;
            switch ($arg)
            {
            case 'UID':
                $i_pos = strpos($read,' ',$i);
                if (!$i_pos) {
                    $i_pos = strpos($read,')',$i);
                }
                if ($i_pos) {
                    $unique_id = substr($read,$i,$i_pos-$i);
                    $i = $i_pos+1;
                } else {
                    break 3;
                }
                break;
            case 'FLAGS':
                $flags = parseArray($read,$i);
                if (!$flags) break 3;
                $aFlags = array();
                foreach ($flags as $flag) {
                    $flag = strtolower($flag);
                    $aFlags[$flag] = true;
                }
                $msg['FLAGS'] = $aFlags;
                break;
            case 'RFC822.SIZE':
                $i_pos = strpos($read,' ',$i);
                if (!$i_pos) {
                    $i_pos = strpos($read,')',$i);
                }
                if ($i_pos) {
                    $msg['SIZE'] = substr($read,$i,$i_pos-$i);
                    $i = $i_pos+1;
                } else {
                    break 3;
                }

                break;
            case 'INTERNALDATE':
                $msg['INTERNALDATE'] = parseString($read,$i);
                break;
            case 'BODY.PEEK[HEADER.FIELDS':
            case 'BODY[HEADER.FIELDS':
                $i = strpos($read,'{',$i);
                $header = parseString($read,$i);
                if ($header === false) break 2;
                /* First we replace all \r\n by \n, and unfold the header */
                $hdr = trim(str_replace(array("\r\n", "\n\t", "\n "),array("\n", ' ', ' '), $header));
                /* Now we can make a new header array with */
                /* each element representing a headerline  */
                $hdr = explode("\n" , $hdr);
                foreach ($hdr as $line) {
                    $pos = strpos($line, ':');
                    if ($pos > 0) {
                        $field = strtolower(substr($line, 0, $pos));
                        if (!strstr($field,' ')) { /* valid field */
                            $value = trim(substr($line, $pos+1));
                            switch($field)
                            {
                            case 'to': $msg['TO'] = $value; break;
                            case 'cc': $msg['CC'] = $value; break;
                            case 'from': $msg['FROM'] = $value; break;
                            case 'date':
                                $msg['DATE'] = str_replace('  ', ' ', $value);
                                break;
                            case 'x-priority': $msg['PRIORITY'] = $value; break;
                            case 'subject': $msg['SUBJECT'] = $value; break;
                            case 'content-type':
                                $type = $value;
                                if ($pos = strpos($type, ";")) {
                                    $type = substr($type, 0, $pos);
                                }
                                $type = explode("/", $type);
                                if(!is_array($type)) {
                                    $msg['TYPE0'] = 'text';
                                    $msg['TYPE1'] = 'plain';
                                }
                                break;
                            default: break;
                            }
                        }
                    }
                }
                break;
            default:
                ++$i;
                break;
            }
        }
        $msgi ="$unique_id";
        $msg['ID'] = $unique_id;

        $messages[$msgi] = $msg;
        ++$msgi;
    }
    array_reverse($messages);
    return $messages;
}

/**
* Returns a message array with all the information about a message.
* See the documentation folder for more information about this array.
*/
function sqimap_get_message ($imap_stream, $id, $mailbox) {
    // typecast to int to prohibit 1:* msgs sets
    $id = (int) $id;
    $flags = array();
    $read = sqimap_run_command ($imap_stream, "FETCH $id (FLAGS BODYSTRUCTURE)", true, $response, $message, TRUE);
    if ($read) {
        if (preg_match('/.+FLAGS\s\((.*)\)\s/AUi',$read[0],$regs)) {
            if (trim($regs[1])) {
                $flags = preg_split('/ /', $regs[1],-1,'PREG_SPLIT_NI_EMPTY');
            }
        }
    } else {
        /* the message was not found, maybe the mailbox was modified? */
        global $sort, $startMessage, $color;

        $errmessage = _("The server couldn't find the message you requested.") .
            '<p>'._("Most probably your message list was out of date and the message has been moved away or deleted (perhaps by another program accessing the same mailbox).");
        /* this will include a link back to the message list */
        error_message($errmessage, $mailbox, $sort, (int) $startMessage, $color);
        exit;
    }
    $bodystructure = implode('',$read);
    $msg =  mime_structure($bodystructure,$flags);
    $read = sqimap_run_command ($imap_stream, "FETCH $id BODY[HEADER]", true, $response, $message, TRUE);
    $rfc822_header = new Rfc822Header();
    $rfc822_header->parseHeader($read);
    $msg->rfc822_header = $rfc822_header;
    return $msg;
}

?>
