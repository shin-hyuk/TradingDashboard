<?php
$connect=mysql_connect('127.0.0.1:6447','crypto_stat','crypto2023');
mysql_select_db('_crypto_stat',$connect) or die(mysql_error());

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '60');

if(empty($_GET[pair]))
	$_GET[pair]="BTCUSDT";

//format date
if(!empty($_GET[date_from]))
	$date_from=date('Y-m-d',strtotime($_GET[date_from]));
if(!empty($_GET[date_to]))
	$date_to=date('Y-m-d',strtotime($_GET[date_to]));

//date query
if(!empty($_GET[date_from]))
	$query=" AND date>='$_GET[date_from]'";
if(!empty($_GET[date_to]))
	$query.=" AND date<='$_GET[date_to]'";

//day query
if(empty($_GET['day']))
{
	//$checked_day1=$checked_day2=$checked_day3=$checked_day4=$checked_day5=$checked_day6=$checked_day7='checked';
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

$_GET[profit]=intval($_GET[profit]);

//must select buy and sell hour
if(empty($_GET[hour_buy])&&empty($_GET[hour_sell]))
	$query=" AND 0";

//all days in range
$data_all=array();
$sql="SELECT * FROM $_GET[pair] WHERE 1 $query ORDER BY date DESC";
$result=mysql_query($sql) or die(mysql_error());
while($row=mysql_fetch_assoc($result))
{
	$data_all[$row[date]]=$row;
}

//query data
$data=array();
$sql="SELECT * FROM $_GET[pair] WHERE 1 $query $query_day $query_exclude ORDER BY date DESC";
$result=mysql_query($sql) or die(mysql_error());
while($row=mysql_fetch_assoc($result))
{
	$row[mid]=intval(($row[high]+$row[low])/2);
	$data[$row[date]]=$row;
}
//add day
reset($data);

//calculate period differences
$diff=array();
$diff_exclude=array();
foreach($data as $key=>$row)
{
	//tag checkbox up or down
	if($row["$_GET[hour_buy]"]>$row[mid])
		$tag="data-tag='1'";
	else
		$tag="data-tag='2'";

	if(in_array($key,$_GET[exclude]))
	{
		$output.="<tr align='right' bgcolor='#E6E6E6'>\n";
		$output.="<td align='center'>";
		$output.="<input type='checkbox' name='exclude[]' value='$key' class='checkbox-large' $tag checked>";
		$output.="</td>\n";
	}
	else
	{
		$output.="<tr align='right'>\n";
		$output.="<td align='center'>";
		$output.="<input type='checkbox' name='exclude[]' value='$key' class='checkbox-large' $tag>";
		$output.="</td>\n";
	}
	$output.="<td>$key</td>\n";

	$output.="<td><font color='green'>$row[high]<br/><font color='blue'>$row[mid]<br/><font color='red'>$row[low]</td>\n";

	//-1H
	$output.="<td><u>".$data_all[date("Y-m-d",strtotime("$key $_GET[hour_buy]:00:00")-1*60*60)][date("H",strtotime("$key $_GET[hour_buy]:00:00")-1*60*60)]."</u><br/>".str_replace(' ','<br/>',date("Y-m-d H:i",strtotime("$key $_GET[hour_buy]:00:00")-1*60*60))."</td>\n";

	//buy price
	if($row["$_GET[hour_buy]"]>$row[mid])
		$output.="<td bgcolor='#AAFFAA'>".$row["$_GET[hour_buy]"]."<br/>(".($row["$_GET[hour_buy]"]-$row[mid]).")</td>\n";
	else
		$output.="<td bgcolor='#FFAAAA'>(".($row["$_GET[hour_buy]"]-$row[mid]).")<br/>".$row["$_GET[hour_buy]"]."</td>\n";

	//display all hours
	$done=0;

	//start
	if($_GET[vs_avg]!=0)
		$started=0;
	else
		$started=1;

	//for each hour in each day
	for($i=$_GET[hour_sell];$i<24;$i++)
	{
		$hour_sell=str_pad($i,2,'0',STR_PAD_LEFT);

		$temp_profit=$row["$hour_sell"]-$row["$_GET[hour_buy]"];

		if(!$started&&($row["$hour_sell"]-$row[mid]<$_GET[vs_avg]))
		{
			$started=1;
			$diff_count_vs_avg_started++;
		}

		if(!$started)
		{
			if($temp_profit>0)
				$output.="<td bgcolor='#E6E6E6' style='font-size:smaller'><font color='blue'>".$temp_profit."</font>";
			else
				$output.="<td bgcolor='#E6E6E6' style='font-size:smaller'><font color='red'>".$temp_profit."</font>";
			$output.="<br/><u>".$row["$hour_sell"]."</u><br/>".($row["$hour_sell"]-$row[mid])."</td>\n";

			$diff_count["$hour_sell"]++;
			$diff["$hour_sell"]+=$temp_profit;
		}
		else
		{
			if(!$done)
			{
				if($temp_profit>$_GET[profit])
				{
					//profit amount
					for($j=$i;$j<24;$j++)
					{
						$hour_sell_temp=str_pad($j,2,'0',STR_PAD_LEFT);
						$diff["$hour_sell_temp"]+=$temp_profit;
						
						//exclude
						if(!in_array($key,$_GET[exclude]))
							$diff_exclude["$hour_sell_temp"]+=$temp_profit;
					}

					$diff_count["$hour_sell"]=$diff_count["$hour_sell"];
					//exclude
					if(!in_array($key,$_GET[exclude]))
						$diff_count_exclude["$hour_sell"]=$diff_count_exclude["$hour_sell"];

					$diff_count_vs_avg_done++;
					$diff_count_vs_avg_amount+=$temp_profit;

					if($temp_profit>0)
						$output.="<td style='font-weight:bold'><font class='green'>".$temp_profit."</font>";
					else
						$output.="<td>".$temp_profit;
					$output.="<br/><u>".$row["$hour_sell"]."</u><br/>".($row["$hour_sell"]-$row[mid])."</td>\n";

					$done=1;
				}
				else
				{
					//profit amount
					$diff["$hour_sell"]+=$temp_profit;

					//exclude
					if(!in_array($key,$_GET[exclude]))
						$diff_exclude["$hour_sell"]+=$temp_profit;

					$diff_count["$hour_sell"]++;
					//exclude
					if(!in_array($key,$_GET[exclude]))
						$diff_count_exclude["$hour_sell"]++;

					if($temp_profit>0)
						$output.="<td><font color='blue'>".$temp_profit."</font>";
					else
						$output.="<td><font color='red'>".$temp_profit."</font>";
					$output.="<br/><u>".$row["$hour_sell"]."</u><br/>".($row["$hour_sell"]-$row[mid])."</td>\n";
				}
			}
			else
			{
				if($temp_profit>0)
					$output.="<td bgcolor='#E6E6E6' style='font-size:smaller'><font color='blue'>".$temp_profit."</font>";
				else
					$output.="<td bgcolor='#E6E6E6' style='font-size:smaller'><font color='red'>".$temp_profit."</font>";
				$output.="<br/><u>".$row["$hour_sell"]."</u><br/>".($row["$hour_sell"]-$row[mid])."</td>\n";
			}
		}

		if($_GET[vs_avg]!=0&&$started&&!$done&&$i==23)
			$diff_count_vs_avg_amount+=$temp_profit;

		//+1H
		if($i==23)
			$output.="<td><u>".$data_all[date("Y-m-d",strtotime("$key 23:00:00")+1*60*60)][date("H",strtotime("$key 23:00:00")+1*60*60)]."</u><br/>".str_replace(' ','<br/>',date("Y-m-d H:i",strtotime("$key 23:00:00")+1*60*60))."</td>\n";
	}

	$output.="</tr>\n";
}

//prepare vs_avg
if($_GET[vs_avg]!=0)
{
	$display_vs_avg_class="style='background:Aqua'";
	$display_vs_avg_rate=intval($diff_count_vs_avg_done/$diff_count_vs_avg_started*100)."%<br/>$diff_count_vs_avg_done/$diff_count_vs_avg_started";
	$display_vs_avg_amount=$diff_count_vs_avg_amount;
}

//prepare output
$output_header="<tr align='right' bgcolor='#E6E6E6'>\n";
$output_header.="<td align='center'><input type='submit' name='go' value='Exclude'/></td>\n";
$output_header.="<td>Sell Hour</td>\n";
$output_header.="<td align='center'>H/Mid/L</td>\n";
$output_header.="<td>-1H</td>\n";
$output_header.="<td>".$_GET[hour_buy].":00</td>\n";
foreach($diff as $key=>$row)
{
	$output_header.="<td>".$key.":00</td>\n";

	if($row>0)
		$output_amount.="<td>".$row."</td>\n";
	else
		$output_amount.="<td class='red'>".$row."</td>\n";
}
$output_header.="<td>+1H</td>\n";
$output_header.="</tr>\n";
$output_amount="<tr align='right'><td align='center'><button type='button' id='clear_exclude'>Clear</button></td><td>Cum Amt</td><td colspan='3'><button type='button' id='exclude_up'>📗 綠</button> <button type='button' id='exclude_down'>📕 紅</button></td>".$output_amount."<td $display_vs_avg_class>$display_vs_avg_amount</td></tr>\n";

foreach($diff_exclude as $key=>$row)
{
	if($row>0)
		$output_amount_exclude.="<td>".$row."</td>\n";
	else
		$output_amount_exclude.="<td class='red'>".$row."</td>\n";
}
$output_amount_exclude="<tr align='right' style='border-bottom:dotted'><td></td><td>Cum Amt</td><td colspan='3'>Excluded</td>".$output_amount_exclude."<td></td></tr>\n";

//count rate
foreach($diff_count as $key=>$row)
{
	$temp=intval(100-$row/count($data)*100);
	if($temp>=90)
		$output_rate.="<td bgcolor='#AAFFAA'>".$temp."%<br/>".(count($data)-$row)."/".count($data)."</td>\n";
	elseif($temp>50)
		$output_rate.="<td>".$temp."%<br/>".(count($data)-$row)."/".count($data)."</td>\n";
	else
		$output_rate.="<td class='red'>".$temp."%<br/>".(count($data)-$row)."/".count($data)."</td>\n";
}
$output_rate="<tr align='right'><td></td><td>Cum Up</td><td colspan='3'></td>".$output_rate."<td $display_vs_avg_class>$display_vs_avg_rate</td></tr>\n";
//<td>Max</td><td>Avg</td><td>Min</td><td>Avg</td>

foreach($diff_count_exclude as $key=>$row)
{
//	$temp=intval((count($data)-$row)/(count($data)-count($_GET[exclude]))*100);
	$temp=intval(100-$row/(count($data)-count($_GET[exclude]))*100);
	if($temp>50)
		$output_rate_exclude.="<td>".$temp."%<br/>".(count($data)-count($_GET[exclude])-$row)."/".(count($data)-count($_GET[exclude]))."</td>\n";
	else
		$output_rate_exclude.="<td class='red'>".$temp."%<br/>".(count($data)-count($_GET[exclude])-$row)."/".(count($data)-count($_GET[exclude]))."</td>\n";
}
$output_rate_exclude="<tr align='right' style='border-top:dotted'><td></td><td>Cum Up</td><td colspan='3'>Excluded</td>".$output_rate_exclude."<td></td></tr>\n";

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
<title><?=$_GET[pair]?> Period</title>
<style>
table {border-collapse:separate;border:1px solid grey;border-spacing:0}
td {border:1px solid grey}
.tabulator {font-size:12px}
.red {color:red}
.green {color:green}
.blue {color:blue}
.checkbox-large {transform:scale(2,2)}
</style>
</head>
<body>
<script type="text/javascript">
$(function() {
	$("[name=hour_buy]").val("<?=$_GET[hour_buy]?>").change();
	$("[name=hour_sell]").val("<?=$_GET[hour_sell]?>").change();
	$("[name=profit]").val("<?=$_GET[profit]?>").change();
	$("[name=vs_avg]").val("<?=$_GET[vs_avg]?>").change();

	$("#clear_exclude").click(function() {
		var checkBoxes = $("input[name=exclude\\[\\]]");
		checkBoxes.prop("checked", false);
	});

	$("#exclude_up").click(function() {
		var checkBoxes = $("input[name=exclude\\[\\]]");
		checkBoxes.each(function(index) {
			if($(this).data("tag")==1)
				$(this).prop("checked",!$(this).prop("checked"));
		});
	});
	$("#exclude_down").click(function() {
		var checkBoxes = $("input[name=exclude\\[\\]]");
		checkBoxes.each(function(index) {
			if($(this).data("tag")==2)
				$(this).prop("checked",!$(this).prop("checked"));
		});
	});

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
	$("#go_default").click(function(){
		$("#clear_exclude").click();
		$("[name=hour_buy]").val("00").change();
		$("[name=hour_sell]").val("01").change();
		$("[name=profit]").val("50").change();
		$("[name=vs_avg]").val("0").change();
		$("#go_year").click();
	});
});
</script>
<form method="get">
<input type="hidden" name="pair" value="<?=$_GET[pair]?>"/>
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
<input type="submit" name="go" value="Default" id="go_default"/>
<br/>
Buy
<select name="hour_buy" required>
	<option></option>
	<option value="00">00</option>
	<option value="01">01</option>
	<option value="02">02</option>
	<option value="03">03</option>
	<option value="04">04</option>
	<option value="05">05</option>
	<option value="06">06</option>
	<option value="07">07</option>
	<option value="08">08</option>
	<option value="09">09</option>
	<option value="10">10</option>
	<option value="11">11</option>
	<option value="12">12</option>
	<option value="13">13</option>
	<option value="14">14</option>
	<option value="15">15</option>
	<option value="16">16</option>
	<option value="17">17</option>
	<option value="18">18</option>
	<option value="19">19</option>
	<option value="20">20</option>
	<option value="21">21</option>
	<option value="22">22</option>
	<option value="23">23</option>
</select>
Sell
<select name="hour_sell" required>
	<option></option>
	<option value="00">00</option>
	<option value="01">01</option>
	<option value="02">02</option>
	<option value="03">03</option>
	<option value="04">04</option>
	<option value="05">05</option>
	<option value="06">06</option>
	<option value="07">07</option>
	<option value="08">08</option>
	<option value="09">09</option>
	<option value="10">10</option>
	<option value="11">11</option>
	<option value="12">12</option>
	<option value="13">13</option>
	<option value="14">14</option>
	<option value="15">15</option>
	<option value="16">16</option>
	<option value="17">17</option>
	<option value="18">18</option>
	<option value="19">19</option>
	<option value="20">20</option>
	<option value="21">21</option>
	<option value="22">22</option>
	<option value="23">23</option>
</select>
Day
<label><input type="checkbox" name="day[]" value="1" <?=$checked_day1?>/>1</label> 
<label><input type="checkbox" name="day[]" value="2" <?=$checked_day2?>/>2</label> 
<label><input type="checkbox" name="day[]" value="3" <?=$checked_day3?>/>3</label> 
<label><input type="checkbox" name="day[]" value="4" <?=$checked_day4?>/>4</label> 
<label><input type="checkbox" name="day[]" value="5" <?=$checked_day5?>/>5</label> 
<label><input type="checkbox" name="day[]" value="6" <?=$checked_day6?>/>6</label> 
<label><input type="checkbox" name="day[]" value="7" <?=$checked_day7?>/>7(Sun)</label>
<br/>
Profit >=
<select name="profit">
	<option>0</value>
	<option>20</value>
	<option>50</value>
	<option>100</value>
	<option>200</value>
	<option>300</value>
	<option>400</value>
	<option>500</value>
	<option>600</value>
	<option>700</value>
	<option>800</value>
	<option>900</value>
	<option>1000</value>
	<option>2000</value>
	<option>-10</value>
	<option>-20</value>
	<option>-50</value>
	<option>-100</value>
	<option>-300</value>
	<option>-500</value>
	<option>-1000</value>
	<option>-2000</value>
	<option>-3000</value>
</select>
Start when below Avg
<select name="vs_avg" style="background:Aqua">
	<option>0</value>
	<option>-100</value>
	<option>-500</value>
	<option>-1000</value>
	<option>-1500</value>
	<option>-2000</value>
</select>
Stop lost
<select style="background:Magenta">
</select>
<br/>
<?=$_GET[pair]?>
<br/>
<table border="1" cellpadding="3px">
<thead bgcolor="#FFFFFF" style="position:sticky;top:0;z-index:1">
<?=$output_rate?>
<?=$output_amount?>
<?php
	if(count($_GET[exclude])>0)
	{
		echo $output_rate_exclude;
		echo $output_amount_exclude;
	}
?>
<?=$output_header?>
</thead>
<tbody>
<?=$output?>
<?=$output_header?>
</tbody>
</table>
</form>
</body>
</html>
