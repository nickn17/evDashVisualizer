<?php

/**
 * Get number parameter (prevents SQL injection)
 */
function getNum($key, $default = 0) {
    if (!isset($_GET[$key]))
        return ($default);
    $ret = str_replace(",", ".", trim($_GET[$key]));
    if (!is_numeric($ret))
        return ($default);
    return $ret;
}

/**
 * Get string parameter (prevents SQL injection)
 */
function getStr($key, $default = "") {
    global $database;
    if (!isset($_GET[$key]))
        return $default;
    $ret = $_GET[$key];
//    if (get_magic_quotes_gpc())
//        $ret = stripslashes($ret); //json_encode($ret, JSON_HEX_APOS)), true);
//    $ret = $database->encodeValue(addslashes($ret));
    return trim($ret);
}

/**
 * formatHourMin
 */
function formatHourMin($timeSec) {
    if (intval($timeSec / 3600) == 0)
        return sprintf("%dm", intval(($timeSec % 3600) / 60));
    return sprintf("%dh%dm", intval($timeSec / 3600), intval(($timeSec % 3600) / 60));
}

/**
 * lonToTile
 */
function lonToTile($long, $zoom) {
    return (($long + 180) / 360) * pow(2, $zoom);
}

/**
 * latToTile
 */
function latToTile($lat, $zoom) {
    return (1 - log(tan($lat * pi() / 180) + 1 / cos($lat * pi() / 180)) / pi()) / 2 * pow(2, $zoom);
}

/**
 * lonPerPixel
 */
function lonPerPixel($tileNo, $zoom) {
    return abs((($tileNo + 1) / pow(2, $zoom) * 360 - 180) - ($tileNo / pow(2, $zoom) * 360 - 180)) / 255;
}

/**
 * latPerPixel
 */
function latPerPixel($tileNo, $zoom) {
    $n = pi() - 2 * pi() * $tileNo / pow(2, $zoom);
    $a1 = (180 / pi() * tan(0.5 * (exp($n) - exp(-$n))));
    $n = pi() - 2 * pi() * ($tileNo + 1) / pow(2, $zoom);
    $a2 = (180 / pi() * tan(0.5 * (exp($n) - exp(-$n))));
    return abs($a2 - $a1) / 255;
}

/*  tileToLon(x: number, zoom: number): number {
  return (x / Math.pow(2, zoom) * 360 - 180);
  }

  tileToLat(y: number, zoom: number): number {
  const n = Math.PI - 2 * Math.PI * y / Math.pow(2, zoom);
  return (180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))));
  } */

/**
 * fetch title
 */
function fetchTile($url) {

    $cacheTileName = "cache/tile_" . md5($url) . ".jpg";
    if (file_exists($cacheTileName)) {
        return file_get_contents($cacheTileName);
    }

    usleep(500000); //0.5s
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "TileProxy/1.0");
    curl_setopt($ch, CURLOPT_URL, $url);
    $tile = curl_exec($ch);
    curl_close($ch);
    file_put_contents($cacheTileName, $tile);
    //die($url . "s");

    return $tile;
}
