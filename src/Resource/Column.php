<?php 
namespace dottwatson\csv\Resource;

use dottwatson\csv\csv;
use dottwatson\csv\Exception\CSVException;

class Column{

    protected $csv = null;

    protected $values   = [];
    protected $name     = '';
    
    public function __construct($name,csv $csv=null){
        $this->name = $name;
        $this->csv  = $csv;
    }

    public function __call($name,$args){
        if($this->csv && is_callable([$this->csv,$name])){
            return call_user_func_array([$this->csv,$name],$args);
        }        
    }


    public function name(){
        return $this->name;
    }

    public function index(){
        if($this->isOrphan()){
            throw new CSVException("this column in Orphan");
            return false;
        }

        $columns = $this->csv->columns();
        return array_search($this->name,$columns);
    }
    
    public function values(){
        $values = [];
        foreach($this->csv->rows() as $row){
            $values[]=$row->get($this->name);
        }
        
        return $values;
    }

    public function unique(){
        $values = $this->values();
        if($values){
            return array_unique($values);
        }
        return null;
    }

    public function appendTo(csv $csv){

    }

    public function empty(){
        foreach($this->csv->rows() as $row){
            $values[]=$row->set($this->name,'');
        }

        return $this;
    }

    public function remove(){

    }

    protected function isOrphan(){
        return (!$this->csv instanceof csv);
    }
}


?>