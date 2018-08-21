<?php
class user
{
    public static function logIn ($data) {
      if (isset($data->x) && isset($data->y)) {
        $userdetails = extractString($data->x);
        $cookie = $data->y;
      } else {
        $userdetails = "anon";
        $cookie = "none";
      }
      $ok = TRUE;
      $keksi = trim(file_get_contents(KARTAT_DIRECTORY."keksi.txt"));
      //rg2log("logIn ".$userdetails." ".$cookie);
      if (file_exists(KARTAT_DIRECTORY."rg2userinfo.txt")) {
        $saved_user = trim(file_get_contents(KARTAT_DIRECTORY."rg2userinfo.txt"));
        $temp = crypt($userdetails, $saved_user);
        if ($temp != $saved_user) {
          //rg2log("User details incorrect. ".$temp." : ".$saved_user);
          $ok = FALSE;
        }
        if ($keksi != $cookie) {
          //rg2log("Cookies don't match. ".$keksi." : ".$cookie);
          $ok = FALSE;
        }
      } else {
        // new account being set up: rely on JS end to force a reasonable name/password
        $temp = crypt($userdetails, $keksi);
        rg2log("Creating new account ".$temp);
        file_put_contents(KARTAT_DIRECTORY."rg2userinfo.txt", $temp.PHP_EOL);
      }
      return $ok;
    }

    // Mickey Mouse function to extract user name and password
    // just avoids plain text transmission for now so a bit better than RG1
    private static function extractString($data) {
      $str = "";
      for ($i = 0; $i < strlen($data); $i = $i + 2) {
        $str .= substr($data, $i, 1);
      }
      return $str;
    }

    public static function generateNewKeksi() {
      // simple cookie generator! Don't need unique, just need something vaguely random
      $keksi = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890"), 0, 20);
      file_put_contents(KARTAT_DIRECTORY."keksi.txt", $keksi.PHP_EOL);
      //rg2log("Writing keksi.txt ".$keksi);
      return $keksi;
    }
}
?>