<?php
$connect = new mysqli('192.168.10.177', 'jason', 'Abc123456', 'stat_unisoft_hk', 3306);

// Check the connection
if ($connect->connect_error) {
    die("Database connection failed: " . $connect->connect_error);
}

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '60');

if(empty($_GET['pair']))
	$_GET['pair']="BTCUSDT";

//format date
if(!empty($_GET['date_from']))
	$date_from=date('Y-m-d',strtotime($_GET['date_from']));
else
	$_GET['date_from']=$date_from=date('Y-m-d');

if(!empty($_GET['date_to']))
	$date_to=date('Y-m-d',strtotime($_GET['date_to']));
else
	$_GET['date_to']=$date_to=date('Y-m-d');

$query = $query_day = $query_exclude = '';

//date query
if(!empty($_GET['date_from']))
	$query = " AND date>='" . $_GET['date_from'] . "'";
if(!empty($_GET['date_to']))
	$query .= " AND date<='" . $_GET['date_to'] . "'";

//day query
if(empty($_GET['day']))
{
	$checked_day1=$checked_day2=$checked_day3=$checked_day4=$checked_day5=$checked_day6=$checked_day7='checked';
}
else
{
	foreach($_GET['day'] as $day)
	{
		${"checked_day$day"}='checked';
		$query_day.=" OR WEEKDAY(date)=($day-1)";
	}
	$query_day=" AND (0 $query_day)";
}

$_GET['exclude_change'] = $_GET['exclude_change'] ?? 0;
$_GET['exclude_change_per'] = $_GET['exclude_change_per'] ?? 0;

if ($_GET['exclude_change'] > 0) {
    $query_exclude .= " AND ABS(`23`-`00`) < " . $_GET['exclude_change'];
}
if ($_GET['exclude_change_per'] > 0) {
    $query_exclude .= " AND ABS((`23`-`00`)/`00`*100) < " . $_GET['exclude_change_per'];
}

//query data
$data=array();
$sql = "SELECT * FROM " . $_GET['pair'] . " WHERE 1 " . $query . " " . $query_day . " " . $query_exclude . " ORDER BY date DESC";

//echo $sql;
if (!$result = $connect->query($sql)) die("Query failed: " . $connect->error);
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$data = array_filter($data, function ($row) {
    for ($hour = 0; $hour <= 23; $hour++) {
        $column = sprintf('%02d', $hour); // Format as 00, 01, ..., 23
        if (isset($row[$column]) && $row[$column] == 0) {
            return false; // Exclude this row
        }
    }
    return true; // Keep this row
});

//add day
//print_r($data);
reset($data);
foreach($data as $key=>$row)
{
	$data[$key]['mid']=intval(($row['high']+$row['low'])/2);
	$data[$key]['volatility']=intval($row['high']-$row['low']);
	$data[$key]['day']=date('N',strtotime($row['date']));
	$data[$key]['change_per']=number_format(($row['23']-$row['00'])/$row['00']*100,1);
}

//add change
reset($data);

$count_day = [];
$avg_day = [];
$avg_volatility = [];
$count_up=$count_down=0;

for ($d = 1; $d <= 7; $d++) {
    $count_day[$d] = ['up' => 0, 'down' => 0];
    $avg_day[$d] = ['count' => 0, 'amount' => 0, 'per' => 0];
    $avg_volatility[$d] = ['count' => 0, 'amount' => 0, 'per' => 0];
}

foreach($data as $key=>$row)
{
	$data[$key]['change']=round($row['23']-$row['00'],0);

	//count changes
	if($data[$key]['change']>0)
	{
		$count_up++;
		$count_day[$data[$key]['day']]['up']++;
	}
	else
	{
		$count_down++;
		$count_day[$data[$key]['day']]['down']++;
	}

	//average amount
	$avg_day[$data[$key]['day']]['count']++;
	$avg_day[$data[$key]['day']]['amount']+=($data[$key]['change']);
	$avg_day[$data[$key]['day']]['per']+=($data[$key]['change_per']);

	//average volatility
	$avg_volatility[$data[$key]['day']]['count']++;
	$avg_volatility[$data[$key]['day']]['amount']+=abs($data[$key]['volatility']);
	$avg_volatility[$data[$key]['day']]['per']+=abs($data[$key]['volatility']/$data[$key]["00"]*100);
}

//define date range
$range=array_column($data,'date');
if(empty($_GET['date_from']))
	$date_from=min($range);
if(empty($_GET['date_to']))
	$date_to=max($range);

//stat : high low count
$hour_high=array_column($data,'high_hour');
$hour_low =array_column($data,'low_hour');
$hour_high_count=array_count_values($hour_high);
$hour_low_count =array_count_values($hour_low);


$data_hlcount=array();
$data_count_high = $data_count_low = '';
for($h=0;$h<24;$h++)
{
	if (!isset($hour_high_count[$h])) {
        $hour_high_count[$h] = 0;
    }
    if (!isset($hour_low_count[$h])) {
        $hour_low_count[$h] = 0;
    }
	
	$data_hlcount[$h]['hour']=sprintf('%02d',$h);
	$data_hlcount[$h]['high']=$hour_high_count[$h];
	$data_hlcount[$h]['low'] =$hour_low_count[$h];
    $data_hlcount[$h]['high_per'] = array_sum($hour_high_count) > 0 ? number_format($hour_high_count[$h] / array_sum($hour_high_count) * 100, 1) : 0;
    $data_hlcount[$h]['low_per'] = array_sum($hour_low_count) > 0 ? number_format($hour_low_count[$h] / array_sum($hour_low_count) * 100, 1) : 0;

	$data_count_high.=','.intval($hour_high_count[$h]);
	$data_count_low.=',-'.intval($hour_low_count[$h]);
}
$data_hlcount=json_encode($data_hlcount);
$data_count_high=substr($data_count_high,1);
$data_count_low=substr($data_count_low,1);

//Change count
$count_up_per = ($count_up + $count_down) > 0 ? intval($count_up / ($count_up + $count_down) * 100) : 0;
for ($day = 1; $day <= 7; $day++) {
    $total = $count_day[$day]['up'] + $count_day[$day]['down'];
    $count_day[$day]['per'] = $total > 0 
        ? intval($count_day[$day]['up'] / $total * 100) 
        : 0;
}

//Overall average
$avg_amount = $avg_per = $avg_volatility_amount = $avg_volatility_per = 0;
if ($count_up + $count_down > 0){
	$avg_amount=intval(($avg_day[1]['amount']+$avg_day[2]['amount']+$avg_day[3]['amount']+$avg_day[4]['amount']+$avg_day[5]['amount']+$avg_day[6]['amount']+$avg_day[7]['amount'])/(($count_up+$count_down)));
	$avg_per=intval(($avg_day[1]['per']+$avg_day[2]['per']+$avg_day[3]['per']+$avg_day[4]['per']+$avg_day[5]['per']+$avg_day[6]['per']+$avg_day[7]['per'])/(($count_up+$count_down)));
	$avg_volatility_amount=intval(($avg_volatility[1]['amount']+$avg_volatility[2]['amount']+$avg_volatility[3]['amount']+$avg_volatility[4]['amount']+$avg_volatility[5]['amount']+$avg_volatility[6]['amount']+$avg_volatility[7]['amount'])/(($count_up+$count_down)));
	$avg_volatility_per=intval(($avg_volatility[1]['per']+$avg_volatility[2]['per']+$avg_volatility[3]['per']+$avg_volatility[4]['per']+$avg_volatility[5]['per']+$avg_volatility[6]['per']+$avg_volatility[7]['per'])/(($count_up+$count_down)));
}

//Average amount and volatility
for ($day = 1; $day <= 7; $day++) {
    $avg_day[$day]['amount'] = $avg_day[$day]['count'] > 0 
        ? intval($avg_day[$day]['amount'] / $avg_day[$day]['count']) 
        : 0; // Default to 0 if count is zero

    $avg_day[$day]['per'] = $avg_day[$day]['count'] > 0 
        ? intval($avg_day[$day]['per'] / $avg_day[$day]['count']) 
        : 0; // Default to 0 if count is zero
}
for ($day = 1; $day <= 7; $day++) {
    $avg_volatility[$day]['amount'] = $avg_volatility[$day]['count'] > 0 
        ? intval($avg_volatility[$day]['amount'] / $avg_volatility[$day]['count']) 
        : 0; // Default to 0 if count is zero

    $avg_volatility[$day]['per'] = $avg_volatility[$day]['count'] > 0 
        ? intval($avg_volatility[$day]['per'] / $avg_volatility[$day]['count']) 
        : 0; // Default to 0 if count is zero
}

//hour differences
reset($data);
$diff = $diff_count_up = $diff_count_down = array();
$hour_buy = $hour_sell = $row_diff = ''; // Initialize to default values
foreach($data as $row)
{
	$sql="SELECT * FROM ".$_GET['pair']."_diff WHERE `date`='$row[date]'";
	if (!$result_diff = $connect->query($sql)) die("Query failed: " . $connect->error);
	if ($result_diff->num_rows <> 1)
	{
		//add differences - pair _diff
		$values="'$row[date]'";
		for($i=0;$i<=23;$i++)
		{
			for($j=0;$j<=23;$j++)
			{
				if($j>$i)
				{
					$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
					$hour_sell=str_pad($j,2,'0',STR_PAD_LEFT);
					$values.=",'".($row["$hour_sell"]-$row["$hour_buy"])."'";
				}
				else
					$values.=",'0'";
			}
		}
		$sql="INSERT INTO ".$_GET[pair]."_diff VALUES ($values)";
		mysql_query($sql);
	}
	else
	{
		$row_diff = $result_diff->fetch_assoc();
		//calculate hour differences
		for($i=0;$i<=23;$i++)
		{
			for($j=0;$j<=23;$j++)
			{
				if($j>$i)
				{
					$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
					$hour_sell=str_pad($j,2,'0',STR_PAD_LEFT);
                    if (!isset($diff[$hour_buy][$hour_sell])) {
                        $diff[$hour_buy][$hour_sell] = 0;
                    }
                    if (!isset($diff_count_up[$hour_buy][$hour_sell])) {
                        $diff_count_up[$hour_buy][$hour_sell] = 0;
                    }
                    if (!isset($diff_count_down[$hour_buy][$hour_sell])) {
                        $diff_count_down[$hour_buy][$hour_sell] = 0;
                    }
					$diff[$hour_buy][$hour_sell]+=$row_diff[$hour_buy.'_'.$hour_sell];
					//count up and down
					if($row_diff[$hour_buy.'_'.$hour_sell]>0)
						$diff_count_up[$hour_buy][$hour_sell]++;
					else
						$diff_count_down[$hour_buy][$hour_sell]++;
				}
			}
		}
	}
}

$data_hrdiff = array();
foreach ($diff as $buy => $row) {
    $data_entry = array('buy' => $buy);

    // Ensure all hour keys exist
    for ($hour = 1; $hour <= 23; $hour++) {
        $hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
        $data_entry[$hour_str] = $row[$hour_str] ?? 0; // Default to 0 if key is missing
    }

    array_push($data_hrdiff, $data_entry);
}

// Convert to JSON
$data_hrdiff = json_encode($data_hrdiff);

$data_hrdiff_count = array();

for ($i = 0; $i < 23; $i++) {
    $hour_buy = str_pad($i, 2, '0', STR_PAD_LEFT);

    // Initialize the data row
    $data_row = array('buy' => $hour_buy);

    for ($j = 1; $j <= 23; $j++) {
        $hour_sell = str_pad($j, 2, '0', STR_PAD_LEFT);

        // Default to 0 if keys are missing
        $up = isset($diff_count_up[$hour_buy][$hour_sell]) ? $diff_count_up[$hour_buy][$hour_sell] : 0;
        $down = isset($diff_count_down[$hour_buy][$hour_sell]) ? $diff_count_down[$hour_buy][$hour_sell] : 0;

        // Avoid division by zero
        $total = $up + $down;
        if ($total > 0) {
            $data_row[$hour_sell] = intval($up / $total * 100);
        } else {
            $data_row[$hour_sell] = 0; // Set to 0 when the denominator is 0
        }

        // Debugging information
        $data_row["x" . $hour_sell] = '▲' . $up . "\n▼" . $down;
    }

    $data_hrdiff_count[] = $data_row;
}

$data_hrdiff_count_avg = [];
foreach ($data_hrdiff_count as $key => $row) {
    for ($i = 1; $i <= 23; $i++) {
        if ($i > $key) {
            $hour_sell = str_pad($i, 2, '0', STR_PAD_LEFT);

            // Initialize keys if not already set
            if (!isset($data_hrdiff_count_avg[$hour_sell]['total'])) {
                $data_hrdiff_count_avg[$hour_sell]['total'] = 0;
            }
            if (!isset($data_hrdiff_count_avg[$hour_sell]['count'])) {
                $data_hrdiff_count_avg[$hour_sell]['count'] = 0;
            }

            // Increment values
            $data_hrdiff_count_avg[$hour_sell]['total'] += $row[$hour_sell];
            $data_hrdiff_count_avg[$hour_sell]['count']++;
        }
    }
}

for($i=1;$i<=23;$i++)
{
	$hour_sell=str_pad($i,2,'0',STR_PAD_LEFT);
	$data_hrdiff_count_avg[$hour_sell]=intval($data_hrdiff_count_avg[$hour_sell]['total']/$data_hrdiff_count_avg[$hour_sell]['count']);
}

$data_hrdiff_count=json_encode($data_hrdiff_count);
$data_hrdiff_count=str_replace(":0,",":null,",$data_hrdiff_count);
//die($data_hrdiff_count);



$hrly_diff=array();
for ($i = 0; $i < 23; $i++) {
    $hour_buy = str_pad($i, 2, '0', STR_PAD_LEFT);
    $hour_sell = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    $key = "$hour_buy-$hour_sell";
    $hrly_diff[$key] = 0;
    $hrly_diff_volatility[$key] = 0;
    $hrly_diff_max[$key] = 0;
    $hrly_diff_min[$key] = 0;
    $hrly_diff_up_1000[$key] = 0;
    $hrly_diff_up_500[$key] = 0;
    $hrly_diff_down_1000[$key] = 0;
    $hrly_diff_down_500[$key] = 0;
}

//hourly differences
foreach($data as $row)
{
	for($i=0;$i<23;$i++)
	{
		$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
		$hour_sell=str_pad($i+1,2,'0',STR_PAD_LEFT);
		$key = "$hour_buy-$hour_sell";

		$temp=$row[$hour_sell]-$row[$hour_buy];
		$hrly_diff["$hour_buy-$hour_sell"]+=$temp;
		$hrly_diff_volatility["$hour_buy-$hour_sell"]+=$row['high']-$row['low'];

		if($temp>0)
		{
			if($temp>$hrly_diff_max["$hour_buy-$hour_sell"])
				$hrly_diff_max["$hour_buy-$hour_sell"]=$temp;
		}
		else
		{
			if($temp<intval($hrly_diff_min["$hour_buy-$hour_sell"]))
			{
				$hrly_diff_min["$hour_buy-$hour_sell"]=$temp;
			}
		}

		if($temp>=1000)
			$hrly_diff_up_1000["$hour_buy-$hour_sell"]++;
		if($temp>=500)
			$hrly_diff_up_500["$hour_buy-$hour_sell"]++;

		if($temp<=-1000)
			$hrly_diff_down_1000["$hour_buy-$hour_sell"]++;
		if($temp<=-500)
			$hrly_diff_down_500["$hour_buy-$hour_sell"]++;
	}
}

//print_r($hrly_diff_volatility);
foreach($hrly_diff as $row)
{
	for($i=0;$i<23;$i++)
	{
		$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
		$hour_sell=str_pad($i+1,2,'0',STR_PAD_LEFT);
		$hrly_diff_avg["$hour_buy-$hour_sell"] = count($data) > 0 ? intval($hrly_diff["$hour_buy-$hour_sell"]/count($data)) : 0;
	}
}

$hrly_head = $hrly_body = $hrly_avg = $hrly_min = $hrly_max = $hrly_down_500 = $hrly_up_500 = $hrly_down_1000 = $hrly_up_1000 =  '';
for($i=0;$i<23;$i++)
{
	$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
	$hour_sell=str_pad($i+1,2,'0',STR_PAD_LEFT);
	$hrly_head.="<td>$hour_buy:00<br/>-<br/>$hour_sell:00</td>";

	if($hrly_diff["$hour_buy-$hour_sell"]>0)
	{
		$hrly_body.="<td class='green'>".$hrly_diff["$hour_buy-$hour_sell"]."</td>";
		$hrly_avg.="<td class='green'>".$hrly_diff_avg["$hour_buy-$hour_sell"]."</td>";
	}
	else
	{
		$hrly_body.="<td class='red'>".$hrly_diff["$hour_buy-$hour_sell"]."</td>";
		$hrly_avg.="<td class='red'>".$hrly_diff_avg["$hour_buy-$hour_sell"]."</td>";
	}

	$hrly_max.="<td>".$hrly_diff_max["$hour_buy-$hour_sell"]."</td>";
	$hrly_min.="<td>".$hrly_diff_min["$hour_buy-$hour_sell"]."</td>";

	$hrly_up_500.="<td>".$hrly_diff_up_500["$hour_buy-$hour_sell"]."</td>";
	$hrly_up_1000.="<td>".$hrly_diff_up_1000["$hour_buy-$hour_sell"]."</td>";
	$hrly_down_500.="<td>".$hrly_diff_down_500["$hour_buy-$hour_sell"]."</td>";
	$hrly_down_1000.="<td>".$hrly_diff_down_1000["$hour_buy-$hour_sell"]."</td>";
}
$hrly_head="<tr align='center' bgcolor='#E6E6E6'><td></td>".$hrly_head."</tr>\n";
$hrly_body="<tr align='right'><td>Changes</td>".$hrly_body."</tr>\n";
$hrly_body.="<tr align='right'><td nowrap>Average Amt</td>".$hrly_avg."</tr>\n";
$hrly_body.="<tr align='right'><td nowrap>Max &#9650;</td>".$hrly_max."</tr>\n";
$hrly_body.="<tr align='right'><td nowrap>Max &#9660;</td>".$hrly_min."</tr>\n";
$hrly_body.="<tr align='center'><td align='left' nowrap>Amount &#9650; 500+</td>".$hrly_up_500."</tr>\n";
$hrly_body.="<tr align='center'><td align='left' nowrap>Amount &#9650; 1000+</td>".$hrly_up_1000."</tr>\n";
$hrly_body.="<tr align='center'><td align='left' nowrap>Amount &#9660; 500+</td>".$hrly_down_500."</tr>\n";
$hrly_body.="<tr align='center'><td align='left' nowrap>Amount &#9660; 1000+</td>".$hrly_down_1000."</tr>\n";

//prepare pattern data
$data_pattern = '';
for($i=0;$i<23;$i++)
{
	$hour_buy=str_pad($i,2,'0',STR_PAD_LEFT);
	$hour_sell=str_pad($i+1,2,'0',STR_PAD_LEFT);

	$data_pattern.=','.$hrly_diff["$hour_buy-$hour_sell"];
}
$data_pattern=substr($data_pattern,1);
//$data_pattern=array("line"=>$data_pattern);
//$data_pattern=json_encode($data_pattern);

//json raw data
$data=json_encode($data);

//buttons
$this_week_from=date('Y-m-d',strtotime('next Monday -1 week'));
$this_week_to=date('Y-m-d',strtotime('last day of this week'));
$this_month_from=date('Y-m-d',strtotime('first day of this month'));
$this_month_to=date('Y-m-d',strtotime('last day of this month'));
$this_year_from=date('Y').'-01-01';
$this_year_to=date('Y').'-12-31';
?>
<!DOCTYPE html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
<script type="text/javascript" src="https://oss.sheetjs.com/sheetjs/xlsx.full.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>
<title><?=$_GET['pair']?></title>
<style>
table {border-collapse:collapse;border:1px solid grey}
.tabulator {font-size:12px}
.red {color:red}
.green {color:green}
.blue {color:blue}
</style>
</head>
<body>
<script type="text/javascript">
$(function() {
	//set vars
	$("[name=exclude_change]").val("<?=$_GET['exclude_change']?>").change();
	$("[name=exclude_change_per]").val("<?=$_GET['exclude_change_per']?>").change();

	//define some data
	var data = <?=$data?>;
	var data_table = new Tabulator("#data-table", {
		height:600,				// set height of table (in CSS or here), this enables the Virtual DOM and improves render speed dramatically (can be any valid css height value)
		layout:"fitColumns",	//fit columns to width of table (optional)
		selectable:true,
		data:data,				//assign data to table
		columns:[				//Define Table Columns
			{title:"Date<br/>HKT", field:"date", sorter:"string", width:"80", resizable:false},
			{title:"Day",  field:"day", headerSort:false, resizable:false, width:"30", hozAlign:"center"},
			{title:"00:00", field:"00", headerSort:false, resizable:false},
			{title:"01:00", field:"01", headerSort:false, resizable:false},
			{title:"02:00", field:"02", headerSort:false, resizable:false},
			{title:"03:00", field:"03", headerSort:false, resizable:false},
			{title:"04:00", field:"04", headerSort:false, resizable:false},
			{title:"05:00", field:"05", headerSort:false, resizable:false},
			{title:"06:00", field:"06", headerSort:false, resizable:false},
			{title:"07:00", field:"07", headerSort:false, resizable:false},
			{title:"08:00", field:"08", headerSort:false, resizable:false},
			{title:"09:00", field:"09", headerSort:false, resizable:false},
			{title:"10:00", field:"10", headerSort:false, resizable:false},
			{title:"11:00", field:"11", headerSort:false, resizable:false},
			{title:"12:00", field:"12", headerSort:false, resizable:false},
			{title:"13:00", field:"13", headerSort:false, resizable:false},
			{title:"14:00", field:"14", headerSort:false, resizable:false},
			{title:"15:00", field:"15", headerSort:false, resizable:false},
			{title:"16:00", field:"16", headerSort:false, resizable:false},
			{title:"17:00", field:"17", headerSort:false, resizable:false},
			{title:"18:00", field:"18", headerSort:false, resizable:false},
			{title:"19:00", field:"19", headerSort:false, resizable:false},
			{title:"20:00", field:"20", headerSort:false, resizable:false},
			{title:"21:00", field:"21", headerSort:false, resizable:false},
			{title:"22:00", field:"22", headerSort:false, resizable:false},
			{title:"23:00", field:"23", headerSort:false, resizable:false},
			{title:"High", field:"high", headerSort:false, resizable:false, cssClass:"green"},
			{title:"Mid",  field:"mid",  headerSort:false, resizable:false, cssClass:"blue"},
			{title:"Low",  field:"low",  headerSort:false, resizable:false, cssClass:"red"},
			{title:"σ", field:"volatility", headerSort:false, resizable:false, hozAlign:"right"},
			{title:"High<br/>Hour", width:"50", field:"high_hour", hozAlign:"center", headerSort:false, resizable:false},
			{title:"Low<br/>Hour",  width:"50", field:"low_hour",  hozAlign:"center", headerSort:false, resizable:false},
			{title:"Change<br/>USD", field:"change", headerSort:false, resizable:false, hozAlign:"right"},
			{title:"%", field:"change_per", headerSort:false, resizable:false, hozAlign:"right"},
		],
		rowFormatter:function(row){
			//high low
			var high_hour = row.getCell('high_hour').getValue();
			high_hour=parseInt(high_hour)+2;
			row.getElement().childNodes[high_hour].style.backgroundColor='#AAFFAA';

			var low_hour = row.getCell('low_hour').getValue();
			low_hour=parseInt(low_hour)+2;
			row.getElement().childNodes[low_hour].style.backgroundColor='#FFAAAA';

			//change
			var change = row.getCell('change').getValue();
			if(change>0)
			{
				row.getElement().childNodes[32].style.backgroundColor='#AAFFAA';
				row.getElement().childNodes[33].style.backgroundColor='#AAFFAA';
			}
			else
			{
				row.getElement().childNodes[32].style.backgroundColor='#FFAAAA';
				row.getElement().childNodes[33].style.backgroundColor='#FFAAAA';
			}
		},
	});

	//define some data
	var data_hlcount = <?=$data_hlcount?>;
	var hlcount_table = new Tabulator("#hlcount-table", {
		layout:"fitColumns",	//fit columns to width of table (optional)
		selectable:true,
		data:data_hlcount,		//assign data to table
		columns:[				//Define Table Columns
			{title:"Hour",  field:"hour", width:"70"},
			{title:"Day High Hour", field:"high", formatter:"progress", cssClass:"green"},
			{title:"Count", field:"high", width:"70", hozAlign:"right", cssClass:"green"},
			{title:"%", field:"high_per", width:"50", hozAlign:"right", cssClass:"green"},
			{title:"Day Low Hour",  field:"low",  formatter:"progress", cssClass:"red"},
			{title:"Count", field:"low", width:"70", hozAlign:"right", cssClass:"red"},
			{title:"%", field:"low_per", width:"50", hozAlign:"right", cssClass:"red"},
		],
	});

	//define some data
	var data_hrdiff = <?=$data_hrdiff?>;
	var hrdiff_table = new Tabulator("#hrdiff-table", {
		layout:"fitColumns",	//fit columns to width of table (optional)
		selectable:true,
		data:data_hrdiff,		//assign data to table
		columns:[				//Define Table Columns
			{title:"Sell<hr/>Buy",  field:"buy", headerSort:false, resizable:false, width:"30", hozAlign:"center"},
			{title:"01:00", field:"01", hozAlign:"right", resizable:false},
			{title:"02:00", field:"02", hozAlign:"right", resizable:false},
			{title:"03:00", field:"03", hozAlign:"right", resizable:false},
			{title:"04:00", field:"04", hozAlign:"right", resizable:false},
			{title:"05:00", field:"05", hozAlign:"right", resizable:false},
			{title:"06:00", field:"06", hozAlign:"right", resizable:false},
			{title:"07:00", field:"07", hozAlign:"right", resizable:false},
			{title:"08:00", field:"08", hozAlign:"right", resizable:false},
			{title:"09:00", field:"09", hozAlign:"right", resizable:false},
			{title:"10:00", field:"10", hozAlign:"right", resizable:false},
			{title:"11:00", field:"11", hozAlign:"right", resizable:false},
			{title:"12:00", field:"12", hozAlign:"right", resizable:false},
			{title:"13:00", field:"13", hozAlign:"right", resizable:false},
			{title:"14:00", field:"14", hozAlign:"right", resizable:false},
			{title:"15:00", field:"15", hozAlign:"right", resizable:false},
			{title:"16:00", field:"16", hozAlign:"right", resizable:false},
			{title:"17:00", field:"17", hozAlign:"right", resizable:false},
			{title:"18:00", field:"18", hozAlign:"right", resizable:false},
			{title:"19:00", field:"19", hozAlign:"right", resizable:false},
			{title:"20:00", field:"20", hozAlign:"right", resizable:false},
			{title:"21:00", field:"21", hozAlign:"right", resizable:false},
			{title:"22:00", field:"22", hozAlign:"right", resizable:false},
			{title:"23:00", field:"23", hozAlign:"right", resizable:false},
		],
		rowFormatter:function(row){
			var cells = row.getCells();
			var i=0;
			cells.forEach(function(cell)
			{
				if(!isNaN(cell.getField()))
				{
					if(cell.getValue()>0)
						row.getElement().childNodes[i].style.color="green";
					else
						row.getElement().childNodes[i].style.color="red";
				}
				i++;
			});
			if(row.getCell('buy').getValue()=="<?=date('H')?>")
				row.select();
		},
	});

	//define some data
	var data_hrdiff_count = <?=$data_hrdiff_count?>;
	var hrdiff_count_table = new Tabulator("#hrdiff_count-table", {
		layout:"fitColumns",	//fit columns to width of table (optional)
		selectable:true,
		data:data_hrdiff_count,		//assign data to table
		columns:[				//Define Table Columns
			{title:"Sell<hr/>Buy",  field:"buy", headerSort:false, resizable:false, width:"30", hozAlign:"center"},
			{title:"01:00<br/><?=$data_hrdiff_count_avg["01"]?>%&#9650;", field:"01", hozAlign:"right", resizable:false},
			{title:"02:00<br/><?=$data_hrdiff_count_avg["02"]?>%&#9650;", field:"02", hozAlign:"right", resizable:false},
			{title:"03:00<br/><?=$data_hrdiff_count_avg["03"]?>%&#9650;", field:"03", hozAlign:"right", resizable:false},
			{title:"04:00<br/><?=$data_hrdiff_count_avg["04"]?>%&#9650;", field:"04", hozAlign:"right", resizable:false},
			{title:"05:00<br/><?=$data_hrdiff_count_avg["05"]?>%&#9650;", field:"05", hozAlign:"right", resizable:false},
			{title:"06:00<br/><?=$data_hrdiff_count_avg["06"]?>%&#9650;", field:"06", hozAlign:"right", resizable:false},
			{title:"07:00<br/><?=$data_hrdiff_count_avg["07"]?>%&#9650;", field:"07", hozAlign:"right", resizable:false},
			{title:"08:00<br/><?=$data_hrdiff_count_avg["08"]?>%&#9650;", field:"08", hozAlign:"right", resizable:false},
			{title:"09:00<br/><?=$data_hrdiff_count_avg["09"]?>%&#9650;", field:"09", hozAlign:"right", resizable:false},
			{title:"10:00<br/><?=$data_hrdiff_count_avg["10"]?>%&#9650;", field:"10", hozAlign:"right", resizable:false},
			{title:"11:00<br/><?=$data_hrdiff_count_avg["11"]?>%&#9650;", field:"11", hozAlign:"right", resizable:false},
			{title:"12:00<br/><?=$data_hrdiff_count_avg["12"]?>%&#9650;", field:"12", hozAlign:"right", resizable:false},
			{title:"13:00<br/><?=$data_hrdiff_count_avg["13"]?>%&#9650;", field:"13", hozAlign:"right", resizable:false},
			{title:"14:00<br/><?=$data_hrdiff_count_avg["14"]?>%&#9650;", field:"14", hozAlign:"right", resizable:false},
			{title:"15:00<br/><?=$data_hrdiff_count_avg["15"]?>%&#9650;", field:"15", hozAlign:"right", resizable:false},
			{title:"16:00<br/><?=$data_hrdiff_count_avg["16"]?>%&#9650;", field:"16", hozAlign:"right", resizable:false},
			{title:"17:00<br/><?=$data_hrdiff_count_avg["17"]?>%&#9650;", field:"17", hozAlign:"right", resizable:false},
			{title:"18:00<br/><?=$data_hrdiff_count_avg["18"]?>%&#9650;", field:"18", hozAlign:"right", resizable:false},
			{title:"19:00<br/><?=$data_hrdiff_count_avg["19"]?>%&#9650;", field:"19", hozAlign:"right", resizable:false},
			{title:"20:00<br/><?=$data_hrdiff_count_avg["20"]?>%&#9650;", field:"20", hozAlign:"right", resizable:false},
			{title:"21:00<br/><?=$data_hrdiff_count_avg["21"]?>%&#9650;", field:"21", hozAlign:"right", resizable:false},
			{title:"22:00<br/><?=$data_hrdiff_count_avg["22"]?>%&#9650;", field:"22", hozAlign:"right", resizable:false},
			{title:"23:00<br/><?=$data_hrdiff_count_avg["23"]?>%&#9650;", field:"23", hozAlign:"right", resizable:false},
		],
		columnDefaults:{
			headerTooltip:false,
			tooltip:function(cell){
				var field = cell.getField();
				var data = cell.getRow().getData()['x'+field];
				return data;
			}
		},
		rowFormatter:function(row){
			var cells = row.getCells();
			var i=0;
			cells.forEach(function(cell)
			{
				if(!isNaN(cell.getField()))
				{
					if(cell.getValue()>50)
						row.getElement().childNodes[i].style.color="green";
					else if(cell.getValue()<50)
						row.getElement().childNodes[i].style.color="red";
					else
						row.getElement().childNodes[i].style.color="blue";

					if(cell.getValue()>=60)
						row.getElement().childNodes[i].style.backgroundColor="#AAFFAA";
				}
				i++;
			});
			if(row.getCell('buy').getValue()=="<?=date('H')?>")
				row.select();
		},
	});

	//pattern
	var data_pattern = [<?=$data_pattern?>];
	$('.pattern-table').sparkline(data_pattern,{type:"line",width:"1020",height:"120",spotRadius:"5",fillColor:"",minSpotColor:"red",maxSpotColor:"green",spotColor:"",normalRangeMin:"-<?=intval($avg_volatility_amount/2)?>",normalRangeMax:"<?=intval($avg_volatility_amount/2)?>",drawNormalOnTop:"1",normalRangeColor:"PaleGreen"});

	//day high count
	var data_count_high = [<?=$data_count_high?>];
	$('.count-high-table').sparkline(data_count_high,{type:"bar",barWidth:"43",height:"50",barColor:"green",zeroColor:"white"});

	//day low count
	var data_count_low = [<?=$data_count_low?>];
	$('.count-low-table').sparkline(data_count_low,{type:"bar",barWidth:"43",height:"50",negBarColor:"red",zeroColor:"white"});

	//buttons
	$("#go_week").click(function(){
		$("input[name=date_from]").val("<?=$this_week_from?>");
		$("input[name=date_to]").val("<?=$this_week_to?>");
	});
	$("#go_month").click(function(){
		$("input[name=date_from]").val("<?=$this_month_from?>");
		$("input[name=date_to]").val("<?=$this_month_to?>");
	});
	$("#go_year").click(function(){
		$("input[name=date_from]").val("<?=$this_year_from?>");
		$("input[name=date_to]").val("<?=$this_year_to?>");
	});
	$("#go_all").click(function(){
		$("input[name=date_from]").val("2017-01-01");
		$("input[name=date_to]").val("<?=date('Y-m-d')?>");
	});
	$("#go_2023").click(function(){
		$("input[name=date_from]").val("2023-01-01");
		$("input[name=date_to]").val("2023-12-31");
	});
	$("#go_2022").click(function(){
		$("input[name=date_from]").val("2022-01-01");
		$("input[name=date_to]").val("2022-12-31");
	});
	$("#go_2021").click(function(){
		$("input[name=date_from]").val("2021-01-01");
		$("input[name=date_to]").val("2021-12-31");
	});
	$("#go_2020").click(function(){
		$("input[name=date_from]").val("2020-01-01");
		$("input[name=date_to]").val("2020-12-31");
	});
	$("#go_2019").click(function(){
		$("input[name=date_from]").val("2019-01-01");
		$("input[name=date_to]").val("2019-12-31");
	});
	$("#go_2018").click(function(){
		$("input[name=date_from]").val("2018-01-01");
		$("input[name=date_to]").val("2018-12-31");
	});
	$("#go_2017").click(function(){
		$("input[name=date_from]").val("2017-01-01");
		$("input[name=date_to]").val("2017-12-31");
	});

	//trigger download
	$("#download-raw").click(function(){
		data_table.download("xlsx", "data.xlsx");
	});

	//clear sort
	$("#hrdiff-reset").click(function(){
		hrdiff_table.clearSort();
	});
	$("#hrdiff_count-reset").click(function(){
		hrdiff_count_table.clearSort();
	});
});
</script>
<form method="get">
<a href='http://stat.unisoft.hk'><button type="button">&#8962; Home</button></a>
<input type="hidden" name="pair" value="<?=$_GET['pair']?>"/>
Date
<input type="date" name="date_from" value="<?=$date_from?>"/> ~ <input type="date" name="date_to" value="<?=$date_to?>"/>
<input type="submit" name="go" value="Go" style="width:50px"/>
<input type="submit" name="go" value="This Week" id="go_week"/>
<input type="submit" name="go" value="This Month" id="go_month"/>
<input type="submit" name="go" value="This Year" id="go_year"/>
<input type="submit" name="go" value="ALL" id="go_all"/>
<input type="submit" name="go" value="2023" id="go_2023"/>
<input type="submit" name="go" value="2022" id="go_2022"/>
<input type="submit" name="go" value="2021" id="go_2021"/>
<input type="submit" name="go" value="2020" id="go_2020"/>
<input type="submit" name="go" value="2019" id="go_2019"/>
<input type="submit" name="go" value="2018" id="go_2018"/>
<input type="submit" name="go" value="2017" id="go_2017"/>
<a href="?pair=<?=$_GET['pair']?>"><button type="button">Reset</button></a>
<br/>
Day
<label><input type="checkbox" name="day[]" value="1" <?=$checked_day1?>/>1</label> 
<label><input type="checkbox" name="day[]" value="2" <?=$checked_day2?>/>2</label> 
<label><input type="checkbox" name="day[]" value="3" <?=$checked_day3?>/>3</label> 
<label><input type="checkbox" name="day[]" value="4" <?=$checked_day4?>/>4</label> 
<label><input type="checkbox" name="day[]" value="5" <?=$checked_day5?>/>5</label> 
<label><input type="checkbox" name="day[]" value="6" <?=$checked_day6?>/>6</label> 
<label><input type="checkbox" name="day[]" value="7" <?=$checked_day7?>/>7(Sun)</label>
<br/>
Exclude Change >
<select name='exclude_change'>
	<option value="10000"></option>
	<option value="5000">5000</option>
	<option value="4000">4000</option>
	<option value="3000">3000</option>
	<option value="2000">2000</option>
	<option value="1000">1000</option>
	<option value="500">500</option>
	<option value="400">400</option>
	<option value="300">300</option>
	<option value="200">200</option>
	<option value="100">100</option>
	<option value="0">Reset</option>
</select>
Exclude Change % >
<select name='exclude_change_per'>
	<option value="50">50%</option>
	<option value="20">20%</option>
	<option value="10">10%</option>
	<option value="9">9%</option>
	<option value="8">8%</option>
	<option value="7">7%</option>
	<option value="6">6%</option>
	<option value="5">5%</option>
	<option value="4">4%</option>
	<option value="3">3%</option>
	<option value="2">2%</option>
	<option value="1">1%</option>
	<option value="0">Reset</option>
</select>
<a href="period.php?pair=<?=$_GET['pair']?>&date_from=<?=$date_from?>&date_to=<?=$date_to?>" target="_BLANK"><button type="button">&#128279; Period Diff</button></a>
<a href="holiday.php?pair=<?=$_GET['pair']?>" target="_BLANK"><button type="button">&#128279; Holiday</button></a>
</form>
<br/>
<table border="1" width="50%">
<tr align="center" bgcolor='#E6E6E6'>
	<td><?=$_GET['pair']?></td>
	<td>Total</td>
	<td>Day 1</td></td>
	<td>Day 2</td></td>
	<td>Day 3</td></td>
	<td>Day 4</td></td>
	<td>Day 5</td></td>
	<td>Day 6</td></td>
	<td>Day 7</td></td>
</tr>
<tr align="right">
	<td align="center">No. of days :<br/><?=$count_up+$count_down?></td>
	<td><font color="green">&#9650;<?=$count_up_per?>%<br/>&#9650;<?=$count_up?></font><br/><font color="red">&#9660;<?=$count_down?></td>
	<td><font color="green">&#9650;<?=$count_day[1]['per']?>%<br/>&#9650;<?=$count_day[1]['up']?></font><br/><font color="red">&#9660;<?=$count_day[1]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[2]['per']?>%<br/>&#9650;<?=$count_day[2]['up']?></font><br/><font color="red">&#9660;<?=$count_day[2]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[3]['per']?>%<br/>&#9650;<?=$count_day[3]['up']?></font><br/><font color="red">&#9660;<?=$count_day[3]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[4]['per']?>%<br/>&#9650;<?=$count_day[4]['up']?></font><br/><font color="red">&#9660;<?=$count_day[4]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[5]['per']?>%<br/>&#9650;<?=$count_day[5]['up']?></font><br/><font color="red">&#9660;<?=$count_day[5]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[6]['per']?>%<br/>&#9650;<?=$count_day[6]['up']?></font><br/><font color="red">&#9660;<?=$count_day[6]['down']?></td>
	<td><font color="green">&#9650;<?=$count_day[7]['per']?>%<br/>&#9650;<?=$count_day[7]['up']?></font><br/><font color="red">&#9660;<?=$count_day[7]['down']?></td>
</tr>
<tr align="right">
	<td align="center">Average<br/>Change</td>
	<td><?=$avg_per?>%<br/><?=$avg_amount?></td>
	<td><?=$avg_day[1]['per']?>%<br/><?=$avg_day[1]['amount']?></td>
	<td><?=$avg_day[2]['per']?>%<br/><?=$avg_day[2]['amount']?></td>
	<td><?=$avg_day[3]['per']?>%<br/><?=$avg_day[3]['amount']?></td>
	<td><?=$avg_day[4]['per']?>%<br/><?=$avg_day[4]['amount']?></td>
	<td><?=$avg_day[5]['per']?>%<br/><?=$avg_day[5]['amount']?></td>
	<td><?=$avg_day[6]['per']?>%<br/><?=$avg_day[6]['amount']?></td>
	<td><?=$avg_day[7]['per']?>%<br/><?=$avg_day[7]['amount']?></td>
</tr>
<tr align="right">
	<td align="center">Average<br/>Volatility</td>
	<td><?=$avg_volatility_per?>%<br/><?=$avg_volatility_amount?></td>
	<td><?=$avg_volatility[1]['per']?>%<br/><?=$avg_volatility[1]['amount']?></td>
	<td><?=$avg_volatility[2]['per']?>%<br/><?=$avg_volatility[2]['amount']?></td>
	<td><?=$avg_volatility[3]['per']?>%<br/><?=$avg_volatility[3]['amount']?></td>
	<td><?=$avg_volatility[4]['per']?>%<br/><?=$avg_volatility[4]['amount']?></td>
	<td><?=$avg_volatility[5]['per']?>%<br/><?=$avg_volatility[5]['amount']?></td>
	<td><?=$avg_volatility[6]['per']?>%<br/><?=$avg_volatility[6]['amount']?></td>
	<td><?=$avg_volatility[7]['per']?>%<br/><?=$avg_volatility[7]['amount']?></td>
</tr>
</table>
<br/>

<table border="1" width="1200">
<tr align="center">
	<td>Hourly Differences</td>
	<td colspan="24"><span class="pattern-table"></span></td>
</tr>
<?=$hrly_head?>
<?=$hrly_body?>
<tr align="center">
	<td>Day High Count</td>
	<td colspan="24"><span class="count-high-table"></span></td>
</tr>
<tr align="center">
	<td>Day Low Count</td>
	<td colspan="24"><span class="count-low-table"></span></td>
</tr>
</table>
<br/>
<br/>
Hour Differences Up % (No. of days)
<button type="button" id="hrdiff_count-reset">Reset</button>
<div id="hrdiff_count-table"></div>
<br/>
Hour Differences (Amount)
<button type="button" id="hrdiff-reset">Reset</button>
<div id="hrdiff-table"></div>
<br/>
Day High and Low Hour Count
<div id="hlcount-table"></div>
<br/>
<a href="raw.php?pair=<?=$_GET['pair']?>" target="_blank">Raw</a>
<button type="button" id="download-raw">Download</button>
<div id="data-table"></div>
</body>
</html>
