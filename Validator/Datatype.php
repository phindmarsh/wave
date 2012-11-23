<?php



abstract class Wave_Validator_Datatype {


	protected $params;
	protected $input;

	const COMPARATOR_ARRAY = 1;
	const COMPARATOR_NUMERIC = 2;
	const COMPARATOR_STRING = 3;

	const LENGTH_GT = 'gt';
	const LENGTH_LT = 'lt';

	const TYPE_INT = 'int';
	const TYPE_ARRAY = 'array';
	const TYPE_STRING = 'varchar';

	const MIN_LENGTH = 'min_length';
	const MAX_LENGTH = 'max_length';
	const MEMBER_OF  = 'member_of';
	const UNIQUE     = 'unique';
	const EXISTS     = 'exists';


	public function __construct($params, $input){

		$this->params = $params;
		$this->input = $input;

	}

	public function validate(){

		if(isset($this->params[self::MIN_LENGTH]) && !$this->checkLength($this->params[self::MIN_LENGTH]))
			return Wave_Validator::ERROR_TOO_SHORT;

		if(isset($this->params[self::MAX_LENGTH]) && !$this->checkLength($this->params[self::MAX_LENGTH], self::LENGTH_LT))
			return Wave_Validator::ERROR_TOO_LONG;

		if(isset($this->params[self::MEMBER_OF]) && !$this->checkMembership($this->params[self::MEMBER_OF]))
			return Wave_Validator::ERROR_NOT_IN_ARRAY;

		if(isset($this->params[self::UNIQUE]) && !$this->checkUniqueness($this->params[self::UNIQUE]))
			return Wave_Validator::ERROR_NOT_UNIQUE;

		if(isset($this->params[self::EXISTS]) && !$this->checkExistance($this->params[self::EXISTS]))
			return Wave_Validator::ERROR_NOT_EXISTS;

		return true;
	}

	public function sanitize(){
		return htmlentities($this->input);
	}


	protected function checkLength($limit, $comparator = self::LENGTH_GT, $datatype = null){
		// establish a comparator
		if ($datatype == null) {
            if     ($this->params['type'] === self::TYPE_ARRAY)  { $datatype = self::COMPARATOR_ARRAY;   }
            elseif ($this->params['type'] === self::TYPE_INT)    { $datatype = self::COMPARATOR_NUMERIC; }
            elseif ($this->params['type'] === self::TYPE_STRING) { $datatype = self::COMPARATOR_STRING;  }
            else throw new Wave_Exception("Field type not specified");
		}

		// based on the comparator deduce the correct count to compare
		$count = null;
		if     ($datatype === self::COMPARATOR_ARRAY)   { $count = count($this->input);  }
		elseif ($datatype === self::COMPARATOR_NUMERIC) { $count = $this->input;         }
		elseif ($datatype === self::COMPARATOR_STRING)  { $count = strlen($this->input); }
		else                                             { $count = $this->input;         }

		//if the comparator is callable (is a function), return the result of that instead, otherwise do the standard op

		if     (is_callable($datatype))          { return $datatype($comparator, $this->input, $limit); }
		elseif ($comparator == self::LENGTH_GT) { return $count >= $limit;                             }
		else                                     { return $count <= $limit;                             }
	}

	protected function checkMembership($container){
		return in_array($this->input, $container);
	}

	protected function checkUniqueness($props){

		if(!isset($props['model']) || (!isset($props['property'])))
			throw new Wave_Exception("A class name and a column must be defined for unique field.");
		$q = Wave_DB::get()->from($props['model'])
				 ->where($props['property'], '=', $this->input);

		$obj = $q->fetchRow();

		return ($obj === null);
	}

	protected function checkExistance($props){

		if(!isset($props['model']) || (!isset($props['property'])))
			throw new Wave_Exception("A class name and a column must be defined for exists field.");
		$q = Wave_DB::get()->from($props['model'])
				 ->where($props['property'], '=', $this->input);


		$obj = $q->fetchRow();

		return ($obj !== null);
	}


}


?>