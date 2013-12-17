<?php
//##copyright##

class iaDbControl extends abstractCore
{


	/**
	 * Returns array of tables
	 *
	 * @return array|bool
	 */
	public function getTables()
	{
		$result = false;

		$query = $this->iaDb->query('SHOW TABLES');
		if ($this->iaDb->getNumRows($query) > 0)
		{
			$result = array();
			$function = INTELLI_CONNECT . '_fetch_row';
			while ($row = $function($query))
			{
				if (0 === strpos($row[0], $this->iaDb->prefix))
				{
					$result[] = $row[0];
				}
			}
		}

		return $result;
	}

	/**
	 * Truncate table
	 * @param string $table - without prefix
	 * @return bool
	 */
	public function truncate($table)
	{
		if (empty($table))
		{
			return false;
		}

		$sql = sprintf('TRUNCATE TABLE `%s`', $this->iaDb->prefix . $table);

		return $this->iaDb->query($sql);
	}

	/**
	 * Splits MySQL dump file into queries and executes each one separately
	 *
	 * @param string $file filename path
	 * @param string $delimiter delimiter for queries
	 *
	 * @return bool
	 */
	public function splitSQL($file, $delimiter = ';')
	{
		set_time_limit(0);

		if (is_file($file))
		{
			$file = fopen($file, 'r');

			if (is_resource($file))
			{
				$query = array();

				while (!feof($file))
				{
					$query[] = fgets($file);

					if (preg_match('#' . preg_quote($delimiter, '~') . '\s*$#iS', end($query)) === 1)
					{
						$query = trim(implode('', $query));
						$query = str_replace(
							array('{prefix}', '{mysql_version}'),
							array($this->iaDb->prefix, $this->iaDb->ver_data),
							$query
						);

						$this->iaDb->query($query);
					}

					if (is_string($query))
					{
						$query = array();
					}
				}
			}
		}
	}

	/**
	 * Return structure sql dump
	 *
	 * @param string $tableName table name
	 * @param bool $aDrop if true use DROP TABLE
	 * @param bool $prefix if true use prefix
	 * @return string
	 */
	public function makeStructureBackup($tableName, $aDrop = false, $prefix = true)
	{
		$tableNameReplacement = $prefix ? $tableName : str_replace($this->iaDb->prefix, '{prefix}', $tableName);

		$fields = $this->iaDb->describe($tableName, false);

		$output = '';
		$output .= $aDrop ? "DROP TABLE IF EXISTS `$tableNameReplacement`;" . PHP_EOL : '';
		$output .= "CREATE TABLE `$tableNameReplacement` (" . PHP_EOL;

		// compose table's structure
		foreach ($fields as $value)
		{
			$output .= "	`{$value['Field']}` {$value['Type']}";
			if ($value['Null'] != 'YES')
			{
				$output .= ' NOT NULL';
			}
			if ($value['Default'])
			{
				$output .= is_numeric($value['Default'])
					? ' default ' . $value['Default']
					: " default '" . $value['Default'] . "'";
			}
			if ($value['Extra'])
			{
				$output .= " {$value['Extra']}";
			}
			$output .= ',' . PHP_EOL;
		}

		// compose table's indices
		if ($indices = $this->iaDb->getAll('SHOW INDEXES FROM ' . $tableName))
		{
			$compositeIndices = array();

			// assemble composite indices for further usage
			foreach ($indices as $key => $index)
			{
				isset($compositeIndices[$index['Key_name']]) || $compositeIndices[$index['Key_name']] = array();
				$compositeIndices[$index['Key_name']][] = $index['Column_name'];
				if (1 < count($compositeIndices[$index['Key_name']]))
				{
					unset($indices[$key]);
				}
			}

			// generate the output
			foreach ($indices as $index)
			{
				$line = "\t";
				$columnList = '(`' . implode('`,`', $compositeIndices[$index['Key_name']]) . '`),';

				if ('PRIMARY' == $index['Key_name'])
				{
					$line .= 'PRIMARY KEY ' . $columnList;
				}
				else
				{
					if ('FULLTEXT' == $index['Index_type'])
					{
						$line .= 'FULLTEXT ';
					}
					if (0 == $index['Non_unique'])
					{
						$line .= 'UNIQUE ';
					}
					$line .= 'KEY `' . $index['Key_name'] . '` ';
					$line .= $columnList;
				}

				$output .= $line . PHP_EOL;
			}
		}

		$output = substr($output, 0, -3);
		$output .= PHP_EOL . ')';

		if ($collation = $this->_getTableCollation($tableName))
		{
			$output .= ' DEFAULT CHARSET = `' . $collation . '`;';
		}

		return stripslashes($output);
	}

	/**
	 * makeDataBackup
	 *
	 * Return data sql dump
	 *
	 * @param string $tableName $tableName table name
	 * @param bool $aComplete if true use complete inserts
	 * @param bool $prefix if true use prefix
	 * @access public
	 * @return string
	 */
	public function makeDataBackup($tableName, $aComplete = false, $prefix = true)
	{
		$tableNameReplacement = $prefix ? $tableName : str_replace($this->iaDb->prefix, '{prefix}', $tableName);

		$out = '';
		$complete = '';

		$this->iaDb->setTable($tableName, false);
		if ($aComplete)
		{
			$fields = $this->iaDb->describe($tableName, false);

			$complete = ' (';

			foreach ($fields as $value)
			{
				$complete .= "`" . $value['Field'] . "`, ";
			}
			$complete = preg_replace('/(,\n|, )?$/', '', $complete);
			$complete .= ')';
		}

		if ($data = $this->iaDb->all())
		{
			foreach ($data as $value)
			{
				$out .= 'INSERT INTO `' . $tableNameReplacement . '`' . $complete . " VALUES (";
				foreach ($value as $key2 => $value2)
				{
					if (!isset($value[$key2]))
					{
						$out .= "null, ";
					}
					elseif ($value[$key2] != '')
					{
						$out .= "'" . iaSanitize::sql($value[$key2]) . "', ";
					}
					else
					{
						$out .= "'', ";
					}
				}
				$out = rtrim($out, ', ');
				$out .= ');' . PHP_EOL;
			}
		}

		$this->iaDb->resetTable();

		return $out;
	}

	/**
	 * Return data + structure sql dump
	 *
	 * @param string $tableName table name
	 * @param bool $drop if true use DROP TABLE
	 * @param bool $complete if true use complete inserts
	 * @param bool $prefix if true use prefix
	 * @access public
	 *
	 * @return string
	 */
	public function makeFullBackup($tableName, $drop = false, $complete = false, $prefix = true)
	{
		$out = $this->makeStructureBackup($tableName, $drop, $prefix);
		$out .= PHP_EOL . PHP_EOL;
		$out .= $this->makeDataBackup($tableName, $complete, $prefix);
		$out .= PHP_EOL . PHP_EOL;

		return $out;
	}

	/**
	 * Returns structure dump of a database
	 *
	 * @param bool $drop if true use DROP TABLE
	 * @param bool $prefix if true use prefix
	 * @access public
	 *
	 * @return string
	 */
	public function makeDbStructureBackup($drop = false, $prefix = true)
	{
		$out = "CREATE DATABASE `" . INTELLI_DBNAME . "`;\n\n";

		$tables = $this->getTables();

		foreach ($tables as $table)
		{
			$out .= $this->makeStructureBackup($table, $drop, $prefix);
			$out .= "\n\n";
		}

		return $out;
	}

	/**
	 * Returns data dump of a database
	 *
	 * @param bool $complete if true use complete inserts
	 * @param bool $prefix if true use prefix
	 * @access public
	 *
	 * @return string
	 */
	public function makeDbDataBackup($complete = false, $prefix = true)
	{
		$result = '';
		$tables = $this->getTables();

		foreach ($tables as $table)
		{
			$result .= $this->makeDataBackup($table, $complete, $prefix);
			$result .= PHP_EOL . PHP_EOL;
		}

		return $result;
	}

	/**
	 * Returns whole database dump
	 *
	 * @param bool $aDrop if true use DROP TABLE
	 * @param bool $aComplete if true use complete inserts
	 * @param bool $aPrefix if true use prefix
	 * @access public
	 *
	 * @return string
	 */
	public function makeDbBackup($aDrop = false, $aComplete = false, $aPrefix = true)
	{
		$out = "CREATE DATABASE `" . INTELLI_DBNAME . "`;\n\n";

		$tables = $this->getTables();

		foreach ($tables as $table)
		{
			$out .= $this->makeStructureBackup($table, $aDrop, $aPrefix);
			$out .= "\n\n";
			$out .= $this->makeDataBackup($table, $aComplete, $aPrefix);
			$out .= "\n\n";
		}

		return $out;
	}

	protected function _getTableCollation($table)
	{
		$sql = sprintf('SHOW CREATE TABLE `%s`', $table);

		$structure = $this->iaDb->getAll($sql);
		$structure = $structure[0]['Create Table'];

		$result = '';

		$matches = array();
		if (preg_match('/DEFAULT CHARSET=([a-z0-9]+)/i', $structure, $matches))
		{
			$result = $matches[1];
		}

		return $result;
	}
}