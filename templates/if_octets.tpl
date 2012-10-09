<head>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js"></script>
  <!-- HighRoller: set the location of Highcharts library -->
  <?php echo HighRoller::setHighChartsLocation("js/highcharts/highcharts.js");?>
</head>

<body style="padding: 0px;">
  <div id="linechart" style="width: 850px; height: 280px;"></div>
  <script type="text/javascript">
    <?php echo $linechart->renderChart();?>
  </script>
</body>
