<?php
// Runs the shipped update-flow helpers against fixtures; never touches Docker, /boot or cron.
require_once $argv[1] ?? dirname(__DIR__).'/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications/include/dockerAutoUpdate.php';

$dir = sys_get_temp_dir().'/aua-flow-'.getmypid();
mkdir($dir);
$write = function($name,$body) use ($dir) {file_put_contents("$dir/$name",$body); return "$dir/$name";};
$withTag = $write('tag.xml','<Container><Repository>ghcr.io/a/b:1.2</Repository></Container>');
$withArgs = $write('args.xml','<Container><Repository> a/b:latest --cpuset-cpus=1 </Repository></Container>');
$noRepo = $write('none.xml','<Container><Name>x</Name></Container>');
$empty = $write('empty.xml','<Container><Repository></Repository></Container>');
$broken = $write('broken.xml','<Container><Repository>');

$live = fn($name,$network,$running=true) => ['Name'=>$name,'NetworkMode'=>$network,'Running'=>$running];
$parents = ['child'=>'parent','orphan'=>'gone','grandchild'=>'child'];
$template = ['template'=>'/x.xml'];

$checks = [
  ['template image: repository with a tag',caAutoUpdateTemplateImage($withTag,'f'),'ghcr.io/a/b:1.2'],
  ['template image: drops extra arguments',caAutoUpdateTemplateImage($withArgs,'f'),'a/b:latest'],
  ['template image: no repository uses the fallback',caAutoUpdateTemplateImage($noRepo,'f'),'f'],
  ['template image: empty repository uses the fallback',caAutoUpdateTemplateImage($empty,'f'),'f'],
  ['template image: unreadable template uses the fallback',caAutoUpdateTemplateImage($broken,'f'),'f'],
  ['template image: missing file uses the fallback',caAutoUpdateTemplateImage("$dir/missing.xml",'f'),'f'],
  ['network parents: reads container networks only',
    caAutoUpdateNetworkParents([$live('a','bridge'),$live('b','container:a'),$live('c','host'),['Name'=>'d']]),['b'=>'a']],
  ['parents first: moves children to the end in order',
    caAutoUpdateParentsFirst(['child','a','grandchild','parent'],$parents),['a','parent','child','grandchild']],
  ['not rebuilt: pinned to a removed ID',caAutoUpdateNotRebuilt(['a'],[$live('a','container:???')]),['a']],
  ['not rebuilt: missing from the live list',caAutoUpdateNotRebuilt(['a'],[]),['a']],
  ['not rebuilt: network resolves',caAutoUpdateNotRebuilt(['a'],[$live('a','container:b')]),[]],
  ['stranded: child of a recreated running parent',
    caAutoUpdateStrandedChildren(['parent'],$parents,[$live('parent','bridge'),$live('child','container:???')],['child'=>$template]),['child']],
  ['stranded: parent not recreated in this run',
    caAutoUpdateStrandedChildren(['other'],$parents,[$live('parent','bridge'),$live('child','container:???')],['child'=>$template]),[]],
  ['stranded: child already follows the new parent',
    caAutoUpdateStrandedChildren(['parent'],$parents,[$live('parent','bridge'),$live('child','container:parent')],['child'=>$template]),[]],
  ['stranded: parent not running, so a rebuild would fail',
    caAutoUpdateStrandedChildren(['parent'],$parents,[$live('parent','bridge',false),$live('child','container:???')],['child'=>$template]),[]],
  ['stranded: child without a template cannot be rebuilt',
    caAutoUpdateStrandedChildren(['parent'],$parents,[$live('parent','bridge'),$live('child','container:???')],['child'=>['template'=>false]]),[]],
];
$failures = 0;
foreach ($checks as [$label,$actual,$expected]) {
  if ($actual === $expected) {echo "PASS $label\n"; continue;}
  $failures++;
  echo "FAIL $label: ".json_encode($actual).' expected '.json_encode($expected)."\n";
}
array_map('unlink',glob("$dir/*"));
rmdir($dir);
echo count($checks)." cases; $failures failures\n";
exit($failures ? 1 : 0);
