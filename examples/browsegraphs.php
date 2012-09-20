<?
#
# This script will dynamically generate basic dashboards for checking
# the metrics gathered by graphite-curlspeed
#
# This is only a proof of concept, you should build your own dashboards.
#

########## Configuration starts
#
# Path to site list file
$LISTFILE="path_to_sites.txt";
# Available locations
$LOCATIONS= Array("Location1","Location2","Location3","LocationEtc");
# Metrics taken by graphite-curlspeed:
$METRICS = Array("size_download","time_connect","time_downloading","time_namelookup","time_starttransfer","time_total");
# Graphite prepend should have same value as in config file
$GRAPHITE_PREPEND="curlspeed";
# Graphite render url
$GRAPHITE_RENDER="http://your.graphite.website.render/render";
# available sizes
$SIZES= Array("300x230","400x300","500x400","700x500");

######### Configuration ends


# Open file and read all keys into KEYS and groups into GROUPS
$file_handle = fopen($LISTFILE, "r");

while (!feof($file_handle)) {
  $siteline = fgets($file_handle);
  if ( preg_match("/^#/",$siteline) || $siteline == "" ) {
    continue;
  }
  list($key,$url) = explode("|",$siteline);
  $KEYS[$key] = Array( 
    url => $url
  );
  $keyarray = explode(".",$key);
  $GROUPS[$keyarray[0]][] = $key;
}
fclose($file_handle);

echo "<html><head><title>curlspeed simple filter</title></head><body>\n";
echo "<FORM METHOD='GET'>";

# Show filters

# Group Filter
echo "Group: <SELECT NAME='filter_group'>";
if (!$_GET["filter_group"]) {
  echo "<OPTION VALUE='' SELECTED>Any";
} else {
  echo "<OPTION VALUE=''>Any";
}
foreach ($GROUPS as $group_name => $group_value) {
  $selected='';
  if ($_GET['filter_group'] && $_GET['filter_group'] == $metric) {
    $selected = ' SELECTED';
  }
  echo "<OPTION VALUE='$group_name'$selected>$group_name";
}
echo "</SELECT>\n";


# Metric Filter
echo "Metrics: <SELECT NAME='filter_metric'>";
if (!$_GET["filter_metric"]) {
  echo "<OPTION VALUE='' SELECTED>Any";
} else {
  echo "<OPTION VALUE=''>Any";
}
foreach ($METRICS as $metric) {
  $selected='';
  if ($_GET['filter_metric'] && $_GET['filter_metric'] == $metric) {
    $selected = ' SELECTED';
  }
  echo "<OPTION VALUE='$metric'$selected>$metric";
}
echo "</SELECT>\n";

# Location Filter
echo "Location: <SELECT NAME='filter_location'>";
if (!$_GET["filter_location"]) {
  echo "<OPTION VALUE='' SELECTED>Any";
} else {
  echo "<OPTION VALUE=''>Any";
}
foreach ($LOCATIONS as $location) {
  $selected='';
  if ($_GET['filter_location'] && $_GET['filter_location'] == $location) {
    $selected = ' SELECTED';
  }
  echo "<OPTION VALUE='$location'$selected>$location";
}
echo "</SELECT>\n";

# Graph Size
echo "Size: <SELECT NAME='filter_size'>";
if (!$_GET["filter_size"]) {
  echo "<OPTION VALUE='' SELECTED>Default";
} else {
  echo "<OPTION VALUE=''>Default";
}
foreach ($SIZES as $size) {
  $selected='';
  if ($_GET['filter_size'] && $_GET['filter_size'] == $size) {
    $selected = ' SELECTED';
  }
  echo "<OPTION VALUE='$size'$selected>$size";
}
echo "</SELECT>\n";

# One graph per metric
if ($_GET['onepermetric'] && $_GET['onepermetric'] == "on") $checked = "CHECKED";
echo "Break all metrics: <input type='checkbox' name='onepermetric' $checked>";
if ($_GET['action']) {
  echo "<input type='hidden' name='action' value='".$_GET['action']."'>";
  echo "<input type='hidden' name='group' value='".$_GET['group']."'>";
}
echo "<input type='submit' value='Cargar filtros'>";
echo "</FORM>\n";

if (!$_GET["action"]) {
  # Default action is to show comparision of one metric per item for each site time average.
  foreach ($GROUPS as $group_name => $group_members) {
    echo "<div style='display: block;'>\n";
    foreach ($METRICS as $metric) {
      if ($_GET['filter_metric'] && $_GET['filter_metric'] != '') {
        if ($_GET['filter_metric'] != $metric) continue;
      }
      $targets = Array();        
      foreach ($group_members as $key_name) {
        $full_key = $GRAPHITE_PREPEND.'.'.$key_name;
        # Get each metrics average between all locations
        if ($_GET['filter_location'] && $_GET['filter_location'] != '') {
          $targets[]= "cactiStyle(alias(${full_key}.".$_GET['filter_location'].".${metric},'$key_name'))";
          $title = "$metric from ".$_GET['filter_location'];
        } else {
          $targets[]= "alias(averageSeries(${full_key}.*.${metric}),'$key_name')";         
          $title = "$metric Average";
        }
        if ($_GET['onepermetric'] && $_GET['onepermetric'] == "on") {
          # Show one graph per available metric:
          showGraph($title,$targets,$_GET['from']);
          $targets = Array();
        }
      }
      # Show graph if onepermetric is not called.
      if (!$_GET['onepermetric'] || $_GET['onepermetric'] != "on") showGraph($title,$targets,$_GET['from']);      
    }
    echo "</div>\n";
  }
}
  

function showGraph($title="Default Title",$targets,$from="",$until="") {
  global $GRAPHITE_RENDER;
  $title=str_replace(' ','_',$title);
  if (!$from || $from == "") $from="-30min";
  if (!$until || $until == "") $until="now";
  if ($_GET['filter_size'] && $_GET['filter_size'] != '') {
    list($width,$height) = explode("x",$_GET['filter_size']);
    $size = "height=$height&width=$width&";
  } else {
    $size = '';
  }
  echo "  <div style='float:left; display:block;'>\n";
  $img='<img style="margin-left:5px; margin-bottom:5px;" src="'.${GRAPHITE_RENDER}.'?from='.$from.'&until='.$until.'&width=400&height=250&title='.$title.'&'.$size;
  foreach ($targets as $target) {
    $img .="target=$target&";
  }
  $img.='">';
  echo "    $img\n";
  echo "  </div>\n";
}
echo "</body></html>\n";
?>