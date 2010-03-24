<?php
/**
 * WordPress DB Class
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 *
 * Modifications for MSSQL DRIVER by A.Garcia and A.Gentile
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */

/**
 * @since 0.71
 */
define('EZSQL_VERSION', 'WP1.25');

/**
 * @since 0.71
 */
define('OBJECT', 'OBJECT', true);

/**
 * @since {@internal Version Unknown}}
 */
define('OBJECT_K', 'OBJECT_K', false);

/**
 * @since 0.71
 */
define('ARRAY_A', 'ARRAY_A', false);

/**
 * @since 0.71
 */
define('ARRAY_N', 'ARRAY_N', false);

require_once 'translations/translations.php';

/**
 * WordPress Database Access Abstraction Object
 *
 * It is possible to replace this class with your own
 * by setting the $wpdb global variable in wp-content/db.php
 * file with your class. You can name it wpdb also, since
 * this file will not be included, if the other file is
 * available.
 *
 * @link http://codex.wordpress.org/Function_Reference/wpdb_Class
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 * @final
 */
class wpdb extends SQL_Translations {

	/**
	 * Whether to show SQL/DB errors
	 *
	 * @since 0.71
	 * @access private
	 * @var bool
	 */
	var $show_errors = true;

	/**
	 * Whether to suppress errors during the DB bootstrapping.
	 *
	 * @access private
	 * @since {@internal Version Unknown}}
	 * @var bool
	 */
	var $suppress_errors = false;

	/**
	 * The last error during query.
	 *
	 * @since {@internal Version Unknown}}
	 * @var string
	 */
	var $last_error = '';

	/**
	 * Amount of queries made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var int
	 */
	var $num_queries = 0;

	/**
	 * Saved result of the last query made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var array
	 */
	var $last_query;

	/**
	 * Saved info on the table column
	 *
	 * @since 1.2.0
	 * @access private
	 * @var array
	 */
	var $col_info;

	/**
	 * Saved queries that were executed
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	var $queries;

	/**
	 * WordPress table prefix
	 *
	 * You can set this to have multiple WordPress installations
	 * in a single database. The second reason is for possible
	 * security precautions.
	 *
	 * @since 0.71
	 * @access private
	 * @var string
	 */
	var $prefix = '';

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var bool
	 */
	var $ready = false;
	var $blogid = 0;
	var $siteid = 0;
	var $blogs;
	var $signups;
	var $site;
	var $sitemeta;
	var $sitecategories;
	var $global_tables = array('blogs', 'signups', 'site', 'sitemeta', 'users', 'usermeta', 'sitecategories', 'registration_log', 'blog_versions');

	/**
	 * WordPress Posts table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $posts;

	/**
	 * WordPress Users table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $users;

	/**
	 * WordPress Categories table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $categories;

	/**
	 * WordPress Post to Category table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $post2cat;

	/**
	 * WordPress Comments table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $comments;

	/**
	 * WordPress Links table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $links;

	/**
	 * WordPress Options table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	var $options;

	/**
	 * WordPress Post Metadata table
	 *
	 * @since {@internal Version Unknown}}
	 * @access public
	 * @var string
	 */
	var $postmeta;

	/**
	 * WordPress Comment Metadata table
	 *
	 * @since 2.9
	 * @access public
	 * @var string
	 */
	var $commentmeta;

	/**
	 * WordPress User Metadata table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	var $usermeta;

	/**
	 * WordPress Terms table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	var $terms;

	/**
	 * WordPress Term Taxonomy table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	var $term_taxonomy;

	/**
	 * WordPress Term Relationships table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	var $term_relationships;

	/**
	 * List of WordPress tables
	 *
	 * @since {@internal Version Unknown}}
	 * @access private
	 * @var array
	 */
	var $tables = array('posts', 'categories', 'post2cat', 'comments', 'links', 'link2cat', 'options',
			'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'commentmeta');

	/**
	 * List of deprecated WordPress tables
	 *
	 * @since 2.9.0
	 * @access private
	 * @var array
	 */
	var $old_tables = array('categories', 'post2cat', 'link2cat');

	/**
	 * Format specifiers for DB columns. Columns not listed here default to %s.  Initialized in wp-settings.php.
	 *
	 * Keys are colmn names, values are format types: 'ID' => '%d'
	 *
	 * @since 2.8.0
	 * @see wpdb:prepare()
	 * @see wpdb:insert()
	 * @see wpdb:update()
	 * @access public
	 * @war array
	 */
	var $field_types = array();

	/**
	 * Database table columns charset
	 *
	 * @since 2.2.0
	 * @access public
	 * @var string
	 */
	var $charset;

	/**
	 * Database table columns collate
	 *
	 * @since 2.2.0
	 * @access public
	 * @var string
	 */
	var $collate;

	/**
	 * Whether to use mysql_real_escape_string
	 *
	 * @since 2.8.0
	 * @access public
	 * @var bool
	 */
	var $real_escape = false;

	/**
	 * Saved result of the last translated query made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var array
	 */
	var $previous_query;

	/**
	* Database type
	* @access public
	* @var string
	*/
	var $db_type;

	/**
	 * Database Username
	 *
	 * @since 2.9.0
	 * @access private
	 * @var string
	 */
	var $dbuser;	
	
	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP4 compatibility layer for calling the PHP5 constructor.
	 *
	 * @uses wpdb::__construct() Passes parameters and returns result
	 * @since 0.71
	 *
	 * @param string $dbtype database type
	 * @param string $dbuser database user
	 * @param string $dbpassword database password
	 * @param string $dbname database name
	 * @param string $dbhost database host
	 */
	function wpdb($dbuser, $dbpassword, $dbname, $dbhost) {
		if( defined( "WP_USE_MULTIPLE_DB" ) && CONSTANT( "WP_USE_MULTIPLE_DB" ) == true )
			$this->db_connect();
		return $this->__construct($dbuser, $dbpassword, $dbname, $dbhost);
	}

	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * @since 2.0.8
	 *
	 * @param string $dbtype database type
	 * @param string $dbuser database user
	 * @param string $dbpassword database password
	 * @param string $dbname database name
	 * @param string $dbhost database host
	 */
	function __construct($dbuser, $dbpassword, $dbname, $dbhost) {
		register_shutdown_function(array(&$this, "__destruct"));

		if ( defined('WP_DEBUG') and WP_DEBUG == true ) {
			$this->show_errors();
		}
		$this->charset = 'utf8';
		if( defined( 'DB_COLLATE' ) && constant( 'DB_COLLATE' ) != '' ) {
			$this->collate = constant( 'DB_COLLATE' );
		} else {
			$this->collate = 'utf8_general_ci';
		}
		if ( defined('DB_CHARSET') ) {
			$this->charset = DB_CHARSET;
		}

		if ( defined('DB_COLLATE') ) {
			$this->collate = DB_COLLATE;
		}
		parent::__construct();
		
		$this->db_type = DB_TYPE;

		// Make sure the version is the same for your ntwdblib.dll.
		// The TDS library and the ntwdblib.dll can't be speaking two different protocols.
		putenv("TDSVER=70");

		// Set text limit sizes to max BEFORE connection is made
		ini_set('mssql.textlimit', 2147483647);
		ini_set('mssql.textsize', 2147483647);

		if (get_magic_quotes_gpc()) {
			$dbhost = trim(str_replace("\\\\", "\\", $dbhost));
		}

		$this->dbh = mssql_connect($dbhost, $dbuser, $dbpassword);
		mssql_min_error_severity(0);
		mssql_min_message_severity(17);

		if (!$this->dbh) {
			$this->bail(sprintf(/*WP_I18N_DB_CONN_ERROR*/"
	<h1>Error establishing a database connection</h1>
	<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
	<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
	</ul>
	<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
	"/*/WP_I18N_DB_CONN_ERROR*/, $dbhost));
			return;
		}

		$this->ready = true;

		// Make sure textsize fields are set to max.
		@mssql_query('SET TEXTSIZE 2147483647');
		/* SET NAMES doesn't translate
		if ( $this->supports_collation() ) {
			$collation_query = '';
			if ( !empty($this->charset) ) {
				$collation_query = "SET NAMES '{$this->charset}'";
				if (!empty($this->collate) )
					$collation_query .= " COLLATE '{$this->collate}'";
			}

			if ( !empty($collation_query) )
				$this->query($collation_query);

		}
		*/
		$this->select($dbname);
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @since 2.0.8
	 *
	 * @return bool Always true
	 */
	function __destruct() {
		return true;
	}

	/**
	 * Sets the table prefix for the WordPress tables.
	 *
	 * Also allows for the CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE to
	 * override the WordPress users and usersmeta tables that would otherwise be determined by the $prefix.
	 *
	 * @since 2.5.0
	 *
	 * @param string $prefix Alphanumeric name for the new prefix.
	 * @return string|WP_Error Old prefix or WP_Error on error
	 */
	function set_prefix($prefix) {

		if ( preg_match('|[^a-z0-9_]|i', $prefix) )
			return new WP_Error('invalid_db_prefix', /*WP_I18N_DB_BAD_PREFIX*/'Invalid database prefix'/*/WP_I18N_DB_BAD_PREFIX*/);

		$old_prefix = '';
		if( isset( $this->base_prefix ) )
			$old_prefix = $this->base_prefix;
		$this->base_prefix = $prefix;
		foreach ( $this->global_tables as $table )
			$this->$table = $prefix . $table;

		if ( empty($this->blogid) )
			return $old_prefix;

		$this->prefix = $this->get_blog_prefix( $this->blogid );

		foreach ( (array) $this->tables as $table )
			$this->$table = $this->prefix . $table;

		if ( defined('CUSTOM_USER_TABLE') )
			$this->users = CUSTOM_USER_TABLE;

		if ( defined('CUSTOM_USER_META_TABLE') )
			$this->usermeta = CUSTOM_USER_META_TABLE;

		return $old_prefix;
	}
	
	function set_blog_id($blog_id, $site_id = '') {
		if ( !empty($site_id) )
			$this->siteid = $site_id;

		$old_blog_id = $this->blogid;
		$this->blogid = $blog_id;

		$this->prefix = $this->get_blog_prefix( $this->blogid );

		foreach ( $this->tables as $table )
			$this->$table = $this->prefix . $table;

		return $old_blog_id;
	}

	function get_blog_prefix( $blog_id = '' ) {
		if ( $blog_id ) {
			return $this->base_prefix . $blog_id . '_';
		} else {
			return $this->prefix;
		}
	}
	
	/**
	 * Selects a database using the current database connection.
	 *
	 * The database name will be changed based on the current database
	 * connection. On failure, the execution will bail and display an DB error.
	 *
	 * @since 0.71
	 *
	 * @param string $db database name
	 * @return null Always null.
	 */
	function select($db) {
		$result = false;
		$result = mssql_select_db($db, $this->dbh);

		if (!$result) {
			$this->ready = false;
			$this->bail(sprintf(/*WP_I18N_DB_SELECT_DB*/'
	<h1>Can&#8217;t select database</h1>
	<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
	<ul>
	<li>Are you sure it exists?</li>
	<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
	<li>On some systems the name of your database is prefixed with your username, so it would be like username_wordpress. Could that be the problem?</li>
	</ul>
	<p>If you don\'t know how to setup a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="http://wordpress.org/support/">WordPress Support Forums</a>.</p>'/*/WP_I18N_DB_SELECT_DB*/, $db, DB_USER));
			return;
		}
	}

	/**
	 * Escapes content for insertion into the database, for security
	 *
	 * @since 0.71
	 *
	 * @param string $string
	 * @return string query safe string
	 */
	function escape($string) {
		return str_replace("'", "''", $string);
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 *
	 * @since 2.3.0
	 *
	 * @param string $s
	 */
	function escape_by_ref(&$s) {
		$s = $this->escape($s);
	}

	/**
	 * Prepares a SQL query for safe use, using sprintf() syntax.
	 *
	 * @link http://php.net/sprintf See for syntax to use for query string.
	 * @since 2.3.0
	 *
	 * @param null|string $args If string, first parameter must be query statement
	 * @param mixed $args,... If additional parameters, they will be set inserted into the query.
	 * @return null|string Sanitized query string
	 */
	function prepare($query = null) {
		if ( is_null( $query ) ) {
			return;
		}
		$this->prepare_args = func_get_args();
		array_shift($this->prepare_args);
		// If args were passed as an array (as in vsprintf), move them up
		if ( isset($this->prepare_args[0]) && is_array($this->prepare_args[0]) ) {
			$this->prepare_args = $this->prepare_args[0];
		}
		$flag = '--PREPARE';
		foreach($this->prepare_args as $key => $arg){
			if ($key !== 0 && is_serialized($arg)) {
				$flag = '--SERIALIZED';
			}
		}
		$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
		$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
		$query = str_replace('%s', "'%s'", $query); // quote the strings
		array_walk($this->prepare_args, array(&$this, 'escape_by_ref'));
		return @vsprintf($query, $this->prepare_args).$flag;
	}

	/**
	 * Print SQL/DB error.
	 *
	 * @since 0.71
	 * @global array $EZSQL_ERROR Stores error information of query and error string
	 *
	 * @param string $str The error to display
	 * @return bool False if the showing of errors is disabled.
	 */
	function print_error($str = '') {
		global $EZSQL_ERROR;

		if (!$str) {
			$str = mssql_get_last_message();
		}
		$EZSQL_ERROR[] = array ('query' => $this->last_query, 'error_str' => $str);

		if ( $this->suppress_errors )
			return false;

		if ( $caller = $this->get_caller() )
			$error_str = sprintf(/*WP_I18N_DB_QUERY_ERROR_FULL*/'WordPress database error %1$s for query %2$s made by %3$s'/*/WP_I18N_DB_QUERY_ERROR_FULL*/, $str, $this->last_query, $caller);
		else
			$error_str = sprintf(/*WP_I18N_DB_QUERY_ERROR*/'WordPress database error %1$s for query %2$s'/*/WP_I18N_DB_QUERY_ERROR*/, $str, $this->last_query);

		$log_error = true;
		if ( ! function_exists('error_log') )
			$log_error = false;

		$log_file = @ini_get('error_log');
		if ( !empty($log_file) && ('syslog' != $log_file) && !@is_writable($log_file) )
			$log_error = false;

		if ( $log_error )
			@error_log($error_str, 0);

		// Is error output turned on or not..
		if ( !$this->show_errors )
			return false;

		// If there is an error then take note of it
		$msg = "WordPress database error: [$str]\n{$this->query}\n";
		
		$str = print_r($str);
		$query = htmlspecialchars($this->last_query, ENT_QUOTES);

		// If there is an error then take note of it
		print "<div id='error'>
		<p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
		<code>$query</code></p>
		</div>";
		
		if( defined( 'ERRORLOGFILE' ) )
			error_log( $msg, 3, CONSTANT( 'ERRORLOGFILE' ) );
		if( defined( 'DIEONDBERROR' ) )
			die( $msg );
	}

	/**
	 * Enables showing of database errors.
	 *
	 * This function should be used only to enable showing of errors.
	 * wpdb::hide_errors() should be used instead for hiding of errors. However,
	 * this function can be used to enable and disable showing of database
	 * errors.
	 *
	 * @since 0.71
	 *
	 * @param bool $show Whether to show or hide errors
	 * @return bool Old value for showing errors.
	 */
	function show_errors( $show = true ) {
		$errors = $this->show_errors;
		$this->show_errors = $show;
		return $errors;
	}

	/**
	 * Disables showing of database errors.
	 *
	 * @since 0.71
	 *
	 * @return bool Whether showing of errors was active or not
	 */
	function hide_errors() {
		$show = $this->show_errors;
		$this->show_errors = false;
		return $show;
	}

	/**
	 * Whether to suppress database errors.
	 *
	 * @param unknown_type $suppress
	 * @return unknown
	 */
	function suppress_errors( $suppress = true ) {
		$errors = $this->suppress_errors;
		$this->suppress_errors = $suppress;
		return $errors;
	}

	/**
	 * Kill cached query results.
	 *
	 * @since 0.71
	 */
	function flush() {
		$this->last_result = array();
		$this->col_info = null;
		$this->last_query = null;
	}
	
	function db_connect( $query = "SELECT" ) {
		global $db_list, $global_db_list;
		if( is_array( $db_list ) == false )
			return true;

		if( $this->blogs != '' && preg_match("/(" . $this->blogs . "|" . $this->users . "|" . $this->usermeta . "|" . $this->site . "|" . $this->sitemeta . "|" . $this->sitecategories . ")/i",$query) ) {
			$action = 'global';
			$details = $global_db_list[ mt_rand( 0, count( $global_db_list ) -1 ) ];
			$this->db_global = $details;
		} elseif ( preg_match("/^\\s*(alter table|create|insert|delete|update|replace) /i",$query) ) {
			$action = 'write';
			$details = $db_list[ 'write' ][ mt_rand( 0, count( $db_list[ 'write' ] ) -1 ) ];
			$this->db_write = $details;
		} else {
			$action = '';
			$details = $db_list[ 'read' ][ mt_rand( 0, count( $db_list[ 'read' ] ) -1 ) ];
			$this->db_read = $details;
		}

		$dbhname = "dbh" . $action;
		
		// Make sure the version is the same for your ntwdblib.dll.
		// The TDS library and the ntwdblib.dll can't be speaking two different protocols.
		putenv("TDSVER=70");

		// Set text limit sizes to max BEFORE connection is made
		ini_set('mssql.textlimit', 2147483647);
		ini_set('mssql.textsize', 2147483647);

		if (get_magic_quotes_gpc()) {
			$details[ 'db_host' ] = trim(str_replace("\\\\", "\\", $details[ 'db_host' ]));
		}

		$this->$dbhname = mssql_connect($details[ 'db_host' ], $details[ 'db_user' ], $details[ 'db_password' ]);
		mssql_min_error_severity(0);
		mssql_min_message_severity(17);
		mssql_select_db($details[ 'db_name' ], $this->$dbhname);
	
		if (!$this->$dbhname ) {
			$this->bail("
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>$dbhost</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
");
		}
	}

	/**
	 * Perform a MSSQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @since 0.71
	 *
	 * @param string $query
	 * @param bool $translate Should we translate the query?
	 * @return int|false Number of rows affected/selected or false on error
	 */
	function query($query, $translate = true) {

		if ( ! $this->ready ) {
			return false;
		}

		// filter the query, if filters are available
		// NOTE: some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
		if ( function_exists('apply_filters') ) {
			$query = apply_filters('query', $query);
		}

		// initialise return
		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;
		
		// use $this->dbh for read ops, and $this->dbhwrite for write ops
		// use $this->dbhglobal for gloal table ops
		unset( $dbh );
		if( defined( "WP_USE_MULTIPLE_DB" ) && CONSTANT( "WP_USE_MULTIPLE_DB" ) == true ) {
			if( $this->blogs != '' && preg_match("/(" . $this->blogs . "|" . $this->users . "|" . $this->usermeta . "|" . $this->site . "|" . $this->sitemeta . "|" . $this->sitecategories . ")/i",$query) ) {
				if( false == isset( $this->dbhglobal ) ) {
					$this->db_connect( $query );
				}
				$dbh =& $this->dbhglobal;
				$this->last_db_used = "global";
			} elseif ( preg_match("/^\\s*(alter table|create|insert|delete|update|replace) /i",$query) ) {
				if( false == isset( $this->dbhwrite ) ) {
					$this->db_connect( $query );
				}
				$dbh =& $this->dbhwrite;
				$this->last_db_used = "write";
			} else {
				$dbh =& $this->dbh;
				$this->last_db_used = "read";
			}
		} else {
			$dbh =& $this->dbh;
			$this->last_db_used = "other/read";
		}

		// Make Necessary Translations
		if ($translate === true) {
			$query = $this->translate($query);
			$this->previous_query = $query;
		}
		
		if ($this->preceeding_query !== false) {
			if (is_array($this->preceeding_query)) {
				foreach ($this->preceeding_query as $p_query) {
					@mssql_query($p_query, $dbh);
				}
			} else {
				@mssql_query($this->preceeding_query, $dbh);
			}
			$this->preceeding_query = false;
		}
		
		// Check if array of queries (this happens for INSERTS with multiple VALUES blocks)
		if (is_array($query)) {
			foreach ($query as $sub_query) {
				$this->_pre_query();
				$this->result = @mssql_query($sub_query, $dbh);
				$return_val = $this->_post_query($sub_query, $dbh);
			}
		} else {
			$this->_pre_query();
			$this->result = @mssql_query($query, $dbh);
			$return_val = $this->_post_query($query, $dbh);
		}
		
		if ($this->following_query !== false) {
			if (is_array($this->following_query)) {
				foreach ($this->following_query as $f_query) {
					@mssql_query($f_query, $dbh);
				}
			} else {
				@mssql_query($this->following_query, $dbh);
			}
			$this->following_query = false;
		}

		return $return_val;
	}
	
	function _pre_query() {
		if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
			$this->timer_start();
		}
	} 
	
	function _post_query($query, $dbh) {
		++$this->num_queries;
		// If there is an error then take note of it..
		if ( $this->result == FALSE && $this->last_error = mssql_get_last_message() ) {
			//var_dump($query);
			//var_dump($this->translation_changes);
			$this->print_error();
			return false;
		}

		if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {

			$this->rows_affected = mssql_rows_affected($dbh);
			// Take note of the insert_id
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$result = @mssql_fetch_object(@mssql_query("SELECT SCOPE_IDENTITY() AS ID", $dbh));
				$this->insert_id = $result->ID;
			}

			$return_val = $this->rows_affected;
		} else {

			$i = 0;
			while ($i < @mssql_num_fields($this->result)) {
				$field = @mssql_fetch_field($this->result, $i);
				$new_field = new stdClass();
				$new_field->name = $field->name;
				$new_field->table = $field->column_source;
				$new_field->def = null;
				$new_field->max_length = $field->max_length;
				$new_field->not_null = true;
				$new_field->primary_key = null;
				$new_field->unique_key = null;
				$new_field->multiple_key = null;
				$new_field->numeric = $field->numeric;
				$new_field->blob = null;
				$new_field->type = $field->type;
				if(isset($field->unsigned)) {
					$new_field->unsigned = $field->unsigned;
				} else {
					$new_field->unsigned = null;
				}
				$new_field->zerofill = null;
				$this->col_info[$i] = $new_field;
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mssql_fetch_object($this->result) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}
			$this->last_result = $this->fix_results($this->last_result);
			// perform limit
			if (!empty($this->limit)) {
				$this->last_result = array_slice($this->last_result, $this->limit['from'], $this->limit['to']);
				$num_rows = count($this->last_result);
			}

			@mssql_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;

			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
	}

	/**
	 * Insert a row into a table.
	 *
	 * <code>
	 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
	 * </code>
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format (optional) An array of formats to be mapped to each of the value in $data.  If string, that format will be used for all of the values in $data.  A format is one of '%d', '%s' (decimal number, string).  If omitted, all values in $data will be treated as strings.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	function insert($table, $data, $format = null) {
		$formats = $format = (array) $format;
		$fields = array_keys($data);
		$formatted_fields = array();
		foreach ( $fields as $field ) {
			if ( !empty($format) )
				$form = ( $form = array_shift($formats) ) ? $form : $format[0];
			elseif ( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form;
		}
		$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
		return $this->query( $this->prepare( $sql, $data) );
	}

	/**
	 * Update a row in the table
	 *
	 * <code>
	 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
	 * </code>
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 *
	 * @param string $table table name
	 * @param array $data Data to update (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array $where A named array of WHERE clauses (in column => value pairs).  Multiple clauses will be joined with ANDs.  Both $where columns and $where values should be "raw".
	 * @param array|string $format (optional) An array of formats to be mapped to each of the values in $data.  If string, that format will be used for all of the values in $data.  A format is one of '%d', '%s' (decimal number, string).  If omitted, all values in $data will be treated as strings.
	 * @param array|string $format_where (optional) An array of formats to be mapped to each of the values in $where.  If string, that format will be used for all of  the items in $where.  A format is one of '%d', '%s' (decimal number, string).  If omitted, all values in $where will be treated as strings.
	 * @return int|false The number of rows updated, or false on error.
	 */
	function update($table, $data, $where, $format = null, $where_format = null) {
		if ( !is_array( $where ) )
			return false;

		$formats = $format = (array) $format;
		$bits = $wheres = array();
		foreach ( (array) array_keys($data) as $field ) {
			if ( !empty($format) )
				$form = ( $form = array_shift($formats) ) ? $form : $format[0];
			elseif ( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$bits[] = "`$field` = {$form}";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach ( (array) array_keys($where) as $field ) {
			if ( !empty($where_format) )
				$form = ( $form = array_shift($where_formats) ) ? $form : $where_format[0];
			elseif ( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$wheres[] = "`$field` = {$form}";
		}

		$sql = "UPDATE `$table` SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres );
		return $this->query( $this->prepare( $sql, array_merge(array_values($data), array_values($where))) );
	}

	/**
	 * Retrieve one variable from the database.
	 *
	 * This combines the functionality of wpdb::get_row() and wpdb::get_col(),
	 * so both the column and row can be picked.
	 *
	 * It is possible to use this function without executing more queries. If
	 * you already made a query, you can set the $query to 'null' value and just
	 * retrieve either the column and row of the last query result.
	 *
	 * @since 0.71
	 *
	 * @param string $query Can be null as well, for caching
	 * @param int $x Column num to return
	 * @param int $y Row num to return
	 * @return mixed Database query results
	 */
	function get_var($query=null, $x = 0, $y = 0) {
		$this->func_call = "\$db->get_var(\"$query\",$x,$y)";
		if ( $query )
			$this->query($query);

		// Extract var out of cached results based x,y vals
		if ( !empty( $this->last_result[$y] ) ) {
			$values = array_values(get_object_vars($this->last_result[$y]));
		}

		// If there is a value return it else return null
		return (isset($values[$x]) && $values[$x]!=='') ? $values[$x] : null;
	}

	/**
	 * Retrieve one row from the database.
	 *
	 * @since 0.71
	 *
	 * @param string $query SQL query
	 * @param string $output ARRAY_A | ARRAY_N | OBJECT
	 * @param int $y Row num to return
	 * @return mixed Database query results
	 */
	function get_row($query = null, $output = OBJECT, $y = 0) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->query($query);
		else
			return null;

		if ( !isset($this->last_result[$y]) )
			return null;

		if ( $output == OBJECT ) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
		} else {
			$this->print_error(/*WP_I18N_DB_GETROW_ERROR*/" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N"/*/WP_I18N_DB_GETROW_ERROR*/);
		}
	}

	/**
	 * Retrieve one column from the database.
	 *
	 * @since 0.71
	 *
	 * @param string $query Can be null as well, for caching
	 * @param int $x Col num to return. Starts from 0.
	 * @return array Column results
	 */
	function get_col($query = null , $x = 0) {
		if ( $query )
			$this->query($query);

		$new_array = array();
		// Extract the column values
		for ( $i=0; $i < count($this->last_result); $i++ ) {
			$new_array[$i] = $this->get_var(null, $x, $i);
		}
		return $new_array;
	}

	/**
	 * Retrieve an entire result set from the database.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Can also be null to pull from the cache
	 * @param string $output ARRAY_A | ARRAY_N | OBJECT_K | OBJECT
	 * @return mixed Database query results
	 */
	function get_results($query = null, $output = OBJECT) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->query($query);
		else
			return null;

		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ( $output == OBJECT_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ( $this->last_result as $row ) {
				$key = array_shift( get_object_vars( $row ) );
				if ( !isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->last_result ) {
				$i = 0;
				foreach( (array) $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[$i] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[$i] = get_object_vars( $row );
					}
					++$i;
				}
				return $new_array;
			}
		}
	}

	/**
	 * Retrieve column metadata from the last query.
	 *
	 * @since 0.71
	 *
	 * @param string $info_type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
	 * @param int $col_offset 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
	 * @return mixed Column Results
	 */
	function get_col_info($info_type = 'name', $col_offset = -1) {
		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
				$i = 0;
				foreach( (array) $this->col_info as $col ) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}

	/**
	 * Starts the timer, for debugging purposes.
	 *
	 * @since 1.5.0
	 *
	 * @return bool Always returns true
	 */
	function timer_start() {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$this->time_start = $mtime[1] + $mtime[0];
		return true;
	}

	/**
	 * Stops the debugging timer.
	 *
	 * @since 1.5.0
	 *
	 * @return int Total time spent on the query, in milliseconds
	 */
	function timer_stop() {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$time_end = $mtime[1] + $mtime[0];
		$time_total = $time_end - $this->time_start;
		return $time_total;
	}

	/**
	 * Wraps fatal errors in a nice header and footer and dies.
	 *
	 * @since 1.5.0
	 *
	 * @param string $message
	 * @return unknown
	 */
	function bail($message) {
		if ( !$this->show_errors ) {
			if ( class_exists('WP_Error') )
				$this->error = new WP_Error('500', $message);
			else
				$this->error = $message;
			return false;
		}
		wp_die($message);
	}

	/**
	 * Whether or not MySQL database is minimal required version.
	 *
	 * @since 2.5.0
	 * @uses $wp_version
	 *
	 * @return WP_Error
	 */
	function check_database_version()
	{
		global $wp_version;
		// Base on database type
		//
		if ($this->db_type == "mysql") {
			// Make sure the server has MySQL 4.0
			if ( version_compare($this->db_version(), '4.0.0', '<') )
			return new WP_Error('database_version',sprintf(__('<strong>ERROR</strong>: WordPress %s requires MySQL 4.0.0 or higher'), $wp_version));
		}
	}

	/**
	 * Whether of not the database version supports collation.
	 *
	 * Called when WordPress is generating the table scheme.
	 *
	 * @since 2.5.0
	 *
	 * @return bool True if collation is supported, false if version does not
	 */
	function supports_collation()
	{
		return false;
	}

	/**
	 * Retrieve the name of the function that called wpdb.
	 *
	 * Requires PHP 4.3 and searches up the list of functions until it reaches
	 * the one that would most logically had called this method.
	 *
	 * @since 2.5.0
	 *
	 * @return string The name of the calling function
	 */
	function get_caller() {
		// requires PHP 4.3+
		if ( !is_callable('debug_backtrace') )
			return '';

		$bt = debug_backtrace();
		$caller = array();

		$bt = array_reverse( $bt );
		foreach ( (array) $bt as $call ) {
			if ( @$call['class'] == __CLASS__ )
				continue;
			$function = $call['function'];
			if ( isset( $call['class'] ) )
				$function = $call['class'] . "->$function";
			$caller[] = $function;
		}
		$caller = join( ', ', $caller );

		return $caller;
	}
	
	/**
	 * The database version number
	 * @param false|string|resource $dbh_or_table (not implemented) Which database to test.  False = the currently selected database, string = the database containing the specified table, resource = the database corresponding to the specified mysql resource.
	 * @return false|string false on failure, version number on success
	 */
	function db_version() {
		return '5.0';
	}
}