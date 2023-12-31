<?php

/**
 * PrefixSearch - Handles searching prefixes of titles and finding any page
 * names that match. Used largely by the OpenSearch implementation.
 *
 * @ingroup Search
 */

class PrefixSearch {
	/**
	 * Do a prefix search of titles and return a list of matching page names.
	 *
	 * @param $search String
	 * @param $limit Integer
	 * @param $namespaces Array: used if query is not explicitely prefixed
	 * @return Array of strings
	 */
	public static function titleSearch( $search, $limit, $namespaces=array() ) {
		$search = trim( $search );
		if( $search == '' ) {
			return array(); // Return empty result
		}
		$namespaces = self::validateNamespaces( $namespaces );

		$title = Title::newFromText( $search );
		if( $title && $title->getInterwiki() == '' ) {
			$ns = array($title->getNamespace());
			if($ns[0] == NS_MAIN)
				$ns = $namespaces; // no explicit prefix, use default namespaces
			return self::searchBackend(
				$ns, $title->getText(), $limit );
		}

		// Is this a namespace prefix?
		$title = Title::newFromText( $search . 'Dummy' );
		if( $title && $title->getText() == 'Dummy'
			&& $title->getNamespace() != NS_MAIN
			&& $title->getInterwiki() == '' ) {
			return self::searchBackend(
				array($title->getNamespace()), '', $limit );
		}

		return self::searchBackend( $namespaces, $search, $limit );
	}


	/**
	 * Do a prefix search of titles and return a list of matching page names.
	 * @param $namespaces Array
	 * @param $search String
	 * @param $limit Integer
	 * @return Array of strings
	 */
	protected static function searchBackend( $namespaces, $search, $limit ) {
		if( count($namespaces) == 1 ){
			$ns = $namespaces[0];
			if( $ns == NS_MEDIA ) {
				$namespaces = array(NS_FILE);
			} elseif( $ns == NS_SPECIAL ) {
				return self::specialSearch( $search, $limit );
			}
		}
		$srchres = array();
		if( wfRunHooks( 'PrefixSearchBackend', array( $namespaces, $search, $limit, &$srchres ) ) ) {
			return self::defaultSearchBackend( $namespaces, $search, $limit );
		}
		return $srchres;
	}

	/**
	 * Prefix search special-case for Special: namespace.
	 *
	 * @param $search String: term
	 * @param $limit Integer: max number of items to return
	 * @return Array
	 */
	protected static function specialSearch( $search, $limit ) {
		global $wgContLang;
		$searchKey = $wgContLang->caseFold( $search );

		// Unlike SpecialPage itself, we want the canonical forms of both
		// canonical and alias title forms...
		SpecialPage::initList();
		SpecialPage::initAliasList();
		$keys = array();
		foreach( array_keys( SpecialPage::$mList ) as $page ) {
			$keys[$wgContLang->caseFold( $page )] = $page;
		}
		foreach( $wgContLang->getSpecialPageAliases() as $page => $aliases ) {
			if( !array_key_exists( $page, SpecialPage::$mList ) ) # bug 20885
				continue;

			foreach( $aliases as $alias ) {
				$keys[$wgContLang->caseFold( $alias )] = $alias;
			}
		}
		ksort( $keys );

		$srchres = array();
		foreach( $keys as $pageKey => $page ) {
			if( $searchKey === '' || strpos( $pageKey, $searchKey ) === 0 ) {
				$srchres[] = Title::makeTitle( NS_SPECIAL, $page )->getPrefixedText();
			}
			if( count( $srchres ) >= $limit ) {
				break;
			}
		}
		return $srchres;
	}

	/**
	 * Unless overridden by PrefixSearchBackend hook...
	 * This is case-sensitive (First character may
	 * be automatically capitalized by Title::secureAndSpit()
	 * later on depending on $wgCapitalLinks)
	 *
	 * @param $namespaces Array: namespaces to search in
	 * @param $search String: term
	 * @param $limit Integer: max number of items to return
	 * @return Array of title strings
	 */
	protected static function defaultSearchBackend( $namespaces, $search, $limit ) {
		$ns = array_shift($namespaces); // support only one namespace
		if( in_array(NS_MAIN,$namespaces))
			$ns = NS_MAIN; // if searching on many always default to main

		// Prepare nested request
		$req = new FauxRequest(array (
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => $ns,
			'aplimit' => $limit,
			'apprefix' => $search
		));

		// Execute
		$module = new ApiMain($req);
		$module->execute();

		// Get resulting data
		$data = $module->getResultData();

		// Reformat useful data for future printing by JSON engine
		$srchres = array ();
		foreach ((array)$data['query']['allpages'] as $pageinfo) {
			// Note: this data will no be printable by the xml engine
			// because it does not support lists of unnamed items
			$srchres[] = $pageinfo['title'];
		}

		return $srchres;
	}

	/**
	 * Validate an array of numerical namespace indexes
	 *
	 * @param $namespaces Array
	 * @return Array
	 */
	protected static function validateNamespaces($namespaces){
		global $wgContLang;
		$validNamespaces = $wgContLang->getNamespaces();
		if( is_array($namespaces) && count($namespaces)>0 ){
			$valid = array();
			foreach ($namespaces as $ns){
				if( is_numeric($ns) && array_key_exists($ns, $validNamespaces) )
					$valid[] = $ns;
			}
			if( count($valid) > 0 )
				return $valid;
		}

		return array( NS_MAIN );
	}
}
