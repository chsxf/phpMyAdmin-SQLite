<?php
/**
 * SQLite 3 export code
 *
 * @package PhpMyAdmin-Export
 * @subpackage SQLite 3
 * @version 0.2-alpha
 */

require_once('sqlite3-common.inc.php');

/**
 * Set of functions used to build CSV dumps of tables
 */

if (isset($plugin_list)) {
    $plugin_list['sqlite3'] = array(
        'text' => __('SQLite 3'),
        'extension' => 'sqlite',
        'mime_type' => 'text/x-sql',
        'options' => array(
            array('type' => 'begin_group', 'name' => 'general_opts'),
            array(
                'type' => 'radio',
                'name' => 'structure_or_data',
                'values' => array(
                    'structure' => __('structure'),
                    'data' => __('data'),
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
        return true;
    }

    /**
     * Outputs export header
     *
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportHeader() {
        global $crlf, $cfg;

        $head  = PMA_exportComment(str_repeat('-', 56));
        $head .= PMA_exportComment('phpMyAdmin SQLite 3 Dump');
        $head .= PMA_exportComment('Version ' . PMA_VERSION);
        $head .= PMA_exportComment('http://www.phpmyadmin.net');
        $head .= PMA_exportComment();

        $host_string = __('Host') . ': ' .  $cfg['Server']['host'];
        if (!empty($cfg['Server']['port'])) {
            $host_string .= ':' . $cfg['Server']['port'];
        }
        $head .= PMA_exportComment($host_string);

        $head .= PMA_exportComment(__('Generation Time').': '.PMA_localisedDate());
        $head .= PMA_exportComment(__('Server version') . ': ' . PMA_MYSQL_STR_VERSION);
        $head .= PMA_exportComment(__('PHP Version') . ': ' . phpversion());
        $head .= PMA_exportComment(sprintf('Exporter Version: %s', X_PMA_SQLITE3_EXPORT_VERSION));
        $head .= PMA_exportComment(str_repeat('-', 56));
        $head .= $crlf;

        return PMA_exportOutputHandler($head);
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
    	return '#' . (empty($text) ? '' : ' ') . $text . $GLOBALS['crlf'];
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
            $schema = PMA_getTableDef($db, $table, $crlf, $error_url);
            return ($schema === false) ? false : PMA_exportOutputHandler($schema);
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
        $comment = $crlf;
        $comment .= PMA_exportComment(str_repeat('-', 56));
        $comment .= PMA_exportComment(sprintf("%s %s.%s", __('Dumping data for table'), PMA_backquote($db), PMA_backquote($table)));
        $comment .= PMA_exportComment(str_repeat('-', 56));
        if (!PMA_exportOutputHandler($comment))
            return false;

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
            if (!PMA_exportOutputHandler($schema_insert . $crlf))
                return false;
        } // end while
        PMA_DBI_free_result($result);

        return true;
    }
}
?>