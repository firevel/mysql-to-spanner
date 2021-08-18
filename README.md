# Laravel tool to convert MySQL schemas to Cloud Spanner Data Definition Language (DDL)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/firevel/mysql-to-spanner.svg?style=flat-square)](https://packagist.org/packages/firevel/mysql-to-spanner)

## Installation

You can install the package via composer:

```bash
composer require firevel/mysql-to-spanner
```

## Usage

You can dump your MySQL database using command:

```php
php artisan db:spanner-dump
```

### Parameters
- Use `--file` to specify output ex.: `php artisan db:spanner-dump --file=storage/spanner-ddl.txt`.
- Use `--disk` together with `--file` to save output in storage defined in `/config/filesystems.php`, ex.: `php artisan db:spanner-dump --disk=gcs --file=exports/spanner-ddl.txt`.
- Use `--ignore-table` to specify tables to ignore during dump, ex: `php artisan db:spanner-dump --ignore-table=password_resets`, `php artisan db:spanner-dump --ignore-table=tmp_*`,  `php artisan db:spanner-dump --ignore-table=table1,table2`

## Credits

- [Mike Slowik][https://github.com/sl0wik]
- [Miguel Costa][https://github.com/mgcostaParedes]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.