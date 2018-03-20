Drutiny (Drupal Scrutiny) provides an automation layer for auditing and reporting for Drupal (7 or 8) sites.

# Getting Started

Please read through Getting started to "install" Drutiny, then review the usage documentation below.

## Why another site audit tool?

Traditional site audit (e.g. the [checklist API](https://www.drupal.org/project/checklistapi) modules in Drupal rely on having certain modules or code present on the server in order to gather the required metrics. The main issue is that if you fail to even have these modules enabled at all, then no auditing will take place in the first instance. This can be a real issue.

Other extensions (e.g. the [site_audit](https://www.drupal.org/project/site_audit) drush extension) are constrained to running only Drush based checks, and you are limited to only excluding checks, you also cannot customise anything about the checks you do run.

Drutiny is different, all checks are from the outside looking in, and require no special code or modules to be enabled on the remote site. This means you can audit all environments from development to production and not leave any lasting performance degradation. Your definition of best practice can evolve outside your Drupal sites, and the checks that you run against the site will always be up to date.


## Extending Drutiny

Drutiny also integrates with other best of breed tools to ensure that you have maximum flexibility when it comes to running checks, e.g.

* Drush (e.g. check the status of a module, or get a variable value)
* SSH (e.g. filesystem checks, directory size checks)
* [Phantomas](https://github.com/macbre/phantomas) (e.g. check the actual rendering of the site and ensure there are no in-page 404s)

If a particular check pertains to just Drupal 7 or Drupal 8 then it will be namespaced as such. In this fashion you are able to run site audits against either Drupal 7 or Drupal 8 sites using the same Drutiny codebase.
