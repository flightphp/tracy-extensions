<?php
declare(strict_types=1);

namespace flight\debug\database;

use PDO;
use PDOStatement;

class PdoQueryCaptureStatement extends PDOStatement {

	/** @var string $unique_value */
	public string $unique_value;

	/**
	 * Construct
	 *
	 * @param PDO $pdo [description]
	 */
	protected function __construct(PDO $pdo)
	{
		//$this->PDO = $pdo;
		$this->unique_value = uniqid("", true);
	}

	/**
	 * Executes a prepared statement
	 *
	 * @param array|null $params input parameters
	 * @return bool
	 */
	public function execute(array|null $params = null): bool
	{
		$start_time = microtime(true);
		$result = parent::execute($params);
		$end_time = microtime(true);
		$execution_time = $end_time - $start_time;
		$params = $params ?? (PdoQueryCapture::$query_data[$this->unique_value]['params'] ?? []);
		PdoQueryCapture::$query_data[$this->unique_value]['execution_time'] = $execution_time;
		PdoQueryCapture::$query_data[$this->unique_value]['rows'] = $this->rowCount();
		$this->transformQueryWithParams($params);
		return $result;
	}

	public function transformQueryWithParams($input_parameters = []) 
	{
		$query = PdoQueryCapture::$query_data[$this->unique_value]['query'];
		$indexed = $input_parameters === array_values($input_parameters);
        foreach($input_parameters as $k => $v) {
            if(is_string($v)) {
				$v = "'{$v}'";
			}
            if($indexed) {
				$query = preg_replace('/\?/', (string) $v, $query, 1);
			} else {
				$query = str_replace(":$k", (string) $v, $query);
			}
        }
		PdoQueryCapture::$query_data[$this->unique_value]['query'] = $query;
	}

	public function bindColumn($column, &$param, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = null): bool
	{
		$result = parent::bindColumn($column, $param, $type, $maxLength, $driverOptions);
		PdoQueryCapture::$query_data[$this->unique_value]['params'][$column] = $param;
		return $result;
	}

	public function bindParam($param, &$var, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = null): bool
	{
		$result = parent::bindParam($param, $param, $type, $maxLength, $driverOptions);
		PdoQueryCapture::$query_data[$this->unique_value]['params'][$param] = $param;
		return $result;
	}

	public function bindValue($param, $value, $type = PDO::PARAM_STR): bool
	{
		$result = parent::bindValue($param, $value, $type);
		PdoQueryCapture::$query_data[$this->unique_value]['params'][$param] = $value;
		return $result;
	}
}
