<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a news");
$a->addGrant(array('ACCESS', 'NEWS_INSERT'));
$a->setReturn(array(array(
	'id'=>'the news id'
)));

$a->addParam(array(
	'name'=>array('title', 'news_title'),
	'description'=>'The news title.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('description', 'news_description'),
	'description'=>'The news description.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>500,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('content', 'news_content'),
	'description'=>'The news content.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>5000,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('author', 'news_author'),
	'description'=>'The news author.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('language', 'lang', 'news_language'),
	'description'=>'The news language.',
	'optional'=>false,
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
	$title = $a->getParam('title');
	$description = $a->getParam('description');
	$content = $a->getParam('content');
	$author = $a->getParam('author');
	$language = $a->getParam('language');
	
	// =================================
	// INSERT NEWS
	// =================================
	$sql = "INSERT INTO `news` (news_title, news_description, news_content, news_author, news_date, news_language) VALUE ('".security::escape($title)."', '".security::escape($description)."', '".security::escape($content)."', '".security::escape($author)."', UNIX_TIMESTAMP, '{$language}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	$id = $GLOBALS['db']->last_id();
	
	responder::send(array("id"=>id));
});

return $a;

?>