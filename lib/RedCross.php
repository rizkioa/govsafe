<?php

class RedCross {

	protected $connection 	= null;

	protected $ll 			= ''; //lat,lng

	//paging vars
	protected $limit		= 50;

	protected $offset		= 0;

	protected $total		= 0;

	public function __construct(){

		if(!empty($_GET['ll']))
			$this->ll=$_GET['ll'];

		$this->connection = new Connection();
		$this->connection->Connect();

	}

	public function GetCenters(){
		$cached = false;

		$collection = $this->connection->getConn()->centers;
		$centers = $collection->findOne(array('updated'=>array('$gt'=>date('U',strtotime('-1 days')))));

		if(empty($centers)){
			//check for error from redcross and return last set of centers
			if(($centers=self::GetRedCross()) === false){
				$centers = $collection->findOne();
				//update date so we don't hit redcross for another day
				if(!empty($centers)){
					$collection->update(array('_id'=>new MongoId($centers['_id']))
						,array('$set'=>array('updated'=>date('U'))));
				}
			} else if(!empty($centers['Locations'])){
				//insert new centers
				$collection = $this->connection->getConn()->centers; 
	        	$centers['updated'] = date('U');
	        	$collection->insert($centers);
			}
		} else {
			$cached = true;
		}

		 if(!empty($centers['Locations']) && !empty($this->ll)){
			$latlng=explode(',', $this->ll);

			for($r=0;$r<count($centers['Locations']); $r++)
				$centers['Locations'][$r]['distance']=self::getDistance(array('lat'=>$latlng[0],'lng'=>$latlng[1]),array('lat'=>$centers['Locations'][$r]['lat'],'lng'=>$centers['Locations'][$r]['lng'])).' mi';
	    }
		
		return array('meta'=>array('cached'=>$cached,'updated'=>!empty($centers['updated'])?date('c',$centers['updated']):date('c'))
			,'paging'=>array('total'=>!empty($centers['Locations'])?count($centers['Locations']):0)
			,'centers'=>!empty($centers['Locations'])?$centers['Locations']:array());
	}

	// http://www.redcross.org/find-help/shelter
	public function GetRedCross(){
		$ch = curl_init(); 

        // set url 
        curl_setopt($ch, CURLOPT_URL, "http://app.redcross.org/nss-app/pages/mapServicesList.jsp?action=list"); 

        //return the transfer as a string 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

        // $output contains the output string 
        $output = curl_exec($ch); 

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // close curl resource to free up system resources 
        curl_close($ch);   

        if ( $status != 200 )
        	return false;

        $json_response = json_decode($output,true);  
        
        return $json_response;
	}

	public function getDistance($from, $to, $unit='k') {
	     $lat1 = (float)$from['lat'];
	     $lon1 = (float)$from['lng'];
	     $lat2 = (float)$to['lat'];
	     $lon2 = (float)$to['lng'];
	 
	     $lat1 *= (pi()/180);
	     $lon1 *= (pi()/180);
	     $lat2 *= (pi()/180);
	     $lon2 *= (pi()/180);
	  
	     $dist = 2*asin(sqrt( pow((sin(($lat1-$lat2)/2)),2) + cos($lat1)*cos($lat2)*pow((sin(($lon1-$lon2)/2)),2))) * 6378.137;
	   
	     if ($unit=="m"){
	     	$dist = ($dist / 1.609344); 
	     	return round($dist,1);
	     }
	     else {
	     	return round($dist,1);	     	
	     }
   }
}
?>