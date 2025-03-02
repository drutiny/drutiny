<?php

namespace Drutiny\Policy;

use Drutiny\Attribute\Description;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class Chart
{
    public function __construct(
        #[Description('The machine-name of the ID that can be used as an HTML id attribute.')]
        public readonly string $id,
        #[Description('The title of the graph')]
        public readonly string $title = '',
        #[Description('The height of the graph area set as a CSS style on the <canvas> element.')]
        public readonly ?string $height = null,
        #[Description('The label for the y-axis.')]
        public readonly string $yAxis = '',
        #[Description('The label for the x-axis.')]
        public readonly string $xAxis = '',
        public readonly ?string $bootstrapColumns = null,
        #[Description('	The width of the graph area set as a CSS style on the <canvas> element.')]
        public readonly string $width = '100%',
        #[Description('	An array of css selectors that return the HTML elements whose text will become chart data.')]
        public readonly array|string $series = [],
        public readonly array|string $seriesLabels = [],
        #[Description('An array of colors expressed using RGB syntax. E.g. rgba(52, 73, 94,1.0).')]
        public readonly array $colors = [],
        #[Description('A css selector that returns an array of HTML elements whose text will become labels in a pie chart or x-axis in a bar graph.')]
        public readonly string $labels = '',
        public readonly int $tableIndex = 0,
        public readonly ?string $htmlClass = null,
        // TODO: Make Enum: top, bottom, left, right, none
        #[Description('The position of the legend. Options are: top, bottom, left, right or none (to remove legend).')]
        public readonly string $legend = 'right',
        // TODO: Make Enum: bar, horizontalBar, line, pie, doughnut
        #[Description('The type of chart to render. Recommend bar, pie or doughnut.')]
        public readonly string $type = 'bar',
        #[Description('A boolean to determine if the table used to read the tabular data should be hidden. Defaults to false.')]
        public readonly bool $hideTable = false,
        public readonly bool $stacked = false,
        public readonly bool $maintainAspectRatio = false,
    ) {
    }

    /**
     * Create a chart from array parameters.
     */
    public static function fromArray(array $chart, string $id):self
    {
        $params = ['id' => $id];
        foreach ($chart as $opt => $value) {
            $property = str_replace('-', '', ucwords($opt, '-'));
            $property[0] = strtolower($property[0]);
            if (!property_exists(self::class, $property)) {
                throw new InvalidArgumentException("$property (".gettype($value).") does not exist");
            }
            $params[$property] = $value;
        }
        return new static(...$params);
    }

    /**
     * Create a new Chart object modified from the existing.
     */
    public function with(...$props):self
    {
        $args = array_merge(get_object_vars($this), $props);
        return new static(...$args);
    }

    public function addXaxisLabels(string $css_selector): self
    {
        return $this->with(labels: $css_selector);
    }

    public function addPieLabels(string $css_selector): self
    {
        return $this->with(labels: $css_selector);
    }

    /**
     * Add a series to the existing chart as a new object.
     */
    public function addSeries(string $css_selector):self
    {
        if (!is_array($this->series)) {
            throw new InvalidArgumentException("Cannot add Series to chart with a single string selector for series: " . $this->series);
        }
        $series = $this->series;
        $series[] = $css_selector;
        return $this->with(series: $series);
    }

    /**
     * Add a series to the existing chart as a new object.
     */
    public function addSeriesLabel(string $css_selector):self
    {
        if (!is_array($this->seriesLabels)) {
            throw new InvalidArgumentException("Cannot add Series Label to chart with a single string selector for series label: " . $this->seriesLabels);
        }
        $seriesLabels = $this->seriesLabels;
        $seriesLabels[] = $css_selector;
        return $this->with(seriesLabels: $seriesLabels);
    }
}
