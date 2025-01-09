<?php
$connect=mysql_connect('192.168.10.17:3306','jason','Abc123456');
mysql_select_db('stat_unisoft_hk',$connect) or die(mysql_error());

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '60');

if(empty($_GET[pair]))
	$_GET[pair]="BTCUSDT";

if(empty($_GET[interval]))
	$_GET[interval]=7;

$go=array();
if($_GET[go]=='CNY')
{
	$go_target[2023]='2023-01-22';
	$go_target[2022]='2022-02-01';
	$go_target[2021]='2021-02-12';
	$go_target[2020]='2020-01-25';
	$go_target[2019]='2019-02-05';
	$go_target[2018]='2018-02-16';
	$go_target[2017]='2017-01-28';
}
elseif($_GET[go]=='EASTER')
{
	$go_target[2023]='2023-04-09';
	$go_target[2022]='2022-04-17';
	$go_target[2021]='2021-04-04';
	$go_target[2020]='2020-04-12';
	$go_target[2019]='2019-04-21';
	$go_target[2018]='2018-04-01';
	$go_target[2017]='2017-04-16';
}
elseif(isset($_GET[go]))
{
	$sql="SELECT DISTINCT YEAR(date) FROM $_GET[pair] ORDER BY date DESC";
	$result=mysql_query($sql) or die(mysql_error());
	while($row=mysql_fetch_array($result))
	{
		if($_GET[go]=='XMAS')
			$go_target[$row[0]]=$row[0].'-12-25';
		elseif($_GET[go]=='JAN')
			$go_target[$row[0]]=$row[0].'-01-01';
		elseif($_GET[go]=='FEB')
			$go_target[$row[0]]=$row[0].'-02-01';
		elseif($_GET[go]=='MAR')
			$go_target[$row[0]]=$row[0].'-03-01';
		elseif($_GET[go]=='APR')
			$go_target[$row[0]]=$row[0].'-04-01';
		elseif($_GET[go]=='MAY')
			$go_target[$row[0]]=$row[0].'-05-01';
		elseif($_GET[go]=='JUN')
			$go_target[$row[0]]=$row[0].'-06-01';
		elseif($_GET[go]=='JUL')
			$go_target[$row[0]]=$row[0].'-07-01';
		elseif($_GET[go]=='AUG')
			$go_target[$row[0]]=$row[0].'-08-01';
		elseif($_GET[go]=='SEPT')
			$go_target[$row[0]]=$row[0].'-09-01';
		elseif($_GET[go]=='OCT')
			$go_target[$row[0]]=$row[0].'-10-01';
		elseif($_GET[go]=='NOV')
			$go_target[$row[0]]=$row[0].'-11-01';
		elseif($_GET[go]=='DEC')
			$go_target[$row[0]]=$row[0].'-12-01';
	}
}

if(is_array($go_target))
{
	$data_pattern=array();
	foreach($go_target as $go_target_year=>$go_target_start)
	{
		$sql="SELECT * FROM $_GET[pair] WHERE date>='$go_target_start' - INTERVAL $_GET[interval] DAY AND date<='$go_target_start' + INTERVAL $_GET[interval] DAY ORDER BY date";
		$result=mysql_query($sql) or die(mysql_error());
		while($row=mysql_fetch_assoc($result))
		{
			$data_pattern[$go_target_year][$row[date]]=$row[23];
		}
	}
}

foreach($data_pattern as $year=>$data)
{
	$temp=$temp2=$temp3=$temp4='';
	unset($diff_start);

	$i=0;
	foreach($data as $date=>$row)
	{
		$highlight='';
		if(!isset($diff_start))
			$diff_start=$row;
		elseif(intval(count($data_pattern[$year])/2)==$i)
		{
			$diff_end=$row;
			$highlight="bgcolor='yellow'";
		}

		$temp.=",".$row;
		$temp2.="<td $highlight>".$date."<br/>(".(($i-$_GET[interval])>0?'+':'').($i-$_GET[interval]).") Day ".date('N',strtotime($date))."</td>";
		$temp3.="<td>$row</td>";

		$i++;
	}
	$temp=substr($temp,1);
	$diff_advance=$row;

	reset($data);
	$i=0;
	foreach($data as $date=>$row)
	{
		if($i==intval(count($data_pattern[$year])/2))
			$diff='';
		elseif($i>intval(count($data_pattern[$year])/2))
			$diff=$row-$diff_end;
		else
			$diff=$diff_end-$row;

		if($diff>0)
			$temp4.="<td class='green'>+$diff<br/>+".intval($diff/$diff_end*100)."%</td>";
		else
			$temp4.="<td class='red'>$diff<br/>".intval($diff/$diff_end*100)."%</td>";

		$i++;
	}

	$output_data_pattern.="\t"."var data_pattern_$year = [".$temp."];\n";
	$output_pattern_script.="\t"."$('.pattern-table_$year').sparkline(data_pattern_$year,{type:'line',width:'100%',height:'100',spotRadius:'5',fillColor:'',minSpotColor:'red',maxSpotColor:'green',spotColor:'',drawNormalOnTop:'1',normalRangeColor:'PaleGreen'})\n";

	$output_pattern_table.="\t"."<tr align='center' style='border-top:dotted'><td colspan='99'><span style='position:absolute;z-index:-1'><h2>$year</h2></span><span class='pattern-table_$year'></span></td></tr>\n";
	$output_pattern_table.="\t"."<tr align='center'>$temp2</tr>\n";
	$output_pattern_table.="\t"."<tr align='center'>$temp3</tr>\n";
	$output_pattern_table.="\t"."<tr align='center'>$temp4</tr>\n";
}
?>
<!DOCTYPE html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
<script type="text/javascript" src="https://oss.sheetjs.com/sheetjs/xlsx.full.min.js"></script>
<script type="text/javascript" src="jquery.sparkline.min.js"></script>
<title><?=$_GET[pair]?> Holiday</title>
<style>
table {border-collapse:collapse;border:1px solid grey}
.red {color:red}
.green {color:green}
.blue {color:blue}
</style>
</head>
<body>
<script type="text/javascript">
$(function() {
	//set vars
	$("input[type=submit][value='<?=$_GET[go]?>']").css("background","yellow");
	$("[name=interval]").val("<?=$_GET[interval]?>").change();

	//pattern
<?=$output_data_pattern?>
<?=$output_pattern_script?>
});
</script>
<form method="get">
<input type="hidden" name="pair" value="<?=$_GET[pair]?>"/>
<a href='http://stat.unisoft.hk'><button type="button">&#8962; Home</button></a>
<?=$_GET[pair]?> 
&pm;<select name='interval'>
	<option>30</option>
	<option>15</option>
	<option>7</option>
	<option>6</option>
	<option>5</option>
	<option>4</option>
	<option>3</option>
	<option>2</option>
	<option>1</option>
</select>
<input type="submit" name="go" value="CNY" style="width:50px"/>
<input type="submit" name="go" value="EASTER"/>
<input type="submit" name="go" value="XMAS"/>
<input type="submit" name="go" value="JAN"/>
<input type="submit" name="go" value="FEB"/>
<input type="submit" name="go" value="MAR"/>
<input type="submit" name="go" value="APR"/>
<input type="submit" name="go" value="MAY"/>
<input type="submit" name="go" value="JUN"/>
<input type="submit" name="go" value="JUL"/>
<input type="submit" name="go" value="AUG"/>
<input type="submit" name="go" value="SEPT"/>
<input type="submit" name="go" value="OCT"/>
<input type="submit" name="go" value="NOV"/>
<input type="submit" name="go" value="DEC"/>
</form>
<br/>
<table border="1">
<?=$output_pattern_table?>
</table>
</body>
</html>
