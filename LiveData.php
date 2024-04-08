<?php

class LiveData {

    const MODE_DRIVE = 1; // >= 10kmh || -15kW and more
    const MODE_CHARGING = 2; // < 10kmh && >= 2kW
    const MODE_IDLE = 3; // else IDLE :)

    private $carIndex;
    private $liveData;
    private $prevRow;
    public $lastRow;
    private $startRow;
    private $debug;
    private $usedChargingTrueCount;
    public $carName;
    public $loaded;
    public $fileName;
    public $jsonData;
    public $startTimestamp;
    public $endTimestamp;

    /**
     * Live data
     */
    function __construct($index, $fileName, $carName) {

        $this->initData();
        $this->debug = false;
        $this->usedChargingTrueCount = 0;
        $this->loaded = 1;
        $this->carName = $carName;
        $this->fileName = $fileName;
        $this->startTimestamp = $this->endTimestamp = null;
        $this->carIndex = $index;
        $this->liveData = [];
        $this->liveData['minOdoKm'] = 0;
        $this->lastRow = false;

        // Checks
        if ($index == 1) {
            if (substr(strtolower($fileName), -5) != ".json") {
                die("JSON file required");
            }
        }
        if ($fileName != "" && !file_exists($fileName)) {
            echo sprintf("File %s not exists\n", $fileName);
        }

        if ($index == 1 || $fileName != "") {
            $this->loaded = true;
            $this->parseData();
        }
    }

    /**
     * Parse data
     */
    function parseData() {

        // Valid json data
        $data = file_get_contents($this->fileName);

        // normalize evDash sdcard format
        if (substr($data, 0, 1) != "[") {
            $cnt = 0;
            $dataArr = explode("\n", $data);
            foreach ($dataArr as $row) {
                if (substr(rtrim($row, "\r"), -2) != "},") {
                    echo "Invalid line: $row\n\n";
                }
                $cnt++;
                if ($cnt >= 5)
                    break;
            }
            unset($dataArr);
            $data = rtrim(rtrim($data, "\n"), ",");
            $data = "[" . $data . "]";
        }

        $tsFrom = getNum('tsfrom', 0);
        $tsTo = getNum('tsto', 0);

        $this->jsonData = json_decode($data, true);
        foreach ($this->jsonData as $key => &$row) {

            if ($this->carIndex == 2) {
                $row['currTime'] += (1 * 3600);
            }
            if ($tsFrom != 0 && $row['currTime'] < $tsFrom) {
                unset($this->jsonData[$key]);
                continue;
            }
            if ($tsTo != 0 && $row['currTime'] > $tsTo) {
                unset($this->jsonData[$key]);
                continue;
            }
            if ($row['lat'] < 20 || $row['lon'] < 7) {
                unset($this->jsonData[$key]);
                continue;
            }


            // Calculated battery management mode if not present
            if (!isset($row['bmMode'])) {
                $row['bmMode'] = "UNKNOWN";
            }
            if (!isset($row['chargingOn']))
                $row['chargingOn'] = false;
            //
            if ($row['odoKm'] <= 1000 || $row['socPerc'] == -1 || /* $row['currTime'] < 1533210449 || */
                    $row['alt'] == -501 ||
                    ($row['carType'] == 0 /* eniro */ && ($row['socPerc'] == 0 || $row['bWatC'] == -100 || $row['opTime'] == 0))
            ) {
                unset($this->jsonData[$key]);
                continue;
            }
            if ($row['alt'] < 100 || $row['alt'] > 2500) {
                $row['alt'] = ($row['alt'] < 100 ? 0 : ($row['alt'] > 2500 ? 2500 : $row['alt']));
            }
            if (isset($row['speedKmhGPS']) && $row['speedKmhGPS'] != -1) {
                $row['speedKmh'] = $row['speedKmhGPS'];
            }
            // Fix -1 speed
            if ($row['speedKmh'] <= 1) {
                $row['speedKmh'] = 0;
            }

            if ($this->liveData['minOdoKm'] == 0) {
                $this->liveData['minOdoKm'] = $row['odoKm'];
            }

            //
            if ($this->startTimestamp === null || $row["currTime"] < $this->startTimestamp) {
                $this->startTimestamp = $row["currTime"];
            }
            if ($this->endTimestamp === null || $row["currTime"] > $this->endTimestamp) {
                $this->endTimestamp = $row["currTime"];
            }
        }

        unset($data);
        //
    }

    /**
     * Init data
     */
    function initData() {
//
        $this->liveData['carName'] = $this->carName;
        $this->liveData['mode'] = self::MODE_IDLE;
        $this->liveData['modeCounter'] = 0;
        $this->liveData[self::MODE_DRIVE]['timeSec'] = 0;
        $this->liveData[self::MODE_DRIVE]['chargedKwh'] = 0;
        $this->liveData[self::MODE_DRIVE]['dischargedKwh'] = 0;
        $this->liveData[self::MODE_DRIVE]['odoKm'] = 0;
        $this->liveData[self::MODE_CHARGING]['timeSec'] = 0;
        $this->liveData[self::MODE_CHARGING]['chargedKwh'] = 0;
        $this->liveData[self::MODE_IDLE]['timeSec'] = 0;
        $this->liveData[self::MODE_IDLE]['dischargedKwh'] = 0;
        $this->liveData['stats'] = array();
//
        $this->prevRow = false;
        $this->startRow = false;
    }

    /**
     * Process row
     */
    function processRow($row) {

        if ($row !== false && ($row['odoKm'] == -1 || $row['socPerc'] == -1 || $row['cecKwh'] <= 0 || $row['cedKwh'] <= 0))
            return;

// Detect mode
        $sugMode = self::MODE_IDLE;
        if ($row !== false) {
            if ($row['chargingOn']) {
                $this->usedChargingTrueCount++;
            }

            if ($row['carType'] < 9 || $row['carType'] > 11) {
                if ($row['chargingOn'] || // log with charging=true support
                        ($this->usedChargingTrueCount < 10 && ($row['motorRpm'] == -1 || $row['motorRpm'] == 0) && $row['batPowKw'] > 0.5)) {
                    $sugMode = self::MODE_CHARGING;
                }
                if (($this->usedChargingTrueCount < 10 || !$row['chargingOn']) && $row['motorRpm'] > 0) {
                    $sugMode = self::MODE_DRIVE;
                }
            } else {
                if (isset($row['speedKmhGPS']) && $row['speedKmhGPS'] != -1) {
                    $row['speedKmh'] = $row['speedKmhGPS'];
                }
                if ($row['chargingOn']) {
                    $sugMode = self::MODE_CHARGING;
                }
                if ($row['speedKmh'] >= 5) {
                    $sugMode = self::MODE_DRIVE;
                }
            }
        }

// Evaluate
        if ($this->startRow === false) {
            $this->startRow = $row;
            $this->prevRow = $row;
            $this->liveData['mode'] = $sugMode;
        } else {
            $oldMode = $this->liveData['mode'];
            if ($row === false || $oldMode != $sugMode) {

                if ($row === false || $row['currTime'] - $this->prevRow['currTime'] > 60)
                    $r = $this->prevRow;
                else
                    $r = $row;

                if ($this->debug) {
                    echo "===============\n";
                    echo $oldMode . "\n";
                    echo "time " . ($r["currTime"] - $this->startRow["currTime"]) . "\n";
                    echo "odoKm " . ($r["odoKm"] - $this->startRow["odoKm"]) . "\n";
                    echo "cecKwh " . ($r["cecKwh"] - $this->startRow["cecKwh"]) . "\n";
                    echo "cedKwh " . ($r["cedKwh"] - $this->startRow["cedKwh"]) . "\n";
                }

                $this->liveData[$oldMode]['timeSec'] += round($r['currTime'] - $this->startRow['currTime'], 4);
                if (isset($this->liveData[$oldMode]['odoKm']))
                    $this->liveData[$oldMode]['odoKm'] += round($r['odoKm'] - $this->startRow['odoKm'], 4);
                if (isset($this->liveData[$oldMode]['chargedKwh']))
                    $this->liveData[$oldMode]['chargedKwh'] += round($r['cecKwh'] - $this->startRow['cecKwh'], 4);
                if (isset($this->liveData[$oldMode]['dischargedKwh']))
                    $this->liveData[$oldMode]['dischargedKwh'] -= round($r['cedKwh'] - $this->startRow['cedKwh'], 4);

                // Build stats
                if ($oldMode == self::MODE_DRIVE || $oldMode == self::MODE_CHARGING) {
                    $modify = false;
                    if (count($this->liveData['stats']) > 0) {
                        $statsRow = $this->liveData['stats'][count($this->liveData['stats']) - 1];
                        if ($statsRow['mode'] == $oldMode)
                            $modify = true;
                    }
                    //echo ("stop" . ($modify ? "1" : "0") . "\n\n");
                    if (!$modify) {
                        $statsRow = array();
                        $statsRow['mode'] = $oldMode;
                        $statsRow['initTime'] = $this->startRow['currTime'];
                        $statsRow['timeSec'] = $statsRow['odoKm'] = $statsRow['chargedKwh'] = $statsRow['dischargedKwh'] = 0;
                        $statsRow['startSocPerc'] = $r['socPerc'];
                    }
                    $statsRow['endTime'] = $r['currTime'];
                    $statsRow['timeSec'] += round($r['currTime'] - $this->startRow['currTime'], 4);
                    $statsRow['odoKm'] += round($r['odoKm'] - $this->startRow['odoKm'], 4);
                    $statsRow['chargedKwh'] += round($r['cecKwh'] - $this->startRow['cecKwh'], 4);
                    $statsRow['dischargedKwh'] -= round($r['cedKwh'] - $this->startRow['cedKwh'], 4);
                    $statsRow['endSocPerc'] = $r['socPerc'];
                    $statsRow['lat'] = $r['lat'];
                    $statsRow['lon'] = $r['lon'];
                    if ($modify) {
                        $this->liveData['stats'][count($this->liveData['stats']) - 1] = $statsRow;
                    } else {
                        $this->liveData['stats'][] = $statsRow;
                    }
                }

                $this->liveData['mode'] = $sugMode;
                $this->liveData['modeCounter']++;
                $this->startRow = $row;
            }
        }

        // Set
        $this->prevRow = $row;
        if ($row === false && $this->debug) {
            print_r($this->liveData['stats']);
            die("STOP");
        }
    }

    /**
     * Get data
     */
    function getData() {
        return $this->liveData;
    }

    /**
     * Get mode
     */
    function getMode() {
        return $this->liveData['mode'];
    }
}
