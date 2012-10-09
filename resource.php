<?php

if(!isset($_REQUEST['action']) or strlen($_REQUEST['action']) == 0) {
    header('HTTP/1.0 404 Not found');
    die('404');
}

$action = $_REQUEST['action'];

require_once('functions.php');
require_once('config.php');

$collectd = new Collectd($config['collectd_rrd_path']);


switch($action) {
    case "list-hosts":
        if(!empty($config['groups'])) {
            echo json_encode(array('group' => $config['groups'])); 
        }else {
            echo $collectd->getHosts();
        }
    break;
    case "service":
        $host = $_REQUEST['host'];
        echo $collectd->getHostServices($host);
    break;
    case "items":
        $host       = $_REQUEST['host'];
        $service    = $_REQUEST['service'];
        echo $collectd->getHostServiceItems($host, $service);
    break;
    case "graph":
        $host       = $_REQUEST['host'];
        $service    = $_REQUEST['service'];
        $item       = $_REQUEST['item'];
        $interval   = $_REQUEST['interval'];
    
        $graph      = $collectd->graph($host, $service, $item);
        $graph->render($interval, 'area');
    break;

}
