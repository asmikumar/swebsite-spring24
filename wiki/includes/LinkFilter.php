<?php

/**
 * Some functions to help implement an external link filter for spam control.
 * 
 * @todo implement the filter. Currently these are just some functions to help
 * maintenance/cleanupSpam.php remove links to a single specified domain. The
 * next thing is to implement functions for checking a given page against a big
 * list of domains.
 *
 * Another cool thing to do would be a web interface for fast spam removal.
 */
class LinkFilter {

	/**
	 * Check whether $text contains a link to $filterEntry
	 *
	 * @param $text String: text to check
	 * @param $filterEntry String: domainparts, see makeRegex() for more details
	 * @return Integer: 0 if no match or 1 if there's at least one match
	 */
	static function matchEntry( $text, $filterEntry ) {
		$regex = LinkFilter::makeRegex( $filterEntry );
		return preg_match( $regex, $text );
	}

	/**
	 * Builds a regex pattern for $filterEntry.
	 *
	 * @param $filterEntry String: URL, if it begins with "*.", it'll be
	 *        replaced to match any subdomain
	 * @return String: regex pattern, for preg_match()
	 */
	private static function makeRegex( $filterEntry ) {
		$regex = '!http://';
		if ( substr( $filterEntry, 0, 2 ) == '*.' ) {
			$regex .= '(?:[A-Za-z0-9.-]+\.|)';
			$filterEntry = substr( $filterEntry, 2 );
		}
		$regex .= preg_quote( $filterEntry, '!' ) . '!Si';
		return $regex;
	}

	/**
	 * Make a string to go after an SQL LIKE, which will match the specified
	 * string. There are several kinds of filter entry:
	 *     *.domain.com    -  Produces http://com.domain.%, matches domain.com
	 *                        and www.domain.com
	 *     domain.com      -  Produces http://com.domain./%, matches domain.com
	 *                        or domain.com/ but not www.domain.com
	 *     *.domain.com/x  -  Produces http://com.domain.%/x%, matches
	 *                        www.domain.com/xy
	 *     domain.com/x    -  Produces http://com.domain./x%, matches
	 *                        domain.com/xy but not www.domain.com/xy
	 *
	 * Asterisks in any other location are considered invalid.
	 * 
	 * @param $filterEntry String: domainparts
	 * @param $prot        String: protocol
	 * @return String
	 * @deprecated Use makeLikeArray() and pass result to Database::buildLike() instead
	 */
	 public static function makeLike( $filterEntry , $prot = 'http://' ) {
		wfDeprecated( __METHOD__ );

		$like = self::makeLikeArray( $filterEntry , $prot );
		if ( !$like ) {
			return false;
		}
		$dbw = wfGetDB( DB_MASTER );
		$s = $dbw->buildLike( $like );
		$m = false;
		if ( preg_match( "/^ *LIKE '(.*)' *$/", $s, $m ) ) {
			return $m[1];
		} else {
			throw new MWException( __METHOD__.': this DBMS is not supported by this function.' );
		}
	}

	/**
	 * Make an array to be used for calls to DatabaseBase::buildLike(), which
	 * will match the specified string. There are several kinds of filter entry:
	 *     *.domain.com    -  Produces http://com.domain.%, matches domain.com
	 *                        and www.domain.com
	 *     domain.com      -  Produces http://com.domain./%, matches domain.com
	 *                        or domain.com/ but not www.domain.com
	 *     *.domain.com/x  -  Produces http://com.domain.%/x%, matches
	 *                        www.domain.com/xy
	 *     domain.com/x    -  Produces http://com.domain./x%, matches
	 *                        domain.com/xy but not www.domain.com/xy
	 *
	 * Asterisks in any other location are considered invalid.
	 *
	 * @param $filterEntry String: domainparts
	 * @param $prot        String: protocol
	 * @return Array to be passed to DatabaseBase::buildLike() or false on error
	 */
	 public static function makeLikeArray( $filterEntry , $prot = 'http://' ) {
		$db = wfGetDB( DB_MASTER );
		if ( substr( $filterEntry, 0, 2 ) == '*.' ) {
			$subdomains = true;
			$filterEntry = substr( $filterEntry, 2 );
			if ( $filterEntry == '' ) {
				// We don't want to make a clause that will match everything,
				// that could be dangerous
				return false;
			}
		} else {
			$subdomains = false;
		}
		// No stray asterisks, that could cause confusion
		// It's not simple or efficient to handle it properly so we don't
		// handle it at all.
		if ( strpos( $filterEntry, '*' ) !== false ) {
			return false;
		}
		$slash = strpos( $filterEntry, '/' );
		if ( $slash !== false ) {
			$path = substr( $filterEntry, $slash );
			$host = substr( $filterEntry, 0, $slash );
		} else {
			$path = '/';
			$host = $filterEntry;
		}
		// Reverse the labels in the hostname, convert to lower case
		// For emails reverse domainpart only
		if ( $prot == 'mailto:' && strpos($host, '@') ) {
			// complete email adress 
			$mailparts = explode( '@', $host );
			$domainpart = strtolower( implode( '.', array_reverse( explode( '.', $mailparts[1] ) ) ) );
			$host = $domainpart . '@' . $mailparts[0];
			$like = array( "$prot$host", $db->anyString() );
		} elseif ( $prot == 'mailto:' ) {
			// domainpart of email adress only. do not add '.'
			$host = strtolower( implode( '.', array_reverse( explode( '.', $host ) ) ) );	
			$like = array( "$prot$host", $db->anyString() );			
		} else {
			$host = strtolower( implode( '.', array_reverse( explode( '.', $host ) ) ) );	
			if ( substr( $host, -1, 1 ) !== '.' ) {
				$host .= '.';
			}
			$like = array( "$prot$host" );

			if ( $subdomains ) {
				$like[] = $db->anyString();
			}
			if ( !$subdomains || $path !== '/' ) {
				$like[] = $path;
				$like[] = $db->anyString();
			}
		}
		return $like;
	}

	/**
	 * Filters an array returned by makeLikeArray(), removing everything past first pattern placeholder.
	 *
	 * @param $arr array: array to filter
	 * @return filtered array
	 */
	public static function keepOneWildcard( $arr ) {
		if( !is_array( $arr ) ) {
			return $arr;
		}

		foreach( $arr as $key => $value ) {
			if ( $value instanceof LikeMatch ) {
				return array_slice( $arr, 0, $key + 1 );
			}
		}

		return $arr;
	}
}
