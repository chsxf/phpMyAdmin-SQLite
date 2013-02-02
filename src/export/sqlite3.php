<?php
/**
 * SQLite 3 export code
 *
 * @package PhpMyAdmin-Export
 * @subpackage SQLite 3
 * @version 0.1-alpha
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

define('X_PMA_SQLITE3_EXPORT_VERSION', '0.1-alpha');

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
            )
        ),
        'options_text' => __('Options'),
        );
} else {

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
     * Returns $table's CREATE definition
     *
     * @param string  $db             the database name
     * @param string  $table          the table name
     * @param string  $crlf           the end of line sequence
     * @param string  $error_url      the url to go back in case of error
     * @param bool    $show_dates     whether to include creation/update/check dates
     * @param bool    $add_semicolon  whether to add semicolon and end-of-line at the end
     * @param bool    $view           whether we're handling a view
     * @return  string   resulting schema
     *
     * @access  public
     */
    function PMA_getTableDef($db, $table, $crlf, $error_url, $show_dates = false, $add_semicolon = true, $view = false)
    {
        $schema_create = $crlf;

        // Table name
        $schema_create .= PMA_exportComment(str_repeat('-', 56));
        $schema_create .= PMA_exportComment(sprintf("%s %s.%s", __('Table structure for table'), PMA_backquote($db), PMA_backquote($table)));
        
        // need to use PMA_DBI_QUERY_STORE with PMA_DBI_num_rows() in mysqli
        $result = PMA_DBI_query('SHOW TABLE STATUS FROM ' . PMA_backquote($db) . ' LIKE \'' . PMA_sqlAddSlashes($table, true) . '\'', null, PMA_DBI_QUERY_STORE);
        if ($result !== false || PMA_DBI_num_rows($result) <= 0) {
            $tmpres = PMA_DBI_fetch_assoc($result);

            if ($show_dates && !empty($tmpres['Create_time']))
                $schema_create .= PMA_exportComment(__('Creation') . ': ' . PMA_localisedDate(strtotime($tmpres['Create_time'])));

            if ($show_dates && !empty($tmpres['Update_time']))
                $schema_create .= PMA_exportComment(__('Last update') . ': ' . PMA_localisedDate(strtotime($tmpres['Update_time'])));

            if ($show_dates && !empty($tmpres['Check_time']))
                $schema_create .= PMA_exportComment(__('Last check') . ': ' . PMA_localisedDate(strtotime($tmpres['Check_time'])));
        }
        else
            return false;
        $schema_create .= PMA_exportComment(str_repeat('-', 56));

        // no need to generate a DROP VIEW here, it was done earlier
        if (!PMA_Table::isView($db, $table))
            $schema_create .= 'DROP TABLE IF EXISTS ' . PMA_backquote($table, false) . ';' . $crlf;

        // generating table structure
        $result = PMA_DBI_query(sprintf('DESCRIBE %s.%s', PMA_backquote($db), PMA_backquote($table)), null, PMA_DBI_QUERY_STORE);
        if ($result !== false)
        {
            $schema_create .= sprintf("CREATE TABLE %s (%s", PMA_backquote($table, false), $crlf);
            $primaryFound = false;
            $fields = array();
            while ($tmpres = PMA_DBI_fetch_assoc($result)) {
                if (!$primaryFound && $tmpres['Key'] == 'PRI')
                {
                    $primaryFound = true;
                    $pk = ' PRIMARY KEY';
                }
                else
                    $pk = '';
                $null = ($tmpres['Null'] == 'NO') ? ' NOT NULL' : '';
                $type = PMA_SQLite_mapTypeFromSQL($tmpres['Type']);
                $default_value = '';

                $fields[] = sprintf("\t%s %s%s%s%s", $tmpres['Field'], $type, $null, $pk, $default_value);
            }
            $schema_create .= implode(",{$crlf}", $fields) . "{$crlf});{$crlf}";
        }
        else
            return false;

        // Generating indices
        $result = PMA_DBI_query(sprintf("SHOW CREATE TABLE %s.%s", PMA_backquote($db), PMA_backquote($table)), null, PMA_DBI_QUERY_STORE);
        if ($result !== false)
        {
            $show_create = PMA_DBI_fetch_row($result);
            preg_match('/^[^(]+\((.+)\)[^)]*$/ms', $show_create[1], $regs);
            $lines = explode("\n", $regs[1]);
            foreach ($lines as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^(UNIQUE )?KEY `(.+)` \((.+)\)$/', $line, $regs))
                {
                    $fields = explode(',', $regs[3]);
                    foreach ($fields as &$field)
                        $field = trim($field, '`');
                    $schema_create .= sprintf("CREATE %sINDEX %s ON %s(%s);%s", $regs[1], $table.'_'.$regs[2], $table, implode(',', $fields), $crlf);
                }
            }    
        }
        else
            return false;

        return $schema_create;
    }

    function PMA_SQLite_mapTypeFromSQL($type) {
        if (preg_match('/(tiny|small|medium|big)?int(eger)?(\(\d+\))?/i', $type, $regs))
            return strtoupper(sprintf("%sint%s", $regs[1], $regs[3]));
        else if (preg_match("/(var)?(binary|char)(\(\d+\))?/i", $type, $regs))
            return strtoupper("%sCHAR%s", $regs[1], $regs[3]);
        else if (in_array(strtolower($type), array('date', 'datetime', 'timestamp', 'time', 'year')))
            return strtoupper($type);
        else if (preg_match('/bool(ean)?/i', $type))
        	return 'BOOLEAN';
        else if (preg_match('/bit\((\d+)\)/i', $type, $regs))
        {
        	if ($regs[1] <= 8)
        		return 'TINYINT';
        	else if ($regs[1] <= 16)
        		return 'SMALLINT';
        	else if ($regs[1] <= 24)
        		return 'MEDIUMINT';
        	else if ($regs[1] <= 32)
        		return 'INT';
        	else
        		return 'BIGINT';
        }
        else if (preg_match('/decimal(\(\d+(,\d+)?\))?/i', $type, $regs))
        	return strtoupper($regs[0]);
        else if (preg_match('/float(\(\d+(,\d+)?\))?/i', $type, $regs))
        	return strtoupper($regs[1]);
        else if (preg_match('/(double( precision)?)(\(\d+,\d+\))?/i', $type, $regs))
        	return strtoupper($regs[1]);
        else if (preg_match('/date(time)?/i', $type, $regs))
        	return strtoupper($regs[0]);
        else if (preg_match('/timestamp/i', $type, $regs))
        	return strtoupper($regs[0]);
        else if (preg_match('/(time|year)/i', $type, $regs))
        	return 'INT';
        else if (preg_match('/(var)?(binary|char)(\(\d+\))?/i', $type, $regs))
        	return strtoupper("%sCHAR%s", $regs[1], $regs[3]);
        else if (preg_match('/(tiny|medium|long)?text/i', $type) || preg_match('/(enum|set)/i', $type))
        	return 'TEXT';
        else if (preg_match('/(tiny|medium|long)?blob/i', $type))
        	return 'BLOB';
        	
        return '';
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
            $schema = PMA_getTableDef($db, $table, $crlf, $error_url, true, true);
            return ($schema === false) ? false : PMA_exportOutputHandler($schema);
        }
        else
            return true;
    }

    /**
     * Outputs the content of a table in CSV format
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
