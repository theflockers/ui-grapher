<?php

$config['collectd_rrd_path'] = '/var/lib/collectd/rrd';

/**
 * Groups configuration
 *
 **/
$config['groups'] = array(
    'Barra Funda' => array( 
        array('name' => 'dft-sp-log001', 'value' => 'dft-sp-log001'),
        array('name' => 'fw01', 'value' => 'fw01'),
        array('name' => 'fw03', 'value' => 'fw03'),
    ),
    'Level3' => array(
        array('name' => 'Link 1Gbit', 'value' => 'Link_1Gbps_Level3'),
        array('name' => 'Link 100Mbit', 'value' => 'Link_bkp_100Mbps_Level3'),
    ),
);
