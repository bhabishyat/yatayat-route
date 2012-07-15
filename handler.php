<?php

class McHandler
{
    protected $count;
    protected $visited;
    protected $routes;
    protected $routesData;
    protected $routeOut;
    protected $noLink = false;
    protected $found = false;
    protected $url = "http://yyapi.monsooncollective.org/route/";
    protected $file = "data/routes.json";
    
    public function __construct()
    {
        $this->_getRoutesFromOSM();
        // Comment the assignment code below to read from the file
        $this->routesData = array(
		104 => array(
			"ref" => "14A",
			"stops" => array(
				1=>"App",
				2=>"Ball",
				3=>"Cat",
				4=>"Dog",
				5=>"Egg",
			)
		),
		105=> array(
			"ref" => "15A",
			"stops"=>array(
				5=>"Egg",
				6=>"Gun",
				7=>"Hen",
				8=>"Ink",
				9=>"Jug",
			)
		),
		106 => array(
			"ref"=>"14c",
			"stops"=>array(
				9=>"Jug",
				10=>"King",
				11=>"Lion",
				12=>"Monkey",
				13=>"Nest",
			)
		),
		107 => array(
			"ref"=>"17C",
			"stops"=>array(
				10=>"King",
				15=>"Orange",
			)
		),
                108 => array(
                    "ref" => "18d",
                    "stops" => array(
                        19 => "currency"
                    )
                ),
	);
    }
    
    public function updateOSMData()
    {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $routes = curl_exec($ch);
        $fp = fopen($this->file , 'w');
        fwrite($fp , $routes);
    }
    
    protected function _getRoutesFromOSM()
    {
        $fp = fopen($this->file , 'r');
        $this->routes = fread($fp , filesize($this->file));
        $this->getRoutesData();
    }
    
    public function getRoutesData()
    {
        $routesArray = json_decode($this->routes);
        $routesData = array();
        foreach($routesArray->routes as $route){
            $routeInfo = array();
            $routeInfo['ref'] = $route->ref;
            $routeStops = array();
            if(!empty($route->stops)){
                foreach($route->stops as $stop){
                    $routeStops[$stop->id] = $stop->name;
                }
            }
            $routeInfo['stops'] = $routeStops;
            $routesData[$route->id] = $routeInfo;
        }
        $this->routesData =  $routesData;
    }
    
        
    public function getLocId($location)
    {
        $location = strtolower($location);
        $stopname =  '';
        //$shortest = -1;

        foreach($this->routesData as $route){
            foreach($route['stops'] as $stopId=>$stopName){
                if(preg_match("/$location/" , strtolower($stopName))){
                    return $stopId;
                } else {
                    /* uses metaphone for string matching */
                    if(metaphone($location , 5) == metaphone(strtolower($stopName) , 5)){
                        return $stopId;
                    }
                    
                    /*
                    //calculate
                    $lev = levenshtein($location, strtolower($stopName));

                    // check for an exact match
                    if ($lev == 0) {                        
                        return $stopId;
                    }
                
                    // if this distance is less than the next found shortest
                    // distance, OR if a next shortest word has not yet been found
                    if ($lev <= $shortest || $shortest < 0) {
                        // set the closest match, and shortest distance
                        $stopid  = $stopId;
                        $shortest = $lev;
                    }
                    */
                }
            }
        }
        return false;
        //return $stopId;
    }
    
    public function getRoutes($location1Id , $location2Id)
    {
        foreach($this->routesData as $routeId=>$route){
            //var_dump(array_keys($route['stops']));exit;
            if(in_array($location1Id , array_keys($route['stops']))){
                $routesLoc1[] = $routeId;
            }
            if(in_array($location2Id , array_keys($route['stops']))){
                $routesLoc2[] = $routeId;
            }
        }
        // Check if both are in same route
        $route = $this->getCommonRoute($routesLoc1 , $routesLoc2);
        if($route){
            $this->routeOut[] = $route;
            return $this->getOutput();
        }
        
        $this->findTransferRoutes($routesLoc1 , $routesLoc2);
        if($this->found){
            return $this->getOutput();
        } 
        return false;
    }
    
    public function findTransferRoutes($routesLoc1 , $routesLoc2)
    {
        while(!$this->found && !$this->noLink){
            $transferableRoutes1 = $this->getTransferableRoute($routesLoc1);
            if(!$transferableRoutes1) return false;
            foreach($transferableRoutes1 as $mainRoute=>$transferableRoute){
                $this->routeOut[] = $mainRoute;
                $route = $this->getCommonRoute($routesLoc2 , $transferableRoute);
                if($route){
                    $this->routeOut[] = $route;
                    $this->found = true;
                    return true;
                } 
            }
            
            if(!$this->found){
                foreach($transferableRoutes1 as $routes){
                    $found = $this->findTransferRoutes($routes , $routesLoc2);
                }
                if(!$found){
                    $this->noLink = true;
                }
            } 
        }
    }
    
    /**
     * Function to get routes to which transfer can be made from the given route
     */
    public function getTransferableRoute($routesLoc1)
    {        
        foreach($routesLoc1 as $routeId){
            $intersectedRoutes = $this->getIntersectedRoutes($routeId);
            $this->visited[] = $routeId;
            $routes[$routeId]  = $intersectedRoutes;
        }            
        return $routes;
    }
    
    /**
     * Function to get routes that have common stops with the given route
     */
    public function getIntersectedRoutes($routeId)
    {
        if(!$this->visited) $this->visited = array();

        $routes = array();
        foreach($this->routesData as $routeKey => $route){
            if(in_array($routeKey , $this->visited)) continue;

            $hasCommon = $this->hasCommonStop($routeId , $routeKey);
            if($hasCommon && $routeId != $routeKey ){
                $routes[] = $routeKey;
            }
        }
        return $routes;
    }
    
    /**
     * Check if two routes have common stops
     */
    public function hasCommonStop($route1Id , $route2Id)
    {
        $routeData = $this->routesData;
        $route1StopsIds = array_keys($routeData[$route1Id]['stops']);
        $route2StopsIds = array_keys($routeData[$route2Id]['stops']);
        
        foreach($route1StopsIds as $stop1Id){
            if(in_array($stop1Id , $route2StopsIds)){
                return true;
            }
        }
        return false;
    }
    /**
     * Function to get a common route from two array of routes
     */
    public function getCommonRoute($routesLocs1 , $routesLocs2)
    {
        foreach($routesLocs1 as $route){
            if(in_array($route , $routesLocs2)){
                // present in the route.
                return $route;
            }
        }    
    }
    
    /**
     * Find common stops betweeen two routes
     */
    public function findCommonStops($route1Id , $route2Id)
    {
        $routeData = $this->routesData;
        $route1StopsIds = array_keys($routeData[$route1Id]['stops']);
        $route2StopsIds = array_keys($routeData[$route2Id]['stops']);
        foreach($route1StopsIds as $stop1Id){
            if(in_array($stop1Id , $route2StopsIds)){
                $stops[] = $routeData[$route1Id]['stops'][$stop1Id];
            }
        }
        return $stops;
    }
    
    public function getOutput()
    {
        $route = $firstRoute = array_shift($this->routeOut);
        $out = $this->routesData[$firstRoute]['ref'];
        while(!empty($this->routeOut)){
            $previous = $route;
            $route = array_shift($this->routeOut);
            $stop = $this->findCommonStops($previous , $route);
            $out .= " ({$stop[0]}) ".$this->routesData[$route]['ref'];
        }
        return $out;
    }
}