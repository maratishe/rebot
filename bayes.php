<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
// php nbayes.php mode(train|classify|top-terms) db(state file)
//$ php nbayes.php --train TRAINDIR --db DBFILE
//$ php nbayes.php --classify TESTDIR --db DBFILE
//$ php nbayes.php --top-terms [-n N] --db DBFILE
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit();
clhelp( '[mode]  train | classify | top-terms');
clhelp( '[db] abs path to the state file');
clhelp( '[dir] none | path to the directory with files');
htg( clget( 'mode,db,dir'));


class NaiveBayes {
    /**
     * A mapping from terms to another mapping, which goes from each class to 
     * the number of times the term has been seen in that class.
     * @var array
     */
    protected $termCounts = array();

    /**
     * A mapping from each class to the number of terms that have been observed 
     * in that class.
     * @var array
     */
    protected $termTotals = array();

    /**
     * The total number of distinct terms seen.
     * @var int
     */
    protected $termGrandTotal = 0;

    /**
     * A mapping from each class to the number of documents that have been seen 
     * in that class.
     * @var array
     */
    protected $docCounts = array();

    /**
     * The total number of documents seen.
     * @var int
     */
    protected $docTotal = 0;

    /**
     * Train an instance labeled as a particular class.
     *
     * @param array $instance An array of terms which have already had feature 
     * selection performed on them.
     * @param string $class The label for this particular instance
     */
    public function trainInstance($instance, $class)
    {
        foreach ($instance as $term) {
            if (!isset($this->termCounts[$term])) {
                $this->termCounts[$term] = array();
                $this->termCounts[$term][$class] = 1;
            } else if (!isset($this->termCounts[$term][$class])) {
                $this->termCounts[$term][$class] = 1;
            } else {
                $this->termCounts[$term][$class] += 1;
            }

            if (!isset($this->termTotals[$class])) {
                $this->termTotals[$class] = 1;
            } else {
                $this->termTotals[$class] += 1;
            }

            $this->termGrandTotal += 1;
        }

        if (!isset($this->docCounts[$class])) {
            $this->docCounts[$class] = 1;
        } else {
            $this->docCounts[$class] += 1;
        }

        $this->docTotal += 1;
    }

    /**
     * Classify an instance and return the name of the best class.
     *
     * It only makes sense to try classification after at least some training 
     * has been done on each possible class.
     *
     * @param array $instance An array of terms which have already had feature 
     * selection performed on them.
     * @return string The class with the greatest posterior probability given 
     * the instance.
     */
    public function classify($instance) {
        $classes = array_keys($this->docCounts);

        $maxScore = -INF;
        $bestClass = '';
        $ranking = array();	// { class: rank} // marat
        foreach ($classes as $class) {
            $score = log($this->termTotals[$class] / $this->termGrandTotal);
            foreach ($instance as $term) {
                $score += $this->calculateTermScore($term, $class);
            }
	   $ranking[ "$class"] = $score;
            //if ($score > $maxScore) {
            //   $maxScore = $score;
            //    $bestClass = $class;
            //}
        }
        //die( jsonraw( $ranking));
        arsort( $ranking, SORT_NUMERIC); return array_keys( $ranking); 
        //return $bestClass;
    }

    /**
     * Return an associative array mapping each class to an ordered list of 
     * its top terms in decreasing order by weight.
     *
     * The top terms are only tracked if the 'reporting' option is enabled when 
     * the classifier is constructed; the number of terms to keep track of is 
     * determined by the 'keepTopN' option.
     *
     * @return array The top terms by class.
     */
    public function recoverTopTerms($keepTopN)
    {
        // Keep track of top terms using a minheap, so that keeping track of 
        // the worst top term is efficient. There's one minheap per class 
        // because term scores are actually contributions to the posterior 
        // probability that a document in which the term occurs belongs to a 
        // particular class (thus the term score depends on the putative 
        // class).
        $classes = array_keys($this->docCounts);
        $topTerms = array();
        foreach ($classes as $class)
            $topTerms[$class] = new SplMinHeap();

        // Go through terms in sorted order so that results are stable as we 
        // keep more top terms, even if all of the top terms have the same 
        // score.
        $terms = array_keys($this->termCounts);
        rsort($terms);

        foreach ($terms as $term) {
            foreach ($this->termCounts[$term] as $class => $count) {
                $termScore = $this->calculateTermScore($term, $class);
                if ($topTerms[$class]->count() < $keepTopN) {
                    $topTerms[$class]->insert(array($termScore, $term));
                } else {
                    list($minScore, $minTerm) = $topTerms[$class]->top();
                    if ($termScore > $minScore) {
                        $topTerms[$class]->extract();
                        $topTerms[$class]->insert(array($termScore, $term));
                    }
                }
            }
        }

        $results = array();
        foreach ($topTerms as $class => $minHeap) {
            // The minheap will yield the lowest-scoring terms first, so we 
            // prepend to the results array, rather than appending.
            $byScoreDesc = array();
            while (!$minHeap->isEmpty()) {
                $entry = $minHeap->extract();
                array_unshift($byScoreDesc, $entry);
            }
            $results[$class] = $byScoreDesc;
        }
        return $results;
    }

    /**
     * Store the training state to a path on disk.
     *
     * @param string $dbpath The path to save the serialized state to.
     */
    public function saveDB($dbpath)
    {
        file_put_contents($dbpath, serialize(array(
            'termCounts' => $this->termCounts,
            'termTotals' => $this->termTotals,
            'termGrandTotal' => $this->termGrandTotal,
            'docCounts' => $this->docCounts,
            'docTotal' => $this->docTotal
        )));
    }

    /**
     * Read the results of a previous training session from disk into memory.
     *
     * @param string $dbpath The path to load the serialized state from; this 
     * should be the same path that was passed to saveDB at some prior point.
     */
    public function loadDB($dbpath)
    {
        $db = unserialize(file_get_contents($dbpath));
        foreach ($db as $key => $val) {
            $this->$key = $val;
        }
    }


    ///////////////////////////////////////////////////////////////////////////
    //                         Private Interface                             //
    ///////////////////////////////////////////////////////////////////////////

    protected function calculateTermScore($term, $class)
    {
        $vocabSize = count($this->termCounts);
        $n_c = isset($this->termCounts[$term][$class]) ?
            $this->termCounts[$term][$class] : 0;
        $n = $this->termTotals[$class];
        return log(($n_c + 1) / ($n + $vocabSize));
    }
}
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @author Chris Pollett chris@pollett.org
 *  @package seek_quarry
 *  @subpackage locale
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2012
 *  @filesource
 */
/**
 * My stab at implementing the Porter Stemmer algorithm presented at
 * http://tartarus.org/~martin/PorterStemmer/def.txt The code is based on the
 * non-thread safe C version given by Martin Porter.  Since PHP is single 
 * threaded this should be okay.  Here given a word, its stem is that part of 
 * the word that is common to all its inflected variants. For example, tall is 
 * common to tall, taller, tallest. A stemmer takes a word and tries to produce 
 * its stem.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage locale
 */
class PorterStemmer {
    static $no_stem_list = array("Titanic");
    /**
     * Storage used in computing the stem
     * @var string
     */
    static $buffer;
    /**
     * Index of the current end of the word at the current state of computing
     * its stem
     * @var int
     */
    static $k;
    /**
     * Index to start of the suffix of the word being considered for 
     * manipulation
     * @var int
     */
    static $j;

    /**
     * Computes the stem of an English word
     *
     * For example, jumps, jumping, jumpy, all have jump as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }

        self::$buffer = $word;

        self::$k = strlen($word) - 1;
        self::$j = self::$k;
        if (self::$k <= 1) return $word;

        self::step1ab();
        self::step1c();
        self::step2();
        self::step3();
        self::step4();
        self::step5();

        return substr(self::$buffer, 0, self::$k + 1);
    }

    /**
     * Checks to see if the ith character in the buffer is a consonant
     *
     * @param int $i the character to check
     * @return if the ith character is a constant
     */
    private static function cons($i)
    {
        switch (self::$buffer[$i])
        {
            case 'a': case 'e': case 'i': case 'o': case 'u':
                return false;
            case 'y': 
                return ($i== 0 ) ? true : !self::cons($i - 1);
            default:
                return true;
        }
    }

    /** 
     * m() measures the number of consonant sequences between 0 and j. if c is
     * a consonant sequence and v a vowel sequence, and [.] indicates arbitrary
     * presence,
     *  <pre>
     *    [c][v]       gives 0
     *    [c]vc[v]     gives 1
     *    [c]vcvc[v]   gives 2
     *    [c]vcvcvc[v] gives 3
     *    ....
     *  </pre>
     */
    private static function m()
    {
        $n = 0;
        $i = 0;
        while (true) {
            if ($i > self::$j) return $n;
            if (!self::cons($i)) break; 
            $i++;
        }

        $i++;


        while (true) {
            while (true) {
                if ($i > self::$j) return $n;
                if (self::cons($i)) break;
                $i++;
            }
            $i++;
            $n++;

            while (true)
            {
                if ($i > self::$j) return $n;
                if (!self::cons($i)) break;
                $i++;
            }
            $i++;
        }
    }

    /**
     * Checks if 0,...$j contains a vowel 
     *
     * @return bool whether it does not
     */
    private static function vowelinstem()
    {
        for ($i = 0; $i <= self::$j; $i++) {
            if (!self::cons($i)) return true;
        }
        return false;
    }

    /**
     * Checks if $j,($j-1) contain a double consonant.
     *
     * @return bool if it does or not
     */
    private static function doublec($j)
    {
        if ($j < 1) return false;
        if (self::$buffer[$j] != self::$buffer[$j - 1]) return false;
        return self::cons($j);
    }

    /** 
     * Checks whether the letters at the indices $i-2, $i-1, $i in the buffer
     * have the form consonant - vowel - consonant and also if the second c is 
     * not w,x or y. this is used when trying to restore an e at the end of a 
     * short word. e.g.
     *<pre>
     *    cav(e), lov(e), hop(e), crim(e), but
     *    snow, box, tray.
     *</pre>
     * @return bool whether the letters at indices have the given form
     */
    private static function cvc($i)
    {
        if ($i < 2 || !self::cons($i) || self::cons($i - 1) || 
            !self::cons($i - 2)) return false;

        $ch = self::$buffer[$i];
        if ($ch == 'w' || $ch == 'x' || $ch == 'y') return false;

        return true;
    }

    /**
     * Checks if the buffer currently ends with the string $s
     * 
     * @param string $s string to use for check
     * @return bool whether buffer currently ends with $s
     */
    private static function ends($s)
    {
        $len = strlen($s);
        $loc = self::$k - $len + 1;
        
        if ($loc < 0 || 
            substr_compare(self::$buffer, $s, $loc, $len) != 0) return false;

        self::$j = self::$k - $len;

        return true;
    }

    /**
     * setto($s) sets (j+1),...k to the characters in the string $s, readjusting
     * k. 
     *
     * @param string $s string to modify the end of buffer with
     */
    private static function setto($s)
    {
        $len = strlen($s);
        $loc = self::$j + 1;
        self::$buffer = substr_replace(self::$buffer, $s, $loc, $len);
        self::$k = self::$j + $len;
    }

    /**
     * Sets the ending in the buffer to $s if the number of consonant sequences 
     * between $k and $j is positive.
     *
     * @param string $s what to change the suffix to
     */
    private static function r($s) 
    {
        if (self::m() > 0) self::setto($s);
    }

    /** step1ab() gets rid of plurals and -ed or -ing. e.g.
     * <pre>
     *     caresses  ->  caress
     *     ponies    ->  poni
     *     ties      ->  ti
     *     caress    ->  caress
     *     cats      ->  cat
     *
     *     feed      ->  feed
     *     agreed    ->  agree
     *     disabled  ->  disable
     *
     *     matting   ->  mat
     *     mating    ->  mate
     *     meeting   ->  meet
     *     milling   ->  mill
     *     messing   ->  mess
     *
     *     meetings  ->  meet
     * </pre>
     */
    private static function step1ab()
    {
        if (self::$buffer[self::$k] == 's') {
            if (self::ends("sses")) {
                self::$k -= 2;
            } else if (self::ends("ies")) {
                self::setto("i");
            } else if (self::$buffer[self::$k - 1] != 's') {
                self::$k--;
            }
        }
        if (self::ends("eed")) { 
            if (self::m() > 0) self::$k--; 
        } else if ((self::ends("ed") || self::ends("ing")) && 
            self::vowelinstem()) {
            self::$k = self::$j;
            if (self::ends("at")) {
                self::setto("ate");
            } else if (self::ends("bl")) {
                self::setto("ble"); 
            } else if (self::ends("iz")) {
                self::setto("ize"); 
            } else if (self::doublec(self::$k)) {
                self::$k--;
                $ch = self::$buffer[self::$k];
                if ($ch == 'l' || $ch == 's' || $ch == 'z') self::$k++;
            } else if (self::m() == 1 && self::cvc(self::$k)) {
                self::setto("e");
            }
       }
    }

    /**
     * step1c() turns terminal y to i when there is another vowel in the stem. 
     */
    private static function step1c() 
    {
        if (self::ends("y") && self::vowelinstem()) {
            self::$buffer[self::$k] = 'i';
        }
    }


    /**
     * step2() maps double suffices to single ones. so -ization ( = -ize plus
     * -ation) maps to -ize etc.Note that the string before the suffix must give
     * m() > 0. 
     */
    private static function step2() 
    {
        if (self::$k < 1) return;
        switch (self::$buffer[self::$k - 1])
        {
            case 'a':
                if (self::ends("ational")) { self::r("ate"); break; }
                if (self::ends("tional")) { self::r("tion"); break; }
                break;
            case 'c': 
                if (self::ends("enci")) { self::r("ence"); break; }
                if (self::ends("anci")) { self::r("ance"); break; }
                break;
            case 'e':
                if (self::ends("izer")) { self::r("ize"); break; }
                break;
            case 'l':
                if (self::ends("bli")) { self::r("ble"); break; } 
                if (self::ends("alli")) { self::r("al"); break; }
                if (self::ends("entli")) { self::r("ent"); break; }
                if (self::ends("eli")) { self::r("e"); break; }
                if (self::ends("ousli")) { self::r("ous"); break; }
                break;
            case 'o': 
                if (self::ends("ization")) { self::r("ize"); break; }
                if (self::ends("ation")) { self::r("ate"); break; }
                if (self::ends("ator")) { self::r("ate"); break; }
                break;
            case 's': 
                if (self::ends("alism")) { self::r("al"); break; }
                if (self::ends("iveness")) { self::r("ive"); break; }
                if (self::ends("fulness")) { self::r("ful"); break; }
                if (self::ends("ousness")) { self::r("ous"); break; }
                break;
            case 't':
                if (self::ends("aliti")) { self::r("al"); break; }
                if (self::ends("iviti")) { self::r("ive"); break; }
                if (self::ends("biliti")) { self::r("ble"); break; }
                break;
            case 'g': 
                if (self::ends("logi")) { self::r("log"); break; } 

        } 
    }

    /** 
     * step3() deals with -ic-, -full, -ness etc. similar strategy to step2. 
     */
    private static function step3() 
    {
        switch (self::$buffer[self::$k])
        {
            case 'e': 
                if (self::ends("icate")) { self::r("ic"); break; }
                if (self::ends("ative")) { self::r(""); break; }
                if (self::ends("alize")) { self::r("al"); break; }
                break;
            case 'i': 
                if (self::ends("iciti")) { self::r("ic"); break; }
                break;
            case 'l': 
                if (self::ends("ical")) { self::r("ic"); break; }
                if (self::ends("ful")) { self::r(""); break; }
                break;
            case 's': 
                if (self::ends("ness")) { self::r(""); break; }
                break;
        }
    }

    /**
     * step4() takes off -ant, -ence etc., in context <c>vcvc<v>. 
     */
    private static function step4()
    {
        if (self::$k < 1) return;
        switch (self::$buffer[self::$k - 1])
        {  
            case 'a':
                if (self::ends("al")) break;
                return;
            case 'c':
                if (self::ends("ance")) break;
                if (self::ends("ence")) break;
                return;
            case 'e': 
                if (self::ends("er")) break; 
                return;
            case 'i': 
                if (self::ends("ic")) break;
                return;
            case 'l': 
                if (self::ends("able")) break;
                if (self::ends("ible")) break;
                return;
            case 'n':
                if (self::ends("ant")) break;
                if (self::ends("ement")) break;
                if (self::ends("ment")) break;
                if (self::ends("ent")) break;
                return;
            case 'o':
                if (self::ends("ion") && self::$j >= 0 && 
                    (self::$buffer[self::$j] == 's' || 
                    self::$buffer[self::$j] == 't')) break;
                if (self::ends("ou")) break;
                return;
            /* takes care of -ous */
            case 's': 
                if (self::ends("ism")) break; 
                return;
            case 't': 
                if (self::ends("ate")) break;
                if (self::ends("iti")) break;
                    return;
            case 'u':
                if (self::ends("ous")) break;
                return;
            case 'v':
                if (self::ends("ive")) break; 
                return;
            case 'z':
                if (self::ends("ize")) break;
                return;
            default:
                return;
        }
        if (self::m() > 1) self::$k = self::$j;
    }

    /** step5() removes a final -e if m() > 1, and changes -ll to -l if
     *  m() > 1.
     */
    private static function step5()
    {
        self::$j = self::$k;
        
        if (self::$buffer[self::$k] == 'e') {
            $a = self::m();
            if ($a > 1 || $a == 1 && !self::cvc(self::$k - 1)) self::$k--;
        }
        if (self::$buffer[self::$k] == 'l' && 
            self::doublec(self::$k) && self::m() > 1) self::$k--;
    }
}

/**
 * An abstraction for turning any collection of source documents into a token 
 * stream.
 *
 * Resources can wrap anything: a text file, a web page, a string, an image, a 
 * directory, and so on. The main things a resource needs to do are provide a 
 * stream of tokens, and a name for the original object that the resource 
 * wraps. Eventually it'd be nice to have a uniform way to link back to the 
 * original object.
 *
 * @package Naive_Bayes
 * @subpackage Tokenizing
 * @author Shawn Tice <sctice@gmail.com>
 */
// Class Resource
/**
 * An abstract source of tokens, and a name for the source.
 */
abstract class Resource {
    const END_STREAM = 0;
    const NEW_DOC = 1;
    const END_DOC = 2;

    /**
     * The resource's human-readable name.
     * @var string
     */
    public $name;

    /**
     * The resource's tokenizer, to keep state between calls to nextToken.
     * @var Tokenizer
     */
    protected $tokenizer;

    /**
     * The token after the next one, or null if there is none. This is used by 
     * nextToken to report whether a token is the last one in a particular 
     * document.
     * @var string
     */
    protected $lookahead = null;

    /**
     * Construct a new Resource with a human-readable name.
     *
     * @param string $name The human-readable name for this resource.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get the next token from this resource.
     *
     * The return value is actually a 3-tuple consisting of the resource 
     * yielding the token, the token, and a status flag. The resource and flag 
     * are returned as well so that resources can be built recursively (e.g. a 
     * directory of file resources) while still allowing the token consumer to 
     * distinguish between individual documents, and conveniently track
     * document-level statistics. The possible statuses are:
     *
     *    - Resource::NEW_DOC, which marks the first token of a new document
     *    - Resource::END_DOC, which marks the last token of a document (though 
     *      there may be more tokens in other documents)
     *    - Resource::END_STREAM, which indicates that there are no more tokens 
     *      at all
     *
     * Because a document with exactly one token may satisfy both the NEW_DOC 
     * and END_DOC conditions simultaneously, the flag is actually a bit array 
     * which should be checked by ANDing (&) with the relevant flag. Note that 
     * NEW_DOC and END_DOC are mutually exclusive with END_STREAM.
     *
     * @return array A 3-tuple consisting of the resource, a token (potentially 
     * empty), and a status flag.
     */
    public function nextToken()
    {
        $status = 0;

        if (is_null($this->lookahead)) {
            $status |= Resource::NEW_DOC;
            $this->lookahead = $this->tokenizer->nextToken();
        }

        if ($this->lookahead === false) {
            return Resource::END_STREAM;
        }

        $token = $this->lookahead;
        $this->lookahead = $this->tokenizer->nextToken();

        if ($this->lookahead === false) {
            $status |= Resource::END_DOC;
        }

        return array($this, $token, $status);
    }
}
// Class FileResource
/**
 * A text file as a token stream.
 */
class FileResource extends Resource {
    /**
     * A pointer to the open file being read.
     * @var resource
     */
    protected $stream;

    /**
     * Construct a new FileResource for the readable file at $filepath.
     *
     * The resource name will be the basename of the file path.
     *
     * @param string $filepath A path to the file to wrap.
     */
    public function __construct($filepath)
    {
        // The resource name is the file's full path.
        parent::__construct($filepath);

        if (($stream = @fopen($filepath, 'r')) === false) {
            $msg = "{$filepath}: Unable to read file. Skipping.\n";
            throw new IOException($msg);
        }

        $this->stream = $stream;
        $this->tokenizer = new Tokenizer($this->stream);
    }

    /**
     * Close the file pointer this resource wraps.
     */
    public function __destruct()
    {
        @fclose($this->stream);
        $this->stream = null;
    }
}
// Class StringResource
/**
 * A PHP string as a token stream.
 */
class StringResource extends Resource {
    /**
     * A generalized stream resource for the wrapped string.
     * @var resource
     */
    protected $stream;

    /**
     * Construct a new StringResource from a string and name.
     *
     * @param string $string The string to treat as a token stream.
     * @param string $name The name to give this resource.
     */
    public function __construct($string, $name)
    {
        // The string needs to be base64-encoded because it's interpreted as a 
        // URI, where characters like '+' and '%' have special meaning.
        $string = base64_encode($string);
        $this->stream = fopen('data://text/plain;base64,' . $string, 'r');
        $this->tokenizer = new Tokenizer($this->stream);
        parent::__construct($name);
    }
}
// DirResource
/**
 * A directory of files as a token stream.
 *
 * Recursively explore a directory and provide tokens for each file in the 
 * directory that matches a filename pattern.
 */
class DirResource extends Resource {
    /**
     * The stack of open directory handles.
     * @var array
     */
    protected $dirstack;

    /**
     * The depth (starting at 0) of the current directory stack.
     * @var int
     */
    protected $depth;

    /**
     * The current file resource tokens are being read from.
     * @var resource
     */
    protected $fileResource;

    /**
     * Shell glob patterns for files to tokenize.
     *
     * A file must match only one of the patterns in order to be tokenized.
     *
     * @var array
     */
    protected $filenamePatterns;

    /**
     * Construct a new DirResource from an initial path to the directory and an 
     * array of valid filename patterns.
     *
     * The default pattern accepts all non-empty filenames.
     *
     * @param string $dirpath The path to the directory.
     * @param array $filenamePatterns An array of shell glob patterns that 
     *     match file paths whose contents should be tokenized.
     */
    public function __construct($dirpath, $filenamePatterns=array('?*'))
    {
        parent::__construct(basename($dirpath));
        $this->filenamePatterns = $filenamePatterns;
        $this->dirstack[] = $this->openDir($dirpath);
        $this->depth = 0;
        $this->fileResource = $this->nextFile();
    }

    /**
     * Get the next token from the current file.
     *
     * If the file is exhausted, then find the next valid file and return the 
     * first token of that file. If there are no more files containing any 
     * tokens, then return Resource::END_STREAM.
     *
     * @return mixed Resource::END_STREAM if there are no more tokens and no 
     * more files to explore, and otherwise a 3-tuple of the current file 
     * resource being tokenized, the next token in the file, and a status.
     */
    public function nextToken()
    {
        // Are there no more files to index?
        if ($this->fileResource === false) {
            return Resource::END_STREAM;
        }

        // Is there at least one more token in this file?
        $token = $this->fileResource->nextToken();
        if ($token !== Resource::END_STREAM) {
            return $token;
        }
        
        // Each file could be empty, in which case it's necessary to move on to 
        // the next file until one isn't empty or all files are exhausted.
        do {
            if (($this->fileResource = $this->nextFile()) === false) {
                return Resource::END_STREAM;
            } else {
                $token = $this->fileResource->nextToken();
            }
        } while ($token === Resource::END_STREAM);

        return $token;
    }

    /**
     * Get the next file that matches one of the {@link
     * DirResource::$filenamePatterns file name patterns}.
     *
     * Scan through the directory tree from the current node until a readable 
     * file that passes through the file name filter has been found, or until 
     * all files have been exhausted; if a file is found, then open it and 
     * assign the handle to $filestream.
     *
     * @return FileResource The next unvisited FileResource that matches one of 
     *     the {@link DirResource::$filenamePatterns file name patterns}.
     */
    protected function nextFile()
    {
        $dir = $this->dirstack[$this->depth];
        $fileResource = false;
        while ($fileResource === false) {
            $filename = $dir->read();
            if ($filename === false) {
                // The current directory has been exhausted, go back up the 
                // directory tree.
                $dir->close();
                array_pop($this->dirstack);
                $this->depth--;
                if ($this->depth >= 0) {
                    $dir = $this->dirstack[$this->depth];
                } else {
                    // All directories and files have been exhausted.
                    return false;
                }
            } else if (in_array($filename, array('.', '..'))) {
                // Skip over '.' and '..' entries in the directory.
                continue;
            } else {
                $filepath = $dir->path . DIRECTORY_SEPARATOR . $filename;
                $filepath = realpath($filepath);
                $filetype = @filetype($filepath);
                if ($filetype == 'file') {
                    // For files, make sure the file matches one of the 
                    // accepted patterns.
                    if ($this->filenameMatch($filename)) {
                        $fileResource = new FileResource($filepath);
                    }
                } else if ($filetype == 'dir') {
                    // For directories, immediately jump down into the child 
                    // directory.
                    $dir = $this->openDir($filepath);
                    $this->dirstack[] = $dir;
                    $this->depth++;
                } else {
                    $msg = "{$filepath}: Unreadable or unsupported file.";
                    throw new IOError($msg);
                }
            }
        }

        return $fileResource;
    }

    /**
     * Does a file name match one of the {@link DirResource::$filenamePatterns 
     * file name patterns}?
     *
     * @param string $filename The file name to check.
     * @return bool True if the file name matches one of the configured 
     *     patterns, and false otherwise.
     */
    protected function filenameMatch($filename)
    {
        foreach ($this->filenamePatterns as $pattern) {
            if (fnmatch($pattern, $filename, FNM_CASEFOLD)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Try to open a directory, and throw an {@link IOException IO Exception} 
     * if there's a problem.
     *
     * @param string $dirpath The path to the directory.
     * @return resource The directory resource.
     */
    protected function openDir($dirpath)
    {
        if (($dir = @dir($dirpath)) === false) {
            throw new IOException("{$dirpath}: Unable to read directory.\n");
        }
        return $dir;
    }
}
// Exceptions
/**
 * Thrown when a file or directory is not readable.
 */
class IOException extends Exception {}
/**
 * Convert plain text into a stream of tokens.
 *
 * The tokenizer tries to avoid reading an entire file into memory in order to 
 * tokenize it.
 *
 * @package Naive_Bayes
 * @subpackage Tokenizing
 * @author Shawn Tice <sctice@gmail.com>
 */
/**
 * Tokenize plain text from one source.
 *
 * The tokenizer attempts to avoid reading the entire file handle at once, and 
 * employs logic to continue extending its buffer in order to avoid cutting a 
 * token into two parts if it happens to extend over the boundary of two reads.
 */
class Tokenizer {
    /**
     * An array of tokenizing options. The options are:
     *
     *    - 'stemmer': A string specifying the name of a class to instantiate
     *      and use for stemming tokens.
     *    - 'stopwords': A string specifying the name of a file to parse for
     *      stopwords. The file should contain a list of words, one per line;
     *      stopwords will be removed from the token stream *before* stemming.
     *
     * @var array
     */
    protected $options = array(
        'stemmer' => 'PorterStemmer',
        'stopwords' => null
    );

    /**
     * The generalized PHP resource to read from.
     * @var resource
     */
    protected $source;

    /**
     * A buffer of text read from the resource so far.
     * @var string
     */
    protected $buffer;

    /**
     * The maximum amount to read at once.
     * @var int
     */
    protected $maxReadSize = 4096;

    /**
     * The regular expression used to identify tokens.
     * @var string
     */
    protected $matchToken = '/[a-zA-Z0-9]+/';

    /**
     * A set of stop words to skip over. If there are any stopwords, the 
     * tokenizer will behave as though those words never occurred in the token 
     * stream.
     * @var array
     */
    protected $stopwords = array();

    /**
     * A stemmer object, which will be used to stem tokens one at a time as 
     * they're parsed out of the source text.
     * @var Stemmer
     */
    protected $stemmer;

    /**
     * Construct a new tokenizer from a generalized resource.
     *
     * Fill the buffer from the resource before the first call to nextToken, so
     * that the buffer has some initial text in it.
     *
     * @param resource $resource The generalized PHP resource to read from.
     * @param array $options An optional associative array of options 
     * specifying a stemmer and set of stop words.
     */
    public function __construct($resource, $options = array())
    {
        $this->source = $resource;
        $this->fillBuffer();
        $this->options = array_merge($this->options, $options);

        if ($this->options['stopwords']) {
            $words = file($this->options['stopwords'], FILE_IGNORE_NEW_LINES);
            $ones = array_fill(0, count($words), true);
            $this->stopwords = array_combine($words, $ones);
        }

        if ($this->options['stemmer']) {
            $this->stemmer = new $this->options['stemmer']();
        }
    }

    /**
     * Get the next token.
     *
     * Read until a token is found or there's no more text to read, and return 
     * the normalized token or false if there are no more tokens.
     *
     * @return mixed False if there are no more tokens, and a token (a string) 
     *     otherwise.
     */
    public function nextToken()
    {
        $token = '';
        while (!$token) {
            if ($this->buffer == '') {
                return false;
            }

            while ($this->buffer != '') {
                // Match one token at a time, and collect the offset information.
                $matched = preg_match(
                    $this->matchToken,
                    $this->buffer,
                    $matches,
                    PREG_OFFSET_CAPTURE
                );

                if ($matched) {
                    list($piece, $offset) = $matches[0];
                    $pieceSize = strlen($piece);
                    if ($offset > 0) {
                        // Drop the non-token text that comes before the token.
                        $this->dropFromFront($offset);
                        if ($token != '') {
                            break;
                        }
                    }
                    $this->dropFromFront($pieceSize);
                    $token .= $piece;
                    if ($this->buffer != '') {
                        break;
                    } else {
                        $this->fillBuffer();
                    }
                } else {
                    $this->dropBuffer();
                    $this->fillBuffer();
                    if ($token != '') {
                        break;
                    }
                }
            }

            $token = $this->normalizeToken($token);
        }

        return $token;
    }

    /**
     * Normalize a token. If stemming is enabled, then run the token through the
     * Porter stemmer (assuming English).
     *
     * @param string $token The token to normalize.
     * @return string The $token normalized to lowercase and optionally 
     * stemmed; the empty string if the token is a stopword.
     */
    protected function normalizeToken($token)
    {
        $token = strtolower($token);

        if ($token == '' || array_key_exists($token, $this->stopwords)) {
            return '';
        }

        if ($this->stemmer) {
            $token = $this->stemmer->stem($token);
        }

        return $token;
    }

    /**
     * Fill the internal buffer.
     *
     * Reads up to {@link Tokenizer::$maxReadSize} bytes from the configured 
     * resource, and adds it to the buffer. There is no check to verify that 
     * the buffer is empty.
     *
     * @return bool True if there's more text to tokenize and false for end of 
     *     file.
     */
    protected function fillBuffer()
    {
        $this->buffer = fgets($this->source, $this->maxReadSize);
        return $this->buffer !== false;
    }

    /**
     * Delete from the front of the internal buffer.
     *
     * @param int $n The number of bytes to drop.
     */
    protected function dropFromFront($n)
    {
        $this->buffer = substr($this->buffer, $n);
    }

    /**
     * Empty out the internal buffer entirely.
     */
    protected function dropBuffer()
    {
        $this->buffer = false;
    }
}




//require_once 'NaiveBayes.php';
//require_once 'Resource.php';
//require_once 'Tokenizer.php';

define('ACTION_TRAIN',    1);
define('ACTION_CLASSIFY', 2);
define('ACTION_TOPTERMS', 3);

/**
 * Print program help and exit, possibly with a non-zero status.
 *
 * @param int $exit The exit status.
 */
function usage($exit = 0)
{
    echo "php nbayes.php --train DIR --db DBFILE\n";
    echo "php nabyes.php --classify DIR --db DBFILE\n";
    echo "php nabyes.php --top-terms [-n N] --db DBFILE\n";
    echo "\n";
    echo "The training directory should contain two or more subdirectories,\n";
    echo "each containing files to be trained on. The subdirectory names\n";
    echo "are taken as the class labels for the examples contained under\n";
    echo "them (the file names are irrelevant).\n";
    echo "\n";
    echo "The test directory should contain only a collection of files to\n";
    echo "be classified.\n";
    echo "\n";
    echo "The DBFILE passed to the train command will be overwritten with\n";
    echo "training data, and the same path should be passed to the classify\n";
    echo "command.\n";
    exit($exit);
}

/**
 * Program entrypoint; parse options and execute appropriately.
 */
function main() {
    global $mode, $dir, $db;

    $options = array('n' => 20);
    if ( $mode == 'train') $options['action'] = ACTION_TRAIN;
    if ( $mode == 'classify') $options['action'] = ACTION_CLASSIFY;
    if ( $mode == 'top-terms') $options['action'] = ACTION_TOPTERMS;
    $options[ 'input'] = $dir;
    $options[ 'dbfile'] = $db;
    
    
    if (!isset($options['action'])) {
        echo "Missing action (--train, --classify, or --top-terms)\n";
        usage(1);
    }

    if (!isset($options['dbfile'])) {
        echo "Missing database file path\n";
        usage(2);
    }

    $nb = new NaiveBayes();

    if ($options['action'] == ACTION_TRAIN) {
        $nb->loadDB($options['dbfile']); // marat (loading current DB before training)
    	trainDir($options['input'], $nb);
        $nb->saveDB($options['dbfile']);
    } else if ($options['action'] == ACTION_CLASSIFY) {
        $nb->loadDB($options['dbfile']);
        classifyPath($options['input'], $nb);
    } else if ($options['action'] == ACTION_TOPTERMS) {
        $nb->loadDB($options['dbfile']);
        reportTopTerms($options['n'], $nb);
    } else {
        echo "Unrecognized action\n";
        usage(3);
    }
}

/**
 * Train an instance of NaiveBayes on a directory hierarchy of documents.
 *
 * Each subdirectory of the root directory should contain documents that all 
 * belong to the same class; the name of each subdirectory is taken as the 
 * class label for all of the documents it contains.
 *
 * @param string $dirpath The path to the root training directory.
 * @param NaiveBayes $nb An instance of NaiveBayes
 */
function trainDir($dirpath, $nb)
{
    $resource = new DirResource($dirpath);
    $class = '';
    $terms = array();
    while (($next = $resource->nextToken()) !== Resource::END_STREAM) {
        list($tokenResource, $token, $status) = $next;

        if ($status & Resource::NEW_DOC) {
            // This is a new training file.
            $path = getRelativePath($resource->name, $tokenResource->name);
            $class = getClass($path);
            echo "training on {$path} in {$class}\n";
        }

        if (!isset($terms[$token])) {
            $terms[$token] = 1;
        } else {
            $terms[$token] += 1;
        }

        if ($status & Resource::END_DOC) {
            // This is the last term of a particular document, so train on the 
            // terms we saw.
            $nb->trainInstance(array_keys($terms), $class);
            $terms = array();
        }
    }
}

/**
 * Classify a document or directory of documents using a trained instance of 
 * NaiveBayes.
 *
 * If passed a path to a file then print the name of the file and its 
 * determined class. Otherwise the path should be for a directory containing a 
 * number of files to be classified; the path for each file and its determined 
 * class are printed, one file per line.
 *
 * @param string $path The path of a file or directory to classify.
 * @param NaiveBayes $nb An instance of NaiveBayes.
 */
function classifyPath($path, $nb)
{
    if (is_dir($path)) {
        $resource = new DirResource($path);
    } else if (is_file($path)) {
        $resource = new FileResource($path);
    } else {
        throw new Exception('Bad path');
    }

    $path = '';
    $terms = array();
    while (($next = $resource->nextToken()) !== Resource::END_STREAM) {
        list($tokenResource, $token, $status) = $next;

        if ($status & Resource::NEW_DOC) {
            $path = getRelativePath($resource->name, $tokenResource->name);
        }

        if (!isset($terms[$token])) {
            $terms[$token] = 1;
        } else {
            $terms[$token] += 1;
        }

        if ($status & Resource::END_DOC) {
            $rank = $nb->classify(array_keys($terms)); // marat
           //die( jsonraw( $rank));
            echo "$path --- " . implode( ' --- ', $rank) . "\n";
            $terms = array();
        }
        
    }
    
}

/**
 * Print out the top terms tracked by an instance of NaiveBayes.
 *
 * @param NaiveBayes $nb The NaiveBayes instance.
 * @param int $n The number of top terms to keep track of.
 */
function reportTopTerms($n, $nb)
{
    $topTermsByClass = $nb->recoverTopTerms($n);
    $classes = array_keys($topTermsByClass);
    sort($classes);

    $header = array('i');
    foreach ($classes as $class) {
        $header[] = $class;
        $header[] = 'dTop';
    }
    echo implode("\t", $header) . "\n";
    foreach ($header as &$col) {
        $col = str_repeat('-', strlen($col));
    }
    echo implode("\t", $header) . "\n";

    $maxScores = array();
    foreach ($classes as $class) {
        $maxScores[$class] = $topTermsByClass[$class][0][0];
    }

    for ($i = 0; $i < $n; $i++) {
        $line = array(strval($i + 1));
        foreach ($classes as $class) {
            if (isset($topTermsByClass[$class][$i])) {
                $entry = $topTermsByClass[$class][$i];
                list($score, $term) = $entry;
                $line[] = $term;
                $line[] = sprintf("%+.4f", $score - $maxScores[$class]);
            } else {
                $line[] = '-';
                $line[] = '-';
            }
        }
        echo implode("\t", $line) . "\n";
    }
}


///////////////////////////////////////////////////////////////////////////////
//                             Utility Functions                             //
///////////////////////////////////////////////////////////////////////////////

/**
 * Return the path from a parent directory to its child.
 *
 * Both paths are assumed to exist, and the parent path should be a prefix of 
 * the child path.
 *
 * @param string $parent The parent directory path.
 * @param string $child The path to a file nested under the parent directory.
 * @return string The path from the parent to the child, without a leading 
 * separator.
 */
function getRelativePath($parent, $child)
{
    $root = realpath($parent);
    return substr(realpath($child), strlen($root) + 1);
}

/**
 * Interpret a relative path as a class name.
 *
 * The class name is the relative path without the final component and 
 * directory separator. If the path is './somefile', then the real path to '.' 
 * is computed, and the basename of that (i.e. the name of '.') becomes the 
 * class.
 *
 * @param string $relpath A path taken relative to some root directory.
 * @return string A class name for the file specified by $relpath.
 */
function getClass($relpath)
{
    $dir = dirname($relpath);
    if ($dir == '.')
        return basename(realpath($dir));
    return $dir;
}

// Start at main.
main();


?>