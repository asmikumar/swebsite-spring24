<?php

# Copyright (C) 2004 Brion Vibber <brion@pobox.com>
# http://www.mediawiki.org/
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
# http://www.gnu.org/copyleft/gpl.html

/**
 * @defgroup Feed Feed
 *
 * Basic support for outputting syndication feeds in RSS, other formats.
 * Contain a feed class as well as classes to build rss / atom ... feeds
 * Available feeds are defined in Defines.php
 *
 * @file
 */

/**
 * A base class for basic support for outputting syndication feeds in RSS and other formats.
 *
 * @ingroup Feed
 */
class FeedItem {
	/**#@+
	 * @var string
	 * @private
	 */
	var $Title = 'Wiki';
	var $Description = '';
	var $Url = '';
	var $Date = '';
	var $Author = '';
	var $UniqueId = '';
	var $RSSIsPermalink;
	/**#@-*/

	/**
	 * Constructor
	 *
	 * @param $Title String: Item's title
	 * @param $Description String
	 * @param $Url String: URL uniquely designating the item.
	 * @param $Date String: Item's date
	 * @param $Author String: Author's user name
	 * @param $Comments String
	 */
	function __construct( $Title, $Description, $Url, $Date = '', $Author = '', $Comments = '' ) {
		$this->Title = $Title;
		$this->Description = $Description;
		$this->Url = $Url;
		$this->UniqueId = $Url;
		$this->RSSIsPermalink = false;
		$this->Date = $Date;
		$this->Author = $Author;
		$this->Comments = $Comments;
	}

	/**
	 * Encode $string so that it can be safely embedded in a XML document
	 *
	 * @param $string String: string to encode
	 * @return String
	 */
	public function xmlEncode( $string ) {
		$string = str_replace( "\r\n", "\n", $string );
		$string = preg_replace( '/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $string );
		return htmlspecialchars( $string );
	}

	/**
	 * Get the unique id of this item
	 *
	 * @return String
	 */
	public function getUniqueId() {
		if ( $this->UniqueId ) {
			return $this->xmlEncode( $this->UniqueId );
		}
	}

	/**
	 * set the unique id of an item
	 *
	 * @param $uniqueId String: unique id for the item
	 * @param $RSSisPermalink Boolean: set to true if the guid (unique id) is a permalink (RSS feeds only)
	 */
	public function setUniqueId($uniqueId, $RSSisPermalink = False) {
		$this->UniqueId = $uniqueId;
		$this->RSSIsPermalink = $isPermalink;
	}

	/**
	 * Get the title of this item; already xml-encoded
	 *
	 * @return String
	 */
	public function getTitle() {
		return $this->xmlEncode( $this->Title );
	}

	/**
	 * Get the URL of this item; already xml-encoded
	 *
	 * @return String
	 */
	public function getUrl() {
		return $this->xmlEncode( $this->Url );
	}

	/**
	 * Get the description of this item; already xml-encoded
	 *
	 * @return String
	 */
	public function getDescription() {
		return $this->xmlEncode( $this->Description );
	}

	/**
	 * Get the language of this item
	 *
	 * @return String
	 */
	public function getLanguage() {
		global $wgContLanguageCode;
		return $wgContLanguageCode;
	}

	/**
	 * Get the title of this item
	 *
	 * @return String
	 */
	public function getDate() {
		return $this->Date;
	}

	/**
	 * Get the author of this item; already xml-encoded
	 *
	 * @return String
	 */
	public function getAuthor() {
		return $this->xmlEncode( $this->Author );
	}

	/**
	 * Get the comment of this item; already xml-encoded
	 *
	 * @return String
	 */
	public function getComments() {
		return $this->xmlEncode( $this->Comments );
	}

	/**
	 * Quickie hack... strip out wikilinks to more legible form from the comment.
	 *
	 * @param $text String: wikitext
	 * @return String
	 */
	public static function stripComment( $text ) {
		return preg_replace( '/\[\[([^]]*\|)?([^]]+)\]\]/', '\2', $text );
	}
	/**#@-*/
}

/**
 * @todo document (needs one-sentence top-level class description).
 * @ingroup Feed
 */
class ChannelFeed extends FeedItem {
	/**#@+
	 * Abstract function, override!
	 * @abstract
	 */

	/**
	 * Generate Header of the feed
	 */
	function outHeader() {
		# print "<feed>";
	}

	/**
	 * Generate an item
	 * @param $item
	 */
	function outItem( $item ) {
		# print "<item>...</item>";
	}

	/**
	 * Generate Footer of the feed
	 */
	function outFooter() {
		# print "</feed>";
	}
	/**#@-*/

	/**
	 * Setup and send HTTP headers. Don't send any content;
	 * content might end up being cached and re-sent with
	 * these same headers later.
	 *
	 * This should be called from the outHeader() method,
	 * but can also be called separately.
	 */
	public function httpHeaders() {
		global $wgOut;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();
		$mimetype = $this->contentType();
		header( "Content-type: $mimetype; charset=UTF-8" );
		$wgOut->sendCacheControl();

	}

	/**
	 * Return an internet media type to be sent in the headers.
	 *
	 * @return string
	 * @private
	 */
	function contentType() {
		global $wgRequest;
		$ctype = $wgRequest->getVal('ctype','application/xml');
		$allowedctypes = array('application/xml','text/xml','application/rss+xml','application/atom+xml');
		return (in_array($ctype, $allowedctypes) ? $ctype : 'application/xml');
	}

	/**
	 * Output the initial XML headers with a stylesheet for legibility
	 * if someone finds it in a browser.
	 * @private
	 */
	function outXmlHeader() {
		global $wgStylePath, $wgStyleVersion;

		$this->httpHeaders();
		echo '<?xml version="1.0"?>' . "\n";
		echo '<?xml-stylesheet type="text/css" href="' .
			htmlspecialchars( wfExpandUrl( "$wgStylePath/common/feed.css?$wgStyleVersion" ) ) .
			'"?' . ">\n";
	}
}

/**
 * Generate a RSS feed
 *
 * @ingroup Feed
 */
class RSSFeed extends ChannelFeed {

	/**
	 * Format a date given a timestamp
	 *
	 * @param $ts Integer: timestamp
	 * @return String: date string
	 */
	function formatTime( $ts ) {
		return gmdate( 'D, d M Y H:i:s \G\M\T', wfTimestamp( TS_UNIX, $ts ) );
	}

	/**
	 * Ouput an RSS 2.0 header
	 */
	function outHeader() {
		global $wgVersion;

		$this->outXmlHeader();
		?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<title><?php print $this->getTitle() ?></title>
		<link><?php print $this->getUrl() ?></link>
		<description><?php print $this->getDescription() ?></description>
		<language><?php print $this->getLanguage() ?></language>
		<generator>MediaWiki <?php print $wgVersion ?></generator>
		<lastBuildDate><?php print $this->formatTime( wfTimestampNow() ) ?></lastBuildDate>
<?php
	}

	/**
	 * Output an RSS 2.0 item
	 * @param $item FeedItem: item to be output
	 */
	function outItem( $item ) {
	?>
		<item>
			<title><?php print $item->getTitle() ?></title>
			<link><?php print $item->getUrl() ?></link>
			<guid<?php if( $item->RSSIsPermalink ) print ' isPermaLink="true"' ?>><?php print $item->getUniqueId() ?></guid>
			<description><?php print $item->getDescription() ?></description>
			<?php if( $item->getDate() ) { ?><pubDate><?php print $this->formatTime( $item->getDate() ) ?></pubDate><?php } ?>
			<?php if( $item->getAuthor() ) { ?><dc:creator><?php print $item->getAuthor() ?></dc:creator><?php }?>
			<?php if( $item->getComments() ) { ?><comments><?php print $item->getComments() ?></comments><?php }?>
		</item>
<?php
	}

	/**
	 * Ouput an RSS 2.0 footer
	 */
	function outFooter() {
	?>
	</channel>
</rss><?php
	}
}

/**
 * Generate an Atom feed
 *
 * @ingroup Feed
 */
class AtomFeed extends ChannelFeed {
	/**
	 * @todo document
	 */
	function formatTime( $ts ) {
		// need to use RFC 822 time format at least for rss2.0
		return gmdate( 'Y-m-d\TH:i:s', wfTimestamp( TS_UNIX, $ts ) );
	}

	/**
	 * Outputs a basic header for Atom 1.0 feeds.
	 */
	function outHeader() {
		global $wgVersion;

		$this->outXmlHeader();
		?><feed xmlns="http://www.w3.org/2005/Atom" xml:lang="<?php print $this->getLanguage() ?>">
		<id><?php print $this->getFeedId() ?></id>
		<title><?php print $this->getTitle() ?></title>
		<link rel="self" type="application/atom+xml" href="<?php print $this->getSelfUrl() ?>"/>
		<link rel="alternate" type="text/html" href="<?php print $this->getUrl() ?>"/>
		<updated><?php print $this->formatTime( wfTimestampNow() ) ?>Z</updated>
		<subtitle><?php print $this->getDescription() ?></subtitle>
		<generator>MediaWiki <?php print $wgVersion ?></generator>

<?php
	}

	/**
	 * Atom 1.0 requires a unique, opaque IRI as a unique indentifier
	 * for every feed we create. For now just use the URL, but who
	 * can tell if that's right? If we put options on the feed, do we
	 * have to change the id? Maybe? Maybe not.
	 *
	 * @return string
	 * @private
	 */
	function getFeedId() {
		return $this->getSelfUrl();
	}

	/**
	 * Atom 1.0 requests a self-reference to the feed.
	 * @return string
	 * @private
	 */
	function getSelfUrl() {
		global $wgRequest;
		return htmlspecialchars( $wgRequest->getFullRequestURL() );
	}

	/**
	 * Output a given item.
	 * @param $item
	 */
	function outItem( $item ) {
		global $wgMimeType;
	?>
	<entry>
		<id><?php print $item->getUniqueId() ?></id>
		<title><?php print $item->getTitle() ?></title>
		<link rel="alternate" type="<?php print $wgMimeType ?>" href="<?php print $item->getUrl() ?>"/>
		<?php if( $item->getDate() ) { ?>
		<updated><?php print $this->formatTime( $item->getDate() ) ?>Z</updated>
		<?php } ?>

		<summary type="html"><?php print $item->getDescription() ?></summary>
		<?php if( $item->getAuthor() ) { ?><author><name><?php print $item->getAuthor() ?></name></author><?php }?>
	</entry>

<?php /* FIXME need to add comments
	<?php if( $item->getComments() ) { ?><dc:comment><?php print $item->getComments() ?></dc:comment><?php }?>
      */
	}

	/**
	 * Outputs the footer for Atom 1.0 feed (basicly '\</feed\>').
	 */
	function outFooter() {?>
	</feed><?php
	}
}
