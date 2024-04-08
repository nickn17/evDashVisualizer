<?php

/*
 * Parameters
 * dark = 0/1
 * info = 0/1 (all info panels)
 * bms = 0/1 bms data
 * speedup = 1
 * center = 0/1 center map
 * zoom = 6-16 (openstreetmap)
 * m = 0 graph  1 map 2 charging.graph (mode for static image)
 * static
 * frame

  php index.php filename=230106_netherland.json  zoom=11 speedup=2 bms=1

 */

// Install
// composer require goat1000/svggraph
//  ffmpeg -i test.mjpeg -pix_fmt yuv420p -b:v 4000k -c:v libx264 test.mp4
error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
ini_set('error_reporting', E_ALL & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', 'On');
ini_set('memory_limit', '2048M');

if (!extension_loaded("curl")) {
    die("Enable curl extension in your php.ini\n");
}
if (!extension_loaded("gd")) {
    die("Enable gd extension in your php.ini\n");
}

include("global.php");
include("LiveData.php");

/**
 *
 */
class EvDashboardOverview {

    const TIME_SCREEN_FRAME = 4200; // seconds
    const TIME_SCREEN_SCROLL = 3600; // seconds

    private $params;
    private $onlyStaticImage;
    private $image;
    private $imageMapBk;
    private $white;
    private $black;
    private $red;
    private $green;
    private $gridColor;
    private $width = 3840; //1920;
    private $height = 2160; //1080;
    private $darkMode;
    private $tileUrl;
    private $hideInfo;
    protected $font;
    protected $font2;
    //
    // Map
    protected $tileSize = 512;

    /**
     * Init
     */
    function __construct() {

        // convert CLI params to GET
        if (PHP_SAPI === 'cli') {
            if (isset($_SERVER['argc']) > 0) {
                foreach ($_SERVER['argv'] as $key => $value) {
                    if ($key != 0) {
                        $argumentList = explode("&", $value);
                        foreach ($argumentList as $key1 => $value1) {
                            $keyValuePairs = explode("=", $value1);
                            $myKey = $keyValuePairs[0];
                            $myValue = $keyValuePairs[1];
                            $_GET[$myKey] = $myValue;
                            $_REQUEST[$myKey] = $myValue;
                        }
                    }
                }
            }
        }

        // Init structures
        // HD tiles
        $this->tileUrl = 'https://b.osm.rrze.fau.de/osmhd/{z}/{x}/{y}.png';
        $this->darkMode = getNum("dark", 0);
        $this->hideInfo = (getNum("info", 1) == 0);
        $this->centerMap = getNum("center", 0);
        //$this->speedup = getNum("speedup", 1);
        //$this->bms = getNum("bms", 0);
        $this->liveData = [];
        $this->fields = array(
            //"carType" => array("title" => "", "unit" => ""),
            "batTotalKwh" => array("title" => "Battery total", "format" => "%02.0f", "unit" => "kWh"),
            "currTime" => array("title" => "Current time", "unit" => ""),
            "opTime" => array("title" => "Operation time", "unit" => "s"),
            "cecKwh" => array("title" => "CEC", "format" => "%03.1f", "unit" => "kWh"),
            "cedKwh" => array("title" => "CED", "format" => "%03.1f", "unit" => "kWh"),
            "-",
            "socPerc" => array("title" => "SOC", "format" => "%02.0f", "unit" => "%"),
            "sohPerc" => array("title" => "SOH", "format" => "%02.1f", "unit" => "%"),
            "speedKmh" => array("title" => "Speed", "format" => "%02.0f", "unit" => "km/h"),
            "odoKm" => array("title" => "Odometer", "format" => "%03.0f", "unit" => "km"),
            "alt" => array("title" => "Altitude", "format" => "%03.0f", "unit" => "m"),
            "-",
            "batPowKw" => array("title" => "Bat.power", "format" => "%03.1f", "unit" => "kW"),
            "powKwh100" => array("title" => "kWh/100km", "format" => "%03.1f", "unit" => ""),
            "batPowA" => array("title" => "Bat.current", "format" => "%03.1f", "unit" => "A"),
            "batV" => array("title" => "Bat.voltage", "format" => "%03.1f", "unit" => "V"),
            "maxChKw" => array("title" => "Avail.charg.power", "format" => "%03.1f", "unit" => "kW"),
//            "maxDisKw" => array("title" => "Avail.disch.power", "format" => "%03.1f", "unit" => "kW"),
            "cellMinV" => array("title" => "Cell min.voltage", "format" => "%03.2f", "unit" => "V"),
            "cellMaxV" => array("title" => "Cell max.voltage", "format" => "%03.2f", "unit" => "V"),
            "-",
            "bmMode" => array("title" => "Bat.management", "unit" => ""),
            "bMinC" => array("title" => "Bat.min.temp.", "format" => "%02.0f", "unit" => "°C"),
            "bMaxC" => array("title" => "Bat.max.temp.", "format" => "%02.0f", "unit" => "°C"),
            "bHeatC" => array("title" => "Bat.heater.temp.", "format" => "%02.0f", "unit" => "°C"),
            "bWatC" => array("title" => "Water cooling temp", "format" => "%02.0f", "unit" => "°C"),
//            "bFanSt" => array("title" => "Bat.FAN status", "unit" => ""),
            "bInletC" => array("title" => "Bat.inlet temp.", "format" => "%02.0f", "unit" => "°C"),
            "tmpA" => array("title" => "Unknown temp.A", "format" => "%02.0f", "unit" => "°C"),
            "tmpB" => array("title" => "Unknown temp.B", "format" => "%02.0f", "unit" => "°C"),
            "tmpC" => array("title" => "Unknown temp.C", "format" => "%02.0f", "unit" => "°C"),
            "tmpD" => array("title" => "Unknown temp.D", "format" => "%02.0f", "unit" => "°C"),
            "motC" => array("title" => "Motor temp.", "format" => "%02.0f", "unit" => "°C"),
            "invC" => array("title" => "Inverter temp.", "format" => "%02.0f", "unit" => "°C"),
            "-",
            "auxPerc" => array("title" => "AUX (12V bat.)", "format" => "%02.0f", "unit" => "%"),
            "auxV" => array("title" => "AUX voltage", "format" => "%03.1f", "unit" => "V"),
            "auxA" => array("title" => "AUX amps.", "format" => "%03.1f", "unit" => "A"),
            "-",
            "inC" => array("title" => "Indoor temp.", "format" => "%03.1f", "unit" => "°C"),
            "outC" => array("title" => "Outdoor temp.", "format" => "%03.1f", "unit" => "°C"),
            "c1C" => array("title" => "Coolant temp.1", "format" => "%03.1f", "unit" => "°C"),
            "c2C" => array("title" => "Coolant temp.2", "format" => "%03.1f", "unit" => "°C"),
            "-",
            "tFlBar" => array("title" => "Front left tire", "format" => "%03.1f", "unit" => "Bar"),
            "tFrBar" => array("title" => "Front right tire", "format" => "%03.1f", "unit" => "Bar"),
            "tRlBar" => array("title" => "Rear left tire", "format" => "%03.1f", "unit" => "Bar"),
            "tRrBar" => array("title" => "Rear right tire", "format" => "%03.1f", "unit" => "Bar"),
        );
    }

    /**
     * Prepare fonts and colors
     */
    private function prepareColors() {

        $this->font = 'fonts/RobotoCondensed-Light.ttf';
        $this->font2 = 'fonts/RobotoCondensed-Bold.ttf';
        $this->white = imagecolorallocate($this->image, 255, 255, 255);
        $this->black = imagecolorallocate($this->image, 0, 0, 0);
        $this->red = imagecolorallocate($this->image, 255, 0, 0);
        $this->green = imagecolorallocate($this->image, 0, 255, 0);
        $this->gridColor = imagecolorallocate($this->image, 24, 24, 24);
        $this->fields['socPerc']['color'] = imagecolorallocate($this->image, 255, 192, 16);
        $this->fields['socPerc']['color2'] = imagecolorallocatealpha($this->image, 255, 192, 16, 80);
        $this->fields['speedKmh']['color'] = imagecolorallocate($this->image, 0, 255, 255);
        $this->fields['speedKmh']['color2'] = imagecolorallocatealpha($this->image, 0, 255, 255, 80);
        $this->fields['batPowKw']['color'] = imagecolorallocate($this->image, 128, 255, 128);
        $this->fields['batPowKw']['color2'] = imagecolorallocatealpha($this->image, 128, 255, 128, 120);
        $this->fields['bHeatC']['color'] = imagecolorallocate($this->image, 255, 0, 0);
        $this->fields['bMinC']['color'] = imagecolorallocate($this->image, 64, 64, 192);
        $this->fields['bWatC']['color'] = imagecolorallocate($this->image, 160, 64, 160);
        $this->fields['tmpA']['color'] = imagecolorallocate($this->image, 40, 40, 40);
        $this->fields['tmpB']['color'] = imagecolorallocate($this->image, 64, 64, 64);
        $this->fields['tmpC']['color'] = imagecolorallocate($this->image, 72, 72, 72);
        $this->fields['tmpD']['color'] = imagecolorallocate($this->image, 80, 80, 80);
    }

    /**
     * Process data
     */
    function preprocessData($jsonFileName, $jsonFileName2, $onlyStaticImage = true) {

        if (getNum('static', 0) == 1) {
            $onlyStaticImage = true;
        }
        $this->onlyStaticImage = $onlyStaticImage;
        $this->liveData[1] = new LiveData(1, $jsonFileName, getStr("car"));
        $this->liveData[2] = new LiveData(2, $jsonFileName2, getStr("car2"));

        // Prepare data
        $this->params = array(
            "keyframes" => 0,
            "minOdoKm" => -1,
            "maxOdoKm" => -1,
            "minCurrTime" => -1,
            "maxCurrTime" => -1,
            "chargingStartX" => -1,
            "latMin" => -1,
            "latMax" => -1,
            "lonMin" => -1,
            "lonMax" => -1,
            "latStartPoint" => -1,
            "lonStartPoint" => -1,
        );

        foreach ($this->liveData[1]->jsonData as $key => &$row) {

            if (($row['odoKm'] != 1.677721e7) && ($this->params['maxOdoKm'] == -1 || $row['odoKm'] > $this->params['maxOdoKm'])) {
                $this->params['maxOdoKm'] = $row['odoKm'];
            }
            if ($row['odoKm'] == 1.677721e7) {
                $row['odoKm'] = $this->params['maxOdoKm'];
            }

            $this->params['keyframes']++;

            if ($this->params['minOdoKm'] == -1 || $row['odoKm'] < $this->params['minOdoKm'])
                $this->params['minOdoKm'] = $row['odoKm'];
            if ($this->params['minCurrTime'] == -1 || $row['currTime'] < $this->params['minCurrTime'])
                $this->params['minCurrTime'] = $row['currTime'];
            if ($this->params['maxCurrTime'] == -1 || $row['currTime'] > $this->params['maxCurrTime'])
                $this->params['maxCurrTime'] = $row['currTime'];
            if ($this->params['latMin'] == -1 || ($row['lat'] != -1 && $row['lat'] < $this->params['latMin']))
                $this->params['latMin'] = $row['lat'];
            if ($this->params['latMax'] == -1 || ($row['lat'] != -1 && $row['lat'] > $this->params['latMax']))
                $this->params['latMax'] = $row['lat'];
            if ($this->params['lonMin'] == -1 || ($row['lon'] != -1 && $row['lon'] < $this->params['lonMin']))
                $this->params['lonMin'] = $row['lon'];
            if ($this->params['lonMax'] == -1 || ($row['lon'] != -1 && $row['lon'] > $this->params['lonMax']))
                $this->params['lonMax'] = $row['lon'];
            if ($this->params['latStartPoint'] == -1 && $row['lat'] != -1)
                $this->params['latStartPoint'] = $row['lat'];
            if ($this->params['lonStartPoint'] == -1 && $row['lon'] != -1)
                $this->params['lonStartPoint'] = $row['lon'];
        }
        $this->params['graph0x'] = 400;
        $this->params['graph0y'] = $this->height * 0.66;
        $this->params['xStep'] = ($this->width - $this->params['graph0x'] - 32) / self::TIME_SCREEN_FRAME;
        $this->params['yStep'] = ($this->height - 96) / 200;
        $this->params['latCenter'] = ($this->params['latMax'] - $this->params['latMin']) / 2 + $this->params['latMin'];
        $this->params['lonCenter'] = ($this->params['lonMax'] - $this->params['lonMin']) / 2 + $this->params['lonMin'];
        $this->params['zoom'] = getNum("zoom", 12);

        //print_r($this->params);        die();
        if ($this->params['keyframes'] == 0) {
            die("no keyframes");
        }

        $this->image = imagecreatetruecolor($this->width, $this->height);
        $this->prepareColors();
        $this->renderMap();
    }

    /**
     * Render map
     */
    function renderMap() {

        // Fetch map
        $this->imageMapBk = imagecreatetruecolor($this->width, $this->height);
        $this->lastRow = false;
        if (!$this->centerMap) {
            $this->params['lonCenter'] = $this->params['lonStartPoint'];
            $this->params['latCenter'] = $this->params['latStartPoint'];
        }

        // Render graphs
        if (!$this->onlyStaticImage) {
            $fp = fopen(str_replace(".json", "", $this->liveData[1]->fileName) . '_map.mjpeg', 'w');
        } else {
            $fp = fopen(str_replace(".json", "", $this->liveData[1]->fileName) . '_map.jpg', 'w');
        }

        $startTimestamp = $this->liveData[1]->startTimestamp;
        $endTimestamp = $this->liveData[1]->endTimestamp;
        if ($this->liveData[2]->loaded && $this->liveData[2]->startTimestamp !== null) {
            if ($this->liveData[2]->startTimestamp < $startTimestamp) {
                $startTimestamp = $this->liveData[2]->startTimestamp;
            }
            if ($this->liveData[2]->endTimestamp > $endTimestamp) {
                $endTimestamp = $this->liveData[2]->endTimestamp;
            }
        }

        /* $eleStep = $this->width / $this->params['keyframes'];
          $stopFrame = getNum("frame", 0);
          for ($frame = 0; $frame < $this->params['keyframes']; $frame++) { */
        $pieAngle = $textX = $textY = 0;
        $maxTimeStamp = $startTimestamp;
        while ($maxTimeStamp < $endTimestamp) {
            $maxTimeStamp += 5;
            $pieAngle = ($pieAngle >= 360 ? 0 : $pieAngle + 5 );
            if (!$this->onlyStaticImage) {
                echo "1";
            } else {
                $maxTimeStamp = $endTimestamp;
            }

            // start of render map background
            $this->centerX = lonToTile($this->params['lonCenter'], $this->params['zoom']);
            $this->centerY = latToTile($this->params['latCenter'], $this->params['zoom']);
            $this->offsetX = floor((floor($this->centerX) - $this->centerX) * $this->tileSize);
            $this->offsetY = floor((floor($this->centerY) - $this->centerY) * $this->tileSize);
            $startX = floor($this->centerX - ($this->width / $this->tileSize) / 2);
            $startY = floor($this->centerY - ($this->height / $this->tileSize) / 2);
            $endX = ceil($this->centerX + ($this->width / $this->tileSize) / 2);
            $endY = ceil($this->centerY + ($this->height / $this->tileSize) / 2);
            $this->offsetX = -floor(($this->centerX - floor($this->centerX)) * $this->tileSize);
            $this->offsetY = -floor(($this->centerY - floor($this->centerY)) * $this->tileSize);
            $this->offsetX += floor($this->width / 2);
            $this->offsetY += floor($this->height / 2);
            $this->offsetX += floor($startX - floor($this->centerX)) * $this->tileSize;
            $this->offsetY += floor($startY - floor($this->centerY)) * $this->tileSize;
            $lonPerPixel = lonPerPixel($startX, $this->params['zoom']);
            $latPerPixel = latPerPixel($startY, $this->params['zoom']);
            for ($x = $startX; $x <= $endX; $x++) {
                for ($y = $startY; $y <= $endY; $y++) {
                    $url = str_replace(array('{z}', '{x}', '{y}'), array($this->params['zoom'], $x, $y), $this->tileUrl);
                    $tileData = fetchTile($url);
                    if ($tileData) {
                        $tileImage = @imagecreatefromstring($tileData);
                    } else {
                        $tileImage = imagecreate($this->tileSize, $this->tileSize);
                        $color = imagecolorallocate($tileImage, 255, 255, 255);
                        @imagestring($tileImage, 1, 127, 127, 'err', $color);
                    }
                    $destX = ($x - $startX) * $this->tileSize + $this->offsetX;
                    $destY = ($y - $startY) * $this->tileSize + $this->offsetY;
                    @imagecopy($this->imageMapBk, $tileImage, $destX, $destY, 0, 0, $this->tileSize, $this->tileSize);
                }
            }
            // end of render map background

            $this->params['lastOdoKm'] = -1;
            $this->params['lastOdoKmPosX'] = -1;
            imagecopy($this->image, $this->imageMapBk, 0, 0, 0, 0, $this->width, $this->height);
            if ($this->darkMode) {
                imagefilter($this->image, IMG_FILTER_NEGATE);
                $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 100);
            } else {
                //$opacity = imagecolorallocatealpha($this->image, 255, 255, 255, 50);
                $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
            }
            imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $opacity);

            $cnt = 0;
            $yStep = $this->height / ($this->params['latMax'] - $this->params['latMin']);
            $xStep = $this->width / ($this->params['lonMax'] - $this->params['lonMin']);
            //print_r($this->params);
            //echo "$xStep / $yStep";
            //die();
            // Loop cars
            foreach ($this->liveData as $index => &$liveData) {

                if (!$liveData->loaded) {
                    continue;
                }
                if (!$this->onlyStaticImage) {
                    echo "2";
                }
                if ($index == 1) {
                    $lastTextY = 0;
                }


                $cnt = 0;
                $row = $prevRow = false;
                $liveData->initData();
                if (!empty($liveData->jsonData) && is_array($liveData->jsonData)) {
                    foreach ($liveData->jsonData as $row) {

                        if ($row['currTime'] > $maxTimeStamp) {
                            break;
                        }

                        $liveData->processRow($row);

                        if ($row['odoKm'] <= 0 || $row['socPerc'] == -1)
                            continue;
                        if ($prevRow !== false && ($row['lat'] == -1 || $row['lon'] == -1)) {
                            $row['lat'] = $prevRow['lat'];
                            $row['lon'] = $prevRow['lon'];
                        }
                        if ($row['lat'] == -1 || $row['lon'] == -1)
                            continue;

                        if ($prevRow !== false) {

                            // Elevation graph
                            if ($index == 1 && !$this->hideInfo) {
                                imagesetthickness($this->image, ($this->darkMode ? 1 : 1));
                                $elepos = round($this->width * 0.99 * (($row['currTime'] - $startTimestamp) / ($endTimestamp - $startTimestamp)));
                                if ($row['alt'] < 1500 && $prevRow['alt'] < 1500) {
                                    imageline($this->image, $elepos, $this->height - ($this->liveData[2]->loaded ? 16 : 16 ) - ($prevRow['alt'] / 2), ( $elepos) + 1, $this->height - ($this->liveData[2]->loaded ? 16 : 16 ) - ($row['alt'] / 2),
                                            ($this->darkMode ? ($row['speedKmh'] > 5 ? $this->white : $this->red) : $this->red));
                                }
                            }

                            // Route
                            $x0 = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($prevRow['lon'], $this->params['zoom'])));
                            $y0 = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($prevRow['lat'], $this->params['zoom'])));
                            $x = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($row['lon'], $this->params['zoom'])));
                            $y = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($row['lat'], $this->params['zoom'])));
                            if (($x0 < 0 && $x < 0) || ($y0 < 0 && $y < 0) || ($x0 > $this->width && $x > $this->width) || ($y0 > $this->height && $y > $this->height)) {
                                // Lat/lon optimizer
                            } else {
                                $i = 50; //abs($cnt - $frame) / 5;
                                $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, ($i > 50 ? 50 : $i)) : imagecolorallocatealpha($this->image, 0, 128, 40, ($i > 50 ? 50 : $i)));
                                $textColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, ($i > 50 ? 50 : $i)) : imagecolorallocate($this->image, 0, 128, 0));
                                if ($index == 2) {
                                    $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 40, 196, 255, ($i > 50 ? 50 : $i)) : imagecolorallocatealpha($this->image, 0, 40, 128, ($i > 50 ? 50 : $i)));
                                    $textColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 40, 196, 255, ($i > 50 ? 50 : $i)) : imagecolorallocate($this->image, 0, 0, 128));
                                }
                                imagesetthickness($this->image, ($this->darkMode ? 3 : 7));
                                imageline($this->image, $x0 + ($index == 2 ? 5 : 0), $y0 - ($index == 2 ? 5 : 0), $x + ($index == 2 ? 5 : 0), $y - ($index == 2 ? 5 : 0), $trackColor);

                                // Charging
                                if ($this->onlyStaticImage && $this->liveData[1]->getMode() == LiveData::MODE_CHARGING) {
                                    imagefilledellipse($this->image, $x, $y, 24, 24, $trackColor);
                                }
                            }
                        }

                        //
                        $cnt++;
                        $prevRow = $row;
                        $liveData->lastRow = $row;
                    }

                    if (!$this->onlyStaticImage) {
                        echo "4";
                    }
                }
            }

            // Last position/summary
            foreach ($this->liveData as $index => &$liveData) {

                if (!$liveData->loaded) {
                    continue;
                }
                $row = $liveData->lastRow;

                // Elevation text
                if ($index == 1 && $row !== false) {
                    if (!$this->hideInfo) {
                        $elepos = round($this->width * (($row['currTime'] - $startTimestamp) / ($endTimestamp - $startTimestamp)));
                        imagettftext($this->image, 12, 0, $elepos + 10, $this->height - 64, $this->red, $this->font, $row['alt'] . "m");
                    }
                }
                $liveData->processRow(false);
                if ($row !== false) {

                    $data = $liveData->getData();
                    if ($row['odoKm'] != -1 && $row['socPerc'] != -1 && $row['lat'] != 0.0 && $row['lon'] != 0.0 && $row['lat'] != -1.0 && $row['lon'] != -1.0) {
                        $x = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($row['lon'], $this->params['zoom'])));
                        $y = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($row['lat'], $this->params['zoom'])));
                        $i = 50;
                        $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, ($i > 50 ? 50 : $i)) : imagecolorallocatealpha($this->image, 0, 128, 40, ($i > 50 ? 50 : $i)));
                        $textColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, ($i > 50 ? 50 : $i)) : imagecolorallocate($this->image, 0, 128, 0));
                        if ($index == 2) {
                            $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 40, 196, 255, ($i > 50 ? 50 : $i)) : imagecolorallocatealpha($this->image, 0, 40, 128, ($i > 50 ? 50 : $i)));
                            $textColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 40, 196, 255, ($i > 50 ? 50 : $i)) : imagecolorallocate($this->image, 0, 0, 128));
                        }

                        $pieAngleDev = ($index == 1 ? 270 : 90) + 30 + $pieAngle;
                        $s = ($index == 1 ? 30 : 36) + 10;
                        imagesetthickness($this->image, 3);
                        imagefilledellipse($this->image, $x, $y, 20, 20, $trackColor);
                        imagefilledarc($this->image, $x, $y, 32, 32, $pieAngleDev, $pieAngleDev + 160, $trackColor, IMG_ARC_NOFILL);
                        imagefilledarc($this->image, $x, $y, 32 + $s, 32 + $s, $pieAngleDev, $pieAngleDev + 160, $trackColor, IMG_ARC_NOFILL);
                        imagesetthickness($this->image, 5);
                        imagefilledarc($this->image, $x, $y, 48 + $s, 48 + $s, -$pieAngleDev, -$pieAngleDev - 90, $trackColor, IMG_ARC_NOFILL);
                        /*                            break;
                          imagesetthickness($this->image, 3);
                          imagefilledellipse($this->image, $x, $y, 20, 20, $trackColor);
                          imagefilledarc($this->image, $x, $y, 32, 32, ($index == 1 ? 90 : 270) + $pieAngle, ($index == 1 ? 270 : 90) + $pieAngle, $trackColor, IMG_ARC_NOFILL);
                          $s = ($index == 1 ? 30 : 34) + 5 - ($pieAngle % 5);
                          $c = ($index == 1 ? 10 : 14);
                          imageline($this->image, $x - $s, $y - $s, $x - $s + $c, $y - $s, $trackColor);
                          imageline($this->image, $x - $s, $y - $s, $x - $s, $y - $s + $c, $trackColor);
                          imageline($this->image, $x - $s, $y + $s, $x - $s + $c, $y + $s, $trackColor);
                          imageline($this->image, $x - $s, $y + $s, $x - $s, $y + $s - $c, $trackColor);
                          imageline($this->image, $x + $s, $y - $s, $x + $s - $c, $y - $s, $trackColor);
                          imageline($this->image, $x + $s, $y - $s, $x + $s, $y - $s + $c, $trackColor);
                          imageline($this->image, $x + $s, $y + $s, $x + $s - $c, $y + $s, $trackColor);
                          imageline($this->image, $x + $s, $y + $s, $x + $s, $y + $s - $c, $trackColor);
                          $s = ($index == 1 ? 42 : 44);
                          $c = ($index == 1 ? 20 : 16);
                          imageline($this->image, $x - $s, $y - $s, $x - $s + $c, $y - $s, $trackColor);
                          imageline($this->image, $x - $s, $y - $s, $x - $s, $y - $s + $c, $trackColor);
                          imageline($this->image, $x - $s, $y + $s, $x - $s + $c, $y + $s, $trackColor);
                          imageline($this->image, $x - $s, $y + $s, $x - $s, $y + $s - $c, $trackColor);
                          imageline($this->image, $x + $s, $y - $s, $x + $s - $c, $y - $s, $trackColor);
                          imageline($this->image, $x + $s, $y - $s, $x + $s, $y - $s + $c, $trackColor);
                          imageline($this->image, $x + $s, $y + $s, $x + $s - $c, $y + $s, $trackColor);
                          imageline($this->image, $x + $s, $y + $s, $x + $s, $y + $s - $c, $trackColor);
                         */
                        //if (!$this->onlyStaticImage)
                        $transWhite = imagecolorallocatealpha($this->image, 255, 255, 255, 72);
                        if ($index == 1) {
                            $textX = $x + 96;
                            $textY = $y + 96;
                            $this->imageroundedrectangle($this->image, $textX - 20, $textY - 40, $textX + 400, $textY + 52, 16, $transWhite);
                        } else {
                            $textY += 40;
                        }

                        imagettftext($this->image, 32, 0, $textX - 2, $textY - 2, $this->white, $this->font,
                                $this->hideInfo ?
                                        sprintf("%0.0fkm", $row['odoKm'] - $data['minOdoKm']) :
                                        sprintf("%2.0f%% %0.0fkm %s", $row['socPerc'], $row['odoKm'] - $data['minOdoKm'],
                                                ($row['chargingOn'] ? strval(round($row['batPowKw'], 0)) . "kW" : strval(round($row['speedKmh'], 0) . "km/h")
                                                ))
                        );
                        imagettftext($this->image, 32, 0, $textX, $textY, $textColor, $this->font,
                                $this->hideInfo ?
                                        sprintf("%0.0fkm", $row['odoKm'] - $data['minOdoKm']) :
                                        sprintf("%2.0f%% %0.0fkm %s", $row['socPerc'], $row['odoKm'] - $data['minOdoKm'],
                                                ($row['chargingOn'] ? strval(round($row['batPowKw'], 0)) . "kW" : strval(round($row['speedKmh'], 0) . "km/h")
                                                ))
                        );

                        // Scroll map
                        if ($x < 1300) {
                            $step = abs(1300 - $x) / 4;
                            $this->params['lonCenter'] -= ($step <= 0 ? 1 : $step) * $lonPerPixel;
                        }
                        if ($x > $this->width - 800) {
                            $step = abs($x - ($this->width - 800)) / 4;
                            $this->params['lonCenter'] += ($step <= 0 ? 1 : $step) * $lonPerPixel;
                        }
                        if ($y < 800) {
                            $step = abs(800 - $y) / 4;
                            $this->params['latCenter'] += ($step <= 0 ? 1 : $step) * $latPerPixel;
                        }
                        if ($y > $this->height - 800) {
                            $step = abs($y - ($this->height - 800)) / 4;
                            $this->params['latCenter'] -= ($step <= 0 ? 1 : $step) * $latPerPixel;
                        }
                    }

                    if (!$this->hideInfo) {
                        $this->renderMapPanel($index, ($index == 2 ? 200 : 0), $data, $row);
                        $this->renderMapItinerar($index, ($index == 2 ? 1210 : 420), $data, $row);
                    }
                }
            }

            if (!$this->onlyStaticImage) {
                echo "5";
            }
            // Map copyright
            $textColor = ($this->darkMode ? $this->white : $this->black);
            $text = 'map © OpenStreetMap, data © OpenStreetMap contributors, © SRTM, HD tiles by osm.rrze.fau.de.';
            $bb = imagettfbbox(24, 0, $this->font, $text);
            imagettftext($this->image, 12, 0, $this->width - $bb[2] - 8, $this->height - ($this->liveData[2]->loaded ? 0 : 0) - 8, $textColor, $this->font, $text);

            // Render image
            if ($this->onlyStaticImage) {
                if (!headers_sent()) {
                    header('Content-type: image/jpeg');
                }
            } else {
                ob_start();
            }
            imagejpeg($this->image);
            if ($this->onlyStaticImage) {
                die();
            }
            $value = ob_get_contents();
            fwrite($fp, $value);
            ob_end_clean();
        }
        if (!$this->onlyStaticImage) {
            echo "9";
        }

        // Free up memory
        fclose($fp);
    }

    /**
     * Render map panel
     */
    function renderMapPanel($index = 1, $y, $data, $row) {

        $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
        $textColor = ($this->darkMode ? $this->white : $this->white);
        if (!$this->darkMode) {
            if ($index == 1) {
                $opacity = imagecolorallocatealpha($this->image, 0, 96, 0, 48);
            } else {
                $opacity = imagecolorallocatealpha($this->image, 0, 0, 128, 48);
            }
        }

        imagefilledrectangle($this->image, 0, $y + 0, $this->width, $y + 200, $opacity);
        $px = 300;
        $this->drawMapText($px, $y + 80, $textColor, "SOC" . sprintf("%3.0f", $row['socPerc']) . "%", " ");
        $px += 320;
        $this->drawMapText($px, $y + 80, $textColor, $data[LiveData::MODE_DRIVE]['odoKm'] . "km", " ");
        $px += 460;
        $this->drawMapText($px, $y + 80, $textColor, "odo." . $row['odoKm'] . "km", " ");
        $px += 320;
        $this->drawMapText($px, $y + 80, $textColor, "alt." . $row['alt'] . "m", " ");
        $px += 40;
        $this->drawMapText($px, $y + 80, $textColor, " ", gmdate("y-m-d H:i:s", $row["currTime"]));
        $px += 1060;

        $px = 2340;
        $this->drawMapText($px, $y + 80, $textColor, sprintf("%1.1f", $row['batPowKw']) . "kW", " ");
        $px += 900;
        if (isset($row['cellMinV'])) {
            $this->drawMapText($px, $y + 80, $textColor, "BMS " . sprintf("%1.2f", $row['cellMinV']) . "V/" . sprintf("%2.0f", $row['bMinC']) . "-" . sprintf("%2.0f", $row['bMaxC']) . "°C"
                    . ($row['bmMode'] == "UNKNOWN" ? "" : " / " . $row['bmMode'])
                    , " ");
        }
//                        imagettftext($this->image, 26, 0, 25, $y, $this->black, $this->font, "BMS MODE");
//                        imagettftext($this->image, 26, 0, 325, $y, $this->black, $this->font2, ( $row['bmMode'] == "ÜNKNOWN" ? "" : $row['bmMode']));

        $px += 570;
        $this->drawMapText($px, $y + 80, $textColor, "IN" . sprintf("%2.0f", $row['inC']) . "/OUT" . sprintf("%2.0f", $row['outC']) . "°C", " ");

        $px = 15;
        $this->drawMapText($px, $y + 172, $textColor, " ", "DRIVE");
        $px += 500;
        $this->drawMapText($px, $y + 172, $textColor, formatHourMin($data[LiveData::MODE_DRIVE]['timeSec'])); //"48h25m");
        $px += 220;
        $this->drawMapText($px, $y + 172, $textColor, "+" . sprintf("%0.1f", $data[LiveData::MODE_DRIVE]['chargedKwh'], 1), round($data[LiveData::MODE_DRIVE]['dischargedKwh'], 1) . "kWh");
        $px += 960;
        if ($data[LiveData::MODE_DRIVE]['odoKm'] >= 1)
            $this->drawMapText($px, $y + 172, $textColor, "~" . sprintf("%0.1f", -($data[LiveData::MODE_DRIVE]['chargedKwh'] + $data[LiveData::MODE_DRIVE]['dischargedKwh']) / $data[LiveData::MODE_DRIVE]['odoKm'] * 100, 1) . "kWh/100km", " ");
        $px += 340;
        $this->drawMapText($px, $y + 172, $textColor, "  " . round($row['speedKmh']) . "km/h", " ");
        $px += 20;
        $this->drawMapText($px, $y + 172, $textColor, " ", "CHARG.");
        $px += 560;
        $this->drawMapText($px, $y + 172, $textColor, formatHourMin($data[LiveData::MODE_CHARGING]['timeSec'])); //"48h25m");
        $px += 400;
        $this->drawMapText($px, $y + 172, $textColor, "+" . sprintf("%0.1f", $data[LiveData::MODE_CHARGING]['chargedKwh'], 1) . "kWh", " ");
        $px += 40;
        $this->drawMapText($px, $y + 172, $textColor, " ", "IDLE");
        $px += 400;
        $this->drawMapText($px, $y + 172, $textColor, formatHourMin($data[LiveData::MODE_IDLE]['timeSec'])); //"48h25m");
        $px += 340;
        $this->drawMapText($px, $y + 172, $textColor, sprintf("%0.1f", $data[LiveData::MODE_IDLE]['dischargedKwh'], 1) . "kWh", " ");
    }

    /**
     * OSD
     */
    private function drawMapText($x, $y, $textColor, $left, $right = " ") {

        $box = imagettfbbox(58, 0, $this->font, $left);
        $textWidth = abs($box[4] - $box[0]);
        imagettftext($this->image, 58, 0, $x - $textWidth, $y, $textColor, $this->font, $left);
        imagettftext($this->image, 58, 0, $x + 16, $y, $textColor, $this->font, $right);
    }

    /**
     * Rounded rectangle
     */
    function imageroundedrectangle(&$img, $x1, $y1, $x2, $y2, $r, $color) {

        $r = min($r, floor(min(($x2 - $x1) / 2, ($y2 - $y1) / 2)));
        // render corners
        imagefilledarc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 0, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);

        // middle fill, left fill, right fill
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x1 + $r, $y2 - $r, $color);
        imagefilledrectangle($img, $x2 - $r, $y1 + $r, $x2, $y2 - $r, $color);
        return true;
    }

    /**
     * Render map itinerar
     */
    function renderMapItinerar($index, $y, $data, $row) {

        // itinerar
        $y = 180 + $y;
        $textColor = ($this->darkMode ? $this->white : $this->black);
        $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
        if (!$this->darkMode) {
            if ($index == 1) {
                $opacity = imagecolorallocatealpha($this->image, 192, 255, 192, 64);
            } else {
                $opacity = imagecolorallocatealpha($this->image, 192, 192, 255, 64);
            }
        }

        // pneu
        $x = ($index == 1 ? 40 : 500);
        $y2 = ($index == 1 ? 1150 : 1200);
        imagettftext($this->image, 26, 0, $x, $y2, $textColor, $this->font2, $row['tFlC'] . "°C/" . round($row['tFlBar'], 1) . "bar");
        imagettftext($this->image, 26, 0, $x, $y2 + 50, $textColor, $this->font2, $row['tRlC'] . "°C/" . round($row['tRlBar'], 1) . "bar");
        imagettftext($this->image, 26, 0, $x + 200, $y2, $textColor, $this->font2, $row['tFrC'] . "°C/" . round($row['tFrBar'], 1) . "bar");
        imagettftext($this->image, 26, 0, $x + 200, $y2 + 50, $textColor, $this->font2, $row['tRrC'] . "°C/" . round($row['tRrBar'], 1) . "bar");

        // itinerar
        $carName = $data['carName'];
        imagefilledrectangle($this->image, 20, $y + 4, 910, $y + 4 - 64 - ($carName == "" ? 0 : 64), $opacity);
        if ($carName != "") {
            imagettftext($this->image, 38, 0, 50, $y - 64, $textColor, $this->font2, $carName);
        }
        imagettftext($this->image, 26, 0, 50, $y - 10, $textColor, $this->font2, "MODE");
        $text = "TIME";
        $box = imagettfbbox(26, 0, $this->font2, $text);
        imagettftext($this->image, 26, 0, 280 - (abs($box[4] - $box[0])), $y - 10, $textColor, $this->font2, $text);
        $text = "DIS/CH.KWH";
        $box = imagettfbbox(26, 0, $this->font2, $text);
        imagettftext($this->image, 26, 0, 500 - (abs($box[4] - $box[0])), $y - 10, $textColor, $this->font2, $text);
        $text = "KM/SOC";
        $box = imagettfbbox(26, 0, $this->font2, $text);
        imagettftext($this->image, 26, 0, 640 - (abs($box[4] - $box[0])), $y - 10, $textColor, $this->font2, $text);
        $text = "~KWH/100KM";
        $box = imagettfbbox(26, 0, $this->font2, $text);
        imagettftext($this->image, 26, 0, 870 - (abs($box[4] - $box[0])), $y - 10, $textColor, $this->font2, $text);
        $y += 48;
        $lastSoc = 0;
        foreach (array_slice($data['stats'], -10, 10) as $statsRow) {
            $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
            if (!$this->darkMode)
                $opacity = imagecolorallocatealpha($this->image, 255, 255, 255, 48);
            imagefilledrectangle($this->image, 20, $y + 4, 910, $y + 4 - 48, $opacity);
            //
            imagettftext($this->image, 26, 0, 50, $y, $textColor, $this->font2, ($statsRow['mode'] == LiveData::MODE_DRIVE ? "DRIVE" : "CHARGE"));
            // time
            $text = formatHourMin($statsRow['timeSec']);
            $box = imagettfbbox(26, 0, $this->font2, $text);
            imagettftext($this->image, 26, 0, 280 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
            //
            if ($statsRow['dischargedKwh'] != 0) {
                $text = sprintf("%2.1f", $statsRow['dischargedKwh']);
                $box = imagettfbbox(26, 0, $this->font2, $text);
                imagettftext($this->image, 26, 0, 400 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
            }
            //
            $text = "+" . sprintf("%.1f", $statsRow['chargedKwh']);
            $box = imagettfbbox(26, 0, $this->font2, $text);
            imagettftext($this->image, 26, 0, 500 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
            //
            if ($statsRow['mode'] == LiveData::MODE_DRIVE) {
                $text = $statsRow['odoKm'] . " km";
                $box = imagettfbbox(26, 0, $this->font2, $text);
                imagettftext($this->image, 26, 0, 640 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
                if ($statsRow['odoKm'] > 0) {
                    $text = "~" . -sprintf("%0.1f", round(($statsRow['dischargedKwh'] + $statsRow['chargedKwh']) / $statsRow['odoKm'] * 100, 1));
                    $box = imagettfbbox(26, 0, $this->font2, $text);
                    imagettftext($this->image, 26, 0, 770 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
                }
                $lastSoc = round($statsRow['endSocPerc']);
            }
            if ($statsRow['mode'] == LiveData::MODE_CHARGING) {
                $text = $lastSoc . "->" . round($statsRow['endSocPerc']) . ($statsRow['endSocPerc'] == 100 ? "" : "%");
                $box = imagettfbbox(26, 0, $this->font2, $text);
                imagettftext($this->image, 26, 0, 640 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
                if ($statsRow['timeSec'] > 0) {
                    $text = "~" . sprintf("%0.1f", ($statsRow['chargedKwh'] / ($statsRow['timeSec'] / 3600)), 1) . " kW";
                    $box = imagettfbbox(26, 0, $this->font2, $text);
                    imagettftext($this->image, 26, 0, 820 - (abs($box[4] - $box[0])), $y, $textColor, $this->font2, $text);
                }
                //
                $mx = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($statsRow['lon'], $this->params['zoom'])));
                $my = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($statsRow['lat'], $this->params['zoom'])));
                imagefilledellipse($this->image, $mx, $my, 12, 12, $this->green);
            }
            $y += 48;
        }
        imagefilledrectangle($this->image, 20, $y + 4 - 32, 910, $y + 4 - 48, $opacity);
    }
}

$overview = new EvDashboardOverview();
$overview->preprocessData(getStr("filename", "demo_data.json"), getStr("filename2", ""), (PHP_SAPI !== 'cli'));
