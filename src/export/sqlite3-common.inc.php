<?php
/**
 * SQLite 3 common code
 *
 * @package PhpMyAdmin-Export
 * @subpackage SQLite 3
 * @version 0.2-alpha
 */

if (! defined('PHPMYADMIN')) {
	exit;
}

if (!defined('X_PMA_SQLITE3_EXPORT_VERSION'))
	define('X_PMA_SQLITE3_EXPORT_VERSION', '0.2-alpha');

if (!function_exists('PMA_SQLite3_mapTypeFromSQL'))
{
	/**
	 * Maps a native MySQL type to the corresponding or nearest SQLite type
	 * @param string $type Type to match
	 * @return string the SQLite type to use
	 */
	function PMA_SQLite3_mapTypeFromSQL($type) {
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
}

if (!function_exists('PMA_getTableDef'))
{
	/**
	 * Returns $table's CREATE definition
	 *
	 * @param string  $db             the database name
	 * @param string  $table          the table name
	 * @param string  $crlf           the end of line sequence
	 * @param string  $error_url      the url to go back in case of error
	 * @return  string   resulting schema
	 *
	 * @access  public
	 */
	function PMA_getTableDef($db, $table, $crlf, $error_url)
	{
		$schema_create = $crlf;
	
		// Table name
		$schema_create .= PMA_exportComment(str_repeat('-', 56));
		$schema_create .= PMA_exportComment(sprintf("%s %s.%s", __('Table structure for table'), PMA_backquote($db), PMA_backquote($table)));
	
		// need to use PMA_DBI_QUERY_STORE with PMA_DBI_num_rows() in mysqli
		$result = PMA_DBI_query('SHOW TABLE STATUS FROM ' . PMA_backquote($db) . ' LIKE \'' . PMA_sqlAddSlashes($table, true) . '\'', null, PMA_DBI_QUERY_STORE);
		if ($result !== false || PMA_DBI_num_rows($result) <= 0) {
			$tmpres = PMA_DBI_fetch_assoc($result);
	
			if (!empty($tmpres['Create_time']))
				$schema_create .= PMA_exportComment(__('Creation') . ': ' . PMA_localisedDate(strtotime($tmpres['Create_time'])));
	
			if (!empty($tmpres['Update_time']))
				$schema_create .= PMA_exportComment(__('Last update') . ': ' . PMA_localisedDate(strtotime($tmpres['Update_time'])));
	
			if (!empty($tmpres['Check_time']))
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
			$fields = array();
			$pkFields = array();
			while ($tmpres = PMA_DBI_fetch_assoc($result)) {
				if ($tmpres['Key'] == 'PRI')
					$pkFields[] = $tmpres['Field'];
				$null = ($tmpres['Null'] == 'NO') ? ' NOT NULL' : '';
				$type = PMA_SQLite3_mapTypeFromSQL($tmpres['Type']);
				$default_value = '';
	
				$fields[] = sprintf("\t%s %s%s%s", $tmpres['Field'], $type, $null, $default_value);
			}
			if (!empty($pkFields))
				$fields[] = sprintf("\tPRIMARY KEY (%s)", implode(', ', $pkFields));
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
}