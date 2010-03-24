<?php
require_once(dirname(__FILE__) . '/fields_map.php');

/**
 * SQL Dialect Translations
 *
 * @category MSSQL
 * @package MySQL_Translations
 * @author A.Garcia & A.Gentile
 * */
class SQL_Translations
{
    /**
     * Field Mapping
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $fields_map = null;

    /**
     * Was this query prepared?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $prepared = false;

    /**
     * Update query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $update_query = false;

    /**
     * Insert query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $insert_query = false;
	
    /**
     * Delete query?
     *
     * @since 2.7.1
     * @access private
     * @var bool
     */
    var $delete_query = false;

    /**
     * Prepare arguments
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $prepare_args = array();

    /**
     * Update Data
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $update_data = array();
	
	/**
     * Limit Info
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $limit = array();

    /**
     * Update Data
     *
     * @since 2.7.1
     * @access private
     * @var array
     */
    var $translation_changes = array();
	
	/**
     * Azure
	 * Are we dealing with a SQL Azure DB?
     *
     * @since 2.7.1
     * @access public
     * @var bool
     */
    var $azure = false;
	
	/**
	 * Preceeding query
	 * Sometimes we need to issue a query
	 * before the original query 
	 *
	 * @since 2.8.5
	 * @access public
	 * @var mixed
	 */
	var $preceeding_query = false;
	
	/**
	 * Following query
	 * Sometimes we need to issue a query
	 * right after the original query 
	 *
	 * @since 2.8.5
	 * @access public
	 * @var mixed
	 */
	var $following_query = false;
	
	/**
	 * Should we verify update/insert queries?
	 *
	 * @since 2.8.5
	 * @access public
	 * @var mixed
	 */
	var $verify = true;
	
    /**
     * php4 style call to constructor.
     * 
     * @since 2.7.1
     *
     */
    function SQL_Translations()
    {
		return $this->__construct();
    }
	
	/**
	 * Assign fields_map as a new Fields_map object
	 *
	 * PHP5 style constructor for compatibility with PHP5.
	 *
	 * @since 2.7.1
	 */
	function __construct()
	{
		$this->fields_map = new Fields_map();
	}

    /**
     * MySQL > MSSQL Query Translation
     * Processes smaller translation sub-functions
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate($query)
    {
		$this->limit = array();
		$this->verify = true;
		$this->insert_query = false;
		$this->delete_query = false;
		$this->update_query = false;
		$this->preceeding_query = false;
		$this->following_query = false;
		
		if ( stripos($query, 'INSERT') === 0 ) {
            $this->insert_query = true;
		}
		
		if ( stripos($query, 'DELETE') === 0 ) {
            $this->delete_query = true;
		}
		
        // Was this query prepared?
        if ( strripos($query, '--PREPARE') !== FALSE ) {
            $query = str_replace('--PREPARE', '', $query);
            $this->prepared = TRUE;
        } else {
            $this->prepared = FALSE;
        }

        // Update Query?
        if ( stripos($query, 'UPDATE') === 0 ) {
            $this->update_query = true;
        }

        // Do we have serialized arguments?
        if ( strripos($query, '--SERIALIZED') !== FALSE ) {
            $query = str_replace('--SERIALIZED', '', $query);
			if ($this->insert_query) {
				$query = $this->on_duplicate_key($query);
			}
            $query = $this->translate_general($query);
            return $query;
        }

        $query = trim($query);

        $sub_funcs = array(
            'translate_general',
            'translate_date_add',
            'translate_if_stmt',
            'translate_sqlcalcrows',
			'translate_limit',
            'translate_now_datetime',
            'translate_distinct_orderby',
            'translate_sort_casting',
            'translate_column_type',
            'translate_remove_groupby',
            'translate_insert_nulltime',
            'translate_incompat_data_type',
            'translate_create_queries',
        );

        // Perform translations and record query changes.
        $this->translation_changes = array();
        foreach ( $sub_funcs as $sub_func ) {
            $old_query = $query;
            $query = $this->$sub_func($query);
            if ( $old_query !== $query ) {
                $this->translation_changes[] = $sub_func;
                $this->translation_changes[] = $query;
                $this->translation_changes[] = $old_query;
            }
        }
        if ( $this->insert_query ) {
			$query = $this->on_duplicate_key($query);
            $query = $this->split_insert_values($query);
        }
        if ( $this->prepared && $this->insert_query && $this->verify ) {
            if ( is_array($query) ) {
                foreach ($query as $k => $v) {
                    $query[$k] = $this->verify_insert($v);
                }
            } else {
                $query = $this->verify_insert($query);
            }
        }

        if ( $this->update_query && $this->verify ) {
            $query = $this->verify_update($query);
        }

        return $query;
    }

    /**
     * More generalized information gathering queries
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_general($query)
    {
        // SERVER VERSION
        if ( stripos($query, 'SELECT VERSION()' ) === 0) {
            $query = substr_replace($query, 'SELECT @@VERSION', 0, 16);
        }
        // SQL_MODE NO EQUIV
        if ( stripos($query, "SHOW VARIABLES LIKE 'sql_mode'" ) === 0) {
            $query = '';
        }
        // LAST INSERT ID
        if ( stripos($query, 'LAST_INSERT_ID()') > 0 ) {
            $start_pos = stripos($query, 'LAST_INSERT_ID()');
            $query = substr_replace($query, '@@IDENTITY', $start_pos, 16);
        }
        // SHOW TABLES
        if ( strtolower($query) === 'show tables;' ) {
            $query = str_ireplace('show tables',"select name from SYSOBJECTS where TYPE = 'U' order by NAME",$query);
        }
        if ( stripos($query, 'show tables like ') === 0 ) {
            $end_pos = strlen($query);
            $param = substr($query, 17, $end_pos - 17);
            $query = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE ' . $param;
        }
        // SET NAMES doesn't exist in T-SQL
        if ( strtolower($query) == "set names 'utf8'" ) {
            $query = "";
        }
        // SHOW COLUMNS
        if ( stripos($query, 'SHOW COLUMNS FROM ') === 0 ) {
            $end_pos = strlen($query);
            $param = substr($query, 18, $end_pos - 18);
            $param = "'". trim($param, "'") . "'";
            $query = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ' . $param;
        }
		
		// SHOW INDEXES - issue with sql azure trying to fix....sys.sysindexes is coming back as invalid onject name
		if ( stripos($query, 'SHOW INDEXES FROM ') === 0 ) {
			return $query;
			$table = substr($query, 18);
			$query = "SELECT sys.sysindexes.name AS IndexName
					  FROM sysobjects
				       JOIN sys.key_constraints ON parent_object_id = sys.sysobjects.id
				       JOIN sys.sysindexes ON sys.sysindexes.id = sys.sysobjects.id and sys.key_constraints.unique_index_id = sys.sysindexes.indid
				       JOIN sys.index_columns ON sys.index_columns.object_id = sys.sysindexes.id  and sys.index_columns.index_id = sys.sysindexes.indid
				       JOIN sys.syscolumns ON sys.syscolumns.id = sys.sysindexes.id AND sys.index_columns.column_id = sys.syscolumns.colid
					  WHERE sys.sysobjects.type = 'u'	
					   AND sys.sysobjects.name = '{$table}'";
		}
		
        // USE INDEX
        if ( stripos($query, 'USE INDEX (') !== FALSE) {
            $start_pos = stripos($query, 'USE INDEX (');
            $end_pos = $this->get_matching_paren($query, $start_pos + 11);
            $params = substr($query, $start_pos + 11, $end_pos - ($start_pos + 11));
            $params = explode(',', $params);
            foreach ($params as $k => $v) {
                $params[$k] = trim($v);
                foreach ($this->fields_map->read() as $table => $fields) {
                    if ( is_array($fields) ) {
                        foreach ($fields as $field_name => $field_meta) {
                            if ( $field_name == $params[$k] ) {
                                $params[$k] = $table . '_' . $params[$k];
                            }
                        }
                    }
                }
            }
            $params = implode(',', $params);
            $query = substr_replace($query, 'WITH (INDEX(' . $params . '))', $start_pos, ($end_pos + 1) - $start_pos);
        }
		
		// DESCRIBE - this is pretty darn close to mysql equiv, however it will need to have a flag to modify the result set
		// this and SHOW INDEX FROM are used in WP upgrading. The problem is that WP will see the different data types and try
		// to alter the table thinking an upgrade is necessary. So the result set from this query needs to be modified using
		// the field_mapping to revert column types back to their mysql equiv to fool WP.
		if ( stripos($query, 'DESCRIBE ') === 0 ) {
			return $query;
			$table = substr($query, 9);
			$query = $this->describe($table);
		}

        // DROP TABLES
        if ( stripos($query, 'DROP TABLE IF EXISTS ') === 0 ) {
            $table = substr($query, 21, strlen($query) - 21);
            $query = 'DROP TABLE ' . $table;
        } elseif ( stripos($query, 'DROP TABLE ') === 0 ) {
            $table = substr($query, 11, strlen($query) - 11);
            $query = 'DROP TABLE ' . $table;
        }
		
		// REGEXP - not supported in TSQL
		if ( stripos($query, 'REGEXP') > 0 ) {
			if ( $this->delete_query && stripos($query, '^rss_[0-9a-f]{32}(_ts)?$') > 0 ) {
				$start_pos = stripos($query, 'REGEXP');
				$query = substr_replace($query, "LIKE 'rss_'", $start_pos);
			}
		}

        // TICKS
        $query = str_replace('`', '', $query);

        // Computed
        // This is done as the SQLSRV driver doesn't seem to set a property value for computed
        // selected columns, thus WP doesn't have anything to work with.
        $query = str_ireplace('SELECT COUNT(*)', 'SELECT COUNT(*) as Computed', $query);
		$query = str_ireplace('SELECT COUNT(1)', 'SELECT COUNT(1) as Computed', $query);
		
		// Turn on IDENTITY_INSERT for Importing inserts or category/tag adds that are 
		// trying to explicitly set and IDENTITY column (WPMU)
		if ($this->insert_query) {
			$tables = array(
				$this->prefix . 'posts' => 'id', 
				$this->prefix . 'terms' => 'term_id', 
			);
			foreach ($tables as $table => $pid) {
				if (stristr($query, 'INTO ' . $table) !== FALSE) {
					$strlen = strlen($table);
					$start_pos = stripos($query, $table) + $strlen;
					$start_pos = stripos($query, '(', $start_pos);
					$end_pos = $this->get_matching_paren($query, $start_pos + 1);
					$params = substr($query, $start_pos + 1, $end_pos - ($start_pos + 1));
					$params = explode(',', $params);
					$found = false;
					foreach ($params as $k => $v) {
						if (strtolower($v) === $pid) {
							$found = true;
						}	
					}
					
					if ($found) {
						$this->preceeding_query = "SET IDENTITY_INSERT $table ON";
						$this->following_query = "SET IDENTITY_INSERT $table OFF";
					}
				}
			}
		}
		
		// UPDATE queries trying to change an IDENTITY column this happens
		// for cat/tag adds (WPMU) e.g. UPDATE wp_1_terms SET term_id = 5 WHERE term_id = 3330
		if ($this->update_query) {
			$tables = array(
				$this->prefix . 'terms' => 'term_id', 
			);
			foreach ($tables as $table => $pid) {
				if (stristr($query, $table . ' SET ' . $pid) !== FALSE) {
					preg_match_all("^=\s\d+^", $query, $matches);
					if (!empty($matches) && count($matches[0]) == 2) {
						$to = trim($matches[0][0], '= ');
						$from = trim($matches[0][1], '= ');
						$this->preceeding_query = "SET IDENTITY_INSERT $table ON";
						// find a better way to get columns (field mapping doesn't grab all)
						$query = "INSERT INTO $table (term_id,name,slug,term_group) SELECT $to,name,slug,term_group FROM $table WHERE $pid = $from";
						$this->following_query = array("DELETE $table WHERE $pid = $from","SET IDENTITY_INSERT $table OFF");
						$this->verify = false;
					}
				}
			}
		}
		
        return $query;
    }


    /**
     * Changes for DATE_ADD and INTERVAL
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_date_add($query)
    {
        $query = preg_replace('/date_add\((.*?),.*?([0-9]+?) (.*?)\)/i', 'DATEADD(\3,\2,\1)', $query);
        $query = preg_replace('/date_sub\((.*?),.*?([0-9]+?) (.*?)\)/i', 'DATEADD(\3,-\2,\1)', $query);

        return $query;
    }


    /**
     * Removing Unnecessary IF statement that T-SQL doesn't play nice with
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_if_stmt($query)
    {
        if ( stripos($query, 'IF (DATEADD(') > 0 ) {
            $start_pos = stripos($query, 'DATEADD(');
            $end_pos = $this->get_matching_paren($query, $start_pos + 8);
            $stmt = substr($query, $start_pos, ($end_pos - $start_pos)) . ') >= getdate() THEN 1 ELSE 0 END)';

            $start_pos = stripos($query, 'IF (');
            $end_pos = $this->get_matching_paren($query, ($start_pos+6))+1;
            $query = substr_replace($query, '(CASE WHEN ' . $stmt, $start_pos, ($end_pos - $start_pos));
        }
        return $query;
    }

    /**
     * SQL_CALC_FOUND_ROWS does not exist in T-SQL
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_sqlcalcrows($query)
    {
        if (stripos($query, 'SQL_CALC_FOUND_ROWS') > 0 ) {
            $sql_calc_pos = stripos($query, 'SQL_CALC_FOUND_ROWS');
            $from_pos = stripos($query, 'FROM');
            $query = substr_replace($query,'* ', $sql_calc_pos, ($from_pos - $sql_calc_pos));
        }
        // catch the next query.
        if ( stripos($query, 'FOUND_ROWS()') > 0 ) {
			$from_pos = stripos($this->previous_query, 'FROM');
			$where_pos = stripos($this->previous_query, 'WHERE');
			$from_str = trim(substr($this->previous_query, $from_pos, ($where_pos - $from_pos)));
			$order_by_pos = stripos($this->previous_query, 'ORDER BY');
			$where_str = trim(substr($this->previous_query, $where_pos, ($order_by_pos - $where_pos)));
			$query = str_ireplace('FOUND_ROWS()', 'COUNT(1) as Computed ' . $from_str . ' ' . $where_str, $query);
        }
        return $query;
    }

    /**
     * Changing LIMIT to TOP...mimicking offset while possible with rownum, it has turned
	 * out to be very problematic as depending on the original query, the derived table
	 * will have a lot of problems with columns names, ordering and what not. 
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_limit($query)
    {
        if ( (stripos($query,'SELECT') !== 0 && stripos($query,'SELECT') !== FALSE)
            && (stripos($query,'UPDATE') !== 0  && stripos($query,'UPDATE') !== FALSE) ) {
            return $query;
        }
        $pattern = '/LIMIT\s*(\d+)((\s*,?\s*)(\d+)*)$/is';
        $matched = preg_match($pattern, $query, $limit_matches);
        if ( $matched == 0 ) {
            return $query;
        }
        // Remove the LIMIT statement
        $true_offset = false;
        $query = preg_replace($pattern, '', $query);
        if ( stripos($query,'DELETE') === 0 ) {
            return $query;
        }
        // Check for true offset
        if ( count($limit_matches) == 5 && $limit_matches[1] != '0' ) {
            $true_offset = true;
        } elseif ( count($limit_matches) == 5 && $limit_matches[1] == '0' ) {
            $limit_matches[1] = $limit_matches[4];
        }

        // Rewrite the query.
        if ( $true_offset === false ) {
            if ( stripos($query, 'DISTINCT') > 0 ) {
                $query = str_ireplace('DISTINCT', 'DISTINCT TOP ' . $limit_matches[1] . ' ', $query);
            } else {
                $query = str_ireplace('DELETE ', 'DELETE TOP ' . $limit_matches[1] . ' ', $query);
                $query = str_ireplace('SELECT ', 'SELECT TOP ' . $limit_matches[1] . ' ', $query);
            }
        } else {
            $limit_matches[1] = (int) $limit_matches[1];
            $limit_matches[4] = (int) $limit_matches[4];

			$this->limit = array(
				'from' => $limit_matches[1], 
				'to' => $limit_matches[4]
			);
        }
        return $query;
    }


    /**
     * Replace From UnixTime and now()
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_now_datetime($query)
    {
        $replacement = 'getdate()';
        $query = preg_replace('/(from_unixtime|unix_timestamp)\s*\(([^\)]*)\)/i', $replacement, $query);
        $query = str_ireplace('NOW()', $replacement, $query);

        // REPLACE dayofmonth which doesn't exist in T-SQL
        $check = $query;
        $query = preg_replace('/dayofmonth\((.*?)\)/i', 'DATEPART(DD,\1)',$query);
        if ($check !== $query) {
            $as_array = $this->get_as_fields($query);
            if (empty($as_array)) {
                $query = str_ireplace('FROM','as dom FROM',$query);
            }
        }
        return $query;
    }

    /**
     * Order By within a Select Distinct needs to have an field for every alias
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_distinct_orderby($query)
    {
        if ( stripos($query, 'DISTINCT') > 0 ) {
            if ( stripos($query, 'ORDER') > 0 ) {
                $order_pos = stripos($query, 'ORDER');
                if ( stripos($query, 'BY', $order_pos) > $order_pos ) {
                    $fields = $this->get_as_fields($query);
                    $ob = stripos($query, 'BY', $order_pos);
                    if ( stripos($query, ' ASC', $ob) > 0 ) {
                        $ord = stripos($query, ' ASC', $ob);
                    }
                    if ( stripos($query, ' DESC', $ob) > 0 ) {
                        $ord = stripos($query, ' DESC', $ob);
                    }
                    $str = 'BY ';
                    $str .= implode(', ',$fields);

                    $query = substr_replace($query, $str, $ob, ($ord-$ob));
                }
            }
        }
        return $query;
    }


    /**
     * To sort text fields they need to be first cast as varchar
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_sort_casting($query)
    {
        if ( in_array('translate_limit', $this->translation_changes)) {
            //return $query;
        }
        if ( stripos($query, 'ORDER') > 0 ) {
            $order_pos = stripos($query, 'ORDER');
            if ( stripos($query, 'BY', $order_pos) == ($order_pos + 6) && stripos($query, 'OVER(', $order_pos - 5) != ($order_pos - 5)) {
                $ob = stripos($query, 'BY', $order_pos);
                if ( stripos($query,' ASC', $ob) > 0 ) {
                    $ord = stripos($query, ' ASC', $ob);
                }
                if ( stripos($query,' DESC', $ob) > 0 ) {
                    $ord = stripos($query, ' DESC', $ob);
                }

                $params = substr($query, ($ob + 3), ($ord - ($ob + 3)));
                $params = preg_split('/[\s,]+/', $params);
                $p = array();
                foreach ( $params as $value ) {
                    $value = str_replace(',', '', $value);
                    if ( !empty($value) ) {
                        $p[] = $value;
                    }
                }
                $str = '';

                foreach ($p as $v ) {
                    $match = false;
                    foreach( $this->fields_map->read() as $table => $table_fields ) {
                        if ( is_array($table_fields) ) {
                            foreach ( $table_fields as $field => $field_meta) {
                                if ($field_meta['type'] == 'text') {
                                    if ( $v == $table . '.' . $field || $v == $field) {
                                        $match = true;
                                    }
                                }
                            }
                        }
                    }
                    if ( $match ) {
                        $str .= 'cast(' . $v . ' as varchar(255)), ';
                    } else {
                        $str .= $v . ', ';
                    }
                }
                $str = rtrim($str, ', ');
                $query = substr_replace($query, $str, ($ob + 3), ($ord - ($ob + 3)));
            }
        }
        return $query;
    }

    /**
     * Meta key fix. \_%  to  [_]%
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_column_type($query)
    {
        if ( stripos($query, "LIKE '\_%'") > 0 ) {
            $start_pos = stripos($query, "LIKE '\_%'");
            $end_pos = $start_pos + 10;
            $str = "LIKE '[_]%'";
            $query = substr_replace($query, $str, $start_pos, ($end_pos - $start_pos));
        }
        return $query;
    }


    /**
     * Remove group by stmt in certain queries as T-SQL will
     * want all column names to execute query properly
     *
     * FIXES: Column 'wp_posts.post_author' is invalid in the select list because
     * it is not contained in either an aggregate function or the GROUP BY clause.
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_remove_groupby($query)
    {
        $query = str_ireplace("GROUP BY {$this->prefix}posts.ID ", ' ', $query);
        // Fixed query for archives widgets.
        $query = str_ireplace(
            'GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date DESC',
            'GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY month DESC, year DESC',
            $query
        );
        return $query;
    }


    /**
     * When INSERTING 0000-00-00 00:00:00 or '' for datetime SQL Server says wtf
     * because it's null value begins at 1900-01-01...so lets change this to current time.
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_insert_nulltime($query)
    {
        if ( stripos($query, 'INSERT') === 0 ) {
            // Lets grab the fields to be inserted into and their position
            // based on the csv.
            $first_paren = stripos($query, '(', 11) + 1;
            $last_paren = $this->get_matching_paren($query, $first_paren);
            $fields = explode(',',substr($query, $first_paren, ($last_paren - $first_paren)));
            $date_fields = array();
            $date_fields_map = $this->fields_map->by_type('date');
            foreach ($fields as $key => $field ) {
                $field = trim($field);

                if ( in_array($field, $date_fields_map) ) {
                    $date_fields[] = array('pos' => $key, 'field' => $field);
                }
            }
            // we have date fields to check
            if ( count($date_fields) > 0 ) {
                // we need to get the values
                $values_pos = stripos($query, 'VALUES');
                $first_paren = stripos($query, '(', $values_pos);
                $last_paren = $this->get_matching_paren($query, ($first_paren + 1));
                $values = explode(',',substr($query, ($first_paren+1), ($last_paren-($first_paren+1))));
                foreach ( $date_fields as $df ) {
                    $v = trim($values[$df['pos']]);
                    $quote = ( stripos($v, "'0000-00-00 00:00:00'") === 0 || $v === "''" ) ? "'" : '';
                    if ( stripos($v, '0000-00-00 00:00:00') === 0
                        || stripos($v, "'0000-00-00 00:00:00'") === 0
                        || $v === "''" ) {
                        if ( stripos($df['field'], 'gmt') > 0 ) {
                            $v = $quote.gmdate('Y-m-d H:i:s').$quote;
                        } else {
                            $v = $quote.date('Y-m-d H:i:s').$quote;
                        }
                    }
                    $values[$df['pos']] = $v;
                }
                $str = implode(',', $values);
                $query = substr_replace($query, $str, ($first_paren+1), ($last_paren-($first_paren+1)));
            }
        }
        return $query;
    }

    /**
     * The data types text and varchar are incompatible in the equal to operator.
     * TODO: Have a check for the appropriate table of the field to avoid collision
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_incompat_data_type($query)
    {
        // Lets check to make sure this is a SELECT query.
        if ( stripos($query, 'SELECT') === 0 || stripos($query, 'DELETE') === 0 ) {
            $operators = array(
                '='  => 'LIKE',
                '!=' => 'NOT LIKE',
                '<>' => 'NOT LIKE'
            );

            foreach($this->fields_map->read() as $table => $table_fields) {
                if (is_array($table_fields)) {
                    foreach ($table_fields as $field => $field_meta) {
                        if ( $field_meta['type'] == 'text' ) {
                            foreach($operators as $oper => $val) {
                                $query = str_ireplace(
                                    $table . '.' . $field . ' ' . $oper,
                                    $table . '.' . $field . ' ' . $val,
                                    $query
                                );
                                $query = str_ireplace($field . ' ' . $oper, $field . ' ' . $val, $query);
								// check for integers to cast.
								$query = preg_replace('/\s+LIKE\s*(\d+)/i', " {$val} cast($1 as varchar(max))", $query);
                            }
                        }
                    }
                }
            }
        }
        return $query;
    }

    /**
     * General create/alter query translations
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Translated Query
     */
    function translate_create_queries($query)
    {
        if ( stripos($query, 'CREATE') !== 0 && stripos($query, 'ALTER') !== 0 ) {
            return $query;
        }
		// deal with alters in a bit
		if (stripos($query, 'ALTER') === 0) {
			return $query;
		}
		
		// fix enum as it doesn't exist in T-SQL
		if (stripos($query, 'enum(') !== false) {
			$enums = array_reverse($this->stripos_all($query, 'enum('));
			foreach ($enums as $start_pos) {
				$end = $this->get_matching_paren($query, $start_pos + 5);
				// get values inside enum
				$values = substr($query, $start_pos + 5, ($end - ($start_pos + 5)));
				$values = explode(',', $values);
				$all_int = true;
				foreach ($values as $value) {
					$val = trim(str_replace("'", '', $value));
					if (!is_numeric($val) || (int) $val != $val) {
						$all_int = false;
					}
				}
				// if enum of ints create an appropriate int column otherwise create a varchar
				if ($all_int) {
					$query = substr_replace($query, 'smallint', $start_pos, ($end + 1) - $start_pos);
				} else {
					$query = substr_replace($query, 'varchar(255)', $start_pos, ($end + 1) - $start_pos);
				}
			}
		}
		
		// remove IF NOT EXISTS as that doesn't exist in T-SQL
		$query = str_ireplace(' IF NOT EXISTS', '', $query);
	
		//save array to file_maps
		$this->fields_map->update_for($query);

		// change auto increment to indentity
        $start_positions = array_reverse($this->stripos_all($query, 'auto_increment'));
        if( stripos($query, 'auto_increment') > 0 ) {
            foreach ($start_positions as $start_pos) {
                $query = substr_replace($query, 'IDENTITY(1,1)', $start_pos, 14);
            }
        }
        if(stripos($query, 'AFTER') > 0) {
            $start_pos = stripos($query, 'AFTER');
            $query = substr($query, 0, $start_pos);
        }
        // replacement of certain data types and functions
        $fields = array(
            'int (',
            'int(',
            'index (',
            'index(',
        );

        foreach ( $fields as $field ) {
            // reverse so that when we make changes it wont effect the next change.
            $start_positions = array_reverse($this->stripos_all($query, $field));
            foreach ($start_positions as $start_pos) {
                $first_paren = stripos($query, '(', $start_pos);
                $end_pos = $this->get_matching_paren($query, $first_paren + 1) + 1;
                if( $field == 'index(' || $field == 'index (' ) {
                    $query = substr_replace($query, '', $start_pos, $end_pos - $start_pos);
                } else {
                    $query = substr_replace($query, rtrim(rtrim($field,'('), ' '), $start_pos, ($end_pos - $start_pos));
                }
            }
        }

        $query = str_ireplace("'0000-00-00 00:00:00'", 'getdate()', $query);

        // strip unsigned
        $query = str_ireplace("unsigned ", '', $query);

        // strip collation, engine type, etc from end of query
        $pos = stripos($query, '(', stripos($query, 'TABLE '));
        $end = $this->get_matching_paren($query, $pos + 1);
        $query = substr_replace($query, ');', $end);

        $query = str_ireplace("DEFAULT CHARACTER SET utf8", '', $query);
        $query = str_ireplace(" CHARACTER SET utf8", '', $query);
		
		// add collation
		$ac_types = array('tinytext', 'longtext', 'mediumtext', 'varchar');
		foreach ($ac_types as $ac_type) {
			$start_positions = array_reverse($this->stripos_all($query, $ac_type));
			foreach ($start_positions as $start_pos) {
				if ($ac_type == 'varchar') {
					$end = $this->get_matching_paren($query, $start_pos + (strlen($ac_type) + 1));
					$sub = substr($query, $end + 2, 7);
					$end_pos = $end + 1;
				} else {
					$query = substr_replace($query, 'TEXT', $start_pos, strlen($ac_type));
					$sub = substr($query, $start_pos + 5, 7);
					$end_pos = $start_pos + 4;
				}
				if ($sub !== 'COLLATE') {
					$query = $this->add_collation($query, $end_pos);
				}
			}
		}

        $keys = array();
        $table_pos = stripos($query, ' TABLE ') + 6;
        $table = substr($query, $table_pos, stripos($query, '(', $table_pos) - $table_pos);
        $table = trim($table);
		
		$reserved_words = array('public');
		// get column names to check for reserved words to encapsulate with [ ]
		foreach($this->fields_map->read() as $table_name => $table_fields) {
            if ($table_name == $table && is_array($table_fields)) {
                foreach ($table_fields as $field => $field_meta) {
                    if (in_array($field, $reserved_words)) {
						$query = str_ireplace($field, "[{$field}]", $query);
					}
                }
            }
        }

        // get primary key constraints
        if ( stripos($query, 'PRIMARY KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'PRIMARY KEY');
            foreach ($start_positions as $start_pos) {
                $start = stripos($query, '(', $start_pos);
                $end_paren = $this->get_matching_paren($query, $start + 1);
                $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
				foreach ($field as $k => $v) {
					if (stripos($v, '(') !== false) {
						$field[$k] = preg_replace('/\(.*\)/', '', $v);
					}
				}
                $keys[] = array('type' => 'PRIMARY KEY', 'pos' => $start_pos, 'field' => $field);
            }
        }
        // get unique key constraints
        if ( stripos($query, 'UNIQUE KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'UNIQUE KEY');
            foreach ($start_positions as $start_pos) {
                $start = stripos($query, '(', $start_pos);
                $end_paren = $this->get_matching_paren($query, $start + 1);
                $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
				foreach ($field as $k => $v) {
					if (stripos($v, '(') !== false) {
						$field[$k] = preg_replace('/\(.*\)/', '', $v);
					}
				}
                $keys[] = array('type' => 'UNIQUE KEY', 'pos' => $start_pos, 'field' => $field);
            }
        }
        // get key constraints
        if ( stripos($query, 'KEY') > 0) {
            $start_positions = $this->stripos_all($query, 'KEY');
            foreach ($start_positions as $start_pos) {
                if (substr($query, $start_pos - 7, 6) !== 'UNIQUE'
                    && substr($query, $start_pos - 8, 7) !== 'PRIMARY'
                    && (substr($query, $start_pos - 1, 1) == ' ' || substr($query, $start_pos - 1, 1) == "\n")) {
                    $start = stripos($query, '(', $start_pos);
                    $end_paren = $this->get_matching_paren($query, $start + 1);
                    $field = explode(',', substr($query, $start + 1, $end_paren - ($start + 1)));
					foreach ($field as $k => $v) {
						if (stripos($v, '(') !== false) {
							$field[$k] = preg_replace('/\(.*\)/', '', $v);
						}
					}
                    $keys[] = array('type' => 'KEY', 'pos' => $start_pos, 'field' => $field);
                }
            }
        }

        $count = count($keys);
        $add_primary = false;
        $key_str = '';
        $lowest_start_pos = false;
        $unwanted = array(
            'slug',
            'name',
            'term_id',
            'taxonomy',
            'term_taxonomy_id',
            'comment_approved',
            'comment_post_ID',
            'comment_approved',
            'link_visible',
            'post_id',
            'meta_key',
            'post_type',
            'post_status',
            'post_date',
            'ID',
            'post_name',
            'post_parent',
            'user_login',
            'user_nicename',
            'user_id',
        );
        for ($i = 0; $i < $count; $i++) {
            if ($keys[$i]['pos'] < $lowest_start_pos || $lowest_start_pos === false) {
                $lowest_start_pos = $keys[$i]['pos'];
            }
            if ($keys[$i]['type'] == 'PRIMARY KEY') {
                $add_primary = true;
            }
            switch ($keys[$i]['type']) {
                case 'PRIMARY KEY':
					$str = "CONSTRAINT [" . $table . "_" . implode('_', $keys[$i]['field']) . "] PRIMARY KEY CLUSTERED (" . implode(',', $keys[$i]['field']) . ") WITH (IGNORE_DUP_KEY = OFF)";
					if (!$this->azure ) {
						$str .= " ON [PRIMARY]";
					}
                break;
                case 'UNIQUE KEY':
                    $check = true;
                    foreach ($keys[$i]['field'] as $field) {
                        if (in_array($field, $unwanted)) {
                            $check = false;
                        }
                    }
                    if ($check) {
						if ($this->azure) {
							$str = 'CONSTRAINT [' . $table . '_' . implode('_', $keys[$i]['field']) . '] UNIQUE NONCLUSTERED (' . implode(',', $keys[$i]['field']) . ')';
						} else {
							$str = 'CONSTRAINT [' . $table . '_' . implode('_', $keys[$i]['field']) . '] UNIQUE NONCLUSTERED (' . implode(',', $keys[$i]['field']) . ')';
						}
					} else {
                        $str = '';
                    }
                break;
				case 'KEY':
					// CREATE NONCLUSTERED INDEX index_name ON table(col1,col2)
					$check = true;
					$str = '';
                    foreach ($keys[$i]['field'] as $field) {
                        if (in_array($field, $unwanted)) {
                            $check = false;
                        }
                    }
                    if ($check) {
						if (!is_array($this->following_query) && $this->following_query === false) {
							$this->following_query = array();
						} elseif (!is_array($this->following_query)) {
							$this->following_query = array($this->following_query);
						}
						if ($this->azure) {
							$this->following_query[] = 'CREATE CLUSTERED INDEX ' . 
							$table . '_' . implode('_', $keys[$i]['field']) . 
							' ON '.$table.'('.implode(',', $keys[$i]['field']).')';
						} else {
							$this->following_query[] = 'CREATE NONCLUSTERED INDEX ' . 
							$table . '_' . implode('_', $keys[$i]['field']) . 
							' ON '.$table.'('.implode(',', $keys[$i]['field']).')';
						}
                    }
				break;
            }
            if ($i !== $count - 1 && $str !== '') {
                $str .= ',';
            }
            $key_str .= $str . "\n";
        }
        if ($key_str !== '') {
            if ($add_primary && !$this->azure) {
                $query = substr_replace($query, $key_str . ") ON [PRIMARY];", $lowest_start_pos);
            } else {
                $query = substr_replace($query, $key_str . ");", $lowest_start_pos);
            }
        }

        return $query;
    }

    /**
     * Given a first parenthesis ( ...will find its matching closing paren )
     *
     * @since 2.7.1
     *
     * @param string $str given string
     * @param int $start_pos position of where desired starting paren begins+1
     *
     * @return int position of matching ending parenthesis
     */
    function get_matching_paren($str, $start_pos)
    {
        $count = strlen($str);
        $bracket = 1;
        for ( $i = $start_pos; $i < $count; $i++ ) {
            if ( $str[$i] == '(' ) {
                $bracket++;
            } elseif ( $str[$i] == ')' ) {
                $bracket--;
            }
            if ( $bracket == 0 ) {
                return $i;
            }
        }
    }

    /**
     * Get the Aliases in a query
     * E.G. Field1 AS yyear, Field2 AS mmonth
     * will return array with yyear and mmonth
     *
     * @since 2.7.1
     *
     * @param string $str a query
     *
     * @return array array of aliases in a query
     */
    function get_as_fields($query)
    {
        $arr = array();
        $tok = preg_split('/[\s,]+/', $query);
        $count = count($tok);
        for ( $i = 0; $i < $count; $i++ ) {
            if ( strtolower($tok[$i]) === 'as' ) {
                $arr[] = $tok[($i + 1)];
            }
        }
        return $arr;
    }

    /**
    * Fix for SQL Server returning null values with one space.
    * Fix for SQL Server returning datetime fields with milliseconds.
    * Fix for SQL Server returning integer fields as integer (mysql returns as string)
    *
    * @since 2.7.1
    *
    * @param array $result_set result set array of an executed query
    *
    * @return array result set array with modified fields
    */
    function fix_results($result_set)
    {
        // If empty bail early.
        if ( is_null($result_set)) {
            return false;
        }
        if (is_array($result_set) && empty($result_set)) {
            return array();
        }
        $map_fields = $this->fields_map->by_type('date');
        $fields = array_keys(get_object_vars(current($result_set)));
        foreach ( $result_set as $key => $result ) {
            // Remove milliseconds
            foreach ( $map_fields as $date_field ) {
                if ( isset($result->$date_field) ) {
                    // date_format is a PHP5 function. sqlsrv is only PHP5 compat
                    // the result set for datetime columns is a PHP DateTime object, to extract
                    // the string we need to use date_format().
					if (is_object($result->$date_field)) {
						$result_set[$key]->$date_field = date_format($result->$date_field, 'Y-m-d H:i:s');
					}
                }
            }
            // Check for null values being returned as space and change integers to strings (to mimic mysql results)
            foreach ( $fields as $field ) {
                if ($field == 'crdate' || $field == 'refdate') {
                    $result_set[$key]->$field = date_format($result->$field, 'Y-m-d H:i:s');
                }
                if ( $result->$field === ' ' ) {
                    $result->$field = '';
                }
                if ( is_int($result->$field) ) {
                    $result->$field = (string) $result->$field;
                }
            }
        }
        return $result_set;
    }
	
	/**
	 * Check to see if INSERT has an ON DUPLICATE KEY statement
	 * This is MySQL specific and will be removed and put into 
	 * a following_query UPDATE STATEMENT
	 *
	 * @param string $query Query coming in
	 * @return string query without ON DUPLICATE KEY statement
	 */
	 function on_duplicate_key($query)
	 {
		if ( stripos($query, 'ON DUPLICATE KEY UPDATE') > 0 ) {
			$table = substr($query, 12, (strpos($query, ' ', 12) - 12));
			// currently just deal with wp_options table
			if (stristr($table, 'options') !== FALSE) {
				$start_pos = stripos($query, 'ON DUPLICATE KEY UPDATE');
				$query = substr_replace($query, '', $start_pos);
				$values_pos = stripos($query, 'VALUES');
				$first_paren = stripos($query, '(', $values_pos);
				$last_paren = $this->get_matching_paren($query, $first_paren + 1);
				$values = explode(',', substr($query, ($first_paren + 1), ($last_paren-($first_paren + 1))));
				// change this to use mapped fields
				$update = 'UPDATE ' . $table . ' SET option_value = ' . $values[1] . ', autoload = ' . $values[2] . 
					' WHERE option_name = ' . $values[0];
				$this->following_query = $update;
			}
        }
		return $query;
	 }

    /**
     * Check to see if an INSERT query has multiple VALUES blocks. If so we need create
     * seperate queries for each.
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return array array of insert queries
     */
    function split_insert_values($query)
    {
        $arr = array();
        if (stripos($query, 'INSERT') === 0) {
            $first = substr($query, 0, (stripos($query, 'VALUES') + 7));
            $values = substr($query, (stripos($query, 'VALUES') + 7));
            $arr = preg_split('/\),\s+\(/', $values);
            foreach ($arr as $k => $v) {
				if (substr($v, -1) !== ')') {
					$v = $v . ')';
				}
				
				if (substr($v, 0, 1) !== '(') {
					$v = '(' . $v;
				}
				
                $arr[$k] = $first . $v;
            }
        }
        if (count($arr) < 2) {
            return $query;
        }
        return $arr;
    }

    /**
     * Check query to make sure translations weren't made to INSERT query values
     * If so replace translation with original data.
     * E.G. INSERT INTO wp_posts (wp_title) VALUES ('SELECT * FROM wp_posts LIMIT 1');
     * The translations may change the value data to SELECT TOP 1 FROM wp_posts...in this case
     * we don't want that to happen.
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Verified Query
     */
    function verify_insert($query)
    {
        $values_pos = stripos($query, 'VALUES');
        $first_paren = stripos($query, '(', $values_pos);
        $last_paren = $this->get_matching_paren($query, $first_paren + 1);
        $values = explode(',', substr($query, ($first_paren + 1), ($last_paren-($first_paren + 1))));
        if ( count($this->prepare_args) !== count($values) ) {
            return $query;
        }
        $i = 0;
        foreach ( $values as $k => $value ) {
            $value = trim($value);
            foreach ($this->prepare_args as $r => $arg) {
                if ( $k == $i && $arg !== $value ) {
                    if ( $arg !== '' && $arg !== '0000-00-00 00:00:00' ) {
                        $values[$k] = "'" . $arg . "'";
                    }
                }
                $i++;
            }
        }
        $str = implode(',', $values);
        $query = substr_replace($query, $str, ($first_paren + 1), ($last_paren - ($first_paren + 1)));
        return $query;
    }

    /**
     * Check query to make sure translations weren't made to UPDATE query values
     * If so replace translation with original data.
     * E.G. UPDATE wp_posts SET post_title = 'SELECT * FROM wp_posts LIMIT 1' WHERE post_id = 1;
     * The translations may change the value data to SELECT TOP 1 FROM wp_posts...in this case
     * we don't want that to happen
     *
     * @since 2.7.1
     *
     * @param string $query Query coming in
     *
     * @return string Verified Query
     */
    function verify_update($query)
    {
		if(empty($this->update_data)) {
			return $query;
		}
        $values = array();
        $keys = array_keys($this->update_data);

        $start = stripos($query, 'SET') + 3;
        $end = strripos($query, 'WHERE');
        $sub = substr($query, $start, $end - $start);
        $arr = explode(', ', $sub);
        foreach ( $arr as $k => $v ) {
            $v = trim($v);
            $st = stripos($v, ' =');
            $sv = substr($v, 0, $st);
            if ( in_array($sv, $keys) ) {
                $sp = substr($v, $st + 4, -1);
                $values[] = str_replace("'", "''", $sp);
            }
        }
        $update_data = array_values($this->update_data);
        if ( count($update_data) == count($values) ) {
            foreach ( $update_data as $key => $val ) {
                if ( $update_data[$key] !== $values[$key] ) {
                    $values[$key] = str_replace("''", "'", $update_data[$key]);
                }
            }

            foreach ( $values as $y => $vt ) {
                $values[$y] = $keys[$y] . " = '" . $vt . "'";
            }
            $str = implode(', ', $values) . ' ';
            $query = substr_replace($query, $str, ($start+1), ($end-($start+1)));
        }
        return $query;
    }

    /**
     * Add collation for a field definition within a CREATE/ALTER query
     *
     * @since 2.8
     * @param $type
     *
     * @return string
     */
    function add_collation($query, $pos)
    {
        $query = substr_replace($query, ' COLLATE Latin1_General_BIN', $pos, 0);
        return $query;
    }
	
    /**
     * Describe wrapper
     *
     * @since 2.8.5
     * @param $table
     *
     * @return string
     */
	function describe($table)
	{
		$sql = "SELECT
			c.name AS Field
			,t.name + t.length_string AS Type
			,CASE c.is_nullable WHEN 1 THEN 'YES' ELSE 'NO' END AS [Null]
			,CASE
				WHEN EXISTS (SELECT * FROM sys.key_constraints AS kc
							   INNER JOIN sys.index_columns AS ic ON kc.unique_index_id = ic.index_id AND kc.parent_object_id = ic.object_id
							   WHERE kc.type = 'PK' AND ic.column_id = c.column_id AND c.object_id = ic.object_id)
							   THEN 'PRI'
				WHEN EXISTS (SELECT * FROM sys.key_constraints AS kc
							   INNER JOIN sys.index_columns AS ic ON kc.unique_index_id = ic.index_id AND kc.parent_object_id = ic.object_id
							   WHERE kc.type <> 'PK' AND ic.column_id = c.column_id AND c.object_id = ic.object_id)
							   THEN 'UNI'
				ELSE ''
			END AS [Key]
			,ISNULL((
				SELECT TOP(1)
					dc.definition
				FROM sys.default_constraints AS dc
				WHERE dc.parent_column_id = c.column_id AND c.object_id = dc.parent_object_id)
			,'') AS [Default]
			,CASE
				WHEN EXISTS (
					SELECT
						*
					FROM sys.identity_columns AS ic
					WHERE ic.column_id = c.column_id AND c.object_id = ic.object_id)
						THEN 'auto_increment'
				ELSE ''
			END AS Extra
		FROM sys.columns AS c
		CROSS APPLY (
			SELECT
				t.name AS n1
				,CASE
					-- Types with length
					WHEN c.max_length > 0 AND t.name IN ('varchar', 'char', 'varbinary', 'binary') THEN '(' + CAST(c.max_length AS VARCHAR) + ')'
					WHEN c.max_length > 0 AND t.name IN ('nvarchar', 'nchar') THEN '(' + CAST(c.max_length/2 AS VARCHAR) + ')'
					WHEN c.max_length < 0 AND t.name IN ('nvarchar', 'varchar', 'varbinary') THEN '(max)'
					-- Types with precision & scale
					WHEN t.name IN ('decimal', 'numeric') THEN '(' + CAST(c.precision AS VARCHAR) + ',' + CAST(c.scale AS VARCHAR) + ')'
					-- Types with only precision
					WHEN t.name IN ('float') THEN '(' + CAST(c.precision AS VARCHAR) + ')'
					-- Types with only scale
					WHEN t.name IN ('datetime2', 'time', 'datetimeoffset') THEN '(' + CAST(c.scale AS VARCHAR) + ')'
					-- The rest take no arguments
					ELSE ''
				END AS length_string
				,*
			FROM sys.types AS t
			WHERE t.system_type_id = c.system_type_id AND t.system_type_id = t.user_type_id
		) AS t
		WHERE object_id = OBJECT_ID('{$table}');";
		return $sql;
	}

    /**
     * Get all occurrences(positions) of a string within a string
     *
     * @since 2.8
     * @param $type
     *
     * @return array
     */
    function stripos_all($haystack, $needle, $offset = 0)
    {
		$arr = array();
		while ($offset !== false) {
			$pos = stripos($haystack, $needle, $offset);
			if ($pos !== false) {
				$arr[] = $pos;
				$pos = $pos + strlen($needle);
			}
			$offset = $pos;
		}
        return $arr;
    }
}

if ( !function_exists('str_ireplace') ) {
    /**
     * PHP 4 Compatible str_ireplace function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $search what needs to be replaced
     * @param string $replace replacing value
     * @param string $subject string to perform replace on
     *
     * @return string the string with replacements
     */
    function str_ireplace($search, $replace, $subject)
    {
        $token = chr(1);
        $haystack = strtolower($subject);
        $needle = strtolower($search);
        while ( $pos = strpos($haystack, $needle) !== FALSE ) {
            $subject = substr_replace($subject, $token, $pos, strlen($search));
            $haystack = substr_replace($haystack, $token, $pos, strlen($search));
        }
        return str_replace($token, $replace, $subject);
    }
}

if ( !function_exists('stripos') ) {
    /**
     * PHP 4 Compatible stripos function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $str the string to search in
     * @param string $needle what we are looking for
     * @param int $offset starting position
     *
     * @return int position of needle if found. FALSE if not found.
     */
    function stripos($str, $needle, $offset = 0)
    {
        return strpos(strtolower($str), strtolower($needle), $offset);
    }
}

if ( !function_exists('strripos') ) {
    /**
     * PHP 4 Compatible strripos function
     * found in php.net comments
     *
     * @since 2.7.1
     *
     * @param string $haystack the string to search in
     * @param string $needle what we are looking for
     *
     * @return int position of needle if found. FALSE if not found.
     */
    function strripos($haystack, $needle, $offset=0)
    {
        if ( !is_string($needle) ) {
            $needle = chr(intval($needle));
        }
        if ( $offset < 0 ) {
            $temp_cut = strrev(substr($haystack, 0, abs($offset)));
        } else{
            $temp_cut = strrev(substr($haystack, 0, max((strlen($haystack) - $offset ), 0)));
        }
        if ( stripos($temp_cut, strrev($needle)) === false ) {
            return false;
        } else {
            $found = stripos($temp_cut, strrev($needle));
        }
        $pos = (strlen($haystack) - ($found + $offset + strlen($needle)));
        return $pos;
    }
}
