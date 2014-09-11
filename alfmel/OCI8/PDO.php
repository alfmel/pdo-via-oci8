<?php
/**
 * PDO userspace driver proxying calls to PHP OCI8 driver
 *
 * @category Database
 * @package yajra/PDO-via-OCI8
 * @author Mathieu Dumoulin <crazyone@crazycoders.net>
 * @copyright Copyright (c) 2013 Mathieu Dumoulin (http:// crazycoders.net/)
 * @license MIT
 */

namespace alfmel\OCI8;

/**
 * Oci8 class to mimic the interface of the PDO class
 *
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class PDO
    extends \PDO
{

    /**
     * Database handler
     *
     * @var resource
     */
    public $_dbh;

    /**
     * Driver options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Whether currently in a transaction
     *
     * @var bool
     */
    protected $_inTransaction = false;

    /**
     * insert query statement table variable
     *
     * @var string
     */
    protected $_table;

    /**
     * Creates a PDO instance representing a connection to a database
     *
     * @param string $dsn
     * @param string $username [optional]
     * @param string $password [optional]
     * @param array $options [optional]
     * @throws \PDOException
     */
    public function __construct($dsn,
                                $username = null,
                                $password = null,
                                array $options = array())
    {
        // Parse the DSN
        $parsedDsn = self::parseDsn($dsn, array('charset'));

        // Get SID name
        $sidString = (isset($parsedDsn['params']['sid'])) ?
            '(SID = '.$parsedDsn['params']['sid']. ')' : '';

        if( strpos($parsedDsn['hostname'],",") !== FALSE ) {

            $hostname = explode(',',$parsedDsn['hostname']);
            $count    = count($hostname);
            $address  = "";

            for($i = 0;$i < $count; $i++) {
               $address .= '(ADDRESS = (PROTOCOL = TCP)(HOST = '.$hostname[$i].
                   ')(PORT = '.$parsedDsn['port'].'))';
            }

             // Create a description to locate the database to connect to
            $description = '(DESCRIPTION =
                '.$address.'
                (LOAD_BALANCE = yes)
                (FAILOVER = on)
                (CONNECT_DATA =
                        '.$sidString.'
                        (SERVER = DEDICATED)
                        (SERVICE_NAME = '.$parsedDsn['dbname'].')
                )
            )';

        } else if ($parsedDsn["hostname"] || $parsedDsn["dbname"]) {
            // Create a basic connection string
            if ($parsedDsn["hostname"])
            {
                $description = $parsedDsn["hostname"] . ":" .
                    $parsedDsn["port"] . "/" . $parsedDsn["dbname"];
            }
            else
            {
                $description = $parsedDsn["dbname"];
            }
        } else {

             // Create a description to locate the database to connect to
             $description = '(DESCRIPTION =
                    (ADDRESS_LIST =
                        (ADDRESS = (PROTOCOL = TCP)(HOST = '.
                            $parsedDsn['hostname'].')
                        (PORT = '.$parsedDsn['port'].'))
                    )
                    (CONNECT_DATA =
                            '.$sidString.'
                            (SERVICE_NAME = '.$parsedDsn['dbname'].')
                    )
                )';

        }

        // see if we have character set
        $charset = null;
        if (array_key_exists("charset", $parsedDsn["params"]))
        {
            $charset = $parsedDsn["params"]["charset"];
        }

        // Attempt a connection
        if (isset($options[\PDO::ATTR_PERSISTENT])
            && $options[\PDO::ATTR_PERSISTENT]) {

            $this->_dbh = @oci_pconnect(
                $username,
                $password,
                $description,
                $charset);

        } else {

            $this->_dbh = @oci_connect(
                $username,
                $password,
                $description,
                $charset);
        }

        // Check if connection was successful
        if (!$this->_dbh) {
            $e = oci_error();
            throw new \PDOException($e['message']);
        }

        // Save the options
        $this->_options = $options;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $statement This must be a valid SQL statement for the
     *   target database server.
     * @param array $options [optional] This array holds one or more key=>value
     *   pairs to set attribute values for the PDOStatement object that this
     *   method returns.
     * @throws \PDOException
     * @return PDOStatement
     */
    public function prepare($statement, $options = null)
    {

        // Get instance options
        if($options == null) $options = $this->_options;
        // Replace ? with a pseudo named parameter
        $newStatement = null;
        $parameter = 0;
        while($newStatement !== $statement)
        {
            if($newStatement !== null)
            {
                $statement = $newStatement;
            }
            $newStatement = preg_replace('/\?/', ':autoparam'.$parameter,
                $statement, 1);
            $parameter++;
        }
        $statement = $newStatement;

        // check if statement is insert function
        if (strpos(strtolower($statement), 'insert into')!==false) {
            preg_match('/insert into\s+([^\s\(]*)?/', strtolower($statement),
                $matches);
            // store insert into table name
            $this->_table = $matches[1];
        }

        // Prepare the statement
        $sth = @oci_parse($this->_dbh, $statement);

        if (!$sth) {
            $e = oci_error($this->_dbh);
            throw new \PDOException($e['message']);
        }

        if (!is_array($options)) {
            $options = array();
        }

        return new PDOStatement($sth, $this, $options);
    }

    /**
     * Initiates a transaction
     *
     * @throws \PDOException
     * @return bool TRUE on success or FALSE on failure
     */
    public function beginTransaction()
    {
        if ($this->inTransaction()) {
            throw new \PDOException('There is already an active transaction');
        }

        $this->_inTransaction = true;
        return true;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @deprecated Use inTransaction() instead
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->inTransaction();
    }

    /**
     * Checks if inside a transaction
     *
     * @return bool TRUE if a transaction is currently active, and FALSE if not.
     */
    public function inTransaction()
    {
        return $this->_inTransaction;
    }

    /**
     * Commits a transaction
     *
     * @throws \PDOException
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commit()
    {
        if (!$this->inTransaction()) {
            throw new \PDOException('There is no active transaction');
        }

        if (oci_commit($this->_dbh)) {
            $this->_inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back a transaction
     *
     * @throws \PDOException
     * @return bool TRUE on success or FALSE on failure.
     */
    public function rollBack()
    {
        if (!$this->inTransaction()) {
            throw new \PDOException('There is no active transaction');
        }

        if (oci_rollback($this->_dbh)) {
            $this->_inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Sets an attribute on the database handle
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setAttribute($attribute, $value)
    {
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Executes an SQL statement and returns the number of affected rows
     *
     * @param string $statement The SQL statement to prepare and execute.
     * @return int The number of rows that were modified or deleted by the SQL
     *   statement you issued.
     */
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Executes an SQL statement, returning the results as a
     * yajra\Pdo\Oci8\Statement object
     *
     * @param string $statement The SQL statement to prepare and execute.
     * @param int|null $fetchMode The fetch mode must be one of the
     *   PDO::FETCH_* constants.
     * @param mixed|null $modeArg Column number, class name or object.
     * @param array|null $ctorArgs Constructor arguments.
     * @return PDOStatement
     */
    public function query($statement,
                          $fetchMode = null,
                          $modeArg = null,
                          array $ctorArgs = array())
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();
        if ($fetchMode) {
            $stmt->setFetchMode($fetchMode, $modeArg, $ctorArgs);
        }

        return $stmt;
    }

    /**
     * returns the current value of the sequence related to the table where
     * record is inserted. The sequence name should follow this for it to work
     * properly:
     *
     *   {$table}.'_'.{$column}.'_seq'
     *
     * Oracle does not support the last inserted ID functionality like MySQL.
     * If the above sequence does not exist, the method will return 0;
     *
     * @param string $name Sequence name; no use in this context
     * @return mixed Last sequence number or 0 if sequence does not exist
     */
    public function lastInsertId($name = null)
    {
        $sequence = $this->_table . "_" . $name . "_seq";
        if (!$this->checkSequence($sequence))
            return 0;

        $stmt = $this->query("select {$sequence}.currval from dual");
        $id = $stmt->fetch();
        return $id;
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the database
     * handle
     *
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string
     */
    public function errorCode()
    {
        $error = $this->errorInfo();
        return $error[0];
    }

    /**
     * Returns extended error information for the last operation on the database
     * handle
     *
     * The array consists of the following fields:
     *
     *   0  SQLSTATE error code (a five characters alphanumeric identifier
     *      defined in the ANSI SQL standard).
     *   1  Driver-specific error code.
     *   2  Driver-specific error message.
     *
     * @return array Error information
     */
    public function errorInfo()
    {
        $e = oci_error($this->_dbh);

        if (is_array($e)) {
            return array(
                'HY000',
                $e['code'],
                $e['message']
            );
        }

        return array('00000', null, null);
    }

    /**
     * Retrieve a database connection attribute
     *
     * @param int $attribute
     * @return mixed A successful call returns the value of the requested PDO
     *   attribute. An unsuccessful call returns null.
     */
    public function getAttribute($attribute)
    {
        if (isset($this->_options[$attribute])) {
            return $this->_options[$attribute];
        }
        return null;
    }

    /**
     * Special non PDO function used to start cursors in the database
     * Remember to call oci_free_statement() on your cursor
     *
     * @access public
     *
     * @return mixed New statement handle, or FALSE on error.
     */
    public function getNewCursor()
    {
        return oci_new_cursor($this->_dbh);
    }

    /**
     * Special non PDO function used to start descriptor in the database
     * Remember to call oci_free_statement() on your cursor
     *
     * @access public
     *
     * @param int $type One of OCI_DTYPE_FILE, OCI_DTYPE_LOB or OCI_DTYPE_ROWID.
     * @return mixed New LOB or FILE descriptor on success, FALSE on error.
     */
    public function getNewDescriptor($type = OCI_D_LOB)
    {
        return oci_new_descriptor($this->_dbh, $type);
    }

    /**
     * Special non PDO function used to close an open cursor in the database
     *
     * @access public
     *
     * @param mixed $cursor A valid OCI statement identifier.
     * @return mixed Returns TRUE on success or FALSE on failure.
     */
    public function closeCursor($cursor)
    {
        return oci_free_statement($cursor);
    }

    /**
     * Places quotes around the input string
     *
     *  If you are using this function to build SQL statements, you are strongly
     * recommended to use prepare() to prepare SQL statements with bound
     * parameters instead of using quote() to interpolate user input into an SQL
     * statement. Prepared statements with bound parameters are not only more
     * portable, more convenient, immune to SQL injection, but are often much
     * faster to execute than interpolated queries, as both the server and
     * client side can cache a compiled form of the query.
     *
     * @param string $string The string to be quoted.
     * @param int $paramType Provides a data type hint for drivers that have
     *   alternate quoting styles
     * @return string Returns a quoted string that is theoretically safe to pass
     *   into an SQL statement.
     * @todo Implement support for $paramType.
     */
    public function quote($string, $paramType = \PDO::PARAM_STR)
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }

    /**
     * Parses a DSN string according to the rules in the PHP manual
     *
     * @param string $dsn
     * @todo Extract this to a DSN Parser object and inject result into PDO class
     * @todo Change return value of array() when invalid to thrown exception
     * @todo Change returned value to object with default values and properties
     * @todo Refactor to use an URI content resolver instead of
     *   file_get_contents() that could support caching for example
     * @param array $params
     * @return array
     * @link http:// www.php.net/manual/en/pdo.construct.php
     */
    public static function parseDsn($dsn, array $params)
    {
        // Create the object we will return to ensure it has the right values
        $returnParams = array("hostname" => "", "port" => 1521, "dbname" => "",
            "params" => array());

        // If there is a colon, it means it's a parsable DSN
        // Doesn't mean it's valid, but at least, it's parsable
        if (strpos($dsn, ':') !== false) {

            // The driver is the first part of the dsn, then comes the variables
            $driver = null;
            $vars = null;
            @list($driver, $vars) = explode(':', $dsn, 2);

            // Based on the driver, the processing changes
            switch($driver)
            {
                case 'uri':

                    // If the driver is a URI, we get the file content at that
                    // URI and parse it
                    return self::parseDsn(file_get_contents($vars), $params);

                case 'oci':
                    // See if we have leading //
                    if(substr($vars, 0, 2) !== '//')
                    {
                        // See if we have key/value pairs
                        if (strpos($vars, "=") === false)
                        {
                            // Consider the value to be a simple dbname
                            $returnParams["dbname"] = $vars;
                        }
                        else
                        {
                            // Get the values from the key/value pairs
                            $params = array();
                            $raw_params = explode(";", $vars);

                            foreach($raw_params as $param)
                            {
                                list($key, $value) =
                                    array_pad(explode("=", $param, 2), 2, null);

                                if ($key)
                                {
                                    $params[strtolower($key)] = $value;
                                }
                            }

                            // Extract the dbname, hostname and port
                            if (array_key_exists("dbname", $params))
                            {
                                $returnParams["dbname"] = $params["dbname"];
                                unset($params["dbname"]);
                            }
                            if (array_key_exists("hostname", $params))
                            {
                                $returnParams["hostname"] = $params["hostname"];
                                unset($params["hostname"]);
                            }
                            if (array_key_exists("port", $params))
                            {
                                $returnParams["port"] = $params["port"];
                                unset($params["port"]);
                            }

                            // The rest are simply other parameters
                            $returnParams["params"] = $params;
                        }
                    }
                    else
                    {
                        $vars = substr($vars, 2);

                        // If there is a / in the initial vars, it means we have
                        // hostname:port configuration to read
                        $returnParams["hostname"] = 'localhost';
                        $returnParams["port"] = 1521;
                        if(strpos($vars, '/') !== false)
                        {

                            // Extract the hostname port from the $vars
                            $hostnamePost = null;
                            @list($hostnamePort, $vars) =
                                explode('/', $vars, 2);

                            // Parse the hostname port into two variables, set
                            // the default port if invalid
                            @list($hostname, $port) =
                                explode(':', $hostnamePort, 2);
                            $returnParams["hostname"] = $hostname;
                            if(is_numeric($port)) {
                                $returnParams["port"] = (int)$port;
                            }

                        }

                        // Extract the dbname/service name from the first part,
                        // the rest are parameters
                        @list($dbname, $vars) = explode(';', $vars, 2);
                        foreach(explode(';', $vars) as $var)
                        {

                            // Get the key/value pair
                            @list($key, $value) = explode('=', $var, 2);

                            // If the key is not a valid parameter, discard
                            if(!in_array($key, $params))
                            {
                                continue;
                            }

                            // Key that key/value pair
                            $returnParams["params"][$key] = $value;

                        }

                        // Dbname may also contain SID
                        if(strpos($dbname,'/SID/') !== false)
                        {
                            list($dbname, $sidKey, $sidValue) =
                                explode('/',$dbname);
                        }

                        // Condense the parameters, hostname, port, dbname into
                        // $returnParams
                        $returnParams['dbname'] = $dbname;
                        if(isset($sidValue)) $returnParams["params"]['sid'] =
                            $sidValue;
                    }

                    // Return the resulting configuration
                    return $returnParams;
                default:
                    throw new \PDOException($driver . " is not supported by " .
                        "this implementation");

            }

        // If there is no colon, it means it's a DSN name in php.ini
        } elseif (strlen(trim($dsn)) > 0) {

            // The DSN passed in must be an alias set in php.ini
            return self::parseDsn(ini_get("pdo.dsn.".$dsn), $params);

        }

        // Not valid, return default values
        return $returnParams;

    }

    /**
     * Special non PDO function to check if sequence exists
     * @param  string $name
     * @return boolean
     */
    public function checkSequence($name)
    {
        if (!$name)
            return false;

        $stmt = $this->query("select count(*)
            from all_sequences
            where
                sequence_name=upper('{$name}')
                and sequence_owner=upper(user)
            ");
        return $stmt->fetch();
    }
}
