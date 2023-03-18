<?php

/*
解析来自https://data.uspto.gov/data3/trademark/dailyxml/applications/的USPTO商标数据
还请参考https://developer.uspto.gov/product/trademark-daily-xml-file-tdxf-applications-assignments-ttab

(c) 2016 Joseph Morris joe at morris.cloud
在MIT许可下使用 https://opensource.org/licenses/MIT

 */

// 更改为您的路径
$path_to_databases = 'C:/Users/YourUserName/trademark-sqlite/tmdb.sqlite3';
$database_connect_string = 'sqlite:'.$path_to_databases.'/tmdb.sqlite3';

$database = new PDO ( $database_connect_string );
// $tmdb->exec ( "DELETE from trademarks" );  // 首先清理数据库，有时取决于您要做什么而必要。

function load_txml_file ( $filename, $tmdb ) { 

  echo ("正在处理 " . $filename . "\n" );
  
  $z = new XMLReader;
  $z->open($filename);  
  
  $doc = new DOMDocument;
  
  while ($z->read() && $z->name !== 'case-file');
  
  $limit = 10000000;  // 减少此项以仅解析特定数量的记录进行测试
  $counter = 0;
  
  while ($z->name === 'case-file' && $counter < $limit ) {
      if ( $counter % 10000 == 0 ) echo $counter . "-";
      $node = simplexml_import_dom($doc->importNode($z->expand(), true));
      
      $serial = (string)($node->{"serial-number"}); // 串行号
      $reg_no = (string)($node->{"registration-number"}); // 注册号
      $ch_xml = $node->{"case-file-header"}; // 案件文件头
      $filing_date = (string)($ch_xml->{"filing-date"}); // 提交日期
      $filing_date = substr_replace($filing_date, "-", 6, 0);
      $filing_date = substr_replace($filing_date, "-", 4, 0);
      $reg_date = (string)($ch_xml->{"registration-date"}); // 注册日期
      $reg_date = substr_replace($reg_date, "-", 6, 0);
      $reg_date = substr_replace($reg_date, "-", 4, 0);
      $mark_text = (string)($ch_xml->{"mark-identification"}); // 标识
      $class_xml = $node->{"classifications"}->{"classification"}; // 分类 TODO：使其适用于多类申请
      $int_class = 0;
      if ( $class_xml != null ) $int_class = $class_xml->{"international-code"}; // 国际分类代码
      // 还有许多其他字段可以添加。从法律上讲，我认为添加最有用的字段是货物和服务描述和存活/死亡。对于其他细节，链接到TSDR可能就足够了。
      $statement = $tmdb->prepare ('INSERT into trademarks ( serial, reg_no, filing_date, reg_date, mark_text, int_class) VALUES ( ?,?,?,?,?,? ) ');
      
      $statement->execute (array ( $serial, $reg_no, $filing_date, $reg_date, $mark_text, $int_class ) );
      $z->next('case-file');  
      $counter++;
    }
  echo ("\n");
}

// 循环遍历目录及其子目录中的所有XML文件
$path = "E:/TrademarkPublicData/TMProcessing/已经提取的xml文件夹";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
$files = array_filter(iterator_to_array($iterator), function($file) {
return $file->isFile() && $file->getExtension() == 'xml';
});

foreach ($files as $file) {
$full_path = $file->getPathname();
load_txml_file($full_path, $database);
}
