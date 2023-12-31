<?php
/**
 * This script is the SQLite database abstraction layer
 *
 * See maintenance/sqlite/README for development notes and other specific information
 * @ingroup Database
 * @file
 */

/**
 * @ingroup Database
 */
class DatabaseSqlite extends DatabaseBase {

	var $mAffectedRows;
	var $mLastResult;
	var $mDatabaseFile;
	var $mName;

	/**
	 * Constructor.
	 * Parameters $server, $user and $password are not used.
	 */
	function __construct( $server = false, $user = false, $password = false, $dbName = false, $failFunction = false, $flags = 0 ) {
		$this->mFailFunction = $failFunction;
		$this->mFlags = $flags;
		$this->mName = $dbName;
		$this->open( $server, $user, $password, $dbName );
	}

	function getType() {
		return 'sqlite';
	}

	/**
	 * @todo: check if it should be true like parent class
	 */
	function implicitGroupby()   { return false; }

	static function newFromParams( $server, $user, $password, $dbName, $failFunction = false, $flags = 0 ) {
		return new DatabaseSqlite( $server, $user, $password, $dbName, $failFunction, $flags );
	}

	/** Open an SQLite database and return a resource handle to it
	 *  NOTE: only $dbName is used, the other parameters are irrelevant for SQLite databases
	 */
	function open( $server, $user, $pass, $dbName ) {
		global $wgSQLiteDataDir;

		$fileName = self::generateFileName( $wgSQLiteDataDir, $dbName );
		if ( !is_readable( $fileName ) ) {
			throw new DBConnectionError( $this, "SQLite database not accessible" );		$this->mConn = false;
		}
		$this->openFile( $fileName );
		return $this->mConn;
	}

	/**
	 * Opens a database file
	 * @return SQL connection or false if failed
	 */
	function openFile( $fileName ) {
		$this->mDatabaseFile = $fileName;
		try {
			if ( $this->mFlags & DBO_PERSISTENT ) {
				$this->mConn = new PDO( "sqlite:$fileName", '', '',
					array( PDO::ATTR_PERSISTENT => true ) );
			} else {
				$this->mConn = new PDO( "sqlite:$fileName", '', '' );
			}
		} catch ( PDOException $e ) {
			$err = $e->getMessage();
		}
		if ( $this->mConn === false ) {
			wfDebug( "DB connection error: $err\n" );
			if ( !$this->mFailFunction ) {
				throw new DBConnectionError( $this, $err );
			} else {
				return false;
			}

		}
		$this->mOpened = !!$this->mConn;
		# set error codes only, don't raise exceptions
		if ( $this->mOpened ) {
			$this->mConn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );
			return true;
		}
	}

	/**
	 * Close an SQLite database
	 */
	function close() {
		$this->mOpened = false;
		if ( is_object( $this->mConn ) ) {
			if ( $this->trxLevel() ) $this->commit();
			$this->mConn = null;
		}
		return true;
	}

	/**
	 * Generates a database file name. Explicitly public for installer.
	 * @param $dir String: Directory where database resides
	 * @param $dbName String: Database name
	 * @return String
	 */
	public static function generateFileName( $dir, $dbName ) {
		return "$dir/$dbName.sqlite";
	}

	/**
	 * Returns version of currently supported SQLite fulltext search module or false if none present.
	 * @return String
	 */
	function getFulltextSearchModule() {
		$table = 'dummy_search_test';
		$this->query( "DROP TABLE IF EXISTS $table", __METHOD__ );
		if ( $this->query( "CREATE VIRTUAL TABLE $table USING FTS3(dummy_field)", __METHOD__, true ) ) {
			$this->query( "DROP TABLE IF EXISTS $table", __METHOD__ );
			return 'FTS3';
		}
		return false;
	}

	/**
	 * SQLite doesn't allow buffered results or data seeking etc, so we'll use fetchAll as the result
	 */
	function doQuery( $sql ) {
		$res = $this->mConn->query( $sql );
		if ( $res === false ) {
			return false;
		} else {
			$r = $res instanceof ResultWrapper ? $res->result : $res;
			$this->mAffectedRows = $r->rowCount();
			$res = new ResultWrapper( $this, $r->fetchAll() );
		}
		return $res;
	}

	function freeResult( $res ) {
		if ( $res instanceof ResultWrapper )
			$res->result = null;
		else
			$res = null;
	}

	function fetchObject( $res ) {
		if ( $res instanceof ResultWrapper )
			$r =& $res->result;
		else
			$r =& $res;

		$cur = current( $r );
		if ( is_array( $cur ) ) {
			next( $r );
			$obj = new stdClass;
			foreach ( $cur as $k => $v )
				if ( !is_numeric( $k ) )
					$obj->$k = $v;

			return $obj;
		}
		return false;
	}

	function fetchRow( $res ) {
		if ( $res instanceof ResultWrapper )
			$r =& $res->result;
		else
			$r =& $res;

		$cur = current( $r );
		if ( is_array( $cur ) ) {
			next( $r );
			return $cur;
		}
		return false;
	}

	/**
	 * The PDO::Statement class implements the array interface so count() will work
	 */
	function numRows( $res ) {
		$r = $res instanceof ResultWrapper ? $res->result : $res;
		return count( $r );
	}

	function numFields( $res ) {
		$r = $res instanceof ResultWrapper ? $res->result : $res;
		return is_array( $r ) ? count( $r[0] ) : 0;
	}

	function fieldName( $res, $n ) {
		$r = $res instanceof ResultWrapper ? $res->result : $res;
		if ( is_array( $r ) ) {
			$keys = array_keys( $r[0] );
			return $keys[$n];
		}
		return  false;
	}

	/**
	 * Use MySQL's naming (accounts for prefix etc) but remove surrounding backticks
	 */
	function tableName( $name ) {
		return str_replace( '`', '', parent::tableName( $name ) );
	}

	/**
	 * Index names have DB scope
	 */
	function indexName( $index ) {
		return $index;
	}

	/**
	 * This must be called after nextSequenceVal
	 */
	function insertId() {
		return $this->mConn->lastInsertId();
	}

	function dataSeek( $res, $row ) {
		if ( $res instanceof ResultWrapper )
			$r =& $res->result;
		else
			$r =& $res;
		reset( $r );
		if ( $row > 0 )
			for ( $i = 0; $i < $row; $i++ )
				next( $r );
	}

	function lastError() {
		if ( !is_object( $this->mConn ) )
			return "Cannot return last error, no db connection";
		$e = $this->mConn->errorInfo();
		return isset( $e[2] ) ? $e[2] : '';
	}

	function lastErrno() {
		if ( !is_object( $this->mConn ) ) {
			return "Cannot return last error, no db connection";
		} else {
			$info = $this->mConn->errorInfo();
			return $info[1];
		}
	}

	function affectedRows() {
		return $this->mAffectedRows;
	}

	/**
	 * Returns information about an index
	 * Returns false if the index does not exist
	 * - if errors are explicitly ignored, returns NULL on failure
	 */
	function indexInfo( $table, $index, $fname = 'DatabaseSqlite::indexExists' ) {
		$sql = 'PRAGMA index_info(' . $this->addQuotes( $this->indexName( $index ) ) . ')';
		$res = $this->query( $sql, $fname );
		if ( !$res ) {
			return null;
		}
		if ( $res->numRows() == 0 ) {
			return false;
		}
		$info = array();
		foreach ( $res as $row ) {
			$info[] = $row->name;
		}
		return $info;
	}

	function indexUnique( $table, $index, $fname = 'DatabaseSqlite::indexUnique' ) {
		$row = $this->selectRow( 'sqlite_master', '*',
			array(
				'type' => 'index',
				'name' => $this->indexName( $index ),
			), $fname );
		if ( !$row || !isset( $row->sql ) ) {
			return null;
		}

		// $row->sql will be of the form CREATE [UNIQUE] INDEX ...
		$indexPos = strpos( $row->sql, 'INDEX' );
		if ( $indexPos === false ) {
			return null;
		}
		$firstPart = substr( $row->sql, 0, $indexPos );
		$options = explode( ' ', $firstPart );
		return in_array( 'UNIQUE', $options );
	}

	/**
	 * Filter the options used in SELECT statements
	 */
	function makeSelectOptions( $options ) {
		foreach ( $options as $k => $v )
			if ( is_numeric( $k ) && $v == 'FOR UPDATE' )
				$options[$k] = '';
		return parent::makeSelectOptions( $options );
	}

	/**
	 * Based on generic method (parent) with some prior SQLite-sepcific adjustments
	 */
	function insert( $table, $a, $fname = 'DatabaseSqlite::insert', $options = array() ) {
		if ( !count( $a ) ) return true;
		if ( !is_array( $options ) ) $options = array( $options );

		# SQLite uses OR IGNORE not just IGNORE
		foreach ( $options as $k => $v )
			if ( $v == 'IGNORE' )
				$options[$k] = 'OR IGNORE';

		# SQLite can't handle multi-row inserts, so divide up into multiple single-row inserts
		if ( isset( $a[0] ) && is_array( $a[0] ) ) {
			$ret = true;
			foreach ( $a as $k => $v )
				if ( !parent::insert( $table, $v, "$fname/multi-row", $options ) )
					$ret = false;
		} else {
			$ret = parent::insert( $table, $a, "$fname/single-row", $options );
		}

		return $ret;
	}

	function replace( $table, $uniqueIndexes, $rows, $fname = 'DatabaseSqlite::replace' ) {
		if ( !count( $rows ) ) return true;
	
		# SQLite can't handle multi-row replaces, so divide up into multiple single-row queries
		if ( isset( $rows[0] ) && is_array( $rows[0] ) ) {
			$ret = true;
			foreach ( $rows as $k => $v )
				if ( !parent::replace( $table, $uniqueIndexes, $v, "$fname/multi-row" ) )
					$ret = false;
		} else {
			$ret = parent::replace( $table, $uniqueIndexes, $rows, "$fname/single-row" );
		}

		return $ret;
	}

	/**
	 * Returns the size of a text field, or -1 for "unlimited"
	 * In SQLite this is SQLITE_MAX_LENGTH, by default 1GB. No way to query it though.
	 */
	function textFieldSize( $table, $field ) {
		return - 1;
	}

	function unionSupportsOrderAndLimit() {
		return false;
	}

	function unionQueries( $sqls, $all ) {
		$glue = $all ? ' UNION ALL ' : ' UNION ';
		return implode( $glue, $sqls );
	}

	function wasDeadlock() {
		return $this->lastErrno() == 5; // SQLITE_BUSY
	}

	function wasErrorReissuable() {
		return $this->lastErrno() ==  17; // SQLITE_SCHEMA;
	}

	function wasReadOnlyError() {
		return $this->lastErrno() == 8; // SQLITE_READONLY;
	}

	/**
	 * @return string wikitext of a link to the server software's web site
	 */
	function getSoftwareLink() {
		return "[http://sqlite.org/ SQLite]";
	}

	/**
	 * @return string Version information from the database
	 */
	function getServerVersion() {
		$ver = $this->mConn->getAttribute( PDO::ATTR_SERVER_VERSION );
		return $ver;
	}

	/**
	 * Query whether a given column exists in the mediawiki schema
	 */
	function fieldExists( $table, $field, $fname = '' ) {
		$info = $this->fieldInfo( $table, $field );
		return (bool)$info;
	}

	/**
	 * Get information about a given field
	 * Returns false if the field does not exist.
	 */
	function fieldInfo( $table, $field ) {
		$tableName = $this->tableName( $table );
		$sql = 'PRAGMA table_info(' . $this->addQuotes( $tableName ) . ')';
		$res = $this->query( $sql, __METHOD__ );
		foreach ( $res as $row ) {
			if ( $row->name == $field ) {
				return new SQLiteField( $row, $tableName );
			}
		}
		return false;
	}

	function begin( $fname = '' ) {
		if ( $this->mTrxLevel == 1 ) $this->commit();
		$this->mConn->beginTransaction();
		$this->mTrxLevel = 1;
	}

	function commit( $fname = '' ) {
		if ( $this->mTrxLevel == 0 ) return;
		$this->mConn->commit();
		$this->mTrxLevel = 0;
	}

	function rollback( $fname = '' ) {
		if ( $this->mTrxLevel == 0 ) return;
		$this->mConn->rollBack();
		$this->mTrxLevel = 0;
	}

	function limitResultForUpdate( $sql, $num ) {
		return $this->limitResult( $sql, $num );
	}

	function strencode( $s ) {
		return substr( $this->addQuotes( $s ), 1, - 1 );
	}

	function encodeBlob( $b ) {
		return new Blob( $b );
	}

	function decodeBlob( $b ) {
		if ( $b instanceof Blob ) {
			$b = $b->fetch();
		}
		return $b;
	}

	function addQuotes( $s ) {
		if ( $s instanceof Blob ) {
			return "x'" . bin2hex( $s->fetch() ) . "'";
		} else {
			return $this->mConn->quote( $s );
		}
	}

	function quote_ident( $s ) {
		return $s;
	}

	function buildLike() {
		$params = func_get_args();
		if ( count( $params ) > 0 && is_array( $params[0] ) ) {
			$params = $params[0];
		}
		return parent::buildLike( $params ) . "ESCAPE '\' ";
	}

	/**
	 * How lagged is this slave?
	 */
	public function getLag() {
		return 0;
	}

	/**
	 * Called by the installer script (when modified according to the MediaWikiLite installation instructions)
	 * - this is the same way PostgreSQL works, MySQL reads in tables.sql and interwiki.sql using dbsource (which calls db->sourceFile)
	 */
	public function setup_database() {
		global $IP;

		# Process common MySQL/SQLite table definitions
		$err = $this->sourceFile( "$IP/maintenance/tables.sql" );
		if ( $err !== true ) {
			echo " <b>FAILED</b></li>";
			dieout( htmlspecialchars( $err ) );
		}
		echo " done.</li>";

		# Use DatabasePostgres's code to populate interwiki from MySQL template
		$f = fopen( "$IP/maintenance/interwiki.sql", 'r' );
		if ( $f == false ) {
			dieout( "Could not find the interwiki.sql file." );
		}
		
		$sql = "INSERT INTO interwiki(iw_prefix,iw_url,iw_local) VALUES ";
		while ( !feof( $f ) ) {
			$line = fgets( $f, 1024 );
			$matches = array();
			if ( !preg_match( '/^\s*(\(.+?),(\d)\)/', $line, $matches ) ) continue;
			$this->query( "$sql $matches[1],$matches[2])" );
		}
	}
	
	public function getSearchEngine() {
		return "SearchSqlite";
	}

	/**
	 * No-op version of deadlockLoop
	 */
	public function deadlockLoop( /*...*/ ) {
		$args = func_get_args();
		$function = array_shift( $args );
		return call_user_func_array( $function, $args );
	}

	protected function replaceVars( $s ) {
		$s = parent::replaceVars( $s );
		if ( preg_match( '/^\s*(CREATE|ALTER) TABLE/i', $s ) ) {
			// CREATE TABLE hacks to allow schema file sharing with MySQL

			// binary/varbinary column type -> blob
			$s = preg_replace( '/\b(var)?binary(\(\d+\))/i', 'BLOB', $s );
			// no such thing as unsigned
			$s = preg_replace( '/\b(un)?signed\b/i', '', $s );
			// INT -> INTEGER
			$s = preg_replace( '/\b(tiny|small|medium|big|)int(\([\s\d]*\)|\b)/i', 'INTEGER', $s );
			// varchar -> TEXT
			$s = preg_replace( '/\bvarchar\(\d+\)/i', 'TEXT', $s );
			// TEXT normalization
			$s = preg_replace( '/\b(tiny|medium|long)text\b/i', 'TEXT', $s );
			// BLOB normalization
			$s = preg_replace( '/\b(tiny|small|medium|long|)blob\b/i', 'BLOB', $s );
			// BOOL -> INTEGER
			$s = preg_replace( '/\bbool(ean)?\b/i', 'INTEGER', $s );
			// DATETIME -> TEXT
			$s = preg_replace( '/\b(datetime|timestamp)\b/i', 'TEXT', $s );
			// No ENUM type
			$s = preg_replace( '/enum\([^)]*\)/i', 'BLOB', $s );
			// binary collation type -> nothing
			$s = preg_replace( '/\bbinary\b/i', '', $s );
			// auto_increment -> autoincrement
			$s = preg_replace( '/\bauto_increment\b/i', 'AUTOINCREMENT', $s );
			// No explicit options
			$s = preg_replace( '/\)[^);]*(;?)\s*$/', ')\1', $s );
		} elseif ( preg_match( '/^\s*CREATE (\s*(?:UNIQUE|FULLTEXT)\s+)?INDEX/i', $s ) ) {
			// No truncated indexes
			$s = preg_replace( '/\(\d+\)/', '', $s );
			// No FULLTEXT
			$s = preg_replace( '/\bfulltext\b/i', '', $s );
		}
		return $s;
	}

	/*
	 * Build a concatenation list to feed into a SQL query
	 */
	function buildConcat( $stringList ) {
		return '(' . implode( ') || (', $stringList ) . ')';
	}

	function duplicateTableStructure( $oldName, $newName, $temporary = false, $fname = 'DatabaseSqlite::duplicateTableStructure' ) {
		$res = $this->query( "SELECT sql FROM sqlite_master WHERE tbl_name='$oldName' AND type='table'", $fname );
		$obj = $this->fetchObject( $res );
		if ( !$obj ) {
			throw new MWException( "Couldn't retrieve structure for table $oldName" );
		}
		$sql = $obj->sql;
		$sql = preg_replace( '/\b' . preg_quote( $oldName ) . '\b/', $newName, $sql, 1 );
		return $this->query( $sql, $fname );
	}

} // end DatabaseSqlite class

/**
 * This class allows simple acccess to a SQLite database independently from main database settings
 * @ingroup Database
 */
class DatabaseSqliteStandalone extends DatabaseSqlite {
	public function __construct( $fileName, $flags = 0 ) {
		$this->mFlags = $flags;
		$this->openFile( $fileName );
	}
}

/**
 * @ingroup Database
 */
class SQLiteField {
	private $info, $tableName;
	function __construct( $info, $tableName ) {
		$this->info = $info;
		$this->tableName = $tableName;
	}

	function name() {
		return $this->info->name;
	}

	function tableName() {
		return $this->tableName;
	}

	function defaultValue() {
		if ( is_string( $this->info->dflt_value ) ) {
			// Typically quoted
			if ( preg_match( '/^\'(.*)\'$', $this->info->dflt_value ) ) {
				return str_replace( "''", "'", $this->info->dflt_value );
			}
		}
		return $this->info->dflt_value;
	}

	function maxLength() {
		return -1;
	}

	function nullable() {
		// SQLite dynamic types are always nullable
		return true;
	}

	# isKey(),  isMultipleKey() not implemented, MySQL-specific concept. 
	# Suggest removal from base class [TS]

	function type() {
		return $this->info->type;
	}

} // end SQLiteField
