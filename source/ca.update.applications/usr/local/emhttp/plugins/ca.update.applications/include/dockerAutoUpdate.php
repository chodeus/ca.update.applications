<?PHP
# The settings page shares its process with other plugins, so these names must stay unique

# Manager is only reported from Unraid 7.0; before that a template marks a dockerMan container
function caAutoUpdateIsThirdParty($liveContainer,$cachedInfo) {
  return array_key_exists('Manager',$liveContainer) ? $liveContainer['Manager'] != "dockerman" : empty($cachedInfo['template']);
}

# CreateDocker.php cannot update a container without a template; only 'false' means a newer image exists
function caAutoUpdateHasUpdate($cachedInfo) {
  return $cachedInfo['updated'] === "false" && ! empty($cachedInfo['template']);
}

# A changed image ID is the evidence that an update happened
function caAutoUpdateChangedContainers($names,$info,$liveContainers) {
  $imagesAfter = array_column($liveContainers,'ImageId','Name');
  return array_values(array_filter($names,function($name) use ($info,$imagesAfter) {
    return isset($imagesAfter[$name]) && $imagesAfter[$name] != $info[$name]['ImageId'];
  }));
}

# The image CreateDocker.php will use, or the fallback when the template cannot be read
function caAutoUpdateTemplateImage($template,$fallback) {
  $doc = new DOMDocument();
  if ( ! @$doc->load($template) ) {
    return $fallback;
  }
  $repository = $doc->getElementsByTagName('Repository')->item(0);
  return ($repository ? strtok(trim($repository->nodeValue)," ") : false) ?: $fallback;
}

# Container name => the container whose network it shares
function caAutoUpdateNetworkParents($liveContainers) {
  $parents = [];
  foreach ($liveContainers as $container) {
    if ( preg_match('/^container:(.+)$/',$container['NetworkMode'] ?? "",$match) ) {
      $parents[$container['Name']] = $match[1];
    }
  }
  return $parents;
}

# A container sharing a network can only start once the container that owns it is running
function caAutoUpdateParentsFirst($names,$networkParents) {
  $children = array_intersect($names,array_keys($networkParents));
  return array_merge(array_diff($names,$children),$children);
}

# Names missing from the live list, or still pinned to the ID of a removed container
function caAutoUpdateNotRebuilt($names,$liveContainers) {
  $networks = array_column($liveContainers,'NetworkMode','Name');
  return array_values(array_filter($names,function($name) use ($networks) {
    return substr($networks[$name] ?? ":???",-4) == ":???";
  }));
}

# Recreating a container strands those sharing its network; a rebuild needs a template and a running parent
function caAutoUpdateStrandedChildren($recreated,$networkParents,$liveContainers,$info) {
  $running = array_column($liveContainers,'Running','Name');
  $children = array_filter(array_keys(array_intersect($networkParents,$recreated)),function($child) use ($networkParents,$running,$info) {
    return ! empty($info[$child]['template']) && ($running[$networkParents[$child]] ?? false);
  });
  return caAutoUpdateNotRebuilt($children,$liveContainers);
}
?>
