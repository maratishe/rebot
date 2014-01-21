<?php

// Full-Text Indexing in stringent environments
// NOTE: Not supposed to be a search engine, need it for full-string database-style indexing
// NOTE2: prefers to work with folders
// start: new Stringex( new StringexSetup( { wdir, keyHashBits, keyHashMask, docHashBits, docHashMask, localSizeLimit, verbose})); 
// 		* all setup keys are optional, will set to default values if not set
//         ** will not overwrite (do it manually)
// Advices/howtos: 
//   (1) add( h, TRUE)    and   purge( h, TRUE)    to dump changes immediately
//   (2) when delaying commits, commit( oldest time)
//   (3) search: find( { key: value, ... })    -- not substrings, the match has to be perfect
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


?>