<?php

namespace Firevel\MysqlToSpanner\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MgCosta\MysqlParser\Dialect;
use MgCosta\MysqlParser\Parser;
use Storage;

class SpannerDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:spanner-dump {--connection=} {--disk=} {--file=} {--only=} {--ignore-table=} {--default-primary-key=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export database schema to Data Definition Language (DDL)';

    /**
     * Parser.
     *
     * @var MgCosta\MysqlParser\Parser;
     */
    protected $parser;

    /**
     * Tables to ignore with wildcard support.
     *
     * @var array
     */
    protected $ignore = [];

    /**
     * Connection name.
     *
     * @var Illuminate\Database\MySqlConnection
     */
    protected $connection;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->connection = DB::connection($this->option('connection'));

        if (! empty($this->option('only'))) {
            $tables = explode(',', $this->option('only'));
        } else {
            $tables = $this->getTables();
        }

        $schemas = [
            'tables' => [],
            'indexes' => [],
            'constraints' => [],
        ];

        if (! empty($this->option('ignore-table'))) {
            $this->ignore = explode(',', $this->option('ignore-table'));
        }

        foreach ($tables as $table) {
            if ($this->isIgnored($table)) {
                continue;
            }
            $this->info("Parsing $table");
            $ddl = $this->getTableDDL($table);

            if (! empty($ddl['tables'])) {
                $schemas['tables'] = array_merge($schemas['tables'], $ddl['tables']);
            }

            if (! empty($ddl['indexes'])) {
                $schemas['indexes'] = array_merge($schemas['indexes'], $ddl['indexes']);
            }

            if (! empty($ddl['constraints'])) {
                $schemas['constraints'] = array_merge($schemas['constraints'], $ddl['constraints']);
            }
        }

        $query = implode(
            "\n",
            array_merge(
                $schemas['tables'],
                $schemas['indexes'],
                $schemas['constraints']
            )
        );

        if (empty($this->option('file'))) {
            echo $query;
        } else {
            $this->saveToFile(
                $query,
                $this->option('file'),
                $this->option('disk'),
            );
        }

        return 0;
    }

    /**
     * Check if table should be ignored.
     *
     * @param  string  $table
     * @return bool
     */
    public function isIgnored($table)
    {
        foreach ($this->ignore as $ignore) {
            if (Str::is($ignore, $table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save output to file.
     *
     * @param  string  $query
     * @param  string  $file
     * @param  string  $disk
     * @return void
     */
    public function saveToFile($query, $file, $disk = null)
    {
        if (empty($disk)) {
            file_put_contents($file, $query);
            $this->info("Query saved to $file");

            return;
        }

        Storage::disk($disk)->put($file, $query);

        $this->info("Query saved to [$disk] $file");
    }

    /**
     * Get parser.
     *
     * @return Parser
     */
    public function getParser()
    {
        if (empty($this->parser)) {
            $this->parser = new Parser();

            if (empty($this->option('default-primary-key'))) {
                $this->parser->shouldAssignPrimaryKey(false);
            } else {
                $this->parser->setDefaultID($this->option('default-primary-key'));
            }
        }

        return $this->parser;
    }

    /**
     * Get Spanner DDL.
     *
     * @param  string  $tableName
     * @return string
     */
    public function getTableDDL($tableName)
    {
        $table = $this->connection->select(
            $this->connection->raw(
                app(Dialect::class)->generateTableDetails($tableName)
            )
        );

        $keys = $this->connection->select(
            $this->connection->raw(
                app(Dialect::class)->generateTableKeysDetails($this->connection->getDatabaseName(), $tableName)
            )
        );

        return $this->getParser()
                    ->setTableName($tableName)
                    ->setDescribedTable($table)
                    ->setKeys($keys)
                    ->toDDL();
    }

    /**
     * Get database tables.
     *
     * @return array
     */
    public function getTables()
    {
        $tables = $this->connection->select('SHOW TABLES');

        return array_map('current', $tables);
    }
}
