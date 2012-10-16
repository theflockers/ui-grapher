<?php
/**
 * @class    Collectd
 * @author   Leandro Mendes<leandro.mendes@dafiti.com.br>
 * @package  monitoring
 * @brief    Abstract the rrd funcions and paths of collectd.
 **/
class Collectd {

	private $__datadir;
	private $__entries;

	public function __construct($collectd_home) {
		$this->__datadir = $collectd_home;
	}

    /**
	 * @function 	__read
     * @brief 		Performs a directory read.
	 * @retun 		true
	 **/
	private function __read($dir = null, $subdir = null) {

		$datadir = $this->__datadir;

		if($dir)
			$datadir = sprintf($this->__datadir. "/%s", $dir);

        if($subdir)
			$datadir = sprintf($datadir. "/%s", $subdir);

		$h = @dir($datadir);
        if(!$h) {
            foreach(glob($datadir . '*') as $dir) {
                $dirs[] = $dir;
            }
            if($dirs) {
                foreach($dirs as $dir) {
                    $name = substr($dir, strrpos($dir, '/') +1, strlen($dir));
                    $this->__entries[] = $this->__readdir(dir($dir), $name);
                }
            }
        } else {
            while(false !== ($entry = $h->read())) {
                if(!preg_match('/^\.{1,2}$/', $entry, $tmp)) {
                    $this->__entries[] = $entry;
                }
            }
        }
	}

    private function __readdir($h, $name = null) {
    	while(false !== ($entry = $h->read())) {
			if(!preg_match('/^\.{1,2}$/', $entry, $tmp)) {
				$entries[$name][] = $entry;
			}
		}
        return $entries;
    }

  	/**
	 * @function	getHosts
	 * @brief	 	Return the JSON encoded hosts
	 * @return 		string
	 **/
	public function getHosts() {
		$this->__read();
		return json_encode(array('hosts' => $this->__entries));
	}

  	/**
	 * @function	getHostServices
	 * @brief	 	Return the JSON encoded services
	 * @return 		string
	 **/
	public function getHostServices($host) {
		$this->__read($host);
        $this->__parseServices();
		return json_encode(array('services' => $this->__entries));
	}

  	/**
	 * @function	getHostServiceItems
	 * @brief	 	Return the JSON encoded services
	 * @return 		string
	 **/
	public function getHostServiceItems($host, $service) {
		$this->__read($host, $service);
        $this->__parseServiceItems();
       
        /** rewrite array do have only one cpu aggregated **/
        if($service == 'cpu') {
            /* get only the first cpu and the cpu count */
            $entries = $this->__entries['cpu-0'];
            $entries['count'] = $this->__entries['count'];
            $this->__entries = array('cpu' => $entries);
        }

		return json_encode(array('items' => $this->__entries));
	}

  	/**
	 * @function	getHostServiceItemPath
	 * @brief	 	Return the JSON encoded Service Item Path
	 * @return 		string
	 **/
    private function getHostServiceItemPath($host, $service, $item, $count = false) {
    	$item_path = $this->__datadir;
        if(is_array($item)) {

            foreach($item as $c => $i) { 
                if($c == 0) continue;
                if($count > 0) {
                    $i = $service . '-' . $i .'.rrd';
                    $path[] = sprintf($item_path. "/%s/%s-%s/%s", $host, $item[0], 0, $i);
                }
                else {
                    $i = $service . '-' . $i .'.rrd';
                    $path[] = sprintf($item_path. "/%s/%s/%s", $host, $item[0], $i);
                }
            }
            return $path;
        }
        else {
		    return sprintf($item_path. "/%s/%s/%s", $host, $service, $item);
        }
    }

  	/**
	 * @function	graph
	 * @brief	 	Return HTML SVG Graph for Service Item
	 * @return 		string
	 **/
    public function graph($host, $service, $item, $count = false) {
        /**
         * change service to interface while
         * the submenu snmp is not working
         **/
        $items = explode(',', $item);
        if(count($items) > 1) 
            $item = $items;
    
        $path = $this->getHostServiceItemPath($host, $service, $item, $count);
        if($service == 'snmp') {
            $service = 'interface';
        }
        return new CollectdGraph($path, $service, $item, $count);
    }

    private function __parseServices() {
        $indexes = array();
        foreach($this->__entries as $entry) {
            if(preg_match('/(\w+)\-.*/', $entry, $tmp)) {
                array_push($indexes, $tmp[1]);
                continue;
            }
            if(preg_match('/^(\w+)$/', $entry, $tmp)) {
                array_push($indexes, $tmp[1]);
                continue;
            }

            array_push($indexes, $entry);
        }
        $indexes = array_unique($indexes);
        $this->__entries = $indexes;
    }

    private function __parseServiceItems() {
        foreach($this->__entries as $idx => $entry) {
            if(is_array($entry)) {
                foreach($entry as $idxc => $sube) {
                    foreach($sube as $e) {
                        //$entries[] = $this->__serviceMatch($e, $idxc);
                        $this->__serviceMatch($e, $idxc);
                    } 
                } 
            } else {
               $this->__serviceMatch($entry);
            }
                
            /*preg_match('/(.*).rrd/', $entry, $tmp);
            $entries[][$tmp[1]] = $entry;*/

        }
        $this->__entries = $this->__nentries;
        $this->__entries['count'] = count($this->__nentries);
    }

    private function __serviceMatch($entry, $idx = null) {
        $this->__match('/if_octets-(.*).rrd/', $entry);
        $this->__match('/(load).rrd/', $entry);
        $this->__match('/df-(.*).rrd/', $entry);
        $this->__match('/disk_(.*).rrd/', $entry, $idx);
        $this->__match('/cpu-(.*).rrd/', $entry, $idx);
    }

    private function __match($pattern, $match, $idx = null) {
        if(preg_match($pattern, $match, $tmp)) {
            if($idx) {
                $this->__nentries[$idx][$tmp[1]][] = $match;
            } else {
                $this->__nentries[][$tmp[1]] = $match;
            }
        }
    }
}

class CollectdGraph {

    private $__path;
    private $__service;

    function __construct($path, $service, $item, $count) {
        $this->__path    = $path;
        $this->__service = $service;
        $this->__item    = $item;
        $this->__count   = $count;
    }

    public function render($interval, $type) {
        require_once('colors.php');

        $file = $this->__path;
        $method = '__'. $this->__service . '_graph';
        return $this->$method($interval, $type, $this->__count);
        //return $this->__{$this->__item}_graph($interval);
    }

    /**
     * @name initGraph
     * @type static
     * @param string $type
     * @param array $options
     * @param array $series
     */
    static private function buildGraph($type, $options, $series) {

        $type = ucfirst($type);

        require_once('lib/HighRoller/HighRoller.php');
        require_once('lib/HighRoller/HighRollerSeriesData.php');
        require_once('lib/HighRoller/HighRoller'. $type. 'Chart.php');

        $klaz = 'HighRoller'. $type. 'Chart';
        $graph = new $klaz;

        $graph->chart       = (object) $options['chart'];
        $graph->plotOptions = (object) $options['plotOptions'];
        $graph->xAxis       = (object) $options['xAxis'];
        $graph->yAxis       = (object) $options['yAxis'];
        $graph->title       = (object) $options['title'];

        foreach($series as $serie) {
            $obj = new HighRollerSeriesData(); 
            $obj->addName($serie['name'])->addColor($serie['color'])->addData($serie['data']);
            $graph->addSeries($obj);
        }
        require('templates/graph-new.tpl');
    }
    
    private function __interface_graph($interval, $type) {

        $data = $this->__interface_data($interval);

        foreach($data['data'] as $value) {
            $time[]         = $value[2];
            $chartData[0][] = $value[0];
            $chartData[1][] = $value[1];
        }

        $series = array(
            1 => array('name' => $data['title'][1], 'color' => '#66FF66', 'data' => $chartData[1]),
            0 => array('name' => $data['title'][0], 'color' => '#6666FF', 'data' => $chartData[0]),
        );

        /** parsing some data */
        preg_match('/(\d)(\w+)/', $interval, $tmp);
        $interval = implode(' ', array($tmp[1], $tmp[2]));

        /* Get the step to render correctly the graph. */
        $offset = 3 * 3600;
        $step = ($time[1] - $time[0]);

        $plotOptions = array( 
            $type => array(
                'marker' => array(
                    'enabled' => true, 
                    'symbol'=> 'circle', 
                    'radius'=> 1, 'states' => array(    
                        'hover' => array('enabled' => true)
                    )
                ),
                'lineWidth' => 1,
                'pointStart' => ($time[0] -$offset) * 1000,
                'pointInterval' => $step * 1000,
            )
        );

        $chart = array('renderTo' => 'graph', 'zoomType' => 'x', 'type' => 'area');
        $title = array('text' => 'Network Usage');
        $xAxis = array('type' => 'datetime');
        $yAxis = array('title' => array('text' => 'KBytes per second') );

        $options = array(
            'plotOptions' => $plotOptions,
            'chart' => $chart,
            'title' => $title,
            'xAxis' => $xAxis,
            'yAxis' => $yAxis
        );

        self::buildGraph($type, $options, $series);
    }

    private function __load_graph($interval, $type, $count = false) {

        $data = $this->__load_data($interval);

        $translate = array( 
            'shortterm' => '5 minutes',
            'midterm' => '10 minutes',
            'longterm' => '15 minutes',
        );

        foreach($data['data'] as $value) {
            $time[]         = $value[0];
            $chartData[1][] = $value[1];
            $chartData[2][] = $value[2];
            $chartData[3][] = $value[3];
        }

        $series = array(
            2 => array('name' => $translate[$data['title'][2]], 'color' => '#FF00AA', 'data' => $chartData[3]),
            1 => array('name' => $translate[$data['title'][1]], 'color' => '#66FF66', 'data' => $chartData[2]),
            0 => array('name' => $translate[$data['title'][0]], 'color' => '#6666FF', 'data' => $chartData[1]),
        );

        /** parsing some data */
        preg_match('/(\d)(\w+)/', $interval, $tmp);
        $interval = implode(' ', array($tmp[1], $tmp[2]));

        /* Get the step to render correctly the graph. */
        $offset = 3 * 3600;
        $step = ($time[1] - $time[0]);

        $plotOptions = array( 
            $type => array(
                'marker' => array(
                    'enabled' => true, 
                    'symbol'=> 'circle', 
                    'radius'=> 1, 'states' => array(    
                        'hover' => array('enabled' => true)
                    )
                ),
                'lineWidth' => 1,
                'pointStart' => ($time[0] -$offset) * 1000,
                'pointInterval' => $step * 1000,
            )
        );

        $chart = array('renderTo' => 'graph', 'zoomType' => 'x', 'type' => 'area');
        $title = array('text' => 'Load Average');
        $xAxis = array('type' => 'datetime');
        $yAxis = array('title' => array('text' => 'CPU Load') );

        $options = array(
            'plotOptions' => $plotOptions,
            'chart' => $chart,
            'title' => $title,
            'xAxis' => $xAxis,
            'yAxis' => $yAxis
        );

        self::buildGraph($type, $options, $series);
    }   

    private function __df_graph($interval, $type, $count = false) {

        $data = $this->__df_data($interval);

        foreach($data['data'] as $value) {
            $time[]         = $value[2];
            $chartData[0][] = $value[0];
            $chartData[1][] = $value[1];
        }

        $series = array(
            1 => array('name' => $data['title'][2], 'color' => '#6666FF', 'data' => $chartData[1]),
            0 => array('name' => $data['title'][1], 'color' => '#FF6666', 'data' => $chartData[0]),
        );

        /** parsing some data */
        preg_match('/(\d)(\w+)/', $interval, $tmp);
        $interval = implode(' ', array($tmp[1], $tmp[2]));

        /* Get the step to render correctly the graph. */
        $offset = 3 * 3600;
        $step = ($time[1] - $time[0]);

        $plotOptions = array( 
            $type => array(
                'stacking' => 'normal',
                'marker' => array(
                    'enabled' => true, 
                    'symbol'=> 'circle', 
                    'radius'=> 1, 'states' => array(    
                        'hover' => array('enabled' => true)
                    )
                ),
                'lineWidth' => 1,
                'pointStart' => ($time[0] -$offset) * 1000,
                'pointInterval' => $step * 1000,
            )
        );

        $chart = array('renderTo' => 'graph', 'zoomType' => 'x', 'type' => 'area');
        $title = array('text' => 'Disk usage');
        $xAxis = array('type' => 'datetime');
        $yAxis = array('title' => array('text' => 'Bytes used') );

        $options = array(
            'plotOptions' => $plotOptions,
            'chart' => $chart,
            'title' => $title,
            'xAxis' => $xAxis,
            'yAxis' => $yAxis
        );

        self::buildGraph($type, $options, $series);
    }   

    private function __disk_graph($interval, $type, $count=false) {}   

    private function __cpu_graph($interval, $type, $count = false) {

        require_once('lib/HighRoller/HighRoller.php');
        require_once('lib/HighRoller/HighRollerSeriesData.php');
        require_once('lib/HighRoller/HighRoller'. ucfirst($type) .'Chart.php');

        $data = $this->__cpu_data($interval);
        foreach($data as $key => $cpudata) {
            foreach($cpudata['data'] as $idx => $value) {
                $time        = $value[1];
                $chartData[$key][] = $value[0];
            }
        }

        $series1 = new HighRollerSeriesData();
        $series2 = new HighRollerSeriesData();
        $series3 = new HighRollerSeriesData();
        $series4 = new HighRollerSeriesData();
        $series5 = new HighRollerSeriesData();
        $series6 = new HighRollerSeriesData();
        $series7 = new HighRollerSeriesData();
        $series8 = new HighRollerSeriesData();

        $colormap = array(
                'steal'     => '#000000',
                'nice'      => '#AACC00',
                'system'    => '#FF0000',
                'wait'      => '#FFAA33',
                'user'      => '#22CC22',
                'idle'      => '#DDDDDD',
                'interrupt' => '#0000FF',
                'softirq'   => '#11AACC',
        );

        $series1->addName($data[0]['title'])->addColor($colormap[$data[0]['title']])->addData($chartData[0]);
        $series2->addName($data[1]['title'])->addColor($colormap[$data[1]['title']])->addData($chartData[1]);
        $series3->addName($data[2]['title'])->addColor($colormap[$data[2]['title']])->addData($chartData[2]);
        $series4->addName($data[3]['title'])->addColor($colormap[$data[3]['title']])->addData($chartData[3]);
        $series5->addName($data[4]['title'])->addColor($colormap[$data[4]['title']])->addData($chartData[4]);
        $series6->addName($data[5]['title'])->addColor($colormap[$data[5]['title']])->addData($chartData[5]);
        $series7->addName($data[6]['title'])->addColor($colormap[$data[6]['title']])->addData($chartData[6]);
        $series8->addName($data[7]['title'])->addColor($colormap[$data[7]['title']])->addData($chartData[7]);

        $linechart = new HighRollerAreaChart();
        $linechart->plotOptions->$type->marker = array('enabled' => true, 'symbol'=> 'circle', 'radius'=> 1, 'states' => array( 'hover' => array('enabled' => true)));
        $linechart->plotOptions->$type->lineWidth = 1;

        preg_match('/(\d)(\w+)/', $interval, $tmp);
        $interval = implode(' ', array($tmp[1], $tmp[2]));
        $offset = 3 * 3600;
        $step = ($time[1] - $time[0]);
        $linechart->plotOptions->$type->pointStart = ($time[0] -$offset) * 1000;
        $linechart->plotOptions->$type->pointInterval = $step * 1000;
        $linechart->chart->renderTo = 'linechart';
        $linechart->chart->zoomType = 'x';
        $linechart->title->text = 'CPU Usage';
        $linechart->yAxis->title->text = 'CPU Usage';
        $linechart->xAxis->type = 'datetime';

        $linechart->addSeries($series1);
        $linechart->addSeries($series2);
        $linechart->addSeries($series3);
        $linechart->addSeries($series4);
        $linechart->addSeries($series5);
        $linechart->addSeries($series6);
        $linechart->addSeries($series7);
        $linechart->addSeries($series8);

        require('templates/graph.tpl');
    }   

    private function __interface_data($interval) {
        $raw = shell_exec('rrdtool fetch '. $this->__path. ' AVERAGE -s -'. $interval);
        $tmp = explode("\n", $raw);
        foreach($tmp as $idx => $line) {
            if(empty($line)) 
                continue;
            if($idx == 0){
                preg_match('/(\w+)\s+(\w+)/', $line,$data['title']);
                unset($data['title'][0]);
                sort($data['title']);
                continue;
            }
            $tmpdata    = explode(': ', $line);
            $trange     = $tmpdata[0];
            $tmp2       = explode(' ', $tmpdata[1]);
            $data['data'][] = array( (float) round($tmp2[0] * 8), round((float) $tmp2[1] * 8), $trange);
        }
        return $data;
    }

    private function __load_data($interval) {
        $raw = shell_exec('rrdtool fetch '. $this->__path. ' MAX -s -'. $interval);
        $tmp = explode("\n", $raw);
        foreach($tmp as $idx => $line) {
            if(empty($line)) 
                continue;
            if($idx == 0){
                preg_match('/(\w+)\s+(\w+)\s+(\w+)/', $line, $data['title']);
                unset($data['title'][0]);
                sort($data['title']);
                continue;
            }
            $tmpdata    = explode(': ', $line);
            $trange     = $tmpdata[0];
            $tmp2       = explode(' ', $tmpdata[1]);
            $data['data'][] = array( $trange, (float) round($tmp2[0]), round((float) $tmp2[1]), round((float) $tmp2[2]) );
        }
        return $data;
    }

    private function __disk_data($interval) {
        $raw = shell_exec('rrdtool fetch '. $this->__path. ' AVERAGE -s -'. $interval);
        $tmp = explode("\n", $raw);
        foreach($tmp as $idx => $line) {
            if(empty($line)) 
                continue;
            if($idx == 0){
                preg_match('/(\w+)\s+(\w+)/', $line,$data['title']);
                unset($data['title'][0]);
                sort($data['title']);
                continue;
            }
            $tmpdata    = explode(': ', $line);
            $trange     = $tmpdata[0];
            $tmp2       = explode(' ', $tmpdata[1]);
            $data['data'][] = array( (float) round($tmp2[0] * 8), round((float) $tmp2[1] * 8), $trange);
        }
        return $data;
    }

    private function __cpu_data($interval) {


        /* first loop the graph items */
        for($x = 0; $x <=7; $x++) {
            
            /* loop the cpu count to get all values */
            for($i = 0; $i < $this->__count; $i++) {

                /* get rrd data */
                $path = preg_replace('/cpu-0/', 'cpu-'. $i, $this->__path[$x]);
                $raw = shell_exec('rrdtool fetch '. $path. ' AVERAGE -s -'. $interval);

                /* explode and loop each line to parse values */
                $tmp = explode("\n", $raw);
                foreach($tmp as $idx => $line) {
                    if(empty($line)) {
                        continue;
                    }
                    if($idx == 0){
                        if(is_array($this->__item)) {
                            $data[$x]['title'] = $this->__item[$x+1];
                        }
                        continue;
                    }
                    $tmpdata     = explode(': ', $line);
                    $trange[]      = $tmpdata[0];

                    $tmp2        = $tmpdata[1];
                    $calcdata[$x][$idx][$i] = $tmp2;
                }
            }
        }
    
        foreach($calcdata as $x => $icalc) {
            foreach($icalc as $i => $calc) {
                $avg = array_sum($calc) / $this->__count;
                $data[$x]['data'][$i] = array( round($avg), $trange);
            }
        }
        return $data;
    }

    private function __df_data($interval) {

        $raw = shell_exec('rrdtool fetch '. $this->__path. ' AVERAGE -s -'. $interval);
        $tmp = explode("\n", $raw);
        foreach($tmp as $idx => $line) {
            if(empty($line)) 
                continue;
            if($idx == 0){
                preg_match('/(\w+)\s+(\w+)/', $line, $data['title']);
                unset($data['title'][0]);
                //sort($data['title']);
                continue;
            }
            $tmpdata    = explode(': ', $line);
            $trange     = $tmpdata[0];
            $tmp2       = explode(' ', $tmpdata[1]);
            $data['data'][] = array( (float) round($tmp2[0]), (float) round($tmp2[1]),  $trange);
        }
        return $data;
    }

}
