<?php

/**
 * Import data from a UseModWiki into a MediaWiki wiki
 * 2003-02-09 Brion VIBBER <brion@pobox.com>
 * Based loosely on Magnus's code from 2001-2002
 *
 * Updated limited version to get something working temporarily
 * 2003-10-09
 * Be sure to run the link & index rebuilding scripts!
 *
 * Some more munging for charsets etc
 * 2003-11-28
 *
 * Partial fix for pages starting with lowercase letters (??)
 * and CamelCase and /Subpage link conversion
 * 2004-11-17
 *
 * Rewrite output to create Special:Export format for import
 * instead of raw SQL. Should be 'future-proof' against future
 * schema changes.
 * 2005-03-14
 *
 * @todo document
 * @file
 * @ingroup Maintenance
 */

if( php_sapi_name() != 'cli' ) {
	echo "Please customize the settings and run me from the command line.";
	die( -1 );
}

/** Set these correctly! */
$wgImportEncoding = "CP1252"; /* We convert all to UTF-8 */
$wgRootDirectory = "/kalman/Projects/wiki2002/wiki/lib-http/db/wiki";

/* On a large wiki, you might run out of memory */
@ini_set( 'memory_limit', '40M' );

/* globals */
$wgFieldSeparator = "\xb3"; # Some wikis may use different char
	$FS = $wgFieldSeparator ;
	$FS1 = $FS."1" ;
	$FS2 = $FS."2" ;
	$FS3 = $FS."3" ;

# Unicode sanitization tools
require_once( dirname( dirname( __FILE__ ) ) . '/includes/normal/UtfNormal.php' );

$usercache = array();

importPages();

# ------------------------------------------------------------------------------

function importPages()
{
	global $wgRootDirectory;

	$gt = '>';
	echo <<<XML
<?xml version="1.0" encoding="UTF-8" ?$gt
<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.1/"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.1/
                               http://www.mediawiki.org/xml/export-0.1.xsd"
           version="0.1"
           xml:lang="en">
<!-- generated by importUseModWiki.php -->

XML;
	$letters = array(
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
		'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
		'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'other' );
	foreach( $letters as $letter ) {
		$dir = "$wgRootDirectory/page/$letter";
		if( is_dir( $dir ) )
			importPageDirectory( $dir );
	}
	echo <<<XML
</mediawiki>

XML;
}

function importPageDirectory( $dir, $prefix = "" )
{
	echo "\n<!-- Checking page directory " . xmlCommentSafe( $dir ) . " -->\n";
	$mydir = opendir( $dir );
	while( $entry = readdir( $mydir ) ) {
		$m = array();
		if( preg_match( '/^(.+)\.db$/', $entry, $m ) ) {
			echo importPage( $prefix . $m[1] );
		} else {
			if( is_dir( "$dir/$entry" ) ) {
				if( $entry != '.' && $entry != '..' ) {
					importPageDirectory( "$dir/$entry", "$entry/" );
				}
			} else {
				echo "<!-- File '" . xmlCommentSafe( $entry ) . "' doesn't seem to contain an article. Skipping. -->\n";
			}
		}
	}
}


# ------------------------------------------------------------------------------

/* fetch_ functions
	Grab a given item from the database
	*/

function useModFilename( $title ) {
	$c = substr( $title, 0, 1 );
	if(preg_match( '/[A-Z]/i', $c ) ) {
		return strtoupper( $c ) . "/$title";
	}
	return "other/$title";
}

function fetchPage( $title )
{
	global $FS1,$FS2,$FS3, $wgRootDirectory;

	$fname = $wgRootDirectory . "/page/" . useModFilename( $title ) . ".db";
	if( !file_exists( $fname ) ) {
		echo "Couldn't open file '$fname' for page '$title'.\n";
		die( -1 );
	}

	$page = splitHash( $FS1, file_get_contents( $fname ) );
	$section = splitHash( $FS2, $page["text_default"] );
	$text = splitHash( $FS3, $section["data"] );

	return array2object( array( "text" => $text["text"] , "summary" => $text["summary"] ,
		"minor" => $text["minor"] , "ts" => $section["ts"] ,
		"username" => $section["username"] , "host" => $section["host"] ) );
}

function fetchKeptPages( $title )
{
	global $FS1,$FS2,$FS3, $wgRootDirectory;

	$fname = $wgRootDirectory . "/keep/" . useModFilename( $title ) . ".kp";
	if( !file_exists( $fname ) ) return array();

	$keptlist = explode( $FS1, file_get_contents( $fname ) );
	array_shift( $keptlist ); # Drop the junk at beginning of file

	$revisions = array();
	foreach( $keptlist as $rev ) {
		$section = splitHash( $FS2, $rev );
		$text = splitHash( $FS3, $section["data"] );
		if ( $text["text"] && $text["minor"] != "" && ( $section["ts"]*1 > 0 ) ) {
			array_push( $revisions, array2object( array ( "text" => $text["text"] , "summary" => $text["summary"] ,
				"minor" => $text["minor"] , "ts" => $section["ts"] ,
				"username" => $section["username"] , "host" => $section["host"] ) ) );
		} else {
			echo "<!-- skipped a bad old revision -->\n";
		}
	}
	return $revisions;
}

function splitHash ( $sep , $str ) {
	$temp = explode ( $sep , $str ) ;
	$ret = array () ;
	for ( $i = 0; $i+1 < count ( $temp ) ; $i++ ) {
		$ret[$temp[$i]] = $temp[++$i] ;
		}
	return $ret ;
	}


/* import_ functions
	Take a fetched item and produce SQL
	*/

function checkUserCache( $name, $host )
{
	global $usercache;

	if( $name ) {
		if( in_array( $name, $usercache ) ) {
			$userid = $usercache[$name];
		} else {
			# If we haven't imported user accounts
			$userid = 0;
		}
		$username = str_replace( '_', ' ', $name );
	} else {
		$userid = 0;
		$username = $host;
	}
	return array( $userid, $username );
}

function importPage( $title )
{
	global $usercache;

	echo "\n<!-- Importing page " . xmlCommentSafe( $title ) . " -->\n";
	$page = fetchPage( $title );

	$newtitle = xmlsafe( str_replace( '_', ' ', recodeText( $title ) ) );

	$munged = mungeFormat( $page->text );
	if( $munged != $page->text ) {
		/**
		 * Save a *new* revision with the conversion, and put the
		 * previous last version into the history.
		 */
		$next = array2object( array(
			'text'     => $munged,
			'minor'    => 1,
			'username' => 'Conversion script',
			'host'     => '127.0.0.1',
			'ts'       => time(),
			'summary'  => 'link fix',
			) );
		$revisions = array( $page, $next );
	} else {
		/**
		 * Current revision:
		 */
		$revisions = array( $page );
	}
	$xml = <<<XML
	<page>
		<title>$newtitle</title>

XML;

	# History
	$revisions = array_merge( $revisions, fetchKeptPages( $title ) );
	if(count( $revisions ) == 0 ) {
		return NULL; // Was "$sql", which does not appear to be defined.
	}

	foreach( $revisions as $rev ) {
		$text      = xmlsafe( recodeText( $rev->text ) );
		$minor     = ($rev->minor ? '<minor/>' : '');
		list( /* $userid */ , $username ) = checkUserCache( $rev->username, $rev->host );
		$username  = xmlsafe( recodeText( $username ) );
		$timestamp = xmlsafe( timestamp2ISO8601( $rev->ts ) );
		$comment   = xmlsafe( recodeText( $rev->summary ) );

		$xml .= <<<XML
		<revision>
			<timestamp>$timestamp</timestamp>
			<contributor><username>$username</username></contributor>
			$minor
			<comment>$comment</comment>
			<text>$text</text>
		</revision>

XML;
	}
	$xml .= "</page>\n\n";
	return $xml;
}

# Whee!
function recodeText( $string ) {
	global $wgImportEncoding;
	# For currently latin-1 wikis
	$string = str_replace( "\r\n", "\n", $string );
	$string = @iconv( $wgImportEncoding, "UTF-8", $string );
	$string = wfMungeToUtf8( $string ); # Any old &#1234; stuff
	return $string;
}

function wfUtf8Sequence($codepoint) {
	if($codepoint <     0x80) return chr($codepoint);
	if($codepoint <    0x800) return chr($codepoint >>  6 & 0x3f | 0xc0) .
                                     chr($codepoint       & 0x3f | 0x80);
    if($codepoint <  0x10000) return chr($codepoint >> 12 & 0x0f | 0xe0) .
                                     chr($codepoint >>  6 & 0x3f | 0x80) .
                                     chr($codepoint       & 0x3f | 0x80);
	if($codepoint < 0x100000) return chr($codepoint >> 18 & 0x07 | 0xf0) . # Double-check this
	                                 chr($codepoint >> 12 & 0x3f | 0x80) .
                                     chr($codepoint >>  6 & 0x3f | 0x80) .
                                     chr($codepoint       & 0x3f | 0x80);
	# Doesn't yet handle outside the BMP
	return "&#$codepoint;";
}

function wfMungeToUtf8($string) {
	$string = preg_replace ( '/&#([0-9]+);/e', 'wfUtf8Sequence($1)', $string );
	$string = preg_replace ( '/&#x([0-9a-f]+);/ie', 'wfUtf8Sequence(0x$1)', $string );
	# Should also do named entities here
	return $string;
}

function timestamp2ISO8601( $ts ) {
	#2003-08-05T18:30:02Z
	return gmdate( 'Y-m-d', $ts ) . 'T' . gmdate( 'H:i:s', $ts ) . 'Z';
}

function xmlsafe( $string ) {
	/**
	 * The page may contain old data which has not been properly normalized.
	 * Invalid UTF-8 sequences or forbidden control characters will make our
	 * XML output invalid, so be sure to strip them out.
	 */
	$string = UtfNormal::cleanUp( $string );

	$string = htmlspecialchars( $string );
	return $string;
}

function xmlCommentSafe( $text ) {
	return str_replace( '--', '\\-\\-', xmlsafe( recodeText( $text ) ) );
}


function array2object( $arr ) {
	$o = (object)0;
	foreach( $arr as $x => $y ) {
		$o->$x = $y;
	}
	return $o;
}


/**
 * Make CamelCase and /Talk links work
 */
function mungeFormat( $text ) {
	global $nowiki;
	$nowiki = array();
	$staged = preg_replace_callback(
		'/(<nowiki>.*?<\\/nowiki>|(?:http|https|ftp):\\S+|\[\[[^]\\n]+]])/s',
		'nowikiPlaceholder', $text );

	# This is probably not  100% correct, I'm just
	# glancing at the UseModWiki code.
	$upper   = "[A-Z]";
	$lower   = "[a-z_0-9]";
	$any     = "[A-Za-z_0-9]";
	$camel   = "(?:$upper+$lower+$upper+$any*)";
	$subpage = "(?:\\/$any+)";
	$substart = "(?:\\/$upper$any*)";

	$munged = preg_replace( "/(?!\\[\\[)($camel$subpage*|$substart$subpage*)\\b(?!\\]\\]|>)/",
		'[[$1]]', $staged );

	$final = preg_replace( '/' . preg_quote( placeholder() ) . '/es',
		'array_shift( $nowiki )', $munged );
	return $final;
}


function placeholder( $x = null ) {
	return '\xffplaceholder\xff';
}

function nowikiPlaceholder( $matches ) {
	global $nowiki;
	$nowiki[] = $matches[1];
	return placeholder();
}


