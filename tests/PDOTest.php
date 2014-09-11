<?php
namespace alfmel\OCI8\Tests;

use alfmel\OCI8\PDO;

require_once(__DIR__ . "/../alfmel/OCI8/PDO.php");

class PDOTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Tests the constructor
     * @dataProvider testParseValidDSNProvider
     */
    public function testParseValidDSN($dsn, $expectedResult)
    {

        //Parse the $dsn and compare to result
        $result = PDO::parseDsn($dsn, array('charset'));
        $this->assertEquals($expectedResult, $result);

    }

    /**
     * Returns all possible test variations for parseValidDSN
     *
     * @access public
     */
    public function testParseValidDSNProvider()
    {
        return array(
            array(
                'dsn' => 'oci:db1',
                'expectedResult' => array(
                    'hostname' => '',
                    'port' => 1521,
                    'dbname' => 'db1',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci://db1',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1521,
                    'dbname' => 'db1',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci:dbname=db1',
                'expectedResult' => array(
                    'hostname' => '',
                    'port' => 1521,
                    'dbname' => 'db1',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci:dbname=db1;port=1599',
                'expectedResult' => array(
                    'hostname' => '',
                    'port' => 1599,
                    'dbname' => 'db1',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci:dbname=db1;port=1599;charset=WE8ISO8859P15',
                'expectedResult' => array(
                    'hostname' => '',
                    'port' => 1599,
                    'dbname' => 'db1',
                    'params' => array('charset' => 'WE8ISO8859P15'),
                ),
            ),
            array(
                'dsn' => 'oci://localhost/db1',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1521,
                    'dbname' => 'db1',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci://localhost:1599/db2',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1599,
                    'dbname' => 'db2',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci://nunc1m.server.business:1599/db3',
                'expectedResult' => array(
                    'hostname' => 'nunc1m.server.business',
                    'port' => 1599,
                    'dbname' => 'db3',
                    'params' => array(),
                ),
            ),
            array(
                'dsn' => 'oci://nunc1m.server.business:1199/db6;charset=WE8ISO8859P15',
                'expectedResult' => array(
                    'hostname' => 'nunc1m.server.business',
                    'port' => 1199,
                    'dbname' => 'db6',
                    'params' => array('charset' => 'WE8ISO8859P15'),
                ),
            ),
        );
    }

}