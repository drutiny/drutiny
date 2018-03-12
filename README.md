# Drutiny (Drupal Scrutiny)

<img src="assets/logo.png" alt="Drutiny logo" align="right"/>

[![Build Status](https://travis-ci.org/drutiny/drutiny.svg?branch=2.x)](https://travis-ci.org/drutiny/drutiny) [![Latest Stable Version](https://poser.pugx.org/drutiny/drutiny/v/stable)](https://packagist.org/drutiny/drutiny) [![Total Downloads](https://poser.pugx.org/drutiny/drutiny/downloads)](https://packagist.org/drutiny/drutiny) [![Latest Unstable Version](https://poser.pugx.org/drutiny/drutiny/v/unstable)](https://packagist.org/drutiny/drutiny) [![License](https://poser.pugx.org/drutiny/drutiny/license)](https://packagist.org/drutiny/drutiny)

This is a generic Drupal site auditing and optional remediation tool.

## Installation
You can install Drutiny into your project with [composer](https://getcomposer.org). Drutiny is a require-dev type dependency.

```
composer require --dev drutiny/drutiny 2.x
```

Alternately, Drutiny can be built as a standalone tool:

```
composer create-project --no-interaction --prefer-source -s dev drutiny/project-dev drutiny-dev
```

[Drush](http://www.drush.org/en/master/) is also required. Its not specifically marked as a dependency as the version of drush to use will depend on the site you're auditing.

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

### Installing Drutiny Libraries
Additional Drutiny policies, audits, profiles and commands can be installed from composer.

```
$ composer search drutiny
```

For Drupal 8 sites, `drutiny/plugin-drupal-8` would be a good plugin to add.

```
$ composer require drutiny/plugin-drupal-8
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

Audits are simple self contained classes that are simple to read and understand. Policies are simple YAML files that determine how to use Audit classes. Drutiny can be extended very easily to audit for your own unique requirements. Pull requests are welcome as well, please see the [contributing guide](./CONTRIBUTING.md).

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

## Why another site audit tool?

Traditional site audit (e.g. the [checklist API](https://www.drupal.org/project/checklistapi) modules in Drupal rely on having certain modules or code present on the server in order to gather the required metrics. The main issue is that if you fail to even have these modules enabled at all, then no auditing will take place in the first instance. This can be a real issue.

Other extensions (e.g. the [site_audit](https://www.drupal.org/project/site_audit) drush extension) are constrained to running only Drush based checks, and you are limited to only excluding checks, you also cannot customise anything about the checks you do run.

This tool is different, all checks are from the outside looking in, and require no special code or modules to be enabled on the remote site. This means you can audit all environments from development to production and not leave any lasting performance degradation. Your definition of best practice can evolve outside your Drupal sites, and the checks that you run against the site will always be up to date. Druntiny also integrates with other best of breed tools to ensure that you have maximum flexibility when it comes to running checks, e.g.

* Drush (e.g. check the status of a module, or get a variable value)
* SSH (e.g. filesystem checks, directory size checks)
* [Phantomas](https://github.com/macbre/phantomas) (e.g. check the actual rendering of the site and ensure there are no in-page 404s)

If a particular check pertains to just Drupal 7 or Drupal 8 then it will be namespaced as such. In this fashion you are able to run site audits against either Drupal 7 or Drupal 8 sites using the same Drutiny codebase.


## Getting help

Because this is a Symfony Console application, you have some other familiar commands:

```
./vendor/bin/drutiny help profile:run
```

In particular, if you use the `-vvv` argument, then you will see all the drush commands, and SSH commands printed to the screen.

## Documentation

You can find more documentation in the [docs](https://github.com/drutiny/drutiny/tree/2.x/docs) folder.

# Credits

* [Theodoros Ploumis](https://github.com/theodorosploumis) for [creating the logo](https://github.com/seanhamlin/drutiny/issues/79) for Drutiny.
