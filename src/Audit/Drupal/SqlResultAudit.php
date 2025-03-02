<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Psr\Cache\CacheItemInterface;

/**
 * Audit the first row returned from a SQL query.
 */
#[Parameter(
    name: 'query',
    mode: Parameter::REQUIRED,
    description: 'The SQL query to run. Can use the audit context for variable replace. E.g. {drush.db-name}.',
    type: Type::STRING
)]
#[Parameter(
    name: 'db_level_query',
    type: Type::BOOLEAN,
    default: false,
    description: 'When true, this will cause an error if the SQL query cannot successfully extract field names from the SELECT query.',
)]
#[Parameter(
    name: 'ttl',
    type: Type::INTEGER,
    default: 3600,
    description: 'Cache time-to-live where other policies may reuse the result.',
)]
class SqlResultAudit extends AbstractAnalysis
{
    /**
     * {@inheritdoc}
     */
    protected function gather():void
    {
        $query = $this->getParameter('query');
        $db_level_query = $this->getParameter('db_level_query');
        if (!$db_level_query) {
            if (!preg_match_all('/^SELECT( DISTINCT)? (.*) FROM/', $query, $fields)) {
                throw new \Exception("Could not parse fields from SQL query: $query.");
            }
            $fields = array_map('trim', explode(',', $fields[2][0]));
            foreach ($fields as &$field) {
                if ($idx = strpos($field, ' as ')) {
                    $field = substr($field, $idx + 4);
                } elseif (preg_match('/[ \(\)]/', $field)) {
                    throw new \Exception("SQL query contains an non-table field without an alias: '$field.'");
                }
            }
        }

        // Migrate 2.x queries to 3.x
        $query = strtr($query, [
          ':db-name' => '{drush.db-name}',
          ':default_collation' => '{default_collation}'
        ]);
        $query = $this->interpolate($query);
        $this->logger->debug("Running SQL query '{query}'", ['query' => $query]);
        $result = $this->target->getService('drush')
          ->sqlq($query)
          ->run(function ($output, CacheItemInterface $item) {
              $item->expiresAfter($this->getParameter('ttl'));
              $data = explode(PHP_EOL, $output);
              
              // Convert each line into cells by exploding on tab seperated values (tsv).
              array_walk($data, function (&$line) {
                  $line = array_map('trim', explode("\t", $line));
                if (empty($line) || count(array_filter($line)) == 0) {
                    $line = false;
                }
              });

              // Filter out $line = false.
              // Filter out empty lines.
              // Filter out PHP deprecation messages.
              return array_filter($data, fn ($line) => !is_bool($line) && !empty($line) && strpos($line[0], 'Deprecated: ') !== 0);
          });

        $fields = $this->getFieldsFromSql($query);
        if (!empty($fields)) {
            $result = array_map(function ($row) use ($fields) {
                // Ensure the row is the same length as the fields.
                list($values, ) = array_chunk($row, count($fields));
                // Ensure the keys are the same length as the values.
                list($keys, ) = array_chunk($fields, count($values));
                return array_combine($keys, $values);
            },
            $result);
        }
        $this->set('count', count($result));
        $this->set('results', $result);
        $this->set('first_row', array_shift($result));
    }

    /**
     * Parse out fields from the SQL query.
     *
     * @param  string $query SQL query string.
     * @return array fields parsed from the SQL query.
     */
    protected function getFieldsFromSql(string $query):array
    {
      // If we can parse fields out of the SQL query, we can make the result set
      // become an associative array.
        if (!preg_match_all('/^SELECT( DISTINCT)? (.*) FROM/', $query, $fields)) {
            return [];
        }
        return array_map(function ($field) {
              $field = trim($field);

              // If the field has an alias, use that instead.
            if ($idx = strpos($field, ' as ')) {
                $field = substr($field, $idx + 4);
            }

              // If the field is a function without an alias, raise a warning.
            if (preg_match('/[ \(\)]/', $field)) {
                $this->logger->warning("SQL query contains an non-table field without an alias: '$field.'");
            }
              return $field;
        },
          explode(',', $fields[2][0]));
    }
}
