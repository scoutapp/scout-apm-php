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

## Automated releases

This project makes use of the `laminas/automatic-releases` GitHub actions for PHP projects to automate the release
process. In summary, this means:

 - Trunk branches are made for each minor, e.g. `8.1.x`
 - Each pull request is assigned to a milestone
 - When a milestone is closed, the trunk branch is tagged and a release created with automatic changelogs based on the
   pull requests assigned to the milestone.
 - If the milestone is for a patch release, a pull request is made to also merge the change into the latest trunk
 - When a minor or major release is made, new trunk branches are made and automatically switched to (e.g. `8.2.x` or `9.0.x`)

For full details, please see [`laminas/automatic-releases`](https://github.com/laminas/automatic-releases/).
