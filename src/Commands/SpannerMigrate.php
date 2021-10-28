<?php

namespace Firevel\MysqlToSpanner\Commands;

use DB;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MgCosta\MysqlParser\Dialect;
use MgCosta\MysqlParser\Parser;
use Storage;

class SpannerMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:spanner-migrate {--spanner-connection=spanner} {--only=} {--ignore-table=} {--default-primary-key=} {--fresh} {--data} {--schema=true} {--chunk-size=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate MySQL database to Cloud Spanner.';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! empty($this->option('only'))) {
            $tables = explode(',', $this->option('only'));
        } else {
            $tables = $this->getTables();
        }

        $connection = $this->option('spanner-connection');

        if (! empty($this->option('ignore-table'))) {
            $this->ignore = explode(',', $this->option('ignore-table'));
        }

        if ($this->option('schema')=='true') {
            $schemas = [
                'tables' => [],
                'indexes' => [],
                'constraints' => [],
            ];

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

            $this->createDatabase(
                $connection,
                array_merge(
                    $schemas['tables'],
                    $schemas['indexes'],
                    $schemas['constraints']
                )

            );
        }

        $this->info('Migrating data.');

        if ($this->option('data')) {
            foreach ($tables as $table) {
                $this->migrateData($connection, $table);
            }
        }


        return 0;
    }

    /**
     * Migrate data to spanner.
     *
     * @param  string $connection Connection name
     * @param  string $table      Table name
     * @return void
     */
    public function migrateData($connection, $table)
    {
        $this->info('Migrating data from table ' . $table);

        DB::table($table)
            ->orderBy($this->getTablePrimaryKey($table))
            ->chunk($this->option('chunk-size'), function ($rows) use ($connection, $table) {
                $rows = json_decode(json_encode($rows), true);
                DB::connection($connection)->table($table)->insert($rows);
            });
    }

    /**
     * Create database.
     *
     * @param  string $connection
     * @param  array $schemas
     * @return void
     */
    public function createDatabase($connection, $schemas)
    {
        foreach ($schemas as &$schema) {
            $schema = rtrim($schema, ";");
        }

        if (DB::connection($connection)->databaseExists()) {
            if (! $this->option('fresh')) {
                throw new Exception("Database already exists. Use --fresh to overwrite exisitng database.");
            }

            $this->info("Deleting database.");
            DB::connection($connection)->dropDatabase();
        }

        $this->info("Creating database.");
        $operation = DB::connection($connection)->createDatabase($schemas);
        $this->info('Database created.');
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
        $table = DB::select(
            DB::raw(
                app(Dialect::class)->generateTableDetails($tableName)
            )
        );

        $keys = DB::select(
            DB::raw(
                app(Dialect::class)->generateTableKeysDetails(DB::connection()->getDatabaseName(), $tableName)
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
        $tables = DB::select('SHOW TABLES');

        return array_map('current', $tables);
    }

    /**
     * Get table primary key.
     *
     * @param  string $table      Table name
     * @return void
     */
    public function getTablePrimaryKey($table)
    {
        $key = DB::select("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");

        if (empty($key)) {
            if (! empty($this->option('default-primary-key'))) {
                return $this->option('default-primary-key');
            }

            throw new Exception('Missing primary key in table ' . $table);
        }
        return $key[0]->Column_name;
    }
}
