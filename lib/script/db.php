<?php

include_once dirname(__FILE__).'/config.php';

/**
 * The global static DB class to easy access the MySQL server.
 */
class DB {
	/**
	 * The current connection
	 * @var mysqli|null 
	 */
	private static $connection = null;
	/**
	 * The current id for the log file
	 * @var int|null
	 */
	private static $logKey = null;
	
	/**
	 * Connect to the database defined with the constants
	 * `DB_SERVER`, `DB_USER`, `DB_PW` and `DB_NAME`.
	 */
	public static function connect() {
		if (self::$connection !== null) return;
		include_once dirname(__FILE__).'/config.php';
		self::$connection = new mysqli(DB_SERVER, DB_USER, DB_PW, DB_NAME);
	}
	
	/**
	 * Close an existing connection to a database.
	 */
	public static function close() {
		if (self::$connection === null) return;
		self::$connection->close();
		self::$connection = null;
	}
	
	/**
	 * clear a previous and unfetched results
	 */
	private static function clearStoredResults() {
		do {
			if ($res = self::$connection->store_result())
				$res->free();
		}
		while (self::$connection->more_results() && self::$connection->next_result());
	}
	
	/**
	 * create a single MySql request
	 * @param string $sql The SQL code to execute
	 * @return DBResult
	 */
	public static function getResult(string $sql): DBResult {
		if (self::$connection === null) self::connect();
		self::clearStoredResults();
		self::log('singleResult', $sql);
		return new DBResult(self::$connection, self::$connection->query($sql));
	}
	
	/**
	 * Create multiple MySql requests
	 * @param string $sql The SQL code to execute
	 * @param string|null $source The name of the caller that want's to execute this
	 * @return DBMultiResult|null
	 */
	public static function getMultiResult(string $sql, ?string $source = null): ?DBMultiResult {
		if (self::$connection === null) self::connect();
		self::clearStoredResults();
		self::log($source == null ? 'multiResult' : $source, $sql);
		$result = self::$connection->multi_query($sql);
		if ($error = self::getError()) {
			echo $error;
			echo "<br/>".PHP_EOL;
			debug_print_backtrace();
			exit;
		}
		if (!$result) return null;
		return new DBMultiResult(self::$connection);
	}
	
	/**
	 * Execute a pure MySQL file without any markup
	 * @param string $path the path to the MySQL file to execute
	 * @return DBMultiResult|null
	 */
	public static function executeFile(string $path): ?DBMultiResult { 
		if (!file_exists($path)) return null;
		else return self::getMultiResult(file_get_contents($path), $path);
	}
	
	/**
	 * Execute a MySQL that can contains PHP markup. The given data will be 
	 * populated as global variables in this file. 
	 * 
	 * *Hint: It also exists a global variable `$success` to notify if the loading
	 * is successful. Feel free to edit this in your SQL script.*
	 * @param string $path Path to the SQL file with markup
	 * @param array $data an array with the data to populate
	 * @return DBMultiResult|null
	 */
	public static function executeFormatFile(string $path, array $data): ?DBMultiResult {
		// echo ".";
		if (!file_exists($path)) {
			echo "file not found: $path<br/>".PHP_EOL;
			debug_print_backtrace();
			return null;
		} 
		// echo ".";
		extract ($data);
		ob_start();
		$success = true;
		include $path;
		$sql = ob_get_contents();
		ob_end_clean();
		if ($success === false) return null;
		return self::getMultiResult($sql, $path);
	}
	
	/**
	 * Get the last error of the Database server
	 * @return string|null
	 */
	public static function getError(): ?string {
		if (self::$connection == null) return null;
		return self::$connection->error;
	}
	
	/**
	 * Escape a given string to use it savely in you SQL query
	 * @param string $text The unsafe input
	 * @return string The safe output
	 */
	public static function escape(string $text): string {
		if (self::$connection === null) self::connect();
		return str_replace(
			array('%','_'),
			array('\%','\_'),
			self::$connection->real_escape_string($text)
		);
	}

	/**
	 * Log a query to the log file. If `DB_LOG_QUERYS` is false this
	 * function will skipped.
	 * @param string $source The source of this call.
	 * @param string $sql The code to log.
	 */
	private static function log(string $source, string $sql) {
		if (!DB_LOG_QUERYS) return;
		if (self::$logKey === null)
			self::$logKey = time();
		$content = '-- '.str_repeat('-', 60).PHP_EOL;
		$content .= '-- Call at: '.date('Y-m-d H:i:s', time()).PHP_EOL;
		$content .= '-- Source: '.$source.PHP_EOL;
		$content .= '-- '.str_repeat('-', 60).PHP_EOL;
		$content .= PHP_EOL;
		$content .= $sql;
		$content .= PHP_EOL.PHP_EOL;
		if (!is_dir(__DIR__ . '/../logs/'))
			mkdir(__DIR__ . '/../logs/');
			if (!is_dir(__DIR__ . '/../logs/db/'))
				mkdir(__DIR__ . '/../logs/db/');
		file_put_contents(
			__DIR__ . '/../logs/db/'.self::$logKey.'.sql',
			$content,
			FILE_APPEND | LOCK_EX);
	}
}

/**
 * This class contains multiple MySQL results
 */
class DBMultiResult {
	/**
	 * The current connection
	 * @var mysql
	 */
	private $connection;
	/**
	 * The last fetched result
	 * @var mysqli_result
	 */
	private $currentResult;
	/**
	 * Identify if the last result was fetched
	 * @var bool
	 */
	private $lastResult;
	/**
	 * Remember if this handler has already freed the results.
	 * @var bool
	 */
	public $hasFreed = false;
	
	/**
	 * Create a new handler for multiple MySQL results.
	 * @param mysqli $connection The connection to the server.
	 */
	public function __construct(mysqli &$connection) {
		$this->connection = &$connection;
		$this->currentResult = $this->connection->store_result();
		$this->lastResult = false;
	}
	
	/**
	 * Try to get the next result of the query.
	 * @return DBResult|null
	 */
	public function getResult(): ?DBResult {
		if (!is_bool($this->lastResult) && !$this->lastResult->hasFreed)
			$this->lastResult->free();
		$this->lastResult = $this->currentResult;
		if (is_bool($this->currentResult)) $result = $this->currentResult;
		else $result = new DBResult($this->connection, $this->currentResult);
		if ($this->hasMoreResults()) {
			$this->connection->next_result();
			$this->currentResult = $this->connection->store_result();
		}
		else $this->currentResult = false;
		return $result === false ? null : $result;
	}
	
	/**
	 * Fetch all entrys of the unfetched results and store them 
	 * in a multidimensional array.
	 * @return mixed[][][]
	 */
	public function getAllResultsAsEntrys(): array {
		$result = array();
		while ($entry = $this->getResult())
			$result[] = $entry->getAllEntrys();
		return $result;		
	}
	
	/**
	 * Check if more results exists in the pipeline.
	 * @return bool
	 */
	public function hasMoreResults(): bool {
		return $this->connection->more_results();
	}
	
	/**
	 * Flush all unfetched results and make the connection ready for new
	 * querys.
	 */
	public function flush() {
		while ($this->connection->more_results() && $this->connection->next_result()) {;}
		if (!is_bool($this->currentResult)) $this->currentResult->free();
		if (!is_bool($this->lastResult)) $this->lastResult->free();
		$this->hasFreed = true;		
	}
	
	/**
	 * Alias for {@see DBMultiResult::flush()}
	 */
	public function free() {
		$this->flush();
	}
}

/**
 * Container for a single result
 */
class DBResult {
	/**
	 * The current result object of the query
	 * @var mysqli_result|bool
	 */
	private $result;
	/**
	 * The current connection to the database
	 * @var mysqli
	 */
	private $connection;
	/**
	 * Remember if this result has been freed
	 * @var bool
	 */
	public $hasFreed = false;
	
	/**
	 * Create a container for single database result
	 * @param mysqli $connection
	 * @param mysqli_result|bool $result
	 */
	public function __construct(mysqli &$connection, $result) {
		$this->result = $result;
		$this->connection = &$connection;
	}
	
	/**
	 * Get the next entry of this result.
	 * @return array|bool
	 */
	public function getEntry() {
		if ($this->result === false) return false;
		if ($this->result === true) return true;
		return $this->result->fetch_array(MYSQLI_ASSOC);
	}
	
	/**
	 * Get all unfetched entrys as array
	 * @return (array|bool)[]
	 */
	public function getAllEntrys(): array {
		$result = array();
		while ($entry = $this->getEntry()) $result[] = $entry;
		return $result;
	}
	
	/**
	 * Free all unfetched entrys of this result
	 */
	public function free() {
		if ($this->result && $this->result !== true) {
			try {
				// $this->result->free();
				$this->result->close();
			}
			catch (\Error $e) {}
		}
		$this->hasFreed = true;
	}
}

/**
 * Force the input to be an ?int
 * @param mixed $value
 * @return int|null
 */
function intvaln($value): ?int {
	if ($value === null)
		return null;
	else return intval($value);
}