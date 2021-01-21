<?php
class course
{
    public static function getCoursesForEvent($eventid)
    {
        $output = array();
        $row = 0;
        // extract control codes
        $controlsFound = false;
        $controls = array();
        $xpos = array();
        $ypos = array();
        $dummycontrols = array();
        // @ suppresses error report if file does not exist
        // read control codes for each course
        if (($handle = @fopen(KARTAT_DIRECTORY."sarjojenkoodit_".$eventid.".txt", "r")) !== false) {
            $controlsFound = true;
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                // ignore first field: it is an index
                $codes = array();
                for ($j = 1; $j < count($data); $j++) {
                    $codes[$j - 1] = $data[$j];
                }
                $controls[$row] = $codes;
                $row++;
            }
            fclose($handle);
        }

        // extract control locations based on map co-ords
        if (($handle = @fopen(KARTAT_DIRECTORY."ratapisteet_".$eventid.".txt", "r")) !== false) {
            $row = 0;
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                // ignore first field: it is an index
                $x = array();
                $y = array();
                $dummycodes = array();
                // field is N separated and then semicolon separated
                $pairs = explode("N", $data[1]);
                for ($j = 0; $j < count($pairs); $j++) {
                    $xy = explode(";", $pairs[$j]);
                    // some courses seem to have nulls at the end so just ignore them
                    if ($xy[0] != "") {
                        $dummycodes[$j] = self::getDummyCode($pairs[$j]);
                        $x[$j] = 1 * $xy[0];
                        // make it easier to draw map
                        $y[$j] = -1 * $xy[1];
                    }
                }
                $xpos[$row] = $x;
                $ypos[$row] = $y;
                $dummycontrols[$row] = $dummycodes;
                $row++;
            }
            fclose($handle);
        }

  if ($eventid === '0') {
    // build temp arrays and return ALL controls for all courses LIVE event 0
    $xcontrols = array();
    for ($row = 0; $row < count($dummycontrols); $row++) {
      if (count($xpos) > $row) {
        // skip first and last of each row
        for ($j = 1; $j < count($dummycontrols[$row])-1; $j++) {
          if (($controlsFound) && (count($controls) > $row)) {
            $xcontrols[$dummycontrols[$row][$j]] = array($controls[$row][$j],$xpos[$row][$j],$ypos[$row][$j]);
          } else {
            $xcontrols[$dummycontrols[$row][$j]] = array($dummycontrols[$row][$j],$xpos[$row][$j],$ypos[$row][$j]);
          }
        }
      }
    }
    // replace all controls
    for ($row = 0; $row < count($dummycontrols); $row++) {
      // fill all controls except first and last to temp arrays
      $rxpos = array();
      $rypos = array();
      $rcontrols = array();
      $rdummycontrols = array();
      $rdummycontrols[0] = $dummycontrols[$row][0];
      if (count($xpos) > $row) {
        $rxpos[0] = $xpos[$row][0];
        $rypos[0] = $ypos[$row][0];
      }
      if (($controlsFound) && (count($controls) > $row)) {
        $rcontrols[0] = $controls[$row][0];
      }
      $i = 1;
      foreach ($xcontrols as $key => $value) {
        $rdummycontrols[$i] = $key;
        if (count($xpos) > $row) {
          $rxpos[$i] = $value[1];
          $rypos[$i] = $value[2];
        }
        if (($controlsFound) && (count($controls) > $row)) {
          $rcontrols[$i] = $value[0];
        }
        $i++;
      }
      $last_idx = count($dummycontrols[$row]) - 1;
      $rdummycontrols[$i] = $dummycontrols[$row][$last_idx];
      $dummycontrols[$row] = $rdummycontrols;
      if (count($xpos) > $row) {
        $rxpos[$i] = $xpos[$row][$last_idx];
        $rypos[$i] = $ypos[$row][$last_idx];
        $xpos[$row] = $rxpos;
        $ypos[$row] = $rypos;
      }
      if (($controlsFound) && (count($controls) > $row)) {
        $last_idx = count($controls[$row]) - 1;
        $rcontrols[$i] = $controls[$row][$last_idx];
        $controls[$row] = $rcontrols;
      }
    }
  }
  
        $row = 0;
        // set up details for each course
        if (($handle = @fopen(KARTAT_DIRECTORY."sarjat_".$eventid.".txt", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                $detail = array();
                $detail["courseid"] = intval($data[0]);
                $detail["name"] = utils::encode_rg_input($data[1]);
                // sarjojenkoodit quite often seems to have things missing for old RG1 events so protect against it
                if (($controlsFound) && (count($controls) > $row)) {
                    $detail["codes"] = $controls[$row];
                } else {
                    $detail["codes"] = $dummycontrols[$row];
                }
                // some RG1 events seem to have unused courses which cause trouble
                if (count($xpos) > $row) {
                    $detail["xpos"] = $xpos[$row];
                    $detail["ypos"] = $ypos[$row];
                } else {
                    $detail["xpos"] = array();
                    $detail["ypos"] = array();
                }
                $output[$row] = $detail;
                $row++;
            }
            fclose($handle);
        }
        return $output;
    }

    private static function getDummyCode($code)
    {
        // create dummy control codes if the results didn't include any
        static $codes = array();
        static $count = 0;
        $dummycode = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($codes[$i] == $code) {
                $dummycode = $i + 1;
            }
        }
        if ($dummycode == 0) {
            $codes[$count] = $code;
            $count++;
            $dummycode = $count;
        }
        //utils::rg2log($code.' becomes '.$dummycode);
        // force to a string since it helps elsewhere
        // and it shows that these are dummy values
        return 'X'.$dummycode;
    }

public static function deleteCourse($eventid) {
  $write["status_msg"] = "";
  if (isset($_GET['courseid'])) {
    $courseid = $_GET['courseid'];
    // delete comments
    $filename = KARTAT_DIRECTORY."kommentit_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for kommentit. ";
    }

    // delete result records
    $filename = KARTAT_DIRECTORY."kilpailijat_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      $deleted = FALSE;
      if ($data[1] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for kilpailijat. ";
    }

    // delete route
    $deleted = FALSE;
    $filename = KARTAT_DIRECTORY."merkinnat_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= " Save error for merkinnat. ";
    }

    // delete course template
    $filename = KARTAT_DIRECTORY."radat_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for radat. ";
    }

    // delete course template
    $filename = KARTAT_DIRECTORY."ratapisteet_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for ratapisteet. ";
    }

    // delete course names
    $filename = KARTAT_DIRECTORY."sarjat_".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for sarjat. ";
    }

    // delete course control list
    $filename = KARTAT_DIRECTORY."sarjojenkoodit".$eventid.".txt";
    $oldfile = file($filename);
    $updatedfile = array();
    $deleted = FALSE;
    foreach ($oldfile as $row) {
      $data = explode("|", $row);
      if ($data[0] == $courseid) {
        $deleted = TRUE;
      } else {
        $updatedfile[] = $row;
      }
    }
    $status = file_put_contents($filename, $updatedfile);

    if (!$status) {
      $write["status_msg"] .= "Save error for sarjojenkoodit. ";
    }

  } else {
    $write["status_msg"] = "Invalid course id. ";
  }

  if ($write["status_msg"] == "") {
    $write["ok"] = TRUE;
    $write["status_msg"] = "Course deleted.";
    rg2log("Course deleted|".$eventid."|".$courseid);
  } else {
    $write["ok"] = FALSE;
  }

  return($write);
}
}
