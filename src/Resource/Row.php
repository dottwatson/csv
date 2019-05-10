<?php

namespace dottwatson\csv\Resource;

use dottwatson\csv\csv;
use dottwatson\csv\CSVException;

class Row
{
    /**
     * The parent CSV instance
     *
     * @var null|object
     */
    protected $csv;

    /**
     * The row values
     *
     * @var array
     */
    protected $values = [];

    /**
     * Undocumented function
     *
     * @param array $data the data for row
     * @param csv   $csv
     */
    public function __construct($data = [], csv $csv = null)
    {
        $this->csv = $csv;

        if ($this->isOrphan()) {
            throw new CSVException('Define a csv handler for this row');

            return;
        }

        $columns = $this->csv->columns();
        $countCols = count($columns);

        $newRow = [];
        for ($x = 0; $x < $countCols; $x++) {
            $columnName = $columns[$x];
            if (isset($data[$columnName])) {
                $newRow[$columnName] = $data[$columnName];
            } elseif (isset($data[$x])) {
                $newRow[$columnName] = $data[$x];
            } else {
                $newRow[$columnName] = '';
            }
        }

        $newRow = array_values($newRow);
        $this->values = $newRow;
    }

    public function __call($name, $args)
    {
        if ($this->csv && is_callable([$this->csv, $name])) {
            return call_user_func_array([$this->csv, $name], $args);
        }
    }

    /**
     * Getter for get a value Es. echo $row->column1;
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Setter for set a value Es. $row->column1 = 'test';
     *
     * @param string $name
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Returns the row index
     *
     * @return int
     */
    public function index()
    {
        return array_search($this, $this->csv->rows(), true);
    }

    /**
     * Set a column value for current row
     *
     * @param string $name
     *
     * @return Row
     */
    public function set($name, $value)
    {
        $column = $this->csv->column($name);
        if ($column) {
            $index = $column->index();
            $this->values[$index] = $value;
        }

        return $this;
    }

    /**
     * Get a column value for current row
     *
     * @param string $name the column name;
     *
     * @return mixed
     */
    public function get($name)
    {
        $column = $this->csv->column($name);
        if ($column) {
            $index = $column->index();

            return $this->values[$index];
        }
    }

    /**
     * Append this row into another csv instance
     *
     * @return void
     */
    public function appendTo(csv $csv)
    {
        $rowData = $this->toArray();

        $csv->appendRow($rowData);

        return $csv->countRows();
    }

    /**
     * Prepend this row into another csv instance
     *
     * @return void
     */
    public function prependTo(csv $csv)
    {
        $rowData = $this->toArray();

        $csv->prependRow($rowData);

        return 0;
    }

    /**
     * Clean all values of this row
     *
     * @return self
     */
    public function empty()
    {
        foreach ($this->values as &$value) {
            $value = '';
        }

        return $this;
    }

    /**
     * Remove this row from csv
     *
     * @return
     */
    public function remove($compact = false)
    {
        $index = $this->index();
        $this->csv->removeRow($index, $compact);
    }

    public function removeColumnIndex($i)
    {
        unset($this->values[$i]);
        $this->values = array_values($this->values);
    }

    public function toArray()
    {
        $columns = $this->csv->columns();

        return array_combine($columns, $this->values);
    }

    protected function isOrphan()
    {
        return ! $this->csv instanceof csv;
    }
}
