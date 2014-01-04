<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a news");
$a->addGrant(array('ACCESS', 'NEWS_SELECT'));
$a->setReturn(array(array(
	'title'=>'the title of the news', 
	'description'=>'the news description',
	'content'=>'the news content',
	'author'=>'the news author',
	'date'=>'the news date'
	)));
$a->addParam(array(
	'name'=>array('id', 'news', 'news_id'),
	'description'=>'The id of the news',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('limit'),
	'description'=>'Limit the result',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('language', 'lang', 'news_language'),
	'description'=>'The news language.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>2,
	'match'=>request::UPPER
	));
	
$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$id = $a->getParam('id');
	$limit = $a->getParam('limit');
	$language = $a->getParam('language');
	
	if( $limit === null )
		$limit = 5;
	
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $id !== null )
		$where .= " AND news_id = '".security::escape($id)."'";
	if( $language !== null )
		$where .= " AND news_language = '".security::escape($language)."'";
		
	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT * FROM `news` WHERE true {$where} ORDER BY news_id DESC LIMIT 0,{$limit}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$news = array();
	foreach( $result as $r )
	{
		$n['id'] = $r['news_id'];
		$n['title'] = $r['news_title'];
		$n['description'] = $r['news_description'];
		$n['content'] = $r['news_content'];
		$n['date'] = $r['news_date'];
		$n['author'] = $r['news_author'];
		$n['language'] = $r['news_language'];
		
		$news[] = $n;
	}

	responder::send($news);
});

return $a;

?>