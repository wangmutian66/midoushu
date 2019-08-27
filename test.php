<?php
	$arr['2018-12-22'] = 291;
	$arr['2018-12-24'] = 123;
	$arr['2018-12-26'] = 29341;
	$arr['2018-12-28'] = 295451;
	$start = "2018-12-01";
	$end = "2018-12-31";
	if(!array_key_exists($start, $arr)){
		$start_arr[$start]=0;
		$arr = $start_arr+$arr;
	}
	if(!array_key_exists($end, $arr)){
		$arr[$end] = 0;
	}
	$last = '';
	foreach($arr as $key=>$r) {
	  while($last && $last < $key) {
	    $res[$last] = 0;
	    $last = date('Y-m-d', $last = strtotime("+1 day {$last}"));
	  }
	  $res[$key] = $r;
	  $last = date('Y-m-d', strtotime("+1 day {$key}"));
	}
	var_dump($res);
?>