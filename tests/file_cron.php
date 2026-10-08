<?php
$path=dirname(__DIR__).'/modules/import_export_pro/upload/system/library/import_pro/cron_runner.php';
if (!is_file($path)) { echo 'FAIL Persistent file cron runner is missing'.PHP_EOL; exit(1); }
require $path;
class FileCronDb { public $released=0; public function escape($s) { return addslashes($s); } public function query($s) { if (strpos($s,'RELEASE_LOCK')!==false) {$this->released++;} return (object)array('row'=>array('acquired'=>1)); } }
$dir=sys_get_temp_dir().'/ccp_file_cron_'.bin2hex(random_bytes(5));mkdir($dir);
$source=$dir.'/input.csv';file_put_contents($source,"code;price\n0001;100\n0002;200\n0003;300\n");
$profile=array('profile_id'=>3,'format'=>'csv','source_type'=>'file','source_path'=>$source);
$db=new FileCronDb();$runner=new ImportProCronRunner($db,$dir.'/storage');
$offsets=array();$bodies=array();$failure=false;
$prepare=function($p) use ($source) { return $source; };
$apply=function($snapshot,$offset,$limit,$started) use (&$offsets,&$bodies,&$failure) { if ($failure) {throw new RuntimeException('Injected batch failure');} $offsets[]=$offset;$bodies[]=file_get_contents($snapshot); return array('has_more'=>$offset<2,'next_offset'=>$offset<2?$offset+2:0,'processed'=>$offset<2?2:1,'errors'=>0); };
$a=$runner->run($profile,'price_only',false,2,$prepare,$apply);
file_put_contents($source,"code;price\nOTHER;999\n");
$failure=true;try {$runner->run($profile,'price_only',false,2,$prepare,$apply);}catch (RuntimeException $e) {}$failure=false;
$b=$runner->run($profile,'price_only',false,2,$prepare,$apply);
$c=$runner->run($profile,'price_only',false,2,$prepare,$apply);
$checks=array('Cursor resumes across cron calls'=>$offsets===array(0,2,0),'Frozen source survives price file replacement'=>$bodies[0]===$bodies[1] && $bodies[2]!==$bodies[1],'Failed batch does not advance cursor'=>count($offsets)===3,'Lock is released on success and failure'=>$db->released===4,'Completed cycle reports completion'=>!$b['has_more']);
$failure_count=0;foreach($checks as $label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok){$failure_count++;}}
foreach (glob($dir.'/storage/*') as $file) {unlink($file);}rmdir($dir.'/storage');unlink($source);rmdir($dir);
exit($failure_count?1:0);
