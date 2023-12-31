<?php
/**
 * This script is the IBM DB2 database abstraction layer
 *
 * See maintenance/ibm_db2/README for development notes and other specific information
 * @ingroup Database
 * @file
 * @author leo.petr+mediawiki@gmail.com
 */

/**
 * This represents a column in a DB2 database
 * @ingroup Database
 */
class IBM_DB2Field {
	private $name = '';
	private $tablename = '';
	private $type = '';
	private $nullable = false;
	private $max_length = 0;

	/**
	 * Builder method for the class 
	 * @param $db DatabaseIbm_db2: Database interface
	 * @param $table String: table name
	 * @param $field String: column name
	 * @return IBM_DB2Field
	 */
	static function fromText($db, $table, $field) {
		global $wgDBmwschema;

		$q = <<<SQL
SELECT
lcase(coltype) AS typname,
nulls AS attnotnull, length AS attlen
FROM sysibm.syscolumns
WHERE tbcreator=%s AND tbname=%s AND name=%s;
SQL;
		$res = $db->query(sprintf($q,
				$db->addQuotes($wgDBmwschema),
				$db->addQuotes($table),
				$db->addQuotes($field)));
		$row = $db->fetchObject($res);
		if (!$row)
			return null;
		$n = new IBM_DB2Field;
		$n->type = $row->typname;
		$n->nullable = ($row->attnotnull == 'N');
		$n->name = $field;
		$n->tablename = $table;
		$n->max_length = $row->attlen;
		return $n;
	}
	/**
	 * Get column name
	 * @return string column name
	 */
	function name() { return $this->name; }
	/**
	 * Get table name
	 * @return string table name
	 */
	function tableName() { return $this->tablename; }
	/**
	 * Get column type
	 * @return string column type
	 */
	function type() { return $this->type; }
	/**
	 * Can column be null?
	 * @return bool true or false
	 */
	function nullable() { return $this->nullable; }
	/**
	 * How much can you fit in the column per row?
	 * @return int length
	 */
	function maxLength() { return $this->max_length; }
}

/**
 * Wrapper around binary large objects
 * @ingroup Database
 */
class IBM_DB2Blob {
	private $mData;

	public function __construct($data) {
		$this->mData = $data;
	}

	public function getData() {
		return $this->mData;
	}
	
	public function __toString()
    {
        return $this->mData;
    }
}

/**
 * Primary database interface
 * @ingroup Database
 */
class DatabaseIbm_db2 extends DatabaseBase {
	/*
	 * Inherited members
	protected $mLastQuery = '';
	protected $mPHPError = false;

	protected $mServer, $mUser, $mPassword, $mConn = null, $mDBname;
	protected $mOut, $mOpened = false;

	protected $mFailFunction;
	protected $mTablePrefix;
	protected $mFlags;
	protected $mTrxLevel = 0;
	protected $mErrorCount = 0;
	protected $mLBInfo = array();
	protected $mFakeSlaveLag = null, $mFakeMaster = false;
	 *
	 */
	
	/// Server port for uncataloged connections
	protected $mPort = null;
	/// Whether connection is cataloged
	protected $mCataloged = null;
	/// Schema for tables, stored procedures, triggers
	protected $mSchema = null;
	/// Whether the schema has been applied in this session
	protected $mSchemaSet = false;
	/// Result of last query
	protected $mLastResult = null;
	/// Number of rows affected by last INSERT/UPDATE/DELETE
	protected $mAffectedRows = null;
	/// Number of rows returned by last SELECT
	protected $mNumRows = null;
	
	/// Connection config options - see constructor
	public $mConnOptions = array();
	/// Statement config options -- see constructor
	public $mStmtOptions = array();
	
	
	const CATALOGED = "cataloged";
	const UNCATALOGED = "uncataloged";
	const USE_GLOBAL = "get from global";
	
	const NONE_OPTION = 0x00;
	const CONN_OPTION = 0x01;
	const STMT_OPTION = 0x02;
	
	const REGULAR_MODE = 'regular';
	const INSTALL_MODE = 'install';
	
	// Whether this is regular operation or the initial installation
	protected $mMode = self::REGULAR_MODE;
	
	/// Last sequence value used for a primary key
	protected $mInsertId = null;
	
	/*
	 * These can be safely inherited
	 * 
	 * Getter/Setter: (18)
	 * failFunction
	 * setOutputPage
	 * bufferResults
	 * ignoreErrors
	 * trxLevel
	 * errorCount
	 * getLBInfo
	 * setLBInfo
	 * lastQuery
	 * isOpen
	 * setFlag
	 * clearFlag
	 * getFlag
	 * getProperty
	 * getDBname
	 * getServer
	 * tableNameCallback
	 * tablePrefix
	 * 
	 * Administrative: (8)
	 * debug
	 * installErrorHandler
	 * restoreErrorHandler
	 * connectionErrorHandler
	 * reportConnectionError
	 * sourceFile
	 * sourceStream
	 * replaceVars
	 * 
	 * Database: (5)
	 * query
	 * set
	 * selectField
	 * generalizeSQL
	 * update
	 * strreplace
	 * deadlockLoop
	 * 
	 * Prepared Statement: 6
	 * prepare
	 * freePrepared
	 * execute
	 * safeQuery
	 * fillPrepared
	 * fillPreparedArg
	 * 
	 * Slave/Master: (4) 
	 * masterPosWait
	 * getSlavePos
	 * getMasterPos
	 * getLag
	 * 
	 * Generation: (9)
	 * tableNames
	 * tableNamesN
	 * tableNamesWithUseIndexOrJOIN
	 * escapeLike
	 * delete
	 * insertSelect
	 * timestampOrNull
	 * resultObject
	 * aggregateValue
	 * selectSQLText
	 * selectRow
	 * makeUpdateOptions
	 * 
	 * Reflection: (1)
	 * indexExists
	 */
	
	/*
	 * These have been implemented
	 * 
	 * Administrative: 7 / 7
	 * constructor [Done]
	 * open [Done]
	 * openCataloged [Done]
	 * close [Done]
	 * newFromParams [Done]
	 * openUncataloged [Done]
	 * setup_database [Done]
	 * 
	 * Getter/Setter: 13 / 13
	 * cascadingDeletes [Done]
	 * cleanupTriggers  [Done]
	 * strictIPs  [Done]
	 * realTimestamps  [Done]
	 * impliciGroupby  [Done]
	 * implicitOrderby  [Done]
	 * searchableIPs  [Done]
	 * functionalIndexes  [Done]
	 * getWikiID  [Done]
	 * isOpen [Done]
	 * getServerVersion [Done]
	 * getSoftwareLink [Done]
	 * getSearchEngine [Done]
	 * 
	 * Database driver wrapper: 23 / 23
	 * lastError [Done]
	 * lastErrno [Done]
	 * doQuery [Done]
	 * tableExists [Done]
	 * fetchObject [Done]
	 * fetchRow [Done]
	 * freeResult [Done]
	 * numRows [Done]
	 * numFields [Done]
	 * fieldName [Done]
	 * insertId [Done]
	 * dataSeek [Done]
	 * affectedRows [Done]
	 * selectDB [Done]
	 * strencode [Done]
	 * conditional [Done]
	 * wasDeadlock [Done]
	 * ping [Done]
	 * getStatus [Done]
	 * setTimeout [Done]
	 * lock [Done]
	 * unlock [Done]
	 * insert [Done]
	 * select [Done]
	 * 
	 * Slave/master: 2 / 2
	 * setFakeSlaveLag [Done]
	 * setFakeMaster [Done]
	 * 
	 * Reflection: 6 / 6
	 * fieldExists [Done]
	 * indexInfo [Done]
	 * fieldInfo [Done]
	 * fieldType [Done]
	 * indexUnique [Done]
	 * textFieldSize [Done]
	 * 
	 * Generation: 16 / 16
	 * tableName [Done]
	 * addQuotes [Done]
	 * makeList [Done]
	 * makeSelectOptions [Done]
	 * estimateRowCount [Done]
	 * nextSequenceValue [Done]
	 * useIndexClause [Done]
	 * replace [Done]
	 * deleteJoin [Done]
	 * lowPriorityOption [Done]
	 * limitResult [Done]
	 * limitResultForUpdate [Done]
	 * timestamp [Done]
	 * encodeBlob [Done]
	 * decodeBlob [Done]
	 * buildConcat [Done]
	 */
	
	######################################
	# Getters and Setters
	######################################
	
	/**
	 * Returns true if this database supports (and uses) cascading deletes
	 */
	function cascadingDeletes() {
		return true;
	}

	/**
	 * Returns true if this database supports (and uses) triggers (e.g. on the page table)
	 */
	function cleanupTriggers() {
		return true;
	}

	/**
	 * Returns true if this database is strict about what can be put into an IP field.
	 * Specifically, it uses a NULL value instead of an empty string.
	 */
	function strictIPs() {
		return true;
	}
	
	/**
	 * Returns true if this database uses timestamps rather than integers
	*/
	function realTimestamps() {
		return true;
	}

	/**
	 * Returns true if this database does an implicit sort when doing GROUP BY
	 */
	function implicitGroupby() {
		return false;
	}

	/**
	 * Returns true if this database does an implicit order by when the column has an index
	 * For example: SELECT page_title FROM page LIMIT 1
	 */
	function implicitOrderby() {
		return false;
	}

	/**
	 * Returns true if this database can do a native search on IP columns
	 * e.g. this works as expected: .. WHERE rc_ip = '127.42.12.102/32';
	 */
	function searchableIPs() {
		return true;
	}

	/**
	 * Returns true if this database can use functional indexes
	 */
	function functionalIndexes() {
		return true;
	}
	
	/**
	 * Returns a unique string representing the wiki on the server
	 */
	function getWikiID() {
		if( $this->mSchema ) {
			return "{$this->mDBname}-{$this->mSchema}";
		} else {
			return $this->mDBname;
		}
	}

	function getType() {
		return 'ibm_db2';
	}
	
	######################################
	# Setup
	######################################
	
	
	/**
	 * 
	 * @param $server String: hostname of database server
	 * @param $user String: username
	 * @param $password String: password
	 * @param $dbName String: database name on the server
	 * @param $failFunction Callback (optional)
	 * @param $flags Integer: database behaviour flags (optional, unused)
	 * @param $schema String
	 */
	public function DatabaseIbm_db2($server = false, $user = false, $password = false,
							$dbName = false, $failFunction = false, $flags = 0,
							$schema = self::USE_GLOBAL )
	{

		global $wgOut, $wgDBmwschema;
		# Can't get a reference if it hasn't been set yet
		if ( !isset( $wgOut ) ) {
			$wgOut = null;
		}
		$this->mOut =& $wgOut;
		$this->mFailFunction = $failFunction;
		$this->mFlags = DBO_TRX | $flags;
		
		if ( $schema == self::USE_GLOBAL ) {
			$this->mSchema = $wgDBmwschema;
		}
		else {
			$this->mSchema = $schema;
		}
		
		// configure the connection and statement objects
		$this->setDB2Option('db2_attr_case', 'DB2_CASE_LOWER', self::CONN_OPTION | self::STMT_OPTION);
		$this->setDB2Option('deferred_prepare', 'DB2_DEFERRED_PREPARE_ON', self::STMT_OPTION);
		$this->setDB2Option('rowcount', 'DB2_ROWCOUNT_PREFETCH_ON', self::STMT_OPTION);
		
		$this->open( $server, $user, $password, $dbName);
	}
	
	/**
	 * Enables options only if the ibm_db2 extension version supports them
	 * @param $name String: name of the option in the options array
	 * @param $const String: name of the constant holding the right option value
	 * @param $type Integer: whether this is a Connection or Statement otion
	 */
	private function setDB2Option($name, $const, $type) {
		if (defined($const)) {
			if ($type & self::CONN_OPTION) $this->mConnOptions[$name] = constant($const);
			if ($type & self::STMT_OPTION) $this->mStmtOptions[$name] = constant($const);
		}
		else {
			$this->installPrint("$const is not defined. ibm_db2 version is likely too low.");
		}
	}
	
	/**
	 * Outputs debug information in the appropriate place
	 * @param $string String: the relevant debug message
	 */
	private function installPrint($string) {
		wfDebug("$string");
		if ($this->mMode == self::INSTALL_MODE) {
			print "<li>$string</li>";
			flush();
		} 
	}
	
	/**
	 * Opens a database connection and returns it
	 * Closes any existing connection
	 * @return a fresh connection
	 * @param $server String: hostname
	 * @param $user String
	 * @param $password String
	 * @param $dbName String: database name
	 */
	public function open( $server, $user, $password, $dbName )
	{
		// Load the port number
		global $wgDBport_db2, $wgDBcataloged;
		wfProfileIn( __METHOD__ );
		
		// Load IBM DB2 driver if missing
		if (!@extension_loaded('ibm_db2')) {
			@dl('ibm_db2.so');
		}
		// Test for IBM DB2 support, to avoid suppressed fatal error
		if ( !function_exists( 'db2_connect' ) ) {
			$error = "DB2 functions missing, have you enabled the ibm_db2 extension for PHP?\n";
			$this->installPrint($error);
			$this->reportConnectionError($error);
		}

		if (!strlen($user)) { // Copied from Postgres
			return null;
		}
		
		// Close existing connection
		$this->close();
		// Cache conn info
		$this->mServer = $server;
		$this->mPort = $port = $wgDBport_db2;
		$this->mUser = $user;
		$this->mPassword = $password;
		$this->mDBname = $dbName;
		$this->mCataloged = $cataloged = $wgDBcataloged;
		
		if ( $cataloged == self::CATALOGED ) {
			$this->openCataloged($dbName, $user, $password);
		}
		elseif ( $cataloged == self::UNCATALOGED ) {
			$this->openUncataloged($dbName, $user, $password, $server, $port);
		}
		// Apply connection config
		db2_set_option($this->mConn, $this->mConnOptions, 1);
		// Not all MediaWiki code is transactional
		// Rather, turn autocommit off in the begin function and turn on after a commit
		db2_autocommit($this->mConn, DB2_AUTOCOMMIT_ON);

		if ( $this->mConn == false ) {
			$this->installPrint( "DB connection error\n" );
			$this->installPrint( "Server: $server, Database: $dbName, User: $user, Password: " . substr( $password, 0, 3 ) . "...\n" );
			$this->installPrint( $this->lastError()."\n" );
			return null;
		}

		$this->mOpened = true;
		$this->applySchema();
		
		wfProfileOut( __METHOD__ );
		return $this->mConn;
	}
	
	/**
	 * Opens a cataloged database connection, sets mConn
	 */
	protected function openCataloged( $dbName, $user, $password )
	{
		@$this->mConn = db2_connect($dbName, $user, $password);
	}
	
	/**
	 * Opens an uncataloged database connection, sets mConn
	 */
	protected function openUncataloged( $dbName, $user, $password, $server, $port )
	{
		$str = "DRIVER={IBM DB2 ODBC DRIVER};";
		$str .= "DATABASE=$dbName;";
		$str .= "HOSTNAME=$server;";
		if ($port) $str .= "PORT=$port;";
		$str .= "PROTOCOL=TCPIP;";
		$str .= "UID=$user;";
		$str .= "PWD=$password;";
		
		@$this->mConn = db2_connect($str, $user, $password);
	}
	
	/**
	 * Closes a database connection, if it is open
	 * Returns success, true if already closed
	 */
	public function close() {
		$this->mOpened = false;
		if ( $this->mConn ) {
			if ($this->trxLevel() > 0) {
				$this->commit();
			}
			return db2_close( $this->mConn );
		}
		else {
			return true;
		}
	}
	
	/**
	 * Returns a fresh instance of this class
	 *
	 * @param $server String: hostname of database server
	 * @param $user String: username
	 * @param $password String
	 * @param $dbName String: database name on the server
	 * @param $failFunction Callback (optional)
	 * @param $flags Integer: database behaviour flags (optional, unused)
	 * @return DatabaseIbm_db2 object
	 */
	static function newFromParams( $server, $user, $password, $dbName, $failFunction = false, $flags = 0)
	{
		return new DatabaseIbm_db2( $server, $user, $password, $dbName, $failFunction, $flags );
	}
	
	/**
	 * Retrieves the most current database error
	 * Forces a database rollback
	 */
	public function lastError() {
		$connerr = db2_conn_errormsg();
		if ($connerr) {
			//$this->rollback();
			return $connerr;
		}
		$stmterr = db2_stmt_errormsg();
		if ($stmterr) {
			//$this->rollback();
			return $stmterr;
		}
		
		return false;
	}
	
	/**
	 * Get the last error number
	 * Return 0 if no error
	 * @return integer
	 */
	public function lastErrno() {
		$connerr = db2_conn_error();
		if ($connerr) return $connerr;
		$stmterr = db2_stmt_error();
		if ($stmterr) return $stmterr;
		return 0;
	}
	
	/**
	 * Is a database connection open?
	 * @return 
	 */
	public function isOpen() { return $this->mOpened; }
	
	/**
	 * The DBMS-dependent part of query()
	 * @param  $sql String: SQL query.
	 * @return object Result object to feed to fetchObject, fetchRow, ...; or false on failure
	 * @access private
	 */
	/*private*/
	public function doQuery( $sql ) {
		//print "<li><pre>$sql</pre></li>";
		// Switch into the correct namespace
		$this->applySchema();
		
		$ret = db2_exec( $this->mConn, $sql, $this->mStmtOptions );
		if( !$ret ) {
			print "<br><pre>";
			print $sql;
			print "</pre><br>";
			$error = db2_stmt_errormsg();
			throw new DBUnexpectedError($this,  'SQL error: ' . htmlspecialchars( $error ) );
		}
		$this->mLastResult = $ret;
		$this->mAffectedRows = null;	// Not calculated until asked for
		return $ret;
	}
	
	/**
	 * @return string Version information from the database
	 */
	public function getServerVersion() {
		$info = db2_server_info( $this->mConn );
		return $info->DBMS_VER;
	}
	
	/**
	 * Queries whether a given table exists
	 * @return boolean
	 */
	public function tableExists( $table ) {
		$schema = $this->mSchema;
		$sql = <<< EOF
SELECT COUNT(*) FROM SYSIBM.SYSTABLES ST
WHERE ST.NAME = '$table' AND ST.CREATOR = '$schema'
EOF;
		$res = $this->query( $sql );
		if (!$res) return false;
		
		// If the table exists, there should be one of it
		@$row = $this->fetchRow($res);
		$count = $row[0];
		if ($count == '1' or $count == 1) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Fetch the next row from the given result object, in object form.
	 * Fields can be retrieved with $row->fieldname, with fields acting like
	 * member variables.
	 *
	 * @param $res SQL result object as returned from Database::query(), etc.
	 * @return DB2 row object
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	public function fetchObject( $res ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		@$row = db2_fetch_object( $res );
		if( $this->lastErrno() ) {
			throw new DBUnexpectedError( $this, 'Error in fetchObject(): ' . htmlspecialchars( $this->lastError() ) );
		}
		return $row;
	}

	/**
	 * Fetch the next row from the given result object, in associative array
	 * form.  Fields are retrieved with $row['fieldname'].
	 *
	 * @param $res SQL result object as returned from Database::query(), etc.
	 * @return DB2 row object
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	public function fetchRow( $res ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		@$row = db2_fetch_array( $res );
		if ( $this->lastErrno() ) {
			throw new DBUnexpectedError( $this, 'Error in fetchRow(): ' . htmlspecialchars( $this->lastError() ) );
		}
		return $row;
	}
	
	/**
	 * Override if introduced to base Database class
	 */
	public function initial_setup() {
		// do nothing
	}
	
	/**
	 * Create tables, stored procedures, and so on
	 */
	public function setup_database() {
		// Timeout was being changed earlier due to mysterious crashes
		// Changing it now may cause more problems than not changing it
		//set_time_limit(240);
		try {
			// TODO: switch to root login if available
			
			// Switch into the correct namespace
			$this->applySchema();
			$this->begin();
			
			$res = $this->sourceFile( "../maintenance/ibm_db2/tables.sql" );
			if ($res !== true) {
				print " <b>FAILED</b>: " . htmlspecialchars( $res ) . "</li>";
			} else {
				print " done</li>";
			}
			$res = null;
	
			// TODO: update mediawiki_version table
			
			// TODO: populate interwiki links
			
			if ($this->lastError()) {
				print "<li>Errors encountered during table creation -- rolled back</li>\n";
				print "<li>Please install again</li>\n";
				$this->rollback();
			}
			else {			
				$this->commit();
			}
		}
		catch (MWException $mwe)
		{
			print "<br><pre>$mwe</pre><br>";
		}
	}

	/**
	 * Escapes strings
	 * Doesn't escape numbers
	 * @param $s String: string to escape
	 * @return escaped string
	 */
	public function addQuotes( $s ) {
		//$this->installPrint("DB2::addQuotes($s)\n");
		if ( is_null( $s ) ) {
			return "NULL";
		} else if ($s instanceof Blob) {
			return "'".$s->fetch($s)."'";
		} else if ($s instanceof IBM_DB2Blob) {
			return "'".$this->decodeBlob($s)."'";
		}
		$s = $this->strencode($s);
		if ( is_numeric($s) ) {
			return $s;
		}
		else {
			return "'$s'";
		}
	}
	
	/**
	 * Verifies that a DB2 column/field type is numeric
	 * @return bool true if numeric
	 * @param $type String: DB2 column type
	 */
	public function is_numeric_type( $type ) {
		switch (strtoupper($type)) {
		case 'SMALLINT':
		case 'INTEGER':
		case 'INT':
		case 'BIGINT':
		case 'DECIMAL':
		case 'REAL':
		case 'DOUBLE':
		case 'DECFLOAT':
			return true;
		}
		return false;
	}
	
	/**
	 * Alias for addQuotes()
	 * @param $s String: string to escape
	 * @return escaped string
	 */
	public function strencode( $s ) {
		// Bloody useless function
		//  Prepends backslashes to \x00, \n, \r, \, ', " and \x1a. 
		//  But also necessary
		$s = db2_escape_string($s);
		// Wide characters are evil -- some of them look like '
		$s = utf8_encode($s);
		// Fix its stupidity
		$from =	array("\\\\",	"\\'",	'\\n',	'\\t',	'\\"',	'\\r');
		$to =	array("\\",		"''",	"\n",	"\t",	'"',	"\r");
		$s = str_replace($from, $to, $s); // DB2 expects '', not \' escaping
		return $s;
	}
	
	/**
	 * Switch into the database schema
	 */
	protected function applySchema() {
		if ( !($this->mSchemaSet) ) {
			$this->mSchemaSet = true;
			$this->begin();
			$this->doQuery("SET SCHEMA = $this->mSchema");
			$this->commit();
		}	
	}
	
	/**
	 * Start a transaction (mandatory)
	 */
	public function begin( $fname = 'DatabaseIbm_db2::begin' ) {
		// turn off auto-commit
		db2_autocommit($this->mConn, DB2_AUTOCOMMIT_OFF);
		$this->mTrxLevel = 1;
	}
	
	/**
	 * End a transaction
	 * Must have a preceding begin()
	 */
	public function commit( $fname = 'DatabaseIbm_db2::commit' ) {
		db2_commit($this->mConn);
		// turn auto-commit back on
		db2_autocommit($this->mConn, DB2_AUTOCOMMIT_ON);
		$this->mTrxLevel = 0;
	}
	
	/**
	 * Cancel a transaction
	 */
	public function rollback( $fname = 'DatabaseIbm_db2::rollback' ) {
		db2_rollback($this->mConn);
		// turn auto-commit back on
		// not sure if this is appropriate
		db2_autocommit($this->mConn, DB2_AUTOCOMMIT_ON);
		$this->mTrxLevel = 0;
	}
	
	/**
	 * Makes an encoded list of strings from an array
	 * $mode:
	 *        LIST_COMMA         - comma separated, no field names
	 *        LIST_AND           - ANDed WHERE clause (without the WHERE)
	 *        LIST_OR            - ORed WHERE clause (without the WHERE)
	 *        LIST_SET           - comma separated with field names, like a SET clause
	 *        LIST_NAMES         - comma separated field names
	 */
	public function makeList( $a, $mode = LIST_COMMA ) {
		if ( !is_array( $a ) ) {
			throw new DBUnexpectedError( $this, 'Database::makeList called with incorrect parameters' );
		}

		$first = true;
		$list = '';
		foreach ( $a as $field => $value ) {
			if ( !$first ) {
				if ( $mode == LIST_AND ) {
					$list .= ' AND ';
				} elseif($mode == LIST_OR) {
					$list .= ' OR ';
				} else {
					$list .= ',';
				}
			} else {
				$first = false;
			}
			if ( ($mode == LIST_AND || $mode == LIST_OR) && is_numeric( $field ) ) {
				$list .= "($value)";
			} elseif ( ($mode == LIST_SET) && is_numeric( $field ) ) {
				$list .= "$value";
			} elseif ( ($mode == LIST_AND || $mode == LIST_OR) && is_array($value) ) {
				if( count( $value ) == 0 ) {
					throw new MWException( __METHOD__.': empty input' );
				} elseif( count( $value ) == 1 ) {
					// Special-case single values, as IN isn't terribly efficient
					// Don't necessarily assume the single key is 0; we don't
					// enforce linear numeric ordering on other arrays here.
					$value = array_values( $value );
					$list .= $field." = ".$this->addQuotes( $value[0] );
				} else {
					$list .= $field." IN (".$this->makeList($value).") ";
				}
			} elseif( is_null($value) ) {
				if ( $mode == LIST_AND || $mode == LIST_OR ) {
					$list .= "$field IS ";
				} elseif ( $mode == LIST_SET ) {
					$list .= "$field = ";
				}
				$list .= 'NULL';
			} else {
				if ( $mode == LIST_AND || $mode == LIST_OR || $mode == LIST_SET ) {
					$list .= "$field = ";
				}
				if ( $mode == LIST_NAMES ) {
					$list .= $value;
				}
				// Leo: Can't insert quoted numbers into numeric columns
				// (?) Might cause other problems. May have to check column type before insertion.
				else if ( is_numeric($value) ) {
					$list .= $value;
				}
				else {
					$list .= $this->addQuotes( $value );
				}
			}
		}
		return $list;
	}
	
	/**
	 * Construct a LIMIT query with optional offset
	 * This is used for query pages
	 * @param $sql string SQL query we will append the limit too
	 * @param $limit integer the SQL limit
	 * @param $offset integer the SQL offset (default false)
	 */
	public function limitResult($sql, $limit, $offset=false) {
		if( !is_numeric($limit) ) {
			throw new DBUnexpectedError( $this, "Invalid non-numeric limit passed to limitResult()\n" );
		}
		if( $offset ) {
			$this->installPrint("Offset parameter not supported in limitResult()\n");
		}
		// TODO implement proper offset handling
		// idea: get all the rows between 0 and offset, advance cursor to offset
		return "$sql FETCH FIRST $limit ROWS ONLY ";
	}
	
	/**
	 * Handle reserved keyword replacement in table names
	 * @return 
	 * @param $name Object
	 */
	public function tableName( $name ) {
		# Replace reserved words with better ones
//		switch( $name ) {
//			case 'user':
//				return 'mwuser';
//			case 'text':
//				return 'pagecontent';
//			default:
//				return $name;
//		}
		// we want maximum compatibility with MySQL schema
		return $name;
	}
	
	/**
	 * Generates a timestamp in an insertable format
	 * @return string timestamp value
	 * @param $ts timestamp
	 */
	public function timestamp( $ts=0 ) {
		// TS_MW cannot be easily distinguished from an integer
		return wfTimestamp(TS_DB2,$ts);
	}

	/**
	 * Return the next in a sequence, save the value for retrieval via insertId()
	 * @param $seqName String: name of a defined sequence in the database
	 * @return next value in that sequence
	 */
	public function nextSequenceValue( $seqName ) {
		// Not using sequences in the primary schema to allow for easy third-party migration scripts
		// Emulating MySQL behaviour of using NULL to signal that sequences aren't used
		/*
		$safeseq = preg_replace( "/'/", "''", $seqName );
		$res = $this->query( "VALUES NEXTVAL FOR $safeseq" );
		$row = $this->fetchRow( $res );
		$this->mInsertId = $row[0];
		$this->freeResult( $res );
		return $this->mInsertId;
		*/
		return null;
	}
	
	/**
	 * This must be called after nextSequenceVal
	 * @return Last sequence value used as a primary key
	 */
	public function insertId() {
		return $this->mInsertId;
	}
	
	/**
	 * Updates the mInsertId property with the value of the last insert into a generated column
	 * @param $table      String: sanitized table name
	 * @param $primaryKey Mixed: string name of the primary key or a bool if this call is a do-nothing
	 * @param $stmt       Resource: prepared statement resource
	 *  of the SELECT primary_key FROM FINAL TABLE ( INSERT ... ) form
	 */
	private function calcInsertId($table, $primaryKey, $stmt) {
		if ($primaryKey) {
			$id_row = $this->fetchRow($stmt);
			$this->mInsertId = $id_row[0];
		}
	}
	
	/**
	 * INSERT wrapper, inserts an array into a table
	 *
	 * $args may be a single associative array, or an array of these with numeric keys,
	 * for multi-row insert
	 *
	 * @param $table   String: Name of the table to insert to.
	 * @param $args    Array: Items to insert into the table.
	 * @param $fname   String: Name of the function, for profiling
	 * @param $options String or Array. Valid options: IGNORE
	 *
	 * @return bool Success of insert operation. IGNORE always returns true.
	 */
	public function insert( $table, $args, $fname = 'DatabaseIbm_db2::insert', $options = array() ) {
		if ( !count( $args ) ) {
			return true;
		}
		// get database-specific table name (not used)
		$table = $this->tableName( $table );
		// format options as an array
		if ( !is_array( $options ) ) $options = array( $options );
		// format args as an array of arrays
		if ( !( isset( $args[0] ) && is_array( $args[0] ) ) ) {
			$args = array($args);
		}
		// prevent insertion of NULL into primary key columns
		list($args, $primaryKeys) = $this->removeNullPrimaryKeys($table, $args);
		// if there's only one primary key
		// we'll be able to read its value after insertion
		$primaryKey = false;
		if (count($primaryKeys) == 1) {
			$primaryKey = $primaryKeys[0];
		}
		
		// get column names
		$keys = array_keys( $args[0] );
		$key_count = count($keys);

		// If IGNORE is set, we use savepoints to emulate mysql's behavior
		$ignore = in_array( 'IGNORE', $options ) ? 'mw' : '';

		// assume success
		$res = true;
		// If we are not in a transaction, we need to be for savepoint trickery
		$didbegin = 0;
		if (! $this->mTrxLevel) {
			$this->begin();
			$didbegin = 1;
		}

		$sql = "INSERT INTO $table (" . implode( ',', $keys ) . ') VALUES ';
		switch($key_count) {
			//case 0 impossible
			case 1:
				$sql .= '(?)';
				break;
			default:
				$sql .= '(?' . str_repeat(',?', $key_count-1) . ')';
		}
		// add logic to read back the new primary key value
		if ($primaryKey) {
			$sql = "SELECT $primaryKey FROM FINAL TABLE($sql)";
		}
		$stmt = $this->prepare($sql);
		
		// start a transaction/enter transaction mode
		$this->begin();

		if ( !$ignore ) {
			$first = true;
			foreach ( $args as $row ) {
				// insert each row into the database
				$res = $res & $this->execute($stmt, $row);
				// get the last inserted value into a generated column
				$this->calcInsertId($table, $primaryKey, $stmt);
			}
		}
		else {
			$olde = error_reporting( 0 );
			// For future use, we may want to track the number of actual inserts
			// Right now, insert (all writes) simply return true/false
			$numrowsinserted = 0;
			
			// always return true
			$res = true;
				
			foreach ( $args as $row ) {
				$overhead = "SAVEPOINT $ignore ON ROLLBACK RETAIN CURSORS";
				db2_exec($this->mConn, $overhead, $this->mStmtOptions);
				
				$res2 = $this->execute($stmt, $row);
				// get the last inserted value into a generated column
				$this->calcInsertId($table, $primaryKey, $stmt);
				
				$errNum = $this->lastErrno();
				if ($errNum) {
					db2_exec( $this->mConn, "ROLLBACK TO SAVEPOINT $ignore", $this->mStmtOptions );
				}
				else {
					db2_exec( $this->mConn, "RELEASE SAVEPOINT $ignore", $this->mStmtOptions );
					$numrowsinserted++;
				}
			}
			
			$olde = error_reporting( $olde );
			// Set the affected row count for the whole operation
			$this->mAffectedRows = $numrowsinserted;
		}
		// commit either way
		$this->commit();
		
		return $res;
	}
	
	/**
	 * Given a table name and a hash of columns with values
	 * Removes primary key columns from the hash where the value is NULL
	 * 
	 * @param $table String: name of the table
	 * @param $args Array of hashes of column names with values
	 * @return Array: tuple containing filtered array of columns, array of primary keys
	 */
	private function removeNullPrimaryKeys($table, $args) {
		$schema = $this->mSchema;
		// find out the primary keys
		$keyres = db2_primary_keys($this->mConn, null, strtoupper($schema), strtoupper($table));
		$keys = array();
		for ($row = $this->fetchObject($keyres); $row != null; $row = $this->fetchRow($keyres)) {
			$keys[] = strtolower($row->column_name);
		}
		// remove primary keys
		foreach ($args as $ai => $row) {
			foreach ($keys as $ki => $key) {
				if ($row[$key] == null) {
					unset($row[$key]);
				}
			}
			$args[$ai] = $row;
		}
		// return modified hash
		return array($args, $keys);
	}
	
	/**
	 * UPDATE wrapper, takes a condition array and a SET array
	 *
	 * @param $table  String: The table to UPDATE
	 * @param $values An array of values to SET
	 * @param $conds  An array of conditions (WHERE). Use '*' to update all rows.
	 * @param $fname  String: The Class::Function calling this function
	 *                (for the log)
	 * @param $options An array of UPDATE options, can be one or
	 *                 more of IGNORE, LOW_PRIORITY
	 * @return Boolean
	 */
	public function update( $table, $values, $conds, $fname = 'Database::update', $options = array() ) {
		$table = $this->tableName( $table );
		$opts = $this->makeUpdateOptions( $options );
		$sql = "UPDATE $opts $table SET " . $this->makeList( $values, LIST_SET );
		if ( $conds != '*' ) {
			$sql .= " WHERE " . $this->makeList( $conds, LIST_AND );
		}
		return $this->query( $sql, $fname );
	}
	
	/**
	 * DELETE query wrapper
	 *
	 * Use $conds == "*" to delete all rows
	 */
	public function delete( $table, $conds, $fname = 'Database::delete' ) {
		if ( !$conds ) {
			throw new DBUnexpectedError( $this, 'Database::delete() called with no conditions' );
		}
		$table = $this->tableName( $table );
		$sql = "DELETE FROM $table";
		if ( $conds != '*' ) {
			$sql .= ' WHERE ' . $this->makeList( $conds, LIST_AND );
		}
		return $this->query( $sql, $fname );
	}
	
	/**
	 * Returns the number of rows affected by the last query or 0
	 * @return Integer: the number of rows affected by the last query
	 */
	public function affectedRows() {
		if ( !is_null( $this->mAffectedRows ) ) {
			// Forced result for simulated queries
			return $this->mAffectedRows;
		}
		if( empty( $this->mLastResult ) )
			return 0;
		return db2_num_rows( $this->mLastResult );
	}
	
	/**
	 * Simulates REPLACE with a DELETE followed by INSERT
	 * @param $table Object
	 * @param $uniqueIndexes Array consisting of indexes and arrays of indexes
	 * @param $rows Array: rows to insert
	 * @param $fname String: name of the function for profiling
	 * @return nothing
	 */
	function replace( $table, $uniqueIndexes, $rows, $fname = 'DatabaseIbm_db2::replace' ) {
		$table = $this->tableName( $table );

		if (count($rows)==0) {
			return;
		}

		# Single row case
		if ( !is_array( reset( $rows ) ) ) {
			$rows = array( $rows );
		}

		foreach( $rows as $row ) {
			# Delete rows which collide
			if ( $uniqueIndexes ) {
				$sql = "DELETE FROM $table WHERE ";
				$first = true;
				foreach ( $uniqueIndexes as $index ) {
					if ( $first ) {
						$first = false;
						$sql .= "(";
					} else {
						$sql .= ') OR (';
					}
					if ( is_array( $index ) ) {
						$first2 = true;
						foreach ( $index as $col ) {
							if ( $first2 ) {
								$first2 = false;
							} else {
								$sql .= ' AND ';
							}
							$sql .= $col.'=' . $this->addQuotes( $row[$col] );
						}
					} else {
						$sql .= $index.'=' . $this->addQuotes( $row[$index] );
					}
				}
				$sql .= ')';
				$this->query( $sql, $fname );
			}

			# Now insert the row
			$sql = "INSERT INTO $table (" . $this->makeList( array_keys( $row ), LIST_NAMES ) .') VALUES (' .
				$this->makeList( $row, LIST_COMMA ) . ')';
			$this->query( $sql, $fname );
		}
	}
	
	/**
	 * Returns the number of rows in the result set
	 * Has to be called right after the corresponding select query
	 * @param $res Object result set
	 * @return Integer: number of rows
	 */
	public function numRows( $res ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		if ( $this->mNumRows ) {
			return $this->mNumRows;
		}
		else {
			return 0;
		}
	}
	
	/**
	 * Moves the row pointer of the result set
	 * @param $res Object: result set
	 * @param $row Integer: row number
	 * @return success or failure
	 */
	public function dataSeek( $res, $row ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		return db2_fetch_row( $res, $row );
	}
	
	###
	# Fix notices in Block.php 
	###
	
	/**
	 * Frees memory associated with a statement resource
	 * @param $res Object: statement resource to free
	 * @return Boolean success or failure
	 */
	public function freeResult( $res ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		if ( !@db2_free_result( $res ) ) {
			throw new DBUnexpectedError($this,  "Unable to free DB2 result\n" );
		}
	}
	
	/**
	 * Returns the number of columns in a resource
	 * @param $res Object: statement resource
	 * @return Number of fields/columns in resource
	 */
	public function numFields( $res ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		return db2_num_fields( $res );
	}
	
	/**
	 * Returns the nth column name
	 * @param $res Object: statement resource
	 * @param $n Integer: Index of field or column
	 * @return String name of nth column
	 */
	public function fieldName( $res, $n ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		return db2_field_name( $res, $n );
	}
	
	/**
	 * SELECT wrapper
	 *
	 * @param $table   Array or string, table name(s) (prefix auto-added)
	 * @param $vars    Array or string, field name(s) to be retrieved
	 * @param $conds   Array or string, condition(s) for WHERE
	 * @param $fname   String: calling function name (use __METHOD__) for logs/profiling
	 * @param $options Associative array of options (e.g. array('GROUP BY' => 'page_title')),
	 *                 see Database::makeSelectOptions code for list of supported stuff
	 * @param $join_conds Associative array of table join conditions (optional)
	 *                    (e.g. array( 'page' => array('LEFT JOIN','page_latest=rev_id') )
	 * @return Mixed: database result resource (feed to Database::fetchObject or whatever), or false on failure
	 */
	public function select( $table, $vars, $conds='', $fname = 'DatabaseIbm_db2::select', $options = array(), $join_conds = array() )
	{
		$res = parent::select( $table, $vars, $conds, $fname, $options, $join_conds );
		
		// We must adjust for offset
		if ( isset( $options['LIMIT'] ) ) {
			if ( isset ($options['OFFSET'] ) ) {
				$limit = $options['LIMIT'];
				$offset = $options['OFFSET'];
			}
		}
		
		
		// DB2 does not have a proper num_rows() function yet, so we must emulate it
		// DB2 9.5.3/9.5.4 and the corresponding ibm_db2 driver will introduce a working one
		// Yay!
		
		// we want the count
		$vars2 = array('count(*) as num_rows');
		// respecting just the limit option
		$options2 = array();
		if ( isset( $options['LIMIT'] ) ) $options2['LIMIT'] = $options['LIMIT'];
		// but don't try to emulate for GROUP BY
		if ( isset( $options['GROUP BY'] ) ) return $res;
		
		$res2 = parent::select( $table, $vars2, $conds, $fname, $options2, $join_conds );
		$obj = $this->fetchObject($res2);
		$this->mNumRows = $obj->num_rows;
		
		
		return $res;
	}
	
	/**
	 * Handles ordering, grouping, and having options ('GROUP BY' => colname)
	 * Has limited support for per-column options (colnum => 'DISTINCT')
	 * 
	 * @private
	 *
	 * @param $options Associative array of options to be turned into
	 *              an SQL query, valid keys are listed in the function.
	 * @return Array
	 */
	function makeSelectOptions( $options ) {
		$preLimitTail = $postLimitTail = '';
		$startOpts = '';

		$noKeyOptions = array();
		foreach ( $options as $key => $option ) {
			if ( is_numeric( $key ) ) {
				$noKeyOptions[$option] = true;
			}
		}

		if ( isset( $options['GROUP BY'] ) ) $preLimitTail .= " GROUP BY {$options['GROUP BY']}";
		if ( isset( $options['HAVING'] ) ) $preLimitTail .= " HAVING {$options['HAVING']}";
		if ( isset( $options['ORDER BY'] ) ) $preLimitTail .= " ORDER BY {$options['ORDER BY']}";
		
		if ( isset( $noKeyOptions['DISTINCT'] ) || isset( $noKeyOptions['DISTINCTROW'] ) ) $startOpts .= 'DISTINCT';
		
		return array( $startOpts, '', $preLimitTail, $postLimitTail );
	}
	
	/**
	 * Returns link to IBM DB2 free download
	 * @return string wikitext of a link to the server software's web site
	 */
	public function getSoftwareLink() {
		return "[http://www.ibm.com/software/data/db2/express/?s_cmp=ECDDWW01&s_tact=MediaWiki IBM DB2]";
	}
	
	/**
	 * Get search engine class. All subclasses of this
	 * need to implement this if they wish to use searching.
	 * 
	 * @return String
	 */
	public function getSearchEngine() {
		return "SearchIBM_DB2";
	}

	/**
	 * Did the last database access fail because of deadlock?
	 * @return Boolean
	 */
	public function wasDeadlock() {
		// get SQLSTATE
		$err = $this->lastErrno();
		switch($err) {
			case '40001':	// sql0911n, Deadlock or timeout, rollback
			case '57011':	// sql0904n, Resource unavailable, no rollback
			case '57033':	// sql0913n, Deadlock or timeout, no rollback
			$this->installPrint("In a deadlock because of SQLSTATE $err");
			return true;
		}
		return false;
	}
	
	/**
	 * Ping the server and try to reconnect if it there is no connection
	 * The connection may be closed and reopened while this happens
	 * @return Boolean: whether the connection exists
	 */
	public function ping() {
		// db2_ping() doesn't exist
		// Emulate
		$this->close();
		if ($this->mCataloged == null) {
			return false;
		}
		else if ($this->mCataloged) {
			$this->mConn = $this->openCataloged($this->mDBName, $this->mUser, $this->mPassword);
		}
		else if (!$this->mCataloged) {
			$this->mConn = $this->openUncataloged($this->mDBName, $this->mUser, $this->mPassword, $this->mServer, $this->mPort);
		}
		return false;
	}
	######################################
	# Unimplemented and not applicable
	######################################
	/**
	 * Not implemented
	 * @return string ''
	 * @deprecated
	 */
	public function getStatus( $which="%" ) { $this->installPrint('Not implemented for DB2: getStatus()'); return ''; }
	/**
	 * Not implemented
	 * TODO
	 * @return bool true
	 */
	/**
	 * Not implemented
	 * @deprecated
	 */
	public function setFakeSlaveLag( $lag ) { $this->installPrint('Not implemented for DB2: setFakeSlaveLag()'); }
	/**
	 * Not implemented
	 * @deprecated
	 */
	public function setFakeMaster( $enabled = true ) { $this->installPrint('Not implemented for DB2: setFakeMaster()'); }
	/**
	 * Not implemented
	 * @return string $sql
	 * @deprecated
	 */ 
	public function limitResultForUpdate($sql, $num) { $this->installPrint('Not implemented for DB2: limitResultForUpdate()'); return $sql; }
	
	/**
	 * Only useful with fake prepare like in base Database class
	 * @return	string
	 */
	public function fillPreparedArg( $matches ) { $this->installPrint('Not useful for DB2: fillPreparedArg()'); return ''; }
	
	######################################
	# Reflection
	######################################
	
	/**
	 * Query whether a given column exists in the mediawiki schema
	 * @param $table String: name of the table
	 * @param $field String: name of the column
	 * @param $fname String: function name for logging and profiling
	 */
	public function fieldExists( $table, $field, $fname = 'DatabaseIbm_db2::fieldExists' ) {
		$table = $this->tableName( $table );
		$schema = $this->mSchema;
		$etable = preg_replace("/'/", "''", $table);
		$eschema = preg_replace("/'/", "''", $schema);
		$ecol = preg_replace("/'/", "''", $field);
		$sql = <<<SQL
SELECT 1 as fieldexists
FROM sysibm.syscolumns sc
WHERE sc.name='$ecol' AND sc.tbname='$etable' AND sc.tbcreator='$eschema'
SQL;
		$res = $this->query( $sql, $fname );
		$count = $res ? $this->numRows($res) : 0;
		if ($res)
			$this->freeResult( $res );
		return $count;
	}
	
	/**
	 * Returns information about an index
	 * If errors are explicitly ignored, returns NULL on failure
	 * @param $table String: table name
	 * @param $index String: index name
	 * @param $fname String: function name for logging and profiling
	 * @return Object query row in object form
	 */
	public function indexInfo( $table, $index, $fname = 'DatabaseIbm_db2::indexExists' ) {
		$table = $this->tableName( $table );
		$sql = <<<SQL
SELECT name as indexname
FROM sysibm.sysindexes si
WHERE si.name='$index' AND si.tbname='$table' AND sc.tbcreator='$this->mSchema'
SQL;
		$res = $this->query( $sql, $fname );
		if ( !$res ) {
			return null;
		}
		$row = $this->fetchObject( $res );
		if ($row != null) return $row;
		else return false;
	}
	
	/**
	 * Returns an information object on a table column
	 * @param $table String: table name
	 * @param $field String: column name
	 * @return IBM_DB2Field
	 */
	public function fieldInfo( $table, $field ) {
		return IBM_DB2Field::fromText($this, $table, $field);
	}
	
	/**
	 * db2_field_type() wrapper
	 * @param $res Object: result of executed statement
	 * @param $index Mixed: number or name of the column
	 * @return String column type
	 */
	public function fieldType( $res, $index ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}
		return db2_field_type( $res, $index );
	}
	
	/**
	 * Verifies that an index was created as unique
	 * @param $table String: table name
	 * @param $index String: index name
	 * @param $fname function name for profiling
	 * @return Bool
	 */
	public function indexUnique ($table, $index, $fname = 'Database::indexUnique' ) {
		$table = $this->tableName( $table );
		$sql = <<<SQL
SELECT si.name as indexname
FROM sysibm.sysindexes si
WHERE si.name='$index' AND si.tbname='$table' AND sc.tbcreator='$this->mSchema'
AND si.uniquerule IN ('U', 'P')
SQL;
		$res = $this->query( $sql, $fname );
		if ( !$res ) {
			return null;
		}
		if ($this->fetchObject( $res )) {
			return true;
		}
		return false;

	}
	
	/**
	 * Returns the size of a text field, or -1 for "unlimited"
	 * @param $table String: table name
	 * @param $field String: column name
	 * @return Integer: length or -1 for unlimited
	 */
	public function textFieldSize( $table, $field ) {
		$table = $this->tableName( $table );
		$sql = <<<SQL
SELECT length as size
FROM sysibm.syscolumns sc
WHERE sc.name='$field' AND sc.tbname='$table' AND sc.tbcreator='$this->mSchema'
SQL;
		$res = $this->query($sql);
		$row = $this->fetchObject($res);
		$size = $row->size;
		$this->freeResult( $res );
		return $size;
	}
	
	/**
	 * DELETE where the condition is a join
	 * @param $delTable String: deleting from this table
	 * @param $joinTable String: using data from this table
	 * @param $delVar String: variable in deleteable table
	 * @param $joinVar String: variable in data table
	 * @param $conds Array: conditionals for join table
	 * @param $fname String: function name for profiling
	 */
	public function deleteJoin( $delTable, $joinTable, $delVar, $joinVar, $conds, $fname = "DatabaseIbm_db2::deleteJoin" ) {
		if ( !$conds ) {
			throw new DBUnexpectedError($this,  'Database::deleteJoin() called with empty $conds' );
		}

		$delTable = $this->tableName( $delTable );
		$joinTable = $this->tableName( $joinTable );
		$sql = "DELETE FROM $delTable WHERE $delVar IN (SELECT $joinVar FROM $joinTable ";
		if ( $conds != '*' ) {
			$sql .= 'WHERE ' . $this->makeList( $conds, LIST_AND );
		}
		$sql .= ')';

		$this->query( $sql, $fname );
	}

	/**
	 * Description is left as an exercise for the reader
	 * @param $b Mixed: data to be encoded
	 * @return IBM_DB2Blob
	 */
	public function encodeBlob($b) {
		return new IBM_DB2Blob($b);
	}
	
	/**
	 * Description is left as an exercise for the reader
	 * @param $b IBM_DB2Blob: data to be decoded
	 * @return mixed
	 */
	public function decodeBlob($b) {
		return $b->getData();
	}
	
	/**
	 * Convert into a list of string being concatenated
	 * @param $stringList Array: strings that need to be joined together by the SQL engine
	 * @return String: joined by the concatenation operator
	 */
	public function buildConcat( $stringList ) {
		// || is equivalent to CONCAT
		// Sample query: VALUES 'foo' CONCAT 'bar' CONCAT 'baz'
		return implode( ' || ', $stringList );
	}
	
	/**
	 * Generates the SQL required to convert a DB2 timestamp into a Unix epoch
	 * @param $column String: name of timestamp column
	 * @return String: SQL code
	 */
	public function extractUnixEpoch( $column ) {
		// TODO
		// see SpecialAncientpages
	}
	
	######################################
	# Prepared statements
	######################################
	
	/**
	 * Intended to be compatible with the PEAR::DB wrapper functions.
	 * http://pear.php.net/manual/en/package.database.db.intro-execute.php
	 *
	 * ? = scalar value, quoted as necessary
	 * ! = raw SQL bit (a function for instance)
	 * & = filename; reads the file and inserts as a blob
	 *     (we don't use this though...)
	 * @param $sql String: SQL statement with appropriate markers
	 * @param $func String: Name of the function, for profiling
	 * @return resource a prepared DB2 SQL statement
	 */
	public function prepare( $sql, $func = 'DB2::prepare' ) {
		$stmt = db2_prepare($this->mConn, $sql, $this->mStmtOptions);
		return $stmt;
	}

	/**
	 * Frees resources associated with a prepared statement
	 * @return Boolean success or failure
	 */
	public function freePrepared( $prepared ) {
		return db2_free_stmt($prepared);
	}

	/**
	 * Execute a prepared query with the various arguments
	 * @param $prepared String: the prepared sql
	 * @param $args Mixed: either an array here, or put scalars as varargs
	 * @return Resource: results object
	 */
	public function execute( $prepared, $args = null ) {
		if( !is_array( $args ) ) {
			# Pull the var args
			$args = func_get_args();
			array_shift( $args );
		}
		$res = db2_execute($prepared, $args);
		return $res;
	}

	/**
	 * Prepare & execute an SQL statement, quoting and inserting arguments
	 * in the appropriate places.
	 * @param $query String
	 * @param $args ...
	 */
	public function safeQuery( $query, $args = null ) {
		// copied verbatim from Database.php
		$prepared = $this->prepare( $query, 'DB2::safeQuery' );
		if( !is_array( $args ) ) {
			# Pull the var args
			$args = func_get_args();
			array_shift( $args );
		}
		$retval = $this->execute( $prepared, $args );
		$this->freePrepared( $prepared );
		return $retval;
	}

	/**
	 * For faking prepared SQL statements on DBs that don't support
	 * it directly.
	 * @param $preparedQuery String: a 'preparable' SQL statement
	 * @param $args Array of arguments to fill it with
	 * @return String: executable statement
	 */
	public function fillPrepared( $preparedQuery, $args ) {
		reset( $args );
		$this->preparedArgs =& $args;
		
		foreach ($args as $i => $arg) {
			db2_bind_param($preparedQuery, $i+1, $args[$i]);
		}
		
		return $preparedQuery;
	}
	
	/**
	 * Switches module between regular and install modes
	 */
	public function setMode($mode) {
		$old =  $this->mMode;
		$this->mMode = $mode;
		return $old;
	}
	
	/**
	 * Bitwise negation of a column or value in SQL
	 * Same as (~field) in C
	 * @param $field String
	 * @return String
	 */
	function bitNot($field) {
		//expecting bit-fields smaller than 4bytes
		return 'BITNOT('.$bitField.')';
	}

	/**
	 * Bitwise AND of two columns or values in SQL
	 * Same as (fieldLeft & fieldRight) in C
	 * @param $fieldLeft String
	 * @param $fieldRight String
	 * @return String
	 */
	function bitAnd($fieldLeft, $fieldRight) {
		return 'BITAND('.$fieldLeft.', '.$fieldRight.')';
	}

	/**
	 * Bitwise OR of two columns or values in SQL
	 * Same as (fieldLeft | fieldRight) in C
	 * @param $fieldLeft String
	 * @param $fieldRight String
	 * @return String
	 */
	function bitOr($fieldLeft, $fieldRight) {
		return 'BITOR('.$fieldLeft.', '.$fieldRight.')';
	}
}
