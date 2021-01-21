<?php
class map
{
    public static function getMaps()
    {
        $output = array();
        $row = 0;
        if (($handle = @fopen(KARTAT_DIRECTORY."kartat.txt", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                $detail = array();
                if (count($data) > 1) {
                    $detail["mapid"] = intval($data[0]);
                    $detail["name"] = utils::encode_rg_input($data[1]);
                    // defaults to jpg so only need to say if we have something else as well
                    if (file_exists(KARTAT_DIRECTORY.$detail['mapid'].'.gif')) {
                        $detail["mapfilename"] = $detail['mapid'].'.gif';
                    }
                    //MaB added if for club
                    if ((count($data) > 14) || (count($data) == 3)) {
                        $detail["club"] = utils::encode_rg_input($data[2]);
                    }
                    $detail["georeferenced"] = false;
                    //MaB "==" -> ">="
                    if (count($data) >= 14) {
                        list($A, $B, $C, $D, $E, $F) = self::generateWorldFile($data);
                        $detail["A"] = $A;
                        $detail["B"] = $B;
                        $detail["C"] = $C;
                        $detail["D"] = $D;
                        $detail["E"] = $E;
                        $detail["F"] = $F;
                        list($localA, $localB, $localC, $localD, $localE, $localF) = self::generateLocalWorldFile($data);
                        $detail["localA"] = $localA;
                        $detail["localB"] = $localB;
                        $detail["localC"] = $localC;
                        $detail["localD"] = $localD;
                        $detail["localE"] = $localE;
                        $detail["localF"] = $localF;
                        // make sure it worked OK
                        if (($E != 0) && ($F != 0)) {
                            $detail["georeferenced"] = true;
                        }
                    }
                    $output[$row] = $detail;
                    $row++;
                }
            }
            fclose($handle);
        }
        return utils::addVersion('maps', $output);
    }

    public static function uploadMapFile()
    {
        $write = array();
        $write["ok"] = false;
        $write["status_msg"] = "Map upload failed.";
        $data = new stdClass();
        $data->x = $_POST["x"];
        $data->y = $_POST["y"];
        //MaB safer compare to true
        if (user::logIn($data) !== true) {
            $write["status_msg"] = "Login failed.";
        } else {
            $filename = $_POST["name"];
            // PHP changes . and space to _ just for fun
            $filename = str_replace(".", "_", $filename);
            $filename = str_replace(" ", "_", $filename);
            if (is_uploaded_file($_FILES[$filename]['tmp_name'])) {
                $file = $_FILES[$filename];
                if ($file['type'] == 'image/jpeg') {
                    if (move_uploaded_file($file['tmp_name'], KARTAT_DIRECTORY.'temp.jpg')) {
                        $write["ok"] = true;
                        $write["status_msg"] = "Map uploaded.";
                    }
                }
                if ($file['type'] == 'image/gif') {
                    if ($image = imagecreatefromgif($file['tmp_name'])) {
                        if (imagejpeg($image, KARTAT_DIRECTORY.'temp.jpg')) {
                            if (move_uploaded_file($file['tmp_name'], KARTAT_DIRECTORY.'temp.gif')) {
                                $write['ok'] = true;
                                $write['status_msg'] = "Map uploaded.";
                            }
                        }
                    }
                }
            }
        }

        $keksi = user::generateNewKeksi();
        $write["keksi"] = $keksi;

        header("Content-type: application/json");
        echo json_encode($write);
    }

    public static function addNewMap($data)
    {
        $write["status_msg"] = "";
        if (($handle = @fopen(KARTAT_DIRECTORY."kartat.txt", "r+")) !== false) {
            // read to end of file to find last entry
            $oldid = 0;
            while (($olddata = fgetcsv($handle, 0, "|")) !== false) {
              // blank rows come back as a single null array entry so ignore them
              if (count($olddata) > 1) {
                // ids should be increasing anyway, but just in case...
                if (intval($olddata[0]) > $oldid) {
                    $oldid = intval($olddata[0]);
                }
              }
            }
            $newid = $oldid + 1;
        } else {
            // create empty kartat file
            $newid = 1;
            $handle = @fopen(KARTAT_DIRECTORY."kartat.txt", "w+");
        }
        // may not have a GIF
        if (file_exists(KARTAT_DIRECTORY."temp.gif")) {
            $renameGIF = rename(KARTAT_DIRECTORY."temp.gif", KARTAT_DIRECTORY.$newid.".gif");
        } else {
            $renameGIF = true;
        }
        // always need a JPG for original Routegadget to maintain backward compatibility
        $renameJPG = rename(KARTAT_DIRECTORY."temp.jpg", KARTAT_DIRECTORY.$newid.".jpg");

        if (($renameJPG && $renameGIF)) {
            $newmap = $newid."|".utils::encode_rg_output($data->name);
            //MaB copyright
            $newmap .= "|".utils::encode_rg_output($data->copyright);
            if ($data->worldfile->valid) {
                $newmap .= "|".$data->xpx[0]."|".$data->lon[0]."|".$data->ypx[0]."|".$data->lat[0];
                $newmap .= "|".$data->xpx[1]."|".$data->lon[1]."|".$data->ypx[1]."|".$data->lat[1];
                $newmap .= "|".$data->xpx[2]."|".$data->lon[2]."|".$data->ypx[2]."|".$data->lat[2];
            }
            if ($data->localworldfile->valid) {
                // save local worldfile for use in aligning georeferenced courses
                $wf =$data->localworldfile->A.",".$data->localworldfile->B.",".$data->localworldfile->C.",".$data->localworldfile->D.",".$data->localworldfile->E.",".$data->localworldfile->F.PHP_EOL;
                @file_put_contents(KARTAT_DIRECTORY."worldfile_".$newid.".txt", $wf);
            }

            $newmap .= PHP_EOL;
            $write["newid"] = $newid;
            $status =fwrite($handle, $newmap);
            if (!$status) {
                $write["status_msg"] = "Save error for kartat. ";
            }
        } else {
            $write["status_msg"] = "Error renaming map file. ";
        }
        @fflush($handle);
        @fclose($handle);

        if ($write["status_msg"] == "") {
            $write["ok"] = true;
            $write["status_msg"] = "Map added";
            utils::rg2log("Map added|".$newid);
        } else {
            $write["ok"] = false;
        }

        return $write;
    }

    private static function generateLocalWorldFile($data)
    {
        // looks for local worldfile
        $file = KARTAT_DIRECTORY."worldfile_".intval($data[0]).".txt";
        $temp = array();
        if (file_exists($file)) {
            $wf = trim(file_get_contents($file));
            $temp = explode(",", $wf);
        }
        if (count($temp) == 6) {
            return array($temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5]);
        } else {
            return array(0, 0, 0, 0, 0, 0);
        }
    }

    public static function generateWorldFile($data)
    {
        // takes three georeferenced points in a kartat row and converts to World File format
        //MaB rg1 copyright details
        $ix = 2;
        if (count($data) < 14) {
            return array(0, 0, 0, 0, 0, 0);
        } elseif (count($data) > 14) {
            // original Routegadget copyright is 3th field, so georeferencing starts from 4th
            $ix = 3;
        }
        for ($i = 0; $i < 3; $i++) {
            //MaB $ix
            $x[$i] = intval($data[$ix + ($i * 4)]);
            $lon[$i] = floatval($data[$ix + 1 + ($i * 4)]);
            $y[$i] = intval($data[$ix + 2 + ($i * 4)]);
            $lat[$i] = floatval($data[$ix + 3 + ($i * 4)]);
            //utils::rg2log($data[0].", ".$lat[$i].", ".$lon[$i].", ".$x[$i].", ".$y[$i]);
        }
        // assumes various things about the three points
        // works for RG2, may not work for the original, but we can live with that
        // idealy we would have saved the world file rather than three points
        if (($x[0]!== 0) || ($y[0] !== 0) || ($y[2] !== 0) || ($x[2] === 0)) {
    //MaB calculations for any coordinate points
    // following worldfile calculation method works only with UTM
    // and because RouteGadget is WGS84 based conversions from WGS84 to UTM and vice versa are made
    // TODO: worldfile coordinate equations for WGS84
    
    // convert to UTM values
    for ($i = 0; $i < 3; $i++) {
      //rg2log("LL".$i." : ".$lat[$i].", ".$lon[$i].", ".$x[$i].", ".$y[$i]);
      if (defined('EPSG_DEFAULT')) {
        list($n[$i],$e[$i],$zon[$i],$sh[$i]) = self::convertLLtoUTM($lat[$i], $lon[$i], EPSG_DEFAULT);
      } else {
        list($n[$i],$e[$i],$zon[$i],$sh[$i]) = self::convertLLtoUTM($lat[$i], $lon[$i]);
      }
      //rg2log("UTM".$i.": ".$n[$i].", ".$e[$i].", ".$zon[$i].", ".($bool_val ? 'true' : 'false'));
    }
    
    $ratio=floatval(0);
    $angle=floatval(0);
    for ($i = 1; $i < 3; $i++) {
      $deltaY=abs($y[0]-$y[$i]);
      $deltaX=abs($x[0]-$x[$i]);
      $pxLen=sqrt(pow($deltaY,2)+pow($deltaX,2));
      //rg2log("deltaY=$deltaY, deltaX=$deltaX, pxLen=$pxLen");

      $deltaN=abs($n[0]-$n[$i]);
      $deltaE=abs($e[0]-$e[$i]);
      $utmLen=sqrt(pow($deltaN,2)+pow($deltaE,2));
      //rg2log("deltaN=$deltaN, $deltaE=$deltaE, pxLen=$utmLen");

      $pxRad=acos($deltaX/$pxLen); // digital image has square pixels
      //$pxRadY=asin($deltaY/$pxLen);
      $utmRad=acos($deltaE/$utmLen); // digital image has square pixels
      //$utmRadN=asin($deltaN/$utmLen
      //rg2log("pxRad=$pxRad, utmRad=$utmRad");

      if (bccomp($pxLen,0,10) == 0) {
        $pxLen = 0.0000000001;
      }

      $ratio+=$utmLen/$pxLen;
      $angle+=abs($pxRad-$utmRad); // digital image has square pixels
      //$angleY+=abs($pxRadY-$utmRadN);
    }

    $ratio=$ratio/2;
    $angle=$angle/2; // digital image has square pixels
    //$angleY=$angleY/2;

    $negX = 1; // is this always positive ?!?
    $negY = -1; // is this always negative ?!?
    $ratioX=$negX * cos($angle);
    $ratioY=$negY * sin($angle); // digital image has square pixels
    //$ratioY=$negY * sin($angleY);
    //utils::rg2log("ratio=$ratio, ratioX=$ratioX, ratioY=$ratioY, angle=$angle");

    $A=$ratio*$ratioX;
    $D=$ratio*$ratioY;
    $B=$ratio*$ratioY;
    $E=-1*$ratio*$ratioX; // E parameter is often a negative number. This is because most image files store data from top to bottom, while the software utilizes traditional Cartesian coordinates with the origin in the conventional lower-left corner
    $C=$e[0] - $A*$x[0] - $B*$y[0];
    $F=$n[0] - $D*$x[0] - $E*$y[0];
    /*
    $C=(($e[0] - $A*$x[0] - $B*$y[0]) + ($e[1] - $A*$x[1] - $B*$y[1]) + ($e[2] - $A*$x[2] - $B*$y[2])) / 3;
    $F=(($n[0] - $D*$x[0] - $E*$y[0]) + ($n[1] - $D*$x[1] - $E*$y[1]) + ($n[2] - $D*$x[2] - $E*$y[2])) / 3;
    */
    //DEBUG: force test following functionality with correct worldfile values
    //$A = 2.002863; $D=-0.382156; $B=-0.382156; $E=-2.002863; $C = 261953.5; $F = 6707650.5;
    //utils::rg2log("Compare jukola UTM: 2.002863, -0.382156, -0.382156, -2.002863, 261953.5, 6707650.5");
    utils::rg2log("worldfile: A=".$A.",D=".$D.",B=".$B.",E=".$E.",C=".$C.",F=".$F);
    //utils::rg2log("Compare Jukola WGS84: A=3.673335956817E-5,D=-2.2520894097928E-6,B=-4.5633315073021E-6,E=-1.8159430321696E-5,C=22.673252188749,F=60.434724902822");

    // TODO: check if real C,F coordinate is in southern hemisphere on maps near to equator
    list($A, $B, $C, $D, $E, $F) = self::convertABCDEF_UTMtoWGS84($A, $B, $C, $D, $E, $F, $zon[0], $sh[0]);
    } else {
        // X = Ax + By + C, Y = Dx + Ey + F
        // C = X - Ax - By, where x and y are 0
        $C = $lon[0];
        // F = Y - Dx - Ey, where X and Y are 0
        $F = $lat[0];
        // A = (X - By - C) / x where y = 0
        $A = ($lon[2] - $C) / $x[2];
        // B = (X - Ax - C) / y
        $B = ($lon[1] - ($A * $x[1]) - $C) / $y[1];
        // D = (Y - Ey - F) / x where y = 0
        $D = ($lat[2] - $F) / $x[2];
        // E = (Y - Dx - F) / y
        $E = ($lat[1] - ($D * $x[1]) -  $F) / $y[1];
    }

    //utils::rg2log("ADBECF: A=".exp_to_dec($A).",D=".exp_to_dec($D).",B=".exp_to_dec($B).",E=".exp_to_dec($E).",C=".$C.",F=".$F);
        return array($A, $B, $C, $D, $E, $F);
    }

/**
 * @return distance in metres between two points
*/
private static function getLatLonDistance($lat1, $lon1, $lat2, $lon2) {
  // Haversine formula (http://www.codecodex.com/wiki/Calculate_distance_between_two_points_on_a_globe)
  //echo $lat1, " ", $lon1, " ",$lat2, " ",$lon2, "<br />";
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);
  $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
  $c = 2 * asin(sqrt($a));
  // multiply by IUUG earth mean radius (http://en.wikipedia.org/wiki/Earth_radius) in metres
  // this is used in some cases 6378137
  return 6371009 * $c;
}

//------------------------------------------------------------------------------
// Convert Longitude/Latitude to UTM
// - optional projection type for overriding zone etc. values
//------------------------------------------------------------------------------
private static function convertLLtoUTM($lat, $lon, $proj=null){
  //Convert Latitude and Longitude to UTM
  //Declarations();
  $southern_hemisphere=false;
  $a = 6378137.0;
  $f = 1/298.2572236;
  $PI=3.1415926535897932384626433832795;
  $drad = $PI/180;//Convert degrees to radians)

  $k0 = 0.9996;//scale on central meridian
  $b = $a*(1-$f);//polar axis.
  $e = sqrt(1 - ($b/$a)*($b/$a));//eccentricity

  //Input Geographic Coordinates
  //Decimal Degree Option
  $latd = floatval($lat);
  $lngd = floatval($lon);

  if(!is_float($latd) || !is_float($lngd)){
    utils::rg2log("Non-Numeric Input Value");
  }
  if($latd <-90 || $latd > 90){
    utils::rg2log("Latitude must be between -90 and 90");
  }
  if($lngd <-180 || $lngd > 180){
    utils::rg2log("Latitude must be between -180 and 180");
  }

  $phi = $latd*$drad;//Convert latitude to radians
  $lng = $lngd*$drad;//Convert longitude to radians
  $latz = 0;//Latitude zone: A-B S of -80, C-W -80 to +72, X 72-84, Y,Z N of 84
  if ($latd > -80 && $latd < 72){$latz = floor(($latd + 80)/8)+2;}
  if ($latd > 72 && $latd < 84){$latz = 21;}
  if ($latd > 84){$latz = 23;}
  
  $utmz = 1 + floor(($lngd+180)/6);//calculate utm zone

    // in Norway 32V is extended 3.0 degrees to west
  if ($latz == 19 && $utmz == 31) {
    if ($lngd >= 3.0) $utmz = 32;
  }
  // in Svalbard remove 32X, 34X and 36X
  else if ($latz == 21 && $utmz >= 31 && $utmz <= 37) {
    if ($utmz == 32) {
      if ($lngd >= 9.0) $utmz = 33;
      else $utmz = 31;
    }
    else if ($utmz == 34) {
      if ($lngd >= 21.0) $utmz = 35;
      else $utmz = 33;
    }
    else if ($utmz == 36) {
      if ($lngd >= 33.0) $utmz = 37;
      else $utmz = 35;
    }
  }
  // Finland ESRI-TM35
  else if (strcmp($proj,'EPSG:3067') === 0) {
    if ($lngd >= 19.0900 && $lngd <= 31.5900 && 
      $latd >= 59.3000 && $latd <= 70.1300) {
      $utmz = 35;
    }
  }
    
  //Calculate Intermediate Terms
  $e0 = $e/sqrt(1 - $e*$e);//Called e prime in reference
  $esq = (1 - ($b/$a)*($b/$a));//e squared for use in expansions
  $e0sq = $e*$e/(1-$e*$e);// e0 squared - always even powers
  $N = $a/sqrt(1-pow($e*sin($phi),2));
  $T = pow(tan($phi),2);
  $C = $e0sq*pow(cos($phi),2);
  $zcm = 3 + 6*($utmz-1) - 180;//Central meridian of zone
  $A = ($lngd-$zcm)*$drad*cos($phi);
  //Calculate M
  $M = $phi*(1 - $esq*(1/4 + $esq*(3/64 + 5*$esq/256)));
  $M = $M - sin(2*$phi)*($esq*(3/8 + $esq*(3/32 + 45*$esq/1024)));
  $M = $M + sin(4*$phi)*($esq*$esq*(15/256 + $esq*45/1024));
  $M = $M - sin(6*$phi)*($esq*$esq*$esq*(35/3072));
  $M = $M*$a;//Arc length along standard meridian
  $M0 = 0;//M0 is M for some origin latitude other than zero. Not needed for standard UTM
  //Calculate UTM Values
  $x = $k0*$N*$A*(1 + $A*$A*((1-$T+$C)/6 + $A*$A*(5 - 18*$T + $T*$T + 72*$C -58*$e0sq)/120));//Easting relative to CM
  $x=$x+500000;//Easting standard 
  $y = $k0*($M - $M0 + $N*tan($phi)*($A*$A*(1/2 + $A*$A*((5 - $T + 9*$C + 4*$C*$C)/24 + $A*$A*(61 - 58*$T + $T*$T + 600*$C - 330*$e0sq)/720))));//Northing from equator

  if ($y < 0) {
    $y = 10000000+$y;
    $southern_hemisphere=true;
  }

  //Output into UTM coords
  return array(round($y,1),round($x,1),$utmz,$southern_hemisphere);
}

//------------------------------------------------------------------------------
// Convert UTM to Longitude/Latitude
//------------------------------------------------------------------------------
private static function convertUTMtoLL($north, $east, $zone=null, $southern_hemisphere=false){
  //Convert UTM Coordinates to Geographic
  $a = 6378137.0;
  $f = 1/298.2572236;
  $PI=3.1415926535897932384626433832795;
  $drad = $PI/180;//Convert degrees to radians

  $k0 = 0.9996;//scale on central meridian
  $b = $a*(1-$f);//polar axis.
  $e = sqrt(1 - ($b/$a)*($b/$a));//eccentricity
  $e0 = $e/sqrt(1 - $e*$e);//Called e prime in reference
  $esq = (1 - ($b/$a)*($b/$a));//e squared for use in expansions
  $e0sq = $e*$e/(1-$e*$e);// e0 squared - always even powers
  $x = floatval($east);
  if ($x<160000 || $x>840000){utils::rg2log("Outside permissible range of easting values\nResults may be unreliable\nUse with caution");} 
  $y = floatval($north);
  if ($y<0){utils::rg2log("Negative values not allowed\nResults may be unreliable\nUse with caution");}
  if ($y>10000000){utils::rg2log("Northing may not exceed 10,000,000\nResults may be unreliable\nUse with caution");}
  $e1 = (1 - sqrt(1 - $e*$e))/(1 + sqrt(1 - $e*$e));//Called e1 in USGS PP 1395 also
  $M0 = 0;//In case origin other than zero lat - not needed for standard UTM
  $M = $M0 + $y/$k0;//Arc length along standard meridian. 
  if ($southern_hemisphere === true){$M=$M0+($y-10000000)/$k;}
  $mu = $M/($a*(1 - $esq*(1/4 + $esq*(3/64 + 5*$esq/256))));
  $phi1 = $mu + $e1*(3/2 - 27*$e1*$e1/32)*sin(2*$mu) + $e1*$e1*(21/16 -55*$e1*$e1/32)*sin(4*$mu);//Footprint Latitude
  $phi1 = $phi1 + $e1*$e1*$e1*(sin(6*$mu)*151/96 + $e1*sin(8*$mu)*1097/512);
  $C1 = $e0sq*pow(cos($phi1),2);
  $T1 = pow(tan($phi1),2);
  $N1 = $a/sqrt(1-pow($e*sin($phi1),2));
  $R1 = $N1*(1-$e*$e)/(1-pow($e*sin($phi1),2));
  $D = ($x-500000)/($N1*$k0);
  $phi = ($D*$D)*(1/2 - $D*$D*(5 + 3*$T1 + 10*$C1 - 4*$C1*$C1 - 9*$e0sq)/24);
  $phi = $phi + pow($D,6)*(61 + 90*$T1 + 298*$C1 + 45*$T1*$T1 -252*$e0sq - 3*$C1*$C1)/720;
  $phi = $phi1 - ($N1*tan($phi1)/$R1)*$phi;
  
  //Latitude
  $latd = round($phi/$drad,6);
    
  //Longitude
  $lng = $D*(1 + $D*$D*((-1 -2*$T1 -$C1)/6 + $D*$D*(5 - 2*$C1 + 28*$T1 - 3*$C1*$C1 +8*$e0sq + 24*$T1*$T1)/120))/cos($phi1);
  
  $lngd = $lng/$drad;
  if ($zone) {
    $utmz = floatval($zone);
  } else {
    $utmz = 1 + floor(($lngd+180)/6);
  }
  $zcm = 3 + 6*($utmz-1) - 180;//Central meridian of zone
  $lngd = round($zcm+$lngd,6);

  //utils::rg2log("y=$north x=$east -> lat=$latd lon=$lngd");

  //Output Latitude / Longitude
  return array($latd, $lngd);
}

//------------------------------------------------------------------------------
// Convert WorldFile from UTM to WGS84
//------------------------------------------------------------------------------
private static function convertABCDEF_UTMtoWGS84($inA, $inB, $inC, $inD, $inE, $inF, $zone=null, $southern_hemisphere=false) {

  // calculation based on fixed points on map
  // x0, y0 is top left, x1, y1 is bottom right, x2, y2 is top right, x3, y3 is bottom left
  // 0, 1 and 2 are saved by the API, and must have these settings
  // 4 is just used here
  // save pixel values of these locations for map image
  $maxx = 3000;
  $maxy = 3000;
  
  $xpx = array(0, $maxx, $maxx, 0);
  $ypx = array(0, $maxy, 0, $maxy);

  // calculate the same locations using worldfile for the map
  $xsrc = array();
  $ysrc = array();

  // UTM coordinates by a worldfile
  for ($i = 0; $i < 4; $i++) {
    $xsrc[$i] = floatval($inA*$xpx[$i] + $inB*$ypx[$i] + $inC);
    $ysrc[$i] = floatval($inD*$xpx[$i] + $inE*$ypx[$i] + $inF);
  }
  // translate source georef to WGS84 (as in GPS file)
  for ($i = 0; $i < 4; $i++) {
    $ptx = $xsrc[$i];
    $pty = $ysrc[$i];
    list($piy[$i], $pix[$i]) = self::convertUTMtoLL($pty, $ptx, $zone, $southern_hemisphere);
  }
  // now need to create the worldfile for WGS84 to map image
  $wfC = $pix[0];
  $wfF = $piy[0];
  $wfA = ($pix[2] - $wfC) / $xpx[2];
  $wfB = ($pix[3] - $wfC) / $ypx[3];
  $wfD = ($piy[2] - $wfF) / $xpx[2];
  $wfE = ($piy[3] - $wfF) / $ypx[3];
  
  return array($wfA, $wfB, $wfC, $wfD, $wfE, $wfF);
}

private static function exp_to_dec($float_str)
// formats a floating point number string in decimal notation, supports signed floats, also supports non-standard formatting e.g. 0.2e+2 for 20
// e.g. '1.6E+6' to '1600000', '-4.566e-12' to '-0.000000000004566', '+34e+10' to '340000000000'
// Author: Bob
{
  // make sure its a standard php float string (i.e. change 0.2e+2 to 20)
  // php will automatically format floats decimally if they are within a certain range
  $float_str = (string)((float)($float_str));

  // if there is an E in the float string
  if(($pos = strpos(strtolower($float_str), 'e')) !== false)
  {
    // get either side of the E, e.g. 1.6E+6 => exp E+6, num 1.6
    $exp = substr($float_str, $pos+1);
    $num = substr($float_str, 0, $pos);
   
    // strip off num sign, if there is one, and leave it off if its + (not required)
    if((($num_sign = $num[0]) === '+') || ($num_sign === '-')) $num = substr($num, 1);
    else $num_sign = '';
    if($num_sign === '+') $num_sign = '';
   
    // strip off exponential sign ('+' or '-' as in 'E+6') if there is one, otherwise throw error, e.g. E+6 => '+'
    if((($exp_sign = $exp[0]) === '+') || ($exp_sign === '-')) $exp = substr($exp, 1);
    else trigger_error("Could not convert exponential notation to decimal notation: invalid float string '$float_str'", E_USER_ERROR);
   
    // get the number of decimal places to the right of the decimal point (or 0 if there is no dec point), e.g., 1.6 => 1
    $right_dec_places = (($dec_pos = strpos($num, '.')) === false) ? 0 : strlen(substr($num, $dec_pos+1));
    // get the number of decimal places to the left of the decimal point (or the length of the entire num if there is no dec point), e.g. 1.6 => 1
    $left_dec_places = ($dec_pos === false) ? strlen($num) : strlen(substr($num, 0, $dec_pos));
   
    // work out number of zeros from exp, exp sign and dec places, e.g. exp 6, exp sign +, dec places 1 => num zeros 5
    if($exp_sign === '+') $num_zeros = $exp - $right_dec_places;
    else $num_zeros = $exp - $left_dec_places;
   
    // build a string with $num_zeros zeros, e.g. '0' 5 times => '00000'
    $zeros = str_pad('', $num_zeros, '0');
   
    // strip decimal from num, e.g. 1.6 => 16
    if($dec_pos !== false) $num = str_replace('.', '', $num);
   
    // if positive exponent, return like 1600000
    if($exp_sign === '+') return $num_sign.$num.$zeros;
    // if negative exponent, return like 0.0000016
    else return $num_sign.'0.'.$zeros.$num;
  }
  // otherwise, assume already in decimal notation and return
  else return $float_str;
}
}
