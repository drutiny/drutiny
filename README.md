# Drutiny - automated site auditing

<img src="https://github.com/drutiny/drutiny/raw/3.0.x/assets/logo.png" alt="Drutiny logo" align="right"/>

[![Build Status](https://travis-ci.org/drutiny/drutiny.svg?branch=3.0.x)](https://travis-ci.org/drutiny/drutiny)
[![Latest Stable Version](https://poser.pugx.org/drutiny/drutiny/v/stable)](https://packagist.org/packages/drutiny/drutiny)
[![Total Downloads](https://poser.pugx.org/drutiny/drutiny/downloads)](https://packagist.org/packages/drutiny/drutiny)
[![Latest Unstable Version](https://poser.pugx.org/drutiny/drutiny/v/unstable)](https://packagist.org/packages/drutiny/drutiny)
[![License](https://poser.pugx.org/drutiny/drutiny/license)](https://packagist.org/packages/drutiny/drutiny)

A generic Drupal site auditing and optional remediation tool.


## Installation


You can install Drutiny into your project with [composer](https://getcomposer.org).
Drutiny is a require-dev type dependency.

```
composer require --dev drutiny/drutiny 2.3.*@dev
```


[Drush](http://docs.drush.org/en/master/) is also required. It's not specifically marked as a dependency as the version of Drush to use will depend on the site you're auditing.

```
composer global require drush/drush:dev-master
```


## Usage

Drutiny is a command line tool that can be called from the composer vendor bin directory:

```
./vendor/bin/drutiny
```
If you're running Drutiny as a standalone tool, substitute `./bin/drutiny`.


### Finding policies available to run

Drutiny comes with a `policy:list` command that lists all the policies available to audit against.

```
./vendor/bin/drutiny policy:list
```

Policies provided by other packages such as [drutiny/plugin-distro-common](https://github.com/drutiny/plugin-distro-common) will also appear here, if they are installed.


### Installing Drutiny Plugins

Additional Drutiny policies, audits, profiles and commands can be installed with composer.

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
./vendor/bin/drutiny policy:audit -p value=600 Drupal-8:PageCacheExpiry @drupalvm.dev
```

Audits are self-contained classes that are simple to read and understand. Policies are simple YAML files that determine how to use Audit classes. Therefore, Drutiny can be extended very easily to audit for your own unique requirements. Pull requests are welcome as well, please see the [contributing guide](https://drutiny.github.io/2.3.x/CONTRIBUTING/).

### Running a profile of checks

A site audit is running a collection of checks that make up a profile. This allows you to audit against a specific standard, policy or best practice. Drutiny comes with some base profiles which you can find using `profile:list`. You can run a profile with `profile:run` in a simlar format to `policy:audit`.

```
./vendor/bin/drutiny profile:run --remediate d8 @drupalvm.dev
```

Parameters can not be passed in at runtime for profiles but are instead predefined by the profile itself.


## Getting help

Because this is a Symfony Console application, you have some other familiar commands:

```
./vendor/bin/drutiny help profile:run
```

In particular, if you use the `-vvv` argument, then you will see all the drush commands, and SSH commands printed to the screen.


## Documentation

You can find more documentation in the [docs](https://drutiny.github.io/drutiny) folder.


# Credits

* [Theodoros Ploumis](https://github.com/theodorosploumis) for [creating the logo](https://github.com/drutiny/drutiny/issues/79) for Drutiny.
