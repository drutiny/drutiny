# Drutiny (Drupal Scrutiny)

<img src="https://github.com/drutiny/drutiny/raw/2.x/assets/logo.png" alt="Drutiny logo" align="right"/>

[![Build Status](https://travis-ci.org/drutiny/drutiny.svg?branch=2.x)](https://travis-ci.org/drutiny/drutiny) [![Documentation Status](https://readthedocs.org/projects/drutiny/badge/?version=latest)](http://drutiny.readthedocs.io/en/latest/?badge=latest)
 [![Latest Stable Version](https://poser.pugx.org/drutiny/drutiny/v/stable)](https://packagist.org/drutiny/drutiny) [![Total Downloads](https://poser.pugx.org/drutiny/drutiny/downloads)](https://packagist.org/drutiny/drutiny) [![Latest Unstable Version](https://poser.pugx.org/drutiny/drutiny/v/unstable)](https://packagist.org/drutiny/drutiny) [![License](https://poser.pugx.org/drutiny/drutiny/license)](https://packagist.org/drutiny/drutiny)

This is a generic Drupal site auditing and optional remediation tool.


## Installation

You can install Drutiny into your project with [composer](https://getcomposer.org). Drutiny is a require-dev type dependency.

```
composer require --dev drutiny/drutiny 2.x-dev
```

Alternately, Drutiny can be built as a standalone tool:

```
composer create-project --no-interaction --prefer-source -s dev drutiny/project-dev drutiny-dev
```

[Drush](http://docs.drush.org/en/master/) is also required. Its not specifically marked as a dependency as the version of Drush to use will depend on the site you're auditing.

```
composer require --global drush/drush
```


## Usage

Drutiny is a command line tool that can be called from the composer vendor bin directory

```
./vendor/bin/drutiny
```

### Finding policies available to run

Drutiny comes with a `policy:list` command that lists all the policies available to audit against.

```
./vendor/bin/drutiny policy:list
```

Policies provided by other packages such as [drutiny/plugin-distro-common](https://github.com/drutiny/plugin-distro-common) will also appear here if they are installed.

### Installing Drutiny Plugins

Additional Drutiny policies, audits, profiles and commands can be installed from composer.

```
$ composer search drutiny
```

For Drupal 8 sites, `drutiny/plugin-drupal-8` would be a good plugin to add.

```
$ composer require drutiny/plugin-drupal-8 2.x-dev
```

### Running an Audit

An audit of a single policy can be run against a site by using `policy:audit` and passing the policy name and site target:

```
./vendor/bin/drutiny policy:audit Drupal-8:PageCacheExpiry @drupalvm.dev
```

The command above would audit the site that resolved to the `@drupalvm.dev` drush alias against the `Drupal-8:PageCacheExpiry` policy.

Some policies have parameters you can specify which can be passed in at call time. Use `policy:info` to find out more about the parameters available for a check.

```
./vendor/bin/drutiny policy:audit -p max_age=600 Drupal-8:PageCacheExpiry @drupalvm.dev
```

Audits are simple self contained classes that are simple to read and understand. Policies are simple YAML files that determine how to use Audit classes. Drutiny can be extended very easily to audit for your own unique requirements. Pull requests are welcome as well, please see the [contributing guide](CONTRIBUTING.md).

### Remediation
Some checks have redemptive capability. Passing the `--remediate` flag into the call with "auto-heal" the site if the check fails on first pass.

```
./vendor/bin/drutiny policy:audit -p max_age=600 --remediate Drupal-8:PageCacheExpiry @drupalvm.dev
```

### Running a profile of checks

A site audit is running a collection of checks that make up a profile. This allows you to audit against a specific standard, policy or best practice. Drutiny comes with some base profiles which you can find using `profile:list`. You can run a profile with `profile:run` in a simlar format to `policy:audit`.

```
./vendor/bin/drutiny profile:run --remediate d8 @drupalvm.dev
```

Parameters can not be passed in at runtime for profiles but are instead predefined by the profile itself.

### Creating your own profiles
The `profile:generate` wizard will help you to produce a profile. stored in the `profiles` directory.

### Customizing policies in profiles
Profiles allow you to define parameters to fine tune policies within. You can do this by specifying parameters listed in `policy:info` in the profile file:

```yaml
title: 10s Page Cache Expiry Profile
policies:
    'Drupal-8:PageCacheExpiry':
        value: 10
```

### Reporting

By default, profile runs report to the console but reports can also be exported in html and json formats.

```
./vendor/bin/drutiny profile:run --remediate --format=html --report-filename=drupalvm-dev.html d8 drush:@drupalvm.dev
```


## Getting help

Because this is a Symfony Console application, you have some other familiar commands:

```
./vendor/bin/drutiny help profile:run
```

In particular, if you use the `-vvv` argument, then you will see all the drush commands, and SSH commands printed to the screen.


## Documentation

You can find more documentation in the [docs](http://drutiny.readthedocs.io/en/latest/) folder.


# Credits

* [Theodoros Ploumis](https://github.com/theodorosploumis) for [creating the logo](https://github.com/drutiny/drutiny/issues/79) for Drutiny.
