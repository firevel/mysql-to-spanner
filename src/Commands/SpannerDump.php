<?php

namespace Firevel\MysqlToSpanner\Commands;

use Storage;
use DB;
use Illuminate\Support\Str;
use MgCosta\MysqlParser\Parser;
use MgCosta\MysqlParser\Dialect;
use Illuminate\Console\Command;

class SpannerDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:spanner-dump {--disk=} {--file=} {--ignore-table=}';

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tables = $this->getTables();

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
     * @return boolean
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
     * @param  string $query
     * @param  string $file
     * @param  string $disk
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
        }

        return $this->parser;
    }

    /**
     * Get Spanner DDL.
     *
     * @param  string $tableName
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
                app(Dialect::class)->generateTableKeysDetails($tableName)
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

        return array_map('current',$tables);        
    }
}
