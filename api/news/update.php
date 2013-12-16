<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a news");
$a->addGrant(array('ACCESS', 'NEWS_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('news_id', 'id'),
	'description'=>'The id of the news',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('title', 'news_title'),
	'description'=>'The news title.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('description', 'news_description'),
	'description'=>'The news description.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>500,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('content', 'news_content'),
	'description'=>'The news content.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5000,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('author', 'news_author'),
	'description'=>'The news author.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
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
	$title = $a->getParam('title');
	$description = $a->getParam('description');
	$content = $a->getParam('content');
	$author = $a->getParam('author');
	$language = $a->getParam('language');
	
	$sql = "SELECT news_id FROM news WHERE news_id = {$id}";
	$data = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
	
	if( !$data['news_id'] )
		throw new ApiException("Unknown news", 404, "Unknown news : {$id}");
	
	$set = '';
	if( $title !== null )
		$set .= ", news_title = '".security::escape($title)."'";
	if( $description !== null )
		$set .= ", news_description = '".security::escape($description)."'";
	if( $content !== null )
		$set .= ", news_content = '".security::escape($content)."'";
	if( $author !== null )
		$set .= ", news_author = '".security::escape($author)."'";		
	if( $language !== null )
		$set .= ", news_language = '".security::escape($language)."'";	

	$sql = "UPDATE news SET news_id = news_id {$set} WHERE news_id = {$id}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	responder::send("OK");
});

return $a;

?>