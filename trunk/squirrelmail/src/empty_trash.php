<?
   include("../config/config.php");
   include("../functions/strings.php");
   include("../functions/page_header.php");
   include("../functions/display_messages.php");
   include("../functions/imap.php");
   include("../functions/array.php");

   include("../src/load_prefs.php");

   $imapConnection = sqimap_login($username, $key, $imapServerAddress, 0);

   sqimap_mailbox_list($imapConnection, $boxes);

   $mailbox = $trash_folder;
   fputs($imapConnection, "1 LIST \"$mailbox\" *\n");
   $data = sqimap_read_data($imapConnection , "1", false, $response, $message);
      
   $dm = sqimap_get_delimiter($imapConnection);
   
   // According to RFC2060, a DELETE command should NOT remove inferiors (sub folders)
   //    so lets go through the list of subfolders and remove them before removing the
   //    parent.
   //    BUG??? - what if a subfolder has a subfolder??  need to start at lowest level
   //       and work up.
   

//   for ($i = 0; $i < count($boxes); $i++) {
//      if (($boxes[$i]["UNFORMATTED"] == $mailbox) ||
//          (substr($boxes[$i]["UNFORMATTED"], 0, strlen($mailbox . $dm)) == $mailbox . $dm)) {
//      if (($boxes[$i]["UNFORMATTED"] != $mailbox) && (substr($boxes[$i]["UNFORMATTED"], 0, strlen($mailbox . $dm)) == $mailbox . $dm)) {
//         removeFolder($imapConnection, $boxes[$i]["UNFORMATTED"], $dm);
//      }
//   }

   // lets remove the trash folder
//   sqimap_mailbox_delete($imapConnection, $mailbox, $dm);
   sqimap_mailbox_create($imapConnection, "$trash_folder", "");

   sqimap_mailbox_select($imapConnection, $trash_folder, $numMessages);
   echo "<HTML><BODY TEXT=\"$color[8]\" BGCOLOR=\"$color[4]\" LINK=\"$color[7]\" VLINK=\"$color[7]\" ALINK=\"$color[7]\">\n";
   displayPageHeader($color, $mailbox);
   messages_deleted_message($trash_folder, $sort, $startMessage, $color);
   sqimap_logout($imapConnection);
?>
