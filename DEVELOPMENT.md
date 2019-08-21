# Dev Guide

## Setup

```bash
composer install
```

## Running tests

```bash
vendor/bin/phpunit
```

## Writing Code

### Checking for static analysis issues

```bash
vendor/bin/psalm
```

### Checking coding standards are met

```bash
vendor/bin/phpcs
```

### Fixing coding standards automatically

We have an automated style fixer called PHP Code Sniffer. Style is checked on TravisCI as well.

```bash
vendor/bin/phpcbf
```
