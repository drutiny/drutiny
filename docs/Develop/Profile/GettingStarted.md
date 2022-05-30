# Profiles

Profiles are a collection of policies that aim to audit a target against a
specific context or purpose. Some examples of profile use cases are:

-   Production-ready Drupal 8 site
-   Organizational policy compliance
-   Security or performance audit

Profiles allow you to run a defined set of polices into a report.

    ./vendor/bin/drutiny profile:run <profile_name> <target>

Reports can also be rendered into HTML or JSON and saved to file.

    ./vendor/bin/drutiny profile:run <profile_name> <target> --format=html -o <filename>

## Creating a Profile

Profiles are YAML files with a file extension of `.profile.yml`. These can be
placed anywhere in the directory you run Drutiny from. It is recommended to
store them in a directory called `Profile`.

## Fields

### title (required)

The title field gives semantic meaning to the collection of policies.

```yaml
title: My custom audit
```

### description

A description of the profiles purpose and why it might be used.

```yaml
description: |
  This profile is to be used to determine if your site is following
  the best practices for security.
```

### policies (required)

A list of policies that make up the profile.

```yaml
policies:
  Drupal-7:NoDuplicateModules: {}
  Drupal-7:OverlayModuleDisabled: {}
  Drupal-7:BlackListPermissions: {}
  Drupal-7:PhpModuleDisabled: {}
```

Policy definitions can contain profile specific overrides for parameters passed
to the policy as well as the severity of the policy in context to the profile.

```yaml
policies:
  Database:Size:
    parameters:
      max_size: 900
    severity: critical
```

**Note:** This is a change from the 2.0.x profile format. Older profiles that
provided default parameters will error.

### dependencies

A list of policies that are run to evaluate the target is appropriate for the
given profile. All policies listed as a dependency must pass for the target to be
considered valid for the profile.

Dependency failures are reported to the Drutiny CLI.
Dependencies are not reported in a successful profile audit.

The dependency declaration follows the same schema as the `policies` declaration.

```yaml
dependencies:
  Database:Size:
    parameters:
      max_size: 900
    severity: critical
```

### include

The include directive allows profiles to be build on top of collections or other
profiles. Each include name should be the machine name of another available profile.

```yaml
include:
  - cloud
  - d8
```

### excluded_policies

This directive allows a profile to exclude policies that were implicitly included
in an included profile defined in the `include` directive.

```yaml
excluded_policies:
  - Drupal-7:BlackListPermissions
  - Drupal-7:CSSAggregation
```

### format

The `format` declaration allows a profile to specify options specific to the
export format of the report (console, HTML or JSON). Based on the format,
the options vary.

Right now there are no specific options for `console` or `json` formats. Only HTML.

    format:
      html:
        template: my-page
        content:
          - heading: My custom section
            body: |
              This is a multiline field that can contain mustache and markdown syntax.
              There are also a variety of variables available to dynamically render
              results.....

### format.html.template

The template to use to theme an HTML report. Defaults to `page`.
To add your own template you need to register a template
directory in `drutiny.yml` and add a template [twig](https://twig.symfony.com/)
file to that directory.

> drutiny.yml:

```yaml
services:
  myDrutinyTemplates:
    class: Twig\Loader\FilesystemLoader
      arguments:
        - path/to/twig/template/dir
      tags: [twig.loader]
```

> path/to/twig/template/dir/my-page.html.twig

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <title>{{ profile.title }} - {{ assessment.uri }}</title>
</head>
<body>
  <h1>{{ profile.title }}</h1>
  <div>
    {{ profile.description | markdown_to_html }}
  </div>
  {% include 'report/page/sections.html.twig' %}

  <footer>
    <p>&copy; Drutiny {{ 'now' | date('Y') }} | {{ "now"|date("F jS, Y") }}</p>
  </footer>
  {% include 'report/page/footer.html.twig' %}
</body>
</html>
```

> myprofile.profile.yml:

```yaml
format:
  html:
    template: my-page.html.twig
```

The configuration example above will register the `path/to/twig/template/dir`
directory. When rendering an HTML report,
Drutiny will look inside `path/to/twig/template/dir`, among other registered
template directories, for a template called `my-page.html.twig`. Note that
multiple template directories can be registered so your template should be
uniquely named.

### format.html.content

Specify the content displayed in an
HTML report and the order that it displays in. Drutiny uses Twig templating for
content rendering and by default will load the contents of [profile.html.twig](https://github.com/drutiny/drutiny/blob/3.4.x/twig/report/profile.html.twig).

The Twig template is made up of `block` sections that Drutiny relies on to build
out the page. If content is not inside a Twig block, then it will not be displayed.

```yaml
format:
  html:
    content: |
      {% block purpose %}
        {{ 'Purpose' | heading }}

        Piping a string through the `heading` twig filter will ensure the HTML
        generated contains classes for better styling of report headings vs
        policy headings.
      {% endblock %}

      This sentence will not be displayed because it is outside of a block.

      {% block period %}
        {{ 'Reporting period' | heading }}

        Period | Date time
        ------ | ---------
        Start | {{ assessment.reportingPeriodStart.format('Y-m-d H:i:s e') }}
        End | {{ assessment.reportingPeriodEnd.format('Y-m-d H:i:s e') }}
      {% endblock %}
```

## Content Variables

These are the variables available to the `format.html.content` template.

### assessment

The assessment variable contains the results of each audited policy in the `results`
property. Each result has properties to test if it was successful, a notice, error
or warning.

```twig
    {# Iterate of each failed result and render its recommendation #}
    {% for result in assessment.results|filter(r => r.isFailure) %}
      {% with result.tokens %}
        {{ include(template_from_string(result.policy.remediation)) | markdown_to_html }}
      {% endwith %}
    {% endfor %}
```

### assessment.results

Each `result` from `assessment.results` is an instance of [AuditResponse](https://github.com/drutiny/drutiny/blob/3.4.x/src/AuditResponse/AuditResponse.php) and has access to the `policy` properties
via the `policy` property. This allows you to render `success`, `failure`,
`warning` and `remediation` messages for a given result using the `template_from_string`
function in twig.

```twig
{% with result.tokens %}
  {{ include(template_from_string(result.policy.success)) | markdown_to_html }}
{% endwith %}
```

The `with` twig tag allow the `result.tokens` array to be accessible as variables
inside the policy template content. This is especially important if your policy
content wishes to use token variables.

### assessment.uri

This is the URI a target was audited with and can be used as a reference for the
target.

### profile

The `profile` variable is an instance of [Profile](https://github.com/drutiny/drutiny/blob/3.4.x/src/Profile.php)
and has access to the profile properties. Namely for content templating purposes,
the profile is most valuable for its `title` and `description` attributes.
