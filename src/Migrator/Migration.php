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

    protected $storedData = false;

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
        var_dump($currentTables);
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
        $installSql =   "CREATE TABLE {$storageTable} (
                            `migration_time` INT(11) UNSIGNED NOT NULL,
                            `status` TINYINT(1) UNSIGNED DEFAULT '0',
                            `data` TEXT
                        )ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $connection = @mysql_connect($dbHost, $dbUser, $dbPass);
        if ($connection) {
            if (mysql_select_db($dbName)) {
                mysql_set_charset('utf8', $connection);
                if (!mysql_query($installSql)) {
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
                WHERE _st.migration_time = (SELECT MAX(migration_time) FROM `{$this->storageTable}`);";

        $this->storedData = $this->runQuery($sql);
    }

    private function runQuery($sql)
    {
        try {
            $result = mysql_query($sql);
        } catch (\Exception $e) {
            throw new MigrationException($e->getMessage(), $e->getCode());
        }

        $rows = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }
}