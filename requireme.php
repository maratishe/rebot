<?php
$STANDALONEMODE = true;



// json encoder
$JSONENCODER = 'jsonraw'; // jsonraw | jsonencode

// search and index
$KBLDIR = '/local/KBL';	// index itself 
$KBDIR = '/local/KB';		// content, files, etc
$KBCODEDIR = '/code/lucene';
$KBTASKDIR = '/code/tasks';
// perform aslock() for each file (for now, only JSON)
$ASLOCKON = false;	// locks files before all file operations
$IOSTATSON = false; // when true, will collect statistics about file file write/reads (with locks)
// collect IO stats globally (can be used by a logger) (only JSON implements it for now)
$IOSTATS = array();  // stats is in [ {type,time,[size]}, ...] 
// file locks
$ASLOCKS = array();	$ASLOCKSTATS = array(); $ASLOCKSTATSON = false; // filename => lock
$JQMODE = 'sourceone';	// debug|source|sourceone (debug is SCRIPT tag per file, sourceone is stuff put into one file)
$JQMAP = array( 'libs' => 'jquery.', 'basics' => '', 'advanced' => '');
$JQ = array(		// {{{ all JQ files (jquery.*.js)
	'libs' => array( 	// those that cannot be changed
		'1.9.1', 'fixes', 'base64', 'form', 'json.2.3', 'svg', 'timers'
	),
	'basics' => array( 'ioutils', 'iobase'),
	'advanced' => array(
		'iodraw',
		// ioatoms
		'ioatoms',
		'ioatoms.input', 
		'ioatoms.output'
	)
); // }}}
$env = makenv(); // CDIR,BIP,SBDIR,ABDIR,BDIR,BURL,ANAME,DBNAME,ASESSION,RIP,RPORT,RAGENT
//var_dump( $env);
foreach ( $env as $k => $v) $$k = $v;
$DB = null; $DBNAME = $ANAME;	// db same as ANAME
$MAUTHDIR = '/code/mauth';
$MFETCHDIR = '/code/mfetch';
// library loader
if ( ! isset( $LIBCASES)) $LIBCASES = array( 'commandline', 'csv', 'filelist', 'hashlist', 'hcsv', 'json', 
	'json', 'math', 'string', 'time', 'db', 'proc', 'async', 'plot', 
	'ngraph', 'objects', 'chart', 'r', 'mauth', 'matrixfile', 'matrixmath',
	'binary', 'curl', 'mfetch', 'network', 'network2', 'remote', 'lucene', 
	'pdf', 'crypt', 'file', 'dll', 'hashing', 'queue',
	'optimization', 'websocket', 'stringex', 'classifiers', 'rebot'
);
if ( ! isset( $STANDALONEMODE) || ! $STANDALONEMODE) foreach ( $LIBCASES as $lib) require_once( "$ABDIR/lib/$lib.php");





// commandline
$CLHELP = array();
// JSON
$JO = array();
// string
// valid char ranges: { from: to (UTF32 ints), ...} -- valid if terms of containing meaning (symbools and junks are discarded)
$UTF32GOODCHARS = tth( "65345=65370,65296=65305,64256=64260,19968=40847,12354=12585,11922=12183,1072=1105,235=235,48=57,97=122,44=46"); // UTF-32 INTS!
$UTF32TRACK = array(); 	// to track decisions for specific chars
// R
$RHOME = '';
// plot, chart
$ANOTHERPDF = true;
$PLOTDONOTSCALE = false;
define( 'FPDF_FONTPATH',  "$ABDIR/lib/fpdf/font/");
// pdf
$XPDF = '/usr/local/xpdf/bin';
// lucene setup
iconv_set_encoding( "input_encoding", "UTF-8");
iconv_set_encoding( "internal_encoding", "UTF-8");
iconv_set_encoding( "output_encoding", "UTF-8");
mb_internal_encoding( "UTF-8");
if ( is_dir( "/usr/local/zend")) {	// if path does not exist, do not make fuss, just let it me -- lucene is probably not used in these cases
	set_include_path( '/usr/local/zend/library');
	require_once( 'Zend/Search/Lucene.php');
	Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
	// analyzers
	//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());
	//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8());
	//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num());
	require_once( "$ABDIR/lib/Utf8MbcsUnigram.php");
	Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Twk_Search_Lucene_Analysis_Analyzer_Common_Utf8MbcsUnigram());
}
else if ( is_dir( 'zend')) {
	set_include_path( 'zend');
	require_once( 'Zend/Search/Lucene.php');
	Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
}
require_once 'Zend/Search/Lucene/Analysis/Analyzer.php';







function makenv() {	// in web mode, htdocs should be in /web
	global $_SERVER, $prefix, $_SESSION;
	$cdir = getcwd(); @chdir( $prefix); $prefix = getcwd(); chdir( $cdir);
	//$s = explode( '/', $prefix); array_pop( $s); $prefix = implode( '/', $s); // remove / at the end of prefix
	$out = array();
	$addr = '';
	if ( isset( $_SERVER[ 'SERVER_NAME'])) $addr = $_SERVER[ 'SERVER_NAME'];
	if ( isset( $_SERVER[ 'DOCUMENT_ROOT'])) $root = $_SERVER[ 'DOCUMENT_ROOT'];
	if ( ! $addr && is_file( '/sbin/ifconfig')) { 	// probably command line, try to get own IP address from ipconfig
		$in = popen( '/sbin/ifconfig', 'r');
		$L = array(); while ( $in && ! feof( $in)) {
			$line = trim( fgets( $in)); if ( ! $line) continue;
			if ( strpos( $line, 'inet addr') !== 0) continue;
			$L2 = explode( 'inet addr:', $line);
			$L3 = array_pop( $L2);
			$L4 = explode( ' ', $L3);
			$L5 = trim( array_shift( $L4));
			array_push( $L, $L5);
		}
		pclose( $in); $addr = implode( ',', $L);
	}
	if ( ! isset( $root) || ! $root) $root = '/web';
	// find $root depending on web space versus CLI environment
	$split = explode( "$root/", $cdir); $aname = '';
	if ( count( $split) == 2) $aname = @array_shift( explode( '/', $split[ 1]));
	else $aname = '';
	//else { $aname = ''; $root = $prefix ? $prefix : $cdir; } // CLI
	// application session
	$session = array();
	if ( $aname && isset( $_SESSION) && isset( $_SESSION[ $aname])) { // check session, detect ssid changes
		$session = $_SESSION[ $aname];
		$ssid = session_id();
		if ( ! isset( $session[ 'ssid'])) $session[ 'ssid'] = $ssid;
		if ( $session[ 'ssid'] != $ssid) { $session[ 'oldssid'] = $session[ 'ssid']; $session[ 'ssid'] = $ssid; }
	}
	// return result
	$L2 = explode( ',', $addr);
	$out = array(
		'SYSTYPE' => ( isset( $_SERVER) && isset( $_SERVER[ 'SYSTEMDRIVE'])) ? 'cygwin' : 'linux',
		'CDIR' => $cdir,
		'BIP' => $addr ? array_shift( $L2) : '',
		'BIPS' => $addr ? explode( ',', $addr) : array(),
		'SBDIR' => $root,	// server base dir, htdocs for web, ajaxkit root for CLI
		'ABDIR' => $prefix,	// ajaxkit base directory
		'BDIR' => "$root" . ( $aname ? '/' . $aname : ''), // base app dir
		'BURL' => ( $addr ? 'http://' . $addr . ( $aname ? "/$aname" : '') : ''),
		'ABURL' => '', 	// add later
		'ANAME' => $aname ? $aname: 'root',
		'SNAME' => ( isset( $_SERVER) && isset( $_SERVER[ 'SCRIPT_NAME'])) ? $_SERVER[ 'SCRIPT_NAME'] : '?', 
		'DBNAME' => $aname,
		// application session
		'ASESSION' => $session,
		// client (browser) specific
		'RIP' => isset( $_SERVER[ 'REMOTE_ADDR']) ? $_SERVER[ 'REMOTE_ADDR'] : '',
		'RPORT' => isset( $_SERVER[ 'REMOTE_PORT']) ? $_SERVER[ 'REMOTE_PORT'] : '',
		'RAGENT' => isset( $_SERVER[ 'HTTP_USER_AGENT']) ? $_SERVER[ 'HTTP_USER_AGENT'] : ''
	);
	$out[ 'ABURL'] = ( $addr ? "http://$addr" . str_replace( "$root", '', $out[ 'ABDIR']) : '');
	return $out;
}
function jqload( $justdumpjs = false, $mode = 'full', $nocanvas = true, $nocallback = true) {
	global $BURL, $ABURL, $ABDIR, $JQ, $JQMODE;
	$files = array(); 
	foreach ( $JQ[ 'libs'] as $file) lpush( $files, "jquery.$file" . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $mode == 'full' || $mode == 'short') foreach ( $JQ[ 'basics'] as $file) lpush( $files, $file . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $mode == 'full') foreach ( $JQ[ 'advanced'] as $file) lpush( $files, $file . ( strpos( $JQMODE, 'source') !== false ? '.min.js' : '.js'));
	if ( $JQMODE == 'debug') {	// separate script tag per file
		foreach ( $files as $file) echo $justdumpjs ? implode( '', file( "$ABDIR/jq/$file")) . "\n" : '<script src="' . $ABURL . "/jq/$file" . '?' . mr( 5) . '"></script>' . "\n";
	}
	if ( $JQMODE == 'source') {	// script type per file with source instead of url pointer
		foreach ( $files as $file) echo ( $justdumpjs ? '' :  "<script>\n") . implode( '', file( "$ABDIR/jq/$file")) . "\n" . ( $justdumpjs ? '' : "</script>\n");
	}
	if ( $JQMODE == 'sourceone') {	// all source inside one tag (no tag if $justdumpjs is true
		if ( ! $justdumpjs) echo "<script>\n\n";
		foreach ( $files as $file) echo implode( '', file( "$ABDIR/jq/$file")) . "\n\n";
		if ( ! $nocallback) echo "if ( callback) eval( callback)();\n";
		if ( ! $justdumpjs) echo "</script>\n";
	}
	// to fix canvas in IE
	if ( ! $justdumpjs && ! $nocanvas) echo '<!--[if IE]><script type="text/javascript" src="' . $ABURL . '/jq/jquery.excanvas.js"></script><![endif]-->' . "\n";
	else if ( ! $nocanvas) echo implode( '', file( "$ABDIR/jq/jquery.excanvas.js")) . "\n\n";
}
function jqparse( $path, $all = false) {	// minimizes JS and echoes the rest
	$in = fopen( $path, 'r');
	$put = false;
	if ( $all) $put = $all;
	while ( ! feof( $in)) {
		$line = trim( fgets( $in));
		if ( ! $put && strpos( $line, '(function($') !== false) { $put = true; continue; }
		if ( ! $all && strpos( $line, 'jQuery)') !== false) break;	// end of file
		if ( ! strlen( $line) || strpos( $line, '//') === 0) continue;
		if ( strpos( $line, '/*') === 0) {	// multiline comment */
			$limit = 100000;
			while ( $limit--) { 
				// /*
				if ( strpos( $line,  '*/') !== FALSE) break;
				$line = trim( fgets( $in));
			}
			continue;
		}
		if ( $put) echo $line . "\n";
	}
	fclose( $in);
}
function flog( $msg, $echo = true, $timestamp = false, $uselock = false, $path = '') {	// writes the message to file log, no end of line
	global $BDIR, $FLOG;
	if ( is_array( $msg)) $msg = htt( $msg);
	if ( ! $FLOG) $FLOG = $path;
	if ( ! $FLOG) $FLOG = "$BDIR/log.txt"; 
	$out = fopen( $FLOG, 'a');
	if ( $timestamp) fwrite( $out, "time=" . tsystemstamp() . ',');
	fwrite( $out, "$msg\n");
	fclose( $out);
	if ( $echo) echo "$msg\n";
}
function checksession( $usedb = false) { // db calls dbsession()
	global $ASESSION, $DB;
	if ( ! isset( $ASESSION[ 'oldssid'])) return;	// nothing wrong
	$oldssid = $ASESSION[ 'oldssid'];
	$ssid = $ASESSION[ 'ssid'];
	if ( $usedb) dbsession( 'reset', "newssid=$ssid", $oldssid);
	unset( $ASESSION[ 'oldssid']);
}
// will save in BURL/log.base64( uid)    as base64( bzip2( json))  -- no clear from extension, but should remember the format
// $msg can be either string ( will tth())  or hash
// will add     (1) time   (2) uid   (3) took (current time - REQTIME)   (4) reply=JO (if not empty/NULL)
function mylog( $msg, $ouid = null, $noreply = false, $ofile = null) {
	global $uid, $BDIR, $JO, $REQTIME, $_SERVER, $ASLOCKSTATS;
	if ( $ouid === null) $ouid = $uid; 
	if ( $ouid === null) $ouid = 'nobody';
	$h = array();
	$h[ 'time'] = tsystemstamp();
	$h[ 'uid'] = $ouid;
	$h[ 'took'] = tsystem() - $REQTIME;
	$h[ 'script'] = lpop( ttl( $_SERVER[ 'SCRIPT_FILENAME'], '/'));
	$h = hm( $h, is_string( $msg) ? tth( $msg) : $msg);	// merge, but keep time and uid in proper order
	if ( $JO && ! $noreply) $h[ 'reply'] = $JO;
	if ( $ASLOCKSTATS) $h[ 'aslockstats'] = $ASLOCKSTATS;
	$file = sprintf( "%s/log.%s", $BDIR, base64_encode( $ouid)); if ( $ofile) $file = $ofile;
	$out = fopen( $file, 'a'); fwrite( $out, h2json( $h, true, null, null, true) . "\n"); fclose( $out);
}





$JSONENCODER = 'jsonencode'; // jsonraw | jsonencode
$KBLDIR = '/local/kb.140107.lucene';	// index itself 
$KBDIR = '/local/KB';		// content, files, etc
$KBCODEDIR = '/code/lucene';
$KBTASKDIR = '/software/rebot.standalone/tasks';
$STRINGEXDIR = '/local/kb.140107.stringex';

function clinit() {

global $prefix, $BDIR, $CDIR;
// additional (local) functions and env (if present)
if ( is_file( "$BDIR/functions.php")) require_once( "$BDIR/functions.php");
if ( is_file( "$BDIR/env.php")) require_once( "$BDIR/env.php");
// yet additional env and functions in current directory -- only when CDIR != BDIR
if ( $CDIR && $BDIR != $CDIR && is_file( "$CDIR/functions.php")) require_once( "$CDIR/functions.php");
if ( $CDIR && $BDIR != $CDIR && is_file( "$CDIR/env.php")) require_once( "$CDIR/env.php");
}
function clrun( $command, $silent = true, $background = true, $debug = false) {

if ( $debug) echo "RUN [$command]\n";
if ( $silent) system( "$command > /dev/null 2>1" . ( $background ? ' &' : ''));
else system( $command);
}
function clget( $one, $two = '', $three = '', $four = '', $five = '', $six = '', $seven = '', $eight = '', $nine = '', $ten = '', $eleven = '', $twelve = '') {

global $argc, $argv, $GLOBALS;
// keys
if ( count( ttl( $one)) > 1) $ks = ttl( $one);
else $ks = array( $one, $two, $three, $four, $five, $six, $seven, $eight, $nine, $ten, $eleven, $twelve);
while ( count( $ks) && ! llast( $ks)) lpop( $ks);
// values
$vs = $argv; $progname = lshift( $vs);
if ( count( $vs) == 1) {	// only one argument, maybe hash
$h = tth( $vs[ 0]); $ok = true; if ( ! count( $h)) $ok = false;
foreach ( $h as $k => $v) if ( ! $k || ! strlen( "$k") || ! $v || ! strlen( "$v")) $ok = false;
if ( $ok && ltt( hk( $h)) == ltt( $ks)) $vs = hv( $h);	// keys are decleared by themselves, just create values
}
if ( count( $vs) && ( $vs[ 0] == '-h' || $vs[ 0] == '--help' || $vs[ 0] == 'help')) { clshowhelp(); die( ''); }
if ( count( $vs) != count( $ks)) {
echo "\n";
echo "ERROR! clget() wrong command line, see keys/values and help below...\n";
echo "(expected) keys: " . ltt( $ks, ' ') . "\n";
echo "(found) values: " . ltt( $vs, ' ') . "\n";
echo "---\n";
clshowhelp();
die( '');
}
// merge keys with values
$h = array(); for ( $i = 0; $i < count( $ks); $i++) $h[ '' . $ks[ $i]] = trim( $vs[ $i]);
$ks = hk( $h); for ( $i = 1; $i < count( $ks); $i++) if ( $h[ $ks[ $i]] == 'ditto') $h[ $ks[ $i]] = $h[ $ks[ $i - 1]];
foreach ( $h as $k => $v) echo "  $k=[$v]\n";
foreach ( $h as $k => $v) $GLOBALS[ $k] = $v;
return $h;
}
function clgetq( $one, $two = '', $three = '', $four = '', $five = '', $six = '', $seven = '', $eight = '', $nine = '', $ten = '', $eleven = '', $twelve = '') {

global $argc, $argv, $GLOBALS;
// keys
if ( count( ttl( $one)) > 1) $ks = ttl( $one);
else $ks = array( $one, $two, $three, $four, $five, $six, $seven, $eight, $nine, $ten, $eleven, $twelve);
while ( count( $ks) && ! llast( $ks)) lpop( $ks);
// values
$vs = $argv; $progname = lshift( $vs);
if ( count( $vs) == 1) {	// only one argument, maybe hash
$h = tth( $vs[ 0]); $ok = true; if ( ! count( $h)) $ok = false;
foreach ( $h as $k => $v) if ( ! $k || ! strlen( "$k") || ! $v || ! strlen( "$v")) $ok = false;
if ( $ok && ltt( hk( $h)) == ltt( $ks)) $vs = hv( $h);	// keys are decleared by themselves, just create values
}
if ( count( $vs) && ( $vs[ 0] == '-h' || $vs[ 0] == '--help' || $vs[ 0] == 'help')) { clshowhelp(); die( ''); }
if ( count( $vs) != count( $ks)) {
echo "\n";
echo "ERROR! clget() wrong command line, see keys/values and help below...\n";
echo "(expected) keys: " . ltt( $ks, ' ') . "\n";
echo "(found) values: " . ltt( $vs, ' ') . "\n";
echo "---\n";
clshowhelp();
die( '');
}
// merge keys with values
$h = array(); for ( $i = 0; $i < count( $ks); $i++) $h[ '' . $ks[ $i]] = trim( $vs[ $i]);
$ks = hk( $h); for ( $i = 1; $i < count( $ks); $i++) if ( $h[ $ks[ $i]] == 'ditto') $h[ $ks[ $i]] = $h[ $ks[ $i - 1]];
foreach ( $h as $k => $v) //echo "  $k=[$v]\n";
foreach ( $h as $k => $v) $GLOBALS[ $k] = $v;
return $h;
}
function clhelp( $msg) { global $CLHELP; lpush( $CLHELP, $msg); }

function clshowhelp() { // show contents of CLHELP 

global $CLHELP;
foreach ( $CLHELP as $msg) {
if ( substr( $msg, strlen( $msg) - 1, 1) != "\n") $msg .= "\n"; 	// no end line in this msg, add one
echo $msg;
}
}
function csvload( $file, $delimiter= ',') { // returns array of arrays

$in = fopen( $file, 'r');
$out = array();
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in));
if ( ! $line) continue;
$split = explode( $delimiter, $line);
if ( count( $out) && count( $split) != count( $out[ count( $out) - 1])) continue;	// lines are not the same
array_push( $out, $split);
}
fclose( $in);
return $out;
}
function csvone( $csv, $number) {	// returns the array of the column by number

$out = array();
foreach ( $csv as $line) {
if ( count( $line) <= $number) continue;
array_push( $out, $line[ $number]);
}
return $out;
}
function &csvminit( $spacer = true) { return array( 'depth' => 0, 'lines' => array(), 'spacer' => $spacer); }

function csvmadd( &$csvm, $blockname, $data) {

$lines =& $csvm[ 'lines'];
$count = count( array_keys( $data));
$lines[ 0] .= $blockname; for ( $i = 0; $i < $count; $i++) $lines[ 0] .= ',';
foreach ( $data as $name => $values) {
$lines[ 1] .= $name . ',';
$size = mmax( array( count( $lines) - 2, count( $values)));
if ( $size <= 0) for ( $y = 2; $y < count( $lines); $y++) $lines[ $y] .= ',';
for ( $y = 0; $y < $size; $y++) {
if ( ! isset( $lines[ $y + 2])) { $lines[ $y + 2] = ''; for ( $z = 0; $z < $csvm[ 'depth']; $z++) $lines[ $y + 2] .= ','; }
if ( isset( $values[ $y])) $lines[ $y + 2] .= $values[ $y] . ',';
else $lines[ $y + 2] .= ',';
}
}
// add another comma (spacer) on all lines
if ( $csvm[ 'spacer']) for ( $i = 0; $i < count( $lines); $i++) $lines[ $i] .= ',';
$csvm[ 'depth'] += ( $count + ( $csvm[ 'spacer'] ? 1 : 0));
}
function csvmprint( &$csvm, $printheaders = true) {

for ( $i = ( $printheaders ? 0 : 2); $i < count( $csvm[ 'lines']); $i++) echo $csvm[ 'lines'][ $i] . "\n";
}
function csvmsave( &$csvm, $path, $printheaders = true, $flag = 'w') {	// save multi-column CSV to file

$out = fopen( $path, $flag);
for ( $i = ( $printheaders ? 0 : 2); $i < count( $csvm[ 'lines']); $i++) fwrite( $out, $csvm[ 'lines'][ $i] . "\n");
fclose( $out);
}
function flmeta( $dir, $extspick = '', $extsignore = '', $recursive = true) {

$h = compact( ttl( 'dir,extspick,extsignore,recursive'));
$h[ 'files'] = array();
foreach ( flgetall( $dir, $extspick, $extsignore, $recursive) as $path => $file) {
$h[ 'files'][ $path] = fstats( $path);
$h[ 'files'][ $path][ 'size'] = filesize( $path);
}
return $h;
}
function flmetaupdate( $meta) {	// returns the updated meta

extract( $meta); // dir, extspick, extsignore, recursive, files
return flmeta( $dir, $extspick, $extsignore, $recursive);
}
function flmetachanges( $meta, $meta2 = null) { // { filepath: 'changed | removed | created', ... }

if ( ! $meta2) $meta2 = flmetaupdate( $meta);
$h = array();
foreach ( $meta2[ 'files'] as $path => $stats) $h[ $path] = '';
foreach ( $meta[ 'files'] as $path => $stats1) {
if ( ! isset( $meta2[ 'files'][ "$path"])) { $h[ "$path"] = 'delete'; continue; }
$stats2 = $meta2[ 'files'][ $path];
$ok = true; foreach ( ttl( 'size,mtime') as $k) if ( $stats1[ $k] != $stats2[ $k]) $ok = false;
if ( ! $ok) $h[ $path] .= ',write';
$ok = true; foreach ( ttl( 'atime') as $k) if ( $stats1[ $k] != $stats2[ $k]) $ok = false;
if ( ! $ok) $h[ $path] .= ',read';
}
foreach ( $meta2[ 'files'] as $path => $stats) if ( ! isset( $meta[ 'files']) || ! isset( $meta[ 'files'][ $path])) $h[ $path] .= ',create';
foreach ( $h as $path => $v) $h[ $path] = ltt( ttl( $v));
return $h;
}
class FilesystemWatch { 

public $meta;
public $reports = array();
public function __construct( $wdir) { $this->meta = flmeta( $wdir); }
public function report() { // returns { bytesin(kb), filesin, filesout}
$meta = $this->meta;
$meta2 = flmetaupdate( $meta);
$changes = flmetachanges( $meta, $meta2);
$bytesin = 0; $bytesout = 0; $filesin = 0; $filesout = 0;
foreach ( $changes as $path => $type) {
if ( $type == 'create') $filesin++;
if ( $type == 'create') $bytesin += $meta2[ 'files'][ $path][ 'size'];
if ( $type == 'remove') $filesout++;
if ( $type == 'write') {
if ( $meta2[ 'files'][ $path][ 'size'] >= $meta[ 'files'][ $path][ 'size']) $bytesin += $meta2[ 'files'][ $path][ 'size'] - $meta[ 'files'][ $path][ 'size'];
else $bytesin += $meta2[ 'files'][ $path][ 'size']; 	// re-write the file
}
if ( $type == 'read') $bytesout += $meta2[ 'files'][ $path][ 'size'];
}
$bytesin = round( $bytesin);
$this->meta = $meta2;
$size = $this->size();
$report = compact( ttl( 'bytesin,bytesout,filesin,filesout,size'));
lpush( $this->reports, htt( $report));
return $report;
}
public function history() { return $this->reports; } // return the entire history of reports
public function count() { return count( $this->meta[ 'files']); }
public function size() { return round( msum( hltl( hv( $this->meta[ 'files']), 'size'))); }
public function clear() { $this->reports = array(); }
}
function flgetall( $dir, $extspick = '', $extsignore = '', $recursive = true) { // picks and ignores are dot-delimited

$CDIR = getcwd();
if ( $extspick) $extspick = ttl( $extspick, '.'); else $extspick = array();
if ( $extsignore) $extsignore = ttl( $extsignore, '.'); else $extsignore = array();
$dirs = array( $dir);
$h = array();
$limit = 10000; while ( count( $dirs)) {
$dir = lshift( $dirs);
$FL = flget( $dir);
foreach ( $FL as $file) {
if ( is_dir( "$dir/$file") && $recursive) { lpush( $dirs, "$dir/$file"); continue; }
$ext = lpop( ttl( $file, '.'));
if ( $extspick && lisin( $extspick, $ext)) { $h[ "$dir/$file"] = $file; continue; }
if ( $extsignore && lisin( $extsignore, $ext)) continue;	// ignore, wrong extension
if ( ! is_file( "$dir/$file")) continue;
$h[ "$dir/$file"] = $file;
}
}
chdir( $CDIR);
return $h;
}
function flget( $dir, $prefix = '', $string = '', $ending = '', $length = -1, $skipfiles = false, $skipdirs = false) {

$in = popen( "ls -a " . strdblquote( $dir), 'r');
$list = array();
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in));
if ( ! $line) continue;
if ( $line === '.' || $line === '..') continue;
if ( is_dir( "$dir/$line") && $skipdirs) continue;
if ( is_file( "$dir/$line") && $skipfiles) continue;
if ( $prefix && strpos( $line, $prefix) !== 0) continue;
if ( $string && ! strpos( $line, $string)) continue;	// string not found anywhere
if ( $ending && strrpos( $line, $ending) !== strlen( $line) - strlen( $ending)) continue;
if ( $length > 0 && strlen( $line) != $length) continue;
array_push( $list, $line);
}
pclose( $in);
return $list;
}
function flparse( $list, $pdef, $numeric = true, $delimiter2 = null) { // returns multiarray containing filenames

$plist = array();
$split = explode( '.', $pdef);
for ( $i = 0; $i < count( $split); $i++) {
if ( strpos( $split[ $i], '*') === false) continue;	// not to be parsed
$pos = $i;
if ( strlen( str_replace( '*', '', $split[ $i]))) $pos = ( int)str_replace( '*', '', $split[ $i]);
$plist[ $pos] = $i;
}
ksort( $plist, SORT_NUMERIC);
$plist = array_values( $plist);
$pcount = count( $split);
$mlist = array();
foreach ( $list as $file) {
$fname = $file;
if ( $delimiter2) $fname = str_replace( $delimiter2, '.', $fname);
$split = explode( '.', $fname);
if ( count( $split) !== $pcount) continue; 	// rogue file
unset( $ml);
$ml =& $mlist;
for ( $i = 0; $i < count( $plist) - 1; $i++) {
$part = $split[ $plist[ $i]];
if ( $numeric) $part = ( int)$part;
if ( ! isset( $ml[ $part])) $ml[ $part] = array();
unset( $nml);
$nml =& $ml[ $part];
unset( $ml);
$ml =& $nml;
}
$part = $split[ $plist[ count( $plist) - 1]];
if ( $numeric) $part = ( int)$part;
if ( isset( $ml[ $part]) && is_array( $ml[ $part])) array_push( $ml[ $part], $file);
else if ( isset( $ml[ $part])) $ml[ $part] = array( $ml[ $part], $file);
else $ml[ $part] = $file;
}
return $mlist;
}
function fldebug( $fl) {

echo "DEBUG FILE LIST\n";
foreach ( $fl as $k1 => $v1) {
echo "$k1   $v1\n";
if ( is_array( $v1)) foreach ( $v1 as $k2 => $v2) {
echo "   $k2   $v2\n";
if ( is_array( $v2)) foreach ( $v2 as $k3 => $v3) {
echo "      $k3   $v3\n";
if ( is_array( $v3)) foreach ( $v3 as $k4 => $v4) {
echo "         $k4   $v4\n";
}
}
}
}
echo "\n\n";
}
function h2kvt( $h, $bdel = "\n", $sdel = ' --- ', $s64ks = false, $s64vs = false) {

$L = array();
foreach ( $h as $k => $v) lpush( $L, ( $s64ks ? s2s64( $k) : $k) . $sdel . ( $s64vs ? s2s64( $v) : $v));
return implode( $bdel, $L);
}
function kvt2h( $s, $bdel = "\n", $sdel = ' --- ', $s64ks = false, $s64vs = false) {

$h = array();
foreach ( explode( $bdel, $s) as $s2) {
$L = explode( $sdel, $s2); if ( count( $L) != 2) continue;
extract( lth( $L, ttl( 'k,v')));
if ( $s64ks) $k = s642s( $k);
if ( $s64vs) $v = s642s( $v);
$h[ '' . trim( $k)] = trim( $v);
}
return $h;
}
function hdebug( &$h, $level) {  // converts hash into text with indentation levels

if ( ! count( $h)) return;
$key = lshift( hk( $h));
$v =& $h[ $key];
for ( $i = 0; $i < $level * 5; $i++) echo ' ';
echo $key;
if ( is_array( $v)) { echo "\n"; hdebug( $h[ $key], $level + 1); }
else echo "   $v\n";
unset( $h[ $key]);
hdebug( $h, $level);	// keep doing it until run out of keys
}
function hm( $one, $two, $three = NULL, $four = NULL) {

if ( ! $one && ! $two) return array();
$out = $one; if ( ! $out) $out = array();
if ( is_array( $two)) foreach ( $two as $key => $value) $out[ $key] = $value;
if ( ! $three) return $out;
foreach ( $three as $key => $value) $out[ $key] = $value;
if ( ! $four) return $out;
foreach ( $four as $key => $value) $out[ $key] = $value;
return $out;
}
function htouch( &$h, $key, $v = array(), $replaceifsmaller = true, $replaceiflarger = true, $tree = false) { // key can be array, will go deep that many levels

if ( is_string( $key) && count( ttl( $key)) > 1 && $tree) $key = ttl( $key);
if ( ! is_array( $key)) $key = array( $key); $changed = false;
foreach ( $key as $k) {
if ( ! isset( $h[ $k])) { $h[ $k] = $v; $changed = true; }
if ( is_numeric( $v) && is_numeric( $h[ $k]) && $replaceifsmaller && $v < $h[ $k]) { $h[ $k] = $v; $changed = true; }
if ( is_numeric( $v) && is_numeric( $h[ $k]) && $replaceiflarger && $v > $h[ $k]) { $h[ $k] = $v; $changed = true; }
if ( $tree) $h =& $h[ $k];	// will go deeper only if 'tree' type is set to true
}
return $changed;
}
function hinc( &$h, $key, $increment = 1) { htouch( $h, "$key", 0, false, false); $h[ "$key"] += $increment; } // increment value for this key, depends on htouch

function hltl( $hl, $key) {	// hash list to list

$l = array();
foreach ( $hl as $h) if ( isset( $h[ $key])) array_push( $l, $h[ $key]);
return $l;
}
function hlf( &$hl, $key = null, $value = null, $remove = false) {	// filters only lines with [ key [=value]]

$lines = array(); $hl2 = array();
foreach ( $hl as $h) {
if ( $key !== null && ! isset( $h[ $key])) continue;
if ( ( $key !== null && $value !== null) && ( ! isset( $h[ $key]) || $h[ $key] != $value)) { lpush( $hl2, $h); continue; }
array_push( $lines, $h);
}
if ( $remove) $hl = $hl2;	// replace the original hashlist
return $lines;
}
function hlm( $hl, $purge = '') {	// merging hash list, $purge can be an array

if ( $purge && ! is_array( $purge)) $purge = explode( ':', $purge);
$ph = array(); if ( $purge) foreach ( $purge as $key) $ph[ $key] = true;
$out = array();
foreach ( $hl as $h) {
foreach ( $h as $key => $value) {
if ( isset( $ph[ $key])) continue;
$out[ $key] = $value;
}
}
return $out;
}
function hlth( $hl, $kkey, $vkey) { // pass keys for key and value on each line

$h = array();
foreach ( $hl as $H) $h[ $H[ $kkey]] = $H[ $vkey];
return $h;
}
function holthl( $h) {

$out = array();
$keys = array_keys( $h);
for ( $i = 0; $i < count( $h[ $keys[ 0]]); $i++) {
$item = array();
foreach ( $keys as $key) $item[ $key] = $h[ $key][ $i];
array_push( $out, $item);
}
return $out;
}
function hltag( &$h, $key, $value) {	// does not return anything

for ( $i = 0; $i < count( $h); $i++) $h[ $i][ $key] = $value;
}
function hlsort( &$hl, $key, $how = SORT_NUMERIC, $bigtosmall = false) {

$h2 = array(); foreach ( $hl as $h) { htouch( $h2, '' . $h[ $key]); lpush( $h2[ '' . $h[ $key]], $h); }
if ( $bigtosmall) krsort( $h2, $how);
else ksort( $h2, $how);
$L = hv( $h2); $hl = array();
foreach ( $L as $L2) { foreach ( $L2 as $h) lpush( $hl, $h); }
return $hl;
}
function hvak( $h, $overwrite = true, $value = NULL, $numeric = false) {

$out = array();
foreach ( $h as $k => $v) {
if ( ! $overwrite && isset( $out[ "$v"])) continue;
$value2 = ( $value === NULL) ? $k : $value;
$out[ "$v"] = $numeric ? ( ( int)$value2) : $value2;
}
return $out;
}
function htv( $h, $key) { return $h[ $key]; }

function htg( $h, $keys = '', $prefix = '', $trim = true) { 

if ( ! $keys) $keys = array_keys( $h);
if ( is_string( $keys)) $keys = ttl( $keys, '.');
foreach ( $keys as $k) $GLOBALS[ $prefix . $k] = $trim ? trim( $h[ $k]) : $h[ $k];
}
function hcg( $h) { foreach ( $h as $k => $v) { if ( is_numeric( $k)) unset( $GLOBALS[ $v]); else unset( $GLOBALS[ $k]); }} 

function hk( $h) { return array_keys( $h); }

function hv( $h) { return array_values( $h); }

function hpop( &$h) { if ( ! count( $h)) return array( null, null); end( $h); $k = key( $h); $v = $h[ $k]; unset( $h[ $k]); return array( $k, $v); }

function hshift( &$h) { if ( ! count( $h)) return array( null, null); reset( $h); $k = key( $h); $v = $h[ $k]; unset( $h[ $k]); return array( $k, $v); }

function hfirst( &$h) { if ( ! count( $h)) return array( null, null); reset( $h); $k = key( $h); return array( $k, $h[ $k]); }

function hlast( &$h) { if ( ! count( $h)) return array( null, null); end( $h); $k = key( $h); return array( $k, $h[ $k]); }

function hpopv( &$h) { if ( ! count( $h)) return null; $v = end( $h); $k = key( $h); unset( $h[ $k]); return $v; }

function hshiftv( &$h) { if ( ! count( $h)) return null; $v = reset( $h); $k = key( $h); unset( $h[ $k]); return $v; }

function hfirstv( &$h) { if ( ! count( $h)) return null; return reset( $h); }

function hlastv( &$h) { if ( ! count( $h)) return null; return end( $h); }

function hpopk( &$h) { if ( ! count( $h)) return null; end( $h); $k = key( $h); unset( $h[ $k]); return $k; }

function hshiftk( &$h) { if ( ! count( $h)) return null; reset( $h); $k = key( $h); unset( $h[ $k]); return $k; }

function hfirstk( &$h) { if ( ! count( $h)) return null; reset( $h); return key( $h); }

function hlastk( &$h) { if ( ! count( $h)) return null; end( $h); return key( $h); }

function hth64( $h, $keys = null) {	// keys can be array or string

if ( $keys === null) $keys = array_keys( $h);
if ( $keys && ! is_array( $keys)) $keys = explode( '.', $keys);
$keys = hvak( $keys, true, true);
$H = array(); foreach ( $h as $k => $v) $H[ $k] = isset( $keys[ $k]) ? base64_encode( $v) : $v;
return $H;
}
function h64th( $h, $keys = null) {	// keys can be array or string

if ( $keys === null) $keys = array_keys( $h);
if ( $keys && ! is_array( $keys)) $keys = explode( '.', $keys);
$keys = hvak( $keys, true, true);
$H = array(); foreach ( $h as $k => $v) $H[ $k] = isset( $keys[ $k]) ? base64_decode( $v) : $v;
return $H;
}
function tth( $t, $bd = ',', $sd = '=', $base64 = false, $base64keys = null) {	// text to hash

if ( ! $base64keys) $base64keys = array();
if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
if ( $base64) $t = base64_decode( $t);
$h = array();
$parts = explode( $bd, trim( $t));
foreach ( $parts as $part) {
$split = explode( $sd, $part);
if ( count( $split) === 1) continue;	// skip this one
$h[ trim( array_shift( $split))] = trim( implode( $sd, $split));
}
foreach ( $base64keys as $k) if ( isset( $h[ $k])) $h[ $k] = base64_decode( $h[ $k]);
return $h;
}
function tthl( $text, $ld = '...', $bd = ',', $sd = '=') {

$lines = explode( '...', base64_decode( $props[ 'search.config']));
$hl = array();
foreach ( $lines as $line) {
$line = trim( $line);
if ( ! $line || strpos( $line, '#') === 0) continue;
array_push( $hl, tth( $line, $bd, $sd));
}
return $hl;
}
function htt( $h, $sd = '=', $bd = ',', $base64 = false, $base64keys = null) { // hash to text

// first, process base64
if ( ! $base64keys) $base64keys = array();
if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
foreach ( $base64keys as $k) if ( isset( $h[ $k])) $h[ $k] = base64_encode( $h[ $k]);
$parts = array();
foreach ( $h as $key => $value) array_push( $parts, $key . $sd . $value);
if ( ! $parts) return '';
if ( $base64) return base64_encode( implode( $bd, $parts));
return implode( $bd, $parts);
}
function ttl( $t, $d = ',', $cleanup = "\n:\t", $skipempty = true, $base64 = false, $donotrim = false) { // text to list

if ( ! $cleanup) $cleanup = '';
if ( $base64) $t = base64_decode( $t);
$l = explode( ':', $cleanup);
foreach ( $l as $i) if ( $i != $d) $t = str_replace( $i, ' ', $t);
$l = array();
$parts = explode( $d, $t);
foreach ( $parts as $p) {
if ( ! $donotrim) $p = trim( $p);
if ( ! strlen( $p) && $skipempty) continue;	// empty
array_push( $l, $p);
}
return $l;
}
function ttlm( $t, $d = ',', $skipempty = true) { // manual ttl

$out = array();
while ( strlen( $t)) {
$pos = 0;
for ( $i = 0; $i < strlen( $t); $i++) if ( ord( substr( $t, $i, 1)) == ord( $d)) break;
if ( $i == strlen( $t)) { array_push( $out, $t); break; }	// end of text
if ( ! $i) { if ( ! $skipempty) array_push( $out, ''); }
else array_push( $out, substr( $t, 0, $i));
$t = substr( $t, $i + 1);
}
return $out;
}
function ltt( $l, $d = ',', $base64 = false) {	// list to text 

if ( ! count( $l)) return '';
if ( $base64) return base64_encode( implode( $d, $l));
return implode( $d, $l);
}
function ldel( $list, $v) {	// delete item from list

$L = array();
foreach ( $list as $item) if ( $item != $v) array_push( $L, $item);
return $L;
}
function ledit( $list, $old, $new) {	// delete item from list

$L = array();
foreach ( $list as $item) {
if ( $item == $old) array_push( $L, $new);
else array_push( $L, $item);
}
return $L;
}
function ltll( $list) { 	// list to list of lists

$out = array(); foreach ( $list as $v) { lpush( $out, array( $v)); }
return $out;
}
function lth( $list, $prefix) { // list to hash using prefix[number] as key, if prefix is array, will use those keys directly

$L = array(); for ( $i = 0; $i < ( is_array( $prefix) ? count( $prefix) : count( $list)); $i++) $L[ $i] = is_array( $prefix) && isset( $prefix[ $i]) ? $prefix[ $i] : "$prefix$i";
$h = array();
for ( $i = 0; $i < ( is_array( $prefix) ? count( $prefix) : count( $list)); $i++) $h[ $L[ $i]] = $list[ $i];
return $h;
}
function lr( $list) { return $list[ mt_rand( 0, count( $list) - 1)]; }

function lrv( $list) { return mt_rand( $list[ 0], $list[ 1]); }

function lm( $one, $two) {

$out = array();
foreach ( $one as $v) array_push( $out, $v);
foreach ( $two as $v) array_push( $out, $v);
return $out;
}
function lisin( $list, $item) { 	// list is in, checks if element is in list

foreach ( $list as $item2) if ( $item2 == $item) return true;
return false;
}
function ladd( &$list, $v) { array_push( $list, $v); }

function lpush( &$list, $v) { array_push( $list, $v); }

function lshift( &$list) { if ( ! $list || ! count( $list)) return null; return array_shift( $list); }

function lunshift( &$list, $v) { array_unshift( $list, $v); }

function lpop( &$list) { if ( ! $list || ! count( $list)) return null; return array_pop( $list); }

function lfirst( &$list) { if ( ! $list || ! count( $list)) return null; return reset( $list); }

function llast( &$list) { if ( ! $list || ! count( $list)) return null; return end( $list); }

function hcsvopen( $filename, $critical = false) {	// returns filehandler

$in = @fopen( $filename, 'r');
if ( $critical && ! $in) die( "could not open [$filename]");
return $in;
}
function hcsvnext( $in, $key = '', $value = '', $notvalue = '') { 	// returns line hash, next by key or value is possible

if ( ! $in) return null;
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in));
if ( ! $line || strpos( $line, '#') === 0) continue;
$hash = tth( $line);
if ( ! $hash || ! count( array_keys( $hash))) continue;
if ( $key) {
if ( ! isset( $hash[ $key])) continue;
if ( $value && $hash[ $key] != $value) continue;
if ( $notvalue && $hash[ $key] == $value) continue;
return $hash;
}
else return $hash;
}
return null;
}
function hcsvclose( $in) { @fclose( $in); }

function hcsvread( $filename, $key = '', $value = '') {	 // returns hash list, can filter by [ key [= value]]

$lines = array();
$hcsv = hcsvopen( $filename);
while ( 1) {
$h = hcsvnext( $hcsv, $key, $value);
if ( ! $h) break;
array_push( $lines, $h);
}
hcsvclose( $hcsv);
return $lines;
}
function jsonencode( $data, $tab = 1, $linedelimiter = "\n") { switch ( gettype( $data)) {

case 'boolean': return ( $data ? 'true' : 'false');
case 'NULL': return "null";
case 'integer': return ( int)$data;
case 'double':
case 'float': return ( float)$data;
case 'string': {
$out = '';
$len = strlen( $data);
$special = false;
for ( $i = 0; $i < $len; $i++) {
$ord = ord( $data{ $i});
$flag = false;
switch ( $ord) {
case 0x08: $out .= '\b'; $flag = true; break;
case 0x09: $out .= '\t'; $flag = true; break;
case 0x0A: $out .=  '\n'; $flag = true; break;
case 0x0C: $out .=  '\f'; $flag = true; break;
case 0x0D: $out .= '\r'; $flag = true; break;
case  0x22:
case 0x2F:
case 0x5C: $out .= '\\' . $data{ $i}; $flag = true; break;
}
if ( $flag) { $special = true; continue; } // switched case
// normal ascii
if ( $ord >= 0x20 && $ord <= 0x7F) {
$out .= $data{ $i}; continue;
}
// unicode
if ( ( $ord & 0xE0) == 0xC0) {
$char = pack( 'C*', $ord, ord( $data{ $i + 1}));
$i += 1;
$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
$out .= sprintf( '\u%04s', bin2hex( $utf16));
$special = true;
continue;
}
if ( ( $ord & 0xF0) == 0xE0) {
$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}));
$i += 2;
$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
$out .= sprintf( '\u%04s', bin2hex($utf16));
$special = true;
continue;
}
if ( ( $ord & 0xF8) == 0xF0) {
$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}));
$i += 3;
$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
$out .= sprintf( '\u%04s', bin2hex( $utf16));
$special = true;
continue;
}
if ( ( $ord & 0xFC) == 0xF8) {
$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}), ord( $data{ $i + 4}));
$i += 4;
$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
$out .= sprintf( '\u%04s', bin2hex( $utf16));
$special = true;
continue;
}
if ( ( $ord & 0xFE) == 0xFC) {
$char = pack( 'C*', $ord, ord( $data{ $i + 1}), ord( $data{ $i + 2}), ord( $data{ $i + 3}), ord( $data{ $i + 4}), ord( $data{ $i + 5}));
$i += 5;
$utf16 = mb_convert_encoding( $char, 'UTF-16', 'UTF-8');
$out .= sprintf( '\u%04s', bin2hex( $utf16));
$special = true;
continue;
}
}
return '"' . $out . '"';
}
case 'array': {
if ( is_array( $data) && count( $data) && ( array_keys( $data) !== range( 0, sizeof( $data) - 1))) {
$parts = array();
foreach ( $data as $k => $v) {
$part = '';
for ( $i = 0; $i < $tab; $i++) $part .= "\t";
$part .= '"' . $k . '"' . ': ' . jsonencode( $v, $tab + 1);
array_push( $parts, $part);
}
return "{" . $linedelimiter . implode( ",$linedelimiter", $parts) . '}';
}
// not a hash, but an array
$parts = array();
foreach ( $data as $v) {
$part = '';
for ( $i = 0; $i < $tab; $i++) $part .= "\t";
array_push( $parts, $part . jsonencode( $v, $tab + 1));
}
return "[$linedelimiter" . implode( ",$linedelimiter", $parts) . ']';
}
}}
function jsonraw( $data) { return json_encode( $data); }

function jsonparse( $text) { return json_decode( $text, true); }

function jsonload( $filename, $ignore = false, $lock = false) {	// load from file and then parse 

global $ASLOCKON, $IOSTATSON, $IOSTATS;
$lockd = $ignore ? $lock : $ASLOCKON;	// lock decision, when ignore is on, listen to local flag
$time = null; if ( $lockd) list( $time, $lock) = aslock( $filename);
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.aslock,time=$time"));
$start = null; if ( $IOSTATSON) $start = tsystem();
$body = ''; $in = @fopen( $filename, 'r'); while ( $in && ! feof( $in)) $body .= trim( fgets( $in));
if ( $in) fclose( $in);
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.fread,time=" . round( tsystem() - $start, 4)));
if ( $lockd) asunlock( $filename, $lock);
//die( '' . count( jsonparse( $body)));
$info = $body ? @jsonparse( $body) : null;
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsonload.done,took=" . round( 1000000 * ( tsystem() - $start)) . ',size=' . ( $body ? strlen( $body) : 0)));
return $info;
}
function jsondump( $jsono, $filename, $ignore = false, $lock = false) {	// dumps to file, does not use JSON class

global $ASLOCKON, $IOSTATSON, $IOSTATS, $JSONENCODER;
$lockd = $ignore ? $lock : $ASLOCKON;	// lock decision, when ignore is on, listen to local flag
$time = null; if ( $lockd)  list( $time, $lock) = aslock( $filename);
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.aslock,time=$time"));
$start = null; if ( $IOSTATSON) $start = tsystem();
$text = $JSONENCODER( $jsono);
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.jsonencode,time=" . round( tsystem() - $start, 4)));
$out = fopen( $filename, 'w'); fwrite( $out, $text); fclose( $out);
if ( $lockd) asunlock( $filename, $lock);
if ( $IOSTATSON) lpush( $IOSTATS, tth( "type=jsondump.done,took=" . round( 1000000 * ( tsystem() - $start)) . ',size=' . strlen( $text)));
}
function jsonsend( $jsono, $header = false) {	// send to browser, do not use JSON class

global $JSONENCODER;
if ( $header) header( 'Content-type: text/html');
echo $JSONENCODER( $jsono);
}
function jsonsendbycallback( $jsono) {	// send to browser, do not use JSON class

$txt = $jsono === null ? null : base64_encode( json_encode( $jsono));
echo "eval( callback)( '$txt')\n";
}
function jsonsendbycallbackm( $items, $asjson = false) {	// send to browser, do not use JSON class, send a LIST of items, first aggregating, then calling a callback

echo "var list = [];\n";
foreach ( $items as $item) echo "list.push( " . ( $asjson ? json_encode( $item) : $item) . ");\n";
echo "eval( callback)( list);\n";
}
function h2json( $h, $base64 = false, $base64keys = '', $singlequotestrings = false, $bzip = false) {

global $JSONENCODER;
if ( ! $base64keys) $base64keys = array();
if ( $base64keys && is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
foreach ( $base64keys as $k) $h[ $k] = base64_encode( $h[ $k]);
if ( $singlequotestrings) foreach ( $h as $k => $v) if ( is_string( $v)) $h[ $k] = "'$v'";
$json = $JSONENCODER( $h);
if ( $bzip) $json = bzcompress( $json);
if ( $base64) $json = base64_encode( $json);
return $json;
}
function json2h( $json, $base64 = false, $base64keys = '', $bzip = false) {

if ( ! $base64keys) $base64keys = array();
if ( $base64keys && is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
if ( $base64) $json = base64_decode( $json);
if ( $bzip) $json = bzdecompress( $json);
$h = @jsonparse( $json);
if ( $h) foreach ( $base64keys as $k) $h[ $k] = base64_decode( $h[ $k]);
return $h;
}
function b64jsonload( $file, $json = true, $base64 = true, $bzip = false) {

$in = finopen( $file); $HL = array();
while ( ! findone( $in)) {
list( $h, $progress) = finread( $in, $json, $base64, $bzip); if ( ! $h) continue;
lpush( $HL, $h);
}
finclose( $in); return $HL;
}
function b64jsonldump( $HL, $file, $json = true, $base64 = true, $bzip = false) {

$out = foutopen( $file, 'w'); foreach ( $HL as $h) foutwrite( $out, $h, $json, $base64, $bzip); foutclose( $out);
}
function jsonerr( $err) { 

global $JO;
if ( ! isset( $JO[ 'errs'])) $JO[ 'errs'] = array();
array_push( $JO[ 'errs'], $err);
return $JO;
}
function jsonmsg( $msg) {

global $JO;
if ( ! isset( $JO[ 'msgs'])) $JO[ 'msgs'] = array();
array_push( $JO[ 'msgs'], $msg);
return $JO;
}
function jsondbg( $msg) {

global $JO;
if ( ! isset( $JO[ 'dbgs'])) $JO[ 'dbgs'] = array();
array_push( $JO[ 'dbgs'], $msg);
return $JO;
}
function mcurvexp( $vs, $exp, $min = null, $max = null, $round = 5) { // vs: list | 'min,max,step'    y = exp( - exp * v)

if ( is_string( $vs)) { 	// 'min,max,step'
extract( lth( ttl( $vs), ttl( 'min2,max2,step2')));
$vs = array(); for ( $v = $min2; $v <= $max2; $v += $step2) lpush( $vs, $v);
}
$map = array(); foreach( $vs as $v) lpush( $map, exp( - $exp * $v));
if ( $min !== null && $max !== null) $map = mmap( $map, $min, $max, $round);
sort( $map, SORT_NUMERIC);
return $map;
}
function mcurveinv( $vs, $min = null, $max = null, $round = 5) { // vs: list | 'min,max,step'    y = 1 / x  -- maps to 0.001..1 first

if ( is_string( $vs)) { 	// 'min,max,step'
extract( lth( ttl( $vs), ttl( 'min2,max2,step2')));
$vs = array(); for ( $v = $min2; $v <= $max2; $v += $step2) lpush( $vs, $v);
}
$vs = mmap( $vs, 0.001, 1);
$map = array(); foreach( $vs as $v) lpush( $map, 1 / $v);
if ( $min !== null && $max !== null) $map = mmap( $map, $min, $max, $round);
sort( $map, SORT_NUMERIC);
return $map;
}
function mproghalfpairpermcount( $n) { // returns number of bi-directional permutations for pairs in n items -- for example, used in tomography

// the loops are OUTER( i = 1; i < n - 1; i++) { INNER( ii = i + 1; ii < n; ii++) { }}
return ( $n - 1) * ( ( ( $n - 1) + 1) / 2);
}
function mproghalfpairperminvert( $count) { // reverse-engineer $n from $count

// $count = ( $n - 1) * ( ( ( $n - 1) + 1) / 2)
// $count = ( $n - 1) * ( $n / 2)
// $count = 0.5 * $n^2 - 0.5 * $n
// 0.5 * $n^2 - 0.5 * $n - $count = 0
// 0.5 * $n^2 +  -0.5 * $n +  -$count = 0
// $root = ( 0.5 +- pow( 0.5^2 + 4 * 0.5 * $count, 0.5) / ( 2 * 0.5)
// $root = 0.5 +- pow( 0.25 + 2 * $count, 0.5)
return 0.5 + pow( 0.25 + 2 * $count, 0.5);
}
function mproghalfpairpermfind( $n, $pos) { // returns( i, ii) -- finds i and ii from the position -- brutal looping

$myi = -1; $myii = -1; $count = 0;
for ( $i = 0; $i < $n - 1; $i++) for ( $ii = $i + 1; $ii < $n; $ii++) { if ( $count == $pos) { $myi = $i; $myii = $ii; }; $count++; }
return array( $myi, $myii);
}
function mrotate( $r, $a, $round = 3) { 	// rotate point ( r, 0) for a degrees (ccw) and return new ( x, y)

while ( $a > 360) $a -= 360;
$cos = cos( 2 * 3.14159265 * ( $a / 360));
$x = round( $r * $cos, $round);
$y = round( pow( pow( $r, 2) - pow( $x, 2), 0.5), $round);
if ( ! misvalid( $y)) $y = 0;
if ( $a > 180) $y = - $y;
return compact( ttl( 'x,y'));
}
function misvalid( $number) {

if ( strtolower( "$number") == 'nan') return false;
if ( strtolower( "$number") == 'na') return false;
if ( strtolower( "$number") == 'inf') return false;
if ( strtolower( "$number") == '-inf') return false;
return true;
}
function mrand( $min, $max, $multiplier = 1000, $round = 4) {

if ( $min > $max) { $a = $max; $max = $min; $min = $a; }
return round( mt_rand( round( $multiplier * $min), round( $multiplier * $max)) / $multiplier, $round);
}
function mr( $length = 10) {	// math random

$out = '';
for ( $i = 0; $i < $length; $i++) $out .= mt_rand( 0, 9);
return $out;
}
function msum( $list) {

$sum = 0; foreach ( $list as $item) $sum += $item;
return $sum;
}
function mavg( $list) {

$sum = 0;
foreach ( $list as $item) $sum += $item;
return count( $list) ? $sum / count( $list) : 0;
}
function mmean( $list, $sort = false) { return m50( $list, $sort); }

function mumid( $list, $sort = false) { $h = array(); foreach ( $list as $v) $h[ "$v"] = true; if ( $sort) ksort( $h, SORT_NUMERIC); return m50( hk( $h)); }

function m25( $list, $sort = false) { if ( $sort) sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.25 * count( $list))]; }

function m50( $list, $sort = false) { if ( $sort) sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.5 * count( $list))]; }

function m75( $list, $sort = false) { if ( $sort) sort( $list, SORT_NUMERIC); return $list[ ( int)( 0.75 * count( $list))]; }

function mAT( $list, $pos, $sort = false) { if ( $sort) sort( $list, SORT_NUMERIC); if ( $pos < 0) $pos = 0; if ( $pos > count( $list) - 1) $pos = count( $list) - 1; return $list[ $pos]; }

function mvar( $list) {

$avg = mavg( $list);
$sum = 0;
foreach ( $list as $item) $sum += abs( pow( $item - $avg, 2));
return count( $list) ? pow( $sum / count( $list), 0.5) : 0;
}
function mmin( $one, $two = NULL) {

$list = $one;
if ( $two !== NULL && ! is_array( $one)) $list = array( $one, $two);
$min = $list[ 0];
foreach ( $list as $v) if ( $v < $min) $min = $v;
return $min;
}
function mmax( $one, $two = NULL) {

$list = $one;
if ( $two !== NULL && ! is_array( $one)) $list = array( $one, $two);
$max = $list[ 0];
foreach ( $list as $v) if ( $v > $max) $max = $v;
return $max;
}
function mround( $v, $round) { // difference from traditional math, $round can be negative, will round before decimal points in this case

if ( $round >= 0) return round( $v, $round);
// round is a negative value, will round before the decimal point
$v2 = 1; for ( $i = 0; $i < abs( $round); $i++) $v2 *= 10;
return $v2 * round( $v / $v2); // first, shrink, then round, then expand again
}
function mhalfround( $v, $round) { // round is multiples of 0.5, same as mround, only semi-decimal, i.e. rounds within closest 0.5 or 5

$round2 = $round - round( $round); // possible half a decimal
$round = round( $round);	// decimals
if ( $round2) $v *= 2;	// make the thing twice as big before rounding
$v = mround( $v, $round);
if ( $round2) $v = mround( 0.5 * $v, $round+1);
return $v;
}
function mratio( $one, $two) {	// one,two cannot be negative

if ( ! $one || ! $two) return 0;
if ( $one && $two && $one == $two) return 1;
$one = abs( $one); $two = abs( $two);
return mmin( $one, $two) / mmax( $one, $two);
}
function mstats( $list, $precision = 2) { 	// return hash of simple stats: min,max,avg,var

$min = mmin( $list); $max = mmax( $list); $avg = round( mavg( $list), $precision); $var = round( mvar( $list), $precision);
$h = tth( "min=$min,max=$max,avg=$avg,var=$var");
foreach ( $h as $k => $v) $h[ $k] = round( $v, $precision);
return $h;
}
function mrel( $list, $min = null, $max = null, $round = 6) { // returns list of values relative to the min

$vs = $list; if ( ! is_array( $vs)) $vs = array( $vs);
if ( $min === null) $min = mmin( $vs);
for ( $i = 0; $i < count( $vs); $i++) $vs[ $i] = ( $max !== null && $max - $min > 0) ? ( $vs[ $i] - $min) / ( $max - $min) : $vs[ $i] / ( $min ? $min : 1);
for ( $i = 0; $i < count( $vs); $i++) $vs[ $i] = round( $vs[ $i], $round);
return is_array( $list) ? $vs : $vs[ 0];
}
function mlog( $list, $digits = 5, $neg = null, $zero = null) { // [ 1, infty] normal, [0, 1] - log( 1 / x), negative are not allowed

$L = $list; if ( ! is_array( $L)) $L = array( $L);
foreach ( $L as $i => $v) {
if ( $v < 0) $v = abs( $v);
if ( $v < 1 && $v > 0) $v = 1 / $v;
if ( $v == 0)$v = 1;
$v = log10( $v);
if ( ! misvalid( $v)) $v = 0; 	// invalid is 0 by default
$L[ $i] = $v;
}
return is_array( $list) ? $L : $L[ 0];
}
function mmap( $list, $min, $max, $precision = 5, $normprecision = 5) {

$list2 = mnorm( $list, null, null, $normprecision);
$list3 = array();
foreach ( $list2 as $v) lpush( $list3, round( $min + $v * ( $max - $min), $precision));
return $list3;
}
function mmaplog( $list, $min, $max, $logmin = 1, $logmax = 10000) {

$list = mmap( $list, $logmin, $logmax);
for ( $i = 0; $i < count( $list); $i++) $list[ $i] = log10( $list[ $i]);
return mmap( $list, $min, $max);
}
function mnorm( $list, $optmax = NULL, $optmin = NULL, $precision = 5) {	// normalize the list to 0..1 scale

$out = array();
$min = mmin( $list);
if ( $optmin !== NULL) $min = $optmin;
$max = mmax( $list);
if ( $optmax !== NULL) $max = $optmax;
foreach ( $list as $item) array_push( $out, round( mratio( $item - $min, $max - $min), $precision));
return $out;
}
function mabs( $list, $round = 5) { // returns list with abs() of values

$out = array(); for ( $i = 0; $i < count( $list); $i++) $out[ $i] = round( abs( $list[ $i]), $round);
return $out;
}
function mdistance( $list) { 	// returns list of distances between samples

$out = array();
for ( $i = 1; $i < count( $list); $i++) array_push( $out, $list[ $i] - $list[ $i - 1]);
return $out;
}
function mpercentile( $list, $percentile, $direction) {

if ( ! count( $list)) return $list;
sort( $list, SORT_NUMERIC);
$range = $list[ count( $list) - 1] - $list[ 0];
$threshold = $list[ 0] + $percentile * $range;
if ( $direction == 'both') $threshold2 = $list[ 0] + ( 1 - $percentile) * $range;
$out = array();
foreach ( $list as $item) {
if ( $direction == 'both' && $item >= $threshold && $item <= $threshold2) {
array_push( $out, $item);
continue;
}
if ( ( $item <= $threshold && $direction == 'down') || ( $item >= $threshold && $direction == 'up'))
array_push( $out, $item);
}
return $out;
}
function mqqplotbysum( $one, $two, $step = 1, $round = 2) { // returns [ x, y], x=one, y=two, lists have to be the same size

$sum = 0;
foreach ( $one as $v) $sum += $v;
foreach ( $two as $v) $sum += $v;
$x = array(); $y = array();
$sum2 = 0;
for ( $i = 0; $i < count( $one); $i += $step) {
for ( $ii = $i; $ii < $i + $step; $ii++) {
$sum2 += $one[ $ii];
$sum2 += $two[ $ii];
}
lpush( $x, round( $sum2 / $sum, 2));
lpush( $y, round( $sum2 / $sum, 2));
}
return array( $x, $y);
}
function mqqplotbyvalue( $one, $two, $step = 1, $round = 2) { // returns [ x, y], x=one, y=two, lists have to be the same size

$max = mmax( array( mmax( $one), mmax( $two)));
$x = array(); $y = array();
for ( $i = 0; $i < count( $one); $i += $step) {
lpush( $x, round( $one[ $i] / $max, 2));
lpush( $y, round( $two[ $i] / $max, 2));
}
return array( $x, $y);
}
function mdensity( $list, $min = null, $max = null, $step = 100, $window = 30, $round = 3) { // $step = $window / 3 is advised, both are countable numerals 

if ( $min === null) $min = mmin( $list);
if ( $max === null) $max = mmax( $list);
$step = round( ( $max - $min) / $step, $round); $out = array();
for ( $v = ( 0.5 * $window) * $step; $v < $max - ( 0.5 * $window) * $step; $v += $step) {
$count = 0;
foreach ( $list as $v2) if ( $v2 >= ( $v - 0.5 * $window * $step) && $v2 <= $v + 0.5 * $window * $step) $count++;
$out[ "$v"] = $count;
}
return $out;
}
function mfrequency( $list, $shaper = 1, $round = 0) { // round 0 means interger values

$out = array();
foreach ( $list as $v) {
$v = $shaper * ( round( $v / $shaper, $round));
if ( ! isset( $out[ "$v"])) $out[ "$v"] = 0;
$out[ "$v"]++;
}
arsort( $out, SORT_NUMERIC);
return $out;
}
function mjitter( $list, $range, $quantizer = 1000) {

for ( $i = 0; $i < count( $list); $i++) {
$jitter = ( mt_rand( 0, $quantizer) / $quantizer) * $range;
$direction = mt_rand( 0, 9);
if ( $direction < 5) $list[ $i] += $jitter;
else $list[ $i] -= $jitter;
}
return $list;
}
function mmdf( $vs, $step = 0.01, $round = 4) { 	// mass distribution function -- returns [ count ratio for 1%, ratio for 2%, ...]

$min = mmin( $vs); $max = mmax( $vs); $range = $max - $min; $before = compact( ttl( 'min,max,range'));
$vs = mnorm( $vs);
$min = mmin( $vs); $max = mmax( $vs); $range = $max - $min;
if ( $range == 0) die( "math/mmdf() ERROR! range is zero!\n");
$h = array();
foreach ( $vs as $v) { $v = $min + $step * ( int)( $v / $step); htouch( $h, "$v", 0, false, false); $h[ "$v"]++; }
ksort( $h, SORT_NUMERIC); $ks = hk( $h);
for ( $i = 1; $i < count( $ks); $i++) for ( $ii = 0; $ii < $i; $ii++) $h[ '' . $ks[ $ii]] += $h[ '' . $ks[ $i]];
foreach ( $h as $k => $v) $h[ "$k"] = round( $v / count( $vs), $round);
ksort( $h, SORT_NUMERIC);
return hv( $h);
}
function mmdfabs( $vs, $step = 0.01, $round = 5) { 	// mdf with absolute keys

$min = mmin( $vs); $max = mmax( $vs); $range = $max - $min; $before = compact( ttl( 'min,max,range'));
$vs = mnorm( $vs);
$min = mmin( $vs); $max = mmax( $vs); $range = $max - $min;
if ( $range == 0) return array(); //die( "math/mmdf() ERROR! range is zero!\n");
$h = array();
foreach ( $vs as $v) { $v = $min + $step * ( int)( $v / $step); htouch( $h, "$v", 0, false, false); $h[ "$v"]++; }
ksort( $h, SORT_NUMERIC); $ks = hk( $h);
for ( $i = 1; $i < count( $ks); $i++) for ( $ii = 0; $ii < $i; $ii++) $h[ '' . $ks[ $ii]] += $h[ '' . $ks[ $i]];
foreach ( $h as $k => $v) $h[ "$k"] = round( $v / count( $vs), $round);
ksort( $h, SORT_NUMERIC);
extract( $before);
$h2 = array(); foreach ( $h as $k => $v) $h2[ '' . round( $min + $k * $range)] = $v;
return $h2;
}
function utf32isgood( $n) { 	// n: 32-bit integer representation of a char (small endian)

global $UTF32GOODCHARS, $UTF32TRACK; if ( count( $UTF32TRACK) > 50000) $UTF32TRACK = array();	// if too big, reset
if ( isset( $UTF32TRACK[ $n])) return $UTF32TRACK[ $n];	// true | false
$good = false;
foreach ( $UTF32GOODCHARS as $low => $high) if ( $n >= $low && $n <= $high) $good = true;
$UTF32TRACK[ $n] = $good; return $good;
}
function utf32fix( $n, $checkgoodness = true) { 	// returns same number OR 32 (space) if bad symbol

if ( $checkgoodness) if ( ! utf32isgood( $n)) return 32;	// return space
if ( $n >= 65345 && $n <= 65370) $n = 97 + ( $n - 65345);	// convert Romaji to single-byte ASCII
return $n;
}
function utf32ispdfglyph( $n) { return ( $n >= 64256 && $n <= 64260); }

function utf32fixpdf( $n) { // returns UTF-32 string

$L = ttl( 'ff,fi,fl,ffi,ffl'); if ( $n >= 64256 && $n <= 64260) return mb_convert_encoding( $L[ $n - 64256], 'UTF-32', 'ASCII');	// replacement string
return bwriteint( bintro( $n)); // string of the current char, no change
}
function utf32clean( $body, $e = null) {	// returns new body

$body3 = ''; if ( ! mb_strlen( $body)) return $body3;
$body = mb_strtolower( $body);
$body2 = @mb_convert_encoding( $body, 'UTF-32', 'UTF-8'); if ( ! $body2) return '';	// nothing in body
$count = mb_strlen( $body2, 'UTF-32');
//echoe( $e, " cleanfilebody($count)");
for ( $i = 0; $i < $count; $i++) {
if ( $e && $i == 5000 * ( int)( $i / 5000)) echoe( $e, " cleanfilebody(" . round( 100 * ( $i / $count)) . '%)');
$char = @mb_substr( $body2, $i, 1, 'UTF-32'); if ( ! $char) continue;
$n = bintro( breadint( $char));
$n2 = utf32fix( $n, true);	// fix range (32 when bad), fix PDF, convert back to string
if ( $n == $n2 && ! utf32ispdfglyph( $n)) $body3 .= $char;
else $body3 .= utf32fixpdf( $n2);
}
// get rid of double spaces
$body2 = trim( @mb_convert_encoding( $body3, 'UTF-8', 'UTF-32')); if ( ! mb_strlen( $body2)) return '';	// nothing left in string
$before = mb_strlen( $body2);
$limit = 1000; while ( $limit--) {
$body2 = str_replace( '  ', ' ', $body2);
$after = mb_strlen( $body2); if ( $after == $before) break;	// no more change
$before = $after;
}
//echoe( $e, '');
if ( $e) { echoe( $e, " cleanfilebody(" . mb_substr( $body2, 0, 50) . '...)'); sleep( 1); }
return $body2;
}
function sfixpdfglyphs( $s) { 	// fix pdf glyphs like ffi,ff, etc.

$body2 = @mb_convert_encoding( $s, 'UTF-32', 'UTF-8'); if ( ! $body2) return $s;	// nothing in body
$body = ''; $count = mb_strlen( $body2, 'UTF-32');
for ( $i = 0; $i < $count; $i++) {
$char = @mb_substr( $body2, $i, 1, 'UTF-32'); if ( ! $char) continue;
$n = bintro( breadint( $char));
if ( $n == 8211) $char = mb_convert_encoding( '--', 'UTF-32', 'ASCII');
//echo  "  $n:" . substr( $s, $i, 1) . "\n";
if ( ! utf32ispdfglyph( $n)) { $body .= $char; continue; }
$body .= utf32fixpdf( $n);
}
return trim( @mb_convert_encoding( $body, 'UTF-8', 'UTF-32'));
}
function strmailto( $email, $subject, $body) { 	// returns encoded mailto URL -- make sure it is smaller than 10?? bytes

$text = "$email?subject=$subject&body=$body";
$setup = array( '://'=> '%3A%2F%2F', '/'=> '%2F', ':'=> '%3A', ' '=> '%20', ','=> '%2C', "\n"=> '%0A', '='=> '%3D', '&'=> '%26', '#'=> '%23', '"'=> '%22');
foreach ( $setup as $k => $v) $text = str_replace( $k, $v, $text);
return $text;
}
function s2s64( $txt) { return base64_encode( $txt); }

function s642s( $txt) { return base64_decode( $txt); }

function strisalphanumeric( $string, $allowspace = true) {

$ok = true;
$alphanumeric = ". a b c d e f g h i j k l m n o p q r s t u v w x y z A B C D E F G H I J K L M N O P Q R S T U V W X Y Z 0 1 2 3 4 5 6 7 8 9 ";
if ( ! $allowspace) $alphanumeric = str_replace( ' ', '', $alphanumeric);
for ( $i = 0; $i < strlen( $string); $i++) {
$letter = substr( $string, $i, 1);
if ( ! is_numeric( strpos( $alphanumeric, $letter))) { $ok = false; break; }
}
return $ok;
}
function strcleanup( $text, $badsymbols, $replace = '') {

for ( $i = 0; $i < strlen( $badsymbols); $i++) {
$text = str_replace( substr( $badsymbols, $i, 1), $replace, $text);
}
return $text;
}
function strtosqlilike( $text) {	// replaces whitespace with %

$split = explode( ' ', $text);
$split2 = array();
foreach ( $split as $part) {
$part = trim( $part);
if ( ! $part) continue;
array_push( $split2, strtolower( $part));
}
return '%' . implode( '%', $split2) . '%';
}
function strdblquote( $text) { return '"' . $text . '"'; }

function strquote( $text) { return "'$text'"; }

function srep( $before, $after, $what, $eachchar = false) { // if eachchar=true, each replace each char in before by after (after is the same for all)

if ( ! $eachchar) return str_replace( $before, $after, $what);
for ( $i = 0; $i < strlen( $before); $i++) $what = str_replace( substr( $before, $i, 1), $after, $what);
return $what;
}
function tstring2yyyymm( $ym) { // ym should be 'Month YYYY' -- if month is not found, 00 is used

$L = ttl( $ym, ' '); $m = count( $L) == 2 ? lshift( $L) : ''; $y = lshift( $L);
if ( $y < 100) $y = ( $y < 20 ? '20' : '19') . $y;
if ( $m) $m = strtolower( $m);
foreach ( tth( 'jan=01,feb=02,mar=03,apr=04,may=05,jun=06,jul=07,aug=08,sep=09,oct=10,nov=11,dec=12') as $k => $v) { if ( $m && strpos( $m, $k) !== false) $m = $v; }
if ( ! $m) $m = 0;
$ym = round( sprintf( "%04d%02d", $y, $m));
return $ym;
}
function tyyyymm2year( $ym) { return ( int)substr( $ym, 0, 4); }

function tyyyymm2month( $ym) { return $m = ( int)substr( $ym, 4, 2); }

function tm2string( $m, $short = false) { 

$one = ttl( '?,January,February,March,April,May,June,July,August,September,October,November,December');
$two = ttl( '?,Jan.,Feb.,March,April,May,June,July,Aug.,Sep.,Oct.,Nov.,Dec.');
return $short ? $two[ $m] : $one[ $m];
}
function tsystem() {	// epoch of system time

$list = @gettimeofday();
return ( double)( $list[ 'sec'] + 0.000001 * $list[ 'usec']);
}
function tsystemstamp() {	// epoch of system time

$list = @gettimeofday();
return @date( 'Y-m-d H:i:s', $list[ 'sec']) . '.' . sprintf( '%06d', $list[ 'usec']);
}
function tsdate( $stamp) {	// extract date from stamp

return trim( array_shift( explode( ' ', $stamp)));
}
function tstime( $stamp) {	// time part of stamp

return trim( array_pop( explode( ' ', $stamp)));
}
function tsdb( $db) {	// Y-m-d H:i:s.us

return dbsqlgetv( $db, 'time', 'SELECT now() as time');
}
function tsclean( $stamp) {	// cuts us off

return array_shift( explode( '.', $stamp));
}
function tsets( $epoch) {	// epoch to string

$epoch = ( double)$epoch;
return @date( 'Y-m-d H:i:s', ( int)$epoch) . ( count( explode( '.', "$epoch")) === 2 ? '.' . array_pop( explode( '.', "$epoch")) : '');
}
function tsste( $string) {	// string to epoch

$usplit = explode( '.', $string);
$split = explode( ' ', $usplit[ 0]);
$us = ( count( $usplit) == 2) ?  '.' . $usplit[ 1] : '';
$dsplit = explode( '-', $split[ 0]);
$tsplit = explode( ':', $split[ 1]);
return ( double)(
@mktime(
$tsplit[ 0],
$tsplit[ 1],
$tsplit[ 2],
$dsplit[ 1],
$dsplit[ 2],
$dsplit[ 0]) . $us
);
}
function tshinterval( $before, $after = null, $fullnames = false) {	// double values

$prefix = 'ms';
$setup = tth( 'ms=milliseconds,s=seconds,m=minutes,h=hours,d=days,w=weeks,mo=months,y=years');
if ( ! $fullnames) foreach ( $setup as $k => $v) $setup[ $k] = $k;	// key same as value
extract( $setup);
if ( ! $after) $interval = abs( $before);
else $interval = abs( $after - $before);
$ginterval = $interval;
if ( $interval < 1) return round( 1000 * $interval) . $ms;
$interval = round( $interval, 1); if ( $interval <= 10) return $interval . $s; // seconds
if ( $interval <= 60) return round( $interval) . $s;
$interval = round( $interval / 60, 1); if ( $interval <= 10) return $interval . $m; // minutes
if ( $interval <= 60) return round( $interval) . $m;
$interval = round( $interval / 60, 1); if ( $interval <= 24) return $interval . $h; // hours
$interval = round( $interval / 24, 1); if ( $interval <= 7) return $interval . $d; // days
$interval = round( $interval / 7, 1); if ( $interval <= 54) return $interval . $w; // weeks
$interval = round( $interval / 30.5, 1); if ( $interval <= 54) return $interval . $w; // weeks
// interpret months from timestamps
$one = tsets( tsystem()); $two = tsets( tsystem() - $ginterval);
$L = ttl( $one, '-'); $one = 12 * lshift( $L) + lshift( $L) - 1 + lshift( $L) / 31;
$L = ttl( $two, '-'); $two = 12 * lshift( $L) + lshift( $L) - 1 + lshift( $L) / 31;
return round( $one - $two, 1) . $mo;
}
function tshparse( $in) { // parses s|m|h|d|w into seconds

$out = ( double)$in;
if ( strpos( $in, 's')) return $out;
if ( strpos( $in, 'm')) return $out * 60;
if ( strpos( $in, 'h')) return $out * 60 * 60;
if ( strpos( $in, 'd')) return $out * 60 * 60 * 24;
if ( strpos( $in, 'w')) return $out * 60 * 60 * 24 * 7;
return $in;
}
function dbstart( $other = '') {

global $DB, $DBNAME;
$name = $DBNAME;
if ( $other) $name = $other;
if ( $DB) return;  	// already connected
// attempt to connect 20 times with 100ms timeout if failed
for ( $i = 0; $i < 10; $i++) {
$conn = @pg_connect( "dbname=$name");
if ( $conn) {
$DB = $conn;
return;
}
usleep( 50000);
}
die( 'could not connect to db');
}
function dblog( $type, $props, $app = -1, $student = -1) {

global $DB, $ASESSION;
$ssid = $ASESSION[ 'ssid'];
if ( $student == -1) $student = $ASESSION[ 'id'];
if ( ! $props) $props = array();
if ( ! is_array( $props)) $props = tth( $props);
$sql = "INSERT INTO logs ( app, ssid, uid, type, props) VALUES ( $app, '$ssid', $student, '$type', '" . base64_encode( jsonencode( $props)) . "')";
@pg_query( $DB, $sql);
}
function dbsession( $type, $props = array(), $ssid = -1, $user = -1) {

global $DB, $ASESSION;
if ( ! $DB) return;	// no debugging if there is no DB
if ( $ssid == -1) $ssid = $ASESSION[ 'ssid'];
if ( $user == -1) $user = $ASESSION[ 'id'];
if ( ! $props) $props = array();
if ( ! is_array( $props)) $props = tth( $props);
$sql = "INSERT INTO sessions ( ssid, uid, type, props) VALUES ( '$ssid', $user, '$type', '" . htt( $props) . "')";
pg_query( $DB, $sql);
}
function dbnid( $db, $counter) {

$sql = "select nextval( '$counter') as id";
$L = @pg_fetch_object( @pg_query( $db, $sql), 0);
return $L->id;
}
function dbget( $db, $table, $id, $key, $base64 = false) {	// id either hash or hcsv format (use single quotes for symbolic values)

if ( is_array( $id)) $id = htt( $id);
$id = str_replace( ',', ' AND ', $id);
$value = dbsqlgetv( $db, $key, "SELECT $key from $table where $id");
if ( $base64) $value = base64_decode( $value);
return $value;
}
function dbset( $db, $table, $id, $key, $value, $quote = false, $base64 = false) { // id either a hash or hcsv format (use single quotes for symbolic values)

if ( is_array( $id)) $id = htt( $id);
$id = str_replace( ',', ' AND ', $id);
if ( $base64) $value = base64_encode( $value);
if ( $quote) $value = "'$value'";
// automatically detect if quotes are needed (non-numeric need quotes)
if ( ! $quote && ! is_numeric( $value)) $value = "'$value'";
$sql = "UPDATE $table SET $key=$value WHERE $id";
@pg_query( $db, $sql);
}
function dbgetprops( $db, $table, $id, $key) { 

$value = dbget( $db, $table, $id, $key);
if ( ! $value) return array();	// some error, possibly
return tth( $value);
}
function dbsetprops( $db, $table, $id, $key, $hash) {	// quote=true by default

dbset( $db, $table, $id, $key, htt( $hash), true);
}
function dbgetjson( $db, $table, $id, $key, $base64 = false, $base64keys = null) { 

$value = dbget( $db, $table, $id, $key);
if ( ! $value) return array();	// some error, possibly
if ( $base64) $value = base64_decode( $value);
$value = jsonparse( $value);
if ( ! $base64keys) $base64keys = array();
if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
foreach ( $base64keys as $key) if ( isset( $value[ $key])) $value[ $key] = base64_decode( $value[ $key]);
return $value;
}
function dbsetjson( $db, $table, $id, $key, $hash, $base64 = false, $base64keys = null) {	// quote=true by default

if ( ! $base64keys) $base64keys = array();
if ( is_string( $base64keys)) $base64keys = ttl( $base64keys, '.');
foreach ( $base64keys as $key2) if ( $hash[ $key2]) $hash[ $key2] = base64_encode( $hash[ $key2]);
$value = jsonencode( $hash);
if ( $base64) $value = base64_encode( $value);
dbset( $db, $table, $id, $key, $value, true);
}
function dbgetime( $db, $tname, $id) {

$sql = "SELECT time FROM $tname WHERE id=$id";
$line = @pg_fetch_object( @pg_query( $db, $sql), 0);
return $line->time;
}
function dbgetetime( $db, $tname, $id) {	// epoch time

$sql = "SELECT extract( epoch from time) as time FROM $tname WHERE id=$id";
$line = @pg_fetch_object( @pg_query( $db, $sql), 0);
return ( double)$line->time;
}
function dbsetime( $db, $tname, $id, $time) {	// string

global $DBCONN;
$sql = "UPDATE $tname SET time='$time' WHERE id=$id";
@pg_query( $db, $sql);
}
function dbsqlgetv( $db, $key, $sql, $critical = false) {

$R = @pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : null;
$L = pg_fetch_assoc( $R, 0);
if ( $key && ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : null;
return $L[ $key];
}
function dbsqlgetl( $db, $key, $sql, $critical = false) {

$R = @pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : array();
$list = array();
for ( $i = 0; $i < pg_num_rows( $R); $i++) {
$L = pg_fetch_assoc( $R, $i);
if ( ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : array();
array_push( $list, $L[ $key]);
}
return $list;
}
function dbsqlgeth( $db, $keys, $sql, $critical = false) {

if ( ! $keys) $keys = array();
if ( ! is_array( $keys)) $keys = explode( '.', $keys);
$R = @pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : array();
$L = pg_fetch_assoc( $R, 0);
foreach ( $keys as $key) if ( ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : array();
return $L;
}
function dbsqlgethl( $db, $keys, $sql, $critical = false) {

if ( ! $keys) $keys = array();
if ( ! is_array( $keys)) $keys = explode( '.', $keys);
$R = pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : array();
$list = array();
for ( $i = 0; $i < pg_num_rows( $R); $i++) {
$L = pg_fetch_assoc( $R, $i);
foreach ( $keys as $key) if ( ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : array();
array_push( $list, $L);
}
return $list;
}
function dbsqlgethcsv( $db, $key, $sql, $critical = false) {

$R = @pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : null;
$L = pg_fetch_assoc( $R, 0);
if ( ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : null;
return tth( $L[ $key]);
}
function dbsqlgethcsvl( $db, $key, $sql, $critical = false) {

$R = @pg_query( $db, $sql);
if ( ! $R || ! pg_num_rows( $R)) return $critical ? die( "failed running sql [$sql]\n") : array();
$list = array();
for ( $i = 0; $i < pg_num_rows( $R); $i++) {
$L = pg_fetch_assoc( $R, $i);
if ( ! isset( $L[ $key])) return $critical ? die( "no key [$key] set in sql result [" . htt( $L) . "]\n") : array();
array_push( $list, tth( $L[ $key]));
}
return $list;
}
function dbsqlhtth( $hash, $strings = array()) {	// hash to type hash

if ( ! is_array( $strings)) $strings = explode( '.', $strings);
$isstring = array();
foreach ( $strings as $string) $isstring[ $string] = true;
$keys = array_keys( $hash);
$out = array();
foreach ( $keys as $key) $out[ $key] = array( 'isstring' => $isstring[ $key], 'value' => $hash[ $key]);
return $out;
}
function dbsqlseth( $db, $tname, $thash, $show = false) {	

$keys = array_keys( $thash);
$kstring = implode( ',', $keys);
$values = array();
foreach ( $keys as $key) {
if ( $thash[ $key][ 'isstring']) array_push( $values, "'" . $thash[ $key][ 'value'] . "'");
else array_push( $values, $thash[ $key][ 'value']);
}
$vstring = implode( ',', $values);
$sql = "insert into $tname ( $kstring) values ( $vstring)";
if ( $show) echo "SQL[$sql]\n";
pg_query( $db, $sql);
}
function dbsqluph( $db, $tname, $where, $thash) {	// updates	

$keys = array_keys( $thash);
$list = array();
foreach ( $keys as $key) {
$value = $thash[ $key][ 'value'];
if ( $thash[ $key][ 'isstring']) $value = "'$value'";
array_push( $list, "$key=$value");
}
$sql = "update $tname set " . implode( ',', $list) . " where $where";
@pg_query( $db, $sql);
}
function dbtimeclean( $db, $tname, $key, $from, $till, $debug = false) { // returns number of erased entries

if ( is_numeric( $from)) $from = tsets( $from);
if ( is_numeric( $till)) $till = tsets( $till);
$number = 0;
if ( $debug) $number = dbsqlgetv( $db, 'count', "SELECT count( $key) as count from $tname where $key between '$from' and '$till'");
@pg_query( $db, "delete from $tname where $key between '$from' and '$till'");
return $number;
}
function dbl() {	// returns list of hashes (name,owner,encoding) for all dbs

return dbparse( dbrun( "psql -l"));
}
function dbtl( $db) { // returns hashlist(schema,name,type,owner) of tables of a given db

return dbparse( dbrun( 'psql -c "\d" ' . $db));
}
function dbtchl( $db, $table) { // db table column hash list (column, type, modifiers)

return dbparse( dbrun( 'psql -c "\d ' . $table . '" ' . $db));
}
function dbtsize( $db, $table, $cname) { // returns integer for size of table

$in = popen( 'psql -c "select count( ' . $cname . ') as count from ' . $table . '" ' . $db, 'r');
$size = NULL; while ( $in && ! feof( $in)) { $line = trim( fgets( $in)); if ( is_numeric( $line)) $size = ( int)$line; }
pclose( $in); return $size;
}
function dbrun( $command) {

$in = popen( $command, 'r');
$lines = array(); while ( $in && ! feof( $in)) { $line = trim( fgets( $in)); if ( ! $line) continue; array_push( $lines, $line); }
pclose( $in); return $lines;
}
function dbparse( $lines) {	// returns hash list

array_shift( $lines);
$names = ttl( array_shift( $lines), '|'); for ( $i = 0; $i < count( $names); $i++) $names[ $i] = strtolower( $names[ $i]);
array_shift( $lines); $L = array();
while ( count( $lines)) {
$l = ttl( array_shift( $lines), '|', "\n:\t", false);
if ( count( $l) !== count( $names)) continue;
$H = array(); for ( $i = 0; $i < count( $names); $i++) $H[ $names[ $i]] = $l[ $i];
array_push( $L, $H);
}
return $L;
}
function procwget( $url, $h = null, $notjson = false) { // returns [ status(true|false), data]

if ( is_string( $h)) $h = tth( $h);
$file = ftempname( 'json', 'wget');
$c = "wget $url" . ( $h ? '?' . htt( $h, '&') : '') . " -O $file";
procpipe( $c);
$h = array(); $status = true;
if ( is_file( "$file")) $h = $notjson ? implode( '', file( $file)) : jsonload( $file);
else $status = false;
if ( is_file( "$file")) `rm -Rf $file`;
return array( $status, $h);
}
function procwpost( $url, $h, $notjson = false) {	// returns[ status(true|false), data]

$file = ftempname( 'json', 'wpost');
$file2 = ftempname( 'json', 'wpost');
$out = fopen( $file2, 'w'); fwrite( $out, htt( $h, '&')); fclose( $file2);
$c = "wget $url --post-file=$file2 -O $file";
procpipe( $c);
$h = array(); $status = true;
if ( is_file( "$file")) $h = $notjson ? implode( '', file( $file)) : jsonload( $file);
else $status = false;
`rm -Rf $file2`; if ( is_file( "$file")) `rm -Rf $file`;
return array( $status, $h);
}
function procfindlib( $name) { 	// will look either in /usr/local, /APPS or /APPS/research

$paths = ttl( '/usr/local,/APPS,/APPS/research');
foreach ( $paths as $path) {
if ( is_dir( "$path/$name")) return "$path/$name";
}
die( "Did not find library [$name] in any of the paths [" . ltt( $paths) . "]\n");
}
function procfindexec( $name) { 	// interface for which command line utility

if ( is_file( "/APPS/$name")) return "/APPS/$name";
$lines = procpipe( "which $name");
while ( count( $lines) && ! trim( llast( $lines))) lpop( $lines);
$path = ''; if ( count( $lines)) $path = lpop( $lines);
if ( ! $path) return null;
$path = trim( $path);
if ( ! is_file( "$path")) return null;
return $path;
}
function procat( $proc, $minutesfromnow = 0) { 

$time = 'now'; if ( $minutesfromnow) $time .= " + $minutesfromnow minutes";
$out = popen( "at $time >/dev/null 2>/dev/null 3>/dev/null", 'w');
fwrite( $out, $proc);
pclose( $out);
}
function procatwatch( $c, $procidstring, $statusfile, $e = null, $sleep = 2, $timeout = 300) { // c should know/use statusfile

$startime = tsystem(); if ( ! $e) $e = echoeinit();
procat( $c); $h = tth( 'progress=?');
while ( tsystem() - $startime < $timeout) {
sleep( $sleep);
if ( ! procpid( $procidstring)) break;	// process finished
$h2 = jsonload( $statusfile, true, true); if ( ! $h2 && ! isset( $h2[ 'progress'])) continue;
$h = hm( $h, $h2); echoe( $e, ' ' . $h[ 'progress']);
}
echoe( $e, '');	// erase all previous echos
}
function procores() { 	// count the number of cores on this machine

$file = file( '/proc/cpuinfo');
$count = 0; foreach ($file as $line) if ( strpos( $line, 'processor') === 0) $count++;
return $count;
}
function procgspdf2png( $pdf, $png = '', $r = 300) { // returns TRUE | failed command line    -- judges failure by absence of png file

if ( ! $png) { $L = ttl( $pdf, '.'); lpop( $L); $png = ltt( $L, '.') . '.png'; }
if ( is_file( $png)) `rm -Rf $png`;
$c = "gswin32c -q -sDEVICE=png16m -r$r -sOutputFile=$png -dBATCH -dNOPAUSE $pdf"; echopipee( $c);
if ( ! is_file( $png)) return $c;
return true;
}
function procgspdf2preview( $pdf, $r = 150) { // returns array (even for one page) of base64 IMG strings for all pages

`rm -Rf tempreview*`;
extract( fpathparse( $pdf)); // filepath, filename, fileroot, filetype
$CWD = getcwd(); chdir( $filepath);
$c = "gswin64c.exe -sDEVICE=jpeg -r$r -o tempreview%04d.jpg -dBATCH -dNOPAUSE $filename"; echopipee( $c);
$L = array();	// [ 'data:image/jpg;base64,AWERAASF....', ...]
foreach ( flget( '.', 'tempreview', '', 'jpg') as $file) {
$in = fopen( $file, 'r');
lpush( $L, 'data:image/jpg;base64,' . s2s64( fread( $in, filesize( $file))));
fclose( $in);
}
`rm -Rf tempreview*`;
chdir( $CWD);
return $L;
}
function procffmpeg( $in = '%06d.png', $out = 'temp.avi', $rate = null) { // returns TRUE | failed command line

if ( is_file( $out)) `rm -Rf $out`;
$c = "ffmpeg";
if ( $rate) $c .= " -r $rate";
$c .= " -i $in $out";
echopipee( $c);
if ( @filesize( $out) == 0) { `rm -Rf $out`; return $c; }	// present but empty file
if ( ! is_file( $out)) return $c;
echopipee( "chmod -R 777 $out");
return true;
}
function procpdftk( $in = 'tempdf*', $out = 'temp.pdf', $donotremove = false) { // returns TRUE | failed command line

if ( is_file( $out)) `rm -Rf $out`;
$c = "pdftk $in cat output $out"; echopipee( $c);
if ( ! is_file( $out)) return $c;
echopipee( "chmod -R 777 $out");
if ( ! $donotremove) `rm -Rf $in`;
return true;
}
function procdf() { 	// runs df -h in terminal and returns hash { mountpoint: { use(string), avail(string), used(string), size(string)}, ...}

$in = popen( 'df -h', 'r');
$ks = ttl( trim( fgets( $in)), ' '); lpop( $ks); lpop( $ks); lpop( $ks); lpush( $ks, 'Use'); // Mounted on
for ( $i = 0; $i < count( $ks); $i++) $ks[ $i] = strtolower( $ks[ $i]);	// lower caps in all keys
$D = array();
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in)); if ( ! $line) continue;
$vs = ttl( $line, ' '); if ( count( $vs) < 4) continue;	// probably 2-line entry
$mount = lpop( $vs); $h = array();
$ks2 = $ks; while ( count( $ks2) > 1) $h[ lpop( $ks2)] = lpop( $vs);
$D[ $mount] = $h;
}
pclose( $in);
return $D;
}
function procdu( $dir = null) { 	// runs du -s 

$cwd = getcwd(); if ( $dir) chdir( $dir); $size = null;
$in = popen( 'du -s', 'r');
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in)); if ( ! $line) continue;
$size = lshift( ttl( $line, ' '));
}
pclose( $in);
return $size;
}
function procdfuse( $mount) { 	// parser for procdf output, will return int of use on that mount

$h = procdf();
if ( ! isset( $h[ $mount])) return null;
return ( int)( $h[ $mount][ 'use']);
}
function procdfavail( $mount) { 	// will parse 'avail', will return available size in Mb

$h = procdf();
if ( ! isset( $h[ $mount])) return null;
$v = $h[ $mount][ 'avail'];
if ( strpos( $v, 'G')) return 1000 * ( int)( $v);
if ( strpos( $v, 'M')) return ( int)$v;
if ( strpos( $v, 'K') || strpos( $line, 'k')) return 0.001 * ( int)( $v);
}
function echoeinit() { // returns handler { last: ( string length), firstime, lastime}

$h = array(); $h[ 'last'] = 0;
$h[ 'firstime'] = tsystem();
$h[ 'lastime'] = tsystem();
return $h;
}
function echoe( &$h, $msg) { // if h[ 'last'] set, will erase old info first, then post current

if ( $h[ 'last']) for ( $i = 0; $i < $h[ 'last']; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces
echo $msg; $h[ 'last'] = mb_strlen( $msg);
$h[ 'lastime'] = tsystem();
}
function echoetime( &$h) { extract( $h); return tshinterval( $firstime, $lastime); }

function procpid( $name, $notpid = null) {  // returns pid or FALSE, if not running

$in = popen( 'ps ax', 'r');
$found = false;
$pid = null;
while( ! feof( $in)) {
$line = trim( fgets( $in));
if ( strpos( $line, $name) !== FALSE) {
$split = explode( ' ', $line);
$pid = trim( $split[ 0]);
if ( $notpid && $notpid == $pid) { $pid = null; continue; }
$found = true;
break;
}
}
pclose( $in);
if ( $found && is_numeric( $pid)) return $pid;
return false;
}
function procline( $name) {

$in = popen( 'ps ax', 'r');
$found = false;
$pid = null;
$pline = '';
while( ! feof( $in)) {
$line = trim( fgets( $in));
if ( strpos( $line, $name) !== FALSE) {
$pline = $line;
break;
}
}
pclose( $in);
if ( $pline) return $pline;
return false;
}
function prockill( $pid, $signal = NULL) { // signal 9 is deadly

if ( ! $pid) return;	 // ignore, if pid is not set
if ( $signal) `kill -$signal $pid > /dev/null 2> /dev/null`;
else `kill $pid > /dev/null 2> /dev/null`;
}
function prockillandmakesure( $name, $limit = 20, $signal = NULL) { // signal 9 is deadly

$rounds = 0;
while ( $rounds < 20 && $pid = procpid( $name)) { $rounds++; prockill( $pid, $signal); }
return $rounds;
}
function procispid( $pid) {  // returns false|true, true if pid still exists

$in = popen( "ps ax", 'r');
$found = false;
while ( $in && ! feof( $in)) {
$pid2 = array_shift( ttl( trim( fgets( $in)), ' '));
if ( $pid - $pid2 === 0) { pclose( $in); return true; }
}
pclose( $in);
return false;
}
function procpipe( $command, $second = false, $third = false) {	// return output of command

$c = "$command";
if ( $second) $c .= ' 2>&1'; else $c .= ' 2>/dev/null';
if ( $third) $c .= ' 3>&1'; else $c .= ' 3> /dev/null';
$in = popen( $c, 'r');
$lines = array();
while ( $in && ! feof( $in)) array_push( $lines, trim( fgets( $in)));
fclose( $in);
return $lines;
}
function procpipe2( $command, $tempfile, $second = false, $third = false, $echo = false, $pname = '', $usleep = 100000) {

$c = "$command > $tempfile";
$tempfile2 = $tempfile . '2';
if ( $second) $c .= ' 2>&1'; else $c .= ' 2>/dev/null';
if ( $third) $c .= ' 3>&1'; else $c .= ' 3> /dev/null';
`$c &`;
if ( ! $pname) $pname = array_shift( ttl( $command, ' '));
$pid = procpid( $pname); if ( ! $pid) $pid = -1;
$lines = array(); $linepos = 0; $lastround = 3;
while( procispid( $pid) || $lastround) {
if ( ! procispid( $pid)) $lastround--;
// get raw lines
`rm -Rf $tempfile2`;
`cp $tempfile $tempfile2`;
$lines2 = array(); $in = fopen( $tempfile2, 'r'); while ( $in && ! feof( $in)) array_push( $lines2, fgets( $in)); fclose( $in);
`rm -Rf $tempfile2`;
//echo "found [" . count( $lines2) . "]\n";
// convert to actual lines by escaping ^m symbol as well
$cleans = array( 0, 13);
foreach ( $cleans as $clean) {
$lines3 = array(); $next = false;
foreach ( $lines2 as $line) {
//echo "line length[" . strlen( $line) . "]\n";
//$lines4 = ttlm( $line, chr( $clean));
$lines4 = ttl( $line, chr( $clean));
//echo "line split[" . count( $lines4) . "]\n";
foreach ( $lines4 as $line2) array_push( $lines3, trim( $line2));
}
$lines2 = $lines3;
}
for ( $i = 0; $i < $linepos && count( $lines2); $i++) array_shift( $lines2);
$linepos += count( $lines2);
foreach ( $lines2 as $line) { array_push( $lines, $line); if ( $echo) echo "pid[$pid][$linepos] $line\n"; }
usleep( $usleep);
}
return $lines;
}
function procwho() { // returns the name of the user

$in = popen( 'whoami', 'r');
if ( ! $in) die( 'fialed to know myself');
$user = trim( fgets( $in));
fclose( $in);
return $user;
}
function procwhich( $command) { // returns the path to the command

$in = popen( 'which $command', 'r');
$path = ''; if ( $in && ! feof( $in)) $path = trim( fgets( $in));
fclose( $in);
return $path;
}
function echopipe( $command, $tag = null, $silent = false, $chunksize = 1024) { // returns array( time it took (s), lastline)

$in = popen( "$command 2>&1 3>&1", 'r');
$start = tsystem();
$line = ''; $lastline = '';
if ( ! $silent) echo $tag ? $tag : '';
while ( $in && ! feof( $in)) {
$stuff = fgets( $in, $chunksize + 1);
if ( ! $silent) echo $stuff; $line .= $stuff;
$tail = substr( $stuff, mb_strlen( $stuff) - 1, 1);
if ( $tail == "\n") { if ( ! $silent) echo  $tag ? $tag : ''; $lastline = $line; $line = ''; }
}
@fclose( $in);
return array( tsystem() - $start, $lastline);
}
function echopipee( $command, $limit = null, $debug = null, $alerts = null, $logfile = null, $newlog = true) {	// returns array( time it took (s), lastline)

if ( $alerts && is_string( $alerts)) $alerts = ttl( $alerts);
$start = tsystem();
$in = popen( "$command 2>&1 3>&1", 'r');
$count = 0; $line = ''; $lastline = '';
if ( $debug) fwrite( $debug, "opening command [$command]\n");
if ( $logfile && $newlog) { $out = fopen( $logfile, 'w'); fclose( $out); }	// empty the log file, only if newlog = true
if ( $logfile && ! $newlog) { $out = fopen( $logfile, 'a'); fwrite( $out, "NEW ECHOPIPEE for c[$command]\n"); fclose( $out); }
$endofline = false;
while ( $in && ! feof( $in)) {
$stuff = fgetc( $in);
$line .= $stuff == chr( 13) ? "\n" : $stuff;
if ( ( ! $limit || ( $limit && mb_strlen( $line) < $limit)) && $stuff != "\n") {
if ( $endofline) {
// end of line or chunk (with limit), revert the line back to zero
if ( $logfile) { $out = fopen( $logfile, 'a'); fwrite( $out, $line); fclose( $out); }
if ( $debug) fwrite( $debug, $line);
// hide previous output
for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces
$count = 0; $lastline = $line; $line = ''; // back to zero
// check for any alert words in output
if ( $alerts) foreach ( $alerts as $alert) { // if alert word is found, echo the full line and do not erase it
if ( strpos( strtolower( $line), strtolower( $alert)) != false) { echo "   $line   "; break; }
}
$endofline = false;
}
echo $stuff;
if ( $stuff != chr( 8)) $count++;
else $count--; if ( $count < 0) $count = 0;
continue;
}
$endofline = true;
}
for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo ' '; echo chr( 8); } // erase current output
pclose( $in);
if ( $logfile) { $out = fopen( $logfile, 'a'); fwrite( $out, "\n\n\n\n\n"); fclose( $out); }
return array( tsystem() - $start, $lastline);
}
function echopipeo( $command) {	// returns array( time it took (s), lastline)

$start = tsystem();
$in = popen( "$command 2>&1 3>&1", 'r');
$endofline = false; $count = 0; $line = ''; $lastline = '';
while ( $in && ! feof( $in)) {
$stuff = fgetc( $in);
$line .= $stuff == chr( 13) ? "\n" : $stuff;
if ( $endofline) { // none-eol-char but endofline is marked
for ( $i = 0; $i < $count; $i++) { echo chr( 8); echo '  '; echo chr( 8); echo chr( 8); } // retreat erasing with spaces
$count = 0; $lastline = $line; $line = ''; // back to zero
$endofline = false;
}
while ( $in && ! feof( $in)) {
$stuff = fgetc( $in);
$line .= $stuff == chr( 13) ? "\n" : $stuff;
if ( $stuff == "\n") break;	// end of line break the inner loop
echo $stuff;
if ( $stuff != chr( 8)) $count++;
else $count--; if ( $count < 0) $count = 0;
}
$endofline = true;
}
pclose( $in);
return array( tsystem() - $start, trim( $lastline));
}
function echopipes( $command, $code = '|||') {	// returns array( time it took (s), lines)

$start = tsystem(); $e = echoeinit();
$in = popen( "$command 2>&1 3>&1", 'r');
$line = ''; $lines = array();
$buffer = array(); $codebuffer = array(); $collectmode = false;
while ( $in && ! feof( $in)) {
$stuff = fgetc( $in);
$line .= $stuff == chr( 13) ? "\n" : $stuff;
lpush( $buffer, $stuff); while ( count( $buffer) > strlen( $code)) lshift( $buffer);
if ( implode( '', $buffer) == $code && ! $collectmode) { $collectmode = true; $codebuffer = array(); continue; }
if ( implode( '', $buffer) == $code && $collectmode) {	// parse the collection
for ( $i = 0; $i < strlen( $code) - 1 && count( $codebuffer); $i++) lpop( $codebuffer); // remove part of the code
echoe( $e, implode( '', $codebuffer));
$collectmode = false;
continue;
}
if ( $collectmode) lpush( $codebuffer, $stuff);
if ( $stuff == "\n") { lpush( $lines, $line); $line = ''; }
}
pclose( $in); echoe( $e, '');
return array( tsystem() - $start, $lines);
}
function aslock( $file, $timeout = 1.0, $grain = 0.05) {	// returns [ time, lock]

global $ASLOCKS, $ASLOCKSTATS, $ASLOCKSTATSON;
// create a fairly unique lock file based on current time
$time = tsystem(); $start = ( double)$time;
if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=aslock.start,time=$time,file=$file,grain=$grain"));
$out = null; $count = 0;
while( $time - $start < $timeout) {
// create a unique lock filename based on rounded current time
$time = tsystem(); if ( count( ttl( "$time", '.')) == 1) $time .= '.0';
$stamp = '' . round( $time);	// times as string
$L = ttl( "$time", '.'); $stamp .= '.' . lpop( $L);	// add us tail
$stamp = $grain * ( int)( $stamp / $grain);	// round what's left of time to the nearest grain
$lock = "$file.$stamp.lock";
if ( ! is_file( $lock)) { $out = fopen( $lock, 'w'); break; }	// success obtaining the lock
usleep( mt_rand( round( 0.5 * 1000000 * $grain), round( 1.5 * 1000000 * $grain)));	// between 0.5 and 1.5 of the grain
$count++;
}
if ( ! $out) $out = @fopen( $lock, 'w');
if ( ! isset( $ASLOCKS[ $lock])) $ASLOCKS[ $lock] = $out;
if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=aslock.end,time=$time,file=$file,count=$count,status=" . ( $out ? 'ok' : 'failed')));
return array( $time, $lock);
}
function asunlock( $file, $lockfile = null) { // if lockfile is nul, will try to close the last lock with this prefix

global $ASLOCKS, $ASLOCKSTATS, $ASLOCKSTATSON;
$time = tsystem();
if ( $lockfile) {
if ( isset( $ASLOCKS[ $lockfile])) { @fclose( $ASLOCKS[ $lockfile]); @unlink( $lockfile); }
unset( $ASLOCKS[ $lockfile]); @unlink( $lockfile);
}
else {	// lockfile unknown, try to close the last one with $file as prefix
$ks = hk( $ASLOCKS);
while ( count( $ks)) {
$k = lpop( $ks);
if ( strpos( $k, $file) !== 0) continue;
@fclose( $ASLOCKS[ $k]); @unlink( $ASLOCKS[ $k]);
unset( $ASLOCKS[ $k]);
break;
}
}
if ( $ASLOCKSTATSON) lpush( $ASLOCKSTATS, tth( "type=asunlock,time=$time,file=$file,status=ok"));
}
class IDGen { 

public $id = 0;
public $used = array();
public function next() { $this->id++; return $this->id; }
public function random( $digits) {
$limit = 1000;
while ( $limit--) {	// generate a new key
$k = mr( $digits); if ( isset( $this->used[ "$k"])) continue;
return $k;
}
die( " ERROR! IDGen() : cannot generate a random key after 1000 attemps, [" . count( $this->used) . "] key have already been used!\n\n");
}
public function reset() { $this->id = 0; $this->used = array(); }
}
class Location {

public $coordinates;
public function __construct( $one = 0, $two = 0, $three = '') {
if ( is_array( $one)) return $this->coordinates = $one;
if ( count( explode( ':', $one)) > 1) return $this->coordinates = explode( ':', $one);
$this->coordinates = array( $one);
if ( $two !== '') array_push( $this->coordinates, $two);
if ( $three !== '') array_push( $this->coordinates, $three);
}
public function isEmpty() {
if ( count( $this->coordinates) == 1 && ! $this->coordinates[ 0]) return true;
return false;
}
public function distance( $location, $precision = 4) {
$dimension = mmax( array( count( $this->coordinates), count( $location->coordinates)));
$sum = 0;
for ( $i = 0; $i < $dimension; $i++) {
$sum += pow(
isset( $this->coordinates[ $i]) ? $this->coordinates[ $i] : 0 -
isset( $location->coordinates[ $i]) ? $location->coordinates[ $i] : 0,
2
);
}
return $sum ? round( pow( $sum, 0.5), $precision) : 1;
}
public function dimension() { return count( $this->coordinates); }
}
class Node {

public $id;
public $location;	// Location object
public $in;			// hash (id) of Edge objects
public $out; 		// hash (id) of Edge objects
// constructor
public function __construct( $IDGen) {
$this->id = is_numeric( $IDGen) ? ( int)$IDGen : $IDGen->next();
$this->in = array(); $this->out = array();
$this->location = new Location();	// empty location just in case
}
public function place( $location) { $this->location = $location; }
public function addIn( $L) { $this->in[ $L->id] = $L; $L->target = $this; }
public function addOut( $L) { $this->out[ $L->id] = $L; $L->source = $this; }
public function isLink( $N) { // out connecting to this node
foreach ( $this->out as $id => $L) if ( $L->target->id == $N->id) return true;
return false;
}
public function getLink( $N) { // out connecting this with N
foreach ( $this->out as $id => $L) if ( $L->target->id == $N->id) return $L;
die( " Node.ERROR: no link from this node(" . $this->id . ") to node(" . $N->id . ")\n");
}
public function getLinks() { return $this->out; }
public function getDistance( $N) {	// N-dimensional distance, 1 hop
if ( ! $this->location || ! $node->location) return 1;	// location object is not set
return $this->location->distance( $N->location);
}
public function isme( $N) { if ( $this->id == $N->id) return true; return false; }
// location shortcuts
public function x() { return $this->location->coordinates[ 0]; }
public function y() { return $this->location->coordinates[ 1]; }
public function z() { return $this->location->coordinates[ 2]; }
public function nth( $n) { return $this->location->coordinates[ $n]; }
}
class Link {

public $id;
public $cost;
public $bandwidth;
public $propagation;
// objects
public $source;		// Node
public $target; 		// Node
public function __construct( $IDGen, $bandwidth = 1, $cost = 1, $propagation = 0) {
$this->id = is_numeric( $IDGen) ? ( int)$IDGen : $IDGen->next();
$this->cost = $cost;
$this->bandwidth = $bandwidth;
$this->propagation = $propagation;
$this->source = NULL; $this->target = NULL;
}
public function distance() {	// uses Location in both target and source
if ( ! $this->source || ! $this->target) die( " Link.ERROR: distance() cannot be calculated for link(" . $this->id . "), no source and target in this link.\n");
if ( $this->source == $this->target) return 0;
$source = $this->source;
return $this->propagation ? ( $this->propagation * 300000) :  $source->getDistance( $this->target);
}
public function delay() { return round( $this->distance() / 300000, 6); }
public function isme( $L) { if ( $this->source->id == $L->source->id && $this->target->id == $L->target->id) return true; return false; }
}
class Path {	// between 2 nodes, can be multihop

public $source;				// Node object
public $destination;		// Node object
public $hops;				// list of Edge objects
public function __construct( $source) {
$this->source = $source;
$this->destination = $source;	// default at first
$this->hops = array();
}
public function addHop( $L) {
lpush( $this->hops, $L);
$this->destination = $L->target;
}
public function getHops() { return $this->hops; }
public function isNodeInPath( $L) {	// walk all hops
if ( $this->source->id == $L->id) return true;
foreach ( $this->hops as $hop) if ( $hop->target->id == $L->id) return true;
return false;
}
public function getHopCount() { return count( $this->hops); }
public function getHopIds() {
$list = array();
foreach ( $this->hops as $L) lpush( $list, $L->id);
return $list;
}
public function getEndToEndCost( $usedistance = true, $usecost = true) {
$delay = 0;
foreach ( $this->hops as $L) {
if ( ! $usedistance && ! $usecost) $delay += 1;
else $delay += ( $usedistance ? $L->delay() : 1) + ( $usecost ? $L->cost : 1);
if ( ! $delay) $delay = 1;
}
return $delay;
}
public function isSamePath( $P) {
if ( $this->getHopCount() != $P->getHopCount()) return false;
for ( $i = 0; $i < count( $this->hops); $i++) if ( $this->hops[ $i]->id != $P->hops[ $i]->id) return false;
return true;
}
public function isSamePrefix( $P) {	// P is the prefix (shorter)
if ( count( $this->hops) < count( $P->hops)) return false;
for ( $i = 0; $i < count( $P->hops); $i++) if ( $this->hops[ $i]->id != $P->hops[ $i]->id) return false;
return true;
}
public function nodestring( $delimiter = '-') {
$list = array( $this->source->id);
if ( ! $this->getHopCount()) return implode( '.', $list);
for ( $i = 0; $i < count( $this->hops); $i++)
array_push( $list, $this->hops[ $i]->target->id);
return implode( $delimiter, $list);
}
}
class Graph {	// simple container for nodes and edges 

public $nodes = array();
public $links = array();
// GETTERS and SETTERS
// node
public function addNode( $N) { $this->nodes[ $N->id] = $N; }
public function getNodes() { return $this->nodes; }
public function getNode( $id) { return $this->nodes[ $id]; }
public function getNodeCount() { return count( $this->nodes); }
// link
public function addLink( $L) { $this->links[ $L->id] = $L; }
public function getLinks() { return $this->links; }
public function getLink( $id) { return $this->links[ $id]; }
public function getLinkByNodeIds( $id1, $id2) {
foreach ( $this->links as $L) if ( $L->source->id == $id1 && $L->target->id = $id2) return $L;
return null;
}
public function getLinkCount() { return count( $this->links); }
// other functions
public function getDimension() {
$ds = array();
foreach ( $this->getNodes() as $N) {
if ( ! $N->location) continue;
lpush( $ds, count( $N->location->coordinates));
}
return mmax( $ds);
}
public function makePathByNodeIds( $nids) {
if ( ! count( $nids)) return null;
if ( is_array( $nids[ 0])) $nids = lshift( $nids);	// multiple paths, use the first one
$N = $this->getNode( ( int)$nids[ 0]); if ( ! $N) die( " Graph.ERROR: makePathByNodeIds() no node for id(" . $nids[ 0] . ")\n");
$P = new Path( $N); lshift( $nids);
while ( count( $nids)) {
$N = $P->destination; $nid = ( int)lshift( $nids);
$N2 = $this->getNode( $nid); if ( ! $N2) die( " Graph.ERROR: makePathByNodeIds() no node for id(" . $nids[ 0] . ")\n");
if ( ! $N->isLink( $N2)) return die( " Graph.ERROR makePathByNodeIds() : no link between nid(" . $N->id . ") and nid(" . $N2->id . ")\n");
$P->addHop( $N->getLink( $N2));
}
return $P;
}
public function purgeLink( $L) {
unset( $L->source->out[ $L->id]);
unset( $L->target->in[ $L->id]);
unset( $this->links[ $L->id]);
unset( $L);
}
public function purgeNode( $N) {
foreach ( $N->in as $L) $this->purgeLink( $L);
foreach ( $N->out as $L) $this->purgeLink( $L);
unset( $this->nodes[ $N->id]);
unset( $N);
}
}
function ngdrawgraph( $C2, $G, $S1, $S2 = null, $size, $spacer = 0, $shiftx = 0, $shifty = 0, $FS = 18) {

$C = null;
if ( ! $C2) {
list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '1', '0,0', '0.1:0.1:0.1:0.1'); $C2 = $CS[ 0];
foreach ( $G->getNodes() as $N) $C2->train( array( $N->x()), array( $N->y()));
$C2->autoticks( null, null, 10, 10);
}
extract( $C2->info()); // xmin, xmax, ymin, ymax
$size = round( $size * mmax( array( $xmax - $xmin, $ymax - $ymin)));
// draw nodes as rectangles
foreach ( $G->getNodes() as $N) ngdrawnode( $C2, $N, $S1, $S2, $size, $spacer, $shiftx, $shifty);
// draw links as polygons -- complex algorithm for calculating where
foreach ( $G->getLinks() as $L) ngdrawlink( $C2, $L, $S1, $S2, $size, $spacer, $shiftx, $shifty);
return $C ? array( $C, $C2) : $C2;
}
function ngdrawnode( $C2, $N, $S1, $S2, $size, $spacer, $shiftx = 0, $shifty = 0) {

$x = $N->x(); $y = $N->y(); $w = round( 0.5 * $size, 1);
$xys = array();
lpush( $xys, ttl( "$x:-$w:$spacer:$shiftx,$y:-$w:$spacer:$shifty"));
lpush( $xys, ttl( "$x:-$w:$spacer:$shiftx,$y:$w:-$spacer:$shifty"));
lpush( $xys, ttl( "$x:$w:-$spacer:$shiftx,$y:$w:-$spacer:$shifty"));
lpush( $xys, ttl( "$x:$w:-$spacer:$shiftx,$y:-$w:$spacer:$shifty"));
if ( $S2) chartshape( $C2, $xys, $S2);	// erase if found
chartshape( $C2, $xys, $S1);
}
function ngdrawlink( $C2, $L, $S1, $S2, $size, $spacer, $shiftx = 0, $shifty = 0) {

$x1 = $L->source->x(); $y1 = $L->source->y();
$x2 = $L->target->x(); $y2 = $L->target->y();
$xys = array(); $w1 = round( 0.2 * $size, 1); $w2 = round( 0.55 * $size, 1);  $w3 = round( 0.3 * $size, 1);
if ( $x1 == $x2) { // vertical line
$ysmall = mmin( array( $y1, $y2)); $ybig = mmax(  array( $y1, $y2));
lpush( $xys, array( "$x1:-$w1:$spacer:$shiftx", "$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, array( "$x1:$w1:-$spacer:$shiftx", "$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, array( "$x1:$w1:-$spacer:$shiftx", "$ybig:-$w2:$spacer:$shifty"));
lpush( $xys, array( "$x1:-$w1:$spacer:$shiftx", "$ybig:-$w2:$spacer:$shifty"));
}
if ( $y1 == $y2) { // horizontal line
$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$y1:-$w1:$spacer:$shifty"));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$y1:$w1:-$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$y2:$w1:-$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$y2:-$w1:$spacer:$shifty"));
}
if ( $x1 != $x2 && $y1 != $y2 && ( $x1 < $x2 && $y1 < $y2 || $x1 > $x2 && $y1 > $y2)) { // upslope
$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
$ysmall = mmin( array( $y1, $y2)); $ybig = mmax( array( $y1, $y2));
lpush( $xys, ttl( "$xsmall:$w2:-$w3:-$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ysmall:$w2:-$w3:$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx:$w3,$ybig:-$w2:$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ybig:-$w2:$w3:$spacer:$shifty"));
}
if ( $x1 != $x2 && $y1 != $y2 && ( $x1 < $x2 && $y1 > $y2 || $x1 > $x2 && $y1 < $y2)) { // downslope
$xsmall = mmin( array( $x1, $x2)); $xbig = mmax( array( $x1, $x2));
$ysmall = mmin( array( $y1, $y2)); $ybig = mmax( array( $y1, $y2));
lpush( $xys, ttl( "$xsmall:$w2:-$w3:-$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ybig:-$w2:$spacer:$shifty"));
lpush( $xys, ttl( "$xsmall:$w2:-$spacer:$shiftx,$ybig:-$w2:$w3:-$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$w3:$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ysmall:$w2:-$spacer:$shifty"));
lpush( $xys, ttl( "$xbig:-$w2:$spacer:$shiftx,$ysmall:$w2:-$w3:-$spacer:$shifty"));
}
if ( count( $xys)) { if ( $S2) chartshape( $C2, $xys, $S2); chartshape( $C2, $xys, $S1); }
}
function graphvizwrite( $H, $path) { 	// H: [ { area, lineshort, linefull, stationshort, stationfull}, ...] -- list of station hashes

$h = array(); foreach ( $H as $h2) { extract( $h2); htouch( $h, $lineshort); lpush( $h[ $lineshort], $stationshort); }
$out = fopen( $path, 'w');
fwrite( $out, "graph G {\n");
foreach ( $h as $line => $stations) fwrite( $out, "   $line -- " . ltt( $stations, ' -- ') . "\n");
fwrite( $out, "}\n");
fclose( $out);
}
function graphviztext( $json, $size =  '11,8') { 	// depends on graphvizwrite() size in inches, default is an *.info file next to input *.dot

$L = ttl( $json, '.'); lpop( $L); lpush( $L, 'dot'); $out = ltt( $L, '.');
graphvizwrite( jsonload( $json), $out); $in = $out;
$L = ttl( $in, '/', '', false); $in = lpop( $L); $root = ltt( $L, '/'); if ( ! $root) $root = getcwd();
$L = ttl( $in, '.'); lpop( $L); $out = ltt( $L, '.') . '.info';
$path = procfindlib( 'graphviz');
$CWD = getcwd(); chdir( $root);
$c = "$path/bin/neato -Gsize=$size -Tdot $in -o $out"; procpipe( $c);
if ( ! is_file( $out)) die( "ERROR! graphviztext() failed to run c[$c]\n");
chdir( $CWD);
return "$root/$out";
}
function graphvizpdf( $json, $legend = true, $specialine = null, $fontsize = null, $size = null, $colors = null) { 	// depends on graphvizwrite(), will create a PDF file with the same root

$in2 = graphviztext( $json, $size);	// create *.info file first
$L = ttl( $in2, '.'); lpop( $L); lpush( $L, 'pdf'); $out = ltt( $L, '.');
if ( ! $fontsize) $fontsize = 10;
if ( ! $size) $size = '11,8';
if ( ! $colors) $colors = ttl( '#099,#900,#990,#059,#809,#8B2,#B52,#29E,#0A0,#C0C');
if ( is_string( $colors)) $colors = ttl( $colors);
while ( count( $colors) < 15) lpush( $colors, lfirst( $colors));	// add more elements
$raw = jsonload( $json); $link2line = array(); $line2stations = array();
foreach ( $raw as $h2) {
extract( $h2); 	// area, lineshort, linefull, stationshort, stationfull
htouch( $line2stations, $lineshort);
lpush( $line2stations[ $lineshort], $stationshort);
}
foreach ( $line2stations as $line => $stations) {
lunshift( $stations, $line);
for ( $i = 1; $i < count( $stations); $i++) $link2line[ $stations[ $i - 1] . ',' . $stations[ $i]] = $line;
}
die( jsonraw( $link2line));
$L = ttl( $json, '.'); lpop( $L); $root = ltt( $L, '.');
// try to draw the PDF by yourself
$lines = file( $in2); $line2color = array(); $station2colors = array(); $line2comment = array();
$stations = array(); $links = array();
foreach ( $lines as $line) {
$line = trim( $line); if ( ! $line) continue;
$bads = '];'; for ( $i = 0; $i < strlen( $bads); $i++) $line = str_replace( substr( $bads, $i, 1), '', $line);
$line = str_replace( '",', ':', $line); $line = str_replace( ', ', ':', $line);
$line = str_replace( ',', ' ', $line);
$line = str_replace( ':', ',', $line);
$line = str_replace( '"', '', $line);
$L = ttl( $line, '['); if ( count( $L) != 2) continue;
$head = lshift( $L); $tail = lshift( $L);
$h = tth( $tail); if ( ! isset( $h[ 'pos'])) continue;
if ( count( ttl( $head, '--')) == 1) {
$h = hm( $h, lth( ttl( $h[ 'pos'], ' '), ttl( 'x,y'))); $stations[ trim( $head)] = $h; continue;
}
extract( lth( ttl( $head, '--'), ttl( 'name1,name2')));
$h = hm( $h, lth( ttl( $h[ 'pos'], ' '), ttl( 'x1,y1,x2,y2,x3,y3,x4,y4')));
$k = "$name1,$name2";
$h[ 'line'] = $link2line[ $k];
$links[ $k] = $h;
}
foreach ( $raw as $h) { extract( $h); if ( ! isset( $line2color[ $lineshort])) $line2color[ $lineshort] = $lineshort == $specialine ? '#000' : ( count( $colors) ? lshift( $colors) : '#666'); $station2colors[ $lineshort] = array( $line2color[ $lineshort]); }
foreach ( $raw as $h) { extract( $h); htouch( $station2colors, $stationshort); lpush( $station2colors[ $stationshort], $line2color[ $lineshort]); }
foreach ( $raw as $h) { extract( $h); $line2comment[ $lineshort] = $linefull . ' (' . $area . ') ' . ( isset( $linecomment) ? $linecomment : ''); }
//die( "\n\n LINE2COLOR: " . jsonraw( $line2color));
$bottom = 0.05; if ( $legend) $bottom += round( ( count( $line2color) * $fontsize) / 200, 2);
$P = plotinit(); plotpage( $P);
$xs = array(); $ys = array(); foreach ( $stations as $k => $v) { extract( $v); lpush( $xs, $x); lpush( $ys, $y); }
plotscale( $P, $xs, $ys, "0.05:0.05:$bottom:0.05");
$yoff = '-5'; if ( $legend) plotline( $P, mmin( $xs), "0:$yoff", mmax( $xs), "0:$yoff", 0.2, '#000', 1.0); $yoff .= ":-$fontsize"; $used = array();
foreach ( $links as $k => $v) {
die( "V: " . jsonraw( $v));
extract( $v); 	// x1..4, y1..4
plotcurve( $P, $x1, $y1, $x2, $y2, $x3, $y3, $x4, $y4, 'D', $line == $specialine ? 1 : 0.5, $line2color[ $line], null, 1.0);
}
foreach ( $stations as $k => $v) {
extract( $v); 	// width, height, x, y
extract( plotstringdim( $P, $k, $fontsize)); // w, h
$colors = $station2colors[ $k]; $add = 0.07 * count( $colors);
die( jsonraw( $colors));
foreach ( $colors as $color) {
$h2 = hvak( $line2color, true); $line = $h2[ $color];
$color2 = ( $line == $specialine) ? '#fff' : ( isset( $line2color[ $k]) ? '#fff' : '#000');
$color3 = isset( $line2color[ $k]) ? $color : ( $specialine == $line ? '#000' : '#fff');
plotellipse( $P, $x, $y, ( 0.8 + $add) * $w, ( 0.7 + $add) * $h, 0, 0, 360, 'DF', 0.5, $color, $color3);
plotstringmc( $P, $x, $y, $k, $fontsize, $color2, 1.0);
$add -= 0.07;
// draw line legend if needed
if ( isset( $used[ $line]) || ! $legend) continue;
// draw legend
plotellipse( $P, 0.5 * $w, "0:$yoff", 0.8 * $w, 0.7 * $h, 0, 0, 360, 'DF', 0.5, $color, $color);
plotstringmc( $P, 0.5 * $w, "0:$yoff", $line, $fontsize, '#fff', 1.0);
plotstringml( $P, ( 0.5 * $w) . ":$w", "0:$yoff", $line2comment[ $line], $fontsize, '#000', 1.0);
$used[ $line] = true; $yoff .= ":-$em:-2";
}
}
plotdump( $P, $out);
return $out;
}
function graphvizrawrite( $H, $prefix = null) { // H: { line: 'station,station,station', ...} -- returns path to .textgraph file

// first, create global frequency data
$h = array();
foreach ( $H as $k => $vs) foreach ( ttl( $vs) as $v) { htouch( $h, "$v", 0, false, false); $h[ "$v"]++; }
// shuffle H in such a way that (1) most frequent stations are in the middle of lines and (2) line names are the last stations
foreach ( $H as $k => $vs) {
$h2 = array();
foreach ( ttl( $vs) as $v) $h2[ "$v"] = $h[ "$v"];
arsort( $h2, SORT_NUMERIC); $L = hk( $h2); $L2 = array();
while ( count( $L)) {
if ( count( $L)) lpush( $L2, lshift( $L));
if ( count( $L)) lunshift( $L2, lshift( $L));
}
$h2 = hvak( $L2, true, true);
unset( $h2[ "$k"]); $h2[ "$k"] = true;	// line name is the last station
$H[ $k] = hk( $h2);
}
// write to file
$file = $prefix ? "$prefix.textgraph" : ftempname( 'textgraph');
$out = fopen( $file, 'w');
fwrite( $out, "graph G {\n");
foreach ( $H as $line => $stations) fwrite( $out, "   $line -- " . ltt( $stations, ' -- ') . "\n");
fwrite( $out, "}\n");
fclose( $out);
return $file;
}
function graphvizrawneato( $file, $prefix = null, $size = '11,8') { // input: .textgraph file   output: neato txt output

$root = getcwd(); if ( $prefix) { extract( fpathparse( $prefix)); $root = $filepath; }
// call neato
$file2 = $prefix ? "$prefix.neato" : ftempname( 'neato');
$path = procfindlib( 'graphviz');
$CWD = getcwd(); chdir( $root);
$c = "$path/bin/neato -Gsize=$size -Tdot $file -o $file2"; procpipe( $c);
if ( ! is_file( $file2)) die( "ERROR! graphviztext() failed to run c[$c]\n");
chdir( $CWD);
return $file2;
}
function graphvizrawparse( $file, $addlinkcurve = false) { // returns: { nodes: { node: { x, y, w, h}, ...}, links: [ { from, to, curve: 'x,y,x,y,...'}, ...]}

// find bounding box
$bw = null; $bh = array();
foreach ( file( $file) as $line) {
if ( strpos( $line, 'bb=') === false) continue;
$line = str_replace( 'bb="', ',', $line);
$line = str_replace( '"', ',', $line);
$L = ttl( $line); lpop( $L); $bh = lpop( $L); $bw = lpop( $L);
break;
}
//die( " bw=$bw,bh=$bh\n");
$nodes = array();	// { node: { x, y, w, h}, ...}
$links = array(); 	// [ { from node, to node, curve}, ...]
foreach ( file( $file) as $line) {
if ( strpos( $line, 'pos=') === false) continue;	// neither node nor link
//echo "line[$line]\n";
$L = ttl( $line, '['); $one = lshift( $L); $two = lshift( $L);
$two = str_replace( '"', '', $two);
$two = str_replace( ', ', ' ', $two);
$two = str_replace( ']', ' ', $two);
if ( count( ttl( $one, '--')) == 1) { // node
$h = tth( $two, ' '); extract( $h); // pos, width, height
$L = ttl( $pos);
$nodes[ "$one"] = lth( array( $L[ 0], $L[ 1], $width, $height), ttl( 'x,y,w,h'));
}
else { // link
extract( lth( ttl( $one, '--'), ttl( 'from,to')));
$L = ttl( $two, ' '); lpop( $L);
$L2 = array(); while ( strpos( llast( $L), 'pos') === false) lpush( $L2, lpop( $L));
$curve = ltt( $L2);
if ( $addlinkcurve) lpush( $links, compact( ttl( 'from,to,curve'))); // use if you want the curve
else lpush( $links, compact( ttl( 'from,to')));
}
}
// map X and W value
$H = array();
foreach ( $nodes as $h2) { extract( $h2); $H[ "$x"] = $x; $H[ "$w"] = $w; }
foreach ( $links as $h2) if ( isset( $h2[ 'curve'])) { $L = ttl( $h2[ 'curve']); while ( count( $L)) { $H[ '' . lfirst( $L)] = lshift( $L); lshift( $L); }}
$vs = mnorm( hk( $H), $bw, 0); $ks = hk( $H); $map = array();
for ( $i = 0; $i < count( $vs); $i++) $map[ '' . $ks[ $i]] = round( $vs[ $i], 4);
foreach ( $nodes as $k => $h2) { extract( $h2); $h2[ 'x'] = $map[ "$x"]; $h2[ 'w'] = $map[ "$w"]; $nodes[ "$k"] = $h2; }
$mapx = $map;
// map Y and H values
$H = array(); foreach ( $nodes as $h2) { extract( $h2); $H[ "$y"] = $y; $H[ "$h"] = $h; }
foreach ( $links as $h2) if ( isset( $h2[ 'curve'])) { $L = ttl( $h2[ 'curve']); while ( count( $L)) { lshift( $L); $H[ '' . lfirst( $L)] = lshift( $L);  }}
$vs = mnorm( hk( $H), $bh, 0); $ks = hk( $H); $map = array();
for ( $i = 0; $i < count( $vs); $i++) $map[ '' . $ks[ $i]] = round( $vs[ $i], 4);
foreach ( $nodes as $k => $h2) { extract( $h2); $h2[ 'y'] = $map[ "$y"]; $h2[ 'h'] = $map[ "$h"]; $nodes[ "$k"] = $h2; }
$mapy = $map;
// if curve is set, map the curve
foreach ( $links as $k => $h2) if ( isset( $h2[ 'curve'])) {
$L = ttl( $h2[ 'curve']);
for ( $i = 0; $i < count( $L); $i += 2) { $L[ $i] = $mapx[ '' . $L[ $i]]; $L[ $i + 1] = $mapy[ '' . $L[ $i + 1]]; }
$links[ "$k"][ 'curve'] = ltt( $L);
}
$h = compact( ttl( 'nodes,links'));
return $h;
}
function graphvizA4read( $root) { 	// reads [root].lines  and   [root].stations    -- returns [ L2S, S2D, S2L, E2L, L2E]   L2S: line2station, S2D: station2description, S2L: station2line, E2L: edge2line, L2E: line2edge 

$H = array(); $S2L = array(); $L2S = array(); $S2D = array(); $E2L = array(); $L2E = array();
if ( ! is_file( "$root.lines") || ! is_file( "$root.stations")) die( " ngraph: graphvizA4read() ERROR! Did not find necessary files. Need $root.lines and $root.stations\n");
foreach ( file( "$root.lines") as $line) {
$line = trim( $line);
if ( ! $line || strpos( $line, '#') === 0) continue;
$L = ttl( $line, '-'); if ( count( $L) < 2) continue;
$line = lfirst( $L);
htouch( $H, "$line"); $prev = null;
foreach ( $L as $station) {
$H[ "$line"][ "$station"] = true;
htouch( $S2L, "$station");
$S2L[ "$station"][ "$line"] = true;
htouch( $L2S, "$line");
$L2S[ "$line"][ "$station"] = true;
$S2D[ "$station"] = null;
if ( $prev) htouch( $E2L, "$prev:$station");
if ( $prev) $E2L[ "$prev:$station"][ "$line"] = true;
htouch( $L2E, "$line");
if ( $prev) $L2E[ "$line"][ "$prev:$station"] = true;
$prev = $station;
}
}
foreach ( file( "$root.stations") as $line) {
$line = trim( $line);
if ( ! $line || strpos( $line, '#') === 0) continue;
$L = ttl( $line, "\t"); if ( count( $L) < 2) continue;
$station = trim( lshift( $L));
$desc = ltt( $L, ' ');
if ( ! isset( $S2L[ "$station"])) die( " ngraph: graphvizA4read() ERROR! Station [$station] is not mentioned in $root.lines!\n");
foreach ( $S2L[ "$station"] as $line => $v) $H[ "$line"][ "$station"] = $desc;
$S2D[ "$station"] = $desc;
}
foreach ( $S2D as $station => $v) if ( ! $v) die( " ngraph: graphvizA4read() ERROR! Station [$station] was not filled in by description from $root.stations\n");
return array( $H, $S2D, $S2L, $E2L, $L2E);
}
function graphvizA4graphviz( $H, $prefix = null) { // H: { line: 'station,station,station', ...} -- returns path to .graphviz file

// write to file
$file = $prefix ? "$prefix.graphviz" : ftempname( 'graphviz');
$out = fopen( $file, 'w');
fwrite( $out, "graph G {\n");
foreach ( $H as $line => $stations) fwrite( $out, '   ' . ltt( ttl( $stations), ' -- ') . "\n");
fwrite( $out, "}\n");
fclose( $out);
return $file;
}
function graphvizA4layout( $L2S, $m = 'neato', $root = null) { // returns [ Ns, Ls]  Ns: [ { name, x, y}, ...]  Ls: [ { from, to, curve: [ { x, y}, ...]}, ...]

if ( ! $m) $m = 'neato';
if ( ! $root) $root = ftempname(); extract( fpathparse( $root));	// filepath,fileroot
$H = array(); foreach ( $L2S as $line => $h) $H[ "$line"] = ltt( hk( $h));
$graphviz = graphvizA4graphviz( $H, $root);
$cwd = getcwd(); chdir( $filepath);
// call method in $m
$path = procfindlib( 'graphviz');
$c = "$path/bin/$m -Gsize=11,8 -Tdot $graphviz -o $fileroot.$m"; procpipe( $c);
if ( ! is_file( "$fileroot.$m")) die( "ERROR! graphvizA4layout() failed to run c[$c]\n");
// parse the plaintext format
$W = null; $H = null;  $Ns = array(); $Ls = array();
$lines = array(); $block= array();
foreach ( file( "$fileroot.$m") as $line) {
$line = trim( $line); if ( ! $line) continue;
if ( substr( $line, strlen( $line) - 1, 1) == '\\') { lpush( $block, substr( $line, 0, strlen( $line) - 1)); continue; }
lpush( $block, $line);
$line = implode( ' ', $block); $line = str_replace( ', ', ',', $line); $line = str_replace( ' ,', ',', $line); lpush( $lines, $line);
$block = array();
}
if ( count( $block)) lpush( $lines, implode( '', $block));
foreach ( $lines as $line) {
if ( strpos( $line, 'bb=')) { $L = ttl( $line, '"'); lpop( $L); $L = ttl( lpop( $L)); $H = lpop( $L); $W = lpop( $L); }
if ( ! strpos( $line, '--') && strpos( $line, 'pos=')) {
$name = lshift( ttl( $line, ' '));
$line = str_replace( '="', '=', $line); $line = str_replace( '[', ', ', $line);
$line = str_replace( '",', ' -- ', $line);
$line = str_replace( '"]', ' -- ', $line);
$line = str_replace( ' ,', ' -- ', $line);
$line = str_replace( '"', '', $line);
$line = str_replace( ',hei', ' -- hei', $line);
//echo "LINE[$line]\n";
$h = tth( $line, '--');
$L = ttl( $h[ 'pos']); $x = $L[ 0]; $y = $L[ 1];
$width = $h[ 'width']; $height = $h[ 'height'];
$Ns[ "$name"] = compact( ttl( 'name,x,y,width,height'));
//echo jsonraw( $Ns[ "$name"]) . "\n";
}
if ( strpos( $line, '--')) {
$L = ttl( $line, ' '); $from = $L[ 0]; $to = $L[ 2];
$L = ttl( $line, '"'); lpop( $L); $L = ttl( lpop( $L), ' ');
$curve = array(); foreach ( $L as $v) { if ( count( ttl( $v)) != 2) die( " Wierd coords[$v] on line[$line]\n"); lpush( $curve, lth( ttl( $v), ttl( 'x,y'))); }
lpush( $Ls, compact( ttl( 'from,to,curve')));
}
}
// map coordinates to fractions of 1
$x1 = array(); $y1 = array();
foreach ( $Ns as $h) { extract( $h); lpush( $x1, $x); lpush( $x1, $width); lpush( $y1, $y); lpush( $y1, $height); }
foreach ( $Ls as $h) foreach ( $h[ 'curve'] as $h) { extract( $h); lpush( $x1, $x); lpush( $y1, $y); }
$x2 = mnorm( $x1, $W, 0); $y2 = mnorm( $y1, $H, 0);
$xmap = array(); $ymap = array();
for ( $i = 0; $i < count( $x1); $i++) { $xmap[ '' . $x1[ $i]] = $x2[ $i]; $ymap[ '' . $y1[ $i]] = $y2[ $i]; }
foreach ( $Ns as $k => $h) { extract( $h); $Ns[ $k][ 'x'] = $xmap[ "$x"]; $Ns[ $k][ 'y'] = $ymap[ "$y"]; $Ns[ $k][ 'width'] = $xmap[ "$width"]; $Ns[ $k][ 'height'] = $ymap[ "$height"]; }
foreach ( $Ls as $k1 => $h) foreach ( $h[ 'curve'] as $k2 => $h2) { extract( $h2); $Ls[ $k1][ 'curve'][ $k2][ 'x'] = $xmap[ "$x"]; $Ls[ $k1][ 'curve'][ $k2][ 'y'] = $ymap[ "$y"]; }
chdir( $cwd);
return array( $Ns, $Ls);
}
function graphvizA4draw( $nodes, $links, $L2S, $S2L, $E2L, $L2E, $output, $fontsize = 14, $specials = array(), $colors = array( '#000'), $donotclose = false) {	// returns [ L2C, L2W]

if ( ! $specials) $specials = array();
if ( ! $colors) $colors = array( '#000');
// prepare colors  L2C: line to color,  L2W: line to line weight
$L2C = array(); foreach ( $L2S as $line => $h) { $L2C[ "$line"] = lfirst( $colors); lpush( $colors, lshift( $colors)); }
$L2W = array(); foreach ( $L2S as $line => $h) $L2W[ "$line"] = count( $h);
asort( $L2W, SORT_NUMERIC); $ks = hv( $L2W); $vs = mmap( hk( $ks), 0.5, 1.5); $map = array(); for ( $i = 0; $i < count( $ks); $i++) $map[ '' . $ks[ $i]] = $vs[ $i];
foreach ( $L2W as $k => $v) $L2W[ "$k"] = $map[ "$v"];
foreach ( $specials as $line => $color) { $L2C[ "$line"] = $color; $L2W[ "$line"] = 2.0; }
$curves = array(); foreach ( $links as $h) { extract( $h); htouch( $curves, "$from:$to"); lpush( $curves[ "$from:$to"], $curve); }
foreach ( $E2L as $k => $h) { extract( lth( ttl( $k, ':'), ttl( 'from,to'))); foreach ( $h as $line => $v) {
if ( ! isset( $curves[ "$from:$to"]) || ! count( $curves[ "$from:$to"])) return array( null, "ERROR! No curve between [$from] and [$to], abort drawing.", null, null);
$L2E[ "$line"][ "$from:$to"] = lshift( $curves[ "$from:$to"]);
}}
//die( jsondump( compact( ttl( 'L2C,L2W,E2C,E2W')), "$filepath/temp.json"));
$FS = $fontsize; $BS = 4.5;
$S = new ChartSetupStyle(); $S->style = 'FD'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = '#fff'; $S->alpha = 1.0;
$Snorm = clone $S; 	// normal style
$R = clone $S; $Sinv = $R; $R->draw = null; $R->lw = 0; $R->fill = '#000';	// invert style
$R = clone $S; $Stext = $R; $R->style = 'D'; $R->draw = '#000'; $R->fill = null;	// text style
$R = clone $Snorm; $Ssnorm = $R; $R->draw = '#888';	// special styles
$R = clone $Sinv; $Ssinv = $R; $R->fill = '#888';
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '1x1', 30, '0:0:0:0');
$C2 = lshift( $CS);
$C2->train( ttl( '0,1'), ttl( '0,1'));
$C2->autoticks( null, null, 10, 10);
$C2->frame( null, null); $used = array();
foreach ( $L2E as $line => $h2) {
$S->draw = $L2C[ "$line"]; $S->lw = $L2W[ "$line"];
foreach ( $h2 as $k => $curve) {
extract( lth( ttl( $k, ':'), ttl( 'from,to'))); 	// from, to
extract( $nodes[ "$from"]); lunshift( $curve, compact( ttl( 'x,y')));
extract( $nodes[ "$to"]); lpush( $curve, compact( ttl( 'x,y')));
chartline( $C2, hltl( $curve, 'x'), hltl( $curve, 'y'), $S);
}
}
foreach ( $nodes as $name => $h2) { for ( $plus = -0.5 + 0.5 * count( $S2L[ "$name"]); $plus >= 0; $plus -= 0.5) {
extract( $h2); 	// x, y, w, h
list( $line, $v) = hshift( $S2L[ "$name"]); $S2L[ "$from"][ "$line"] = $v;	// rotate lines for links
$Stext->draw = '#000'; if ( isset( $L2S[ "$name"])) $Stext->draw = '#fff';
$Sinv->fill = $L2C[ "$line"]; $Sinv->draw = '#000';
$Snorm->draw = $L2C[ "$line"]; $Snorm->fill = '#fff';
chartbaloonellipse( $C2, "$name", $fontsize, $x, $y, isset( $L2S[ "$name"]) ? $Sinv : $Snorm, $Stext, 0, 0, $plus, $plus);
}}
if ( ! $donotclose) $C->dump( $output);
return array( $L2C, $L2W, $C2, $C);
}
function ngparsegml( $file) {	// returns ( 'nodes' => ( id => ( name,x,y), 'links' => ( id => ( source,target,bandwidth,metric)))

$nodes = array();
$links = array();
$in = fopen( $file, 'r');
$entry = NULL; $mode = '';
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in));
if ( strpos( $line, 'node') === 0) {	// new node
if ( $entry) array_push( $nodes, $entry);
$entry = array(); $mode = 'node';
}
if ( strpos( $line, 'edge') === 0) { 	// new edge
if ( $entry) {
if ( $mode == 'node') array_push( $nodes, $entry);
else array_push( $links, $entry);
$mode = 'link';
}
$entry = array();
}
if ( is_array( $entry)) array_push( $entry, $line);
}
array_push( $links, $entry);
fclose( $in);
// turn arrays to hashes
$hnodes = array(); $hlinks = array();
foreach ( $nodes as $node) {
$hnode = array();
foreach ( $node as $line) {
$split = explode( ' ', $line);
$hnode[ array_shift( $split)] = implode( ' ', $split);
}
array_push( $hnodes, $hnode);
}
foreach ( $links as $link) {
$hlink = array();
foreach ( $link as $line) {
$split = explode( ' ', $line);
$hlink[ array_shift( $split)] = implode( ' ', $split);
}
array_push( $hlinks, $hlink);
}
// go over the list
$topo = array( 'nodes' => array(), 'links' => array());
foreach ( $hnodes as $node) {
$id = ( int)$node[ 'id'];
$entry = array(
'name' => str_replace( '"', '', $node[ 'name']),
'x' => ( double)$node[ 'x'],
'y' => ( double)$node[ 'y']
);
$topo[ 'nodes'][ $id] = $entry;
}
foreach ( $hlinks as $link) {
$b = trim( $link[ 'bandwidth']);
if ( strpos( $b, 'G')) $b = 1000000000.0 * ( int)$b;
if ( strpos( $b, 'M')) $b = 1000000.0 * ( int)$b;
array_push( $topo[ 'links'], array(
'source' => ( int)$link[ 'source'],
'target' => ( int)$link[ 'target'],
'bandwidth' => $b,
'weight' => ( double)$link[ 'weight']
));
}
return $topo;
}
function ngmakegraph( $h) {	// h can come from ngparsegml 

$G = new Graph(); $IDGEN = new IDGen();
foreach ( $h[ 'nodes'] as $id => $nh) {
$N = new Node( $id);
$N->place( new Location( $nh[ 'x'], $nh[ 'y']));
$G->addNode( $N);
}
foreach ( $h[ 'links'] as $id => $eh) {
$L = new Link( $IDGEN, $eh[ 'bandwidth'], $eh[ 'weight'], 0);
$L->source = $G->nodes[ ( int)$eh[ 'source']];
$L->target = $G->nodes[ ( int)$eh[ 'target']];
$G->links[ $L->id] = $L;
$L->source->addOut( $L); $L->target->addIn( $L);
}
return $G;
}
/** writes GML format to a file
Rules:
graph should be directed by default, one should avoid having undirected graphs
(if you need one, use script to create undirected GML by creating additional links for reverse directions)
node id attribute of nodes is sequential
node name attribute is in format: $node->name $node->id so that to keep the actual id arround)
in graphics section of node, only x and y will be created,
(* if coordinates have >2 dimensions, something else should be figured out)
all nodes should have Location objects with coordinates set in at least 2 dimensions
(if not, you can use ngrandomlocations() to add random locations to your nodes)
*/
function ngsavegml( $G, $file, $directed = true) {

$out = fopen( $file, 'w');
fwrite( $out, "graph [\n");	// open graph
fwrite( $out, "\t" . "directed " . ( $directed ? '1' : '0') . "\n");
$nids = array(); 	// node id => sequence id
foreach ( $G->nodes as $id => $N) {
fwrite( $out, "\t" . "node [\n");	// open node
fwrite( $out ,"\t\t" . "id " . $id . "\n");
fwrite( $out, "\t\t" . 'name "Node' . $id . '"' . "\n");
fwrite( $out, "\t\t" . "graphics [\n");	// open graphics
fwrite( $out, "\t\t\t" . "center [\n"); 			// open center
fwrite( $out, "\t\t\t\t" . "x " . $N->location->coordinates[ 0] . "\n");
fwrite( $out, "\t\t\t\t" . "y " . $N->location->coordinates[ 1] . "\n");
fwrite( $out, "\t\t\t" . "]\n");					// close center
fwrite( $out, "\t\t" . "]\n");	// close graphics
fwrite( $out, "\t" . "]\n");	 // close node
$nids[ $id] = $N;
}
foreach ( $G->links as $id => $L) {
fwrite( $out, "\t" . "edge [\n");	// open edge
fwrite( $out, "\t\t" . "simplex 1\n");
fwrite( $out, "\t\t" . "source " . $L->source->id . "\n");
fwrite( $out, "\t\t" . "target " . $L->target->id . "\n");
fwrite( $out, "\t\t" . "bandwidth " . $L->bandwidth . "\n");
fwrite( $out, "\t\t" . "weight " . $L->cost . "\n");
fwrite( $out, "\t" . "]\n"); 			// close edge
}
fwrite( $out, "]\n");	// close graph
fclose( $out);
}
/** takes full path to GML file, node id 1,2, returns list of paths (=node id lists)
* 		returns list of array( source,node1,node2...,dest) of node ids
* 		in most cases, there is only one array in the list = one shortest path exists between nodes
*		WARNING: list can also be empty = there is no path between nodes
*/
function ngRspGML( $gml, $n1, $n2, $cleanup = true) { // if cleanup=false, set path to Rscript file 

if ( ! is_numeric( $n1)) $n1 = $n1->id;
if ( ! is_numeric( $n2)) $n2 = $n2->id;
$s = "library( igraph)\n";
$s .= 'g <- read.graph( "' . $gml . '", "gml")' . "\n";
$s .= 'get.shortest.paths( g, ' . $n1 . ', ' . $n2 . ', "out")' . "\n";
$lines = Rscript( $s, null, false, $cleanup);
$list = array();
while ( count( $lines)) {
$line = trim( lshift( $lines)); if ( ! $line) continue;
if ( strpos( $line, '[[') !== 0) die( " ERROR Strange line($line)\n");
$vs = Rreadlist( $lines);	// messes with lines (by reference)
$source = ( int)lfirst( $vs);
$dest = ( int)llast( $vs);
if ( $source != $n1 || $dest != $n2) die( " ngRspGML() ERROR: bad e2e path, source($source) and dest($dest) are not ($nid1) and ($nid2)\n");	// start and end are not my nodes
lpush( $list, $vs);
}
return $list;
}
function ngRsp( $T, $n1, $n2, $directed = true, $cleanup = true, $gml = null) {	// writes temp.gml in current dir and calls ngRspGML()

$nid2id = hvak( hk( $T->getNodes()), true);
$id2nid = hk( $T->getNodes());
if ( ! $gml) { ngsavegml( $T, 'temp.gml', $directed); $gml = 'temp.gml'; }	// directed by default
$nids = ngRspGML( $gml, $nid2id[ $n1], $nid2id[ $n2], $cleanup);
if ( ! $nids || ! count( $nids)) return null;
if ( is_array( $nids[ 0])) $nids = lshift( $nids);
for ( $i = 0; $i < count( $nids); $i++) $nids[ $i] = $id2nid[ $nids[ $i]];
if ( $cleanup) `rm -Rf temp.gml`;
return $nids;
}
class OHash {	// object hash, also works with arrays

public $object;
private $keys;
private $hash;
function __construct( &$hash) {
$this->keys = array();
if ( ! $hash || ! is_array( $hash)) return;
$this->hash =& $hash;
$this->keys = array_keys( $hash);
unset( $this->object);
if ( ! $this->end()) $this->object =& $hash[ $this->keys[ 0]];
}
function end() { return count( $this->keys) ? false : true; }
function key() { return $this->keys[ 0]; }
function &object() { return $this->hash[ $this->keys[ 0]]; }
function next() {
array_shift( $this->keys);
unset( $this->object);
if ( count( $this->keys)) $this->object =& $this->hash[ $this->keys[ 0]];
}
}
function Rscript( $rstring, $tempfile = null, $skipemptylines = true, $cleanup = true, $echo = false, $quiet = true) {

global $RHOME;
if ( ! $tempfile) $tempfile = ftempname( 'rscript');
if ( $tempfile && lpop( ttl( $tempfile, '.')) != 'rscript') $tempfile = ftempname( 'rscript', $tempfile);
$out = fopen( $tempfile, 'w');
fwrite( $out, $rstring . "\n");
fclose( $out);
$c = "Rscript $tempfile";
if ( $RHOME) $c = "$RHOME/bin/$c";
if ( $quiet) $c .= ' 2>/dev/null 3>/dev/null';
$in = popen( $c, 'r');
$lines = array();
while ( $in && ! feof( $in)) {
$line = trim( fgets( $in));
if ( ! $line && $skipemptylines) { if ( $echo) echo "\n"; continue; }
if ( $echo) echo $line . "\n";
array_push( $lines, $line);
}
fclose( $in);
if ( $cleanup) `rm -Rf $tempfile`;
return $lines;
}
function Rreadlist( &$lines) { 	// reads split list in R output, list split into several lines, headed by [elementcount]

$L = array();
while ( count( $lines)) {
$line = lshift( $lines);
if ( ! trim( $line)) break;
$L2 = ttl( trim( $line), ' ');	// safely remove empty elements
if ( ! $L2 || ! count( $L2)) break;
if ( strpos( $L2[ 0], '[') !== 0) break;
$count = ( int)str_replace( '[', '', str_replace( ']', '', $L2[ 0]));
if ( $count !== count( $L) + 1) die( "Rreadlist() ERROR: Strange R line, expecting count[" . count( $L) . "] but got line [" . trim( $line) . "], critical, so, die()\n\n");
for ( $ii = 1; $ii < count( $L2); $ii++) lpush( $L, $L2[ $ii]);
}
return $L;
}
function Rreadmatrix( &$lines) {	// reads a matrix of values, returns mx object

// first, estimate how many rows in matrix (not cols)
$rows = array();
while ( count( $lines)) {
$line = trim( lshift( $lines)); if ( ! $line) break;
$L = ttl( $line, ' '); $head = lshift( $L);
//echo " line($line) head($head) L:" . json_encode( $L) . "\n";
if ( strpos( $head, ',]') === false) continue; // next line
$head = str_replace( ',', '', $head);
htouch( $rows, "$head"); foreach ( $L as $v) lpush( $rows[ "$head"], $v);
}
//echo " read matrix OK\n";
return hv( $rows);	// same as mx object: [ rows: [ cols]]
}
function Rreadlisthash( &$lines) {	// reads hash of lists

// first, estimate how many rows in matrix (not cols)
$rows = array(); $ks = array();
while ( count( $lines)) {
$line = trim( lshift( $lines)); if ( ! $line) break;
if ( strpos( $line, '[') === false) { $ks = ttl( $line, ' '); continue; }
$L = ttl( $line, ' '); $head = lshift( $L);
$head = str_replace( '[', '', $head); $head = str_replace( ',]', '', $head);
$line = ( int)$head; htouch( $rows, $line);
if ( count( $L) != count( $ks)) die( " Rreadlisthash() ERROR! ks(" . ltt( $ks) . ") does not match vs(" . ltt( $L) . ")\n");
for ( $i = 0; $i < count( $ks); $i++) $rows[ $line][ $ks[ $i]] = $L[ $i];
}
foreach ( $rows as $row => $h) $rows[ $row] = hv( $h);
return hv( $rows);
}
function Rpe( $L, $mindim = 2, $maxdim = 7, $lagmin = 1, $lagmax = 1, $cleanup = true) { 	// list of values, returns minimum PE

$R = "library( pdc)\n";
$R .= "pe <- entropy.heuristic( c( " . ltt( $L) . "), m.min=$mindim, m.max=$maxdim, t.min=$lagmin, t.max=$lagmax)\n";
$R .= 'pe$entropy.values';
$mx = mxtranspose( Rreadmatrix( Rscript( $R, 'pe', false, $cleanup))); if ( ! $mx || ! is_array( $mx) || ! isset( $mx[ 2])) die( " bad R.PE\n");
$h  = array();
return round( mmin( $mx[ 2]), 2); // return the samelest PE among dimensions
}
function RSstrcmp( $one, $two, $cleanup = true) {

$R = "agrep( '$one', '$two')";
$L = Rreadlist( Rscript( $R, null, true, $cleanup));
if ( ! $L && ! count( $L)) return 0;
rsort( $L, SORT_NUMERIC);
return lshift( $L);
}
function Rdixon( $list, $cleanup = true) { // will return { Q, p-value} from Dixon outlier test, data should be ordered and preferrably normalized

sort( $list, SORT_NUMERIC);
$script = "library( outliers)\n";
$script .= "dixon.test( c( " . ltt( $list) . "))\n";
$L = Rscript( $script, 'dixon', true, $cleanup);
foreach ( $L as $line) {
$line = trim( $line); if ( ! $line) continue;
$h = tth( $line); if ( ! isset( $h[ 'Q']) || ! isset( $h[ 'p-value'])) continue;
return $h;
}
return null;
}
function Rruns( $list, $skipemptylines = true, $cleanup = true) {

$script = "library( lawstat)\n";
$script .= "runs.test( c( " . ltt( $list) . "))\n";
$L = Rscript( $script, 'runs', $skipemptylines, $cleanup);
if ( ! count( $L)) return lth( ttl( '-1,-1'), ttl( 'statistic,pvalue'));
while ( count( $L) && ! strlen( trim( llast( $L)))) lpop( $L);
if ( ! count( $L)) return lth( ttl( '-1,-1'), ttl( 'statistic,pvalue'));
$s = llast( $L); $s = str_replace( '<', '=', $s);
$h = tth( $s); if ( ! isset( $h[ 'p-value'])) die( "ERROR! Cannot parse RUNS line [" . llast( $L) . "]\n");
return lth( hv( $h), ttl( 'statistic,pvalue'));
}
/** reinforcement learning, requires MDP (depends on XML) package installed (seems to only install on Linux)
* automatic stuff:
*    - binaries are created with RL_ prefix
*    - 'reward' is the automatic label of the optimized variable
* setup structure: [ stage1, stage2, stage3, ... ]
*   stage structure: { 'state label': { 'action label': { action setup}}, ...}
*     action setup: { weight, dests: [ { state (label), prob (0..1)}, ...]}
*/
function RsimpleMDP( $setup, $skipemptylines = true, $cleanup = true) { 	// returns [ { stageno, stateno, state, action, weight}, ...]   list of data for each iteration

// create the script
$s = 'library( MDP)' . "\n";
$s .= 'prefix <- "RL_"' . "\n";
$s .= 'w <- binaryMDPWriter( prefix)' . "\n";
$s .= 'label <- "reward"' . "\n";
$s .= 'w$setWeights(c( label))' . "\n";
$s .= 'w$process()' . "\n";
// create map of stages and actions
$map = array(); foreach ( $setup as $k1 => $h1) lpush( $map, hvak( hk( $h1), true));
//echo 'MAP[' . json_encode( $map) . "]\n";
for ( $i = 0; $i < count( $setup); $i++) {
$h = $setup[ $i];
$s .= '   w$stage()' . "\n";
foreach ( $h as $label1 => $h1) {
//echo "label1[$label1] h1[" . json_encode( $h1) . "]\n";
$s .= '      w$state( label = "' . $label1 . '"' . ( $h1 ? '' : ', end=T') . ')' . "\n";
if ( ! $h1) continue;	// no action state, probably terminal stage
foreach ( $h1 as $label2 => $h2) {
extract( $h2);	// weight, dests: [ { state, prob}]
$fork = array(); foreach ( $dests as $h3) {
extract( $h3); // state, prob
lpush( $fork, 1);
lpush( $fork, $map[ $i + 1][ $state]);
lpush( $fork, $prob);
}
$s .= '         w$action( label = "' . $label2 . '", weights = ' . $weight . ', prob = c( ' . ltt( $fork) . '), end = T)' . "\n";
}
$s .= '      w$endState()' . "\n";
}
$s .= '   w$endStage()' . "\n";
}
$s .= 'w$endProcess()' . "\n";
$s .= 'w$closeWriter()' . "\n";
$s .= "\n";
$s .= 'stateIdxDf( prefix)' . "\n";
$s .= 'actionInfo( prefix)' . "\n";
$s .= 'mdp <- loadMDP( prefix)' . "\n";
$s .= 'mdp' . "\n";
$s .= 'valueIte( mdp , label , termValues = c( 50, 20))' . "\n";
$s .= 'policy <- getPolicy( mdp , labels = TRUE)' . "\n";
$s .= 'states <- stateIdxDf( prefix)' . "\n";
$s .= 'policy <- merge( states , policy)' . "\n";
$s .= 'policyW <- getPolicyW( mdp, label)' . "\n";
$s .= 'policy <- merge( policy, policyW)' . "\n";
$s .= 'policy' . "\n";
// run the script
$L = Rscript( $s, 'mdp', $skipemptylines, $cleanup);
while ( count( $L) && strpos( $L[ 0], 'Run value iteration using') !== 0) lshift( $L);
if ( count( $L) < 3) return null;	// some error, probably the problem is written wrong
lshift( $L); lshift( $L); // header should be sId, n0, s0, lable, aLabel, w0
if ( ! is_numeric( lshift( ttl( $L[ 0], ' ')))) lshift( $L);
$out = array();
foreach ( $L as $line) {
$L2 = ttl( $line, ' ');
$run = lshift( $L2);
lshift( $L2);
$stageno = lshift( $L2);
$stateno = lshift( $L2);
$state = lshift( $L2);
$action = lshift( $L2);
$weight = lshift( $L2);
$h = tth( "run=$run,stageno=$stageno,stateno=$stateno,state=$state,action=$action,weight=$weight");
lpush( $out, $h);
}
// create policy from runs
$policy = array();
foreach  ( $out as $h) {
$stageno = null; extract( $h);	// stageno, state, action
if ( ! is_numeric( $stageno)) continue;
if ( ! isset( $policy[ $stageno])) $policy[ $stageno] = array();
$policy[ $stageno][ $state] = $action;
}
ksort( $policy, SORT_NUMERIC);
return $policy;
}
function Rkmeans( $list, $centers, $group = true, $cleanup = true) { // returns list of cluster numbers as affiliations

sort( $list, SORT_NUMERIC);
$s = 'kmeans( c( ' . ltt( $list) . "), $centers)";
$lines = Rscript( $s, 'kmeans', false, $cleanup);
while ( count( $lines) && trim( $lines[ 0]) != 'Clustering vector:') lshift( $lines);
if ( count( $lines)) lshift( $lines);
$out = array();
foreach ( $lines as $line) {
$line = trim( $line); if ( ! $line) break;	// end of block
$L = ttl( $line, ' '); lshift( $L);
foreach ( $L as $v) lpush( $out, ( int)$v);
}
if ( count( $out) != count( $list)) return null;	// failed
if ( ! $group) return $out; // these are just cluster belonging ... 1 through centers
if ( count( $out) != count( $list)) die( "ERROR! Rkmeans() counts do not match    LIST(" . ltt( $list) . ")   OUT(" . ltt( $out) . ")   LINES(" . ltt( $lines, "\n") . ")\n");
$clusters = array(); for ( $i = 0; $i < $centers; $i++) $clusters[ $i] = array();
for ( $i = 0; $i < count( $list); $i++) {
if ( ! isset( $out[ $i])) die( "ERROR! Rkmeans() no out[$i]   LIST(" . ltt( $list) . ")  OUT(" . ltt( $out) . ")\n");
if ( ! isset( $clusters[ $out[ $i] - 1])) die( "ERROR! Rkmeans() no cluster(" . $out[ $i] . ") in data  LIST(" . ltt( $list) . ")  OUT(" . ltt( $out) . ")");
lpush( $clusters[ $out[ $i] - 1], $list[ $i]);
}
return $clusters;
}
function Rkmeanshash( $list, $means, $digits = 5) { 	// returns { 'center': [ data], ...}

$L = Rkmeans( $list, $means, true);
if ( count( $L) != $means) die( " Rkmeanshash() ERROR! count(" . count( $L) . ") != means($means)\n");
$h = array();
foreach ( $L as $L2) $h[ '' . round( mavg( $L2), $digits)] = $L2;
ksort( $h, SORT_NUMERIC);
return $h;
}
/** cross-correlation function (specifically, the one implemented by R)
$one is the first array
$two is the second array, will be tested agains $one
$lag is the lag in ccf() (read ccf manual in R)
$normalize true will normalize both arrays prior to calling ccf()
$debug should be on only when testing for weird behavior
returns hash ( lag => ccf)
*/
function Rccf( $one, $two, $lag = 5, $normalize = true, $cleanup = true, $debug = false) {

if ( $debug) echo "\n";
if ( $debug) echo "Rccf, with [" . count( $one) . "] and [" . count( $two) . "] in lists\n";
if ( $normalize) { $one = mnorm( $one); $two = mnorm( $two); }
$rstring = 'ccf('
. ' c(' . implode( ',', $one) . '), '
. ' c(' . implode( ',', $two) . '), '
. "plot = FALSE, lag.max = $lag, na.action = na.pass"
. ')';
if ( $debug) echo "rstring [$rstring]\n";
$lines = Rscript( $rstring, 'ccf', true, $cleanup);
while ( count( $lines) && strpos( $lines[ 0], 'Autocorrelations') === false) lshift( $lines); lshift( $lines);
$out = array();
while ( count( $lines)) {
$ks = ttl( lshift( $lines), ' ');
$vs = ttl( lshift( $lines), ' ');
$out = hm( $out, lth( $vs, $ks));
}
return $out;
}
function Rccfbest( $ccf) {

arsort( $ccf, SORT_NUMERIC);
$key = array_shift( array_keys( $ccf));
return $ccf[ $key];
}
function Rccfsimple( $one, $two, $normalize = true, $cleanup = true) { return htv( Rccf( $one, $two, 1, $normalize, $cleanup), '0'); } 

function Racf( $one, $maxlag = 15, $normalize = true, $debug = false) {

if ( $maxlag < 3) return array();	// too small leg
if ( $debug) echo "\n";
if ( $debug) echo "Rccf, with [" . count( $one) . "] and [" . count( $two) . "] in lists\n";
if ( $normalize) { $one = mnorm( $one); $two = mnorm( $two); }
$rstring = 'acf('
. ' c(' . implode( ',', $one) . '), '
. ' c(' . implode( ',', $two) . '), '
. "plot = FALSE, lag.max = $maxlag, na.action = na.pass"
. ')';
if ( $debug) echo "rstring [$rstring]\n";
$lines = Rscript( $rstring, 'acf');
if ( $debug) echo "received [" . count( $lines) . "] lines from Rscript()\n";
if ( $debug) foreach ( $lines as $line) echo '   + [' . trim( $line) . ']' . "\n";
$goodlines = array();
while ( count( $lines)) {
$line = trim( array_pop( $lines));
$line = str_replace( '+', '', str_replace( '[', '', str_replace( ']', '', $line)));
array_unshift( $goodlines, $line);
$L = ttl( $line, ' '); if ( $L[ 0] == 0 && $L[ 1] == 1 && $L[ 2] == 2) break;
}
$out = array();
while ( count( $goodlines)) {
$keys = ttl( array_shift( $goodlines), ' ');
$values = ttl( array_shift( $goodlines), ' ');
for ( $i = 0; $i < count( $keys); $i++) $out[ $keys[ $i]] = $values[ $i];
}
return $out;
}
/** try to fit a list of values to a given distribution model, return parameter hash if successful
$list is a simple array of values ( normalization is preferred?)
$type is the type supported by fitdistr (read R MASS manual)
$expectkeys: string in format key1.key2.key3 (dot-delimited list of keys to parse from fitdist output)
returns hash ( parameter => value)
*** distributions without START: exponential,lognormal,poisson,weibull
*** others will require START variable assigned something
*/
function Rfitdistr( $list, $type, $cleanup = true) {	 // returns hash ( param name => param value)

$rs = "library( MASS)\n"	// end of line is essential
. "fitdistr( c( " . implode( ',', $list) . '), "' . $type . '")' . "\n";
$lines = Rscript( $rs, 'fitdistr', true, $cleanup);
$h = null;
while ( count( $lines) > 2) {
$L = ttl( lshift( $lines), ' ');
$L2 = ttl( $lines[ 0], ' ');
if ( count( $L) != count( $L2)) continue;
$good = true; foreach ( $L2 as $v) if ( ! is_numeric( $v)) $good = false;
if ( ! $good) continue;
// good data
for ( $i = 0; $i < count( $L); $i++) $h[ $L[ $i]] = $L2[ $i];
break;
}
return $h;
}
/** test a given distirbution model agains real samples
$list is array of values to be tested
$type string supported by ks.test() in R (read manual if in doubt)
$params hash specific to a given distribution (read manual, and may be test in R before running automatically)
returns hash ( D, p-value) when successful, empty hash otherwise
*** map from distr names:  exponential=pexp,lognormal=plnorm,poisson=ppois,weibull=pweibull
*/
function Rkstest( $list, $type, $params = null, $cleanup = true) { // params is hash, returns hash of output 

$type = is_array( $type) ? 'c(' . ltt( $type) . ')' :'"' . $type . '"';
$rs = "ks.test( c(" . ltt( $list) . '), ' . $type . ( $params ? ', ' . htt( $params) : '') . ")\n";
$lines = Rscript( $rs, 'kstest', true, $cleanup);
foreach ( $lines as $line) {
$h = tth( str_replace( '<', '=', $line));
if ( ! isset( $h[ 'D']) && ! isset( $h[ 'p-value'])) continue;
return $h;
}
return array();
}
function Rfitlinear( $list) { // returns list( b, a) in Y = aX + b, X: keys, Y: values in list

$s = 'y = c(' . ltt( $list) . ')' . "\n";
$s .= 'x = c(' . ltt( hk( $list)) . ')' . "\n";
$s .= 'lm( y~x)' . "\n";
$lines = Rscript( $s, 'fitlinear');
while( count( $lines) && ! trim( llast( $lines))) lpop( $lines);
if ( ! count( $lines)) return array( null, null);
return ttl( lpop( $lines), ' ');
}
function Rpls( $x, $y, $cleanup = true) { // x: list, y: list (same length), returns list of scores (SPE)

$S = "library( pls)\n";
$S .= "mydata = data.frame( X = as.matrix( c(" . ltt( $x) . ")), Y = as.matrix( c( " . ltt( $y) . ")))\n";
$S .= "data = plsr( X ~ Y, data = mydata)\n";
$S .= 'data$scores' . "\n";
$L = Rscript( $S, 'pls', true, $cleanup);
while ( count( $L) && trim( $L[ 0]) != 'Comp 1') lshift( $L);
if ( ! count( $L)) return null;
lshift( $L); $L2 = array();
for ( $i = 0; $i < count( $y) && count( $L); $i++) lpush( $L2, lpop( ttl( lshift( $L), ' ')));
return $L2;
}
function Rkalman( $x, $degree = 1, $cleanup = true) { 	// x: list, returns prediction list of size( list) [ 0, pred 1, pred2 ...]

$S = "library( dlm)\n";
$S .= "dlmFilter( c( " . ltt( $x) . "), dlmModPoly( $degree))\n";
$L = Rscript( $S, 'kalman', true, $cleanup);
while ( count( $L) && trim( $L[ 0]) != '$f') lshift( $L); // skip until found line '$f' prediction values
lshift( $L);	// skip the line with $f itself
return Rreadlist( $L);
}
/** select top N principle components based an a matrix (matrixmath)
*	$percentize true|false, if true, will turn fractions into percentage points
*	$round how many decimal numbers to round to
*	returns hashlist ( std.dev, prop, cum.prop)
*/
function Rpcastats( $mx, $howmany = 10, $percentize = true, $round = 2) { // returns hashlist

$lines = Rscript( "summary( princomp( " . mx2r( $mx) . "))");
//echo "[" . count( $lines) . "] lines\n";
if ( ! $lines) return array();
while ( strpos( $lines[ 0], 'Importance of components') !== 0) array_shift( $lines);
array_shift( $lines);
$H = array();
while ( count( $lines) && count( array_keys( $H)) < $howmany) {
$tags = ttl( array_shift( $lines), ' ');
//echo "tags: " . ltt( $tags, ' ') . "\n";
for ( $i = 0; $i < count( $tags); $i++) {
$tags[ $i] = array_pop( explode( '.', $tags[ $i]));
}
$labels = ttl( 'std.dev,prop,cum.prop');
while ( count( $labels)) {
$label = array_shift( $labels);
$L = ttl( array_shift( $lines), ' ');
$tags2 = $tags;
while ( count( $tags2)) {
$tag = array_pop( $tags2);
$H[ $tag][ $label] = array_pop( $L);
}
}
}
ksort( $H, SORT_NUMERIC);
$list = array_values( $H);
while ( count( $list) > $howmany) array_pop( $list);
if ( $percentize) for ( $i = 0; $i < count( $list); $i++) foreach ( $list[ $i] as $k => $v) if ( $k != 'std.dev') $list[ $i][ $k] = round( 100 * $v, $round);
return $list;
}
function Rpcascores( $mx, $comp) { // which component, returns list of size of mx's width

$text = "pca <- princomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
$text .= "pca" . '$' . "scores[,$comp]\n";
$lines = Rscript( $text, 'pca');
//echo "[" . count( $lines) . "] lines\n";
if ( ! $lines) return array();
$list = array();
foreach ( $lines as $line) {
$L = ttl( $line, ' '); array_shift( $L);
foreach ( $L as $v) array_push( $list, $v);
}
while ( count( $list) > count( $mx)) array_pop( $list);
return $list;
}
function Rpcaloadings( $mx, $comp, $cleanup = true) { // which component, returns list of size of mx's width

$text = "pca <- princomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
$text .= "pca" . '$' . "loadings[,$comp]\n";
$lines = Rscript( $text, 'pca', true, $cleanup);
//echo "[" . count( $lines) . "] lines\n";
if ( ! $lines) return array();
$list = array();
foreach ( $lines as $line) {
$L = ttl( $line, ' '); array_shift( $L);
foreach ( $L as $v) array_push( $list, $v);
}
while ( count( $list) > count( $mx)) array_pop( $list);
return $list;
}
function Rpcarotation( $mx, $cleanup = true) { // returns MX[ row1[ PC1, PC2,...]], ...] -- standard matrix

$text = "pca <- prcomp( " . ( is_array( $mx[ 0]) ?  mx2r( $mx) : 'matrix( c(' . ltt( $mx) . '), ' . ( int)pow( count( $mx), 0.5) . ', ' . ( int)pow( count( $mx), 0.5) . ')') . ")\n";
$text .= 'pca$rotation' . "\n";
$lines = Rscript( $text, 'pcarotation', true, $cleanup);
return Rreadlisthash( $lines);
}
function Rdist( $rscript, $cleanup = true) { return Rreadlist( Rscript( $rscript, null, true, $cleanup)); } // general distribution runner/reader, output should always be R list

function Rdistbinom( $period, $howmany = 10) { 	// probability is 1/period, default howmany is 100 * period

$prob = round( 1 / $period, 6);
if ( ! $howmany) $howmany = $period * 1000;
if ( $howmany > 1000000) $howmany = $period * 1000;
return Rdist( "rbinom( $howmany, 1, $prob)");
}
function Rdistpoisson( $mean, $howmany = 1000) { return Rdist( "rpois( $howmany, $mean)"); }

function Rdensity( $L, $cleanup = true) { 	// returns { x, y} of density

$R = 'd <- density( c(' . ltt( $L) . '))' . "\n";
$x = Rreadlist( Rscript( $R . 'd$x', null, true, $cleanup));
$y = Rreadlist( Rscript( $R . 'd$y', null, true, $cleanup));
return array( 'x' => $x, 'y' => $y);
}
function Rhist( $L, $breaks = 20, $digits = 3, $cleanup = true) { 	// y value = bin counts

$R = 'd <- hist( c(' . ltt( $L) . "), prob=1, breaks=$breaks)" . "\n";
$y = Rreadlist( Rscript( $R . 'd$counts', null, true, $cleanup));
$step = ( 1 / $breaks) * ( mmax( $L) - mmin( $L));
$x = 0.5 * $step; $h = array();
foreach ( $y as $v) { $h[ '' . round( $x, $digits)] = $v; $x += $step; }
return $h;
}
function mauth( $login, $password, $domain = '', $timeout = 2.0, $bip = null) { // ANAME, SBDIR, MAUTHDPORT

global $BIP, $MAUTHDIR, $ANAME, $SBDIR, $MAUTHDPORT;	// this name should be registered with mauth
if ( ! $bip) $bip = $BIP;
$app = $ANAME;
if ( $domain) $app = $domain;	// allows optionally to set another domain for login
$c = "command=login,domain=$app,login=$login,password=$password";
if ( strlen( $c) > 240) return array( false, 'either login or password are too long');
// get mauthd env
require( "$MAUTHDIR/mauthdport.php");
if ( ! $MAUTHDPORT) return array( false, 'failed to read mauth runtime details');
$info = ntcptxopen( $bip, $MAUTHDPORT);
if ( $info[ 'error']) return array( false, "could not contact mauth deamon");
$sock = $info[ 'sock'];
//echo "mauth sock[$sock]\n";
$status = ntcptxstring( $sock, sprintf( '%250s', $c), $timeout);
//die( "txstring OK\n");
if ( ! $status) { @socket_shutdown( $sock); @sock_close( $sock); return array( false, "could not comm(tx) to mauth deamon"); }
$text = ntcprxstring( $sock, 250, $timeout);
//die( jsonsend( jsonmsg( 'RX ok')));
if ( ! $text) { @socket_shutdown( $sock); @socket_close( $sock); return array( false, "failed to comm(rx) with mauth deamon"); }
//echo "string [$text]\n";
$info = tth( $text); if ( $info[ 'status'] == 'ok') return array( true, '');
return array( false, $info[ 'msg']);
}
function mauthchange( $login, $password, $domain = '', $timeout = 2.0, $bip = null) { // ANAME, SBDIR, MAUTHDPORT

global $BIP, $MAUTHDIR, $ANAME, $SBDIR, $MAUTHDPORT;	// this name should be registered with mauth
if ( ! $bip) $bip = $BIP;
$app = $ANAME;
if ( $domain) $app = $domain;	// allows optionally to set another domain for login
// get mauthd env
require( "$MAUTHDIR/mauthdport.php");
if ( ! $MAUTHDPORT) return array( false, 'failed to read mauth runtime details');
$info = ntcptxopen( $bip, $MAUTHDPORT);
if ( $info[ 'error']) return array( false, "could not contact mauth deamon");
$sock = $info[ 'sock'];
//echo "mauth sock[$sock]\n";
$status = ntcptxstring( $sock, sprintf( '%250s', "command=change,domain=$app,login=$login,password=$password"), $timeout);
if ( ! $status) { @socket_shutdown( $sock); @sock_close( $sock); return array( false, "could not comm(tx) to mauth deamon"); }
$text = ntcprxstring( $sock, 250, $timeout);
if ( ! $text) { @socket_shutdown( $sock); @socket_close( $sock); return array( false, "failed to comm(rx) with mauth deamon"); }
//echo "string [$text]\n";
$info = tth( $text); if ( $info[ 'status'] == 'ok') return array( true, '');
return array( false, $info[ 'msg']);
}
function mauthadd( $login, $password, $domain = '', $timeout = 2.0, $bip = null) { // ANAME, SBDIR, MAUTHDPORT

global $BIP, $MAUTHDIR, $ANAME, $SBDIR, $MAUTHDPORT;	// this name should be registered with mauth
if ( ! $bip) $bip = $BIP;
//die( jsonsend( jsonmsg( "BIP[$BIP]")));
$app = $ANAME;
if ( $domain) $app = $domain;	// allows optionally to set another domain for login
// get mauthd env
require( "$MAUTHDIR/mauthdport.php");
if ( ! $MAUTHDPORT) return array( false, 'failed to read mauth runtime details');
$info = ntcptxopen( $bip, $MAUTHDPORT);
if ( $info[ 'error']) return array( false, "could not contact mauth deamon");
$sock = $info[ 'sock'];
//echo "mauth sock[$sock]\n";
$status = ntcptxstring( $sock, sprintf( '%250s', "command=add,domain=$app,login=$login,password=$password"), $timeout);
if ( ! $status) { @socket_shutdown( $sock); @sock_close( $sock); return array( false, "could not comm(tx) to mauth deamon"); }
$text = ntcprxstring( $sock, 250, $timeout);
if ( ! $text) { @socket_shutdown( $sock); @socket_close( $sock); return array( false, "failed to comm(rx) with mauth deamon"); }
//echo "string [$text]\n";
$info = tth( $text); if ( $info[ 'status'] == 'ok') return array( true, '');
return array( false, $info[ 'msg']);
}
function mfinit( $file, $w, $h, $fill = false, $fillvalue = 0, $readmode = false) {

if ( $readmode) return array( 'file' => $file, 'w' => $w, 'h' => $h);
$out = fopen( $file, "wb");
ftruncate( $out, $h * $w * 4);
if ( $fill) {	// fill with fillvalue
rewind( $out);
for ( $i = 0; $i < $w; $i++) {
for ( $y = 0; $y < $h; $y++) bfilewriteint( $out, $fillvalue);
}
}
fclose( $out);
return array( 'file' => $file, 'w' => $w, 'h' => $h);
}
function mfend( &$mf) {

if ( isset( $mf[ 'in']) && $mf[ 'in']) { fclose( $mf[ 'in']); unset( $mf[ 'in']); }
if ( isset( $mf[ 'out']) && $mf[ 'out']) { fclose( $mf[ 'out']); unset( $mf[ 'out']); }
}
function mfopenread( $file, $w, $h) { return mfinit( $file, $w, $h, false, 0, true); }

function mfopenwrite( $file, $w, $h, $fill = false, $fillvalue = 0) { return mfinit( $file, $w, $h, $fill, $fillvalue); }

function mfclose( &$mf) { return mfend( $mf); } 

function mfgetline( &$mf, $line, $store = true, $keep = true, $seek = true, $debug = false, $debugperiod = 500) {

if ( isset( $mf[ 'in'])) $in = $mf[ 'in'];
else $in = fopen( $mf[ 'file'], 'rb');
if ( $store) $mf[ 'in'] = $in;
if ( $seek) { rewind( $in); fseek( $in, $line * $mf[ 'w'] * 4); }
$out = array();
$debugv = $debug ? $debugperiod : -1;
for ( $i = 0; $i < $mf[ 'w']; $i++) {
$v = bfilereadint( $in);
if ( ! $v) $v = -1;
$debugv--;
if ( ! $debugv) { echo "dec/hex[$v " . bint2hex( $v) . "]\n"; $debugv = $debugperiod; }
if ( $debug) echo '.';
array_push( $out, $v);
}
if ( ! $keep) { fclose( $in); unset( $mf[ 'in']); }
return $out;
}
function mfgethorizontal( &$mf, $line, $poslist, $store = true, $keep = true) {

if ( isset( $mf[ 'in'])) $in = $mf[ 'in'];
else $in = fopen( $mf[ 'file'], 'rb');
if ( $store) $mf[ 'in'] = $in;
//rewind( $in);
$pos = $line * $mf[ 'w'] * 4;
//echo "   POS[$pos = $line * " . $mf[ 'w'] . " * 4]";
fseek( $in, $pos);
//echo "START.POS[" . ftell( $in) . "]"; sleep( 1);
$out = array();
$lastpos = 0;
foreach ( $poslist as $pos) {
$posdiff = $pos - $lastpos;
for ( $i = 0; $i < $posdiff; $i++) bfilereadint( $in);
$v = bfilereadint( $in);
//echo '..' . ftell( $in) . '..';
$out[ $pos] = $v;
$lastpos = $pos + 1;
}
if ( ! $keep) { fclose( $in); unset( $mf[ 'in']); }
return $out;
}
function mfsetline( &$mf, $line, $list, $store = true, $keep = true, $seek = true, $debug = false, $debugperiod = 500) {

if ( isset( $mf[ 'out'])) $out = $mf[ 'out'];
else { $out = fopen( $mf[ 'file'], 'r+b'); }
if ( $store) $mf[ 'out'] = $out;
if ( $seek) { rewind( $out); fseek( $out, $line * $mf[ 'w'] * 4); }
$debugv = $debug ? $debugperiod : -1;
for ( $i = 0; $i < $mf[ 'w']; $i++) {
$v = isset( $list[ $i]) ? round( $list[ $i]) : 0;
if ( $v < 0) $v = bfullint();
$debugv--;
$s = bfilewriteint( $out, $v);
if ( ! $debugv) { echo "dec/string[$v.$s(" . strlen( $s) . ")]"; $debugv = $debugperiod; }
if ( $debug) { echo '.'; }
}
if ( ! $keep) { fclose( $out); unset( $mf[ 'out']); }
}
function mfsetvalue( &$mf, $y, $x, $value, $store = true, $keep = true) {

if ( isset( $mf[ 'out'])) $out = $mf[ 'out'];
else { $out = fopen( $mf[ 'file'], 'r+b'); }
if ( $store) $mf[ 'out'] = $out;
fseek( $out, ( $y * $mf[ 'w'] * 4) + $x * 4);
$v = round( $value);
if ( $v < 0) $v = bfullint();
$s = bfilewriteint( $out, $v);
if ( ! $keep) { fclose( $out); unset( $mf[ 'out']); }
}
function &mf2mx( $mf, $w, $h, $missing = null) { // missing = -1 values in mf file

$mx = mxinit( $h, $w);
$mf = mfinit( $mf, $w, $h, null, null, true); // read mode
for ( $i = 0; $i < $h; $i++) {
$line = mfgetline( $mf, $i);
for ( $y = 0; $y < $w; $y++)
$mx[ $i][ $y] = ( $line[ $y] == -1 ? ( $missing === null ? $line[ $y] : $missing): $line[ $y]);
}
mfend( $mf);
return $mx;
}
function mx2mf( &$mx, $w, $h, $file) {	// write the file

$mf = mfinit( $file, $w, $h);	// write mode
for ( $i = 0; $i < $h; $i++) mfsetline( $mf, $mx[ $i], false, false, true);
mfend( $mf);
}
/** library for working with matrix math, created 2010/03/09
*	Depends on R for major operations, small ones serve by itself
*/
function &mxinit( $rows, $cols, $fill = 0) {

$mx = array();
for ( $row = 0; $row < $rows; $row++) {
$mx[ $row] = array();
for ( $col = 0; $col < $cols; $col++) $mx[ $row][ $col] = $fill;
}
return $mx;
}
function mxinfo( &$mx) { return array( 'rows' => count( $mx), 'cols' => count( $mx[ 0])); }

function mx01( &$mx, $threshold = 0) {	// all values > 0 are 1, 0 otherwise

extract( mxinfo( $mx));
for ( $row = 0; $row < $rows; $row++)
for ( $col = 0; $col < $cols; $col++)
if ( $mx[ $row][ $col] > $threshold) $mx[ $row][ $col] = 1; else $mx[ $row][ $col] = 0;
}
function mxnorm( &$mx, $digits = 2) {

$min = $mx[ 0][ 0]; $max = $min;
for ( $x = 0; $x < count( $mx); $x++) {
for ( $y = 0; $y < count( $mx); $y++) {
if ( $mx[ $x][ $y] < $min) $min = $mx[ $x][ $y];
if ( $mx[ $x][ $y] > $max) $max = $mx[ $x][ $y];
}
}
for ( $x = 0; $x < count( $mx); $x++) {
for ( $y = 0; $y < count( $mx); $y++) {
$v = 0; if ( $min != $max) $v = ( $mx[ $x][ $y] - $min) / ( $max - $min);
$mx[ $x][ $y] = round( $v, $digits);
}
}
}
function &mxsum( &$mx1, &$mx2) { 	// returns a third matrix object

$info1 = mxinfo( $mx1); $info2 = mxinfo( $mx2);
foreach ( $info1 as $k => $v) if ( $info2[ $k] != $v) { echo "ERROR in matrixmath(): mx1 and mx2 have differecent dimensions!\n"; die( ''); }
extract( $info1);
$mx = mxinit( $rows, $cols); for ( $row = 0; $row < $rows; $row++) for ( $col = 0; $col < $cols; $col++) $mx[ $row][ $col] = $mx1[ $row][ $col] + $mx2[ $row][ $col];
return $mx;
}
function &mxproduct( &$mx1, &$mx2) { // this requires R, returns new matrix

$info1 = mxinfo( $mx1); $info2 = mxinfo( $mx2); // row1, col2 are new dimensions
$rows = $info1[ 'rows']; $cols = $info2[ 'cols'];
$rs = mx2r( $mx1) . ' %*% ' . mx2r( $mx2);
$lines = Rscript( $rs, null, false, true);
while ( ! strlen( trim( $lines[ count( $lines) - 1]))) array_pop( $lines);
$nlines = array(); for ( $i = 0; $i < $rows; $i++) $nlines[ $i] = array();
while ( count( $lines) && count( $nlines[ 0]) < $cols) {
for ( $i = $rows - 1; $i >= 0; $i--) {
$line = trim( array_pop( $lines));
if ( strpos( $line, '[') !== 0) { echo "Wrong format of line Rscript output [$line]\n" . "Matrix debug\n"; mxdebug( $mx1); mxdebug( $mx2); die( ''); }
$list = ttl( $line, ' '); array_shift( $list);
while ( count( $list)) array_unshift( $nlines[ $i], array_pop( $list));
}
array_pop( $lines);
}
for ( $row = 0; $row < $rows; $row++)
if ( count( $nlines[ $row]) !== $cols) { echo "Wrong col count on row[$row]\n" . "Matrix debug\n"; mxdebug( $mx1); mxdebug( $mx2); die( ''); }
$mx = mxinit( $rows, $cols);
for ( $row = 0; $row < $rows; $row++)
for ( $col = 0; $col < $cols; $col++)
$mx[ $row][ $col] = $nlines[ $row][ $col];
return $mx;
}
function &mxtranspose( &$mx) {	// returns another matrix

$info = mxinfo( $mx); foreach ( $info as $k => $v) $$k = $v;
$nmx = mxinit( $cols, $rows);
for ( $col = 0; $col < $cols; $col++)
for ( $row = 0; $row < $rows; $row++)
$nmx[ $col][ $row] = $mx[ $row][ $col];
return $nmx;
}
function mx2r( &$mx) {	// returns r notation: matrix( c( ,,,), rows, cols)

extract( mxinfo( $mx)); // cols, rows
$list = array();
for ( $col = 0; $col < $cols; $col++) for ( $row = 0; $row < $rows; $row++) array_push( $list, $mx[ $row][ $col]);
return "matrix( c( " . implode( ',', $list) . "), $rows, $cols)";
}
function list2mx( $VS, $cols) {

$mx = array();
for ( $i = 0; $i < count( $VS) - $cols; $i++) $mx[ $i] = array();
for ( $row = 0; $row < count( $VS) - $cols; $row++) {
for ( $col = 0; $col < $cols; $col++) {
$mx[ $row][ $col] = $VS[ $col + $row];
}
}
return $mx;
}
function mx2csv( &$mx, $file, $mode = 'w') {

$out = fopen( $file, $mode);
$info = mxinfo( $mx); foreach ( $info as $k => $v) $$k = $v;
for ( $row = 0; $row < $rows; $row++) {
$list = array();
for ( $col = 0; $col < $cols; $col++) array_push( $list, $mx[ $row][ $col]);
fwrite( $out, implode( ',', $list) . "\n");
}
fclose( $out);
}
function mxdebug( &$mx, $width = 5, $space = true) {	// formated matrix to stdout

$info = mxinfo( $mx);  foreach ( $info as $k => $v) $$k = $v;
echo "matrix info[" . htt( $info) . "]\n";
for ( $row = 0; $row < $rows; $row++) {
echo '   ';
for ( $col = 0; $col < $cols; $col++) {
$v = $mx[ $row][ $col];
if ( $v == ( int)$v) echo sprintf( ( $space ? ' ' : '') . '%' . $width . 'd', $v);
else echo sprintf( ( $space ? ' ' : '') . '%' . $width . 'f', $v);
}
echo "\n";
}
}
function bstring2bytes( $string, $dir = '') { 	// writes to a temp file

$name = ftempname( '', 'bstring2bytes', $dir);
$out = fopen( $name, 'w'); fwrite( $out, $string); fclose( $out);
$L = array();
$in = fopen( $name, 'r');  while ( $in && ! feof( $in)) lpush( $L, bfilereadbyte( $in));
fclose( $in);
`rm -Rf $name`;
return $L;
}
function breadbyte( $s) {	// returns interger of one byte or null

$v = @unpack( 'Cbyte', $s);
return isset( $v[ 'byte']) ? $v[ 'byte'] : null;
}
function breadbytes( $s, $count = 4) { 	// returns list of bytes, up to four -- if more, do integers or split smaller

$ks = ttl( 'one,two,three,four');
$def = ''; for ( $i = 0; $i < $count; $i++) $def .= 'C' . $ks[ $i];
$v = @unpack( $def, $s); if ( ! $v || ! is_array( $v)) return null;
return hv( $v);	// return list of values
}
function breadint( $s) { $v = @unpack( 'Inumber', $s); return isset( $v[ 'number']) ? $v[ 'number'] : null; }

function bwritebytes( $one, $two = null, $three = null, $four = null, $five = null, $six = null) {

if ( is_array( $one)) {	// extract one,two,three,.... from array of one
$L = ttl( 'one,two,three,four,five,six'); while ( count( $L) > count( $one)) lpop( $L);
$h = array(); for ( $i = 0; $i < count( $L); $i++) $h[ $L[ $i]] = $one[ $i];
extract( $h);
}
if ( $two === null) return pack( "C", $one);
if ( $three === null) return pack( "CC", $one, $two);
if ( $four === null) return pack( "CCC", $one, $two, $three);
if ( $five === null) return pack( "CCCC", $one, $two, $three, $four);
if ( $six === null) return pack( "CCCCC", $one, $two, $three, $four, $five);
return pack( "CCCCCC", $one, $two, $three, $four, $five, $six);
}
function bwriteint( $n) { return pack( 'I', $n); } 	// back 4 bytes of integer into a binary string (also UTF-32)

function bintro( $n) { 	// binary reverse byte order of integer

return bmask( btail( $n >> 24, 8), 24, 8) + bmask( btail( $n >> 16, 8) << 8, 16, 8) + bmask( btail( $n >> 8, 8) << 16, 8, 8) + bmask( btail( $n, 8) << 24, 0, 8);
}
function bjamwrite( $out, $h, $donotwriteheader = false) { 	// write values from this hash (array is a kind of hash), returns header bytes

foreach ( $h as $k => $v) if ( is_numeric( $v)) $h[ $k] = ( int)$v;	// make sure all numbers are round\
$header = bjamheaderwrite( $out, $h, $donotwriteheader);
//die( '   header:' . json_encode( $header));
$count = btail( $header[ 0] >> 5, 3); $bs = bjamheader2bitstring( $header); $vs = hv( $h);
//die( "  bs[$bs]\n");
for ( $i = 0; $i < $count; $i++) {
$code = bjamstr2code( substr( $bs, 3 + 3 * $i, 3));
$count2 = $code - 4 + 1; if ( $count2 < 0) $count2 = 0;
for ( $ii = $count2 - 1; $ii >= 0; $ii--) bfilewritebyte( $out, btail( $vs[ $i] >> ( 8 * $ii), 8));	// if count2 = 0 (NULL), nothing is written
}
return $header;
}
function bjamread( $in, $header = null) { 	// read one set (with header) from the file, return list of values

if ( ! $header) $header = bjamheaderead( $in);
//die( " header[" . json_encode( $header) . "]\n");
$count = btail( $header[ 0] >> 5, 3); $bs = bjamheader2bitstring( $header); $vs = array();
//echo " count[$count] bs[$bs]";
for ( $i = 0; $i < $count; $i++) {
$code = bjamstr2code( substr( $bs, 3 + 3 * $i, 3));
if ( $code == 0) { lpush( $vs, null); continue; } // no actual data, deduct from flags
if ( $code == 3) { lpush( $vs, true); continue; }
$count2 = $code - 4 + 1; if ( $count2 < 0) $count2 = 0; $v = array();
for ( $ii = 0; $ii < $count2; $ii++) lpush( $v, bfilereadbyte( $in));
while ( count( $v) < 4) lunshift( $v, 0);
$v = bhead( $v[ 0] << 24, 8) | bmask( $v[ 1] << 16, 8, 8) | bmask( $v[ 2] << 8, 16, 8) | btail( $v[ 3], 8);
lpush( $vs, $v);
//echo "   code[$code] v[$v]";
}
//die( "\n");
return $vs;
}
function bjamheaderwrite( $out, $h, $donotwrite = false) { // returns [ byte1, byte2, byte3, ...] as many bytes as needed	

$ks = hk( $h); while ( count( $ks) > 7) lpop( $ks);
$hs = bbitstring( count( $ks), 3);
foreach ( $ks as $k) $hs .= bbitstring( bjamint2code( $h[ $k]), 3);
//die( "   h[" . json_encode( $h) . "] hs[$hs]\n");
$bytes = array();
for ( $i = 0; $i < strlen( $hs); $i += 8) {
$byte = array(); for ( $ii = 0; $ii < 8; $ii++) lpush( $byte, ( $i + $ii < strlen( $hs)) ? ( substr( $hs, $i + $ii, 1) == '0' ? 0 : 1) : 0);
lpush( $bytes, bwarray2byte( $byte));
}
if ( $donotwrite) return $bytes;	// return header bytes without writing to file
foreach ( $bytes as $byte) bfilewritebyte( $out, $byte);
return $bytes;
}
function bjamheaderead( $in) { 	// returns [ byte1, byte2, byte3, ...]

$bytes = array( bfilereadbyte( $in));	// first byte
$count = btail( $bytes[ 0] >> 5, 3);	// count of items
$bitcount = 3 + 3 * $count;
$bytecount = $bitcount / 8; if ( $bytecount > ( int)$bytecount) $bytecount = 1 + ( int)$bytecount;
$bytecount = round( $bytecount);	// make it round just in case
for ( $i = 1; $i < $bytecount; $i++) lpush( $bytes, bfilereadbyte( $in));
return $bytes;
}
function bjamheader2bitstring( $bytes) { // returns '01011...' bitstring of the header, some bits at the end may be unused

$bs = '';
foreach ( $bytes as $byte) { $byte = bwbyte2array( $byte); foreach ( $byte as $bit) $bs .= $bit ? '1' : '0'; }
return $bs;
}
function bjamint2code( $v) { // returns 3-bit binary code for this (int) value

if ( $v === null || $v === false) return 0;	// 000
if ( $v === true) return 3;	// 011
$count = 1;
if ( btail( $v >> 8, 8)) $count = 2;
if ( btail( $v >> 16, 8)) $count = 3;
if ( btail( $v >> 24, 8)) $count = 4;
return 4 + ( $count - 1);  // between 100 and 111
}
function bjamstr2code( $s) { // converts 3-char string into a code

$byte = array(); for ( $i = 0; $i < 5; $i++) lpush( $byte, 0);
for ( $i = 0; $i < 3; $i++) lpush( $byte, substr( $s, $i, 1) == '0' ? 0 : 1);
return bwarray2byte( $byte);
}
function bjamcode2count( $code) { return $code >= 4 ? $code - 4 + 1 : 0; }

function bjamcount2code( $count) { return $count > 0 ? 4 + $count - 1 : 0; } 

function bfilereadint( $in) {

$s = fread( $in, 4);
return breadint( $s);
}
function bfilewriteint( $out, $v) {

$s = pack( "I", $v);
fwrite( $out, $s);
return $s;
}
function bfilereadbyte( $in) {	// return interger 

$s = fread( $in, 1);
return breadbyte( $s);
}
function bfilewritebyte( $out, $v) {

fwrite( $out, bwritebytes( $v));
}
function boptfilereadint( $in, $flags = null) { // return integer, if $flags = null, read byte with flags first

if ( $flags === null) $flags = bwbyte2array( bfilereadbyte( $in), true);	// as numbers
$count = 0;
if ( is_array( $flags)) for ( $i = 0; $i < count( $flags); $i++) $flags[ $i] = $flags[ $i] ? 1 : 0; // make sure those are numbers, not boolean values
if ( is_array( $flags) && count( $flags) > 2 && $flags[ 0] && $flags[ 1] && $flags[ 2]) $count = 4;
else if ( is_array( $flags)) $count = $flags[ 0] * 2 + $flags[ 1];
else $count = $flags;	// number of bytes to read can be passed as integer
$v = 0;
if ( $count > 0) $v = bfilereadbyte( $in);
if ( $count > 1) $v = bmask( bfilereadbyte( $in) << 8, 16, 8) | $v;
if ( $count > 2) $v = bmask( bfilereadbyte( $in) << 16, 8, 8) | $v;
if ( $count > 3) $v = bmask( bfilereadbyte( $in) << 24, 0, 8) | $v;
return $v;
}
function boptfilewriteint( $out, $v, $writeflags = true, $donotwrite = false, $count = null, $maxcount = 4) { // if writeflags=false, will return flags and will not write them

$flags = array();
// set flags first
$flags = array( false, false);
if ( ! $count) {	// calculate the count
$count = 0;
if ( btail( $v, 8) && $maxcount > 0) { $flags = array( false, true); $count = 1; }
if ( btail( $v >> 8, 8) && $maxcount > 1) { $flags = array( true, false); $count = 2; }
if ( btail( $v >> 16, 8) && $maxcount > 2) { $flags = array( true, true); $count = 3; }
if ( btail( $v >> 24, 8) && $maxcount > 3) { $flags = array( true, true, true); $count = 4; }
}
while ( count( $flags) < 8) lpush( $flags, false);	// fillter
if ( $donotwrite) return $flags;	// do not do the actual writing
if ( $writeflags) bfilewritebyte( $out, bwarray2byte( $flags));
// now write bytes of the number, do not write anything if zero size
if ( $count > 0) bfilewritebyte( $out, btail( $v, 8));
if ( $count > 1) bfilewritebyte( $out, btail( $v >> 8, 8));
if ( $count > 2) bfilewritebyte( $out, btail( $v >> 16, 8));
if ( $count > 3) bfilewritebyte( $out, btail( $v >> 24, 8));
return $flags;
}
function bwbyte2array( $v, $asnumbers = false) { // returns array of flags

$L = array();
for ( $i = 0; $i < 8; $i++) {
lunshift( $L, ( $v & 0x01) ? ( $asnumbers ? 1 : true) : ( $asnumbers ? 0 : false));
$v = $v >> 1;
}
return $L;
}
function bwarray2byte( $flags) { // returns number representing the flags

$number = 0;
while ( count( $flags)) {
$number = $number << 1;
$flag = lshift( $flags);
if ( $flag) $number = $number | 0x01;
else $number = $number | 0x00;
}
return $number;
}
function bfullint() { return ( 0xFF << 24) + ( 0xFF << 16) + ( 0xFF << 8) + 0xFF; }

function bemptyint() { return ( 0x00 << 24) + ( 0x00 << 16) + ( 0x00 << 8) + 0x00; }

function b01( $pos, $length) { // return int where bit string has $length bits starting from pos

$v = 0x01;
for ( $i = 0; $i < $length - 1; $i++) $v = ( $v << 1) | 0x01;
for ( $i = 0; $i < ( 32 - $pos - $length); $i++) $v = ( ( $v << 1) | 0x01) ^ 0x01; // sometimes << bit shift in PHP results in 1 at the tail, this weird notation will work with or without this bug
return $v;
}
function bmask( $v, $pos, $length) { // returns value where only $length bits from $pos are left, and the rest are zero

$mask = b01( $pos, $length);
return $v & $mask;
}
function bhead( $v, $bits) { return bmask( $v, 0, $bits); }

function btail( $v, $bits) { return bmask( $v, 32 - $bits, $bits); }

function bbitstring( $number, $length = 32, $separatelength = 0) { 	// from end

$out = ''; $separator = $separatelength;
for ( $i = 0; $i < $length; $i++) {
$number2 = $number & 0x01;
if ( $number2) $out = "1$out";
else $out = "0$out";
$separator--; if ( $separator == 0 && $i < $length - 1) { $out = ".$out"; $separator = $separatelength; }
$number = $number >> 1;
}
return $out;
}
function bint2hex( $number) { return sprintf( "%X", $number); } // only integer types 

function bint2bytestring( $number) { 	// returns string containing byte sequence from integer (from head to tail bits)

return bwritebytes( bmask( $number >> 24, 24, 8), bmask( $number >> 16, 24, 8), bmask( $number >> 8, 24, 8), bmask( $number, 24, 8));
}
function bbytestring2int( $s) {

$v = @unpack( 'Cone/Ctwo/Cthree/Cfour', $s);
extract( $v);
return bmask( $one << 24, 0, 8) | bmask( $two << 16, 8, 8) | bmask( $three << 8, 16, 8) | bmask( $four, 24, 8);
}
function bint2bytelist( $number, $count = 4) { $L = array(); for ( $i = 0; $i < $count; $i++) lunshift( $L, btail( $number >> ( 8 * $i), 8)); return $L; }

/** packets: specific binary format for writing packet trace information compactly  2012/03/31 moved to fin/fout calls
* the main idea: use boptfile but collect and store all flag bits separately (do not allow boptfile read/write bits from file)
* flags are collected into 2 first bytes in the following structure:
*   BYTE 0: (1) protocol, (7) length of the record
*   BYTE 1: (2) pspace, (2) sport, (2) dport, (2) psize
*  *** sip and dip are written in fixed 4 bytes and do not require flags
*/
function bpacketsinit( $filename) { return fopen( $filename, 'w'); } // noththing to do, just open the new file

function bpacketsopen( $filename) { return fopen( $filename, 'r'); } // binary safe

function bpacketsclose( $handle) { fclose( $handle); }

function bpacketswrite( $out, $h) { // h { pspace, sip, sport, dip, dport, psize, protocol}

$L = ttl( 'pspace,sip,sport,dip,dport,psize'); foreach ( $L as $k) $h[ $k] = ( int)$h[ $k]; // force values to integers
extract( $h);
$flags = array( 0, 0);
$flags[ 0] = $protocol == 'udp' ? 0x00 : bmask( 0xff, 24, 1);
// first, do the flag run
$size = 4;
$f = boptfilewriteint( null, $pspace, true, true, null, 3); // pspace  (max 3 bytes = 2 flag bits)
$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | $v;
$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
$size += 4;	// sip
$f = boptfilewriteint( null, $sport, true, true, null, 3); // sport
$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 2);
$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
$size += 4;	// dip
$f = boptfilewriteint( null, $dport, true, true, null, 3); // dport
$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 4);
$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
$f = boptfilewriteint( null, $psize, true, true, null, 3); // psize
$v = bwarray2byte( $f); $flags[ 1] = $flags[ 1] | ( $v >> 6);
$size += ( $f[ 0] ? 2 : 0) + ( $f[ 1] ? 1 : 0);
// remember the length of the line
$flags[ 0] = $flags[ 0] | $size;
// now, write the actual data
bfilewritebyte( $out, $flags[ 0]);
bfilewritebyte( $out, $flags[ 1]);
boptfilewriteint( $out, $pspace, false, false, null, 3); // pspace
boptfilewriteint( $out, $sip, false, false, 4); // sip
boptfilewriteint( $out, $sport, false, false, null, 3); // sport
boptfilewriteint( $out, $dip, false, false, 4); // dip
boptfilewriteint( $out, $dport, false, false, null, 3); // dport
boptfilewriteint( $out, $psize, false, false, null, 3); // psize
}
function bpacketsread( $in) { // returns { pspace, sip, sport, dip, dport, psize, protocol}

if ( ! $in || feof( $in)) return null; // no data
$v = bfilereadbyte( $in); $f = bwbyte2array( $v, true);
$protocol = $f[ 0] ? 'tcp' : 'udp';	// protocol
$f[ 0] = 0;
$linelength = bwarray2byte( $f);	// line length
if ( ! $linelength) return null;	// no data
$h = array();
$h[ 'protocol'] = $protocol;
$v = bfilereadbyte( $in); $f = bwbyte2array( $v, true);
$h[ 'pspace'] = boptfilereadint( $in, array( $f[ 0], $f[ 1], 0, 0, 0, 0, 0, 0));
$h[ 'sip'] = boptfilereadint( $in, array( 1, 1, 1, 0, 0, 0, 0, 0));
$h[ 'sport'] = boptfilereadint( $in, array( $f[ 2], $f[ 3], 0, 0, 0, 0, 0));
$h[ 'dip'] = boptfilereadint( $in, array( 1, 1, 1, 0, 0, 0, 0, 0));
$h[ 'dport'] = boptfilereadint( $in, array( $f[ 4], $f[ 5], 0, 0, 0, 0, 0, 0));
$h[ 'psize'] = boptfilereadint( $in, array( $f[ 6], $f[ 7], 0, 0, 0, 0, 0, 0));
return $h;
}
/** flows: specific binary format for storing binary information about packet flows
* main idea: to use boptfile* optimizers but without writing flags with information, instead, flags are aggregated into structure below
*  BYTE 0: (1) protocol  (2) sport, (2) dport, (3) bytes
*  BYTE 1: (1) startimeus invert (if 1, 1000000 - value) (3) length of startimeus (1) durationus invert (3) length of durationus   000 means no value = BYTE 2 flags not set == value not written into file
*  BYTE 2: (2) packets, (2) startimeus (optional) (2) duration(s) (2) duration(us) (optional)  -- optionals depend on lengths in BYTE1
*  ** sip, dip, and startime(s) are written in 4 bytes and do not require flags (not compressed)
*/
function bflowsinit( $timeoutms, $filename) { // create new file, write timeout(ms) as first 2 bytes (65s max)s, return file handle

$out = fopen( $filename, 'w');
$timeout = ( int)$timeoutms;	// should not be biggeer than 65565s
bfilewritebyte( $out, btail( $timeout >> 8, 8));
bfilewritebyte( $out, btail( $timeout, 8));
return $out;
}
function bflowsopen( $filename) { 	// returns [ handler, timeout (ms)]

$in = fopen( $filename, 'r');
$timeout = bmask( bfilereadbyte( $in) << 8, 16, 8) + bfilereadbyte( $in);
return array( $in, $timeout);
}
function bflowsclose( $handle) { fclose( $handle); }

function bflowswrite( $out, $h, $debug = false) { // needs { sip, sport, dip, dport, bytes, packets, startime, lastime, protocol}

extract( $h); if ( ! isset( $protocol)) $protocol = 'tcp';
if ( $debug) echo "\n";
$flags = array( 0, 0, 0);	// flags
$flags[ 0] = $protocol == 'udp' ? 0x00 : bmask( 0xff, 24, 1);
$startime = round( $startime, 6);	// not more than 6 digits
$startimes = ( int)$startime;	// startimes
$startimeus = round( 1000000 * ( $startime - ( int)$startime)); if ( $startimeus > 999999) $startimeus = 999999;
while ( strlen( "$startimeus") < 6) $startimeus = "0$startimeus";
while ( strlen( "$startimeus") && substr( "$startimeus", strlen( $startimeus) - 1, 1) == '0') $startimeus = substr( $startimeus, 0, strlen( $startimeus) - 1);
$duration = round( $lastime - $startime, 6);
$durations = ( int)$duration; 	// durations
$durationus = round( 1000000 * ( $duration - ( int)$duration)); if ( $durationus > 999999) $durationus = 999999;
while ( strlen( "$durationus") < 6) $durationus = "0$durationus";
while ( strlen( "$durationus") && substr( "$durationus", strlen( $durationus) - 1, 1) == '0') $durationus = substr( $durationus, 0, strlen( $durationus) - 1);
if ( $debug) echo "bflowswrite() : setup : startimes[$startimes] startimeus[$startimeus]   durations[$durations] durationus[$durationus]\n";
// first, do the flag run
$f = boptfilewriteint( null, $sport, true, true, null, 3); // sport  (max 3 bytes = 2 flag bits)
$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 1);
$f = boptfilewriteint( null, $dport, true, true, null, 3); // dport
$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 3);
$f = boptfilewriteint( null, $bytes, true, true); // bytes -- this one can actually be 4 bytes = 3 flag bits
$v = bwarray2byte( $f); $flags[ 0] = $flags[ 0] | ( $v >> 5);
$f = boptfilewriteint( null, $packets, true, true, null, 3); // packets
$v = bwarray2byte( $f); $flags[ 2] = $flags[ 2] | $v;
if ( $debug) echo "bflowswrite() : startimeus : ";
$startimeus2 = null; if ( strlen( $startimeus)) {	// store us of startime (check which one is shorter)
$v = null; $v1 = ( int)$startimeus; $v2 = ( int)( 999999 - $v1);
if ( $debug) echo " v1[$v1] v2[$v2]";
if ( strlen( "$v1") <= strlen( "$v2")) $v = $v1;	// v1 is shorter, do not invert
else { $flags[ 1] = $flags[ 1] | bmask( 0xff, 24, 1); $v = $v2; }
$flags[ 1] = $flags[ 1] | bmask( strlen( $startimeus) << 4, 25, 3); // read length of value
if ( $debug) echo " v.before.write[$v]";
$f = boptfilewriteint( null, $v, true, true, null, 3); $flags[ 2] = $flags[ 2] | ( bwarray2byte( $f) >> 2);
$startimeus2 = $v;
if ( $debug) echo "  f[" . bbitstring( bwarray2byte( $f), 8) . "]   flags1[" . bbitstring( $flags[ 1], 8) . "] flags2[" . bbitstring( $flags[ 2], 8) . "]\n";
}
$f = boptfilewriteint( null, $durations, true, true, null, 3); // durations
$v = bwarray2byte( $f); $flags[ 2] = $flags[ 2] | ( $v >> 4);
$durationus2 = null; if ( strlen( $durationus)) {	// store duration
$v = null; $v1 = ( int)$durationus; $v2 = ( int)( 999999 - $v1);
if ( strlen( "$v1") <= strlen( "$v2")) $v = $v1;	// v1 is shorter, do not invert
else { $flags[ 1] = $flags[ 1] | ( bmask( 0xff, 24, 1) >> 4); $v = $v2; }
$flags[ 1] = $flags[ 1] | btail( strlen( $durationus), 3);
$f = boptfilewriteint( null, $v, true, true, null, 3); $flags[ 2] = $flags[ 2] | ( bwarray2byte( $f) >> 6);
$durationus2 = $v;
if ( $debug) echo "bflowswrite() : durationus : v1[$v1] v2[$v2] v[$v]   flags1[" . bbitstring( $flags[ 1], 8) . "] flags2[" . bbitstring( $flags[ 2], 8) . "]\n";
}
// now, write the actual data
bfilewritebyte( $out, $flags[ 0]);
bfilewritebyte( $out, $flags[ 1]);
bfilewritebyte( $out, $flags[ 2]);
if ( $debug) echo "bflowswrite() : flags : b1[" . bbitstring( $flags[ 0], 8) . "] b2[" . bbitstring( $flags[ 1], 8) . "] b3[" . bbitstring( $flags[ 2], 8) . "]\n";
boptfilewriteint( $out, $sip, false, false, 4);
boptfilewriteint( $out, $sport, false, false, null, 3);
boptfilewriteint( $out, $dip, false, false, 4);
boptfilewriteint( $out, $dport, false, false, null, 3);
boptfilewriteint( $out, $bytes, false);	// do not limit, allow 4 bytes of data
boptfilewriteint( $out, $packets, false, false, null, 3);
boptfilewriteint( $out, $startimes, false, false, 4);
if ( strlen( $startimeus)) boptfilewriteint( $out, $startimeus2, false, false, null, 3); // only if this is a none-zero string
boptfilewriteint( $out, $durations, false, false, null, 3);
if ( strlen( $durationus)) boptfilewriteint( $out, $durationus2, false, false, null, 3);
}
function bflowsread( $in, $debug = false) { // returns { sip,sport,dip,dport,bytes,packets,startime,lastime,protocol,duration}

if ( $debug) echo "\n\n";
if ( ! $in || feof( $in)) return null; // no data
$b1 = bfilereadbyte( $in); $f1 = bwbyte2array( $b1, true); // first byte of flags
$b2 = bfilereadbyte( $in); $f2 = bwbyte2array( $b2, true);	// second byte of flags
$b3 = bfilereadbyte( $in); $f3 = bwbyte2array( $b3, true);	// third byte of flags
if ( $debug) echo "bflowsread() : setup :   B1 " . bbitstring( $b1, 8) . "   B2 " . bbitstring( $b2, 8) . "   B3 " . bbitstring( $b3, 8) . "\n";
$h = tth( 'sip=?,sport=?,dip=?,dport=?,bytes=?,packets=?,startime=?,lastime=?,protocol=?,duration=?');	// empty at first
$h[ 'protocol'] = btail( $b1 >> 7, 1) ? 'tcp': 'udp';
$h[ 'sip'] = boptfilereadint( $in, 4);
$h[ 'sport'] = boptfilereadint( $in, btail( $b1 >> 5, 2));
$h[ 'dip'] = boptfilereadint( $in, 4);
$h[ 'dport'] = boptfilereadint( $in, btail( $b1 >> 3, 2));
$h[ 'bytes'] = boptfilereadint( $in, bwbyte2array( $b1 << 5));
$h[ 'packets'] = boptfilereadint( $in, btail( $b3 >> 6, 2));
// startime -- complex parsing logic
if ( $debug) echo "bflowsread() : startime : ";
$v = boptfilereadint( $in, 4); $v2 = btail( $b2 >> 4, 4); $v3 = '';
if ( $debug) echo " v2[$v2]";
if ( $v2) { // parse stuff after decimal point
$v3 = boptfilereadint( $in, btail( $b3 >> 4, 2));
if ( $debug) echo " v3[$v3]";
if ( btail( $v2 >> 3, 1)) $v3 = 999999 - $v3; // invert
if ( $debug) echo " v3[$v3]";
$v2 = btail( $v2, 3);
if ( $debug) echo " v2[$v2]";
while ( strlen( "$v3") < $v2) $v3 = "0$v3";
if ( $debug) echo " v3[$v3]";
}
if ( $debug) echo "   b2[" . bbitstring( $b2, 8) . "] b3[" . bbitstring( $b3, 8) . "]\n";
$h[ 'startime'] = ( double)( $v . ( $v3 ? ".$v3" : ''));
// duration us -- complex logic
if ( $debug) echo "bflowsread() : duration : ";
$v = boptfilereadint( $in, btail( $b3 >> 2, 2)); $v2 = btail( $b2, 4); $v3 = '';
if ( $debug) echo " v[$v] v2[$v2] v3[$v3]";
if ( $v2) { // parse stuff after decimal point
$v3 = boptfilereadint( $in, btail( $b3, 2));
if ( $debug) echo " v3[$v3]";
if ( btail( $v2 >> 3, 1)) $v3 = 999999 - $v3; // invert
if ( $debug) echo " v3[$v3]";
$v2 = btail( $v2, 3); while ( strlen( "$v3") < $v2) $v3 = "0$v3";
if ( $debug) echo " v3[$v3]";
}
if ( $debug) echo " v3[$v3]\n";
$h[ 'duration'] = ( double)( $v . ( $v3 ? ".$v3" : ''));
$h[ 'lastime'] = $h[ 'startime'] + $h[ 'duration'];
if ( $debug) echo "bflowsread() : finals : duration[" . $h[ 'duration'] . "] lastime[" . $h[ 'lastime'] . "]\n";
return $h;
}
function curlold( $url) {

$hs = array(
'Accept: text/html, text/plain, image/gif, image/x-bitmap, image/jpeg, image/pjpeg',
'Connection: Keep-Alive',
'Content-type: application/x-www-form-urlencoded;charset=UTF-8'
);
//$ua = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';
$c = curl_init( $url);
curl_setopt( $c, CURLOPT_HTTPHEADER, $hs);
curl_setopt( $c, CURLOPT_HEADER, 0);
curl_setopt( $c, CURLOPT_USERAGENT, $ua);
curl_setopt( $c, CURLOPT_TIMEOUT, 5);
curl_setopt( $c, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec( $c);
$limit = 5;
while ( ! $body && $limit--) {
usleep( 100000);
$body = @curl_exec( $c);
}
if ( $body === false) $body = '';
return trim( $body);
}
function curlsmart( $url) {

global $BDIR;
list( $status, $body) = mfetchWget( $url);
//die( $body);
//system( 'wget -UFirefox -O ' . $BDIR . '/temp.html "' . $url . '" > ' . $BDIR . '/temp.txt 2>&1 3>&1');
//`/bin/bash /Users/platypus/test.sh`;
//die( '');
//$body = '';
//$in = fopen( "$BDIR/temp.html", 'r'); while ( $in && ! feof( $in)) $body .= fgets( $in); fclose( $in);
return trim( $body);
}
function curlplain( $url) {

$in = @popen( 'curl "' . $url . '"');
$body = '';
while ( $in && ! feof( $in)) $body .= fgets( $in);
@pclose( $in);
}
function wgetplain( $url, $file = 'temp', $log = 'log') {

system( "wget -UFirefox " . '"' . $url . '"' . " -O $file -o $log");
$body = '';
$in = @fopen( $file, 'r');
while ( $in && ! feof( $in)) $body .= fgets( $in);
@fclose( $in);
return $body;
}
function curlcleanup( $body, $bu, &$info) {

$bads = array(
'<script' => '<scriipt',
'</script' => '</scriipt',
'onload' => 'onloadd',
'onerror' => 'onerrror',
'document.' => 'documennt.',
'window.' => 'winddow.',
'.location' => '.loccation',
'<style' => '<sstyle',
'</style' => '</sstyle',
'<link' => '<llink',
'<object' => '<obbject',
'</object' => '</obbject',
'<embed' => '<embbed',
'</embed' => '</embbed',
'.js' => '.jjs',
'setTimeout(' => 'sedTimeout(',
'@import' => 'impport',
'url(' => 'yurl(',
'codebase' => 'ccodebase',
'http://counter.rambler.ru/' => ''
);
foreach ( $bads as $bad => $good) {
$body = str_replace( $bad, $good, $body);
$body = str_replace( strtoupper( $bad), $good, $body);
}
//$body = aggnCurlRidScript( $body, $info);
//$body = aggnCurlChangeUrl( $body, $bu, $info);
$info[ 'body'] = $body;
}
function mfetchWget( $url, $proctag, $timeout = 5, $minsize = 200) {

global $BDIR;
if ( strlen( $url) > 700) return array( false, 'URL too long');
`rm $BDIR/temp.html`;
$c = 'wget -UFirefox -O ' . $BDIR . '/temp.html "' . $url . '" > ' . $BDIR . '/temp.txt 2>&1 3>&1';
//echo "mfetchWget()  c[$c]\n";
list( $status, $msg, $span) = mfetch( $c, "$BDIR/temp.html", $timeout);
//echo "mfetchWget()  status[$status] msg[$msg] span[$span]\n";
if ( ! $span) $span = -1;	// error time
$size = filesize( "$BDIR/temp.html");
if ( $size < $minsize) return array( false, 'mfetch feedback is too small, giving up');
// parse temp.html
$body = ''; $in = fopen( "$BDIR/temp.html", 'r'); while ( $in && ! feof( $in)) $body .= fgets( $in); fclose( $in);
return array( true, $body, $span);
}
function mfetch( $command, $proctag = '', $wait = 0, $appdir = null, $pidfile = null, $timeout = 5, $MFETCHPORT = null) {

global $BIP, $BDIR, $MFETCHDIR;
// get mauthd env
if ( ! $MFETCHPORT) require_once( "$MFETCHDIR/mfetchport.php");
if ( ! $MFETCHPORT) return array( false, 'failed to read the port of mfetch deamon');
//echo "mfetch()  MFETCHPORT[$MFETCHPORT]\n";
$json = array();
$json[ 'command'] = $command;
$json[ 'proctag'] = $proctag;
$json[ 'wait'] = $wait;
if ( $appdir) $json[ 'appdir'] = $appdir;
if ( $pidfile) $json[ 'pidfile'] = $pidfile;
$buf = sprintf( "%1000s", h2json( $json, true));
//echo "mfetch()  command buf[$buf]\n";
if ( strlen( $buf) > 1000) return array( false, 'command is too long for mfetch');
$info = ntcptxopen( $BIP, $MFETCHPORT);
//echo "mfetch()  ntcptxopen.info[" . htt( $info) . "]\n";
if ( $info[ 'error']) return array( false, 'failed during comm(tx) to mfetch');
$sock = $info[ 'sock'];
//echo "mauth sock[$sock]\n";
$status = ntcptxstring( $sock, $buf, $timeout);
if ( ! $status) { @socket_shutdown( $sock); @sock_close( $sock); return array( false, 'failed during comm(rx) with mfetch'); }
$text = ntcprxstring( $sock, 150, $timeout + 1);
//echo "TEXT[" . base64_decode( $text) . "]\n";
if ( ! $text) { @socket_shutdown( $sock); @socket_close( $sock); return array( false, 'failed reading mfetch feedback'); }
$info = json2h( $text, true); if ( $info[ 'status']) return array( true, '', isset( $info[ 'time']) ? $info[ 'time']: null);
return array( false, 'failed to complete mfetch transaction', isset( $info[ 'time']) ? $info[ 'time'] : null);
}
class NTCPClient { 

public $id;
public $sock;
public $lastime;
public $inbuffer = '';
public $outbuffer = '';
public $buffersize;
// hidden functions -- not part of the interface
public function __construct() { }
public function init( $rip = null, $rport = null, $id = null, $sock = null, $buffersize = 2048) {
$this->id = $id ? $id : uniqid();
if ( $sock) $this->sock = $sock;
else { 	// create new socket
$sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP) or die( "ERROR (NTCPClient): could not create a new socket.\n");
@socket_set_nonblock( $sock); $status = false;
$limit = 5; while ( $limit--) {
$status = @socket_connect( $sock, $rip, $rport);
if ( $status || socket_last_error() == SOCKET_EINPROGRESS) break;
usleep( 10000);
}
if ( ! $status && socket_last_error() != SOCKET_EINPROGRESS) die( "ERROR (NTCPServer): could not connect to the new socket.\n");
$this->sock = $sock;
}
$this->lastime = tsystem();
$this->buffersize = $buffersize;
}
public function recv() {
$buffer = '';
$status = @socket_recv( $this->sock, $buffer, $this->buffersize, 0);
//echo "buffer($buffer)\n";
if ( $status <= 0) return null;
$this->inbuffer .= substr( $buffer, 0, $status);
return $this->parse();
}
public function parse() {
$B =& $this->inbuffer;
//echo "B:$B\n";
if ( strpos( $B, 'FFFFF') !== 0) return;
$count = '';
for ( $pos = 5; $pos < 25 && ( $pos + 5 < strlen( $B)); $pos++) {
if ( substr( $B, $pos, 5) == 'FFFFF') { $count = substr( $B, 5, $pos - 5); break; }
}
if ( ! strlen( $count)) return;	// nothing to parse yet
if ( strlen( $B) < 5 * 2 + strlen( $count) + $count) return null;	// the data has not been collected yet
$h = json2h( substr( $B, 5 * 2 + strlen( $count), $count), true, null, true);
if ( strlen( $B) == 5 * 2 + strlen( $count) + $count) $B = '';
$B = substr( $B, 5 * 2 + strlen( $count) + $count);
return $h;
}
public function send( $h = null, $persist = false) { 	// will send bz64json( msg)
$B =& $this->outbuffer;
//echo "send: $B\n";
if ( $h !== null && is_string( $h)) $h = tth( $h);
if ( $h !== null) { $B = h2json( $h, true, null, null, true); $B = 'FFFFF' . strlen( $B) . 'FFFFF' . $B; }
$status = @socket_write( $this->sock, $B, strlen( $B) > $this->buffersize ? $buffersize : strlen( $B));
$B = substr( $B, $status);
if ( $B && $persist) return $this->send( null, true);
return $status;
}
public function isempty() { return $this->outbuffer ? false : true; }
public function close() { @socket_close( $this->sock); }
}
class NTCPServer { 

public $port;
public $sock;
public $socks = array();
public $clients = array();
public $buffersize = 2048;
public $nonblock = true;
public $usleep = 10;
public $timeout;
public $clientclass;
public function __construct() {}
public function start( $port, $nonblock = false, $usleep = 0, $timeout = 300, $clientclass = 'NTCPClient') {
$this->port = $port;
$this->nonblock = $nonblock;
$this->clientclass = $clientclass;
$this->usleep = $usleep;
$this->timeout = $timeout;
$this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP)  or die( "ERROR (NTCPServer): failed to creater new socket.\n");
socket_set_option( $this->sock, SOL_SOCKET, SO_REUSEADDR, 1) or die( "ERROR (NTCPServer): socket_setopt() filed!\n");
if ( $nonblock) socket_set_nonblock( $this->sock);
$status = false; $limit = 5;
while ( $limit--) {
$status = @socket_bind( $this->sock, '0.0.0.0', $port);
if ( $status) break;
usleep( 10000);
}
if ( ! $status) die( "ERROR (NTCPServer): cound not bind the socket.\n");
socket_listen( $this->sock, 20) or die( "ERROR (NTCPServer): could not start listening to the socket.\n");
$this->socks = array( $this->sock);
while ( 1) { if ( $this->timetoquit()) break; foreach ( $this->socks as $sock) {
if ( $sock == $this->sock) { // main socket, check for new connections
$client = @socket_accept( $sock);
if ( $client) {
//echo "new client $client\n";
if ( $this->nonblock) @socket_set_nonblock( $client);
lpush( $this->socks, $client);
$client = new $this->clientclass();
$client->init( null, null, uniqid(), $client, $this->buffersize);
lpush( $this->clients, $client);
$this->newclient( $client);
}
}
else { // existing socket
$client = null;
foreach ( $this->clients as $client2) if ( $client2->sock = $sock) $client = $client2;
if ( tsystem() - $client->lastime > $this->timeout) {
$this->clientout( $client);
@socket_close( $client->sock);
$this->removeclient( $client);
continue;
}
if ( $client) $this->eachloop( $client);
if ( $client && strlen( $client->outbuffer)) { if ( $client->send()) $client->lastime = tsystem(); }
if ( $client) { $h = $client->recv(); if ( $h) { $this->receive( $h, $client); $client->lastime = tsystem(); }}
}
//echo "loop sock: $sock\n";
}; if ( $this->usleep) usleep( $this->usleep); }
socket_close( $this->sock);
}
public function clientout( $client) {
$L = array(); $L2 = array( $this->sock);
foreach ( $this->clients as $client2) if ( $client2->sock != $client->sock) { lpush( $L, $client2); lpush( $L2, $client2->sock); }
$this->clients = $L;
$this->socks = $L2;
}
// interface, should extend some of the functions, some may be left alone
public function timetoquit() { return false; }
public function newclient( $client) { }
public function removeclient( $client) { }
public function eachloop( $client) { }
public function send( $h, $client) { $client->send( $h); }
public function receive( $h, $client) { }
}
function nwakeonlan( $addr, $mac, $port = '7') { // port 7 seems to be default

flush();
$addr_byte = explode(':', $mac);
$hw_addr = '';
for ($a=0; $a <6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
$msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);
for ($a = 1; $a <= 16; $a++) $msg .= $hw_addr;
// send it to the broadcast address using UDP
// SQL_BROADCAST option isn't help!!
$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ( $s == false) {
//echo "Error creating socket!\n";
//echo "Error code is '".socket_last_error($s)."' - " . socket_strerror(socket_last_error($s));
return FALSE;
}
else {
// setting a broadcast option to socket:
$opt_ret = 0;
$opt_ret = @socket_set_option( $s, 1, 6, TRUE);
if($opt_ret <0) {
//echo "setsockopt() failed, error: " . strerror($opt_ret) . "\n";
return FALSE;
}
if( socket_sendto($s, $msg, strlen( $msg), 0, $addr, $port)) {
//echo "Magic Packet sent successfully!";
socket_close($s);
return TRUE;
}
else {
echo "Magic packet failed!";
return FALSE;
}
}
}
class NServer {	// interface, mirror in myNServer to get notified

public $active;	// true while working, false will kill server
// type (string|hash|file), status (true|false), info(path,string,hash), size, rx statistics (time interval between chunks in us)
public function onload( $type, $rip, $port, $status, $info = '', $size = 0, $stats = array()) {}
// events are newsocket|abort|start|chunk|end|timeout, info is a hash
public function onevent( $type, $info = array()) { }
// types are sock,error,info,start,list,chunk,end,exit
public function debug( $type, $msg) {} // for all small events and changes
// called before nserver function exits, so, wrap up in this method
public function error( $msg) { }	// when something goes wrong
}
/** main (continuous) TCP server, can rx files|strings|hashes
*	$port to listen on (on all interfaces)
*	&$nserver class with methods as in NServer class above
*		methods will be called on various events and completed rx processes
*	$path the path to a directory (no trailing slash) to restrict saving files to
*		(in this case, files from the other side can be passed as filenames only, no path
*	$timeout in seconds, if >0 then will send 'timeout' event when over
*	$usleep is the sleep time in each loop, 200ms seems to be good generally
*/
function nserver( $port, $nserver, $path = '', $timeout = 0, $usleep = 300000) { // strings, hashes, and files

$info = ntcprxopen( $port);  $start = tsystem();
if ( $info[ 'error']) return $nserver->error( "could not open socket on port[$port]\n");
$server = $info[ 'sock'];
$socks = array(); $infos = array(); $stats = array();
while ( 1) {
if ( $timeout && tsystem() - $start > $timeout) {	// send timeout event
$newtimeout = $nserver->onevent( 'timeout', array());
if ( $newtimeout) $timeout = ( int)$newtimeout;
if ( ! $timeout) $timeout = 1;
$start = tsystem();
}
if ( ! $nserver->active) { // wrap up gracefully
$nserver->debug( 'exit', 'active flag is off');
// fist, close all active sockets, if any
foreach ( $socks as $sock) { @socket_shutdown( $sock); @socket_close( $sock); }
return @socket_close( $server);	// abort mission
}
$sock = @ntcprxcheck( $server);
if ( $sock) {
//echo "new socket[$sock]\n";
//echo "\n [" . count( $socks) . "] sockets";
$rip = ''; $rport = -1; socket_getpeername( $sock, $rip, $rport);
$nserver->debug( 'sock', "new socket [$sock] from rip[$rip] rport[$rport]");
$info = ntcprxinfo( $sock);
//if ( $info) echo ",  rx.info[" . htt( $info) . "]";
//else echo ", no rx.info";
if ( ! $info) {
//echo ",  not info, shutting the socket";
$nserver->debug( 'error', "could not get INFO block from rip[$rip] rport[$rport]");
@socket_shutdown( $sock); socket_close( $sock);
}
else {	// good socket + good rx info, start working
$nserver->debug( 'info', "got INFO from rip[$rip] rport[$rport], info[" . htt( $info) . "]");
$info[ 'rip'] = $rip; $info[ 'rport'] = $rport;
switch( $info[ 'type']) {
case 'file': {
if ( $path) { // check and finalize path to file
if ( count( explode( '/', $info[ 'path'])) == 1) $info[ 'path'] = $path . '/' . $info[ 'path'];
if ( strpos( $info[ 'path'], $path) !== 0) { 	// outside of allowed path
$nserver->debug( 'error', "path [" . $info[ 'path'] . "] is not allowed for this server");
ntcptxstatus( $sock, false, 'path not allowed because of restrictions');
@socket_shutdown( $sock); socket_close( $sock);
break;
}
}
$nserver->onevent( 'newsocket', $info);
$found = false; foreach ( $infos as $i) if ( isset( $i[ 'path']) &&  $i[ 'path'] == $info[ 'path']) { $found = true; break; }
if ( $found) {	// file path clash!
$nserver->debug( 'error', "path[" . $info[ 'path'] . "] clashes with another socket");
ntcptxstatus( $sock, false, 'file path clash');
@socket_shutdown( $sock); socket_close( $sock);
$nserver->onevent( 'abort', $info);
break;
}
$out = @fopen( $info[ 'path'], 'wb');
if ( ! $out) {	// no such path
$nserver->debug( 'error', "could not open path[" . $info[ 'path'] . "] on this machine");
ntcptxstatus( $sock, false, 'path does not exist');
@socket_shutdown( $sock); socket_close( $sock);
$nserver->onevent( 'abort', $info);
break;
}
// file write handler obtained successfully, keep working
$info[ 'out'] = $out; $info[ 'rsize'] = 0;
$status = ntcptxstatus( $sock, true);
if ( ! $status) { 	// error transmitting INFO ACK
$nserver->debug( 'error', "could not transmit INFO ACK to rip[$rip] rport[$rport]");
@socket_shutdown( $sock); socket_close( $sock);
$nserver->onevent( 'abort', $info);
break;
}
array_push( $socks, $sock);
array_push( $infos, $info);
array_push( $stats, array( tsystem()));
$nserver->onevent( 'start', $info);
$nserver->debug( 'start', "started working on file RX");
break;
}
default: {
$nserver->onevent( 'newsocket', $info);
//echo ", sending ACK for string";
if ( ! ntcptxstatus( $sock, true)) {
//echo " ERROR! could not tx info ACK!";
$nserver->debug( 'error', "could not transmit INFO ACK to rip[$rip] rport[$rport]");
@socket_shutdown( $sock); socket_close( $sock);
$nserver->onevent( 'abort', $info);
break;
}
array_push( $socks, $sock);
array_push( $infos, $info);
array_push( $stats, array( tsystem()));
$nserver->onevent( 'start', $info);
$nserver->debug( 'start', "started working on text RX");
break;
}
}
}
}
// process existing sockets
$nserver->debug( 'list', 'there are [' . count( $socks) . '] in active list');
for ( $i = 0; $i < count( $socks); $i++) {
//echo "\n"; echo "working sock[". $socks[ $i] ."]";
switch( $infos[ $i][ 'type']) {
case 'file': {
if ( $infos[ $i][ 'rsize'] == $infos[ $i][ 'size']) {	// OK
//echo " finished rx, send data ACK\n";
$nserver->debug( 'end', 'finished file RX on [' . htt( $infos[ $i]) . ']');
ntcptxstatus( $socks[ $i], true);
@socket_shutdown( $socks[ $i]);
socket_close( $socks[ $i]);
fclose( $infos[ $i][ 'out']);
//echo "file[" . $infos[ $i][ 'path'] . "] size[" . $infos[ $i][ 'size'] . "]\n";
$socks[ $i] = false;	// for cleanup
$nserver->onevent( 'end', $infos[ $i]);
$stat = mdistance( $stats[ $i]); for ( $ii = 0; $ii < count( $stat); $ii++) $stat[ $ii] = ( int)( 1000000 * $stat[ $ii]);
$nserver->onload( 'file', $infos[ $i][ 'rip'], $infos[ $i][ 'rport'], true, $infos[ $i][ 'path'], $infos[ $i][ 'size'], $stat);
break;
}
//echo "   rsize[" . $infos[ $i][ 'rsize'] . "] size[" . $infos[ $i][ 'size'] . "]";
$status = ntcprxfileone( $socks[ $i], $infos[ $i][ 'out'], 1000);
if ( $status === false) { 	// check if finished
// bad transmission
$nserver->debug( 'error', "did not receive all bytes on [" . htt( $infos[ $i])  . "]");
ntcptxstatus( $socks[ $i], false, 'did not recieve all bytes');
fclose( $infos[ $i][ 'out']); unlink( $infos[ $i][ 'path']); // delete file
@socket_shutdown( $socks[ $i]);
socket_close( $socks[ $i]);
$socks[ $i] = false;
echo "ERROR! bad rx of a file\n";
$nserver->onevent( 'abort', $infos[ $i]);
$nserver->onload( 'file', $infos[ $i][ 'rip'], $infos[ $i][ 'rport'], false);
break;
}
$infos[ $i][ 'rsize'] += $status;
$nserver->onevent( 'chunk', $infos[ $i]);
$nserver->debug( 'chunk', "size [$status], on socket[" . htt( $infos[ $i]) . "]");
array_push( $stats[ $i], tsystem());
break;
}
default: {
$text = ntcprxstring( $sock, $infos[ $i][ 'size']);
if ( strlen( $text) != $infos[ $i][ 'size']) {	// failed
//echo "failed to receive text, raw[$text]\n";
ntcptxstatus( $socks[ $i], false, 'size does not match');
@socket_shutdown( $socks[ $i]);
socket_close( $socks[ $i]);
//echo "ERROR! Failed rx of text type\n";
$socks[ $i] = false;
$nserver->onevent( 'abort', $infos[ $i]);
$nserver->onload( $infos[ $i][ 'type'], $infos[ $i][ 'rip'], $infos[ $i][ 'rport'], false);
break;
}
// OK
//echo "raw[$text]\n";
ntcptxstatus( $socks[ $i], true);
@socket_shutdown( $socks[ $i]);
socket_close( $socks[ $i]);
//echo "text[$text]\n";
$socks[ $i] = false;
$nserver->onevent( 'chunk', $infos[ $i]);
array_push( $stats[ $i], tsystem());
$stat = mdistance( $stats[ $i]); for ( $ii = 0; $ii < count( $stat); $ii++) $stat[ $ii] = ( int)( 1000 * $stat[ $ii]);$stat = mdistance( $stats[ $i]); for ( $ii = 0; $ii < count( $stat); $ii++) $stat[ $ii] = ( int)( 1000000 * $stat[ $ii]);
$nserver->onload( $infos[ $i][ 'type'], $infos[ $i][ 'rip'], $infos[ $i][ 'rport'], true, $infos[ $i][ 'type'] == 'hash' ? tth( $text) : $text, strlen( $text), $stat);
break;
}
}
}
// sort the array removing finished entities
$nsocks = array(); $ninfos = array(); $nstats = array();
for ( $i = 0; $i < count( $socks); $i++) {
if ( ! $socks[ $i]) continue;	// empty one
array_push( $nsocks, $socks[ $i]);
array_push( $ninfos, $infos[ $i]);
array_push( $nstats, $stats[ $i]);
}
$socks = $nsocks; $infos = $ninfos; $stats = $nstats;
if ( ! count( $socks)) usleep( $usleep);
}
}
function nsendstring( $rip, $rport, $text) {	// TCP string to a remote machine

$info = ntcptxopen( $rip, $rport);
$sock = $info[ 'sock']; if ( $info[ 'error']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "could not connect to remote socket rip[$rip] rport[$rport]"); }
$in = ntcptxinfostring( $sock, $text); //echo "send tx info\n";
if ( $in === false) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "failed to send INFO block"); }
$info = ntcprxstatus( $sock); if ( ! $info[ 'status']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "did not receive INFO ACK from rip[$rip] rport[$rport]"); }
ntcptxstring( $sock, $text); //echo "sent string\n";
//@ntcpshutwrite( $sock); //echo "closed writing, waiting for status\n";
$info = ntcprxstatus( $sock); if ( ! $info[ 'status']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "did not receive DATA ACK"); }
@socket_shutdown( $sock); socket_close( $sock);
return array( true, 'ok');
}
function nsendfile( $rip, $rport, $path, $rpath) { 	// TCP file to a remote machine

$info = ntcptxopen( $rip, $rport); if ( $info[ 'error']) return array( false, "could not connect to remote socket rip[$rip] rport[$rport]");
$sock = $info[ 'sock'];
$in = ntcptxinfofile( $sock, $path, $rpath);
if ( $in === false) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "failed while transmitting INFO to rip[$rip] rport[$rport]"); }
$status = ntcprxstatus( $sock);
if ( ! $status[ 'status']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "failed to receive INFO ACK from rip[$rip] rport[$rport]"); }
while ( ntcptxfileone( $sock, $in, 1000, 30.0)) {}
//@ntcpshutwrite( $sock);
$status = ntcprxstatus( $sock, 1000, 60.0);
if ( ! $status[ 'status']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "failed to receive DATA ACK from rip[$rip] rport[$rport]"); }
@socket_shutdown( $sock); socket_close( $sock);
return array( true, 'ok');
}
function nsendraw( $rip, $rport, $text, $length = 250) {	// TCP raw string to a remote machine

$info = ntcptxopen( $rip, $rport); if ( $info[ 'error']) { @socket_shutdown( $sock); socket_close( $sock); return array( false, "could not connect to remote socket rip[$rip] rport[$rport]"); }
$sock = $info[ 'sock'];
if ( strlen( $text) != $length) $text = sprintf( '%' . $length . 's', $text);
ntcptxstring( $sock, $text); //echo "sent string\n";
@socket_shutdown( $sock); socket_close( $sock);
return array( true, 'ok');
}
/* rx length-fixed message over UDP, timeout > 0 meanes non-block sockets
*	$length is important
*	$timeout=0 means block, otherwise, non-blocking
*	if $sock is non-zero
*	if $keep = true, will return the socket unclosed()
* returns hash( 'msg', 'error', 'sock', 'rip', 'rport', 'stats' => hash( 'sock', 'rx'))
*		(sock and rx are time(s,double) it took to set up socket and rx stuff)
*		(rip and rport are remote IP and port of packet source)
*/
function nudprx( $port, $length = 250, $sock = -1, $keep = false, $timeout = 0) { // msg, error, stats ( sock, rx)

$info = array( 'msg' => '', 'error' => true, 'sock' => -1, 'rip' => '', 'rport' => '', 'stats' => array( 'sock' => -1, 'rx' => -1));
// bind to socket
if ( $sock == -1) {	// no socket passed, create new
$start = tsystem();
$sock = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP);
$status = socket_bind( $sock, '0', $port);
$c = 10; while ( ! $status && $c--) {
usleep( mt_rand( 10000, 100000));
$status = socket_bind( $sock, $BIP, $port);
}
$end = tsystem(); $info[ 'stats'][ 'sock'] = $end - $start;
if ( ! $status) { $info[ 'msg'] = "could not bind to port[$port]"; @socket_close( $sock); return $info; }
if ( $timeout > 0) @socket_set_nonblock( $sock);
$info[ 'sock'] = $sock;
}
else { $info[ 'sock'] = $sock; $end = tsystem(); }
// rx the message
$start = $end; $msg = '';
//echo "sock[$sock]\n";
$c = 1000; while ( $c-- && ( ( ! $timeout || ( $timeout > 0 &&  $end - $start < $timeout)))) {
$status = socket_recvfrom( $sock, $info[ 'msg'], $length, 0, $info[ 'rip'], $info[ 'rport']);
//echo " [$status]";
//echo " status[$status]";
if ( $status > 0) break;	// rx success
usleep( mt_rand( 10000, 100000));
$end = tsystem(); continue;
}
$end = tsystem(); $info[ 'stats'][ 'rx'] = $end - $start;
if ( strlen( $info[ 'msg']) == $length) $info[ 'error'] = false;
else $info[ 'msg'] = @socket_strerror( @socket_last_error( $sock));
if ( $timeout && ( $end - $start >= $timeout)) $info[ 'msg'] = 'timeout reached while waiting on socket';
if ( ! $keep) socket_close( $info[ 'sock']);
return $info;
}
/* tx length-fixed message over UDP
*	if $length = -1, will use actual string length of $msg
*	if $sock != -1, will not create new but will use old
*	if $keep = true, will not close socket when finished
*	returns hash( 'error', 'msg', 'sock', 'stats' => hash( 'tx'))
*		(if 'error' = false, them message is the original one
*		otherwise, contains error message)
*		tx in stats is time it took to transmit the message
*/
function nudptx( $rip, $rport, $msg, $length = -1, $sock = -1, $keep = false) {

$info = array( 'error' => true, 'msg' => '', 'sock' => -1, 'stats' => array( 'tx' => 0));
$start = tsystem();
if ( $sock == -1) $sock = @socket_create( AF_INET, SOCK_DGRAM, SOL_UDP);
$info[ 'sock'] = $sock;
//echo "sock[$sock]\n";
if ( $length == -1) $length = strlen( $msg);
$msg = sprintf( '%' . $length . 's', $msg);
$status = socket_sendto( $sock, $msg, strlen( $msg), 0, $rip, $rport);
//echo "status[$status]\n";
if ( $status != $length) { $info[ 'msg'] = @socket_strerror( @socket_last_error( $sock)); $info[ 'status'] = $status; }
else $info[ 'error'] = false;
if ( ! $keep) @socket_close( $sock);
$info[ 'stats'][ 'tx'] = tsystem() - $start;
return $info;
}
function ntcprxopen( $port, $nonblock = false) {

global $BIP;
$info = array( 'sock' => -1, 'error' => true, 'msg' => '');
$sock = socket_create_listen( $port);
//$status = socket_bind( $sock, $BIP, $port);
//$c = 10; while ( $c-- && ! $status) {
//	usleep( mt_rand( 10000, 1000000));
//	$status = socket_bind( $sock, $BIP, $port);
//}
if ( $sock) {
$info[ 'sock'] = $sock;
$info[ 'error'] = false;
if ( $nonblock) @socket_set_nonblock( $sock);
@socket_listen( $sock);
}
else $info[ 'msg'] = "could not bind to port[$port]\n";
return $info;
}
function ntcptxopen( $rip, $rport) {

$info = array( 'error' => true, 'msg' => '', 'sock' => -1);
$start = tsystem();
$sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP);
$info[ 'sock'] = $sock;
$status = @socket_connect( $sock, $rip, $rport); //$rip, $rport);
$c = 200; while ( $c-- && ! $status) {
usleep( mt_rand( 100000, 800000));
$status = socket_connect( $sock, $rip, $rport);
echo " $status"; usleep( 500000);
}
if ( ! $status) { $info[ 'msg'] = @socket_strerror( @socket_last_error( $sock)); return $info; }
//echo "socket conn OK\n";
$info[ 'sock'] = $sock;
$info[ 'error'] = false;
@socket_set_nonblock( $sock);
return $info;
}
function ntcprxcheck( $sock) { 

$sock = @socket_accept( $sock);
if ( $sock === FALSE) return $sock;
return $sock;
}
function ntcptxinfo( $sock, $info, $length = 1000) {

$info = sprintf( '%' . $length . 's', htt( $info));
if ( strlen( $info) > $length) return false;	// info string too long
return ntcptxstring( $sock, $info);
}
function ntcptxinfofile( $sock, $path, $rpath, $length = 1000) { // false | file handler

if ( ! is_file( $path)) return false;
$size = filesize( $path); if ( ! $size || $size <= 0) return false; // strange size
$info = array( 'type' => 'file', 'path' => $rpath, 'size' => $size);
$status = ntcptxinfo( $sock, $info, $length);
if ( $status) return fopen( $path, 'rb');
return $status;
}
function ntcptxinfostring( $sock, $string, $length = 1000) {

$info = array( 'type' => 'string', 'size' => strlen( $string));
return ntcptxinfo( $sock, $info, $length);
}
function ntcptxstatus( $sock, $status, $msg = 'none', $length = 1000) {

$info = array( 'type' => 'status', 'status' => ( $status ? 'true' : 'false'), 'msg' => $msg);
return ntcptxinfo( $sock, $info, $length);
}
/** send the contents of a string over TCP socket
*	$sock the TCP socket
*	$in FILE handle in reading mode
*	$length length of chunk in bytes, will keep sending until finished
* returns true (chunk sent fine), null (end of file), false (error in channel)
*/
function ntcptxfileone( $sock, $in, $length, $timeout = 30) {

if ( feof( $in)) return null; //{ echo " EOF!"; return null; }
$chunk = fread( $in, $length); $start = tsystem();
if ( strlen( $chunk) == 0) return true;	// will feof() next time
while ( strlen( $chunk)) {
$status = @socket_write( $sock, $chunk, strlen( $chunk));
if ( $status === FALSE && tsystem() - $start > $timeout) return false;
$chunk = substr( $chunk, $status);
}
//echo " $length";
return true;
}
function ntcptxstring( $sock, $chunk, $timeout = 30) { // returns true|false

//echo "\n\n"; echo "ntcptxstring()   string[$chunk]";
$start = tsystem();
while ( strlen( $chunk)) {
$status = @socket_write( $sock, $chunk, strlen( $chunk));
if ( $status === FALSE && tsystem() - $start > $timeout) return false;
//echo " [$status]";
if ( $status == strlen( $chunk)) break;
$chunk = substr( $chunk, $status);
}
//echo "\n";
return true;
}
function ntcprxinfo( $sock, $length = 1000) {	// return hash (after check)

$chunk = ntcprxstring( $sock, $length);
if ( strlen( $chunk) != $length) return false;
return tth( trim( $chunk));
}
function ntcprxfileone( $sock, $out, $length, $timeout = 30) {	// returns length or false

$rlength = 0; $start = tsystem();
while ( $rlength < $length) {
$status = @socket_read( $sock, $length);
if ( $status === false && tsystem() - $start > $timeout) return ( $rlength > 0 ? $rlength : $status);
fwrite( $out, $status, strlen( $status));
$rlength += strlen( $status);
}
return $rlength;
}
function ntcprxstring( $sock, $length = 1000, $timeout = 30) {	// returns content

$content = '';  $start = tsystem();
//echo "\n\n"; echo "ntcprxstring():";
while ( strlen( $content) < $length) {
$status = @socket_read( $sock, $length);
if ( $status === false && tsystem() - $start > $timeout) break;
$content .= $status;
//echo "  [$status]";
}
//echo "... done\n";
return $content;
}
function ntcprxstatus( $sock, $length = 1000, $timeout = 30) {	// returns hash or false

$text = ntcprxstring( $sock, $length, $timeout);
if ( ! strlen( $text) || strlen( $text) != $length) return false;
$info = tth( trim( $text));
if ( $info[ 'type'] != 'status') return false;
$info[ 'status'] = ( $info[ 'status'] == 'true' ? true : false);
return $info;
}
function ntcpshutread( $sock) { @socket_shutdown( $sock, 0); }

function ntcpshutwrite( $sock) { @socket_shutdown( $sock, 1); }

function rweb( $json) { // base64( json( type,wait,command,proctag,login,password,domainURL))

$h = json2h( $json, true);
foreach ( $h as $k => $v) $h[ $k] = trim( $v);
extract( $h);
//echo "rweb()  json extract OK\n";
// pre-check
if ( ! strlen( $login) || ! strlen( $password)) return array( 'status' => false, 'msg' => 'no mauth info');
if ( ! strlen( $command)) return array( 'status' => false, 'msg' => 'empty command');
//echo "rweb()  precheck PASS\n";
// run remote command
$url = $h[ 'url'];
unset( $h[ 'url']);
$json = h2json( $h, true);
$url .= "/actions.php?action=get&json=$json";	// hope it is not too long
//echo "URL: [$url]\n";
list( $status, $body) = @mfetchWget( $url, $proctag, 5, 2);
//echo "rweb()  feedback [$body]\n";
if ( $status) $json = @jsonparse( $body);
else $json = array( 'status' => false, 'msg' => 'unknown error occurred in process');
//echo "rweb()  returning...\n";
return $json;
}
function rcli( $json) {	// same, only CLI version 

$h = json2h( $json, true);
foreach ( $h as $k => $v) $h[ $k] = trim( $v);
extract( $h);
// pre-check
if ( ! strlen( $login) || ! strlen( $password)) { $json = array( 'status' => false, 'msg' => 'no mauth info'); die( jsonsend( $json)); }
if ( ! strlen( $command)) { $json = array( 'status' => false, 'msg' => 'empty command'); die( jsonsend( $json)); }
// run remote command
$url = $h[ 'url'];
unset( $h[ 'url']);
$json = h2json( $h, true);
$url .= "/actions.php?action=get&json=$json";	// hope it is not too long
$json = @jsonparse( @wgetplain( $url, 5, 20));
if ( ! $json) $json = array( 'status' => false, 'msg' => 'unknown error occurred in process');
return $json;
}
class KBL { 

private $autotype = false; // if true, will declare new fields as text
private $L = null;
private $lasterr = '';
private $D;
private $typemap = array();
public function __construct( $dir = null) {	// return [ $L | null, error]  -- absolute path
global $KBLDIR;
if ( $dir) $KBLDIR = $dir;
$dir = $KBLDIR;
$L = null;
try {
if ( is_dir( $dir) && count( flget( $dir)))
$this->L = new Zend_Search_Lucene( $dir, false);
else $this->L = new Zend_Search_Lucene( $dir, true);
} catch ( Zend_Search_Lucene_Exception $e) { $lasterr = $e->getMessage();  return; }
if ( is_file( "$KBLDIR/typemap.json")) $this->typemap = jsonload( "$KBLDIR/typemap.json");
}
public function __destruct() { global $KBLDIR; $this->L->commit(); @jsondump( $this->typemap, "$KBLDIR/typemap.json"); }
public function optimize() { $this->L->optimize(); }
public function status() { return $this->L; }
public function lasterr() { return $this->lasterr; }
public function addtype( $k, $type) { $this->typemap[ "$k"] = $type; }  // keyword, text, unindexed
public function autotypeon() { $this->autotype = true; }
// document    -- NOTE: h2doc() will work only with previously used and populated typemap
public function docstart() { $this->D = new Zend_Search_Lucene_Document(); }
public function dockeyword( $k, $v) { $this->typemap[ "$k"] = 'keyword'; @$this->D->addField( Zend_Search_Lucene_Field::Keyword( $k, $v, 'UTF-8'));}
public function doctext( $k, $v) { $this->typemap[ $k] = 'text'; @$this->D->addField( Zend_Search_Lucene_Field::Text( $k, $v, 'UTF-8'));}
public function docunindexed( $k, $v) { $this->typemap[ $k] = 'unindexed'; @$this->D->addField( Zend_Search_Lucene_Field::UnIndexed( $k, $v, 'UTF-8'));}
public function docend( $commit = false) { try { $L = $this->L; @$L->addDocument( $this->D); } catch ( Zend_Search_Lucene_Exception $e) { return; }; unset( $this->D); if ( $commit) $L->commit(); }
public function doc2h( $doc) { $h = array(); $h[ 'id'] = $doc->id; foreach ( $this->typemap as $k => $type) { try { $v = $doc->$k; $h[ "$k"] = $v; } catch ( Zend_Search_Lucene_Exception $e) { $h[ $k] = ''; continue; }}; return $h; }
public function h2doc( $h, $commit = true, $donotadd = false) { $this->docstart(); foreach ( $h as $k => $v) { try {
if ( is_array( $v)) $v = ltt( $v, ' ');
if ( ! isset( $this->typemap[ "$k"]) && $this->autotype) $this->typemap[ "$k"] = 'text';
if ( ! isset( $this->typemap[ "$k"])) continue;	// skip, have not met this type before
//echo "\n\n[$k] " . strlen( $v) . " " . mb_substr( $v, 0, 100) . "\n";
if ( $this->typemap[ $k] == 'keyword') $this->dockeyword( $k, $v);
if ( $this->typemap[ $k] == 'text') $this->doctext( $k, $v);
if ( $this->typemap[ $k] == 'unindexed') $this->docunindexed( $k, $v);
} catch ( Zend_Search_Lucene_Exception $e) { continue; }}; if ( ! $donotadd) $this->docend( $commit); }
public function docs2hl( $docs) { $HL = array(); foreach ( $docs as $doc) lpush( $HL, $this->doc2h( $doc)); return $HL; }
// higher-level interface
public function getkeys() { return $this->typemap; }
public function query( $q, $limit = 300) { // returns [ doc, ...]
$q = mb_strtolower( $q);
Zend_Search_Lucene::setResultSetLimit( $limit);
$hits = array();
try { $hits = $this->L->find( $q);}
catch ( Zend_Search_Lucene_Exception $e) { $this->lasterr = $e->getMessage(); return null; }
return $hits;
}
public function find( $q, $limit = 300) { 	// calls query and returns [ doc2h, ...]
$hits = $this->query( $q);
if ( ! $hits) return null;
$hs = array();
foreach ( $hits as $hit) lpush( $hs, $this->doc2h( $hit));
return $hs;
}
public function add( $h, $commit = true) { $this->h2doc( $h, $commit); } // shorthand, not really necessary
public function get( $id) { return $this->L->isDeleted( ( int)$id) ? null : $this->L->getDocument( ( int)$id); }
public function purge( $id) { $this->L->delete( ( int)$id); }
public function update( $h) {  $this->purge( $h[ 'id']); $this->h2doc( $h); }
}
function pdf2txt( $path, $enc = 'UTF-8') { // returns text extracted from that pdf

global $XPDF;
$c = "$XPDF/pdftotext -enc " . strdblquote( $enc) . ' ' . strdblquote( $path) . ' ' . strdblquote( "$path.txt");
echo "     c[$c]\n";
@unlink( "$pdf.txt");
system( $c);
$in = @fopen( "$path.txt", 'r');
$body = ''; while ( $in && ! feof( $in)) $body .= fgets( $in);
@fclose( $in);
@unlink( "$path.txt");
return $body;
}
function cryptCRC32( $string) { return crc32( $string); }

function cryptCRC24( $string) { return btail( crc32( $string), 24); }

function cryptCRC24self( $bytes) { 	// returns hash digest of the array of bytes

$L = array(
0x00000000, 0x00d6a776, 0x00f64557, 0x0020e221, 0x00b78115, 0x00612663, 0x0041c442, 0x00976334,
0x00340991, 0x00e2aee7, 0x00c24cc6, 0x0014ebb0, 0x00838884, 0x00552ff2, 0x0075cdd3, 0x00a36aa5,
0x00681322, 0x00beb454, 0x009e5675, 0x0048f103, 0x00df9237, 0x00093541, 0x0029d760, 0x00ff7016,
0x005c1ab3, 0x008abdc5, 0x00aa5fe4, 0x007cf892, 0x00eb9ba6, 0x003d3cd0, 0x001ddef1, 0x00cb7987,
0x00d02644, 0x00068132, 0x00266313, 0x00f0c465, 0x0067a751, 0x00b10027, 0x0091e206, 0x00474570,
0x00e42fd5, 0x003288a3, 0x00126a82, 0x00c4cdf4, 0x0053aec0, 0x008509b6, 0x00a5eb97, 0x00734ce1,
0x00b83566, 0x006e9210, 0x004e7031, 0x0098d747, 0x000fb473, 0x00d91305, 0x00f9f124, 0x002f5652,
0x008c3cf7, 0x005a9b81, 0x007a79a0, 0x00acded6, 0x003bbde2, 0x00ed1a94, 0x00cdf8b5, 0x001b5fc3,
0x00fb4733, 0x002de045, 0x000d0264, 0x00dba512, 0x004cc626, 0x009a6150, 0x00ba8371, 0x006c2407,
0x00cf4ea2, 0x0019e9d4, 0x00390bf5, 0x00efac83, 0x0078cfb7, 0x00ae68c1, 0x008e8ae0, 0x00582d96,
0x00935411, 0x0045f367, 0x00651146, 0x00b3b630, 0x0024d504, 0x00f27272, 0x00d29053, 0x00043725,
0x00a75d80, 0x0071faf6, 0x005118d7, 0x0087bfa1, 0x0010dc95, 0x00c67be3, 0x00e699c2, 0x00303eb4,
0x002b6177, 0x00fdc601, 0x00dd2420, 0x000b8356, 0x009ce062, 0x004a4714, 0x006aa535, 0x00bc0243,
0x001f68e6, 0x00c9cf90, 0x00e92db1, 0x003f8ac7, 0x00a8e9f3, 0x007e4e85, 0x005eaca4, 0x00880bd2,
0x00437255, 0x0095d523, 0x00b53702, 0x00639074, 0x00f4f340, 0x00225436, 0x0002b617, 0x00d41161,
0x00777bc4, 0x00a1dcb2, 0x00813e93, 0x005799e5, 0x00c0fad1, 0x00165da7, 0x0036bf86, 0x00e018f0,
0x00ad85dd, 0x007b22ab, 0x005bc08a, 0x008d67fc, 0x001a04c8, 0x00cca3be, 0x00ec419f, 0x003ae6e9,
0x00998c4c, 0x004f2b3a, 0x006fc91b, 0x00b96e6d, 0x002e0d59, 0x00f8aa2f, 0x00d8480e, 0x000eef78,
0x00c596ff, 0x00133189, 0x0033d3a8, 0x00e574de, 0x007217ea, 0x00a4b09c, 0x008452bd, 0x0052f5cb,
0x00f19f6e, 0x00273818, 0x0007da39, 0x00d17d4f, 0x00461e7b, 0x0090b90d, 0x00b05b2c, 0x0066fc5a,
0x007da399, 0x00ab04ef, 0x008be6ce, 0x005d41b8, 0x00ca228c, 0x001c85fa, 0x003c67db, 0x00eac0ad,
0x0049aa08, 0x009f0d7e, 0x00bfef5f, 0x00694829, 0x00fe2b1d, 0x00288c6b, 0x00086e4a, 0x00dec93c,
0x0015b0bb, 0x00c317cd, 0x00e3f5ec, 0x0035529a, 0x00a231ae, 0x007496d8, 0x005474f9, 0x0082d38f,
0x0021b92a, 0x00f71e5c, 0x00d7fc7d, 0x00015b0b, 0x0096383f, 0x00409f49, 0x00607d68, 0x00b6da1e,
0x0056c2ee, 0x00806598, 0x00a087b9, 0x007620cf, 0x00e143fb, 0x0037e48d, 0x001706ac, 0x00c1a1da,
0x0062cb7f, 0x00b46c09, 0x00948e28, 0x0042295e, 0x00d54a6a, 0x0003ed1c, 0x00230f3d, 0x00f5a84b,
0x003ed1cc, 0x00e876ba, 0x00c8949b, 0x001e33ed, 0x008950d9, 0x005ff7af, 0x007f158e, 0x00a9b2f8,
0x000ad85d, 0x00dc7f2b, 0x00fc9d0a, 0x002a3a7c, 0x00bd5948, 0x006bfe3e, 0x004b1c1f, 0x009dbb69,
0x0086e4aa, 0x005043dc, 0x0070a1fd, 0x00a6068b, 0x003165bf, 0x00e7c2c9, 0x00c720e8, 0x0011879e,
0x00b2ed3b, 0x00644a4d, 0x0044a86c, 0x00920f1a, 0x00056c2e, 0x00d3cb58, 0x00f32979, 0x00258e0f,
0x00eef788, 0x003850fe, 0x0018b2df, 0x00ce15a9, 0x0059769d, 0x008fd1eb, 0x00af33ca, 0x007994bc,
0x00dafe19, 0x000c596f, 0x002cbb4e, 0x00fa1c38, 0x006d7f0c, 0x00bbd87a, 0x009b3a5b, 0x004d9d2d
);
$key = array( 0);
foreach ( $bytes as $byte) $key = btail( $key >> 8, 24) ^ $L[ btail( $key ^ $byte, 8)];
return $key;
}
function cryptbitmap( $bytes) { return btail( $bytes[ 0] >> $bytes[ 1], $bytes[ 2]); } // key = bitwise prefix, bytes are actually numbers

function fdeltaprofile( $file, $blocksize = null, $ignoreflags = false, $debug = false) { // returns meta: { size, stats, blocks: [ md5, ...]}

$e = null; if ( $debug) $e = echoeinit();
extract( fpathparse( $file)); // filepath
$h = array();  $size = filesize( $file);
if ( ! $blocksize) $blocksize = round( 0.01 * $size); if ( $blocksize < 10) $blocksize = 10;	// 5 bytes at least!
$h[ 'size'] = @$size;
$h[ 'blocksize'] = $blocksize;
$h[ 'stats'] = array(); if ( ! $ignoreflags) $h[ 'stats'] = fstats( $file);
$h[ 'blocks'] = array();
if ( ! $blocksize) return $h;	// empty file
$in = fopen( $file, 'rb'); $progress = 0;
while ( ! feof( $in)) {
//$temp = ftempname( '', "$fileroot.fdelta", $filepath); if ( ! $temp) continue;
//$out = fopen( $temp, 'wb');
//if ( ! $out) { usleep( 400000); continue; }
//if ( ! $in) die( " ERROR! IN file pointer is [" . jsonraw( $in) . "], quit.\n");
//$limit = $blocksize; while ( ! feof( $in) && $limit--) fwrite( $out, fread( $in, 1));
//fclose( $out);
lpush( $h[ 'blocks'], md5( fread( $in, $blocksize)));
//`rm -Rf $temp`;
$progress += $blocksize;
if ( $e) echoe( $e, round( 100 * ( $progress / ( $size ? $size : 1))) . '%');
}
fclose( $in);
if ( $e) echoe( $e, '');
return $h;
}
function fdeltacompare( $file, $meta, $ignoreflags = false) { // return [ changed? TRUE | FALSE, new meta]

$meta2 = fdeltaprofile( $file, $meta[ 'blocksize'], $ignoreflags);
return array( $meta2 == $meta ? false : true, $meta2);
}
function fdeltagracefulcheck( $file, $meta) { // returns [ changed ( true | false), new metadata] -- maybe the same if nothing changed

extract( $meta); // size, blocksize, stats, blocks
extract( fpathparse( $file)); // filepath
$h = $meta; $size2 = filesize( $file); $h[ 'size'] = $size2;
$stats2 = fstats( $file); $h[ 'stats'] = $stats2;
if ( $size2 == $size && $stats2 == $stats) return array( false, $h);
$h[ 'blocks'] = array();
$in = fopen( $file, 'rb'); $progress = 0;
while ( ! feof( $in)) {
$temp = ftempname( '', "$fileroot.fdelta", $filepath); if ( ! $temp) continue;
$out = fopen( $temp, 'wb');
if ( ! $out) { usleep( 400000); continue; }
if ( ! $in) die( " ERROR! IN file pointer is [" . jsonraw( $in) . "], quit.\n");
$limit = $blocksize; while ( ! feof( $in) && $limit--) fwrite( $out, fread( $in, 1));
fclose( $out);
lpush( $h[ 'blocks'], md5_file( $temp));
`rm -Rf $temp`;
$progress += $blocksize;
if ( $e) echoe( $e, ' ' . round( 100 * ( $progress / ( $size ? $size : 1))) . '%');
}
fclose( $in);
if ( $e) echoeinit( $e);
return array( true, $h);
}
function fdeltareport( $meta1, $meta2, $verbose = false) { // [ { change hash}, ...]    change hash: { type, misc other keys...}

extract( $meta1); // blocksize
$R = array();
// sizechange
$type = 'size'; $diff = $meta2[ 'size'] - $meta1[ 'size']; if ( $diff) $R[ $type] = compact( ttl( 'type,diff'));
// stats -- flags
foreach ( $meta1[ 'stats'] as $k => $v) {
$type = 'stats'; $key = $k; $diff = $meta2[ 'stats'][ "$k"] - $meta1[ 'stats'][ "$k"];
if ( $diff) $R[ $type]  = compact( ttl( 'type,key,diff'));
}
// blocks
$type = 'blocks'; $diff = 0; $h = array();
foreach ( ttl( 'meta1,meta2') as $k) { $meta = $$k; foreach ( $meta[ 'blocks'] as $k) { htouch( $h, "$k", 0, false, false); $h[ "$k"]++; }}
$diff = 0; foreach ( $h as $k => $v) if ( $v != 2) $diff++;
$diff = round( $diff / ( count( $h) ? count( $h) : 1), 3);
if ( $diff) $R[ $type] = compact( ttl( 'type,diff'));
if ( ! $verbose) return $R;
// identify changed blocks
unset( $R1); unset( $R2);
$R1 =& $meta1[ 'blocks']; $R2 =& $meta2[ 'blocks'];
$diffs = array(); // { blockpos: 'changed' | 'removed' | 'added', ...}
for ( $i = 0; $i < mmax( count( $R1), count( $R2)); $i++) {
$blockpos = $blocksize * $i;
if ( ! isset( $R1[ $i])) { $diffs[ "$blockpos"] = 'added'; continue; }
if ( ! isset( $R2[ $i])) { $diffs[ "$blockpos"] = 'removed'; continue; }
if ( $R1[ $i] != $R2[ $i]) { $diffs[ "$blockpos"] = 'changed'; continue; }
}
$R[ 'details'] = $diffs;
return $R;
}
function cleanfilename( $name,  $bad = '', $replace = '.', $donotlower = true) {

if ( ! $bad) $bad = '*{}|=/ -_",;:!?()[]&%$# ' . "'" . '\\';
$name = strcleanup( $name, $bad, $replace);
for ( $i = 0; $i < 10; $i++) $name = str_replace( $replace . $replace, $replace, $name);
if ( strpos( $name, '.') === 0) $name = substr( $name, 1);
if ( ! $donotlower) $name = strtolower( $name);
return $name;
}
function fstats( $file) {  // { ctime, mtime, atime}

$ctime = filectime( $file);
$atime = fileatime( $file);
$mtime = filemtime( $file);
return compact( ttl( 'ctime,atime,mtime'));
}
function fpathparse( $path, $ashash = true) { 	// returns [ (absolute) filepath (no slash), filename, fileroot (without path), filetype (extension)]

$L = ttl( $path, '/'); $L = ttl( lpop( $L), '.');
$type = llast( $L); if ( count( $L) > 1) lpop( $L);
$root = ltt( $L, '.');
$L = ttl( $path, '/', '', false);
if ( count( $L) === 1) return $ashash ? lth( array( getcwd(), $path, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( getcwd(), $path, $root, $type);	// plain filename in current directory
if ( ! strlen( $L[ 0])) { $filename = lpop( $L); return $ashash ? lth( array( ltt( $L, '/'), $filename, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( ltt( $L, '/'), $filename, $root, $type); }	// absolute path
// relative path
$cwd = getcwd(); $filename = lpop( $L); $path = ltt( $L, '/');
chdir( $path);	// should follow relative path as well
$path = getcwd(); chdir( $cwd);	// read cwd and go back
return $ashash ? lth( array( $path, $filename, $root, $type), ttl( 'filepath,filename,fileroot,filetype')) : array( $path, $filename, $root, $type);
}
function fbackup( $file, $move = false) { 	// will save a backup copy of this file as file.tsystem()s.random(10)

$suffix = sprintf( "%d.%d", ( int)tsystem(), mr( 10));
if ( $move) procpipe( "mv $file $file.$suffix");
else procpipe( "cp $file $file.$suffix");
}
function fbackups( $file) { 	// will find all backups for this file and return { suffix(times.random): filename}, will retain the path

$L = ttl( $file, '/', '', false); $file = lpop( $L); $path = ltt( $L, '/'); // if no path will be empty
$FL = flget( $path, $file); $h = array();
foreach ( $FL as $file2) {
if ( $file2 === $file || strlen( $file2) <= strlen( $file)) continue;
$suffix = str_replace( $file . '.', '', $file2);
$h[ "$suffix"] = $path ? "$path/$file2" : $file2;
}
return $h;
}
function ftempname( $ext = '', $prefix = '', $dir = '') { 	// dir can be '', file in form: [ prefix.] times . random( 10) . ext

$limit = 10;
while ( $limit--) {
$temp = ( $dir ? $dir . '/' : '') . ( $prefix ? $prefix . '.' : '') . ( int)tsystem() . '.' . mr( 10) . ( $ext ? '.' . $ext : '');
if ( ! is_file( $temp)) return $temp;
}
die( " ERROR! ftempname() failed to create a temp name\n");
}
function finopen( $file) { 	// opens( read), reads file size, returns { in: handle, total(bytes),current(bytes),progress(%)}

$h = array();
$h[ 'total'] = filesize( $file);
$h[ 'current'] = 0;	// did not read any
$h[ 'count'] = 0; // count of lines
$h[ 'progress'] = '0%';
$h[ 'in'] = fopen( $file, 'r');
return $h;
}
function finread( &$h, $json = true, $base64 = true, $bzip2 = true) {	// returns array( line | hash | array(), 'x%' | null)

extract( $h); if ( ! $in || feof( $in)) return array( null, null, null); // empty array and null progress
$line = fgets( $in); if ( ! trim( $line)) return array( null, null, null); 	// empty line
$h[ 'count']++;
$h[ 'current'] += mb_strlen( $line);
$h[ 'progress'] = round( 100 * ( $h[ 'current'] / $h[ 'total'])) . '%';
if ( $json) return array( json2h( trim( $line), $base64, null, $bzip2), $h[ 'progress'], $h[ 'count']);
if ( $base64) $line = base64_decode( trim( $line));
if ( $bzip2) $line = bzdecompress( $line);
return array( $line, $h[ 'progress'], $h[ 'count']);
}
function finclose( &$h) { extract( $h); fclose( $in); }

function findone( &$h) { extract( $h); return ( ! $in) | feof( $in); }

function foutopen( $file, $flag = 'w') { // returns { bytes, progress (easy to read kb,Mb format)}

$h = array();
$h[ 'bytes'] = 0; // count of written bytes
$h[ 'count'] = 0; // count of lines
$h[ 'progress'] = '0b';	// b, kb, Mb, Gb
$h[ 'out'] = fopen( $file, $flag);
return $h;
}
function foutwrite( &$h, $stuff, $json = true, $base64 = true, $bzip2 = true) {	// returns output filesize (b, kb, Mb, etc..)

if ( is_string( $stuff)) $stuff = tth( $stuff);
if ( $json) $stuff = h2json( $stuff, $base64, null, null, $bzip2);
else { // not an object, should be TEXT!, but can still base64 and bzip2 it
if ( $bzip2) $stuff = bzcompress( $stuff);
if ( $base64) $stuff = base64_encode( $stuff);
}
if ( mb_strlen( $stuff)) $h[ 'bytes'] += mb_strlen( $stuff);
$tail = ''; $progress = $h[ 'bytes'];
if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'kb'; }
if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'Mb'; }
if ( $progress > 1000) { $progress = round( 0.001 * $progress); $tail = 'Gb'; }
$h[ 'progress'] = $progress . $tail;
if ( mb_strlen( $stuff)) fwrite( $h[ 'out'], "$stuff\n");
return $h[ 'progress'];
}
function foutclose( &$h) { extract( $h); fclose( $out); }

function fbjamopen( $file, $firstValueIsNotTime = false) {

$h = array();
if ( ! $firstValueIsNotTime) $h[ 'time'] = 0;
$h[ 'in'] = fopen( $file, 'r');
return $h;
}
function fbjamnext( $in, $logic, $filter = array()) {	// returns: hash | null   logic: hash | hash string,   filter: hash | hash string

if ( is_string( $filter)) $filter = tth( $filter);	// string hash
if ( is_string( $logic)) $logic = tth( $logic);
while ( $in[ 'in'] && ! feof( $in[ 'in'])) {
$L = bjamread( $in[ 'in']); if ( ! $L) return null;
if ( isset( $in[ 'time'])) $in[ 'time'] += 0.000001 * $L[ 0];	// move time if 'time' key exists
$h = array(); $good = true;
for ( $i = 0; $i < count( $logic) && $i < count( $L); $i++) {
$def = $logic[ $i];
if ( count( ttl( $def, ':')) === 1) { $h[ $def] = $L[ $i]; continue; }
// this is supposed to be a { id: string} map now
$k = lshift( ttl( $def, ':')); $v = lpop( ttl( $def, ':'));
$map = tth( $v);
if ( ! isset( $map[ $L[ $i]])) { $good = false; break; } // this record is outside of parsing logic
$h[ $k] = $map[ $L[ $i]];
}
if ( ! $good) continue;	// go to the next
foreach ( $filter as $k => $v) if ( ! isset( $h[ $k]) || $h[ $k] != $v) $good = false;
if ( ! $good) continue;
return $h;	// this data sample is fit, return it
}
return null;
}
function fbjamclose( &$h) { fclose( $h[ 'in']); }

class DLLE { // one DLL entity, extend to define your own payload, do not change DLL part, but you can still access prev/next vars

// functionality, specific to DLL
public $prev = null;
public $next = null;
}
class DLL { 	// (E)ntity (L)ist, the DLL itself

// basic DLL structure and getters
public $count = 0;
public $head = null;
public $tail = null;
public function count() { return $this->count; }
public function head() { return $this->head; }
public function tail() { return $this->tail; }
// getters
public function at( $pos) { // returns E at a given pos in the list
$e = $this->head;
for ( $i = 0; $i < $pos && $e; $i++) $e = $e->next;
return $e;
}
public function pop() { 	// pop entry at DLL tail and returns it
if ( ! $this->tail) die( " ERROR! DLL.pop() Empty DLL!");	// nothing in DLL so far
$a = $this->tail; if ( ! $a->prev) { $this->head = null; $this->tail = null; $this->count = 0; $a->next = null; $a->prev = null; return $a; } // the last one
$b = $a->prev;
$b->next = null; $a->prev = null; $a->next = null; $this->tail = $b;
$this->count--; if ( $this->count < 0) die( " ERROR! DLL.pop() count < 0 (" . $this->count . ")\n");
return $a;
}
public function shift() { // shifts head entry and returns it
if ( ! $this->head) die( " ERROR! DLL.shift() Empty DLL!"); 	// empty DLL
$a = $this->head; if ( ! $a->next) { $this->head = null; $this->tail = null; $this->count = 0; $a->prev = null; $a->next = null; return $a; } // last one
$b = $this->next;
$b->prev = null; $a->next = null; $a->prev = null; $this->head = $b;
$this->count--; if ( $this->count < 0) die( " ERROR! DLL.shift() count < 0 (" . $this->count . ")\n");
}
// setters
public function push( $e) { // add new entry to the end of the DLL
if ( $e->next || $e->prev) $this->deset( $e);
if ( ! $this->head) { $this->head = $e; $this->tail = $e; $e->prev = null; $e->next = null; $this->count = 1; return; }	// first one
$a = $this->tail;
$a->next = $e; $e->prev = $a; $e->next = null; $this->tail = $e;
$this->count++;
}
public function unshift( $e) { // adds new entry to the head of DLL
if ( $e->next || $e->prev) $this->deset( $e);
if ( ! $this->head) { $this->head = $e; $this->tail = $e; $e->prev = null; $e->next = null; $this->count = 1; return; } // first in DLL
$a = $this->head;
$a->prev = $e; $e->prev = null; $e->next = $a; $this->head = $e;
$this->count++;
}
public function before( $pos, $e) { // insert E before a given pos
if ( $pos == 0) return unshift( $e);
if ( $e->next || $e->prev) $this->deset( $e);
$E = $this->at( $pos);
if ( ! $E) return; // quit silently
$A = $E->prev; $B = $E->next;
$A->next = $e; $e->prev = $A;
$e->next = $E; $E->prev = $e;
$this->count++;
}
public function after( $pos, $e) { // insert E after $pos
if ( $pos == $this->count) return $this->push( $e);
if ( $e->next || $e->prev) $this->deset( $e);
$E = $this->at( $pos);
if ( ! $E) return; // quit silently
$B = $E->next;
$E->next = $e; $e->prev = $E;
$B->prev = $e; $e->next = $B;
$this->count++;
}
// other functionality
public function deset( $e) { // extracts this E from DLL (and close up the hole), E itself can continue its separate live
$a = $e->prev; $b = $e->next;
if ( ! $a && ! $b) { $this->head = null; $this->tail = null; $this->count = 0; return; }
if ( $a && $b) { $a->next = $b; $b->prev = $a; }	 // middle
if ( ! $a && $b) { $b->prev = null; $this->head = $b; }
if ( $a && ! $b) { $a->next = null; $this->tail = $a; }
$e->prev = null; $e->next = null;
$this->count--; if ( $this->count < 0) die( " ERROR! DLL.deset() count < 0 (" . $this->count . ")\n");
}
public function debug() { 	// debug/check the structure of this DLL
$e = $this->head; $count = 0;
while ( $e) { $count++; $e = $e->next; }
if ( $count != $this->count) die( " ERROR! DLL.debug() Bad data, count[" . $this->count . "] but actually found [$count]\n");
}
}
class HashTable {

public $h = array();	// hash table itself
public $count = 0;
public $hsize = 1;	// how many entries to allow for each key (collision avoidance)
public $length = 32;
public $type = 'CRC24';	// ending of crypt*** hashing function from crypt.php
public function __construct( $type, $length, $hsize) { $this->type = $type; $this->length = $length; $this->hsize = $hsize; }
public function count( $total = false) { return $total ? $this->count : count( $this->h); }
public function key( $id) { $k = 'crypt' . $this->type; return btail( $k( $id), $this->length); } // calculates hash key
public function get( $id, $key = null) { // returns [ object | NULL, cost of horizontal search]
if ( $key === null) $key = $this->key( $id);
if ( ! isset( $this->h[ $key])) return array( NULL, 0);
$L =& $this->h[ $key];
for ( $i = 0; $i < count( $L); $i++) if ( $L[ $i]->id() == $id) return array( $L[ $i], $i + 1);
return array( NULL, count( $L));
}
public function set( $e) {	// returns TRUE on success, FALSE otherwise
$k = $this->key( $e->id());
if ( ! isset( $this->h[ $k])) $this->h[ $k] = array();
if ( count( $this->h[ $k]) >= $this->hsize) return false; 	// collision cannot be resolved, quit on this entry
$this->count++; lpush( $this->h[ $k], $e);
return true;
}
public function remove( $e) { // returns hcost of lookup
$k = $this->key( $e->id());
if ( ! isset( $this->h[ $k])) die( " ERROR! HashTable:remove() key[$key] does not exist in HashTable\n");
$L = $this->h[ $k]; $L2 = array();
foreach ( $L as $e2) if ( $e->id() != $e2->id()) lpush( $L2, $e2);
$this->count -= count( $L) - count( $L2);
if ( ! count( $L2)) unset( $this->h[ $k]); else $this->h[ $k] = $L2;
return count( $L);
}
}
class QTKV {  // QueueTimeKeyValue

public $s; // setup for time grain at each stage
public $topc = false;
public $c; // conditions per grain stage
public $count = 0;
public $q = array();
public function __construct( $setup) { // [ time1[, time2]] ex: [ 0.1, 0.01] -- setup can be array, or comma-string
if ( is_string( $setup)) $this->s = ttl( $setup);
else $this->s = $setup;
$this->c = array(); foreach ( $this->s as $grain) lpush( $this->c, array());
}
public function put( $time, $k, $v, $replaceifsmaller = true, $replaceifbigger = true) { // time can clash but keys in each time should be unique
$q =& $this->q;
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( ! $level) $this->topc = true;
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
htouch( $q, "$time");
htouch( $q[ "$time"], "$k", $v, $replaceifsmaller, $replaceifbigger);
$this->count++;
}
public function next() { // returns [ time, key, value], shifts the value from the queue
$q =& $this->q; if ( ! count( $q)) return array( null, null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null, null);
$k2 = hfirstk( $q); if ( ! count( $q[ "$k2"])) { unset( $q[ "$k2"]); unset( $this->c[ $level][ "$k2"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k2"])) { ksort( $q[ "$k2"], SORT_NUMERIC); unset( $this->c[ $level][ "$k2"]); }
$q =& $q[ "$k2"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->next(); }	// empty slot, remove and run again
list( $k, $v) = hshift( $q[ "$time"]);	if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->next();
$this->count--;
return array( $time, $k, $v);
}
public function peek() { // returns [ time, key, value], but does not shift the value
$q =& $this->q; if ( ! count( $q)) return array( null, null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null, null);
$k2 = hfirstk( $q); if ( ! count( $q[ "$k2"])) { unset( $q[ "$k2"]); unset( $this->c[ $level][ "$k2"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k2"])) { ksort( $q[ "$k2"], SORT_NUMERIC); unset( $this->c[ $level][ "$k2"]); }
$q =& $q[ "$k2"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->next(); }	// empty slot, remove and run again
list( $k, $v) = hfirst( $q[ "$time"]);	if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->next();
$this->count--;
return array( $time, $k, $v);
}
public function delete( $time, $k) {	// returns deleted value
$q =& $this->q;
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
$v = $q[ "$time"][ "$k"]; unset( $q[ "$time"][ "$k"]);
if ( ! count( $q[ "$time"])) unset( $q[ "$time"]);
$this->count--;
return $v;
}
public function update( $time, $k, $v) { // returns old value
$q =& $this->q;
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
$v = $q[ "$time"][ "$k"]; unset( $q[ "$time"][ "$k"]);
if ( ! count( $q[ "$time"])) unset( $q[ "$time"]);
return $v;
}
public function &ref( $time, $k) { // returns reference to current value
$q =& $this->q;
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
return $q[ "$time"][ "$k"];
}
public function count() { return $this->count; }
}
class QTV {  // QueueTimeValue -- multiple entries for the same time are allowed

public $s; // setup for time grain at each stage
public $topc = false;
public $c; // conditions per grain stage
public $count = 0;
public $q = array();
public function __construct( $setup) { // [ time1[, time2]] ex: [ 0.1, 0.01] -- setup can be array, or comma-string
if ( is_string( $setup)) $this->s = ttl( $setup);
else $this->s = $setup;
$this->c = array(); foreach ( $this->s as $grain) lpush( $this->c, array());
}
public function put( $time, $v) { // time can clash but keys in each time should be unique
$q =& $this->q;
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( ! $level) $this->topc = true;
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
htouch( $q, "$time"); lpush( $q[ "$time"], $v);
$this->count++;
}
public function next() { // returns [ time, value], shifts the value from the queue
$q =& $this->q; if ( ! count( $q)) return array( null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null);
$k = hfirstk( $q); if ( ! count( $q[ "$k"])) { unset( $q[ "$k"]); unset( $this->c[ $level][ "$k"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k"])) { ksort( $q[ "$k"], SORT_NUMERIC); unset( $this->c[ $level][ "$k"]); }
$q =& $q[ "$k"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->next(); }	// empty slot, remove and run again
list( $k, $v) = hshift( $q[ "$time"]);	if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->next();
$this->count--;
return array( $time, $v);
}
public function peek() { // returns [ time, value], but does not shift the value
$q =& $this->q; if ( ! count( $q)) return array( null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null);
$k = hfirstk( $q); if ( ! count( $q[ "$k"])) { unset( $q[ "$k"]); unset( $this->c[ $level][ "$k"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k"])) { ksort( $q[ "$k"], SORT_NUMERIC); unset( $this->c[ $level][ "$k"]); }
$q =& $q[ "$k"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->next(); }	// empty slot, remove and run again
list( $k, $v) = hfirst( $q[ "$time"]);	if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->next();
$this->count--;
return array( $time, $v);
}
public function count() { return $this->count; }
}
class QTVM {  // QueueTimeValue(plus)Map

public $s; // setup for time grain at each stage
public $topc = false;
public $c; // conditions per grain stage
public $count = 0;
public $id = 1;
public $q = array();
public $m = array();
public function __construct( $setup) { // [ time1[, time2]] ex: [ 0.1, 0.01] -- setup can be array, or comma-string
if ( is_string( $setup)) $this->s = ttl( $setup);
else $this->s = $setup;
$this->c = array(); foreach ( $this->s as $grain) lpush( $this->c, array());
}
public function put( $time, $v, $h = array()) { // returns id for this entry, will overwrite $h[ 'ref'] and $h[ 'ref2']
$q =& $this->q; $m =& $this->m;
if ( is_string( $h)) $h = tth( $h);
for ( $level = 0; $level < count( $this->s); $level++) {
$grain = $this->s[ $level];
$k2 = $grain * floor( $time / $grain);
$c = htouch( $q, "$k2");
if ( ! $level) $this->topc = true;
if ( $c) $this->c[ $level][ "$k2"] = true; // this key at this level is updated
$q =& $q[ "$k2"];
}
htouch( $q, "$time");
$id = $this->id++;
$q[ "$time"][ "$id"] = $v;
$m[ "$id"] = $h; $m[ "$id"][ 'ref'] =& $q[ "$time"][ "$id"]; $m[ "$id"][ 'ref2'] = $time; // reference
$this->count++;
return $id;
}
public function next() { // returns [ time, value, id, h]
$q =& $this->q; $m =& $this->m;
if ( ! count( $q)) return array( null, null, null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null, null, null);
$k = hfirstk( $q); if ( ! count( $q[ "$k"])) { unset( $q[ "$k"]); unset( $this->c[ $level][ "$k"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k"])) { ksort( $q[ "$k"], SORT_NUMERIC); unset( $this->c[ $level][ "$k"]); }
$q =& $q[ "$k"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->next(); }	// empty slot, remove and run again
list( $id, $v) = hshift( $q[ "$time"]); if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->next();
$h  = $m[ "$id"]; unset( $m[ "$id"]); unset( $h[ 'ref']); unset( $h[ 'ref2']); $this->count--;
return array( $time, $v, $id, $h);
}
public function peek() { // returns [ time, value, id, h]
$q =& $this->q; $m =& $this->m;
if ( ! count( $q)) return array( null, null, null, null);
if ( $this->topc) { ksort( $q, SORT_NUMERIC); $this->topc = false; } // ksort top level
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null, null, null);
$k = hfirstk( $q); if ( ! count( $q[ "$k"])) { unset( $q[ "$k"]); unset( $this->c[ $level][ "$k"]); return $this->peek(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level][ "$k"])) { ksort( $q[ "$k"], SORT_NUMERIC); unset( $this->c[ $level][ "$k"]); }
$q =& $q[ "$k"];
}
// the last level, time then keys
$time = hfirstk( $q); if ( ! count( $q[ "$time"])) { unset( $q[ "$time"]); return $this->peek(); }	// empty slot, remove and run again
list( $id, $v) = hfirst( $q[ "$time"]); if ( ! count( $q[ "$time"])) unset( $q[ "$time"]); // shift key and value under this time
if ( $v === null) return $this->peek();
$h  = $m[ "$id"]; unset( $h[ 'ref']); unset( $h[ 'ref2']);
return array( $time, $v, $id, $h);
}
public function extract( $id) { // returns [ time, value, hash] for that id
$q =& $this->q; $m =& $this->m;
$h = $m[ "$id"]; $v = $h[ 'ref']; $time = $h[ 'ref2']; unset( $m[ "$id"]); unset( $h[ 'ref']); unset( $h[ 'ref2']);
for ( $level = 0; $level < count( $this->s); $level++) { // all levels but last
if ( ! count( $q)) return array( null, null, null);
$k = hfirstk( $q); if ( ! count( $q[ "$k"])) { unset( $q[ "$k"]); unset( $this->c[ $level + 1][ "$k"]); return $this->next(); }	// empty slot, remove and run again
if ( isset( $this->c[ $level + 1][ "$k"])) { ksort( $q[ "$k"], SORT_NUMERIC); unset( $this->c[ $level + 1][ "$k"]); }
$q =& $q[ "$k"];
}
unset( $q[ "$time"][ "$id"]); if ( ! count( $q[ "$time"])) unset( $q[ "$time"]);
$this->count--;
return array( $time, $v, $h);
}
public function delete( $id) {	// returns [ time, v, h]
$q =& $this->q; $m =& $this->m;
$v = $m[ "$id"][ 'ref']; $time = $m[ "$id"][ 'ref2']; // value
$h = $m[ "$id"]; unset( $h[ 'ref']); unset( $h[ 'ref2']);  unset( $m[ "$id"]);
$this->count--;
return array( $time, $v, $h);
}
public function update( $id, $k, $v) { $m =& $this->m; $m[ "$k"] = $v; }  // not payload value, but info key: value
public function &ref( $id) {  return $this->m[ "$id"][ 'ref']; }
public function &idref( $id) { return $this->m[ "$id"]; }
public function map2hash( $mapk = null, $infok = null) { // if $mapk != null, will hash by key, not by id,   if infok!=null, will only use that info key as value
$h = array(); foreach ( $this->m as $id => $h2) {
$k = $mapk ? $h2[ $mapk] : $id;
$v = $infok ? $h2[ $infok] : $h2;
$h[ "$k"] = $v;
}
return $h;
}
public function map2list( $infok = null) { // if infok!=null, will only use that info key as value
$L = array(); foreach ( $this->m as $id => $h2) lpush( $L, $infok ? $h2[ $infok] : $h2);
return $L;
}
public function count() { return $this->count; }
}
class GA {

public $e = null;
public $e2 = null;
public $allstop = false;
public $verbosity = 0;
public $genecount;
public $chrocount;
public $genes; // each gene should consist of multiple chromosomes
public $digits = 3;
//
// EXTEND these functions
//
public function fitness( $g) { return 0; }
public function isvalid( $g) { return false; }
public function makechromosome( &$g, $pos, $new = false) { return null; } 	// used by mutation function, should be extended in children classess!s
public function generationreport( $generation, $evals) { }	// extend if you need a report on each generation
//
// TO DO TOUCH these functions -- rewrite only when completely sure what you are doing
// optimize == maximize,   if you need minimize, return 1 / fitness() in extended function
public function optimize( $genecount, $chrocount, $crossover = 0.5, $mutation = 0.5, $creation = 0.2, $untouchables = 3, $generations = 1000, $digits = 6, $verbosity = 1) { // returns [ bests, evals]   bests: best score succession, evals: last evals
$this->verbosity = $verbosity;
if ( $verbosity > 0) $this->e = echoeinit();
if ( $verbosity > 1) $this->e2 = echoeinit();
$this->digits = $digits;
$this->genecount = $genecount;
$this->chrocount = $chrocount;
$evals = array(); $before = tsystem();
$this->makegenes( $genecount, $chrocount);
//die( " AFTER\n");
for ( $i = 0; $i < $generations; $i++) {
if ( $this->e2) echoe( $this->e2, '');
// first, find untouchbles and put them into top array
$top = array();
if ( count( $evals)) arsort( $evals, SORT_NUMERIC);
if ( count( $evals)) for ( $ii = 0; $ii < $untouchables; $ii++) { list( $pos, $fitness) = hshift( $evals); $top[ $pos] = $fitness; }
$top2 = array(); foreach ( $top as $k => $v) $top2[ $k] = round( $v, $this->digits);
if ( $this->e) echoe( $this->e, "GA gen " . ( $i + 1) . " of $generations  top(" . htt( $top2) . ")");
// run this generation, keep untouchables in top
$evals = $this->generation( $evals, $crossover, $mutation, $creation, $top);
//die( " evals:" . json_encode( $evals) . "\n");
if ( $this->allstop) return array( null, null); // aborted
if ( $this->e2) echoe( $this->e2, "  evals:" . ltt( hv( mstats( hv( $evals), $digits)), '/'));
if ( $this->verbosity == 2) echo " OK\n";
$this->generationreport( $i, $evals);
}
$this->check( $evals);
if ( $this->e2) echoe( $this->e2, '');
if ( $this->e) echoe( $this->e, '');
// all doSne, return evals
arsort( $evals, SORT_NUMERIC);
return array( $this->genes, $evals);
}
public function generation( $evals, $crossover = 0.5, $mutation = 0.5, $creation = 0.2, $top = null) { // returns new list of fitness values
if ( ! $top) $top = array();
if ( ! count( $evals)) { for ( $i = 0; $i < count( $this->genes); $i++) if ( ! isset( $top[ $i])) $evals[ $i] = null; }
$this->check( $evals);
//die( " AFTER CHECK evals:" . json_encode( $evals) . "\n");
// ids: list of gene ids, subject to crossover and mutation
$ids = array(); for ( $i = 0; $i < count( $this->genes); $i++) if ( ! isset( $top[ $i])) lpush( $ids, $i);
// crossovers
$howmany = round( $crossover * count( $ids));
while ( $howmany > 0) {
$id1 = lr( $ids); $id2 = lr( $ids); if ( $id1 == $id2) continue;	// random ids, same id not allowed
list( $c1, $c2, $diff) = $this->crossover( $this->genes[ $id1], $this->genes[ $id2]);
if ( $this->e2) echoe( $this->e2, "   crossover($howmany): $id1 <> $id2 (" . ( $diff >= 0 ? '+' : '') . round( $diff, $this->digits) . ")");
if ( isset( $top[ $id1])) $id1 = mmax( hk( $this->genes)) + 1;
if ( isset( $top[ $id2])) $id2 = mmax( hk( $this->genes)) + 1;
$this->genes[ $id1] = $c1; $evals[ $id1] = null;
$this->genes[ $id2] = $c2; $evals[ $id2] = null;
$howmany--;
}
// mutations
$howmany = round( $mutation * count( $ids));
while ( $howmany > 0) {
$id = lr( $ids); if ( isset( $top[ $id])) continue;	// should not mutate one of the top
list( $c, $diff) = $this->mutation( $this->genes[ $id]);
if ( $this->e2) echoe( $this->e2, "   mutation($howmany): $id (" . ( $diff >= 0 ? '+' : '') . round( $diff, $this->digits) . ")");
$this->genes[ $id] = $c;
$evals[ $id] = null;
$howmany--;
}
// fill in unknown evals
if ( $this->e2) echoe( $this->e2, "   check");
// new genes
foreach ( $top as $id => $fitness) $evals[ $id] = $fitness;
arsort( $evals, SORT_NUMERIC);
$howmany = round( $creation * count( $evals));
for ( $i = 0; $i < $howmany; $i++) { list( $id, $fitness) = hpop( $evals); }
while ( count( $evals) < $this->genecount) { // repopulate with new genes
$id = mmax( hk( $this->genes)) + 1;
$this->genes[ $id] = $this->makegene( $this->chrocount);
$evals[ $id] = $this->fitness( $this->genes[ $id]);	// beware that fitness can abort the process
if ( ! is_numeric( $evals[ $id])) die( " ERROR! optimization.php/GA.generation() non-numeric fitness!\n");
if ( $this->allstop) return $evals; // aborted
if ( $this->e2) echoe( $this->e2, "   creation(" . count( $evals) . '<' . $this->genecount . '): ' . round( $evals[ $id], $this->digits));
}
// remap evals to cleanup and straighten up
arsort( $evals, SORT_NUMERIC);
$ks = hk( $evals); $vs = hv( $evals); $genes = $this->genes; $evals = array();  $this->genes = array();
for ( $i = 0; $i < count( $ks); $i++) { $evals[ $i] = $vs[ $i]; $this->genes[ $i] = $genes[ $ks[ $i]]; }
return $evals;
}
public function makegene( $chrocount) {
$limit = 1000; $g = null;
while ( $limit--) {
$g = array();
for ( $i = 0; $i < $chrocount; $i++) $this->makechromosome( $g, $i, true);
$good = true; foreach ( $g as $chrom) if ( $chrom === null) die( " optimization.php/GA Error: NULL chromosome in gene, will not continute.\n");
if ( $this->isvalid( $g)) break;
else $g = null;
}
if ( $g === null) die( " optimization.php/GA makegene() ERROR: No gene was created after many loops!");
return $g;	// successful gene
}
public function makegenes( $genecount, $chrocount) {
$this->genes = array();
for ( $i = 0; $i < $genecount; $i++) {
//echo " gene($i)\n";
lpush( $this->genes, $this->makegene( $chrocount));
if ( $this->e) echoe( $this->e, "initial population: " . count( $this->genes) . ' < ' . $genecount);
}
}
public function crossover( $p1, $p2) { // returns array( $c1, $c2, $diff), c: child, diff: different between best fitness before and after
$low = 0; $high = count( $p1) - 1;
if ( $high - $low > 2) { $low++; $high--; }
if ( $high < $low) $high = $low;
$point = mt_rand( $low, $high);
$one = $p1;
$two = $p2;
$three = array(); for ( $i = 0; $i < count( $p1); $i++) lpush( $three, $i <= $point ? $p1[ $i] : $p2[ $i]);
if ( ! $this->isvalid( $three)) $three = null;	// bad child
$four = array(); for ( $i = 0; $i < count( $p1); $i++) lpush( $four, $i <= $point ? $p2[ $i] : $p1[ $i]);
if ( ! $this->isvalid( $four)) $four = null;	// bad child
$evals = array(); foreach ( ttl( 'one,two') as $k) {
$evals[ $k] = $this->fitness( $$k);
if ( ! is_numeric( $evals[ $k])) die( " ERROR! optimization.php/GA.crossover() non-numeric fitness!\n");
}
$before = mmax( hv( $evals));
foreach ( ttl( 'three,four') as $k) {
$v = $$k; if ( $v === null) continue;
$evals[ $k] = $this->fitness( $$k);
if ( ! is_numeric( $evals[ $k])) die( " ERROR! optimization.php/GA.crossover() non-numeric fitness!\n");
}
$after = mmax( hv( $evals));
arsort( $evals, SORT_NUMERIC);
list( $k1, $v1) = hfirst( $evals);	if ( count( $evals) > 1) hshift( $evals); // best of four
list( $k2, $v2) = hfirst( $evals);	// second best of four
//echo "  k1($k1) k2($k2) evals(" . json_encode( $evals) . ")\n";
$v1 = $$k1; $v2 = $$k2;
return array( $v1, $v2, $after - $before);
}
public function mutation( $p) { // returns array( $c, $diff), c: child, diff: fitnext after - fitness before
$before = $this->fitness( $p);
if ( ! is_numeric( $before)) die( " ERROR! optimization.php/GA.mutation() non-numeric BEFORE fitness!\n");
$pos = mt_rand( 0, count( $p) - 1);
$c = $p; $this->makechromosome( $c, $pos);	// create a new chromosome for this gene
if ( ! $this->isvalid( $c)) $c = $p;	// mutation failed
$after = $this->fitness( $c);
if ( ! is_numeric( $after)) die( " ERROR! optimization.php/GA.mutation() non-numeric AFTER fitness!\n");
return array( $c, $after - $before);
}
public function check( &$evals) { foreach ( $evals as $id => $fitness) {
if ( $fitness === null) {
$evals[ $id] = $this->fitness( $this->genes[ $id]);
if ( ! is_numeric( $evals[ $id])) die( " ERROR! optimization.php/GA.check() non-numeric fitness!\n");
}
if ( $this->allstop) return; // aborted
if ( $this->e2) echoe( $this->e2, "   fitness:$id(" . round( $evals[ $id], $this->digits) . ")");
}}
public function abort() { $this->allstop = true; }
}
/** Copyright (c) 2012, Adam Alexander
All rights reserved.
Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
* Neither the name of PHP WebSockets nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
class WebSocketUser {

public $socket;
public $id;
public $headers = array();
public $handshake = false;
public $handlingPartialPacket = false;
public $partialBuffer = "";
public $sendingContinuous = false;
public $partialMessage = "";
public $hasSentClose = false;
// streaming support
public $in = null;
public $pos;
public $blocksize;
public $step;
public $lastpos;
public $lastime = 0;
function __construct($id,$socket) { $this->id = $id; $this->socket = $socket; }
}
abstract class WebSocketServer {
protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
protected $maxBufferSize;
protected $master;
protected $sockets                              = array();
protected $users                                = array();
protected $interactive                          = true;
protected $headerOriginRequired                 = false;
protected $headerSecWebSocketProtocolRequired   = false;
protected $headerSecWebSocketExtensionsRequired = false;
function __construct($addr, $port, $bufferLength = 2048) {
$this->maxBufferSize = $bufferLength;
$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
socket_listen($this->master,20)                               or die("Failed: socket_listen()");
$this->sockets[] = $this->master;
$this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
while( true) {
if ( empty($this->sockets)) {
$this->sockets[] = $master;
}
$read = $this->sockets;
$write = $except = null;
@socket_select($read,$write,$except,null);
foreach ($read as $socket) {
if ($socket == $this->master) {
$client = socket_accept($socket);
if ($client < 0) {
$this->stderr("Failed: socket_accept()");
continue;
} else {
$this->connect($client);
}
} else {
$numBytes = @socket_recv($socket,$buffer,$this->maxBufferSize,0); // todo: if($numBytes === false) { error handling } elseif ($numBytes === 0) { remote client disconected }
if ($numBytes == 0) {
$this->disconnect($socket);
} else {
$user = $this->getUserBySocket($socket);
if (!$user->handshake) {
$this->doHandshake($user,$buffer);
} else {
if ($message = $this->deframe($buffer, $user)) {
$this->process($user, mb_convert_encoding($message, 'UTF-8'));
if($user->hasSentClose) {
$this->disconnect($user->socket);
}
} else {
do {
$numByte = @socket_recv($socket,$buffer,$this->maxBufferSize,MSG_PEEK);
if ($numByte > 0) {
$numByte = @socket_recv($socket,$buffer,$this->maxBufferSize,0);
if ($message = $this->deframe($buffer,$user)) {
$this->process($user,$message);
if($user->hasSentClose) {
$this->disconnect($user->socket);
}
}
}
} while($numByte > 0);
}
}
}
}
}
}
}
abstract protected function process($user,$message); // Calked immediately when the data is recieved.
abstract protected function connected($user);        // Called after the handshake response is sent to the client.
abstract protected function closed($user);           // Called after the connection is closed.
protected function connecting($user) {
// Override to handle a connecting user, after the instance of the User is created, but before
// the handshake has completed.
}
protected function send($user,$message,$type='text') {
//$this->stdout("> $message");
$message = $this->frame($message,$user, $type);
socket_write($user->socket,$message,strlen($message));
}
protected function connect($socket) {
$user = new $this->userClass(uniqid(),$socket);
array_push($this->users,$user);
array_push($this->sockets,$socket);
$this->connecting($user);
}
protected function disconnect($socket,$triggerClosed=true) {
$foundUser = null;
$foundSocket = null;
foreach ($this->users as $key => $user) {
if ($user->socket == $socket) {
$foundUser = $key;
$disconnectedUser = $user;
break;
}
}
if ($foundUser !== null) {
unset($this->users[$foundUser]);
$this->users = array_values($this->users);
}
foreach ($this->sockets as $key => $sock) {
if ($sock == $socket) {
$foundSocket = $key;
break;
}
}
if ($foundSocket !== null) {
unset($this->sockets[$foundSocket]);
$this->sockets = array_values($this->sockets);
}
if ($triggerClosed) {
$this->closed($disconnectedUser);
}
}
protected function doHandshake($user, $buffer) {
$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
$headers = array();
$lines = explode("\n",$buffer);
foreach ($lines as $line) {
if (strpos($line,":") !== false) {
$header = explode(":",$line,2);
$headers[strtolower(trim($header[0]))] = trim($header[1]);
} else if (stripos($line,"get ") !== false) {
preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
$headers['get'] = trim($reqResource[1]);
}
}
if (isset($headers['get'])) {
$user->requestedResource = $headers['get'];
} else {
// todo: fail the connection
$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
}
if (!isset($headers['host']) || !$this->checkHost($headers['host'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['sec-websocket-key'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
} else {
}
if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
}
if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
$handshakeResponse = "HTTP/1.1 403 Forbidden";
}
if (($this->headerSecWebSocketProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerSecWebSocketProtocolRequired && !$this->checkWebsocProtocol($header['sec-websocket-protocol']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (($this->headerSecWebSocketExtensionsRequired && !isset($headers['sec-websocket-extensions'])) || ($this->headerSecWebSocketExtensionsRequired && !$this->checkWebsocExtensions($header['sec-websocket-extensions']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
// Done verifying the _required_ headers and optionally required headers.
if (isset($handshakeResponse)) {
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->disconnect($user->socket);
return false;
}
$user->headers = $headers;
$user->handshake = $buffer;
$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);
$rawToken = "";
for ($i = 0; $i < 20; $i++) {
$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
}
$handshakeToken = base64_encode($rawToken) . "\r\n";
$subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
$extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";
$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->connected($user);
}
protected function checkHost($hostName) {
return true; // Override and return false if the host is not one that you would expect.
// Ex: You only want to accept hosts from the my-domain.com domain,
// but you receive a host from malicious-site.com instead.
}
protected function checkOrigin($origin) {
return true; // Override and return false if the origin is not one that you would expect.
}
protected function checkWebsocProtocol($protocol) {
return true; // Override and return false if a protocol is not found that you would expect.
}
protected function checkWebsocExtensions($extensions) {
return true; // Override and return false if an extension is not found that you would expect.
}
protected function processProtocol($protocol) {
return ""; // return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
// The carriage return/newline combo must appear at the end of a non-empty string, and must not
// appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
// the response body, which will trigger an error in the client as it will not be formatted correctly.
}
protected function processExtensions($extensions) {
return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
}
protected function getUserBySocket($socket) {
foreach ($this->users as $user) {
if ($user->socket == $socket) {
return $user;
}
}
return null;
}
protected function stdout($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function stderr($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function frame($message, $user, $messageType='text', $messageContinues=false) {
switch ($messageType) {
case 'continuous':
$b1 = 0;
break;
case 'text':
$b1 = ($user->sendingContinuous) ? 0 : 1;
break;
case 'binary':
$b1 = ($user->sendingContinuous) ? 0 : 2;
break;
case 'close':
$b1 = 8;
break;
case 'ping':
$b1 = 9;
break;
case 'pong':
$b1 = 10;
break;
}
if ($messageContinues) {
$user->sendingContinuous = true;
} else {
$b1 += 128;
$user->sendingContinuous = false;
}
$length = strlen($message);
$lengthField = "";
if ($length < 126) {
$b2 = $length;
} elseif ($length <= 65536) {
$b2 = 126;
$hexLength = dechex($length);
//$this->stdout("Hex Length: $hexLength");
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 2) {
$lengthField = chr(0) . $lengthField;
}
} else {
$b2 = 127;
$hexLength = dechex($length);
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 8) {
$lengthField = chr(0) . $lengthField;
}
}
return chr($b1) . chr($b2) . $lengthField . $message;
}
protected function deframe($message, $user) {
//echo $this->strtohex($message);
$headers = $this->extractHeaders($message);
$pongReply = false;
$willClose = false;
switch($headers['opcode']) {
case 0:
case 1:
case 2:
break;
case 8:
// todo: close the connection
$user->hasSentClose = true;
return "";
case 9:
$pongReply = true;
case 10:
break;
default:
//$this->disconnect($user); // todo: fail connection
$willClose = true;
break;
}
if ($user->handlingPartialPacket) {
$message = $user->partialBuffer . $message;
$user->handlingPartialPacket = false;
return $this->deframe($message, $user);
}
if ($this->checkRSVBits($headers,$user)) {
return false;
}
if ($willClose) {
// todo: fail the connection
return false;
}
$payload = $user->partialMessage . $this->extractPayload($message,$headers);
if ($pongReply) {
$reply = $this->frame($payload,$user,'pong');
socket_write($user->socket,$reply,strlen($reply));
return false;
}
if (extension_loaded('mbstring')) {
if ($headers['length'] > mb_strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
} else {
if ($headers['length'] > strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
}
$payload = $this->applyMask($headers,$payload);
if ($headers['fin']) {
$user->partialMessage = "";
return $payload;
}
$user->partialMessage = $payload;
return false;
}
protected function extractHeaders($message) {
$header = array('fin'     => $message[0] & chr(128),
'rsv1'    => $message[0] & chr(64),
'rsv2'    => $message[0] & chr(32),
'rsv3'    => $message[0] & chr(16),
'opcode'  => ord($message[0]) & 15,
'hasmask' => $message[1] & chr(128),
'length'  => 0,
'mask'    => "");
$header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);
if ($header['length'] == 126) {
if ($header['hasmask']) {
$header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
}
$header['length'] = ord($message[2]) * 256
+ ord($message[3]);
} elseif ($header['length'] == 127) {
if ($header['hasmask']) {
$header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
}
$header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256
+ ord($message[3]) * 65536 * 65536 * 65536
+ ord($message[4]) * 65536 * 65536 * 256
+ ord($message[5]) * 65536 * 65536
+ ord($message[6]) * 65536 * 256
+ ord($message[7]) * 65536
+ ord($message[8]) * 256
+ ord($message[9]);
} elseif ($header['hasmask']) {
$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
}
//echo $this->strtohex($message);
//$this->printHeaders($header);
return $header;
}
protected function extractPayload($message,$headers) {
$offset = 2;
if ($headers['hasmask']) {
$offset += 4;
}
if ($headers['length'] > 65535) {
$offset += 8;
} elseif ($headers['length'] > 125) {
$offset += 2;
}
return substr($message,$offset);
}
protected function applyMask($headers,$payload) {
$effectiveMask = "";
if ($headers['hasmask']) {
$mask = $headers['mask'];
} else {
return $payload;
}
while (strlen($effectiveMask) < strlen($payload)) {
$effectiveMask .= $mask;
}
while (strlen($effectiveMask) > strlen($payload)) {
$effectiveMask = substr($effectiveMask,0,-1);
}
return $effectiveMask ^ $payload;
}
protected function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
//$this->disconnect($user); // todo: fail connection
return true;
}
return false;
}
protected function strtohex($str) {
$strout = "";
for ($i = 0; $i < strlen($str); $i++) {
$strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
$strout .= " ";
if ($i%32 == 7) {
$strout .= ": ";
}
if ($i%32 == 15) {
$strout .= ": ";
}
if ($i%32 == 23) {
$strout .= ": ";
}
if ($i%32 == 31) {
$strout .= "\n";
}
}
return $strout . "\n";
}
protected function printHeaders($headers) {
echo "Array\n(\n";
foreach ($headers as $key => $value) {
if ($key == 'length' || $key == 'opcode') {
echo "\t[$key] => $value\n\n";
} else {
echo "\t[$key] => ".$this->strtohex($value)."\n";
}
}
echo ")\n";
}
}
abstract class WebSocketServerStreaming {
protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
protected $maxBufferSize;
protected $master;
protected $sockets                              = array();
protected $users                                = array();
protected $interactive                          = true;
protected $headerOriginRequired                 = false;
protected $headerSecWebSocketProtocolRequired   = false;
protected $headerSecWebSocketExtensionsRequired = false;
function __construct($addr, $port, $bufferLength = 2048) {
$this->maxBufferSize = $bufferLength;
$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
socket_set_nonblock( $this->master);
socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
socket_listen($this->master,20)                               or die("Failed: socket_listen()");
$this->sockets[] = $this->master;
$this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
while( true) {
if ( empty( $this->sockets)) $this->sockets[] = $master;
$read = $this->sockets;
$write = $except = null;
//@socket_select( $read, $write, $except, 0);
foreach ( $read as $socket) {
//echo "B1 sock($socket)\n";
// call round robin for existing users
if ( $socket != $this->master) {
//echo "B2 sock($socket)\n";
$user = $this->getUserBySocket( $socket);
if ( $user->handshake && $user->in) { if ( ! $this->tx( $user)) { $this->disconnect( $user->socket); continue; } }
}
//echo "B3\n";
// check for new sockets
if ( $socket == $this->master) {
//echo "B4\n";
$client = @socket_accept( $socket);
if ( $client <= 0) continue;
socket_set_nonblock( $client);
$this->connect( $client);
}
else {
//echo "B5\n";
$numBytes = @socket_recv( $socket, $buffer, $this->maxBufferSize,0); // todo: if($numBytes === false) { error handling } elseif ($numBytes === 0) { remote client disconected }
if ( $numBytes <= 0) continue;
$user = $this->getUserBySocket( $socket);
if ( ! $user->handshake) { $this->doHandshake($user,$buffer); continue; }
if ( $message = $this->deframe( $buffer, $user)) {
//echo "B6\n";
$this->rx( $user, mb_convert_encoding( $message, 'UTF-8'));
//echo "B6b\n";
if ( $user->hasSentClose) $this->disconnect( $user->socket);
//echo "B6c\n";
continue;
}
//echo "Bpre7\n";
do {
//echo "socket.rx\n";
//echo "B7\n";
$numByte = @socket_recv($socket,$buffer,$this->maxBufferSize,MSG_PEEK);
if ( $numByte > 0) {
//echo "B8a\n";
$numByte = @socket_recv( $socket, $buffer, $this->maxBufferSize, 0);
if ( $message = $this->deframe( $buffer, $user)) {
//echo "B8b\n";
$this->rx( $user, $message);
if ( $user->hasSentClose) $this->disconnect($user->socket);
}
}
} while( $numByte > 0);
}
}
}
}
abstract protected function rx( $user, $message); // Calked immediately when the data is recieved.
abstract protected function tx( $user); // Calked immediately when the data is recieved.
abstract protected function connected($user);        // Called after the handshake response is sent to the client.
abstract protected function closed($user);           // Called after the connection is closed.
protected function connecting($user) {
// Override to handle a connecting user, after the instance of the User is created, but before
// the handshake has completed.
}
protected function send( $user, $message, $type = 'text') {
//$this->stdout("> $message");
$message = $this->frame( $message, $user, $type);
while ( strlen( $message)) {
$bytes = @socket_write( $user->socket, $message, strlen( $message));
$message = substr( $message, $bytes);
}
}
protected function connect($socket) {
$user = new $this->userClass(uniqid(),$socket);
array_push($this->users,$user);
array_push($this->sockets,$socket);
$this->connecting($user);
}
protected function disconnect($socket,$triggerClosed=true) {
$foundUser = null;
$foundSocket = null;
$disconnectedUser = false;
foreach ($this->users as $key => $user) {
if ($user->socket == $socket) {
$foundUser = $key;
$disconnectedUser = $user;
break;
}
}
if ($foundUser !== null) {
unset($this->users[$foundUser]);
$this->users = array_values($this->users);
}
foreach ($this->sockets as $key => $sock) {
if ($sock == $socket) {
$foundSocket = $key;
break;
}
}
if ($foundSocket !== null) {
unset($this->sockets[$foundSocket]);
$this->sockets = array_values($this->sockets);
}
if ($triggerClosed && $disconnectedUser) $this->closed($disconnectedUser);
}
protected function doHandshake($user, $buffer) {
$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
$headers = array();
$lines = explode("\n",$buffer);
foreach ($lines as $line) {
if (strpos($line,":") !== false) {
$header = explode(":",$line,2);
$headers[strtolower(trim($header[0]))] = trim($header[1]);
} else if (stripos($line,"get ") !== false) {
preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
$headers['get'] = trim($reqResource[1]);
}
}
if (isset($headers['get'])) {
$user->requestedResource = $headers['get'];
} else {
// todo: fail the connection
$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
}
if (!isset($headers['host']) || !$this->checkHost($headers['host'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['sec-websocket-key'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
} else {
}
if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
}
if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
$handshakeResponse = "HTTP/1.1 403 Forbidden";
}
if (($this->headerSecWebSocketProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerSecWebSocketProtocolRequired && !$this->checkWebsocProtocol($header['sec-websocket-protocol']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (($this->headerSecWebSocketExtensionsRequired && !isset($headers['sec-websocket-extensions'])) || ($this->headerSecWebSocketExtensionsRequired && !$this->checkWebsocExtensions($header['sec-websocket-extensions']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
// Done verifying the _required_ headers and optionally required headers.
if (isset($handshakeResponse)) {
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->disconnect($user->socket);
return false;
}
$user->headers = $headers;
$user->handshake = $buffer;
$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);
$rawToken = "";
for ($i = 0; $i < 20; $i++) {
$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
}
$handshakeToken = base64_encode($rawToken) . "\r\n";
$subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
$extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";
$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->connected($user);
}
protected function checkHost($hostName) {
return true; // Override and return false if the host is not one that you would expect.
// Ex: You only want to accept hosts from the my-domain.com domain,
// but you receive a host from malicious-site.com instead.
}
protected function checkOrigin($origin) {
return true; // Override and return false if the origin is not one that you would expect.
}
protected function checkWebsocProtocol($protocol) {
return true; // Override and return false if a protocol is not found that you would expect.
}
protected function checkWebsocExtensions($extensions) {
return true; // Override and return false if an extension is not found that you would expect.
}
protected function processProtocol($protocol) {
return ""; // return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
// The carriage return/newline combo must appear at the end of a non-empty string, and must not
// appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
// the response body, which will trigger an error in the client as it will not be formatted correctly.
}
protected function processExtensions($extensions) {
return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
}
protected function getUserBySocket($socket) {
foreach ($this->users as $user) {
if ($user->socket == $socket) {
return $user;
}
}
return null;
}
protected function stdout($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function stderr($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function frame($message, $user, $messageType='text', $messageContinues=false) {
switch ($messageType) {
case 'continuous':
$b1 = 0;
break;
case 'text':
$b1 = ($user->sendingContinuous) ? 0 : 1;
break;
case 'binary':
$b1 = ($user->sendingContinuous) ? 0 : 2;
break;
case 'close':
$b1 = 8;
break;
case 'ping':
$b1 = 9;
break;
case 'pong':
$b1 = 10;
break;
}
if ($messageContinues) {
$user->sendingContinuous = true;
} else {
$b1 += 128;
$user->sendingContinuous = false;
}
$length = strlen($message);
$lengthField = "";
if ($length < 126) {
$b2 = $length;
} elseif ($length <= 65536) {
$b2 = 126;
$hexLength = dechex($length);
//$this->stdout("Hex Length: $hexLength");
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 2) {
$lengthField = chr(0) . $lengthField;
}
} else {
$b2 = 127;
$hexLength = dechex($length);
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 8) {
$lengthField = chr(0) . $lengthField;
}
}
return chr($b1) . chr($b2) . $lengthField . $message;
}
protected function deframe($message, $user) {
//echo $this->strtohex($message);
$headers = $this->extractHeaders($message);
$pongReply = false;
$willClose = false;
switch($headers['opcode']) {
case 0:
case 1:
case 2:
break;
case 8:
// todo: close the connection
$user->hasSentClose = true;
return "";
case 9:
$pongReply = true;
case 10:
break;
default:
//$this->disconnect($user); // todo: fail connection
$willClose = true;
break;
}
if ($user->handlingPartialPacket) {
$message = $user->partialBuffer . $message;
$user->handlingPartialPacket = false;
return $this->deframe($message, $user);
}
if ($this->checkRSVBits($headers,$user)) {
return false;
}
if ($willClose) {
// todo: fail the connection
return false;
}
$payload = $user->partialMessage . $this->extractPayload($message,$headers);
if ($pongReply) {
$reply = $this->frame($payload,$user,'pong');
socket_write($user->socket,$reply,strlen($reply));
return false;
}
if (extension_loaded('mbstring')) {
if ($headers['length'] > mb_strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
} else {
if ($headers['length'] > strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
}
$payload = $this->applyMask($headers,$payload);
if ($headers['fin']) {
$user->partialMessage = "";
return $payload;
}
$user->partialMessage = $payload;
return false;
}
protected function extractHeaders($message) {
$header = array('fin'     => $message[0] & chr(128),
'rsv1'    => $message[0] & chr(64),
'rsv2'    => $message[0] & chr(32),
'rsv3'    => $message[0] & chr(16),
'opcode'  => ord($message[0]) & 15,
'hasmask' => $message[1] & chr(128),
'length'  => 0,
'mask'    => "");
$header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);
if ($header['length'] == 126) {
if ($header['hasmask']) {
$header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
}
$header['length'] = ord($message[2]) * 256
+ ord($message[3]);
} elseif ($header['length'] == 127) {
if ($header['hasmask']) {
$header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
}
$header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256
+ ord($message[3]) * 65536 * 65536 * 65536
+ ord($message[4]) * 65536 * 65536 * 256
+ ord($message[5]) * 65536 * 65536
+ ord($message[6]) * 65536 * 256
+ ord($message[7]) * 65536
+ ord($message[8]) * 256
+ ord($message[9]);
} elseif ($header['hasmask']) {
$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
}
//echo $this->strtohex($message);
//$this->printHeaders($header);
return $header;
}
protected function extractPayload($message,$headers) {
$offset = 2;
if ($headers['hasmask']) {
$offset += 4;
}
if ($headers['length'] > 65535) {
$offset += 8;
} elseif ($headers['length'] > 125) {
$offset += 2;
}
return substr($message,$offset);
}
protected function applyMask($headers,$payload) {
$effectiveMask = "";
if ($headers['hasmask']) {
$mask = $headers['mask'];
} else {
return $payload;
}
while (strlen($effectiveMask) < strlen($payload)) {
$effectiveMask .= $mask;
}
while (strlen($effectiveMask) > strlen($payload)) {
$effectiveMask = substr($effectiveMask,0,-1);
}
return $effectiveMask ^ $payload;
}
protected function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
//$this->disconnect($user); // todo: fail connection
return true;
}
return false;
}
protected function strtohex($str) {
$strout = "";
for ($i = 0; $i < strlen($str); $i++) {
$strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
$strout .= " ";
if ($i%32 == 7) {
$strout .= ": ";
}
if ($i%32 == 15) {
$strout .= ": ";
}
if ($i%32 == 23) {
$strout .= ": ";
}
if ($i%32 == 31) {
$strout .= "\n";
}
}
return $strout . "\n";
}
protected function printHeaders($headers) {
echo "Array\n(\n";
foreach ($headers as $key => $value) {
if ($key == 'length' || $key == 'opcode') {
echo "\t[$key] => $value\n\n";
} else {
echo "\t[$key] => ".$this->strtohex($value)."\n";
}
}
echo ")\n";
}
}
abstract class WebSocketServerStreamingWithFork {
protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
protected $maxBufferSize;
protected $timeout = 300;
protected $master;
protected $sockets                              = array();
protected $users                                = array();
protected $interactive                          = true;
protected $headerOriginRequired                 = false;
protected $headerSecWebSocketProtocolRequired   = false;
protected $headerSecWebSocketExtensionsRequired = false;
function __construct( $addr, $port, $bufferLength = 2048, $timeout = 300) {
$this->maxBufferSize = $bufferLength;
$this->timeout = $timeout;
$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
socket_set_nonblock( $this->master);
socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
socket_listen($this->master,20)                               or die("Failed: socket_listen()");
$this->sockets[] = $this->master;
echo "Server started\nListening on: $addr:$port\nMaster socket: " . $this->master . "\n";
$client = null;
while( true) {
usleep( 10000);
echo ' .';
$client = @socket_accept( $this->master);
if ( $client <= 0) continue;
echo " fork!";
// new client, fork!
$pid = pcntl_fork();
if ( $pid == -1) continue; 	// fork failed
if ( $pid == 0) break;	// client sockets served outside the while
}
// serve the client
$socket = $client;
socket_set_nonblock( $socket);
$user = new $this->userClass( uniqid(), $socket);
$this->connecting( $user); // notify class extension that a new client has entered
// do the handshake
$limit = 10; $status = -1; $msg = '';
while (  $limit--) {
$status = @socket_recv( $socket, $msg, $this->maxBufferSize, 0); // todo: if($numBytes === false) { error handling } elseif ($numBytes === 0) { remote client disconected }
if ( $status > 0) break;
usleep( 10000);
}
if ( ! $status) die( " Failed to get handshake from the other side!\n");
$this->doHandshake( $user, $msg); // sends reply to the handshake
$user->lastime = tsystem();	// for timeout
while( tsystem() - $user->lastime < $this->timeout) {	// 5min timeout on inactivity
if ( $this->tx( $user)) $user->lastime = tsystem();
$status = @socket_recv( $socket, $msg, $this->maxBufferSize, 0);
if ( $status <= 0) continue;
$msg = $this->deframe( $msg, $user);
$this->rx( $user, mb_convert_encoding( $msg, 'UTF-8'));
$user->lastime = tsystem();
}
// disconnect client socket
@socket_close( $socket);
$this->closed( $user); unset( $user);
die( " Done\n");
}
abstract protected function rx( $user, $message); // Calked immediately when the data is recieved.
abstract protected function tx( $user); // Calked immediately when the data is recieved.
abstract protected function connected( $user);        // Called after the handshake response is sent to the client.
abstract protected function closed($user);           // Called after the connection is closed.
protected function connecting( $user) {
// Override to handle a connecting user, after the instance of the User is created, but before
// the handshake has completed.
}
protected function send( $user, $message, $type = 'text') {
//$this->stdout("> $message");
$message = $this->frame( $message, $user, $type);
while ( strlen( $message)) {
$bytes = @socket_write( $user->socket, $message, strlen( $message));
$message = substr( $message, $bytes);
}
}
protected function disconnect( $socket,$triggerClosed=true) {
$foundUser = null;
$foundSocket = null;
$disconnectedUser = false;
foreach ($this->users as $key => $user) {
if ($user->socket == $socket) {
$foundUser = $key;
$disconnectedUser = $user;
break;
}
}
if ($foundUser !== null) {
unset($this->users[$foundUser]);
$this->users = array_values($this->users);
}
foreach ($this->sockets as $key => $sock) {
if ($sock == $socket) {
$foundSocket = $key;
break;
}
}
if ($foundSocket !== null) {
unset($this->sockets[$foundSocket]);
$this->sockets = array_values($this->sockets);
}
if ($triggerClosed && $disconnectedUser) $this->closed($disconnectedUser);
}
protected function doHandshake($user, $buffer) {
$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
$headers = array();
$lines = explode("\n",$buffer);
foreach ($lines as $line) {
if (strpos($line,":") !== false) {
$header = explode(":",$line,2);
$headers[strtolower(trim($header[0]))] = trim($header[1]);
} else if (stripos($line,"get ") !== false) {
preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
$headers['get'] = trim($reqResource[1]);
}
}
if (isset($headers['get'])) {
$user->requestedResource = $headers['get'];
} else {
// todo: fail the connection
$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
}
if (!isset($headers['host']) || !$this->checkHost($headers['host'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (!isset($headers['sec-websocket-key'])) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
} else {
}
if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
}
if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
$handshakeResponse = "HTTP/1.1 403 Forbidden";
}
if (($this->headerSecWebSocketProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerSecWebSocketProtocolRequired && !$this->checkWebsocProtocol($header['sec-websocket-protocol']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
if (($this->headerSecWebSocketExtensionsRequired && !isset($headers['sec-websocket-extensions'])) || ($this->headerSecWebSocketExtensionsRequired && !$this->checkWebsocExtensions($header['sec-websocket-extensions']))) {
$handshakeResponse = "HTTP/1.1 400 Bad Request";
}
// Done verifying the _required_ headers and optionally required headers.
if (isset($handshakeResponse)) {
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->disconnect($user->socket);
return false;
}
$user->headers = $headers;
$user->handshake = $buffer;
$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);
$rawToken = "";
for ($i = 0; $i < 20; $i++) {
$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
}
$handshakeToken = base64_encode($rawToken) . "\r\n";
$subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
$extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";
$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
$this->connected($user);
}
protected function checkHost($hostName) {
return true; // Override and return false if the host is not one that you would expect.
// Ex: You only want to accept hosts from the my-domain.com domain,
// but you receive a host from malicious-site.com instead.
}
protected function checkOrigin($origin) {
return true; // Override and return false if the origin is not one that you would expect.
}
protected function checkWebsocProtocol($protocol) {
return true; // Override and return false if a protocol is not found that you would expect.
}
protected function checkWebsocExtensions($extensions) {
return true; // Override and return false if an extension is not found that you would expect.
}
protected function processProtocol($protocol) {
return ""; // return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
// The carriage return/newline combo must appear at the end of a non-empty string, and must not
// appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
// the response body, which will trigger an error in the client as it will not be formatted correctly.
}
protected function processExtensions($extensions) {
return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
}
protected function getUserBySocket($socket) {
foreach ($this->users as $user) {
if ($user->socket == $socket) {
return $user;
}
}
return null;
}
protected function stdout($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function stderr($message) {
if ($this->interactive) {
echo "$message\n";
}
}
protected function frame($message, $user, $messageType='text', $messageContinues=false) {
switch ($messageType) {
case 'continuous':
$b1 = 0;
break;
case 'text':
$b1 = ($user->sendingContinuous) ? 0 : 1;
break;
case 'binary':
$b1 = ($user->sendingContinuous) ? 0 : 2;
break;
case 'close':
$b1 = 8;
break;
case 'ping':
$b1 = 9;
break;
case 'pong':
$b1 = 10;
break;
}
if ($messageContinues) {
$user->sendingContinuous = true;
} else {
$b1 += 128;
$user->sendingContinuous = false;
}
$length = strlen($message);
$lengthField = "";
if ($length < 126) {
$b2 = $length;
} elseif ($length <= 65536) {
$b2 = 126;
$hexLength = dechex($length);
//$this->stdout("Hex Length: $hexLength");
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 2) {
$lengthField = chr(0) . $lengthField;
}
} else {
$b2 = 127;
$hexLength = dechex($length);
if (strlen($hexLength)%2 == 1) {
$hexLength = '0' . $hexLength;
}
$n = strlen($hexLength) - 2;
for ($i = $n; $i >= 0; $i=$i-2) {
$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
}
while (strlen($lengthField) < 8) {
$lengthField = chr(0) . $lengthField;
}
}
return chr($b1) . chr($b2) . $lengthField . $message;
}
protected function deframe($message, $user) {
//echo $this->strtohex($message);
$headers = $this->extractHeaders($message);
$pongReply = false;
$willClose = false;
switch($headers['opcode']) {
case 0:
case 1:
case 2:
break;
case 8:
// todo: close the connection
$user->hasSentClose = true;
return "";
case 9:
$pongReply = true;
case 10:
break;
default:
//$this->disconnect($user); // todo: fail connection
$willClose = true;
break;
}
if ($user->handlingPartialPacket) {
$message = $user->partialBuffer . $message;
$user->handlingPartialPacket = false;
return $this->deframe($message, $user);
}
if ($this->checkRSVBits($headers,$user)) {
return false;
}
if ($willClose) {
// todo: fail the connection
return false;
}
$payload = $user->partialMessage . $this->extractPayload($message,$headers);
if ($pongReply) {
$reply = $this->frame($payload,$user,'pong');
socket_write($user->socket,$reply,strlen($reply));
return false;
}
if (extension_loaded('mbstring')) {
if ($headers['length'] > mb_strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
} else {
if ($headers['length'] > strlen($payload)) {
$user->handlingPartialPacket = true;
$user->partialBuffer = $message;
return false;
}
}
$payload = $this->applyMask($headers,$payload);
if ($headers['fin']) {
$user->partialMessage = "";
return $payload;
}
$user->partialMessage = $payload;
return false;
}
protected function extractHeaders($message) {
$header = array('fin'     => $message[0] & chr(128),
'rsv1'    => $message[0] & chr(64),
'rsv2'    => $message[0] & chr(32),
'rsv3'    => $message[0] & chr(16),
'opcode'  => ord($message[0]) & 15,
'hasmask' => $message[1] & chr(128),
'length'  => 0,
'mask'    => "");
$header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);
if ($header['length'] == 126) {
if ($header['hasmask']) {
$header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
}
$header['length'] = ord($message[2]) * 256
+ ord($message[3]);
} elseif ($header['length'] == 127) {
if ($header['hasmask']) {
$header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
}
$header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256
+ ord($message[3]) * 65536 * 65536 * 65536
+ ord($message[4]) * 65536 * 65536 * 256
+ ord($message[5]) * 65536 * 65536
+ ord($message[6]) * 65536 * 256
+ ord($message[7]) * 65536
+ ord($message[8]) * 256
+ ord($message[9]);
} elseif ($header['hasmask']) {
$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
}
//echo $this->strtohex($message);
//$this->printHeaders($header);
return $header;
}
protected function extractPayload($message,$headers) {
$offset = 2;
if ($headers['hasmask']) {
$offset += 4;
}
if ($headers['length'] > 65535) {
$offset += 8;
} elseif ($headers['length'] > 125) {
$offset += 2;
}
return substr($message,$offset);
}
protected function applyMask($headers,$payload) {
$effectiveMask = "";
if ($headers['hasmask']) {
$mask = $headers['mask'];
} else {
return $payload;
}
while (strlen($effectiveMask) < strlen($payload)) {
$effectiveMask .= $mask;
}
while (strlen($effectiveMask) > strlen($payload)) {
$effectiveMask = substr($effectiveMask,0,-1);
}
return $effectiveMask ^ $payload;
}
protected function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
//$this->disconnect($user); // todo: fail connection
return true;
}
return false;
}
protected function strtohex($str) {
$strout = "";
for ($i = 0; $i < strlen($str); $i++) {
$strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
$strout .= " ";
if ($i%32 == 7) {
$strout .= ": ";
}
if ($i%32 == 15) {
$strout .= ": ";
}
if ($i%32 == 23) {
$strout .= ": ";
}
if ($i%32 == 31) {
$strout .= "\n";
}
}
return $strout . "\n";
}
protected function printHeaders($headers) {
echo "Array\n(\n";
foreach ($headers as $key => $value) {
if ($key == 'length' || $key == 'opcode') {
echo "\t[$key] => $value\n\n";
} else {
echo "\t[$key] => ".$this->strtohex($value)."\n";
}
}
echo ")\n";
}
}
function stringexdocid( $doc) { return $doc[ '__docid']; } 

class StringexStats {

public $setup;
public $stats = array();
public function __construct( $setup) { $this->setup = $setup; if ( is_file( $setup->wdir . '/stats.json')) $this->stats = jsonload( $setup->wdir . '/stats.json'); }
public function add( $filename, $postats, $syncnow = true) {
$this->stats[ "$filename"] = $postats;
if ( $syncnow) $this->dump();
}
public function dump() { jsondump( $this->stats, $this->setup->wdir . '/stats.json'); }
}
class StringexSetup { // wdir, keyHashBits, keyHashMask, docHashBits, docHashMask, localSizeLimit, verbose 

public $wdir = '.';
public $keyHashBits = 16;
public $keyHashMask = 4;
public $docHashBits = 32;
public $docHashMask = 24;
public $localSizeLimit = 2000;	// in kb
public $smallcapsindex = true; // if true, will turn all text to small caps prior to calculating index -- needed for matches and search
public $verbose = false;
public $keys = array();
public function __construct( $h = array()) { foreach ( $h as $k => $v) $this->$k = is_numeric( $v) ? round( $v) : $v; }
public function ashash() { return get_object_vars( $this); }
public function makeys() { // automatically deducts keys from current contents of the stringex directory
$keys = array();
foreach ( flget( $this->wdir, '', '', 'imap') as $file) {
$k = str_replace( '.imap', '', $file);
if ( $k == 'docs') continue;
lpush( $keys, $k);
}
$this->keys = $keys;
}
}
class StringexMeta { //  { blockmask: { hashkey: object, ...}, ...}

protected $setup;
protected $name;
protected $h; //  { blockey(bk): { itemkey(ik): [ doc id, ...], ...}, ...}
protected $log = array();	// { time: { blockmask}, ..}
protected $blockstats = array();
protected $map = array(); // { filename: access count, ... }
protected $vmap = array(); // { value: ik, ...}
protected $imap = array(); // { bk: { ik: 'startpos,length', ...}, ... } -- for each subblock
private $stats;
public $details = array( 'size' => 0, 'reads' => 0, 'writes' => 0, 'readbytes' => 0, 'writebytes' => 0, 'blocks' => 0); 	// stats
public function __construct( $name, $setup, $stats) {
$this->setup = $setup;
$this->stats = $stats;
$this->name = $name;
$this->h = array();
extract( $setup->ashash()); // wdir
if ( is_file( "$wdir/$name.bmap")) $this->bmap = jsonload( "$wdir/$name.bmap");	// load current map of blocks
if ( is_file( "$wdir/$name.vmap")) $this->vmap = jsonload( "$wdir/$name.vmap");
if ( is_file( "$wdir/$name.imap")) $this->imap = jsonload( "$wdir/$name.imap");
}
protected function log( $bk, $bks) {
$time = tsystem();
unset( $this->log[ $bk]); $this->log[ $bk] = compact( ttl( 'bks,time'));
}
protected function itemkey( $k) {
//$k2 = cryptCRC24( bstring2bytes( $k, $this->setup->wdir));
$k1 = $this->setup->smallcapsindex ? strtolower( $k) : $k;
$k2 = cryptCRC24( $k1);
$k3 = btail( $k2 >> ( 24 - $this->setup->keyHashBits), $this->setup->keyHashBits);
$this->vmap[ "$k1"] = $k3;
return $k3;
}
protected function blockey( $ik) { return btail( $ik >> ( $this->setup->keyHashBits - $this->setup->keyHashMask), $this->setup->keyHashMask);}
protected function blockey2string( $bk) { return sprintf( '%0' . ( round( log10( round( b01( 32 - $this->setup->keyHashMask, $this->setup->keyHashMask)))) + 2) . 'd', $bk); }
protected function makeys( $k, $ik = null, $bk = null, $bks = null) {
if ( ! $ik) $ik = $this->itemkey( $k);
if ( ! $bk) $bk = $this->blockey( $ik);
if ( ! $bks) $bks = $this->blockey2string( $bk);
$name = $this->name; htouch( $this->map, "$name.$bks", 0, false, false);
$this->map[ "$name.$bks"]++;
return array( $ik, $bk, $bks, $this->setup->wdir, $this->name);
}
protected function updateblocksize( $bk) {
$size = 0;
if ( $this->setup->verbose) foreach ( $this->h[ $bk] as $ik => $h) {
$__ik = $ik;
foreach ( $h as $k => $docs) $h[ "$k"] = hk( $docs);
$docs = $h;
$size += strlen( h2json( compact( ttl( '__Ik,docs')), true, '', false, true));
}
$this->blockstats[ $bk] = $size;	// update block size
$this->details[ 'size'] = round( 0.001 * $size);
}
protected function fallback( $bk, $bks) { 	// if pointed check failes, fall back by reading the entire block
$wdir = $this->setup->wdir; $name = $this->name;
$in = finopen( "$wdir/$name.$bks");
while ( ! findone( $in)) {
list( $h, $p) = finread( $in); if ( ! $h) continue;
extract( $h); $ik = $__ik; // __ik, docs
htouch( $this->h, $bk);
htouch( $this->h[ $bk], $ik);
foreach ( $docs as $k => $docids) {
htouch( $this->h[ $bk][ $ik], $k);
foreach ( $docids as $docid) $this->h[ $bk][ $ik][ $k][ $docid] = true;
}
}
finclose( $in);
}
protected function check( $ik, $bk, $bks) {
$wdir = $this->setup->wdir; $name = $this->name;
if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ $ik])) return;
$imap = array(); if ( is_file( "$wdir/$name.imap")) $imap = jsonload( "$wdir/$name.imap");
if ( ! isset( $imap[ "$bk"])) return; 	// no such block in map
if ( ! isset( $imap[ "$bk"]) || ! isset( $imap[ "$bk"][ "$ik"])) return;	 // no such block in imap
htouch( $this->h, $bk); extract( lth( ttl( $imap[ "$bk"][ "$ik"]), ttl( 'start,end'))); // start, end
if ( $end <= $start) return $this->fallback( $bk, $bks);
$in = fopen( "$wdir/$name.$bks", 'r'); fseek( $in, ( int)$start);
$s = fread( $in, round( $end - $start));
fclose( $in);
$h = @json2h( trim( $s), true, null, true);
if ( ! $h) return $this->fallback( $bk, $bks);
extract( $h); $ik = $__ik; // __ik, docs
//die( " k#$__k, docs#" . jsonraw( $docs) . "\n");
htouch( $this->h, $bk);
htouch( $this->h[ $bk], $ik);
foreach ( $docs as $k => $docids) {
htouch( $this->h[ $bk][ $ik], $k);
foreach ( $docids as $docid) $this->h[ $bk][ $ik][ $k][ $docid] = true;
}
}
// interface
public function all() { 	// return all docids
$docs = array(); 	// docid: true
$wdir = $this->setup->wdir; $name = $this->name;
foreach ( $this->imap as $bk => $h) {
$bks = $this->blockey2string( $bk);
$in = finopen( "$wdir/$name.$bks");
while ( ! findone( $in)) {
list( $h, $p) = finread( $in); if ( ! $h) continue;
foreach ( $h[ 'docs'] as $k => $docids) foreach ( $docids as $docid) $docs[ "$docid"] = true;
}
finclose( $in);
}
return hk( $docs);
}
public function find( $k = null, $docid = null, $ik = null, $bk = null, $bks = null) { // null | [ docids] | { bk, ik}
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
$this->check( $ik, $bk, $bks);
if ( ! isset( $this->h[ $bk])) return null;
if ( ! isset( $this->h[ $bk][ $ik])) return null;
if ( ! isset( $this->h[ $bk][ $ik][ "$k"])) return null;
return hk( $this->h[ $bk][ $ik][ "$k"]);	// list of docs
}
public function add( $k, $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) {
//echo "\nMeta.add()\n";
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
$h = $this->find( $k, $docid, $bk, $bks);
htouch( $this->h, $bk);
htouch( $this->h[ $bk], $ik);
htouch( $this->h[ $bk][ $ik], "$k");
$this->h[ $bk][ $ik][ "$k"][ "$docid"] = true;
$this->log( $bk, $bks);
$this->updateblocksize( $bk);
//die( jsonraw( $this->blockstats));
$this->details[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
if ( $syncnow) $this->sync( tsystem());
}
public function purge( $k, $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) { // if k and docid are set, removes only docid
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
$h = $this->find( $k, $docid, $bk, $bks);
htouch( $this->h, $bk);
htouch( $this->h[ $bk], $ik);
htouch( $this->h[ $bk][ $ik], "$k");
unset( $this->h[ $bk][ $ik][ "$k"][ "$docid"]);
if ( ! count( $this->h[ $bk][ $ik][ "$k"])) unset( $this->h[ $bk][ $ik][ "$k"]); 	// no more docs for this k
if ( ! count( $this->h[ $bk][ $ik])) unset( $this->h[ $bk][ $ik]);
if ( ! count( $this->h[ $bk])) { unset( $this->h[ $bk]); unset( $this->map[ "$name.$bks"]); unset( $this->imap[ $bk]); } // remove block from the map as well
$this->log( $bk, $bks);
$this->updateblocksize( $bk);
}
public function sync( $time2 = 'one', $emulate = false) { // write all changes to disk -- returns earliest time
//echo "\nMeta.sync()\n";
if ( $time2 == 'one' && count( $this->log)) $time2 = mmin( hltl( hv( $this->log), 'time'));	// pop only one
if ( $time2 == 'one' || ! $time2) $time2 = tsystem();
//echo " META SYNC ($time2): " . jsonraw( $this->log) . "\n";
$wdir = $this->setup->wdir; $name = $this->name;
// first, dump the map
arsort( $this->map, SORT_NUMERIC); jsondump( $this->map, "$wdir/$name.bmap");
if ( $this->vmap) { ksort( $this->vmap); jsondump( $this->vmap, "$wdir/$name.vmap"); }
//echo "\n\n"; echo $this->name . '  log: ' . jsonraw( $this->log) . "\n";
foreach ( hk( $this->log) as $bk) {
$bk = round( $bk);
extract( $this->log[ $bk]); // bks, time
if ( $time > $time2) continue;	// skip this one, too early
unset( $this->log[ $bk]);
// first, load all missing iks in BK
$in = null; if ( is_file( "$wdir/$name.$bks")) $in = finopen( "$wdir/$name.$bks");
while ( $in && ! findone( $in)) {
list( $h, $p) = finread( $in); if ( ! $h) continue;
extract( $h); $ik = $__ik; // __ik, docs
htouch( $this->h, $bk);
htouch( $this->h[ $bk], $ik);
//if ( isset( $this->h[ $bk][ $ik])) continue;	// do not overwrite changed places
foreach ( $docs as $k => $vs) {
htouch( $this->h[ $bk][ $ik], $k);
foreach ( $vs as $docid) $this->h[ $bk][ $ik][ $k][ $docid] = true;
}
}
if ( $in) finclose( $in);
// write the block back to the filesystem
$this->details[ 'writes']++;
if ( ! isset( $this->h[ $bk])) { `rm -Rf $wdir/$name.$bks`; continue; }
$out = foutopen( "$wdir/$name.$bks", 'w'); $postats = array(); $this->imap[ $bk] = array(); $imap = 0;
foreach ( $this->h[ $bk] as $ik => $h) {
foreach ( $h as $k => $docs) if ( ! strlen( trim( $k))) unset( $h[ $k]);
foreach ( $h as $k => $docs) foreach ( $docs as $k2 => $docid) if ( ! strlen( trim( $k2))) unset( $h[ $k][ $k2]);
foreach ( $h as $k => $docs) $h[ $k] = hk( $docs);
$docs = $h; $__ik = $ik;
foutwrite( $out, compact( ttl( '__ik,docs')));
lpush( $postats, $out[ 'bytes']);
$this->imap[ $bk][ $ik] = "$imap," . ( $out[ 'bytes'] - $imap); $imap = $out[ 'bytes']; // position, length
}
$this->details[ 'writebytes'] += $out[ 'bytes'];
$this->stats->add( "$name.$bks", $postats, false);
foutclose( $out); unset( $this->h[ $bk]);
unset( $this->h[ $bk]); $this->blockstats[ $bk] = 0;
}
$this->details[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
if ( ! count( $this->h)) $this->details[ 'size'] = 0;
$this->stats->dump();
jsondump( $this->imap, "$wdir/$name.imap");	// ik positions in blocks
return count( $this->log) ? mmin( hltl( hv( $this->log), 'time')) : tsystem();
}
public function stats() { $this->details[ 'blocks'] = count( $this->h); return $this->details; }
public function vmap() { return $this->vmap; }
public function imap() { return $this->imap; }
}
class StringexDocs extends StringexMeta { //  { blockmask: { hashkey: object, ...}, ...}

protected $setup;
protected $name;
protected $h; //  { blockey(bk): { docid: { doc hash + __docid}, ...}, ...}
protected $log = array();	// { time: { blockmask}, ..}
protected $blockstats = array();
private $stats;
public $details = array( 'size' => 0, 'reads' => 0, 'writes' => 0, 'readbytes' => 0, 'writebytes' => 0); 	// stats
public function __construct( $setup, $stats) {
$this->setup = $setup;
$this->stats = $stats;
$this->name = 'docs';
$this->h = array();
extract( $setup->ashash()); $name = $this->name; // wdir
if ( is_file( "$wdir/$name.bmap")) $this->map = jsonload( "$wdir/$name.bmap");	// load current map of blocks
if ( is_file( "$wdir/$name.imap")) $this->imap = jsonload( "$wdir/$name.imap");
}
protected function updateblocksize( $bk) {
$size = 0;
if ( $this->setup->verbose) foreach ( $this->h[ $bk] as $docid => $doc) $size += strlen( h2json( $doc, true, '', false, true));
$this->blockstats[ $bk] = $size;	// update block size
$this->details[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
}
protected function blockey( $ik) { return btail( $ik >> ( $this->setup->docHashBits - $this->setup->docHashMask), $this->setup->docHashMask);}
protected function fallback( $bk, $bks) { 	// if pointed check failes, fall back by reading the entire block
//echo "FALLBACK\n";
$wdir = $this->setup->wdir; $name = $this->name;
$in = finopen( "$wdir/$name.$bks");
while ( ! findone( $in)) {
list( $h, $p) = finread( $in); if ( ! $h) continue;
$docid = $h[ '__docid'];
htouch( $this->h, $bk);
$this->h[ $bk][ $docid] = $h;
}
finclose( $in);
}
protected function check( $ik, $bk, $bks) {
$wdir = $this->setup->wdir; $name = $this->name;
//echo "CHECK(bk=$bk,bks=$bks)\n";
if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ $ik])) return;
$imap = array(); if ( is_file( "$wdir/$name.imap")) $imap = jsonload( "$wdir/$name.imap");
//echo jsonraw( $imap) . "\n";
if ( ! isset( $imap[ "$bk"])) return; 	// no such block in map
if ( ! isset( $imap[ "$bk"]) || ! isset( $imap[ "$bk"][ "$ik"])) return;	 // no such block in imap
htouch( $this->h, $bk); extract( lth( ttl( $imap[ "$bk"][ "$ik"]), ttl( 'start,end'))); // start, end
//echo "start#$start end#$end\n";
if ( $end <= $start) return $this->fallback( $bk, $bks);
$in = fopen( "$wdir/$name.$bks", 'r'); fseek( $in, ( int)$start);
$s = fread( $in, round( $end - $start));
//echo strlen( $s) . "\n";
fclose( $in);
$doc = @json2h( trim( $s), true, null, true);
if ( ! $doc || ! is_array( $doc) || ! isset( $doc[ '__docid'])) return $this->fallback( $bk, $bks);
//echo jsonraw( hk( $doc)) . "\n";
$this->h[ $bk][ $doc[ '__docid']] = $doc;
}
// interface
public function get( $docid, $bk = null, $bks = null) { // null | doc hash
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $docid);
$this->check( $docid, $bk, $bks);
if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ "$docid"])) return $this->h[ $bk][ "$docid"];	// doc
return null;
}
public function set( $docid, $doc, $syncnow = false, $ik = null, $bk = null, $bks = null) {
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $docid);
//echo "  SET(docid=$docid,ik=$ik,bk=$bk,bks=$bks)\n";
$h = $this->get( $docid, $bk, $bks);
if ( ! $h) $h = array();
//echo "docs.set() old doc: " . jsonraw( hk( $h)) . "\n";
$h = hm( $h, $doc); $h = hm( $h, array( '__docid' => $docid));
//echo "docs.set() tags after merger : " . jsonraw( $h[ 'tags']) . "\n";
htouch( $this->h, $bk);
$this->h[ $bk][ "$docid"] = $h;
$this->updateblocksize( $bk);
$this->log( $bk, $bks);	// new doc, mark the log
if ( $syncnow) $this->sync( tsystem());
}
public function purge( $k, $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) { // $k is only for compatibility
list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $docid);
$h = $this->get( $docid, $bk, $bks);
if ( ! $h) return;		// no doc, nothing to do
$this->log( $bk, $bks);
unset( $this->h[ $bk][ "$docid"]);
if ( ! count( $this->h[ $bk])) { unset( $this->h[ $bk]); unset( $this->map[ "$name.$kbs"]); unset( $this->imap[ $bk]);  }
$this->updateblocksize( $bk);
}
public function sync( $time2 = 'one', $emulate = false) { // write all changes to disk
if ( $time2 == 'one' && count( $this->log)) $time2 = mmin( hltl( hv( $this->log), 'time'));	// pop only one
if ( $time2 == 'one' || ! $time2) $time2 = tsystem();
//echo " SYNC($time2) : " . jsonraw( $this->log) . "\n\n";
$wdir = $this->setup->wdir; $name = $this->name;
// first, dump the map
arsort( $this->map, SORT_NUMERIC); jsondump( $this->map, "$wdir/$name.bmap");
//echo "\n\n"; echo jsonraw( $this->log)  . "\n";
//echo "docs.sync() log: " . jsonraw( $this->log) . "\n";
foreach ( hk( $this->log) as $bk) {
extract( $this->log[ $bk]); // bks, time
if ( $time > $time2) continue;	// skip this one, too early
unset( $this->log[ $bk]);
//echo "docs.sync() about to start writing\n";
// first, load all missing iks in BK
$in = null; if ( is_file( "$wdir/$name.$bks")) $in = finopen( "$wdir/$name.$bks");
while ( $in && ! findone( $in)) {
list( $doc, $p) = finread( $in); if ( ! $doc) continue;
$docid = trim( $doc[ '__docid']);
if ( ! isset( $this->h[ $bk][ $docid])) $this->h[ $bk][ $docid] = $doc;
}
if ( $in) finclose( $in);
//echo "docs.sync() filled in all remaining keys\n";
// write the block back to the filesystem
$this->details[ 'writes']++;
if ( ! isset( $this->h[ $bk])) { `rm -Rf $wdir/$name.$bks`; continue; }
$out = foutopen( "$wdir/$name.$bks", 'w'); $postats = array(); $this->imap[ $bk] = array(); $imap = 0;
foreach ( $this->h[ $bk] as $docid => $doc) {
foutwrite( $out, $doc); if ( $out[ 'bytes'] <= $imap) die( " ERROR! StringexDocs.sync($name.$bks) -- foutwrite() did not advance position -- imap#$imap fout(" . htt( $out) . ")\n");
lpush( $postats, $out[ 'bytes']);
$this->imap[ $bk][ $docid] = "$imap," . ( $out[ 'bytes'] - $imap); $imap = $out[ 'bytes'];
}
$this->details[ 'writebytes'] += $out[ 'bytes'];
$this->stats->add( "$name.$bks", $postats, false);
unset( $this->h[ $bk]);
$this->blockstats[ $bk] = 0;
foutclose( $out);
}
if ( ! count( $this->h)) $this->details[ 'size'] = 0;
$this->details[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
$this->stats->dump();
jsondump( $this->imap, "$wdir/$name.imap");
return count( $this->log) ? mmin( hltl( hv( $this->log), 'time')) : tsystem();
}
}
class Stringex { 

public $setup;
public $keys = array();
public $keyreg = array();	// { key: write access count, ...}
public $docs;
private $stats;
public $fout4logs = null;	// for backup mode
public function __construct( $setup = null) { // if setup is string, then it is a wdir
global $STRINGEXDIR; if ( ! isset( $setup) || ! $setup) $setup = $STRINGEXDIR;
if ( is_string( $setup)) $wdir = $setup; else $wdir = $setup->wdir;
if ( ! is_dir( $wdir)) mkdir( $wdir);
if ( ! is_dir( $wdir)) die( "ERROR! Stringex:__construct() cannot find [$wdir]\n");
`chmod -R 777 $wdir`;
if ( ! is_object( $setup)) $setup = new StringexSetup( is_file( "$wdir/setup.json") ? jsonload( "$wdir/setup.json") : array());
$this->setup = $setup;
$setup->wdir = $wdir;
$this->stats = new StringexStats( $setup);
$this->docs = new StringexDocs( $setup, $this->stats);
if ( is_string( $setup->keys)) $setup->keys = ttl( $setup->keys);
foreach ( $setup->keys as $k) $this->keys[ $k] = new StringexMeta( $k, $setup, $this->stats);
jsondump( $setup->ashash(), "$wdir/setup.json");
}
// stats
public function details() {
$stats = array();
foreach ( $this->keys as $k => $K) {
if ( ! $stats) $stats = $K->details();
else foreach ( $K->details() as $k2 => $v2) $stats[ $k2] += $v2;
}
foreach ( $this->docs->details() as $k => $v) $stats[ $k] += $v;
return $stats;
}
public function count() { return -1; }
// high-level interface
public function getkeys() { return hk( $this->keys); }
public function get( $docids, $h = null) {	// return docs for ids -- if ( h) verifies the input
$L = array();
if ( is_string( $docids)) $docids = ttl( $docids);
foreach ( $docids as $docid) {
$h2 = $this->docs->get( $docid); if ( ! $h2) continue;	// just skip it
//if ( ! $h2) die( "ERROR! Stringex:find() Doc($docid) not found in docs! Should not happen.\n");
if ( ! $h) { lpush( $L, $h2); continue; }
$ok = true;
foreach ( $h as $k => $v) {
if ( ! isset( $h2[ $k])) { $ok = false; break; }
$v2 = is_array( $h2[ $k]) ? ltt( $h2[ $k], ' ') : $h2[ $k];
if ( strpos( $v2, $v) === false) { $ok = false; break; }
}
if ( $ok) lpush( $L, $h2);
}
return $L;
}
public function find( $h, $idonly = false) { // null | list of docs(+__docid)  -- search as intersection of keys
$H = array(); if ( is_string( $h)) $h = tth( $h);
foreach ( $h as $k => $v) {
if ( ! isset( $this->keys[ $k])) continue;
$docs = $this->keys[ $k]->find( $v);
//echo "found (" . count( $docs) . ") docs\n"; jsondump( $docs, 'temp.json'); die( "\n");
//echo " DOCS($k=$v): " . jsonraw( $docs) . "\n";
if ( ! $docs) return null;	// no such docs
if ( ! count( $H)) { $H = hvak( $docs, true, true); continue; } 	// first list
foreach ( $docs as $docid) if ( ! isset( $H[ "$docid"])) unset( $H[ "$docid"]);
$docs = hvak( $docs, true, true); foreach ( hk( $H) as $docid) if ( ! isset( $docs[ "$docid"])) unset( $H[ "$docid"]);
if ( ! count( $H)) return null; 	// no matches
}
if ( ! count( $H)) return null;
if ( $idonly) return hk( $H);
return $this->get( hk( $H));
}
public function add( $h, $syncnow = false) { // update if $h[ '__docid'] is set
//echo "\nMain.add()\n";
if ( ! isset( $h[ '__docid'])) { $docid = md5( jsonraw( $h)); $h[ '__docid'] = $docid; }
$docid = trim( $h[ '__docid']);
$this->docs->set( $docid, $h, $syncnow);
if ( $this->fout4logs) foutwrite( $this->fout4logs, $h);
foreach ( $h as $k => $v) {
if ( $k == '__docid') continue;
if ( ! isset( $this->keys[ $k])) continue;
if  ( ! $v) continue;	// no information in this key
if ( is_string( $v) || is_numeric( $v)) $v = array( $v);
//if ( ! is_array( $v)) die( " bad v[". jsonraw( $v) . "]\n");
foreach ( $v as $v2) $this->keys[ $k]->add( $v2, $docid, $syncnow);
}
return $docid;
}
public function update( $doc, $syncnow = false) { 	// first remove this doc from keys which have changed values, then add()
$docid = $doc[ '__docid'];
$oldoc = lshfit( $this->get( "$docid"));	// old document
$changed = array();
foreach ( $doc as $k => $newvs) {
if ( ! isset( $oldoc[ $k])) continue;	// new key
$oldvs = $oldoc[ $k];
if ( ! is_array( $newvs)) $newvs = array( $newvs);
if ( ! is_array( $oldvs)) $oldvs = array( $oldvs);
$newvs = hvak( $newvs, true, true);
$oldvs = hvak( $oldvs, true, true);
foreach ( $oldvs as $k2 => $v2) if ( ! isset( $newvs[ $k2])) $changed[ $k] = $k2; // this value for this key is not present in the new doc
}
foreach ( $changed as $k => $oldv) if ( isset( $this->keys[ $k])) $this->keys[ $k]->purge( $oldv, $docid, $syncnow);
$this->add( $doc, $syncnow);	// update in normal mode
}
public function purge( $h, $syncnow = false) { foreach ( $h as $k => $v) {
if ( $k == '__docid') continue;
if ( ! $v) continue;
if ( is_string( $v)) $v = array( $v);
foreach ( $v as $v2) $this->keys[ $k]->purge( $v2, $h[ '__docid'], $syncnow);
}; $this->docs->purge( null, $h[ '__docid'], $syncnow); }
public function commit( $time2 = null) { 	// commit all changes to disk
$wdir = $this->setup->wdir;
if ( ! $time2) $time2 = tsystem();
foreach ( $this->keys as $k => $K) $K->sync( $time2);
$this->docs->sync( $time2);
jsondump( $this->setup->ashash(), "$wdir/setup.json");
}
public function log( $type, $docid) {
$wdir = $this->setup->wdir;
$log = array(); if ( is_file( "$wdir/AAA.LOG")) $log = jsonload( "$wdir/AAA.LOG");
$log[ '' . round( tsystem())] = compact( ttl( 'type,docid'));
jsondump( $log, "$wdir/AAA.LOG");
}
}
class BayesClassifier {

public $statefile;
public $homedir;
public $wdir;	// working directory -- should be empty becauase will be popualted and erased repeatedly
public function __construct( $wdir, $statefile) {
$this->wdir = $wdir;
$this->statefile = $statefile;
$this->homedir = getcwd();
}
public function train( $class, $stuff) {	// no return
$wdir = $this->wdir; $statefile = $this->statefile; $homedir = $this->homedir;
if ( ! is_dir( "$wdir")) mkdir( "$wdir");
`rm -Rf $wdir/*`; $CDIR = getcwd();  // */
mkdir( "$wdir/$class"); chdir( "$wdir/$class");
$out = fopen( '0000', 'w'); fwrite( $out, $stuff); fclose( $out);
chdir( $wdir);
$c = "php /code/makascripts/classifiers/bayes.php train $statefile .";
procpipe( $c);
chdir( $CDIR); `rm -Rf $wdir/*`; // */
}
public function classify( $stuff) { // returns [ most likely class, less likely class, ...]
$wdir = $this->wdir; $statefile = $this->statefile; $homedir = $this->homedir;
if ( ! is_dir( "$wdir")) mkdir( "$wdir");
`rm -Rf $wdir/*`; $CDIR = getcwd(); // */
$out = fopen( "$wdir/0000", 'w'); fwrite( $out, $stuff); fclose( $out);
chdir( $wdir);
$c = "php /code/makascripts/classifiers/bayes.php classify $statefile .";
//echo " c[$c]\n";
$lines = procpipe( $c);
//die( jsonraw( $lines));
foreach ( $lines as $line) {
$line = trim( $line); if ( ! $line) continue;
$L = ttl( $line, '---'); if ( count( $L) < 2) continue;
$name = lshift( $L); if ( $name != '0000') continue;
return $L;
}
chdir( $CDIR);
return array();
}
}
class ClassifierStateStore { // each store is a file:   key --- base64( bzip2( content)) 

public function store( $name, $statefile, $storefile) {
$in = fopen( $statefile, 'r'); $state = fread( $in, filesize( $statefile)); fclose( $in);
$h = array();
foreach ( file( $storefile) as $line) {
$line = trim( $line); if ( ! $line) continue;
$L = ttl( $line, '---'); if ( count( $L) != 2) continue;
$key = lshift( $L); $value = lshift( $L);
$h[ "$key"] = $value;
}
$h[ "$name"] = s2s64( bzcompress( $state));
$out = fopen( $storefile, 'w');
foreach ( $h as $k => $v) fwrite( $out, "$k --- $v\n");
fclose( $out);
}
public function get( $name, $storefile) { 	// returns state -- note: meaningless if not in a file (possibly)
$h = array();
foreach ( file( $storefile) as $line) {
$line = trim( $line); if ( ! $line) continue;
$L = ttl( $line, '---'); if ( count( $L) != 2) continue;
$key = lshift( $L); $value = lshift( $L);
if ( $key == $name) return bzdecompress( s642s( $value));
}
return null;
}
public function restore( $name, $storefile, $statefile) { // returns state just in case
$state = $this->get( $name, $storefile);
if ( ! $state) die( " ERROR! ClassifierStateStore:restore()  no state for name#$name at statefile#$statefile\n");
$out = fopen( $statefile, 'w'); fwrite( $out, $state); fclose( $out);
return $state;
}
}
class RebotPaper { // extracts 

public $wdir;
public function __construct( $wdir) {
$this->wdir = $wdir;
}
// raw functions
public function parsetex1line( $file, $key) { $end = null; foreach ( file( $file) as $line) {
if ( $end) break;
$line = trim( $line); if ( ! $line) continue;
if ( strpos( $line, $key) !== false) {
$line = strcleanup( $line, '\\');
$line = strcleanup( $line, '{}', ' --- ');
$L = ttl( $line, '---'); $end = trim( lpop( $L));
break;
}
}; return $end ? trim( $end) : ''; }
public function parsetexNline( $file, $key, $skip) { $end = null; $lines = file( $file); while ( count( $lines)) {
if ( $end) break;
$line = trim( lshift( $lines)); if ( ! $line) continue;
if ( strpos( $line, $key) !== false) {
for ( $i = 0; $i < $skip; $i++) $line = trim( lshift( $lines));
$line = strcleanup( $line, '\\');
$line = strcleanup( $line, '{}', ' --- ');
$L = ttl( $line, '---'); $end = trim( lpop( $L));
break;
}
}; return $end ? trim( $end) : ''; }
public function cleanup( $s) {
for ( $i = 0; $i < 10; $i++) $s = str_replace( '  ', ' ', $s);
return $s;
}
// interface
public function title( $notfound = '') {
$wdir = $this->wdir;
$title = null;
foreach ( ttl( 'paper.tex,chapter.tex') as $target) {
if ( ! is_file( "$wdir/$target")) continue;
if ( ! $title) $title = $this->parsetex1line( "$wdir/$target", 'title{'); // }
if ( ! $title) $title = $this->parsetexNline( "$wdir/$target", 'myheader', 1);
if ( $title) break;
}
return $title ? trim( $this->cleanup( $title)) : $notfound;
}
public function authors( $notfound = '') {
$wdir = $this->wdir;
$s = null;
foreach ( ttl( 'slides.tex') as $target) {
if ( ! is_file( "$wdir/$target")) continue;
if ( ! $s) $s = $this->parsetex1line( "$wdir/$target", 'author{'); // }
if ( $s) break;
}
$L = null; if ( $s) $L = ttl( $s, ' ');
if ( $L) foreach ( $L as $k => $v) if ( strpos( $v, '@') !== false) unset( $L[ $k]);
if ( $L) $s = ltt( $L, ' ');
return $s ? trim( $this->cleanup( $s)) : $notfound;
}
public function tags( $notfound = '') {
$wdir = $this->wdir;
$s = null;
foreach ( ttl( 'paper.tex,chapter.tex') as $target) {
if ( ! is_file( "$wdir/$target")) continue;
if ( ! $s) $s = $this->parsetex1line( "$wdir/$target", 'jk{'); // }
if ( ! $s) $s = $this->parsetex1line( "$wdir/$target", 'keywords{'); // }
if ( ! $s) $s = $this->parsetexNline( "$wdir/$target", 'myheader', 3);
if ( $s) break;
}
return $s ? trim( $this->cleanup( $s)) : $notfound;
}
public function summary( $notfound = '') {
$wdir = $this->wdir;
$s = null;
foreach ( ttl( 'paper.tex,chapter.tex') as $target) {
if ( ! is_file( "$wdir/$target")) continue;
if ( ! $s) $s = $this->parsetex1line( "$wdir/$target", 'ja{'); // }
if ( ! $s) $s = $this->parsetex1line( "$wdir/$target", 'abstract{'); // }
if ( ! $s) $s = $this->parsetexNline( "$wdir/$target", 'myheader', 2);
if ( $s) break;
}
return $s ? trim( $this->cleanup( $s)) : $notfound;
}
}
class RebotImage { 

public $exiftool; 	// path to exiftool executable
public $filedir;
public $filename;
public function __construct( $filepath) { // path to the main path itself
$this->exiftool = procfindexec( 'exiftool');
extract( fpathparse( $filepath)); // filepath, filename
$this->filedir = $filepath;
$this->filename = $filename;
}
// exit raw
public function exif() {
if ( ! $this->exiftool) return array();	// no info
$CDIR = getcwd(); chdir( $this->filedir);
$c = $this->exiftool . ' ' . $this->filename;
$L = procpipe( $c); $h = array();
foreach ( $L as $line) {
$line = trim( $line); if ( ! $line) continue;
$L2 = ttl( $line, ' : '); if ( count( $L2) < 2) continue;
$k = str_replace( ' ', '', trim( lshift( $L2)));
$k = str_replace( '/', '', $k);
$k = str_replace( '\\', '', $k);
$v = ltt( $L2, ' : ');
$h[ $k] = $v;
}
chdir( $CDIR);
//jsondbg( $h);
return $h;
}
public function filesize( $raw = null, $roundup = 1) { // in kbps
if ( ! $raw) $raw = $this->exif();
$number = ( double)$raw[ 'FileSize'];
$h = tth( 'kB=1,Kb=1,KB=1,Mb=1000,MB=1000,mB=1000');
$letter = trim( str_replace( "$number", '', $raw[ 'FileSize']));
if ( ! isset( $h[ "$letter"]))  $letter = lr( hk(( $h)));
$index = $h[ "$letter"]; $number *= $index;
return ( $roundup * ( int)( $number / $roundup)) . 'kb';
}
public function camera( $raw = null) { // model name
if ( ! $raw) $raw = $this->exif();
$k = 'CameraModelName';
return isset( $raw[ "$k"]) ? $raw[ "$k"] : '';
}
public function created( $raw = null) { // returns { yyyy, yyyymm, yyyymmdd, XXh}
if ( ! $raw) $raw = $this->exif();
$k = 'CreateDate';
if ( ! isset( $raw[ $k])) $k = 'ModifyDate';
if ( ! isset( $raw[ "$k"])) return array();
$s = $raw[ "$k"];
$date = str_replace( '-', '', tsdate( $s)); $date = str_replace( ':', '', $date);
$time = str_replace( ':', '', tstime( $s)); $time = str_replace( '.', '', $time);
$yyyy = substr( "$date", 0, 4);
$yyyymm = substr( "$date", 0, 6);
$yyyymmdd = "$date";
$hour = substr( "$time", 0, 2) . 'h';
return compact( ttl( 'yyyy,yyyymm,yyyymmdd,hour'));
}
public function dimensions( $raw = null) { // WxH
if ( ! $raw) $raw = $this->exif();
$k = 'ImageSize';
return isset( $raw[ "$k"]) ? $raw[ "$k"] : '0x0';
}
// interface
public function profile() { 	// { exif: { filesize, camera, created, dimensions}, ... }
$raw  = $this->exif(); $h = array();
foreach ( ttl( 'filesize,camera,created,dimensions') as $k) { $v = $this->$k( $raw); $h2 = array(); if ( ! is_array( $v)) $h2[ "$k"] = $v; else $h2 = $v; $h = hm( $h, $h2); }
return $h;
}
}


?>
