<?php

$fileName = "";
foreach ($_SERVER['argv'] as $key => $value) {
    if ($key == 0) {
        continue;
    }
    if (file_exists($value)) {
        $fileName = $value;
    }
}

if ($fileName == "") {
    die("Empty file");
}

$data = file_get_contents($fileName);
$data = rtrim(rtrim($data, "\n"), ",");
$data = "[" . $data . "]";
$jsonData = json_decode($data, true);

unset($data);
$data = [];
foreach ($jsonData as $row) {
    $newRow = [];
    foreach ($row as $key => $value) {
        if ($key != "cellMinV" && $key != "cellMaxV") {
            if (substr($key, 0, 1) == "c" && substr($key, -1) == "V") {
                continue;
            }
        }
        $newRow[$key] = $value;
    }
    $data[] = $newRow;
}

file_put_contents($fileName . "_2", json_encode($data));
