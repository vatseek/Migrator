<?php

namespace Migrator;

use Migrator\Exception\MigrationException;

class Migration
{
    protected $dbHost = false;
    protected $dbName = false;
    protected $dbUser = false;
    protected $dbPass = false;

    protected $connection = false;

    public function __construct($dbName, $dbUser, $dbPass, $dbHost = 'localhost')
    {
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->dbUser = $dbUser;
        $this->dbPass = $dbPass;

        $this->connection = @mysql_connect($this->dbHost, $this->dbUser, $this->dbPass);
        if (!$this->connection) {
            throw new MigrationException('MySQL connect error');
        }

        if (!@mysql_select_db('information_schema')) {
            throw new MigrationException('MySQL access error');
        }
    }

    public function run()
    {
        var_dump($this->connection);
    }
}