<?php
// Runs the shipped page block and helper against fixtures; never touches Docker, /boot or cron.
$base = dirname(__DIR__).'/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications';
$pagePath = $argv[1] ?? "$base/dockerUpdateApps.page";
$helpersPath = $argv[2] ?? "$base/include/helpers.php";
require $helpersPath;
require_once "$base/include/dockerAutoUpdate.php";
$page = file_get_contents($pagePath);

function between($source,$from,$to) {
  $start = strpos($source,$from);
  $end = strpos($source,$to,$start === false ? 0 : $start);
  if ($start === false || $end === false) throw new Exception("Block not found: $from");
  return substr($source,$start,$end-$start);
}
function tr($string,$ret=false) {return $string;}

function renderedContainers($block,$info,$moreInfo,$settings=[]) {
  $dockerUpdateSettings = $settings;
  eval($block);
  preg_match_all("/data-docker='([^']+)'/",$dockerSettings,$matches);
  return $matches[1];
}
$block = between($page,'$dockerSettings = "<div class=\'caAutoUpdateGrid\'>";','} else {'."\n".'  $dockerSettings = "<table><tr><td><center>');

$live = fn($name,$manager='dockerman') => ['Name'=>$name,'Image'=>"$name:latest",'Manager'=>$manager];
$info = ['zulu'=>['icon'=>''],'deleted'=>['icon'=>''],'alpha'=>['icon'=>''],'third'=>['icon'=>'']];
$on = fn(...$names) => array_combine($names,array_map(fn($n)=>['name'=>$n,'update'=>true],$names));

$checks = [
  ['page drops a cached name with no live container',
    renderedContainers($block,$info,[$live('alpha'),$live('zulu'),$live('third',false)]),['alpha','zulu','third']],
  ['page lists nothing when the live list is empty',
    renderedContainers($block,$info,[]),[]],
  ['page hides third-party containers when told to',
    renderedContainers($block,$info,[$live('alpha'),$live('third',false)],['global'=>['dockerIgnoreThirdParty'=>'yes']]),['alpha']],
  ['page without a Manager key hides only containers with no template',
    renderedContainers($block,['alpha'=>['icon'=>'','template'=>'/x.xml'],'third'=>['icon'=>'','template'=>false]],
      [['Name'=>'alpha','Image'=>'alpha:latest'],['Name'=>'third','Image'=>'third:latest']],['global'=>['dockerIgnoreThirdParty'=>'yes']]),['alpha']],
  ['merge turns an offered toggle off',
    mergeAutoUpdateContainers($on('a','b'),['a'],['a','b']),$on('a')],
  ['merge turns an offered toggle on',
    mergeAutoUpdateContainers([],['a'],['a']),$on('a')],
  ['merge keeps a toggle the page did not offer',
    mergeAutoUpdateContainers($on('hidden'),[],['a']),$on('hidden')],
  ['merge keeps every toggle when nothing was offered',
    mergeAutoUpdateContainers($on('a','b'),[],[]),$on('a','b')],
];
$failures = 0;
foreach ($checks as [$label,$actual,$expected]) {
  if ($actual === $expected) {echo "PASS $label\n"; continue;}
  $failures++;
  echo "FAIL $label: ".json_encode($actual).' expected '.json_encode($expected)."\n";
}
echo count($checks)." cases; $failures failures\n";
exit($failures ? 1 : 0);
