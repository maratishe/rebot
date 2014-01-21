<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( "");
//htg( clget( ''));
$JSONENCODER = 'jsonencode'; // jsonraw | jsonencode


echo "\n\n"; $in = finopen( 'raw.bz64jsonl');
$skip = mt_rand( 1, 500); echo "skip#$skip\n"; 
$S2L = null;  // { station: { line: true, ...}, ...}
$L2S = null;  // { line: { station: next station | null, ...}, ...}
while ( ! findone( $in) && $skip-- > 0) {
	list( $h, $p) = finread( $in); if ( ! $h) continue;
	extract( $h); // L2S, S2L
}
echo "lines: " . ltt( hk( $L2S), ' ') . "\n";
echo "stations: " . ltt( hk( $S2L), ' ') . "\n";
finclose( $in);


function prepare( $LINES, $lines) {
	global $S2L, $L2S;
	$C = array(); foreach ( $lines as $line) foreach ( $L2S[ $line] as $station => $v) { htouch( $C, $station, 0, false, false); $C[ $station]++; }
	$M = array(); foreach ( $lines as $line) foreach ( $L2S[ $line] as $station => $v) foreach ( $LINES as $LINE) if ( isset( $L2S[ $LINE][ $station])) { htouch( $M, $station, 0, false, false); $M[ $station]++; }
	return array( $C, $M);
}
function Overlap( $LINES, $lines) {
	global $L2S, $S2L;
	list( $C, $M) = prepare( $LINES, $lines);
	return count( $M);
}
function Contribute( $LINES, $lines) {
	global $L2S, $S2L;
	list( $C, $M) = prepare( $LINES, $lines);
	return count( $C);
}
function OverlapNotExtend( $LINES, $lines) {
	global $L2S, $S2L;
	list( $C, $M) = prepare( $LINES, $lines);
	return count( $M) / count( $C);
}
function OverlapManyLines( $LINES, $lines) {
	global $L2S, $S2L;
	list( $C, $M) = prepare( $LINES, $lines);
	return count( $M) * count( $lines);
}
function makemap( $metric) {
	global $L2S, $S2L; 
	$h = array(); foreach ( $L2S as $line => $h2) $h[ $line] = count( $h2);
	arsort( $h, SORT_NUMERIC); list( $line, $length) = hfirst( $h);
	echo "longest line#$line length#$length\n";
	$counter = 0; $e = echoeinit(); $e2 = echoeinit(); 
	$UL = array(); $UL[ $line] = true; $MAP[ "$line"] = 0; // used lines  { line: y, ...}
	while ( count( $UL) < count( $L2S)) {
		$counter++; echoe( $e2, ''); echoe( $e, "RUN#$counter  " . count( $UL) . '<' . count( $L2S));
		$E = array(); // { 'y -- line -- line -- ...': eval, ... }    -- keep top 25 only 
		asort( $MAP, SORT_NUMERIC);
		for ( $i = 0; $i < 1000; $i++) {
			echoe( $e2, "  random#$i");
			$lines = array(); foreach ( $L2S as $line => $h) if ( ! isset( $UL[ $line])) $lines[ $line] = true;
			$lines = hk( $lines); shuffle( $lines); $count = mt_rand( 1, count( $lines));
			while ( count( $lines) < $count) lpop( $lines);
			while ( count( $lines) > 1) { 
				$C = array(); 
				foreach ( $lines as $line) foreach ( $L2S[ $line] as $station => $v) { htouch( $C, $station, 0, false, false); $C[ $station]++; }
				if ( mmax( hv( $C)) > 1) lpop( $lines);
				else break;		// these >1 lines can fit together
			}
			// try to attach from the top   C: all stations from new lines   M: only stations which overlap with current
			list( $LINES, $Y) = hfirst( $MAP); $LINES = ttl( $LINES, ' -- ');
			$E[ ( $Y - 1) . " -- " . ltt( $lines, ' -- ')] = $metric( $LINES, $lines);
			// try to attach from the bottom   C: all stations from new lines   M: only stations which overlap with current
			list( $LINES, $Y) = hlast( $MAP); $LINES = ttl( $LINES, ' -- ');
			$E[ ( $Y + 1) . " -- " . ltt( $lines, ' -- ')] = $metric( $LINES, $lines);
			arsort( $E, SORT_NUMERIC); while ( count( $E) > 25) hpop( $E);
		}
		list( $solution, $eval) = hfirst( $E);
		echoe( $e, "  solution#$solution  eval#$eval"); 
		$L = ttl( $solution, '--'); $Y = lshift( $L); $lines = $L;
		foreach ( $lines as $line) $UL[ "$line"] = true;
		$MAP[ ltt( $lines, ' -- ')] = $Y;
	}
	echo " OK\n"; return $MAP;
}


$FS = 16; $BS = 5; $counter = 0; $MAPsizes = array();
//$colors = ttl( '#07F,#F70,#F70,#70F,#F5A,#AF5,#7BF,#FB7,#7BF,#FF5,#AF5'); 
$colors = ttl( '#000,#111,#222,#333,#444,#000,#111,#222,#333,#444,#000,#111,#222,#333,#444');
shuffle( $colors);
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 1; $S->draw = '#000'; $S->fill = null; $S->alpha = 0.7;
$Sb = clone $S; $Sb->draw = '#f00'; $Sb->lw = 2.0; $Sb->alpha = 1.0;
$S2 = clone $S; $R = $S2; $R->style = 'F'; $R->lw = 0; $R->draw = null; $R->fill = '#000'; $R->alpha = 0.5;
$S3 = clone $S; $R = $S3; $R->lw = 0.5; $R->draw = '#000'; $R->alpha = 0.5;
$S4 = clone $S; $R = $S4; $R->lw = 0.2;
$S5 = clone $S; $R = $S5; $R->lw = 1.5; $R->draw = '#f00'; $R->fill = null; $R->alpha = 0.6;
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '2x2', 3, '0.05:0.05:0.05:0.05');
$DATA = compact( ttl( 'S2L,L2S')); // { S2L, L2S, cases: { metric: { L2Y, S2X, bad}, ...}}
function drawone( $C2, $MAP, $metric) {
	global $S2L, $L2S, $S, $Sb, $S2, $S3, $S4, $S5, $BS, $colors, $DATA;
	$YS = hv( $MAP); $XS = hk( hk( $S2L));
	$C2->train( array( mmin( $XS), mmax( $XS)), array( mmin( $YS), mmax( $YS)));
	$C2->autoticks( null, null, 10, 10);
	$C2->frame( null, null);
	$L2Y = array(); $S2X = hvak( hk( $S2L));
	foreach ( $MAP as $k => $Y) foreach ( ttl( $k, '--') as $line) $L2Y[ "$line"] = $Y;
	$colors2 = $colors; $grid = array(); 
	foreach ( $L2Y as $line => $Y) {
		$xs = array(); foreach ( $L2S[ $line] as $station => $v) lpush( $xs, $S2X[ $station]);
		$S->draw = lshift( $colors2); chartline( $C2, array( mmin( $xs), mmax( $xs)), array( $Y, $Y), $Y == 0 ? $Sb : $S);
		for ( $x = mmin( $xs); $x <= mmax( $xs); $x++) { htouch( $grid, "$Y"); htouch( $grid[ "$Y"], "$x", 0, false, false); $grid[ "$Y"][ "$x"]++; }
	}
	foreach ( $S2X as $station => $X) {
		$ys = array(); foreach ( $S2L[ $station] as $line => $v) lpush( $ys, $L2Y[ $line]);
		chartline( $C2, array( $X, $X), array( mmin( $ys), mmax( $ys)), $S3);
		foreach ( $ys as $Y) { chartscatter( $C2, array( $X), array( $Y), 'circle', $BS, $S2); htouch( $grid[ "$Y"], "$X", 0, false, false); $grid[ "$Y"][ "$X"]++; }
		for ( $y = mmin( $ys); $y <= mmax( $ys); $y++) { htouch( $grid[ "$y"], "$X", 0, false, false); $grid[ "$y"][ "$X"]++; } 
	}
	$bad = array(); // [ { x, y}, ...]
	foreach ( $grid as $y => $xs) foreach ( $xs as $x => $count) if ( $count == 2) {
		chartscatter( $C2, array( $x), array( $y), 'cross', $BS - 1, $S5);
		lpush( $bad, compact( ttl( 'x,y')));
	}
	$CL = new ChartLegend( $C2);
	$CL->add( null, $BS, 0.1, "$metric", $S);
	$CL->draw( true);
	htouch( $DATA, 'cases'); $DATA[ 'cases'][ $metric] = compact( ttl( 'L2Y,S2X,bad'));
}
foreach ( ttl( 'Overlap,Contribute,OverlapNotExtend,OverlapManyLines') as $metric) { $MAP = makemap( $metric); drawone( lshift( $CS), $MAP, $metric); lpush( $MAPsizes, count( $MAP));}
$file = sprintf( 'layout2map.%02d.%d.pdf', round( mavg( $MAPsizes)), round( tsystem())); 
$C->dump( $file);
$out = foutopen( 'layout2.bz64jsonl', 'a'); foutwrite( $out, $DATA); foutclose( $out);


?>