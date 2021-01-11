<?php

class LiveData {

    const MODE_DRIVE = 1; // >= 10kmh || -15kW and more
    const MODE_CHARGING = 2; // < 10kmh && >= 2kW
    const MODE_IDLE = 3; // else IDLE :)

    private $liveData;
    private $prevRow;
    private $startRow;
    private $debug;

    /**
     * Live data
     */
    function __construct() {

        $this->initData();
        $this->debug = false;
    }

    /**
     * Init data
     */
    function initData() {
//
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
            if (($row['motorRpm'] == -1 || $row['motorRpm'] == 0) && $row['batPowKw'] > 0.5) {
                $sugMode = self::MODE_CHARGING;
            }
            if ($row['motorRpm'] > 0) {
                $sugMode = self::MODE_DRIVE;
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
                $this->liveData['modeCounter'] ++;
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

}
