<?php
/**
 * SQLite 3 (binary) export code
 *
 * @package PhpMyAdmin-Export
 * @subpackage SQLite 3
 * @version 0.2-alpha
 */

if (!class_exists('SQLite3'))
	return;

require_once('sqlite3-common.inc.php');

/**
 * Set of functions used to build CSV dumps of tables
 */

if (isset($plugin_list)) {
    $plugin_list['sqlite3-binary'] = array(
        'text' => __('SQLite 3 (Binary)'),
        'extension' => 'sqlite',
        'mime_type' => 'application/x-sqlite3',
    	'force_file' => true,
        'options' => array(
            array('type' => 'begin_group', 'name' => 'general_opts'),
            array(
                'type' => 'radio',
                'name' => 'structure_or_data',
                'values' => array(
                    'structure' => __('structure'),
                    'structure_and_data' => __('structure and data')
                )
            ),
        	array('type' => 'end_group')
        ),
        'options_text' => __('Options')
    );
} else {
	
	/**
     * Outputs export footer
     *
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
	function PMA_exportFooter() {
		global $sqlite_db, $sqlite_tmp_filename;
    	if (!$sqlite_db->close())
    		return false;
    	return PMA_exportOutputHandler(file_get_contents($sqlite_tmp_filename));
    }
    
    /**
     * Outputs export header
     *
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
	function PMA_exportHeader() {
        global $sqlite_db, $sqlite_tmp_filename;

        $sqlite_tmp_filename = tempnam(sys_get_temp_dir(), 'sql');
        if ($sqlite_tmp_filename === false)
        	return false;
        
        try {
        	$sqlite_db = new SQLite3($sqlite_tmp_filename);
        	return true;
        }
        catch (Exception $e) {
        	return false;
        }
    }
    
    /**
     * Outputs table's structure
     *
     * @param string  $db           database name
     * @param string  $table        table name
     * @param string  $crlf         the end of line sequence
     * @param string  $error_url    the url to go back in case of error
     * @param bool    $relation     whether to include relation comments
     * @param bool    $comments     whether to include the pmadb-style column comments
     *                                as comments in the structure; this is deprecated
     *                                but the parameter is left here because export.php
     *                                calls PMA_exportStructure() also for other export
     *                                types which use this parameter
     * @param bool    $mime         whether to include mime comments
     * @param bool    $dates        whether to include creation/update/check dates
     * @param string  $export_mode  'create_table', 'triggers', 'create_view', 'stand_in'
     * @param string  $export_type  'server', 'database', 'table'
     * @return  bool      Whether it succeeded
     *
     * @access  public
     */
	function PMA_exportStructure($db, $table, $crlf, $error_url, $relation = false, $comments = false, $mime = false, $dates = false, $export_mode, $export_type)
    {
    	if ($export_mode == 'create_table')
        {
        	global $sqlite_db;
        	$schema = PMA_getTableDef($db, $table, $crlf, $error_url, true, true);
        	if ($schema === false)
        		return false;
        	$queries = explode(';', $schema);
        	foreach ($queries as $query)
        	{
        		if (empty($query))
        			continue;
        		if (!$sqlite_db->exec($query))
        			return false;
        	}
        	return true;
        }
        else
            return true;
    }
    
    /**
     * Outputs the content of a table
     *
     * @param string  $db         database name
     * @param string  $table      table name
     * @param string  $crlf       the end of line sequence
     * @param string  $error_url  the url to go back in case of error
     * @param string  $sql_query  SQL query for obtaining data
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
	function PMA_exportData($db, $table, $crlf, $error_url, $sql_query) {
		global $sqlite_db;
    	
    	// Gets the data from the database
        $result      = PMA_DBI_query($sql_query, null, PMA_DBI_QUERY_UNBUFFERED);
        $fields_cnt  = PMA_DBI_num_fields($result);

        // Gets fields names
        $fields = array();
        for ($i = 0; $i < $fields_cnt; $i++)
            $fields[] = stripslashes(PMA_DBI_field_name($result, $i));
        $fields = implode(',', $fields);

        // Format the data
        while ($row = PMA_DBI_fetch_row($result)) {
            $values = array();
            for ($i = 0; $i < $fields_cnt; $i++) {
                if (is_null($row[$i]))
                    $values[] = 'NULL';
                else
                    $values[] = sprintf("'%s'", str_replace("'", "''", $row[$i]));
            }

            $schema_insert = sprintf("INSERT INTO %s (%s) VALUES (%s);", $table, $fields, implode(',', $values));
            if (!$sqlite_db->query($schema_insert))
            	return false;
        } // end while
        PMA_DBI_free_result($result);

        return true;
    }
    
    /**
     * Possibly outputs comment
     *
     * @param string  $text  Text of comment
     * @return  string      The formatted comment
     *
     * @access  private
     */
    function PMA_exportComment($text = '')
    {
    	return '';
    }
    
    /**
     * Outputs database header
     *
     * @param string  $db Database name
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportDBHeader($db) {
    	return true;
    }
    
    /**
     * Outputs database footer
     *
     * @param string  $db Database name
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportDBFooter($db) {
    	return true;
    }
    
    /**
     * Outputs CREATE DATABASE statement
     *
     * @param string  $db Database name
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportDBCreate($db) {
    	return true;
    }
}
?>