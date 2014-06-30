<?php

namespace nitm\helpers;

use yii\base\Behavior;

//class that sets up and retrieves, deletes and handles modifying of contact data
class Response extends Behavior
{
	public static $view;
	public static $controller;
	public static $viewOptions = [
		'content' => '',
		'view' => '@nitm/views/response/index', //The view file
		'args' => [], //the arguments to the view file,
		'options' => [
			'class' => ''
		],
	];
	public static $format;
	public static $forceAjax = false;
	public static $viewPath = '@nitm/views/response/index';
	public static $viewModal = '@nitm/views/response/modal';
	
	protected static $layouts = [
		'column1' => '@nitm/views/layouts/column1'
	];
	
	public static function getFormat()
	{
		switch(empty(static::$format))
		{
			case true:
			static::setFormat();
			break;
		}
		return static::$format;
	}
	
	public static function initContext($controller=null, $view=null)
	{
		static::$controller = !($controller) ? \Yii::$app->controller : $controller;
		static::$view = !($view) ? static::$controller->getView() : $view;
	}
	
	/*
	 * Determine how to return the data
	 * @param mixed $result Data to be displayed
	 */
	public static function render($result=null, $params=null, $partial=true)
	{
		$contentType = "text/html";
		switch(1)
		{
			case \Yii::$app->request->isAjax:
			case static::$forceAjax === true:
			$render = 'renderAjax';
			break;
			
			case $partial == true:
			$render = 'renderPartial';
			break;
			
			default:
			$render = 'render';
			break;
		}
		$params = is_null($params) ? static::$viewOptions : $params;
		$format = (!\Yii::$app->request->isAjax && (static::getFormat() == 'modal')) ? 'html' : static::getFormat();
		switch($format)
		{
			case 'xml':
			//implement handling of XML responses
			$contentType = "application/xml";
			$ret_val = $result;
			break;
			
			case 'html':
			$params['view'] =  empty($params['view']) ? static::$viewPath :  $params['view'];
			$params['options'] = isset(static::$viewOptions['options']) ? static::$viewOptions['options'] : [];
			$ret_val = static::$controller->$render($params['view'], $params['args'], static::$controller);
			break;
			
			case 'modal':
			$params['view'] =  empty($params['view']) ? static::$viewPath :  $params['view'];
			$params['args']['options'] = isset(static::$viewOptions['options']) ? static::$viewOptions['options'] : [];
			$ret_val = static::$controller->$render(static::$controllerModal, 
				[
					'content' => static::$controller->$render($params['view'], $params['args'], static::$controller),
					'footer' => @$params['footer'],
					'title' => \Yii::$app->request->isAjax ? @$params['title'] : '',
					'modalOptions' => @$params['modalOptions'],
				],
				static::$controller
			);
			break;
			
			case 'json':
			$contentType = "application/json";
			$ret_val = $result;
			break;
			
			default:
			$contentType = "text/plain";
			$ret_val = @strip_tags($result['data']);
			break;
		}
		\Yii::$app->response->getHeaders()->set('Content-Type', $contentType);
		return $ret_val;
	}
	
	/*
	 * Get the desired display format supported
	 * @return string format
	 */
	public static function setFormat($format=null)
	{
		$ret_val = null;
		$format = (is_null($format)) ? (!\Yii::$app->request->get('__format') ? null : \Yii::$app->request->get('__format')) : $format;
		switch($format)
		{
			case 'text':
			case 'raw':
			$ret_val = 'raw';
			\Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
			break;
			
			case 'modal':
			$ret_val = $format;
			\Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;
			break;
			
			case 'xml':
			$ret_val = $format;
			\Yii::$app->response->format = \yii\web\Response::FORMAT_XML;
			break;
			
			case 'jsonp':
			$ret_val = $format;
			\Yii::$app->response->format = \yii\web\Response::FORMAT_JSONP;
			break;
			
			case 'json':
			$ret_val = $format;
			\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
			break;
			
			default:
			$ret_val = 'html';
			\Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;
			break;
		}
		static::$format = $ret_val;
		return $ret_val;
	}
	
	/**
	 * Has the user reuqested a specific format?
	 * @return boolean
	 */
	public static function formatSpecified()
	{
		return \Yii::$app->request->get('__format') != null;
	}
	
	/**
	 * Get the layout file
	 * @param string $layout
	 * @return string
	 */
	protected static function getLayoutPath($layout='column1')
	{
		switch(isset(static::$layouts[$layout]))
		{
			case false:
			$layout = 'column1';
			break;
		}
		return static::$layouts[$layout];
	}
}
?>