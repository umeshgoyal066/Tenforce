<?php
if (!defined("LOADED_AS_MODULE")) {
    die ("Vous n'&ecirc;tes pas autoris&eacute; &agrave; acc&eacute;der directement &agrave; cette page...");
}
function isFilterPresent($filterName, $datas, $exclude){
    $result=false;
    $onlyCols = explode(',', $datas);
    if (empty($datas) ||(!empty($datas) && in_array($filterName,$onlyCols))){
        $result=true;
    }
    $excCols = explode(',', $exclude);
    if (!empty($exclude) && in_array($filterName,$excCols)){
        $result=false;
    }
    return $result;
}

function echoParameters(){
    echo '"parameters":[';
    echo '{';
    echo '"name":"datas",';
    echo '"in":"query",';
    echo '"description":"The data you want to retrieve (comma separated). Example: id,semimajorAxis,isPlanet.",';
    echo '"required":false,';
    echo '"type":"string"';
    echo '},';
    echo '{';
    echo '"name":"exclude",';
    echo '"in":"query",';
    echo '"description":"One or more data you want to exclude (comma separated). Example: id,isPlanet.",';
    echo '"required":false,';
    echo '"type":"string"';
    echo '},';
    /*  echo '{';
      echo '"name":"include",';
      echo '"in":"query",';
      echo '"description":"One or more related entities (comma separated).",';
      echo '"required":false,';
      echo '"type":"string"';
      echo '},';*/
    echo '{';
    echo '"name":"order",';
    echo '"in":"query",';
    echo '"description":"A data you want to sort on and the sort direction (comma separated). Example: id,desc. Only one data is authorized.",';
    echo '"required":false,';
    echo '"type":"string"';
    echo '},';
    echo '{';
    echo '"name":"page",';
    echo '"in":"query",';
    echo '"description":"Page number (number>=1) and page size (size>=1 and 20 by default) (comma separated). NB: You cannot use \"page\" without \"order\"! Example: 1,10.",';
    echo '"required":false,';
    echo '"type":"string"';
    echo '},';
    echo '{';
    echo '"name":"brutData",';
    echo '"in":"query",';
    echo '"description":"Transform the object in records. NB: This can also be done client-side in JavaScript!",';
    echo '"required":false,';
    echo '"type":"boolean"';
    echo '}';
    echo ',';
    echo '{';
    echo '"name":"filter[]",';
    echo '"in":"query",';
    echo '"description":"Filters to be applied. Each filter consists of a data, an operator and a value (comma separated). Example: id,eq,mars. Accepted operators are : cs (like) - sw (start with) - ew (end with) - eq (equal) - lt (less than) - le (less or equal than) - ge (greater or equal than) - gt (greater than) - bt (between). And all opposites operators : ncs - nsw - new - neq - nlt - nle - nge - ngt - nbt. Note : if anyone filter is invalid, all filters will be ignore.",';
    echo '"required":false,';
    echo '"type":"array",';
    echo '"collectionFormat":"multi",';
    echo '"items":{"type":"string"}';
    echo '},';
    echo '{';
    echo '"name":"satisfy",';
    echo '"in":"query",';
    echo '"description":"Should all filters match (default)? Or any?",';
    echo '"required":false,';
    echo '"type":"string",';
    echo '"enum":["any"]';
    echo '}';
    echo ']'; // parameter
}

function allowOrigin($allowOrigins) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Origin: '.$allowOrigins);
    }
}

function executeCommand($settings, $request, $method, $get) {
    if ($method!='GET' && $method!='OPTIONS'&& $method!='HEAD' ){
        exitWith403('action');
    }
    header_remove("X-Powered-By"); 
    if ($settings['origin']) {
         allowOrigin($settings['allow_origin']);
    }
    if (!$settings['request']) {
        swagger();
    } else {
        $parameters = getParameters($settings,$request,$method,$get);
        switch($parameters['action']){
            case 'list': $output = listCommand($parameters); break;
            case 'read': $output = readCommand($parameters); break;
            //       case 'create': $output = $this->createCommand($parameters); break;
            //       case 'update': $output = $this->updateCommand($parameters); break;
            //       case 'delete': $output = $this->deleteCommand($parameters); break;
            //       case 'increment': $output = $this->incrementCommand($parameters); break;
            case 'headers': $output = headersCommand(); break;
            default: $output = false;
        }
        if ($output!==false) {
            startOutput();
            echo json_encode($output);
        }
    }
}

function listCommand($parameters) //, $transform, $exclude, $ordering)
{
    extract($parameters);

    startOutput();
  //  ob_start("ob_gzhandler");

    $allColumns = Bodies::getValidColumns($datas, $exclude);
    if (count($allColumns) == 0) {
        exitWith403("You need more data in datas or less data in exclude");
    }

    $isRelPresent=isFilterPresent('rel', $datas, $exclude);
    $isPlanetPresent=isFilterPresent('planet', $datas, $exclude);
    $isMoonPresent=isFilterPresent('moon', $datas, $exclude);

    echo '{"' . $GLOBALS['object'] . '":';
    if ($brutData){
        echo '{"datas":';

        $columnString = '';
        $colCount = count($allColumns);
        if ($colCount > 0) {
            $i = 0;
            $columnString = '[';
            foreach ($allColumns as $column) {
                switch($column->getColId()){
                    case 'aroundPlanet':
                        $columnString .= '{"' . $column->getColId() . '":[';
                        if ($isPlanetPresent) {
                            $columnString .= '"planet"';
                        }
                        if ($isRelPresent) {
                            if ($isPlanetPresent) $columnString .= ',';
                            $columnString .= '"rel"';
                        }
                        $columnString .= ']}';
                        break;
                    case 'moons':
                        $columnString .= '{"' . $column->getColId() . '":[';
                        if ($isPlanetPresent) {
                            $columnString .= '"moon"';
                        }
                        if ($isRelPresent) {
                            if ($isPlanetPresent) $columnString .= ',';
                            $columnString .= '"rel"';
                        }
                        $columnString .= ']}';
                        break;
                    default:
                        $columnString .= '"' . $column->getColId() . '"';
                        break;
                }
                $i++;
                if ($i < $colCount) $columnString .= ',';
            }
            if ($isRelPresent) {
                $columnString .= ',"rel"';
            }
            $columnString .= ']';
        }
        echo $columnString;
        echo ',';
        echo '"records":';
    }

    echo '[';
    echo Bodies::getAll($allColumns, $brutData, $orderings, $page, $filters, $isRelPresent, $isPlanetPresent, $isMoonPresent);
    echo ']';
    if ($brutData){ echo '}';}
    echo '}';//fin

   // ob_end_flush();
    return false;
}

function readCommand($parameters) {
    extract($parameters);

    $allColumns=Bodies::getValidColumns($datas, $exclude);
    if (count($allColumns)==0){
        exitWith403("no column");
    }

    $isRelPresent=isFilterPresent('rel',$datas, $exclude);
    $isPlanetPresent=isFilterPresent('planet', $datas, $exclude);
    $isMoonPresent=isFilterPresent('moon', $datas, $exclude);

    $object = new Bodies($key, $datas, $exclude);
    if (!$object->isExists()) {
        //existe pas mais la VF existe
        if ($object->isEnglish()) {
            exitWith301($object->getId());
        }
        // n'existe vraiment pas
        exitWith404('entity');
    }else {
        startOutput();
        $result='{';
        if ($object->getId()!==null) {
            $j=0; // pour les colonnes
            foreach ($allColumns as $column) {
                switch ($column->getColId()) {
                    case "id":
                        $result .= '"id":"' . $object->getId() . '"';
                        break;
                    case "name":
                        $result .= '"name":"' . $object->getName() . '"';
                        break;
                    case "englishName":
                        $result .= '"englishName":"' . $object->getEnglishName() . '"';
                        break;
                    case "isPlanet":
                        $result .= '"isPlanet":' . ($object->getIsPlanet() == 0 ? 'false' : 'true') . '';
                        break;
                    case "moons":
                        $result .= '"moons":'.Bodies::getSatellite($object->getId(), false, $isRelPresent, $isMoonPresent);
                        break;
                    case "semimajorAxis":
                        $result .= '"semimajorAxis":' . ($object->getSemimajorAxis() != 0 ? $object->getSemimajorAxis() : 0) . '';
                        break;
                    case "orbitalExcentricity":
                        $result .= '"orbitalExcentricity":' . ($object->getOrbitalExcentricity() != 0 ? $object->getOrbitalExcentricity() : 0) . '';
                        break;
                    case "mass":
                        $result .= '"mass":';
                        if ($object->getMassVal() <> 0) {
                            $result .= '{';
                            $result .= '"massValue":' . $object->getMassVal() . ',';
                            $result .= '"massExponent":' . $object->getmassExponent();
                            $result .= '}';
                        } else {
                            $result .= 'null';
                        }
                    break;
                    case "aroundPlanet":
                            $result .= '"aroundPlanet":';
                            if ($object->getAroundPlanet() <> "") {
                                $result .= '{';
                                if ($isPlanetPresent) {
                                    $result .= '"planet":"' . $object->getAroundPlanet() . '"';
                                }
                                if ($isRelPresent) {
                                    if ($isPlanetPresent) $result .= ', ';
                                    $result .= '"rel":"' . $GLOBALS['API_URL'] . '/' . $object->getAroundPlanet() . '"';
                                }
                                $result .= '}';
                            } else {
                                $result .= 'null';
                            }
                        break;
                    case "discoveredBy":
                        $result .= '"discoveredBy":"' . $object->getDiscoveredBy() . '"';
                        break;
                    case "discoveryDate":
                        $result .= '"discoveryDate":"' . $object->getDiscoveryDate() . '"';
                        break;
                    case "alternativeName":
                        $result .= '"alternativeName":"' . $object->getAlternativeName() . '"';
                        break;
                }
                $j++;
                if ($j < count($allColumns)) {
                    $result .= ',';
                }
            }
        }else{
            $result = null;
        }
        $result.='}';
        echo $result;
    }
    return false;
}

function startOutput() {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

function parseRequestParameter(&$request,$characters) {
    if ($request==='') return false;
    $pos = strpos($request,'/');
    $value = $pos?substr($request,0,$pos):$request;
    $request = $pos?substr($request,$pos+1):'';
    if (!$characters) return $value;
    return preg_replace("/[^$characters]/",'',$value);
}

function exitWith301($url){
    header("HTTP/1.1 301 Moved Permanently");
    header("location: ".$url);
    header("Connection: close");
    exit();
}

function exitWith404($type) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type:',true,404);
        die("Not found ($type)");
    } else {
        throw new \Exception("Not found ($type)");
    }
}

function exitWith403($type) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type:',true,403);
        die("Forbidden ($type)");
    } else {
        throw new \Exception("Forbidden ($type)");
    }
}

function exitWith400($type) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type:',true,400);
        die("The request could not be understood by the server due to malformed syntax. The client SHOULD NOT repeat the request without modifications. ($type)");
    } else {
        throw new \Exception("Bad request ($type)");
    }
}

function exitWith422($object) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type:',true,422);
        die(json_encode($object));
    } else {
        throw new \Exception(json_encode($object));
    }
}

function parseGetParameter($get,$name,$characters) {
    $value = isset($get[$name])?$get[$name]:false;
    return $characters?preg_replace("/[^$characters]/",'',$value):$value;
}

function parseGetParameterArray($get,$name,$characters) {
    $values = isset($get[$name])?$get[$name]:false;
    if (!is_array($values)) $values = array($values);
    if ($characters) {
        foreach ($values as &$value) {
            $value = preg_replace("/[^$characters]/",'',$value);
        }
    }
    return $values;
}

function mapMethodToAction($method,$key) {
    switch ($method) {
        case 'OPTIONS': case 'HEAD': return 'headers';break;
        case 'GET': return ($key===false)?'list':'read';break;
        case 'PUT': return 'update';break;
        case 'POST': return 'create';break;
        case 'DELETE': return 'delete';break;
        case 'PATCH': return 'increment';break;
        default: exitWith404('method');
    }
    return false;
}

function headersCommand() {
    $headers = array();
    $headers[]='Access-Control-Allow-Headers: Content-Type, X-XSRF-TOKEN';
    $headers[]='Access-Control-Allow-Methods: OPTIONS, GET, HEAD';
    $headers[]='Access-Control-Allow-Credentials: true';
    $headers[]='Access-Control-Max-Age: 1728000';

    foreach ($headers as $header) header($header);

    startOutput();
    echo '{';
    echo '"header":{';
    //echo json_encode($headers);
    echo '"Access-Control-Allow-Headers": ["Content-Type", "X-XSRF-TOKEN"],';
    echo '"Access-Control-Allow-Methods": ["OPTIONS", "GET", "HEAD"],';
    echo '"Access-Control-Allow-Credentials": true,';
    echo '"Access-Control-Max-Age": 1728000)';
    echo '},';

    echo '"GET":{';
    echoParameters();
    echo '}';
    echo '}';
    return false;
}

function processOrderingsParameter($orderings) {
    if (!$orderings) return false;
  //  foreach ($orderings as &$order) {
  //      $order = explode(",",$orderings,2);

    foreach ($orderings as  $order) {
        $order = explode(",",$order);

        if (count($orderings)<2) $order[1]='ASC';

        if (empty($order[1])) $order[1]='ASC';
        if (!strlen($order[0])) return false;


        //convert id to column name
        $descCol=Bodies::getDescColumns();
        $find=false;
        foreach($descCol as $col) {
            if ($col->getColId() == $order[0]) {
                $find=true;
                $orderName=$col->getColName();
            }
        }
        if ($find){
            $order[0]=$orderName;
        }

        $direction = strtoupper($order[1]);
        if (in_array($direction,array('ASC','DESC'))) {
            $order[1] = $direction;
        }

    }
    if (!$find){
        $order=null;
    }

    return $order;
}

function processPageParameter($page) {
    if (!$page) return false;
    $page = explode(',',$page);
    if (!is_numeric($page[0])) return false;
    if (count($page)<2) $page[1]=20;
    if (!is_numeric($page[1])) return false;
    $page[0] = ($page[0]-1)*$page[1];

    if ($page[0]<0) $page[0]=0;
    if ($page[1]<=0) $page[1]=1;
    return $page;
}

function processFiltersParameter($tables,$satisfy,$filterStrings) {
    $filters = array();
    addFilters($filters,$tables[0],$satisfy,$filterStrings);
    return $filters;
}

function addFilters(&$filters,$table,$satisfy,$filterStrings) {
    if ($filterStrings) {
        for ($i=0;$i<count($filterStrings);$i++) {
            $parts = explode(',',$filterStrings[$i],3);
            if (count($parts)>=2) {
                if (strpos($parts[0],'.')) list($t,$f) = explode('.',$parts[0],2);
                else list($t,$f) = array($table,$parts[0]);
                $comparator = $parts[1];
                $value = isset($parts[2])?$parts[2]:null;
                $and = isset($satisfy[$t])?$satisfy[$t]:'and';
                addFilter($filters,$t,$and,$f,$comparator,$value);
            }
        }
    }
}

function addFilter(&$filters,$table,$and,$field,$comparator,$value) {
    if (!isset($filters[$table])) $filters[$table] = array();
    if (!isset($filters[$table][$and])) $filters[$table][$and] = array();
    $filter = convertFilter($field,$comparator,$value);
    if ($filter) $filters[$table][$and][] = $filter;
}

function convertFilter($field, $comparator, $value) {
  //  $result = convertFilter($field,$comparator,$value);
  //  if ($result) return $result;
    // default behavior
    $comparator = strtolower($comparator);
    if ($comparator[0]!='n') {
        if (strlen($comparator)==2) {
            switch ($comparator) {
                case 'cs': return array('? LIKE ?',$field,'%'.addcslashes($value,'%_').'%');
                case 'sw': return array('? LIKE ?',$field,addcslashes($value,'%_').'%');
                case 'ew': return array('? LIKE ?',$field,'%'.addcslashes($value,'%_'));
                case 'eq': return array('? = ?',$field,$value);
                case 'lt': return array('? < ?',$field,$value);
                case 'le': return array('? <= ?',$field,$value);
                case 'ge': return array('? >= ?',$field,$value);
                case 'gt': return array('? > ?',$field,$value);
                case 'bt':
                    $v = explode(',',$value);
                    if (count($v)<2) return false;
                    return array('? BETWEEN ? AND ?',$field,$v[0],$v[1]);
       //         case 'in': return array('? IN ?',$field,explode(',',$value));
//                case 'is': return array('? IS NULL',$field);
            }
        }
    } else {
      /*  if (strlen($comparator)==2) {
            switch ($comparator) {
                case 'ne': return convertFilter($field, 'neq', $value); // deprecated
                case 'ni': return convertFilter($field, 'nin', $value); // deprecated
                case 'no': return convertFilter($field, 'nis', $value); // deprecated
            }
        } else*/
            if (strlen($comparator)==3) {
            switch ($comparator) {
                case 'ncs': return array('? NOT LIKE ?',$field,'%'.addcslashes($value,'%_').'%');
                case 'nsw': return array('? NOT LIKE ?',$field,addcslashes($value,'%_').'%');
                case 'new': return array('? NOT LIKE ?',$field,'%'.addcslashes($value,'%_'));
                case 'neq': return array('? <> ?',$field,$value);
                case 'nlt': return array('? >= ?',$field,$value);
                case 'nle': return array('? > ?',$field,$value);
                case 'nge': return array('? < ?',$field,$value);
                case 'ngt': return array('? <= ?',$field,$value);
                case 'nbt':
                    $v = explode(',',$value);
                    if (count($v)<2) return false;
                    return array('? NOT BETWEEN ? AND ?',$field,$v[0],$v[1]);
        //        case 'nin': return array('? NOT IN ?',$field,explode(',',$value));
                //case 'nis': return array('? IS NOT NULL',$field);
            }
        }
    }
    return false;
}

function addWhereFromFilters($filters,&$sql,&$params) {
  //  echo json_encode($filters);

    $first = true;
    $descCol=Bodies::getDescColumns();
            
    if (isset($filters['or'])) {
        //validation des filres
        foreach ($filters['or'] as $i=>$filter) {
            $isExists=false;
            foreach($descCol as $col) {
                if ($filter[1]==$col->getColId()){
                    $isExists=true;
                }
            }
            if ($isExists) {
                $allExist=true;
            } else {
                $allExist = false;
                break;
            }
        }
        if (!$allExist) return;

        //application des filtres
        $first = false;
        $sql .= ' WHERE (';
        foreach ($filters['or'] as $i=>$filter) {
            $find=false;
            $sql .= $i==0?'':' OR ';
            $sql .= $filter[0];

            for ($i=1;$i<count($filter);$i++) {
                if ($i==1){
                    //convert id to column name
                    foreach($descCol as $col) {
                        if ($col->getColId() == $filter[1]) {
                            $find=true;
                            $orderName=$col->getColName();
                        }
                    }
                    if ($find){
                        $params[]=$orderName;
                    }

                }else {
                    $params[] = "'".$filter[$i]."'";
                }
            }

        }
        $sql .= ')';
    }
    if (isset($filters['and'])) {
        //validation des filres
        foreach ($filters['and'] as $i=>$filter) {
            $isExists=false;
            foreach($descCol as $col) {
                if ($filter[1]==$col->getColId()){
                    $isExists=true;
                }
            }
            if ($isExists) {
                $allExist=true;
            } else {
                $allExist = false;
                break;
            }
        }
        if (!$allExist) return;

        //application des filtres
        foreach ($filters['and'] as $i=>$filter) {
            $sql .= $first?' WHERE ':' AND ';
            $sql .= $filter[0];

            $find=false;
            for ($i=1;$i<count($filter);$i++) {
                if ($i==1){
                    //convert id to column name
                    foreach($descCol as $col) {
                        if ($col->getColId() == $filter[1]) {
                            $find=true;
                            $orderName=$col->getColName();
                            $colType=$col->getColType();
                        }
                    }
                    if ($find){
                        $params[]=$orderName;
                    }
                }else {
                    $params[] = "'".$filter[$i]."'";
                }
            }
            $first = false;
        }
    }
}

function processSatisfyParameter($tables,$satisfyString) {
    $satisfy = array();
    foreach (explode(',',$satisfyString) as $str) {
        if (strpos($str,'.')) list($t,$s) = explode('.',$str,2);
        else list($t,$s) = array($tables[0],$str);
        $and = ($s && strtolower($s)=='any')?'or':'and';
        $satisfy[$t] = $and;
    }
    return $satisfy;
}

function getParameters($settings,$request,$method,$get) {
    extract($settings);

    $table     = parseRequestParameter($request, 'a-zA-Z0-9\-_');
    $key       = parseRequestParameter($request, 'a-zA-Z0-9\-_,'); // auto-increment or uuid
    $action    = mapMethodToAction($method,$key);
    $exclude   = parseGetParameter($get, 'exclude', 'a-zA-Z0-9\-_,.*');
    $orderings = parseGetParameterArray($get, 'order', 'a-zA-Z0-9\-_,');
    $page      = parseGetParameter($get, 'page', '0-9,');
    $brutData = parseGetParameter($get, 'brutData', 't1');
    $datas   = parseGetParameter($get, 'datas', 'a-zA-Z0-9\-_,.*');
    $filters   = parseGetParameterArray($get, 'filter', false);
    $satisfy   = parseGetParameter($get, 'satisfy', 'a-zA-Z0-9\-_,.');

    $tables[]=$GLOBALS['object'];
    $satisfy   = processSatisfyParameter($tables,$satisfy);
    $filters   = processFiltersParameter($tables,$satisfy,$filters);

    $orderings = processOrderingsParameter($orderings);
    $page      = processPageParameter($page);

    if ($table!=$GLOBALS['object']) exitWith404('entity');

    return compact('action','tables','key','page','filters','orderings','brutData','exclude','datas');
}
?>