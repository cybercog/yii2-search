<?php
namespace nitm\search\traits;

/**
 * Traits defined for expanding query scopes until yii2 resolves traits issue
 */
trait SearchTrait {
	
	public $text;
	public $filter = [];
	public $expand = 'all';
	
	public $primaryModel;
	public $primaryModelClass;
	public $useEmptyParams;
	
	public $queryOptions = [];
	/**
	 * Should wildcards be used for text searching?
	 */
	public $booleanSearch;
	/**
	 * Should the or clause be used?
	 */
	public $inclusiveSearch;
	public $exclusiveSearch;
	public $mergeInclusive;
	
	public static $sanitizeType = true; 
	public static $namespace = '\nitm\models\\';
	
	protected $dataProvider;
	protected $conditions = [];
	
	public function scenarios()
	{
		return ['default' => $this->attributes()];
	}
	
	public function __set($name, $value)
	{
		try {
			parent::__set($name, $value);
		} catch(\Exception $error) {
			$this->{$name} = $value;
		}
	}
	
	public function __get($name)
	{
		try {
			$ret_val = parent::__get($name);
		} catch(\Exception $error) {
			if($this->hasProperty($name) && !empty($this->{$name}))
				$ret_val = $this->{$name};
			else
				$ret_val = $this->hasAttribute($name) ? $this->getAttribute($name) : null;
		}
		return $ret_val;
	}

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
		$ret_val = [];
		foreach($this->attributes() as $attr)
		{
			$ret_val[$attr] = \Yii::t('app', $this->properName($attr));
		}
		return $ret_val;
	}

    public function search($params=[])
    {
		$this->restart();
		$params = $this->filterParams($params);
        if (!($this->load($params[$this->primaryModel->formName()], false) && $this->validate())) {
			$this->addQueryOptions();
            return $this->dataProvider;
        }
		
		foreach($params[$this->primaryModel->formName()] as $attr=>$value)
		{
			if(isset($this->primaryModel->getTableSchema()->columns[$attr]))
			{
				$column = $this->primaryModel->getTableSchema()->columns[$attr];
				switch($column->phpType)
				{
					case 'integer':
					case 'boolean':
					case 'double':
					case 'array':
					$this->addCondition($column->name, $value);
					break;
					
					case 'string':
					$this->addCondition($column->name, $value, $this->booleanSearch);
					break;
				}
			}
		}
		$this->addConditions();
		$this->addQueryOptions();
        return $this->dataProvider;
    }
	
	/**
	 * Overriding default find function
	 */
	protected static function findInternal($className, $model=null, $options=null)
	{
		$query = \Yii::createObject($className, [get_called_class()]);
		if(is_object($model))
		{
			if(!empty($model->withThese))
				$query->with($model->withThese);
			foreach($model->queryFilters as $filter=>$value)
			{
				switch(strtolower($filter))
				{
					case 'select':
					case 'indexby':
					case 'orderby':
					if(is_string($value) && ($value == 'primaryKey'))
					{
						unset($model->queryFilters[$filter]);
						$query->$filter(static::primaryKey()[0]);
					}
					break;
				}
			}
			static::applyFilters($query, $model->queryFilters);
		}
		return $query;
	}
	
	public function restart()
	{
		$oldType = $this->type();
		if(!$this->primaryModel)
		{
			$class = $this->getPrimaryModelClass();
			if(class_exists($class)) 
				$options = [];
			else {
				$class = "\\nitm\search\\".ucFirst($this->engine)."Search";
				$options = [
					'indexType' => $this->type(),
				];
			}
			if($this->enine == 'db')
				$options['noDbInit'] = true;
			$this->primaryModel = new $class($options);
		}
        $query = $this->primaryModel->find($this);
        $this->dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
        ]);
		$this->conditions = [];
		$this->setIndexType($oldType);
		return $this;
	}
	
	public function getPrimaryModelClass()
	{
		if(!isset($this->primaryModelClass))
			$this->setPrimaryModelClass();
		return $this->primaryModelClass;
	}
	
	public function setPrimaryModelClass($class=null)
	{
		if(!is_null($class) && class_exists($class))
			$this->primaryModelClass = $class;
		else {
			if(!isset($this->primaryModelClass))
			{
				$class = $this->getModelClass($this->properClassName(static::formName()));
				$this->primaryModelClass = class_exists($class) ? $class : static::className();
			}
			else
				$this->primaryModelClass = static::className();
		}
	}
	
	public function getModelClass($class)
	{
		return rtrim(static::$namespace, '\\').'\\'.array_pop(explode('\\', $class));
	}
	
	public static function useSearchClass($callingClass)
	{
		return strpos(strtolower($callingClass), 'models\search') !== false;
	}
	
	protected function addConditions()
	{
		foreach($this->conditions as $type=>$condition)
		{
			$where = ($this->exclusiveSearch) ? 'andWhere' : $type.'Where';
			array_unshift($condition, $type);
			$this->dataProvider->query->$where($condition);
		}
	}

    protected function addCondition($attribute, $value, $partialMatch=false)
    {
        if (($pos = strrpos($attribute, '.')) !== false) {
            $modelAttribute = substr($attribute, $pos + 1);
        } else {
            $modelAttribute = $attribute;
        }
        if (is_string($value) && trim($value) === '') {
            return;
        }
		switch(1)
		{
			case is_numeric($value) && !$partialMatch:
			case is_bool($value) && !$partialMatch:
			case is_array($value) && !$partialMatch:
            switch($this->inclusiveSearch && !$this->exclusiveSearch)
			{
				case true:
				$this->conditions['or'][] = [$attribute => $value];
				break;
				
				default:
				$this->conditions['and'][] = [$attribute => $value];
				break;
			}
			break;
			
			default:
			switch($partialMatch) 
			{
				case true:
				$attribute = "LOWER(".$attribute.")";
				$value = $this->expand($value);
				switch($this->inclusiveSearch)
				{
					case true:
					$this->conditions['or'][] = ['or like', $attribute, $value, false];
					break;
					
					default:
					$this->conditions['and'][] = ['and like', $attribute, $value, false];
					break;
				}
				break;
				
				default:
            	$this->conditions['and'][] = [$attribute => $value];
				break;
			}
			break;
		}
	}
	
	protected function expand($values)
	{
		$values = (array)$values;
		foreach($values as $idx=>$value)
		{
			switch($this->expand)
			{
				case 'right':
				$value = $value."%";
				break;
				
				case 'left':
				$value = "%".$value;
				break;
				
				case 'none':
				$value = $value;
				break;
				
				default:
				$value = "%".$value."%";
				break;
			}
			$values[$idx] = strtolower($value);
		}
		return sizeof($values) == 1 ? array_pop($values) : $values;
	}
	
	private function setProperties($names=[], $values=[])
	{
		$names = is_array($names) ? $names : [$names];
		$values = is_array($values) ? $values : [$values];
		switch(sizeof($names) == sizeof($values))
		{
			case true:
			foreach($names as $idx=>$name)
			{
				$this->{$name} = $values[$idx];
			}
			break;
		}
	}
	
	private function addQueryOptions()
	{
		foreach($this->queryOptions as $type=>$queryOpts)
		{
			switch(strtolower($type))
			{
				case 'where':
				case 'andwhere':
				case 'orwhere':
				case 'limit':
				case 'with':
				case 'orderby':
				case 'indexby':
				case 'groupby':
				case 'addgroupby':
				case 'join':
				case 'leftjoin':
				case 'rightjoin':
				case 'innerjoin':
				case 'having':
				case 'andhaving':
				case 'orhaving':
				case 'union':
				case 'select':
				$this->dataProvider->query->$type($queryOpts);
				break;
			}
		}
	}
	
	/**
	 * Filter the parameters and remove some options
	 */
	private function filterParams($params=[])
	{
		$params = isset($params[$this->primaryModel->formName()]) ? $params[$this->primaryModel->formName()] : (is_array($params) ? $params : []);
		foreach($params as $name=>$value)
		{
			switch($name)
			{
				case 'filter':
				foreach($value as $filterName=>$filterValue)
				{
					switch($filterName)
					{
						case 'exclusive':
						$this->exclusiveSearch = (bool)$filterValue;
						break;
						
						case 'sort':
						$direction = isset($params['order']) ? $params['order'] : SORT_DESC;
						$this->dataProvider->query->orderBy([$filterValue => $direction]);
						$this->useEmptyParams = true;
						break;
					}
				}
				unset($params['filter']);
				break;
				
				case 'text':
				case 'q':
				if(!empty($value)) 
				{
					$this->text = $value;
					$params = array_merge((array)$params, $this->getTextParam($value));
				}
				unset($params[$name]);
				break;
			}
		}
		return $this->getParams($params, $this->useEmptyParams);
	}
	
	protected function getTextParam($value)
	{
		$params = [];
		$this->mergeInclusive = true;
		foreach($this->primaryModel->getTableSchema()->columns as $column)
		{
			switch($column->phpType)
			{
				case 'string':
				case 'datetime':
				$params[$column->name] = isset($params[$column->name]) ? $params[$column->name] : $value;
				break;
			}
		}
		return $params;
	}
	
	protected function getParams($params)
	{
		$params = array_intersect_key($params, array_flip($this->attributes()));
		$this->exclusiveSearch = !isset($this->exclusiveSearch) ? (!(empty($params) && !$this->useEmptyParams)) : $this->exclusiveSearch;
		if(sizeof($this->attributes()) >= 1)
			$params = (empty($params) && !$this->useEmptyParams) ? array_combine($this->attributes(), array_fill(0, sizeof($this->attributes()), '')) : $params;
		if(sizeof($params) >= 1) $this->setProperties(array_keys($params), array_values($params));
		$params = [$this->primaryModel->formName() => $params];
		return $params;
	}
	
	/**
	 * This function properly maps the object to the correct class
	 */
	protected static function instantiateInternal($attributes, $type=null)
	{
		$type = isset($attributes['_type']) ? $attributes['_type'] : $type;
		if(!is_null($type))
			$properName = \nitm\models\Data::properClassName($type);
		else 
			$properName = static::formName();
			
		$class = rtrim(static::$namespace, '\\').'\\search\\'.$properName;
		
		if(!class_exists($class))
			$class = static::className();

		$model = new $class();
		$model->setAttributes($attributes, false);
		static::normalize($model, true);
		return $model;
	}
	
	/**
	 * Convert some common properties
	 * @param array $item
	 * @param boolean decode the item
	 * @return array
	 */
	public static function normalize(&$item, $decode=false, $columns=null)
	{
		$columns = is_array($columns) ? $columns : static::columns();
		
		foreach((array)$item as $f=>$v)
		{
			if(!isset($columns[$f]))
				continue;
			$info = \yii\helpers\ArrayHelper::toArray($columns[$f]);
			switch(array_shift(explode('(', $info['dbType'])))
			{
				case 'tinyint':
				$item[$f] = $info['dbType'] == 'tinyint(1)' ? (boolean)$v : $v;
				break;
				
				case 'blob':
				case 'longblob':
				case 'mediumblob':
				unset($item[$f]);
				continue;
				break;
				
				case 'timestamp':
				/**
				 * This is most likely a timestamp behavior.
				 * Convert it to a time value here
				 */
				if(is_object($v))
					$item[$f] = time();
				break;
				
				case 'text':
				case 'varchar':
				case 'string':
				if(is_array($v)) {
					$item[$f] = static::normalize($v, $decode, $columns);
				}
				/*else {
					$args = [$v, ENT_COMPAT|ENT_HTML5, ini_get("default_charset")];
					if($decode)
						$func = 'html_entity_decode';
					else
					{
						$func = 'htmlentities';
						$args[] = false;
					}
					$item[$f] = call_user_func_array($func, $args);
				}*/
				break;
			}
		}
		return $item;
	}
}
?>