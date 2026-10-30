<?php
// Runs the shipped age lines from updateApplications.php; never touches plugins, /boot or cron.
$base = dirname(__DIR__).'/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications';
$script = file_get_contents($argv[1] ?? "$base/scripts/updateApplications.php");
require $argv[2] ?? "$base/include/helpers.php";

$start = strpos($script,'$age = ');
$end = strpos($script,'if ( ($age >= $appList',$start === false ? 0 : $start);
if ($start === false || $end === false) throw new Exception('Age block not found');
$block = substr($script,$start,$end-$start);

function ageOf($block,$version,$now) {
  $appList = ['delay'=>3];
  $pluginDate = date_create_from_format("Y.m.d",$version);
  $currentDate = date_create($now);
  eval($block);
  return (int)$age;
}
function checks($block,$now,$cases) {
  $failures = 0;
  foreach ($cases as $label => [$version,$expected]) {
    $actual = ageOf($block,$version,$now);
    echo ($actual === $expected ? 'PASS' : 'FAIL')." $label: $version at $now = $actual".($actual === $expected ? '' : ", expected $expected")."\n";
    if ($actual !== $expected) $failures++;
  }
  return $failures;
}

date_default_timezone_set('UTC');
$failures = 0;
foreach (['00:00:01','07:00:00','23:59:59'] as $time) {
  $failures += checks($block,"2026-10-31 $time",[
    'dated today'                => ['2026.10.31',0],
    'dated 3 days ago'           => ['2026.10.28',3],
    'one month and one day ago'  => ['2026.09.30',31],
    'one month and 30 days ago'  => ['2026.09.01',60],
    'across a year boundary'     => ['2025.12.31',304],
    'across a leap day'          => ['2024.02.29',975],
    'dated in the future'        => ['2026.11.02',0],
  ]);
}
date_default_timezone_set('America/New_York');
$failures += checks($block,'2026-03-09 07:00:00',['across a daylight saving change' => ['2026.03.07',2]]);
echo "$failures failures\n";
exit($failures ? 1 : 0);
