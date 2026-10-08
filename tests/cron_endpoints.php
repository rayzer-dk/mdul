<?php
define('DB_PREFIX','test_');
define('DIR_SYSTEM',dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/');
class Controller { public $request;public $response;public $config;public $load;public $registry;public $model_extension_module_import_pro; }
class EndpointConfig { public $values=array();public function get($key){return $this->values[$key]??null;} }
class EndpointResponse { public $data;public function addHeader($s){}public function setOutput($s){$this->data=json_decode($s,true);} }
class EndpointLoader { public function model($route){} }
class EndpointModel { public $calls=array();public function runScheduledImport($id,$dry,$limit,$mode){$this->calls[]=array($id,$dry,$limit,$mode);return array('has_more'=>1,'next_offset'=>100);} }
class EndpointDb { public $queries=array();public function escape($s){return addslashes($s);}public function query($s){$this->queries[]=$s;$rows=strpos($s,'GET_LOCK')!==false?array(array('acquired'=>1)):array();return(object)array('row'=>$rows?$rows[0]:array(),'rows'=>$rows,'num_rows'=>count($rows));} }
class EndpointRegistry { public $db;public $config;public function get($key){return $key==='db'?$this->db:$this->config;} }
require dirname(__DIR__).'/modules/import_export_pro/upload/catalog/controller/extension/module/import_pro.php';
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/catalog/controller/extension/module/supplier_sync_parser_pro.php';
$failures=0;
function endpointCheck($ok,$label){global $failures;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok){$failures++;}}
$i=new ControllerExtensionModuleImportPro();$i->config=new EndpointConfig();$i->response=new EndpointResponse();$i->load=new EndpointLoader();$i->model_extension_module_import_pro=new EndpointModel();
$i->config->values=array('module_import_pro_status'=>1,'module_import_pro_cron_token'=>'secret');
$i->request=(object)array('get'=>array('profile_id'=>2,'scheduled'=>1,'limit'=>50,'run_mode'=>'price_only'),'server'=>array('HTTP_X_CCP_CRON_KEY'=>'secret'));
$i->cronImport();endpointCheck($i->response->data['success'] && $i->model_extension_module_import_pro->calls===array(array(2,false,50,'price_only')),'File cron accepts header and invokes persistent batches');
endpointCheck(!isset($i->response->data['next_url']),'Scheduled response never exposes secret next URL');
$i->request->server['HTTP_X_CCP_CRON_KEY']='wrong';$i->cronImport();endpointCheck(!$i->response->data['success'] && count($i->model_extension_module_import_pro->calls)===1,'Wrong file cron key cannot import');
$i->config->values['module_import_pro_status']=0;$i->cronImport();endpointCheck(!$i->response->data['success'] && count($i->model_extension_module_import_pro->calls)===1,'Disabled file cron cannot import');
$s=new ControllerExtensionModuleSupplierSyncParserPro();$s->config=new EndpointConfig();$s->response=new EndpointResponse();$db=new EndpointDb();$registry=new EndpointRegistry();$registry->db=$db;$registry->config=$s->config;$s->registry=$registry;
$s->config->values=array('module_supplier_sync_parser_pro_status'=>1,'module_supplier_sync_parser_pro_cron_token'=>'secret','module_supplier_sync_parser_pro_stop_queue'=>0);
$s->request=(object)array('get'=>array('supplier_id'=>1,'limit'=>5),'server'=>array('HTTP_X_CCP_CRON_KEY'=>'secret'));
$s->cron();endpointCheck($s->response->data['ok'] && strpos(implode("\n",$db->queries),'GET_LOCK')!==false,'Supplier endpoint invokes protected scheduled cycle');
$before=count($db->queries);$s->config->values['module_supplier_sync_parser_pro_status']=0;$s->cron();endpointCheck(!$s->response->data['ok'] && count($db->queries)===$before,'Disabled supplier endpoint performs no SQL');
$s->config->values['module_supplier_sync_parser_pro_status']=1;$s->request->server['HTTP_X_CCP_CRON_KEY']='wrong';$s->cron();endpointCheck(!$s->response->data['ok'] && count($db->queries)===$before,'Wrong supplier key performs no SQL');
exit($failures?1:0);
