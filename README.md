# Backpack-articles

[![Build Status](https://travis-ci.org/parabellumKoval/backpack-articles.svg?branch=master)](https://travis-ci.org/parabellumKoval/backpack-articles)
[![Coverage Status](https://coveralls.io/repos/github/parabellumKoval/backpack-articles/badge.svg?branch=master)](https://coveralls.io/github/parabellumKoval/backpack-articles?branch=master)

[![Packagist](https://img.shields.io/packagist/v/parabellumKoval/backpack-articles.svg)](https://packagist.org/packages/parabellumKoval/backpack-articles)
[![Packagist](https://poser.pugx.org/parabellumKoval/backpack-articles/d/total.svg)](https://packagist.org/packages/parabellumKoval/backpack-articles)
[![Packagist](https://img.shields.io/packagist/l/parabellumKoval/backpack-articles.svg)](https://packagist.org/packages/parabellumKoval/backpack-articles)

This package provides a quick starter kit for implementing articles for Laravel Backpack. Provides a database, CRUD interface, API routes and more.

## Installation

Install via composer
```bash
composer require parabellumKoval/backpack-articles
```

Migrate
```bash
php artisan migrate
```

### Publish

#### Configuration File
```bash
php artisan vendor:publish --provider="Backpack\Articles\ServiceProvider" --tag="config"
```

#### Views File
```bash
php artisan vendor:publish --provider="Backpack\Articles\ServiceProvider" --tag="views"
```

#### Migrations File
```bash
php artisan vendor:publish --provider="Backpack\Articles\ServiceProvider" --tag="migrations"
```

#### Routes File
```bash
php artisan vendor:publish --provider="Backpack\Articles\ServiceProvider" --tag="routes"
```

## Usage

### Seeders
```bash
php artisan db:seed --class="Backpack\Articles\database\seeders\ArticlesSeeder"
```

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [](https://github.com/parabellumKoval/backpack-articles)
- [All contributors](https://github.com/parabellumKoval/backpack-articles/graphs/contributors)
