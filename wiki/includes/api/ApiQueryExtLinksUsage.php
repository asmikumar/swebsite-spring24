<?php

/*
 * Created on July 7, 2007
 *
 * API for MediaWiki 1.8+
 *
 * Copyright (C) 2006 Yuri Astrakhan <Firstname><Lastname>@gmail.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	// Eclipse helper - will be ignored in production
	require_once ( 'ApiQueryBase.php' );
}

/**
 * @ingroup API
 */
class ApiQueryExtLinksUsage extends ApiQueryGeneratorBase {

	public function __construct( $query, $moduleName ) {
		parent :: __construct( $query, $moduleName, 'eu' );
	}

	public function execute() {
		$this->run();
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	private function run( $resultPageSet = null ) {

		$params = $this->extractRequestParams();

		$protocol = $params['protocol'];
		$query = $params['query'];

		// Find the right prefix
		global $wgUrlProtocols;
		if ( $protocol && !in_array( $protocol, $wgUrlProtocols ) )
		{
			foreach ( $wgUrlProtocols as $p ) {
				if ( substr( $p, 0, strlen( $protocol ) ) === $protocol ) {
					$protocol = $p;
					break;
				}
			}
		}
		else
			$protocol = null;

		$db = $this->getDB();
		$this->addTables( array( 'page', 'externallinks' ) );	// must be in this order for 'USE INDEX'
		$this->addOption( 'USE INDEX', 'el_index' );
		$this->addWhere( 'page_id=el_from' );
		$this->addWhereFld( 'page_namespace', $params['namespace'] );

		if ( !is_null( $query ) || $query != '' )
		{
			if ( is_null( $protocol ) )
				$protocol = 'http://';

			$likeQuery = LinkFilter::makeLikeArray( $query, $protocol );
			if ( !$likeQuery )
				$this->dieUsage( 'Invalid query', 'bad_query' );

			$likeQuery = LinkFilter::keepOneWildcard( $likeQuery );
			$this->addWhere( 'el_index ' . $db->buildLike( $likeQuery ) );
		}
		else if ( !is_null( $protocol ) )
			$this->addWhere( 'el_index ' . $db->buildLike( "$protocol", $db->anyString() ) );

		$prop = array_flip( $params['prop'] );
		$fld_ids = isset( $prop['ids'] );
		$fld_title = isset( $prop['title'] );
		$fld_url = isset( $prop['url'] );

		if ( is_null( $resultPageSet ) ) {
			$this->addFields( array (
				'page_id',
				'page_namespace',
				'page_title'
			) );
			$this->addFieldsIf( 'el_to', $fld_url );
		} else {
			$this->addFields( $resultPageSet->getPageTableFields() );
		}

		$limit = $params['limit'];
		$offset = $params['offset'];
		$this->addOption( 'LIMIT', $limit + 1 );
		if ( isset ( $offset ) )
			$this->addOption( 'OFFSET', $offset );

		$res = $this->select( __METHOD__ );

		$result = $this->getResult();
		$count = 0;
		while ( $row = $db->fetchObject( $res ) ) {
			if ( ++ $count > $limit ) {
				// We've reached the one extra which shows that there are additional pages to be had. Stop here...
				$this->setContinueEnumParameter( 'offset', $offset + $limit );
				break;
			}

			if ( is_null( $resultPageSet ) ) {
				$vals = array();
				if ( $fld_ids )
					$vals['pageid'] = intval( $row->page_id );
				if ( $fld_title ) {
					$title = Title :: makeTitle( $row->page_namespace, $row->page_title );
					ApiQueryBase::addTitleInfo( $vals, $title );
				}
				if ( $fld_url )
					$vals['url'] = $row->el_to;
				$fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $vals );
				if ( !$fit )
				{
					$this->setContinueEnumParameter( 'offset', $offset + $count - 1 );
					break;
				}
			} else {
				$resultPageSet->processDbRow( $row );
			}
		}
		$db->freeResult( $res );

		if ( is_null( $resultPageSet ) ) {
			$result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ),
					$this->getModulePrefix() );
		}
	}

	public function getAllowedParams() {
		global $wgUrlProtocols;
		$protocols = array( '' );
		foreach ( $wgUrlProtocols as $p ) {
			$protocols[] = substr( $p, 0, strpos( $p, ':' ) );
		}

		return array (
			'prop' => array (
				ApiBase :: PARAM_ISMULTI => true,
				ApiBase :: PARAM_DFLT => 'ids|title|url',
				ApiBase :: PARAM_TYPE => array (
					'ids',
					'title',
					'url'
				)
			),
			'offset' => array (
				ApiBase :: PARAM_TYPE => 'integer'
			),
			'protocol' => array (
				ApiBase :: PARAM_TYPE => $protocols,
				ApiBase :: PARAM_DFLT => '',
			),
			'query' => null,
			'namespace' => array (
				ApiBase :: PARAM_ISMULTI => true,
				ApiBase :: PARAM_TYPE => 'namespace'
			),
			'limit' => array (
				ApiBase :: PARAM_DFLT => 10,
				ApiBase :: PARAM_TYPE => 'limit',
				ApiBase :: PARAM_MIN => 1,
				ApiBase :: PARAM_MAX => ApiBase :: LIMIT_BIG1,
				ApiBase :: PARAM_MAX2 => ApiBase :: LIMIT_BIG2
			)
		);
	}

	public function getParamDescription() {
		return array (
			'prop' => 'What pieces of information to include',
			'offset' => 'Used for paging. Use the value returned for "continue"',
			'protocol' => array(	'Protocol of the url. If empty and euquery set, the protocol is http.',
						'Leave both this and euquery empty to list all external links' ),
			'query' => 'Search string without protocol. See [[Special:LinkSearch]]. Leave empty to list all external links',
			'namespace' => 'The page namespace(s) to enumerate.',
			'limit' => 'How many pages to return.'
		);
	}

	public function getDescription() {
		return 'Enumerate pages that contain a given URL';
	}
	
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'bad_query', 'info' => 'Invalid query' ),
		) );
	}

	protected function getExamples() {
		return array (
			'api.php?action=query&list=exturlusage&euquery=www.mediawiki.org'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiQueryExtLinksUsage.php 69932 2010-07-26 08:03:21Z tstarling $';
	}
}
