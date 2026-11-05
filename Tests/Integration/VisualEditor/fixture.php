<?php
use TYPO3\CMS\Core\Core\{Bootstrap,SystemEnvironmentBuilder};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
$loader=require getcwd().'/vendor/autoload.php';
SystemEnvironmentBuilder::run(1,SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($loader);Bootstrap::loadExtTables();
Bootstrap::initializeBackendUser(TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication::class);Bootstrap::initializeBackendAuthentication();
$pool=GeneralUtility::makeInstance(ConnectionPool::class);
function dh($data,$cmd=[]){$h=GeneralUtility::makeInstance(DataHandler::class);$h->start($data,$cmd);$h->process_datamap();$h->process_cmdmap();if($h->errorLog)throw new RuntimeException(json_encode($h->errorLog));return $h;}
$mode=$argv[1]??'inspect';
if($mode==='inspect'){
 foreach(['desiderio_tabs','desiderio_pricingthreetier','desiderio_gallery','desiderio_featurelist','desiderio_datatable'] as $type){foreach($GLOBALS['TCA']['tt_content']['types'][$type]['columnsOverrides']??[] as $field=>$c){if(in_array($c['config']['type']??$GLOBALS['TCA']['tt_content']['columns'][$field]['config']['type']??'', ['inline','file']))echo json_encode([$type,$field,array_replace($GLOBALS['TCA']['tt_content']['columns'][$field]['config'],$c['config']??[])]),"\n";}}
 echo json_encode(['galleryImage'=>$GLOBALS['TCA']['gallery_items']['columns']['image']['config']??null]);exit;
}
if($mode==='fixture'){
 if(file_exists('var/ve-integration/fixture.json'))throw new RuntimeException('fixture exists');
 $pass=bin2hex(random_bytes(20)).'!Aa9';
 $tables=array_keys($GLOBALS['TCA']);$excludes=[];foreach($GLOBALS['TCA'] as $t=>$tc){foreach($tc['columns']??[] as $f=>$c){if($c['exclude']??false)$excludes[]="$t:$f";}}
 $h=dh(['pages'=>['NEWpage'=>['pid'=>8430,'title'=>'VEE disposable integration','slug'=>'/content-types/visual-editor-inline/integration-20261105','nav_hide'=>1,'no_search'=>1,'hidden'=>0,'perms_everybody'=>31,'TSconfig'=>"tx_visualeditorenhancements.inlineRecords.enabled = 1\n"]], 'be_groups'=>['NEWgroup'=>['pid'=>0,'title'=>'VEE disposable integration 20261105','groupMods'=>'web_edit,web_layout,web_list,user_setup','tables_select'=>implode(',',$tables),'tables_modify'=>implode(',',array_diff($tables,['be_users','be_groups','sys_workspace'])),'non_exclude_fields'=>implode(',',$excludes),'explicit_allowdeny'=>'tt_content:CType:*:ALLOW','allowed_languages'=>'0,1,2,3','workspace_perms'=>1]],'be_users'=>['NEWadmin'=>['pid'=>0,'username'=>'vee_integration_admin_20261105','password'=>$pass,'admin'=>1,'disable'=>0,'lang'=>'en']]]);
 $page=$h->substNEWwithIDs['NEWpage'];$group=$h->substNEWwithIDs['NEWgroup'];$admin=$h->substNEWwithIDs['NEWadmin'];
 dh(['be_groups'=>[$group=>['db_mountpoints'=>(string)$page]],'be_users'=>['NEWeditor'=>['pid'=>0,'username'=>'vee_integration_editor_20261105','password'=>$pass,'admin'=>0,'disable'=>0,'usergroup'=>(string)$group,'lang'=>'de']]]);
 $editor=$pool->getConnectionForTable('be_users')->fetchOne('SELECT uid FROM be_users WHERE username=?',['vee_integration_editor_20261105']);
 $h=dh(['sys_workspace'=>['NEWworkspace'=>['pid'=>0,'title'=>'VEE disposable integration 20261105','adminusers'=>'be_users_'.$admin,'members'=>'be_users_'.$editor]]]);$ws=$h->substNEWwithIDs['NEWworkspace'];
 foreach([$admin,$editor] as $id)$pool->getConnectionForTable('be_users')->update('be_users',['uc'=>serialize(['tx_visualeditor_inlineRecords'=>1,'tx_visualeditor_fieldChooserMode'=>'tabs','tx_visualeditor_showLibrary'=>1,'tx_visualeditor_showContextButtons'=>1])],['uid'=>$id]);
 $h=dh(['tt_content'=>['NEWtabs'=>['pid'=>$page,'CType'=>'desiderio_tabs','header'=>'VEE disposable tabs','desiderio_tabs_items'=>'NEWone,NEWtwo'], 'NEWlist'=>['pid'=>$page,'CType'=>'desiderio_featurelist','header'=>'VEE disposable list']], 'tabs_items'=>['NEWone'=>['pid'=>$page,'tab_label'=>'Integration One','tab_content'=>'<p>First disposable panel</p>'],'NEWtwo'=>['pid'=>$page,'tab_label'=>'Integration Two','tab_content'=>'<p>Second disposable panel</p>']]]);
 $fixture=['page'=>$page,'group'=>$group,'admin'=>$admin,'editor'=>(int)$editor,'workspace'=>$ws,'records'=>$h->substNEWwithIDs,'password'=>$pass];file_put_contents('var/ve-integration/fixture.json',json_encode($fixture,JSON_PRETTY_PRINT));chmod('var/ve-integration/fixture.json',0600);unset($fixture['password']);echo json_encode($fixture,JSON_PRETTY_PRINT);exit;
}
if($mode==='extra'){
 $f=json_decode(file_get_contents('var/ve-integration/fixture.json'),true);$pid=$f['page'];
 $h=dh(['tt_content'=>['NEWcheck'=>['pid'=>$pid,'CType'=>'desiderio_featurechecklist','header'=>'VEE disposable checklist','desiderio_featurechecklist_items'=>'NEWcheckitem'],'NEWlegal'=>['pid'=>$pid,'CType'=>'desiderio_legallinks','header'=>'VEE disposable legal','desiderio_legallinks_link_items'=>'NEWlegalitem'],'NEWtable'=>['pid'=>$pid,'CType'=>'desiderio_datatable','header'=>'VEE disposable nested table','desiderio_datatable_column_definitions'=>'NEWcol','desiderio_datatable_rows'=>'NEWrow'],'NEWshared'=>['pid'=>$pid,'CType'=>'desiderio_heroproduct','header'=>'VEE disposable shared']], 'feature_checklist_items'=>['NEWcheckitem'=>['pid'=>$pid,'title'=>'Baseline checklist']], 'legal_links_link_items'=>['NEWlegalitem'=>['pid'=>$pid,'label'=>'Baseline legal']], 'data_table_columns'=>['NEWcol'=>['pid'=>$pid,'column_label'=>'Column']], 'data_table_rows'=>['NEWrow'=>['pid'=>$pid,'row_label'=>'Row','cells'=>'NEWcell']], 'data_table_cells'=>['NEWcell'=>['pid'=>$pid,'value'=>'Cell']]]);
 $f['records']=array_merge($f['records'],$h->substNEWwithIDs);file_put_contents('var/ve-integration/fixture.json',json_encode($f,JSON_PRETTY_PRINT));
 $types=array_keys($GLOBALS['TCA']['tt_content']['types']);dh(['be_groups'=>[$f['group']=>['explicit_allowdeny'=>implode(',',array_map(fn($t)=>'tt_content:CType:'.$t.'',$types))]]]);
 echo json_encode($h->substNEWwithIDs);
}
if($mode==='visible'){$f=json_decode(file_get_contents('var/ve-integration/fixture.json'),true);$rows=$pool->getConnectionForTable('tt_content')->fetchAllAssociative('SELECT uid FROM tt_content WHERE pid=? AND deleted=0',[$f['page']]);$data=[];foreach($rows as $row)$data['tt_content'][$row['uid']]=['hidden'=>0];dh($data);echo 'Fixture elements visible';}
if($mode==='settings'){
 $f=json_decode(file_get_contents('var/ve-integration/fixture.json'),true);$id=$f[$argv[2]]??0;if(!in_array($id,[$f['admin'],$f['editor']],true))throw new RuntimeException('Fixture identity only');
 $pool->getConnectionForTable('be_users')->update('be_users',['uc'=>serialize(['tx_visualeditor_inlineRecords'=>1,'tx_visualeditor_fieldChooserMode'=>$argv[3],'tx_visualeditor_showLibrary'=>1,'tx_visualeditor_showContextButtons'=>1]),'workspace_id'=>(int)($argv[4]??0)],['uid'=>$id]);echo 'Fixture preferences set';
}
if($mode==='version'){
 $f=json_decode(file_get_contents('var/ve-integration/fixture.json'),true);$GLOBALS['BE_USER']->workspace=$f['workspace'];
 $conn=$pool->getConnectionForTable('tt_content');
 if(!$conn->count('*','tt_content',['t3ver_oid'=>$f['records']['NEWcheck'],'t3ver_wsid'=>$f['workspace']]))dh([],['tt_content'=>[$f['records']['NEWcheck']=>['version'=>['action'=>'new','treeLevels'=>0]]]]);
 $conn=$pool->getConnectionForTable('feature_checklist_items');$children=$conn->fetchFirstColumn('SELECT uid FROM feature_checklist_items WHERE pid=? AND t3ver_wsid=0 AND deleted=0',[$f['page']]);
 foreach($children as $uid){if(!$conn->count('*','feature_checklist_items',['t3ver_oid'=>$uid,'t3ver_wsid'=>$f['workspace']]))dh([],['feature_checklist_items'=>[$uid=>['version'=>['action'=>'new','treeLevels'=>0]]]]);}
 echo 'Core-created complete draft fixtures';
}
if($mode==='cleanup'){
 $f=json_decode(file_get_contents('var/ve-integration/fixture.json'),true);
 $GLOBALS['BE_USER']->workspace=$f['workspace'];$drafts=$pool->getConnectionForTable('tt_content')->fetchFirstColumn('SELECT uid FROM tt_content WHERE pid=? AND t3ver_wsid=?',[$f['page'],$f['workspace']]);
 foreach($drafts as $uid)dh([],['tt_content'=>[$uid=>['discard'=>1]]]);
 $GLOBALS['BE_USER']->workspace=0;
 dh(['be_users'=>[$f['admin']=>['disable'=>1],$f['editor']=>['disable'=>1]]],['pages'=>[$f['page']=>['delete'=>1]],'be_users'=>[$f['admin']=>['delete'=>1],$f['editor']=>['delete'=>1]],'be_groups'=>[$f['group']=>['delete'=>1]],'sys_workspace'=>[$f['workspace']=>['delete'=>1]]]);
 foreach([$f['admin'],$f['editor']] as $id){$pool->getConnectionForTable('be_users')->update('be_users',['disable'=>1],['uid'=>$id,'deleted'=>1]);$pool->getConnectionForTable('be_sessions')->delete('be_sessions',['ses_userid'=>$id]);}
 $remaining=[];foreach($GLOBALS['TCA'] as $table=>$tca){if(empty($tca['ctrl']['delete']))continue;$columns=$pool->getConnectionForTable($table)->createSchemaManager()->listTableColumns($table);if(!isset($columns['pid']))continue;$count=$pool->getConnectionForTable($table)->count('*',$table,['pid'=>$f['page'],$tca['ctrl']['delete']=>0]);if($count)$remaining[$table]=$count;}
 echo json_encode(['page'=>$f['page'],'remainingActivePageOwnedRows'=>$remaining]);
}
