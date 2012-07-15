<?php
include_once('handler.php');
$loc1 = 'App';
$loc2 = 'ballistic';
$handler = new McHandler();
//$handler->updateOSMData();
$loc1Id = $handler->getLocId($loc1);
$loc2Id = $handler->getLocId($loc2);
if(!$loc1Id){
    echo "Sorry we could not find the first location you entered. Please make sure you spelled it correct";
    exit;
} else if(!$loc2Id){
    echo "Sorry we could not find the destination location. Please make sure you spelled it correct";
    exit;
}
$routes = $handler->getRoutes($loc1Id , $loc2Id);
if(!$routes){
    echo "Sorry we could not find routes from {$loc1} to {$loc2}.";
} else {
    print $routes;
};