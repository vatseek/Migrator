<?php

namespace Migrator;

use Migrator\Exception\MigrationException;

class Migration
{
    protected $dbHost = false;
    protected $dbName = false;
    protected $dbUser = false;
    protected $dbPass = false;
    protected $storageTable = false;

    protected $connection = false;

    protected $storedData = array();

    const SCHEMA_DATABASE = 'information_schema';

    public function __construct($dbName, $dbUser, $dbPass, $dbHost = 'localhost', $storageTable = 'migrations')
    {
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->dbUser = $dbUser;
        $this->dbPass = $dbPass;
        $this->storageTable = $storageTable;

        $this->connection = @mysql_connect($this->dbHost, $this->dbUser, $this->dbPass);
        if (!$this->connection) {
            throw new MigrationException('MySQL connect error');
        }
        mysql_set_charset('utf8', $this->connection);

        if (!@mysql_select_db(self::SCHEMA_DATABASE)) {
            throw new MigrationException('MySQL access error');
        }

        $this->loadData();
    }

    public function run()
    {
        $this->getDifference();
    }

    protected function getDifference()
    {
        $currentTables = $this->getCurrentTables();
        foreach ($currentTables as $table) {
            foreach ($this->storedData as $storedTable) {
                if ($storedTable['TABLE_NAME'] == $table['TABLE_NAME']) {
                    $diff = array_diff($storedTable, $table);
                    if ($diff) {
                        $this->createTableMigration($table, $diff);
                    }

                    break;
                }
            }
        }
    }

    protected function createTableMigration($table, $diff)
    {

    }

    /**
     * @return array
     * @throws MigrationException
     */
    protected function getCurrentTables()
    {
        $sql = "SELECT * FROM `TABLES` WHERE `TABLE_SCHEMA` = '$this->dbName'; ";
        $rows = $this->runQuery($sql);
        foreach ($rows as $key => $table) {
            if ($table['TABLE_NAME'] == $this->storageTable) {
                unset($rows[$key]);
                break;
            }
        }

        return $rows;
    }

    /**
     * Install migration data table
     *
     * @param string $dbName
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbHost
     * @param string $storageTable
     *
     * @throws MigrationException
     */
    public static function install($dbName, $dbUser, $dbPass, $dbHost = 'localhost', $storageTable = 'migrations')
    {
        $sql =  "CREATE TABLE {$storageTable} (
                    `migration_time` INT(11) UNSIGNED NOT NULL,
                    `status` TINYINT(1) UNSIGNED DEFAULT '0',
                    `data` TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $connection = @mysql_connect($dbHost, $dbUser, $dbPass);
        if ($connection) {
            if (mysql_select_db($dbName)) {
                mysql_set_charset('utf8', $connection);
                if (!mysql_query($sql)) {
                    throw new MigrationException('Install query error');
                }
            } else {
                throw new MigrationException('Install error');
            }
        } else {
            throw new MigrationException('Install error');
        }
    }

    private function loadData()
    {
        $sql = "SELECT * FROM `{$this->dbName}`.`{$this->storageTable}` AS _st
                WHERE _st.migration_time = (
                    SELECT MAX(_st2.migration_time)
                    FROM `{$this->dbName}`.`{$this->storageTable}` AS _st2
                );";

        $result = $this->runQuery($sql);
        if ($result && is_array($result)) {
            $row = end($result);
            $this->storedData = unserialize($row['data']);
        }
    }

    private function setData($data)
    {
        $currentTime = time();
        $dataStamp = serialize($data);
        $sql = "INSERT INTO `{$this->dbName}`.`{$this->storageTable}`
                SET
                    `migration_time` = '{$currentTime}',
                    `status` = '0',
                    `data` = '{$dataStamp}';";

        try {
            mysql_query($sql);
        } catch (\Exception $e) {
            throw new MigrationException($e->getMessage(), $e->getCode());
        }
    }

    private function runQuery($sql)
    {
        try {
            $result = mysql_query($sql);
        } catch (\Exception $e) {
            throw new MigrationException($e->getMessage(), $e->getCode());
        }

        if (!$result) {
            return array();
        }

        $rows = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }
}