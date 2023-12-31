<?php
/**
 * Metadata.php -- provides DublinCore and CreativeCommons metadata
 * Copyright 2004, Evan Prodromou <evan@wikitravel.org>.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @author Evan Prodromou <evan@wikitravel.org>
 */

abstract class RdfMetaData {
	const RDF_TYPE_PREFS = 'application/rdf+xml,text/xml;q=0.7,application/xml;q=0.5,text/rdf;q=0.1';

	/**
	 * Constructor
	 * @param $article Article object
	 */
	public function __construct( Article $article ){
		$this->mArticle = $article;
	}

	public abstract function show();

	/**
	 *
	 */
	protected function setup() {
		global $wgOut, $wgRequest;

		$httpaccept = isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : null;
		$rdftype = wfNegotiateType( wfAcceptToPrefs( $httpaccept ), wfAcceptToPrefs( self::RDF_TYPE_PREFS ) );

		if( !$rdftype ){
			wfHttpError( 406, 'Not Acceptable', wfMsg( 'notacceptable' ) );
			return false;
		} else {
			$wgOut->disable();
			$wgRequest->response()->header( "Content-type: {$rdftype}; charset=utf-8" );
			$wgOut->sendCacheControl();
			return true;
		}
	}

	/**
	 *
	 */
	protected function reallyFullUrl() {
		return $this->mArticle->getTitle()->getFullURL();
	}

	protected function basics() {
		global $wgContLanguageCode, $wgSitename;

		$this->element( 'title', $this->mArticle->mTitle->getText() );
		$this->pageOrString( 'publisher', wfMsg( 'aboutpage' ), $wgSitename );
		$this->element( 'language', $wgContLanguageCode );
		$this->element( 'type', 'Text' );
		$this->element( 'format', 'text/html' );
		$this->element( 'identifier', $this->reallyFullUrl() );
		$this->element( 'date', $this->date( $this->mArticle->getTimestamp() ) );

		$lastEditor = User::newFromId( $this->mArticle->getUser() );
		$this->person( 'creator', $lastEditor );

		foreach( $this->mArticle->getContributors() as $user ){
			$this->person( 'contributor', $user );
		}

		$this->rights();
	}

	protected function element( $name, $value ) {
		$value = htmlspecialchars( $value );
		print "\t\t<dc:{$name}>{$value}</dc:{$name}>\n";
	}

	protected function date($timestamp) {
		return substr($timestamp, 0, 4) . '-'
		  . substr($timestamp, 4, 2) . '-'
		  . substr($timestamp, 6, 2);
	}

	protected function pageOrString( $name, $page, $str ){
		if( $page instanceof Title )
			$nt = $page;
		else
			$nt = Title::newFromText( $page );

		if( !$nt || $nt->getArticleID() == 0 ){
			$this->element( $name, $str );
		} else {
			$this->page( $name, $nt );
		}
	}

	protected function page( $name, $title ){
		$this->url( $name, $title->getFullUrl() );
	}

	protected function url($name, $url) {
		$url = htmlspecialchars( $url );
		print "\t\t<dc:{$name} rdf:resource=\"{$url}\" />\n";
	}

	protected function person($name, User $user ){
		global $wgContLang;

		if( $user->isAnon() ){
			$this->element( $name, wfMsgExt( 'anonymous', array( 'parsemag' ), 1 ) );
		} else if( $real = $user->getRealName() ) {
			$this->element( $name, $real );
		} else {
			$this->pageOrString( $name, $user->getUserPage(), wfMsg( 'siteuser', $user->getName() ) );
		}
	}

	/**
	 * Takes an arg, for future enhancement with different rights for
	 * different pages.
	 */
	protected function rights() {
		global $wgRightsPage, $wgRightsUrl, $wgRightsText;

		if( $wgRightsPage && ( $nt = Title::newFromText( $wgRightsPage ) )
			&& ($nt->getArticleID() != 0)) {
			$this->page('rights', $nt);
		} else if( $wgRightsUrl ){
			$this->url('rights', $wgRightsUrl);
		} else if( $wgRightsText ){
			$this->element( 'rights', $wgRightsText );
		}
	}

	protected function getTerms( $url ){
		global $wgLicenseTerms;

		if( $wgLicenseTerms ){
			return $wgLicenseTerms;
		} else {
			$known = $this->getKnownLicenses();
			if( isset( $known[$url] ) ) {
				return $known[$url];
			} else {
				return array();
			}
		}
	}

	protected function getKnownLicenses() {
		$ccLicenses = array('by', 'by-nd', 'by-nd-nc', 'by-nc',
							'by-nc-sa', 'by-sa');
		$ccVersions = array('1.0', '2.0');
		$knownLicenses = array();

		foreach ($ccVersions as $version) {
			foreach ($ccLicenses as $license) {
				if( $version == '2.0' && substr( $license, 0, 2) != 'by' ) {
					# 2.0 dropped the non-attribs licenses
					continue;
				}
				$lurl = "http://creativecommons.org/licenses/{$license}/{$version}/";
				$knownLicenses[$lurl] = explode('-', $license);
				$knownLicenses[$lurl][] = 're';
				$knownLicenses[$lurl][] = 'di';
				$knownLicenses[$lurl][] = 'no';
				if (!in_array('nd', $knownLicenses[$lurl])) {
					$knownLicenses[$lurl][] = 'de';
				}
			}
		}

		/* Handle the GPL and LGPL, too. */

		$knownLicenses['http://creativecommons.org/licenses/GPL/2.0/'] =
		  array('de', 're', 'di', 'no', 'sa', 'sc');
		$knownLicenses['http://creativecommons.org/licenses/LGPL/2.1/'] =
		  array('de', 're', 'di', 'no', 'sa', 'sc');
		$knownLicenses['http://www.gnu.org/copyleft/fdl.html'] =
		  array('de', 're', 'di', 'no', 'sa', 'sc');

		return $knownLicenses;
	}
}

class DublinCoreRdf extends RdfMetaData {

	public function show(){
		if( $this->setup() ){
			$this->prologue();
			$this->basics();
			$this->epilogue();
		}
	}

	/**
	 * begin of the page
	 */
	protected function prologue() {
		global $wgOutputEncoding;

		$url = htmlspecialchars( $this->reallyFullUrl() );
		print <<<PROLOGUE
<?xml version="1.0" encoding="{$wgOutputEncoding}" ?>
<!DOCTYPE rdf:RDF PUBLIC "-//DUBLIN CORE//DCMES DTD 2002/07/31//EN" "http://dublincore.org/documents/2002/07/31/dcmes-xml/dcmes-xml-dtd.dtd">
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:dc="http://purl.org/dc/elements/1.1/">
	<rdf:Description rdf:about="{$url}">

PROLOGUE;
	}

	/**
	 * end of the page
	 */
	protected function epilogue() {
		print <<<EPILOGUE
	</rdf:Description>
</rdf:RDF>
EPILOGUE;
	}
}

class CreativeCommonsRdf extends RdfMetaData {

	public function show(){
		if( $this->setup() ){
			global $wgRightsUrl;

			$url = $this->reallyFullUrl();

			$this->prologue();
			$this->subPrologue('Work', $url);

			$this->basics();
			if( $wgRightsUrl ){
				$url = htmlspecialchars( $wgRightsUrl );
				print "\t\t<cc:license rdf:resource=\"$url\" />\n";
			}

			$this->subEpilogue('Work');

			if( $wgRightsUrl ){
				$terms = $this->getTerms( $wgRightsUrl );
				if( $terms ){
					$this->subPrologue( 'License', $wgRightsUrl );
					$this->license( $terms );
					$this->subEpilogue( 'License' );
				}
			}
		}

		$this->epilogue();
	}

	protected function prologue() {
		global $wgOutputEncoding;
		echo <<<PROLOGUE
<?xml version='1.0'  encoding="{$wgOutputEncoding}" ?>
<rdf:RDF xmlns:cc="http://web.resource.org/cc/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">

PROLOGUE;
	}

	protected function subPrologue( $type, $url ){
		$url = htmlspecialchars( $url );
		echo "\t<cc:{$type} rdf:about=\"{$url}\">\n";
	}

	protected function subEpilogue($type) {
		echo "\t</cc:{$type}>\n";
	}

	protected function license($terms) {

		foreach( $terms as $term ){
			switch( $term ) {
			 case 're':
				$this->term('permits', 'Reproduction'); break;
			 case 'di':
				$this->term('permits', 'Distribution'); break;
			 case 'de':
				$this->term('permits', 'DerivativeWorks'); break;
			 case 'nc':
				$this->term('prohibits', 'CommercialUse'); break;
			 case 'no':
				$this->term('requires', 'Notice'); break;
			 case 'by':
				$this->term('requires', 'Attribution'); break;
			 case 'sa':
				$this->term('requires', 'ShareAlike'); break;
			 case 'sc':
				$this->term('requires', 'SourceCode'); break;
			}
		}
	}

	protected function term( $term, $name ){
		print "\t\t<cc:{$term} rdf:resource=\"http://web.resource.org/cc/{$name}\" />\n";
	}

	protected function epilogue() {
		echo "</rdf:RDF>\n";
	}
}