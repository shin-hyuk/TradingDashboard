<?php
if(isset($_FILES['csv']))
{
	$connect=mysql_connect('127.0.0.1:6446','crypto_stat','crypto2023');
	mysql_select_db('_crypto_stat',$connect) or die(mysql_error());

	if($_FILES['csv']['size']>0)
	{
		//create new temp table
		mysql_query("DROP TABLE IF EXISTS `temp_csv`");
		mysql_query("CREATE TABLE `temp_csv` (
			`id` bigint NOT NULL,
			`record_time` datetime DEFAULT NULL,
			`pair` varchar(8) DEFAULT NULL,
			`open` int DEFAULT NULL,
			`high` int DEFAULT NULL,
			`low` int DEFAULT NULL,
			`close` int DEFAULT NULL,
			`vol_btc` int DEFAULT NULL,
			`vol_usdt` bigint DEFAULT NULL,
			`count` int DEFAULT NULL,
			`gmt8` datetime NOT NULL
			)");
		mysql_query("ALTER TABLE `temp_csv` ADD PRIMARY KEY(`id`)");

		//import csv
		$row = 1;
		if(($handle = fopen($_FILES['csv']['tmp_name'], "r")) !== FALSE)
		{
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
			{
				if($row>1000)
				{
					//process first 1000 lines
					break;
				}
				elseif($row++>2)
				{
					//skip header & field name
					mysql_query("INSERT INTO temp_csv VALUES ('$data[0]','$data[1]','$data[2]','$data[3]','$data[4]','$data[5]','$data[6]','$data[7]','$data[8]','$data[9]','')");
				}
			}
			fclose($handle);
		}
		echo 'Import Done<br>';

		//refine data
		mysql_query("DELETE from `temp_csv` WHERE `record_time`>'2020-08-01' AND `record_time`<'2020-11-21' AND `id`>99999999999");
		mysql_query("UPDATE `temp_csv` SET `id`=`id`/1000 WHERE `id`>99999999999");
		mysql_query("UPDATE `temp_csv` SET gmt8 = date_add( `record_time`, INTERVAL 8 HOUR )");

		//prepare raw
		if(strpos($_FILES['csv']['name'],'BTCUSDT') !== false)
			$target_table='BTCUSDT';
		elseif(strpos($_FILES['csv']['name'],'ETHUSDT') !== false)
			$target_table='ETHUSDT';

		//clear cache
		mysql_query("TRUNCATE TABLE ".$target_table."_diff");

		$last_date=mysql_result(mysql_query("SELECT date FROM $target_table ORDER BY date DESC LIMIT 7,1"),0);

		$sql="SELECT * FROM `temp_csv` WHERE gmt8>'$last_date 23:59:59' ORDER BY gmt8";
//echo $sql;
		$result=mysql_query($sql) or die(mysql_error());
		$data=array();
		while($row=mysql_fetch_assoc($result))
		{
			$temp_date=substr($row[gmt8],0,10);
			$temp_hour=substr($row[gmt8],11,2);
			$data[$temp_date][$temp_hour]=$row[open];

			if($temp_hour=='00')
				$data[$temp_date][$temp_hour][open]=$row[open];
			if($row[high]>$data[$temp_date][high])
			{
				$data[$temp_date][high]=$row[high];
				$data[$temp_date][high_hour]=$temp_hour;
			}
			if($row[low]<$data[$temp_date][low]||!isset($data[$temp_date][low]))
			{
				$data[$temp_date][low]=$row[low];
				$data[$temp_date][low_hour]=$temp_hour;
			}
		}

		foreach($data as $date=>$row)
		{
			$sql="REPLACE INTO $target_table VALUES ('$date',
			'".$row['00']."','".$row['01']."','".$row['02']."','".$row['03']."','".$row['04']."','".$row['05']."',
			'".$row['06']."','".$row['07']."','".$row['08']."','".$row['09']."','".$row['10']."','".$row['11']."',
			'".$row['12']."','".$row['13']."','".$row['14']."','".$row['15']."','".$row['16']."','".$row['17']."',
			'".$row['18']."','".$row['19']."','".$row['20']."','".$row['21']."','".$row['22']."','".$row['23']."',
			'$row[high]','$row[low]','$row[high_hour]','$row[low_hour]')";
//echo "$sql<br>";
			mysql_query($sql);
		}
		echo 'Update done';
	}
	echo '<hr>';
}

?>
<h1><a href='https://www.cryptodatadownload.com/cdd/Binance_BTCUSDT_1h.csv' target='_BLANK'>https://www.cryptodatadownload.com/cdd/Binance_BTCUSDT_1h.csv</a><h1>
<h1><a href='https://www.cryptodatadownload.com/cdd/Binance_ETHUSDT_1h.csv' target='_BLANK'>https://www.cryptodatadownload.com/cdd/Binance_ETHUSDT_1h.csv</a><h1>
<form method="post" enctype="multipart/form-data">
	<input type="file" name="csv">
	<input type="submit">
</form>
