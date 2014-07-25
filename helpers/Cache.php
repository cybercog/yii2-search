<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;

/*
 * Setup model based caching, as PHP doesn't support serialization of Closures
 */
class Cache extends Model
{
	protected static $cache;
	private static $_cache = [];
	
	/**
	 * Cache function that returns caching object
	 */
	public static function cache()
	{
		if(!isset(static::$cache))
		{
			static::$cache = \Yii::$app->hasProperty('cache') ? \Yii::$app->cache : new \yii\caching\FileCache;
		}
		return static::$cache;
	}
	
	public static function setModel($key, $model)
	{
		static::$_cache[$key] = $model;
	}
	
	public static function exists($key)
	{
		return isset(static::$_cache[$key]);
	}
	
	/**
	 * Get a cached model
	 * @param string $key
	 * @param string $property
	 * @param string $modelClass
	 * @return instanceof $modelClass
	 */
	public static function getModel($key, $property=null, $modelClass=null)
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		//switch(static::$cache->exists($key))
		$ret_val = null;
		switch(isset(static::$_cache[$key]))
		{
			case true:
			$ret_val = static::$_cache[$key];
			//$ret_val = static::$cache->get($key);
			break;
			
			default:
			switch(1)
			{
				case !is_null($property) && !is_null($modelClass):
				$ret_val = is_a(static::$$property, $modelClass::className()) ? static::$$property : new $modelClass;
				//static::$cache->set($key, $ret_val, 1000);
				static::$_cache[$key] = $ret_val;
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	/**
	 * Get a cached array
	 * @param string $key
	 * @param string $property
	 * @return array
	 */
	public static function getModelArray($key, $property=null)
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		//switch(static::$cache->exists($key))
		$ret_val = [];
		switch(isset(static::$_cache[$key]))
		{
			case true:
			$ret_val = static::$_cache[$key];
			//$ret_val = static::$cache->get($key);
			break;
			
			default:
			switch(1)
			{
				case !is_null($property):
				$ret_val = is_array(static::$property) ? static::$property : [];
				//static::$cache->set($key, $ret_val, 1000);
				static::$_cache[$key] = $ret_val;
				break;
			}
			break;
		}
		return $ret_val;
	}
}
?>