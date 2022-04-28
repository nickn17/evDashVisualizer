<?php

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

    private $jsonData;
    //
    private $params;
    private $onlyStaticImage;
    private $image;
    private $imageMapBk;
    private $white;
    private $black;
    private $red;
    private $green;
    private $gridColor;
    private $width = 1920;
    private $height = 1080;
    private $darkMode;
    private $tileUrl;
    private $hideInfo;
    // Map
    protected $tileSize = 256;

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
        $this->tileUrl = 'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png';
        $this->tileUrl = 'https://tile-a.openstreetmap.fr/hot/{z}/{x}/{y}.png';
        $this->darkMode = getNum("dark", 0);
        $this->hideInfo = (getNum("info", 1) == 0);
        $this->speedup = getNum("speedup", 1);
        $this->bms = getNum("bms", 0);
        $this->liveData = new LiveData();
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
     * Prepare colors
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
     * Process
     */
    function preprocessData($jsonFileName, $onlyStaticImage = true) {

        $this->onlyStaticImage = $onlyStaticImage;
        $this->fileName = $jsonFileName;
        if (substr(strtolower($this->fileName), -5) != ".json")
            die("JSON file required");

        $data = file_get_contents($this->fileName);
        $data = rtrim(rtrim($data, "\n"), ",");
        $data = "[" . $data . "]";
//$data = '{"carType":0,"batTotalKwh":64,"currTime":1589013371,"opTime":4913451,"socPerc":52.5,"sohPerc":100,"powKwh100":41928.39,"speedKmh":0.093,"motorRpm":0,"odoKm":50460,"batPowKw":38.9934,"batPowA":104.4,"batV":373.5,"cecKwh":10829.4,"cedKwh":10131.4,"maxChKw":42.14,"maxDisKw":11.79,"cellMinV":3.8,"cellMaxV":3.8,"bMinC":8,"bMaxC":10,"bHeatC":20,"bInletC":20,"bFanSt":0,"bWatC":20,"tmpA":20,"tmpB":10,"tmpC":8,"tmpD":13,"auxPerc":65,"auxV":14.6,"auxA":15.231,"inC":22,"outC":7.5,"c1C":44,"c2C":6.5,"tFlC":15,"tFlBar":2.6,"tFrC":16,"tFrBar":2.5,"tRlC":13,"tRlBar":2.4,"tRrC":14,"tRrBar":2.5}';
        $this->jsonData = json_decode($data, true);
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
        foreach ($this->jsonData as $key => &$row) {
            // Calculated battery management mode if not present
            if (!isset($row['bmMode'])) {
                $row['bmMode'] = "-";
                $debug06 = (isset($row['debug2']) ? $row['debug2'] : "");
                $debug06 = str_replace("ATSH7E4/220106/", "", $debug06);
                if (strpos($debug06, "620106") !== false) {
                    $tempByte = hexdec(substr($debug06, 34, 2));
                    switch ($tempByte & 0xf) {
                        case 1: $row['bmMode'] = "LTR COOLING";
                            break;
                        case 3: $row['bmMode'] = "LTR";
                            break;
                        case 4: $row['bmMode'] = "COOLING";
                            break;
                        case 6: $row['bmMode'] = "OFF";
                            break;
                        case 0xE: $row['bmMode'] = "PTC HEATER";
                            break;
                        default: $row['bmMode'] = "UNKNOWN";
                    }
                }
                for ($i = 1; $i < 200; $i++) {
                    if (isset($row['c' . $i . 'V']))
                        unset($row['c' . $i . 'V']);
                }
            }
            //
            if ($row['odoKm'] <= 1000 || $row['socPerc'] == -1 || /* $row['currTime'] < 1533210449 || */
                    $row['alt'] == -501 ||
                    ($row['carType'] == 0 /* eniro */ && ($row['socPerc'] == 0 || $row['bWatC'] == -100 || $row['opTime'] == 0))
            ) {
                unset($this->jsonData[$key]);
                continue;
            }
            if (isset($row['speedKmhGPS']) && $row['speedKmhGPS'] != -1) {
                $row['speedKmh'] = $row['speedKmhGPS'];
            }

// xxxxx            
//          if ($row['speedKmh'] > 10)  $row['speedKmh'] = $row['speedKmh'] - 111;
// xxxxx            

            if (($row['odoKm'] != 1.677721e7) && ($this->params['maxOdoKm'] == -1 || $row['odoKm'] > $this->params['maxOdoKm']))
                $this->params['maxOdoKm'] = $row['odoKm'];
            if ($row['odoKm'] == 1.677721e7)
                $row['odoKm'] = $this->params['maxOdoKm'];
            

            $this->params['keyframes'] ++;
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
        if ($this->onlyStaticImage) {
            switch (getNum("m", 1)) {
                case 0: $this->renderSummary();
                    break;
                case 1: $this->renderMap();
                    break;
                case 2: $this->renderChargingGraph();
                    break;
            }
        } else {
            $this->renderMap();
            $this->renderSummary();
        }
    }

    /**
     * Render summary graph
     */
    function renderSummary() {

        if (!$this->onlyStaticImage) {
            $fp = fopen(str_replace(".json", "", $this->fileName) . '_sum.mjpeg', 'w');
        } else {
            $fp = fopen(str_replace(".json", "", $this->fileName) . '_sum.jpg', 'w');
        }

        // Render graphs
        imagesetthickness($this->image, 1);
        $this->font = 'fonts/RobotoCondensed-Light.ttf';
        $startFrame = getNum("frame");
        $startTime = $this->params['minCurrTime'];
        for ($frame = $startFrame; $frame < $this->params['keyframes']; $frame++) {

            if ($this->onlyStaticImage) {
                $frame = $this->params['keyframes'];
            }

            $this->params['lastOdoKm'] = -1;
            $this->params['lastOdoKmPosX'] = -1;

            imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $this->black);

            // Grid
            for ($i = -6; $i <= 13; $i++) {
                $x0 = $this->params['graph0x'];
                $x = $this->params['graph0x'] + (self::TIME_SCREEN_FRAME * $this->params['xStep']);
                imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * 10 * $i), $x, $this->params['graph0y'] - ($this->params['yStep'] * 10 * $i), $this->gridColor);
                imagettftext($this->image, 10, 0, $x0 - 32, $this->params['graph0y'] - ($this->params['yStep'] * 10 * $i), $this->white, $this->font, $i * 10);
            }

            // Prepare colors
            // print lines
            $prevRow = false;
            $cnt = 0;
            foreach ($this->jsonData as $row) {
                if ($row['odoKm'] == -1 || $row['socPerc'] == -1)
                    continue;
                if ($row['currTime'] > $startTime + self::TIME_SCREEN_SCROLL) {
                    $startTime = $row['currTime'] - self::TIME_SCREEN_SCROLL;
                }
                //
                if ($row['currTime'] >= $startTime) {
                    $x = $this->params['graph0x'] + (($row['currTime'] - $startTime) * $this->params['xStep']);
                    if ($prevRow !== false) {
                        $x0 = $this->params['graph0x'] + (($prevRow['currTime'] - $startTime) * $this->params['xStep']);
                        //
                        imagesetthickness($this->image, 1);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['tmpA']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['tmpA']), $this->fields['tmpA']['color']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['tmpB']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['tmpB']), $this->fields['tmpB']['color']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['tmpC']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['tmpC']), $this->fields['tmpC']['color']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['tmpD']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['tmpD']), $this->fields['tmpD']['color']);
                        //
                        if ($prevRow['speedKmh'] > 5 || $row['speedKmh'] > 5)
                            imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['speedKmh']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['speedKmh']), $this->fields['speedKmh']['color2']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['batPowKw']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['batPowKw']), ($this->params['chargingStartX'] > 0 ? $this->fields['batPowKw']['color'] : $this->fields['batPowKw']['color2']));
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['socPerc']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['socPerc']), $this->fields['socPerc']['color2']);
                        imagesetthickness($this->image, 2);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['bHeatC']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['bHeatC']), $this->fields['bHeatC']['color']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['bWatC']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['bWatC']), $this->fields['bWatC']['color']);
                        imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['bMinC']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['bMinC']), $this->fields['bMinC']['color']);
                    }
                    if ($x - $this->params['lastOdoKmPosX'] > 20 && $this->params['lastOdoKm'] != $row['odoKm']) {
                        imagettftext($this->image, 16, 90, $x, $this->height - 48, $this->white, $this->font, ($row['odoKm'] - $this->params['minOdoKm'] . " km"));
                        $this->params['lastOdoKm'] = $row['odoKm'];
                        $this->params['lastOdoKmPosX'] = $x;
                    }
                    if ($this->params['chargingStartX'] == -1 && ($row['chargingOn']/* || ($row['speedKmh'] < 3 && $row['batPowKw'] > 1) */)) {
                        $this->params['chargingStartX'] = $x;
                        $this->params['lastSocPerc'] = -1;
                        $this->params['lastSocPercPosX'] = -1;
                    } else if ($this->params['chargingStartX'] != -1) {
                        if ($x - $this->params['lastSocPercPosX'] > 20) {
                            imagettftext($this->image, 16, 90, $x, $this->params['graph0y'] + 64, $this->white, $this->font, "" . round($row['socPerc']) . " %");
                            $this->params['lastSocPerc'] = $row['socPerc'];
                            $this->params['lastSocPercPosX'] = $x;
                        }
                        if (!$row['chargingOn']/* || ($row['speedKmh'] > 10 || $row['batPowKw'] < 1) */) {
                            if ($prevRow !== false)
                                imageline($this->image, $x0, $this->params['graph0y'] - ($this->params['yStep'] * $prevRow['batPowKw']), $x, $this->params['graph0y'] - ($this->params['yStep'] * $row['batPowKw']), $this->fields['batPowKw']['color']);
                            imagettftext($this->image, 16, 0, $this->params['chargingStartX'], $this->height - 48, $this->white, $this->font, "CHARGING");
                            imagefilledrectangle($this->image, $this->params['chargingStartX'], $this->height - 32, ($x < $this->params['chargingStartX'] ? $x0 : $x), $this->height - 30, $this->fields['batPowKw']['color']);
                            $this->params['chargingStartX'] = -1;
                        }
                    }
                }
                //
                $prevRow = $row;
                $cnt++;
                if ($cnt > $frame)
                    break;
            }

            // Render parameter
            $tmpY = 48;
            foreach ($this->fields as $key => $obj) {

                if (gettype($obj) == "string" && $obj == "-") {
                    $tmpY += 8;
                    imageline($this->image, 32, $tmpY - 20, 300, $tmpY - 20, $this->white);
                    $tmpY += 4;
                    continue;
                }

                $text = "";
                if (isset($row[$key])) {
                    if (isset($obj['format'])) {
                        $row[$key] = sprintf($obj['format'], $row[$key]);
                    }
                    $text = $row[$key] . " " . $obj['unit'];
                    if ($key == "powKwh100" && $row["speedKmh"] < 40)
                        $text = "";
                    if ($key == "currTime")
                        $text = gmdate("y-m-d H:i:s", $row[$key]);
                    if (strlen($key) == 6 && substr($key, -3) == "Bar")
                        $text .= "/" . str_replace("Bar", "C", $row[str_replace("Bar", "C", $key)]) . "°C";
                    if ($obj['title'] != "")
                        $key = $obj['title'];
                }

                // key
                $box = imagettfbbox(16, 0, $this->font, $key);
                $textWidth = abs($box[4] - $box[0]);
                $textHeight = abs($box[5] - $box[1]);
                imagettftext($this->image, 16, 0, 180 - $textWidth, $tmpY, (isset($obj['color']) ? $obj['color'] : $this->white), $this->font, $key);
                // value
                if ($text != "") {
                    imagettftext($this->image, 16, 0, 200, $tmpY, (isset($obj['color']) ? $obj['color'] : $this->white), $this->font, $text);
                }
                $tmpY += 22;
            }

            if ($this->onlyStaticImage) {
                header('Content-type: image/jpeg');
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
            if ($this->speedup > 1)
                $frame += $this->speedup - 1;
        }

        // Free up memory
        fclose($fp);
        imagedestroy($this->image);
    }

    /**
     * Render map
     */
    function renderMap() {

        // Fetch map
        $this->imageMapBk = imagecreatetruecolor($this->width, $this->height);
        $this->lastRow = false;
        $this->params['lonCenter'] = $this->params['lonStartPoint'];
        $this->params['latCenter'] = $this->params['latStartPoint'];

        // Render graphs
        if (!$this->onlyStaticImage) {
            $fp = fopen(str_replace(".json", "", $this->fileName) . '_map.mjpeg', 'w');
        } else {
            $fp = fopen(str_replace(".json", "", $this->fileName) . '_map.jpg', 'w');
        }

        $eleStep = $this->width / $this->params['keyframes'];
        $stopFrame = getNum("frame", 0);
        for ($frame = 0; $frame < $this->params['keyframes']; $frame++) {

            if ($this->onlyStaticImage) {
                $frame = $this->params['keyframes'] /* / 10 */;
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
                        $tileImage = imagecreatefromstring($tileData);
                    } else {
                        $tileImage = imagecreate($this->tileSize, $this->tileSize);
                        $color = imagecolorallocate($tileImage, 255, 255, 255);
                        @imagestring($tileImage, 1, 127, 127, 'err', $color);
                    }
                    $destX = ($x - $startX) * $this->tileSize + $this->offsetX;
                    $destY = ($y - $startY) * $this->tileSize + $this->offsetY;
                    imagecopy($this->imageMapBk, $tileImage, $destX, $destY, 0, 0, $this->tileSize, $this->tileSize);
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

            $prevRow = false;
            $cnt = 0;
            $yStep = $this->height / ($this->params['latMax'] - $this->params['latMin']);
            $xStep = $this->width / ($this->params['lonMax'] - $this->params['lonMin']);
            //print_r($this->params);
            //echo "$xStep / $yStep";
            //die();

            $prevRow = false;
            $row = false;
            $cnt = 0;
            $this->liveData->initData();
            foreach ($this->jsonData as $row) {

                $this->liveData->processRow($row);

                if ($row['odoKm'] <= 0 || $row['socPerc'] == -1)
                    continue;
                if ($prevRow !== false && ($row['lat'] == -1 || $row['lon'] == -1)) {
                    $row['lat'] = $prevRow['lat'];
                    $row['lon'] = $prevRow['lon'];
                }
                if ($row['lat'] == -1 || $row['lon'] == -1)
                    continue;


                if ($prevRow !== false) {
                    // elevation graph
                    if (!$this->hideInfo) {
                        imagesetthickness($this->image, ($this->darkMode ? 1 : 1));
                        imageline($this->image, $cnt * $eleStep, $this->height - ($prevRow['alt'] / 5), ( $cnt * $eleStep) + 1, $this->height - ($row['alt'] / 5),
                                ($this->darkMode ? ($row['speedKmh'] > 5 ? $this->white : $this->red) : $this->red));
                    }
                    //
                    imagesetthickness($this->image, ($this->darkMode ? 2 : 3));
                    $x0 = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($prevRow['lon'], $this->params['zoom'])));
                    $y0 = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($prevRow['lat'], $this->params['zoom'])));
                    $x = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($row['lon'], $this->params['zoom'])));
                    $y = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($row['lat'], $this->params['zoom'])));
                    $i = abs($cnt - $frame) / 5;
                    if ($i > 50)
                        $i = 50;
                    $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, $i) : imagecolorallocatealpha($this->image, 0, 128, 40, $i));
                    imageline($this->image, $x0, $y0, $x, $y, $trackColor);
                }

                //
                $prevRow = $row;
                $cnt++;
                if ($cnt > $frame) {
                    break;
                }
            }

            if ($row !== false)
                imagettftext($this->image, 12, 0, ($cnt * $eleStep) + 10, $this->height - 64, $this->white, $this->font, $row['alt'] . "m");
            $this->liveData->processRow(false);

            if ($row !== false) {
                if ($row['odoKm'] != -1 && $row['socPerc'] != -1) {
                    $x = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($row['lon'], $this->params['zoom'])));
                    $y = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($row['lat'], $this->params['zoom'])));
                    $trackColor = ($this->darkMode ? imagecolorallocatealpha($this->image, 255, 196, 40, 0) : imagecolorallocatealpha($this->image, 0, 128, 40, 0));
                    imagefilledellipse($this->image, $x, $y, 12, 12, $trackColor);
                    imagettftext($this->image, 24, 0, $x + 16, $y + 16, $this->red, $this->font,
                            $this->hideInfo ?
                                    sprintf("%0.0fkm", $row['odoKm'] - $this->params['minOdoKm']) :
                                    sprintf("%2.0f%% %0.0fkm", $row['socPerc'], $row['odoKm'] - $this->params['minOdoKm'])
                    );
                    // Scroll map
                    if ($x < 650) {
                        $step = abs(650 - $x);
                        $this->params['lonCenter'] -= ($step <= 0 ? 1 : $step) * $lonPerPixel;
                    }
                    if ($x > $this->width - 400) {
                        $step = abs($x - ($this->width - 400));
                        $this->params['lonCenter'] += ($step <= 0 ? 1 : $step) * $lonPerPixel;
                    }
                    if ($y < 400) {
                        $step = abs(400 - $y);
                        $this->params['latCenter'] += ($step <= 0 ? 1 : $step) * $latPerPixel;
                    }
                    if ($y > $this->height - 400) {
                        $step = abs($y - ($this->height - 400));
                        $this->params['latCenter'] -= ($step <= 0 ? 1 : $step) * $latPerPixel;
                    }
                }

                if (!$this->hideInfo) {
                    $data = $this->liveData->getData();
                    $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
                    $textColor = ($this->darkMode ? $this->white : $this->black);
                    if (!$this->darkMode)
                        $opacity = imagecolorallocatealpha($this->image, 255, 255, 255, 48);
                    imagefilledrectangle($this->image, 0, 0, $this->width, 110, $opacity);
                    $px = 175;
                    $this->drawMapOsd($px, 48, $textColor, "SOC" . sprintf("%3.0f", $row['socPerc']) . "%", " ");
                    $px += 160;
                    $this->drawMapOsd($px, 48, $textColor, $data[LiveData::MODE_DRIVE]['odoKm'] . "km", " ");
                    $px += 200;
                    $this->drawMapOsd($px, 48, $textColor, "alt." . $row['alt'] . "m", " ");
                    $px += 80;
                    $this->drawMapOsd($px, 48, $textColor, " ", gmdate("Y-m-d H:i:s", $row["currTime"]));
                    $px += 600;
                    $this->drawMapOsd($px, 48, $textColor, "  " . round($row['speedKmh']) . "km/h", " ");

                    $px = 1450;
                    $this->drawMapOsd($px, 48, $textColor, "OUT" . sprintf("%2.0f", $row['outC']) . "°C", " ");
                    $px += 250;
                    $this->drawMapOsd($px, 48, $textColor, "BAT." . sprintf("%2.0f", $row['bMinC']) . "-" . sprintf("%2.0f", $row['bMaxC']) . "°C", " ");
                    $px += 176;
                    $this->drawMapOsd($px, 48, $textColor, "IN" . sprintf("%2.0f", $row['inC']) . "°C", " ");


                    $px = 10;
                    $this->drawMapOsd($px, 96, $textColor, " ", "DRIVE");
                    $px += 260;
                    $this->drawMapOsd($px, 96, $textColor, formatHourMin($data[LiveData::MODE_DRIVE]['timeSec'])); //"48h25m");
                    $px += 130;
                    $this->drawMapOsd($px, 96, $textColor, "+" . sprintf("%0.1f", $data[LiveData::MODE_DRIVE]['chargedKwh'], 1), round($data[LiveData::MODE_DRIVE]['dischargedKwh'], 1) . "kWh");
                    $px += 500;
                    if ($data[LiveData::MODE_DRIVE]['odoKm'] >= 1)
                        $this->drawMapOsd($px, 96, $textColor, "~" . sprintf("%0.1f", -($data[LiveData::MODE_DRIVE]['chargedKwh'] + $data[LiveData::MODE_DRIVE]['dischargedKwh']) / $data[LiveData::MODE_DRIVE]['odoKm'] * 100, 1) . "kWh/100km", " ");
                    $px += 20;
                    $this->drawMapOsd($px, 96, $textColor, " ", "CHARGING");
                    $px += 340;
                    $this->drawMapOsd($px, 96, $textColor, formatHourMin($data[LiveData::MODE_CHARGING]['timeSec'])); //"48h25m");
                    $px += 200;
                    $this->drawMapOsd($px, 96, $textColor, "+" . sprintf("%0.1f", $data[LiveData::MODE_CHARGING]['chargedKwh'], 1) . "kWh", " ");
                    $px += 20;
                    $this->drawMapOsd($px, 96, $textColor, " ", "IDLE");
                    $px += 240;
                    $this->drawMapOsd($px, 96, $textColor, formatHourMin($data[LiveData::MODE_IDLE]['timeSec'])); //"48h25m");
                    $px += 170;
                    $this->drawMapOsd($px, 96, $textColor, sprintf("%0.1f", $data[LiveData::MODE_IDLE]['dischargedKwh'], 1) . "kWh", " ");

                    // bms
                    if ($this->bms == 1) {
                        $y = 700;
                        $coldgate = ($row['bMaxC'] >= 35 ? "RAPIDGATE " . sprintf("%1.0f", $row['bMaxC']) . "°C" :
                                ($row['bMinC'] < 5 ? "COLDGATE LEVEL 3 (" . sprintf("%1.0f", $row['bMinC']) . "°C)" :
                                ($row['bMinC'] < 15 ? "COLDGATE LEVEL 2 (" . sprintf("%1.0f", $row['bMinC']) . "°C)" :
                                ($row['bMinC'] < 25 ? "COLDGATE LEVEL 1 (" . sprintf("%1.0f", $row['bMinC']) . "°C)" :
                                "OPTIMAL BAT.TEMPERATURE (25-34°C)" ))));
                    /*    imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "COOLANT");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font, sprintf("%1.0f", $row['bWatC']) . "°C");
                        $y += 48;
                        imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "MOTOR");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font, sprintf("%1.0f", $row['invC']) . " / " . sprintf("%1.0f", $row['motC']) . "°C");
                        $y += 48;
                    */    imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "BATTERY");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, sprintf("%1.0f", $row['bMinC']) . " / " . sprintf("%1.0f", $row['bMaxC']) . "°C");
                        if ($row['chargingOn'] == 1) {
                            $y += 48;
                            imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font,
                                    "CHARGING");
                            imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2,
                                    round($row['batPowKw'], 0) . "kW");
                        }
                        $y += 48;
                        imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "POWER KW");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, sprintf("%1.2f", $row['batPowKw']) . "");
                        $y += 48;
                        imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "CELL MIN [V]");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, sprintf("%1.2f", $row['cellMinV']) . "");
                        $y += 48;
                        imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "BMS MODE");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, ( $row['bmMode'] == "ÜNKNOWN" ? "" : $row['bmMode']));
                        $y += 48;
                        imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "STATE");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, $coldgate);
                        $y += 48;
                    /*    imagettftext($this->image, 38, 0, 25, $y, $this->black, $this->font, "CELL MAX [V]");
                        imagettftext($this->image, 38, 0, 325, $y, $this->black, $this->font2, sprintf("%1.2f", $row['cellMaxV']) . "");
                    */}

                    // itinerar
                    $y = 180;
                    $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
                    if (!$this->darkMode)
                        $opacity = imagecolorallocatealpha($this->image, 255, 255, 255, 48);
                    imagefilledrectangle($this->image, 20, $y + 4, 610, $y + 4 - 32, $opacity);
                    imagettftext($this->image, 20, 0, 30, $y, $textColor, $this->font, "MODE");
                    $text = "TIME";
                    $box = imagettfbbox(20, 0, $this->font, $text);
                    imagettftext($this->image, 20, 0, 190 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                    $text = "DIS/CH.KWH";
                    $box = imagettfbbox(20, 0, $this->font, $text);
                    imagettftext($this->image, 20, 0, 340 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                    $text = "KM/SOC";
                    $box = imagettfbbox(20, 0, $this->font, $text);
                    imagettftext($this->image, 20, 0, 440 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                    $text = "~KWH/100KM";
                    $box = imagettfbbox(20, 0, $this->font, $text);
                    imagettftext($this->image, 20, 0, 600 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                    $y += 32;
                    $lastSoc = 0;
                    foreach (array_slice($data['stats'], -10, 10) as $statsRow) {
                        $opacity = imagecolorallocatealpha($this->image, 0, 0, 0, 72);
                        if (!$this->darkMode)
                            $opacity = imagecolorallocatealpha($this->image, 255, 255, 255, 48);
                        imagefilledrectangle($this->image, 20, $y + 4, 610, $y + 4 - 32, $opacity);
                        //
                        imagettftext($this->image, 20, 0, 30, $y, $textColor, $this->font, ($statsRow['mode'] == LiveData::MODE_DRIVE ? "DRIVE" : "CHARGE"));
                        // time
                        $text = formatHourMin($statsRow['timeSec']);
                        $box = imagettfbbox(20, 0, $this->font, $text);
                        imagettftext($this->image, 20, 0, 190 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                        //
                        if ($statsRow['dischargedKwh'] != 0) {
                            $text = sprintf("%2.1f", $statsRow['dischargedKwh']);
                            $box = imagettfbbox(20, 0, $this->font, $text);
                            imagettftext($this->image, 20, 0, 270 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                        }
                        //
                        $text = "+" . sprintf("%.1f", $statsRow['chargedKwh']);
                        $box = imagettfbbox(20, 0, $this->font, $text);
                        imagettftext($this->image, 20, 0, 340 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                        //
                        if ($statsRow['mode'] == LiveData::MODE_DRIVE) {
                            $text = $statsRow['odoKm'] . " km";
                            $box = imagettfbbox(20, 0, $this->font, $text);
                            imagettftext($this->image, 20, 0, 440 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                            if ($statsRow['odoKm'] > 0) {
                                $text = "~" . -sprintf("%0.1f", round(($statsRow['dischargedKwh'] + $statsRow['chargedKwh']) / $statsRow['odoKm'] * 100, 1));
                                $box = imagettfbbox(20, 0, $this->font, $text);
                                imagettftext($this->image, 20, 0, 510 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                            }
                            $lastSoc = round($statsRow['endSocPerc']);
                        }
                        if ($statsRow['mode'] == LiveData::MODE_CHARGING) {
                            $text = $lastSoc . "->" . round($statsRow['endSocPerc']) . ($statsRow['endSocPerc'] == 100 ? "" : "%");
                            $box = imagettfbbox(20, 0, $this->font, $text);
                            imagettftext($this->image, 20, 0, 440 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                            if ($statsRow['timeSec'] > 0) {
                                $text = "~" . sprintf("%0.1f", ($statsRow['chargedKwh'] / ($statsRow['timeSec'] / 3600)), 1) . " kW";
                                $box = imagettfbbox(20, 0, $this->font, $text);
                                imagettftext($this->image, 20, 0, 562 - (abs($box[4] - $box[0])), $y, $textColor, $this->font, $text);
                            }
                            //
                            $mx = floor(($this->width / 2) - $this->tileSize * ( $this->centerX - lonToTile($statsRow['lon'], $this->params['zoom'])));
                            $my = floor(($this->height / 2) - $this->tileSize * ($this->centerY - latToTile($statsRow['lat'], $this->params['zoom'])));
                            imagefilledellipse($this->image, $mx, $my, 12, 12, $this->green);
                        }
                        $y += 32;
                    }
                }
            }

            $textColor = ($this->darkMode ? $this->white : $this->black);
            imagettftext($this->image, 12, 0, 8, $this->height - 8, $textColor, $this->font, 'map © OpenStreetMap, data © OpenStreetMap contributors, © SRTM, Tiles style by Humanitarian OpenStreetMap Team hosted by OpenStreetMap France.');
            if ($this->onlyStaticImage) {
                header('Content-type: image/jpeg');
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
            if ($this->speedup > 1)
                $frame += $this->speedup - 1;
        }

        // Free up memory
        fclose($fp);
    }

    /**
     * Render chargin graph
     */
    function renderChargingGraph() {

//        if (!$this->onlyStaticImage) {
            $fp = fopen(str_replace(".json", "", $this->fileName) . '_chr.svg', 'w');
//        }
//        if ($this->onlyStaticImage) {
//            header('Content-type: image/svg');
//        } else {
            ob_start();
//        }
//        imagejpeg($this->image);
        require 'svggraph.php';

//        if ($this->onlyStaticImage) {
//            die();
//        }
        $value = ob_get_contents();
        fwrite($fp, $value);
        ob_end_clean();
        fclose($fp);            
  
/*  
        $this->liveData->initData();
        foreach ($this->jsonData as $row) {
            $this->liveData->processRow($row);
        }
        $this->liveData->processRow(false);

        $sumData = $this->liveData->getData();
        foreach ($sumData["stats"] as $sumData) {
            if ($sumData['mode'] == LiveData::MODE_CHARGING) {
                print_r($sumData);
            }
        }
*/
    }

    /**
     * OSD
     */
    private function drawMapOsd($x, $y, $textColor, $left, $right = " ") {
        $box = imagettfbbox(32, 0, $this->font, $left);
        $textWidth = abs($box[4] - $box[0]);
        imagettftext($this->image, 32, 0, $x - $textWidth, $y, $textColor, $this->font, $left);
        imagettftext($this->image, 32, 0, $x + 16, $y, $textColor, $this->font, $right);
    }

}

$overview = new EvDashboardOverview();
$overview->preprocessData(getStr("filename", "demo_data.json"), (PHP_SAPI !== 'cli'));
