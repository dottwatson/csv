<?php

namespace dottwatson\csv\Resource;

use dottwatson\csv\csv;

class Query implements \Iterator
{
    private $rows = [];
    private $csv;
    private $index = 0;

    private $queryRules = [];
    private $queryOrders = [];
    private $queryGroups = [];
    private $queryLimits = [];

    private $comparators = [
        '=' => ["\$row->get('%STR') == '%VALUE'", 'is_string'],
        '!=' => ["\$row->get('%STR') != '%VALUE'", 'is_string'],
        '>' => ["\$row->get('%STR') > '%VALUE'", 'is_string'],
        '>=' => ["\$row->get('%STR') >= '%VALUE'", 'is_string'],
        '<' => ["\$row->get('%STR') < '%VALUE'", 'is_string'],
        '<=' => ["\$row->get('%STR') <= '%VALUE'", 'is_string'],
        'contains' => ["strpos(\$row->get('%STR'),'%VALUE') !== false", 'is_string'],
        'not_contains' => ["strpos(\$row->get('%STR'),'%VALUE') === false", 'is_string'],
        'between' => ["\$row->get('%STR') >= '%0' and '%STR' <= '%1'", 'is_array'],
        'not_between' => ["\$row->get('%STR') < '%1' and '%STR' > '%1'", 'is_array'],
        'is' => ["filter_var(\$row->get('%STR'), %VALUE)", 'is_string']
    ];

    private $order = [
        'ASC' => SORT_ASC,
        'DESC' => SORT_DESC,
        'ASC_NUM' => SORT_NUMERIC | SORT_ASC,
        'DESC_NUM' => SORT_NUMERIC | SORT_DESC,
    ];

    public function __construct($rows, csv $csv)
    {
        $this->rows = $rows;
        $this->csv = $csv;
    }

    public function current()
    {
        return $this->rows[$this->index];
    }

    public function next() : void
    {
        $this->index++;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return isset($this->rows[$this->key()]);
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function reverse()
    {
        $this->rows = array_reverse($this->rows);
        $this->rewind();
    }

    /**
     * Filter rows based on data rules
     * example of each rule:
     * ['COLUMN_NAME' , '=' , VALUE ] is equal to `COLUMN_NAME = VALUE`
     * ['COLUMN_NAME' , '!=' , VALUE ] is equal to `COLUMN_NAME != VALUE`
     * ['COLUMN_NAME' , '>' , VALUE ] is equal to `COLUMN_NAME > VALUE`
     * ['COLUMN_NAME' , '>=' , VALUE ] is equal to `COLUMN_NAME >= VALUE`
     * ['COLUMN_NAME' , '<' , VALUE ] is equal to `COLUMN_NAME < VALUE`
     * ['COLUMN_NAME' , '<=' , VALUE ] is equal to `COLUMN_NAME <= VALUE`
     * ['COLUMN_NAME' , 'contains' , VALUE ] is equal to `strpos(COLUMN_NAME,VALUE) !== false`
     * ['COLUMN_NAME' , 'not_contains' , VALUE ] is equal to `strpos(COLUMN_NAME,VALUE) === false`
     * ['COLUMN_NAME' , 'between', [ VALUE_1, VALUE_2 ] ] is equal to `COLUMN_NAME >= VALUE_1 and COLUMN_NAME <= VALUE_2`
     * [ 'COLUMN_NAME' , 'not_between', [ VALUE_1, VALUE_2 ] ] is equal to `COLUMN_NAME < VALUE_1 and COLUMN_NAME > VALUE_2`
     * ['COLUMN_NAME', 'is', VALUE ] is equal to `filter_var(COLUMN_NAME,VALUE)`
     *
     * @param array $data
     *
     * @return self
     */
    public function filterBy($data = [])
    {
        $this->rules = [];
        if (is_array($data) && ! empty($data)) {
            foreach ($data as $conditionInfo) {
                if (is_array($conditionInfo) && count($conditionInfo) > 2) {
                    $fieldName = $conditionInfo[0];
                    $comparator = strtolower((string) $conditionInfo[1]);
                    $comparatorValue = $conditionInfo[2];

                    if (isset($this->comparators[$comparator])) {
                        $conditionValueType = $this->comparators[$comparator][1];
                        $isValid = call_user_func($conditionValueType, $comparatorValue);
                        if ($isValid === true) {
                            $ruleStr = str_replace(['%STR', '%VALUE'], [(string) $fieldName, (string) $comparatorValue], $this->comparators[$comparator][0]);

                            if ($conditionValueType == 'is_array') {
                                $valueArray = array_values($comparatorValue);
                                foreach ($valueArray as $k => $v) {
                                    $ruleStr = str_replace('%' . $k, (string) $v, $ruleStr);
                                }
                            }

                            $this->queryRules[] = $ruleStr;
                        } else {
                            throw new CSVException('Invalid value for rule. Passed ' . (var_export($comparatorValue, true)));

                            return false;
                        }
                    } else {
                        throw new CSVException('Invalid ' . ((string) $conditionInfo[1]) . ' in filter rules');

                        return false;
                    }
                } else {
                    throw new CSVException('Rules must contains at last 3 parameters');

                    return false;
                }
            }
        }

        return $this;
    }

    /**
     * Order rows based on data
     * example: data = [ 'COLUMN_NAME'=>SORT_ASC , 'COLUMN_NAME_2'=>SORT_DESC  ]
     *
     * @param array $data
     *
     * @return self
     */
    public function orderBy($data = [])
    {
        if ($data) {
            foreach ($data as $orderField => $orderDirection) {
                if (! in_array($orderDirection, $this->order, true)) {
                    throw new CSVException("The order Type {$orderDirection} is not valid");

                    return false;
                }

                $this->queryOrders[$orderField] = $orderDirection;
            }
        }

        return $this;
    }

    /**
     * Groups rows by data
     * example: ['COLUMN_NAME','COLUMN_NAME_2']
     *
     * @param array $data
     *
     * @return self
     */
    public function groupBy($data = [])
    {
        if ($data) {
            if (is_string($data)) {
                $this->queryGroups[] = $data;
            } elseif (is_array($data)) {
                foreach ($data as $groupField) {
                    $this->queryGroups[] = $groupField;
                }
            }
        }

        return $this;
    }

    /**
     * Limit the resultset of rows
     * example:
     *      ->limit(1) returns the first row in the matched resultset
     *      ->limit(10,1) returns 1 row starting from array index 10
     *
     * @param int $num
     * @param int $offset
     *
     * @return self
     */
    public function limit($num = 0, $offset = null)
    {
        $this->queryLimits = [$num, $offset];

        return $this;
    }

    /**
     * Execute the query ad returns an array of rows
     *
     * @return array
     */
    public function get()
    {
        $rows = $this->rows;

        if (! $rows) {
            return [];
        }

        //filter
        if ($this->queryRules) {
            $ruleCode = implode(' && ', $this->queryRules);
            foreach ($rows as $k => $row) {
                $allowed = false;
                eval("\$allowed = ({$ruleCode});");

                if (! $allowed) {
                    unset($rows[$k]);
                }
            }

            $rows = array_values($rows);
        }

        //group
        if ($this->queryGroups) {
            $tmpRows = $rows;
            foreach ($tmpRows as &$row) {
                $rowGroupCheck = [];
                $rowReference = $row;

                foreach ($this->queryGroups as $groupName) {
                    $rowGroupCheck[$groupName] = $rowReference->get($groupName);
                }

                $row = $row->toArray();
                $row['__&row'] = $rowReference;
                $row['__&rowCompare'] = $rowGroupCheck;
            }

            $endRows = [];
            while ($matchRow = array_shift($tmpRows)) {
                if (empty($endRows)) {
                    $endRows[] = $matchRow;
                } else {
                    $selectedRows = array_column($endRows, '__&rowCompare');
                    $found = false;
                    foreach ($selectedRows as $selectedRow) {
                        if ($matchRow['__&rowCompare'] === $selectedRow) {
                            $found = true;

                            break;
                        }
                    }
                    if (! $found) {
                        $endRows[] = $matchRow;
                    }
                }
            }

            foreach ($endRows as &$endRow) {
                $endRow = $endRow['__&row'];
            }

            $rows = $endRows;
        }

        //order
        if ($this->queryOrders) {
            $orderData = [];
            $tmpRows = [];

            foreach ($rows as $k => $row) {
                $rowArray = $row->toArray();
                $rowArray['__&row'] = $row;
                $tmpRows[] = $rowArray;
            }

            foreach ($this->queryOrders as $orderName => $orderDirection) {
                $values = [];
                foreach ($tmpRows as $tmpRow) {
                    $values[] = (array_key_exists($orderName, $tmpRow)) ? $tmpRow[$orderName] : null;
                }

                $orderData[] = $values;
                $orderData[] = $orderDirection;
            }

            $orderData[] = $tmpRows;

            array_multisort(...$orderData);

            $tmpRows = array_pop($orderData);

            foreach ($tmpRows as &$row) {
                $row = $row['__&row'];
            }

            $rows = $tmpRows;
        }

        //limit
        if ($this->queryLimits) {
            $num = $this->queryLimits[0];
            $offset = $this->queryLimits[1];

            if ($offset === null) {
                $offset = 0;
            } else {
                $num = $this->queryLimits[1];
                $offset = $this->queryLimits[0];
            }
            $rows = array_slice($rows, $offset, $num);
        }

        return $rows;
    }
}
