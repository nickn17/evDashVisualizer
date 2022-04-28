<?php
require 'SVGGraph/autoloader.php';

function geoRevLoc ($lat, $lon)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "TileProxy/1.0");
    curl_setopt($ch, CURLOPT_URL, "https://nominatim.openstreetmap.org/reverse?lat=$lat&lon=$lon&format=json&accept-language=sk");
    $place = curl_exec($ch);
    curl_close($ch);
    $place = "[" . $place . "]";
    $place = json_decode($place, true);
//    print_r($place);
    $amenity       = isset($place[0]['address']['amenity'])       ?       $place[0]['address']['amenity']      . ', ' : '';
    $road          = isset($place[0]['address']['road'])          ?       $place[0]['address']['road']                : '';
    $house_number  = isset($place[0]['address']['house_number'])  ? ' ' . $place[0]['address']['house_number'] . ', ' : ', ';
    $city          = isset($place[0]['address']['city'])          ?       $place[0]['address']['city'] : 
                        ( isset($place[0]['address']['town'])     ? $place[0]['address']['town'] :  
                        ( isset($place[0]['address']['village'])  ? $place[0]['address']['village']                   : '' ));
    return ($amenity.$road.$house_number.$city);
}

$settings = [
  'auto_fit'                    => true,
  'thousands'                   => ' ',
  'decimal'                     => ',',
//  'datetime_keys'               => true,
//  'structured_data'             => true,
  'back_colour'                 => '#fff',
//  'back_stroke_width'           => 0,
//  'back_stroke_colour'          => '#eee',
//  'stroke_colour'               => '#000',

//  'axis_colour'                 => '#333',
  'axis_overlap'                => 2,
  'grid_colour'                 => '#666',
  'label_colour'                => '#000',
  'axis_font'                   => 'Verdana',
  'axis_font_size'              => 10,
  'label_v'                     => 'nabíjací výkon [kW]',
//  'label_colour_v'              => 'blue',
//  'units_y'                     => 'kW',
  'axis_min_h'                  => 0,
  'axis_max_h'                  => 100,
  'label_h'                     => '% SOC',

  'minimum_grid_spacing'        => 20,
  'show_subdivisions'           => true,
  'show_grid_subdivisions'      => true,
//  'grid_subdivision_colour'     => '#ccc',

  'line_stroke_width'           => 1,

  'marker_size'                 => 2,

  'legend_entry_height' => 10,
  'legend_position' => 'outer bottom 5 -5',
  'legend_stroke_width' => 0,
  'legend_shadow_opacity' => 0,
  
];

$markers = "circle square triangle cross x pentagon diamond hexagon octagon asterisk star threestar fourstar eightstar";
$settings['marker_type'] = explode(' ', $markers);


    $mode    = LiveData::MODE_IDLE;
    $oldMode = LiveData::MODE_IDLE;
    $oldSOC  = -1;

    foreach ($this->jsonData as $row) {
    
        if ($row === false)
            continue;
        if ($row['odoKm'] <= 0 || $row['socPerc'] == -1)
            continue;

        if ($row['carType'] < 9 || $row['carType'] > 11) {
            if (($row['motorRpm'] == -1 || $row['motorRpm'] == 0) && $row['batPowKw'] > 0.5) {
                $mode = LiveData::MODE_CHARGING;
            } else {
                $mode = LiveData::MODE_IDLE;
            }
            if ($row['motorRpm'] > 0) {
                $mode = LiveData::MODE_DRIVE;
            }
        } else {
//            if (isset($row['speedKmhGPS']) && $row['speedKmhGPS'] != -1) {
//                $row['speedKmh'] = $row['speedKmhGPS'];
//            }
            if ($row['speedKmh'] == 0 && $row['batPowKw'] > 0.5) {
                $mode = LiveData::MODE_CHARGING;
            } else {
                $mode = LiveData::MODE_IDLE;
            }
            if ($row['speedKmh'] > 0) {
                $mode = LiveData::MODE_DRIVE;
            }
        }
        
        if ($mode == LiveData::MODE_CHARGING && $row['socPerc'] != $oldSOC)
        {
            if ($oldSOC == -1)
                for ($i = 0; $i < $row['socPerc']; $i++)
                    $dat[$i] = null;                    
            $oldSOC = $row['socPerc'];             
            $dat[$oldSOC] = $row['batPowKw']; 
        } 
        
        if ($oldMode == LiveData::MODE_CHARGING && $mode != LiveData::MODE_CHARGING)
        {   
            $data [] = $dat;
            $dat = [];
            $oldSOC  = -1;
            $settings['legend_entries'][] = geoRevLoc($row['lat'], $row['lon']);
        }
        $oldMode = $mode;            
    }



$settings['pad_bottom']= 5 + 16 * count ($data);

$graph = new Goat1000\SVGGraph\SVGGraph(960, 520, $settings);

$graph->values($data);
//$graph->colours(array('blue','green'));
echo $graph->fetch('MultiLineGraph', false);
//print_r ($data);
//var_export ($data);
//var_export ($settings['legend_entries']);
?>
