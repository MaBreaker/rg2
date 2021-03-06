<?php
class splitsbrowser
{
    public static function getSplitsbrowser($eventid)
    {
        $page = file_get_contents("html/splitsbrowser.html");
        $eventname = event::getEventName($eventid);
        $page = str_replace('<EVENT_NAME>', $eventname, $page);
        //MaB debug
        if (defined('DEBUG')) {
            $page = str_replace('DEBUG_CLOSE', "", $page);
            $page = str_replace('DEBUG', "", $page);
            $page = str_replace('MINIFIED_CLOSE', "--", $page);
            $page = str_replace('MINIFIED', "!--", $page);
        } else {
            $page = str_replace('DEBUG_CLOSE', "--", $page);
            $page = str_replace('DEBUG', "!--", $page);
            $page = str_replace('MINIFIED_CLOSE', "", $page);
            $page = str_replace('MINIFIED', "", $page);
        }
        $page = str_replace('<SPLITSBROWSER_DIRECTORY>', SPLITSBROWSER_DIRECTORY, $page);
        $result_data = self::getResultsCSV($eventid);
        $page = str_replace('<SPLITSBROWSER_DATA>', $result_data, $page);
        return $page;
    }

    // formats results as needed for Splitsbrowser
    private static function getResultsCSV($eventid)
    {
        $result_data = "\n'Headers;\\n' + \n";
        $first_line = true;
        $coursecount = 0;
        $courses = array();
        $controls = array();
        // read control codes for each course: use hajontakanta if it exists
        if (($handle = @fopen(KARTAT_DIRECTORY."hajontakanta_".$eventid.".txt", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                $courses[$coursecount] = $data[0];
                //MaB $data[1]; variant name
                $codes =  explode("_", $data[2]);
                $controls[$coursecount] = $codes;
                $coursecount++;
            }
            fclose($handle);
        }
        // try sarjojenkoodit if we didn't find anything
        if ($coursecount == 0) {
            if (($handle = @fopen(KARTAT_DIRECTORY."sarjojenkoodit_".$eventid.".txt", "r")) !== false) {
                while (($data = fgetcsv($handle, 0, "|")) !== false) {
                    $courses[$coursecount] = $data[0];
                    $codes = array();
                    // ignore start and finish: just need control codes
                    for ($j = 2; $j < (count($data) - 1); $j++) {
                        $codes[$j - 2] = $data[$j];
                    }
                    $controls[$coursecount] = $codes;
                    $coursecount++;
                }
                fclose($handle);
            }
        }
        // shouldn't get a request for non-existent event but it seems to happen...#266
        $results = array();
        if (file_exists(KARTAT_DIRECTORY."kilpailijat_".$eventid.".txt")) {
            $results = file(KARTAT_DIRECTORY."kilpailijat_".$eventid.".txt");
        }
        foreach ($results as $result) {
            $data = explode("|", $result);
            // extract time
            list($t, $temp) = utils::tidyTime($data[7]);
            if (intval($data[0]) < GPS_RESULT_OFFSET) {
                if ($first_line) {
                    $first_line = false;
                } else {
                    $result_data .= " +\n";
                }
                // 0-based column indexes
                // 0: startno, so use resultid
                $result_data .= "'".intval($data[0]).";;;";
                // 3: surname
                // escape apostrophe in names
                $name = str_replace("'", "\'", $data[3]);
                $result_data .= $name.";;;;;;";
                // 9: start time
                $result_data .= self::convertSecondsToHHMMSS(intval($data[4])).";;";
                // 11: time
                //MaB more 0 variants to check
                if ($t === "00:00:00" or $t === "0:00:00" or $t === "0" or $t === "") {
                    $result_data .= "---;;;;;;;";
                } else {
                    $result_data .= $t.";;;;;;;";
                }
                // 18: course name
                // escape apostrophe in course name
                $name = str_replace("'", "\'", $data[2]);
                //MaB name
                if(ctype_digit(strval($data[6]))) {
                    $name .= $data[6];
                }
                $result_data .= $name.";;;;;;;;;;;;;;;;;;;;";
                // find codes for this course
                if ($data[6] !== '') {
                    $variant = $data[6]; //MaB variant
                } else {
                    $variant = $data[1]; //MaB course
                }
                $courseindex = -1;
                for ($i = 0; $i < $coursecount; $i++) {
                    //MaB isset for safety
                    if (isset($courses[$i]) && $courses[$i] == $variant) {
                        $courseindex = $i;
                        break;
                    }
                }
                // 38: course number, 39: course name
                // escape apostrophe in course name
                $name = str_replace("'", "\'", $data[2]);
                $result_data .= intval($data[1]).";".$name.";;;";
                // trim trailing ; which create null fields when expanded
                $temp = rtrim($data[8], ";");
                // split array at ; and force to integers
                $splits = array_map('intval', explode(";", $temp));
                // 42: control count, 44 start time
                // RG2 has finish split, but Splitsbrowser doesn't need it
                //MaB remove last split time if it is zero or the same as finish time
                //$split_count = count($splits) -  1;
                $split_count = count($splits);
                while (($split_count > 1) && ((($splits[$split_count - 1]) === 0) || ($splits[$split_count - 1] === $splits[$split_count - 2]))) {
                    $split_count = $split_count - 1;
                }
                $split_count = $split_count - 1;
                $result_data .= $split_count.";;".self::convertSecondsToHHMMSS(intval($data[4])).";";
                // 45: finish time
                if ($split_count > 0) {
                    //MaB $splits[$split_count]
                    $finish_secs = intval($splits[$split_count]) + intval($data[4]);
                } else {
                    $finish_secs = intval($data[4]);
                }
                if ($finish_secs == 0) {
                    // #155: send invalid rather than 0 times to Splitsbrowser
                    $result_data .= "---;";
                } else {
                    $result_data .= self::convertSecondsToHHMMSS($finish_secs).";";
                }
                if ($courseindex > -1) {
                    $controlcount = count($controls[$courseindex]);
                } else {
                    $controlcount = 0;
                }
                for ($i = 0; $i < $split_count; $i++) {
                    // 46: control 1 number
                    if ($courseindex > -1) {
                        if ($i < $controlcount) {
                            $result_data .= $controls[$courseindex][$i].";";
                        } else {
                            $result_data .= "XXX;";
                        }
                    } else {
                        $result_data .= ($i + 1).";";
                    }
                    // 47: control 1 split
                    // #155: send invalid rather than 0 times to Splitsbrowser
                    if ($splits[$i] == 0) {
                        $result_data .= "---;";
                    } else {
                        $result_data .= self::convertSecondsToMMSS($splits[$i]).";";
                    }
                }
                $result_data .= "\\n'";
            }
        }
        $result_data .= ";";
        $result_data = str_replace("&amp;", "\&", $result_data);
        if ($first_line) {
            // we didn't find any results
            $results_data = "";
        }
        return $result_data;
    }

    private static function convertSecondsToHHMMSS($seconds)
    {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours*3600)) / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        ;
    }

    private static function convertSecondsToMMSS($seconds)
    {
        $mins = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return sprintf('%d:%02d', $mins, $secs);
        ;
    }
}