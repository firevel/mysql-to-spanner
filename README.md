# Laravel tool to convert MySQL schemas to Cloud Spanner Data Definition Language (DDL)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/firevel/mysql-to-spanner.svg?style=flat-square)](https://packagist.org/packages/firevel/mysql-to-spanner)

## Migrating from MySQL to Cloud Spanner

Start here: [Migrating from MySQL to Cloud Spanner](https://cloud.google.com/architecture/migrating-mysql-to-spanner).

Make sure that:
- All tables got primary keys.
- `AUTO_INCREMENT` can be ignored (not supported by Cloud Spanner).

## Installation

You can install the package via composer:

```bash
composer require firevel/mysql-to-spanner
```

## Usage

### MySQL dump

You can dump your MySQL database using command:

```php
php artisan db:spanner-dump
```

Parameters supported:
- Use `--connection` to specify source (MySQL) connection name.
- Use `--file` to specify output ex.: `php artisan db:spanner-dump --file=storage/spanner-ddl.txt`.
- Use `--disk` together with `--file` to save output in storage defined in `/config/filesystems.php`, ex.: `php artisan db:spanner-dump --disk=gcs --file=exports/spanner-ddl.txt`.
- Use `--ignore-table` to specify tables to ignore during dump, ex: `php artisan db:spanner-dump --ignore-table=password_resets`, `php artisan db:spanner-dump --ignore-table=tmp_*`,  `php artisan db:spanner-dump --ignore-table=table1,table2`.
- Use `--default-primary-key` to specify default primary key ex.: `php artisan db:spanner-dump --default-primary-key=id`.
- Use `--only` to specify tables to export ex.: `php artisan db:spanner-dump --only=table1,table2`.

### MySQL to Spanner migration

You can migrate your MySQL data to Spanner using command:

```php
php artisan db:spanner-migrate
```

By default only schema will be migrated, if you would like to migrate schema and data run:

```php
php artisan db:spanner-migrate --data
```

Parameters supported:
- Use `--connection` to specify source (MySQL) connection name.
- Use `--spanner-connection` to specify connection name (`spanner` by default).
- Use `--fresh` to delete database before creating schemas.
- Use `--data` to migrate rows.
- Use `--chunk-size` to specify chunk size used in data migration.
- Use `--schema=false` to skip schema migration.
- Use `--ignore-table` to specify tables to ignore during dump, ex: `php artisan db:spanner-dump --ignore-table=password_resets`, `php artisan db:spanner-dump --ignore-table=tmp_*`,  `php artisan db:spanner-dump --ignore-table=table1,table2`.
- Use `--default-primary-key` to specify default primary key ex.: `php artisan db:spanner-dump --default-primary-key=id`.
- Use `--only` to specify tables to export ex.: `php artisan db:spanner-dump --only=table1,table2`.


## Credits

- [Michael Slowik](https://github.com/sl0wik)
- [Miguel Costa](https://github.com/mgcostaParedes)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
