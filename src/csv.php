<?php

declare(strict_types=1);

namespace dottwatson\csv;

use dottwatson\csv\Exception\CSVException;
use dottwatson\csv\Resource\Column;
use dottwatson\csv\Resource\Query;
use dottwatson\csv\Resource\Row;

class csv
{
    /**
     * The rows list
     *
     * @var array
     */
    protected $rows = [];
    /**
     * The columns list
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Default csv configuration
     *
     * @var array
     */
    protected $config = [
        'separator' => ';',
        'enclosure' => '"',
        'header' => true
    ];

    /**
     * Constructor
     *
     * @param array $config The csv base configuration as encloser, separator and header
     */
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * get configuration parameter
     *
     * @param string $name
     *
     * @return mixed
     */
    public function config($name)
    {
        return (isset($this->config[$name]))
            ? $this->config[$name]
            : null;
    }

    /**
     * Create a csv from File
     *
     * @param string $fileName
     * @param array  $config
     *
     * @return csv a csv instance
     */
    public static function createFromFile($fileName, $config = [])
    {
        $instance = new static($config);

        if (is_file($fileName) && is_readable($fileName)) {
            $source = file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $cnt = 0;
            while ($row = array_shift($source)) {
                $row = str_getcsv($row, $instance->config('separator'), $instance->config('enclosure'));

                if ($cnt == 0 && $instance->config('header') == true) {
                    foreach ($row as $columnName) {
                        $instance->appendColumn($columnName);
                    }
                } else {
                    $instance->appendRow($row);
                }
                $cnt++;
            }

            return $instance;
        }
    }

    /**
     * Returns the total rows count
     *
     * @return int
     */
    public function countRows()
    {
        return count($this->rows);
    }

    /**
     * return the total cont columns
     *
     * @return int
     */
    public function countColumns()
    {
        return count($this->columns);
    }

    /**
     * append a new row in the csv
     *
     * @param array $data The array keys must match the column names
     *
     * @return this
     */
    public function appendRow($data)
    {
        $this->rows[] = new Row($data, $this);

        return $this;
    }

    /**
     * Prepend a new row in the csv
     *
     * @param array $data The array keys must match the column names
     *
     * @return this
     */
    public function prependRow($data)
    {
        $row = new Row($data, $this);
        array_unshift($this->rows, $row);

        return $this;
    }

    /**
     * Get row by its index
     *
     * @param int $i
     *
     * @return null|Row
     */
    public function row($i)
    {
        return (isset($this->rows[$i]))
            ? $this->rows[$i]
            : null;
    }

    /**
     * Get all rows
     *
     * @return array
     */
    public function rows()
    {
        return $this->rows;
    }

    /**
     * Get a column by its name
     *
     * @param string $name
     *
     * @return null|Column
     */
    public function column($name)
    {
        $columns = $this->columns();
        $i = array_search($name, $columns, true);
        if ($i !== false) {
            return $this->columns[$i];
        }
    }

    /**
     * append a column at the end of coumns
     *
     * @param string $name
     *
     * @return this
     */
    public function appendColumn($name, $defaultValue = null)
    {
        $columns = $this->columns();
        if (in_array($name, $columns, true)) {
            throw new CSVException("{$name} already exists as column");

            return false;
        }

        $column = new Column($name, $this);
        $this->columns[] = $column;
        foreach ($this->rows() as $row) {
            $row->set($name, (string) $defaultValue);
        }

        return $this;
    }

    /**
     * Remove a column from csv
     *
     * @param string $name
     *
     * @return void
     */
    public function removeColumn($name)
    {
        $column = $this->column($name);

        if (! $column) {
            throw new CSVException("column {$name} does not exists");

            return false;
        }

        $i = $column->index();
        foreach ($this->rows as $row) {
            $row->removeColumnIndex($i);
        }

        unset($this->columns[$i]);
        $this->columns = array_values($this->columns);

        return $this;
    }

    /**
     * Get all columns as array of names
     *
     * @return array
     */
    public function columns()
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $column->name();
        }

        return $columns;
    }

    /**
     * Export data as CSV format according to the instance configuration
     *
     * @param bool $withHeaders add the header row in the export
     *
     * @return string
     */
    public function asCSV($withHeaders = true)
    {
        $data = [];
        $enclosure = $this->config('enclosure');
        $separator = $this->config('separator');

        foreach ($this->rows as $row) {
            $rowArrayData = $row->toArray();
            foreach ($rowArrayData as &$value) {
                $value = (is_numeric($value)) ? $value : "{$enclosure}{$value}{$enclosure}";
            }
            $data[] = implode($separator, $rowArrayData);
        }

        if ($withHeaders) {
            $columns = $this->columns();
            foreach ($columns as &$column) {
                $column = (is_numeric($column)) ? $column : "{$enclosure}{$column}{$enclosure}";
            }
            $columns = implode($separator, $columns);
            array_unshift($data, $columns);
        }

        return implode("\n", $data);
    }

    /**
     * Export csv as JSON string
     *
     * @param int $flags the json_encode flags, if any
     *
     * @return string
     */
    public function asJSON($flags = 0)
    {
        $data = [];
        foreach ($this->rows() as $row) {
            $data[] = $row->toArray();
        }

        return json_encode($data, $flags);
    }

    /**
     * Export CSV as XML
     *
     * @param array $conf The configuration array [root=root node name,row=row node name, column=column node name]
     *
     * @return void
     */
    public function asXML($conf = [])
    {
        $params = [
            'root' => 'csv',
            'row' => 'row',
            'column' => 'column'
        ];

        $params = array_merge($params, $conf);

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<{$params['root']}>\n";
        foreach ($this->rows() as $row) {
            $node = "\t<{$params['row']}>\n";
            $data = $row->toArray();
            foreach ($data as $columnName => $value) {
                $node .= "\t\t<{$params['column']} name=\"{$columnName}\"><![CDATA[{$value}]]></{$params['column']}>\n";
            }
            $node .= "\t</{$params['row']}>\n";
            $xml .= $node;
        }
        $xml .= "</{$params['root']}>";

        return $xml;
    }

    /**
     * Save CSV in a file
     *
     * @param string $fileName
     * @param array  $config   configuration [header=include header row,append=append to existing contents]
     *
     * @return void
     */
    public function saveTo($fileName, $config = [])
    {
        $options = ['header' => true, 'append' => false];
        $options = array_merge($options, $config);
        $data = $this->asCSV($options['header']);
        $flags = ($options['append']) ? FILE_APPEND : 0;
        try {
            file_put_contents($fileName, "{$data}\n", $flags);

            return true;
        } catch (CSVException $e) {
            return false;
        }
    }

    /**
     * Save CSV in a file and returns a the newly create csv file instance.
     *
     * @param string $fileName
     * @param array  $config   configuration [header=include header row,append=append to existing contents]
     *
     * @return void
     */
    public function saveAndLoad($fileName, $config = [])
    {
        $options = ['header' => true, 'append' => false];
        $options = array_merge($options, $config);
        $data = $this->asCSV($options['header']);
        $flags = ($options['append']) ? FILE_APPEND : 0;
        try {
            file_put_contents($fileName, "{$data}\n", $flags);

            return static::createFromFile($fileName, $this->config);
        } catch (CSVException $e) {
            return false;
        }
    }

    /**
     * Filter rows by conditions defined in data
     *
     * @param array $data the filter rules
     *
     * @return Query
     */
    public function filterBy($data = [])
    {
        $instance = new Query($this->rows, $this);

        return $instance->filterBy($data);
    }

    /**
     * Group rows by conditions defined in data
     *
     * @param array $data the filter rules
     *
     * @return Query
     */
    public function groupBy($data = [])
    {
        $instance = new Query($this->rows, $this);

        return $instance->groupBy($data);
    }

    /**
     * Order rows by conditions defined in data
     *
     * @param array $data the filter rules
     *
     * @return Query
     */
    public function orderBy($data = [])
    {
        $instance = new Query($this->rows, $this);

        return $instance->orderBy($data);
    }

    /**
     * Limit the resultset of rows to its value (works as MySQL limit)
     *
     * @param int    $num    The total rows to return
     * @param [type] $offset if not null, the offset of the rows collection
     *
     * @return Query
     */
    public function limit($num = 0, $offset = null)
    {
        $instance = new Query($this->rows, $this);

        return $instance->limit($num, $offset);
    }

    /**
     * Execute closure on each row
     *
     * @param array|Closure|string $callBack
     *
     * @return CSV
     */
    public function eachRow($callBack)
    {
        if (! is_callable($callBack)) {
            throw new Exception('eachRow expect a callable method or function');
        }

        foreach ($this->rows as $rowIndex => $row) {
            call_user_func_array($callBack, [$row, $this]);
        }

        return $this;
    }

    /**
     * Removes a row from csv
     *
     * @param int  $i
     * @param bool $compact if true, it rebuilds all the rows index
     *
     * @return this
     */
    public function removeRow($i, $compact = false)
    {
        $row = $this->row($i);

        if ($row === null) {
            throw new CSVException("row {$i} does not exists");

            return false;
        }
        unset($this->rows[$i]);

        if ($compact) {
            $this->rows = array_values($this->rows);
        }

        return $this;
    }

    /**
     * Removes a row from csv
     *
     * @param bool $compact if true, it rebuilds all the rows index
     *
     * @return this
     */
    public function removeRows($indexes = [], $compact = false)
    {
        foreach ($indexes as $i) {
            $row = $this->row($i);

            if ($row === null) {
                throw new CSVException("row {$i} does not exists");

                return false;
            }
            unset($this->rows[$i]);
        }

        if ($compact) {
            $this->rows = array_values($this->rows);
        }

        return $this;
    }
}
