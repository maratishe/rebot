<?php
// uses bayes.php in /code/makascripts/classifiers
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

?>