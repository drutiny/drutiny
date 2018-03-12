# Contributing to Drutiny

In general the Drutiny team will be looking to find ways to help people contribute. Pull requests are definitely appreciated.

## Small changes that will be accepted

* New audits and policies that others can utilize. Ideally they will have arguments where needed to make this as easy as possible to adapt for other sites.
* Spelling typos, grammar changes etc
* Better comments and code style
* Tests

Please consider these libraries as appropriate:

* [Drupal 7 Plugin](https://github.com/drutiny/plugin-drupal-7)
* [Drupal 8 Plugin](https://github.com/drutiny/plugin-drupal-8)
* [Drupal Distribution](https://github.com/drutiny/plugin-distro-common)

## Larger changes that will be accepted

* New integrations with other tools, where they provide significant value
* Any better OO techniques and code organisation
* Any open issues currently in the issue queue
* Anything that involves making the codebase more testible
* Removing technical debt

## Changes that will be (most likely) rejected

* Anything that requires special sauce in order to run
* Vendor specific checks that cannot be re-used

These types of changes are better off in third-party libraries.

# Coding standards

This project adheres to the same coding standards as the Drupal project.

## How to check code style

```
./vendor/bin/phpcs --config-set installed_paths ../../drupal/coder/coder_sniffer
./vendor/bin/phpcs --standard=Drupal --extensions=php,css,txt,md src/ -sp
```

## How to fix using phpcbf

```
./vendor/bin/phpcbf --standard=Drupal --extensions=php,css,txt,md src/
```


# Tests

PHPunit is being used in Drutiny, and ideally every check should have a simple test class to accompany it.



## How to run PHPunit

```
./vendor/bin/phpunit
```

You can run a subset of the tests by running just a group:

```
$ ./vendor/bin/phpunit --list-groups
PHPUnit 5.7.15 by Sebastian Bergmann and contributors.

Available test group(s):
 - base
 - check
 - report
```

e.g.

```
./vendor/bin/phpunit --group base
./vendor/bin/phpunit --group check
./vendor/bin/phpunit --group report
```
