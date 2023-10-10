<?php
namespace AWSS3;

use AWSS3\utils;
use AWSS3\helpers;

final class bucket_query
{
    private $s3Client = null;
    private $_data = [ 
        'Prefix'=>null, 
        'Bucket'=>null, 
        'Delimiter'=>null, 
        'MaxKeys'=>1000, 
        'StartAfter'=>0,    
        'ContinuationToken'=>null
    ];

    public function __construct($params = array())
    {
        $this->s3Client = utils::getS3Client();

        foreach( $params as $key => $val ) {
            if( !array_key_exists($key,$this->_data) ) continue;
            // $this[$key] = $val;
            $this->_data[$key] = $val;
        }
    }

    public function __get($key)
    {
        if( isset($this->_data[$key]) ) return $this->_data[$key];
    }

    public function __set($key,$val)
    {
        switch( $key ) {
        case 'Prefix':
        case 'Bucket':
        case 'Delimiter':
        case 'Marker':
        case 'ContinuationToken':
            if( !is_null($val) ) $val = trim($val);
            $this->_data[$key] = $val;
            break;
        case 'MaxKeys':
            $this->_data[$key] = (int) max(1,min(1000,(int) $val));
            break;
        case 'StartAfter':
            $this->_data[$key] = (int) max(0,min(100000,(int) $val));
            break;
        case 'pagecount':
        case 'pagenum':
            $this->_data[$key] = (int) $val;
            break;
        }
    }

    public function &execute()
    {

        try {
            
            $results = $this->s3Client->listObjectsV2($this->_data);
            //print_r($results);
            //$listing = $results->get('Contents');
            $listing['data'] = $this->FetchAll($results);
            $listing['itemcount'] = $results['KeyCount'];
            $listing['NextContinuationToken'] = $results['NextContinuationToken'];
            $listing['ContinuationToken'] = $results['ContinuationToken'];
            $listing['total'] = $this->objectCount();
            //print_r($listing);

            if($results['IsTruncated']){
                $listing['data_truncated'] = true;  
            }
            //$listing = helpers::sort_by_key($listing, 'name');

/*          $resultPaginator = $this->s3Client->getPaginator('ListObjects', $this->_data);
            $listing = [];
            foreach ($resultPaginator as $result) {
                $listing = array_merge($listing, $result->get('Contents') ?: [], $result->get('CommonPrefixes') ?: []);
            }*/

        } catch (AwsException $e) {
            // Handle the error
            if($e->getStatusCode() == 404){
                $error_message = $bucket_id." ".$e->getAwsErrorMessage();
            } else {
                $error_message = $e->getMessage();
            }

            echo $error_message;
        }

        return $listing;

        
    }

    /**
     * Fetch all of the records in this resultset as an array of objects.
     *
     * @return object[]
     */
    public function FetchAll($rs)
    {

        $__mod = \cms_utils::get_module('AWSS3');
        $entryarray = [];

        //Directories
        foreach ($rs['CommonPrefixes'] as $dir) {

            $onerow = new \stdClass();
            $onerow->key = $dir['Prefix'];
            $onerow->name = basename($onerow->key);
            $onerow->dir = true;
            $onerow->mime = 'directory';
            $onerow->ext = '';

            $entryarray[]= $onerow;

        }

        foreach( $rs['Contents'] as $row ) {

                $onerow = new \stdClass();
                $onerow->key = $row['Key'];
                $onerow->name = basename($onerow->key);
                $onerow->size = utils::formatBytes($row['Size']);
                $onerow->date = strtotime( $row['LastModified'] );
        
                $explodedfile = explode('.', $onerow->name); 
                $onerow->ext = array_pop($explodedfile);
                
                if($__mod->GetOptionValue("custom_url") !== '' && $__mod->GetOptionValue("use_custom_url") == true ){
                    $base_url = $__mod->GetOptionValue("custom_url").'/';
                } else {
                    $base_url = "https://".$bucket_id.".s3.".$__mod->GetPreference('access_region').".amazonaws.com/";
                }
                
                $onerow->url = $base_url.$onerow->key;
                $onerow->url_link = "<a href='" . $onerow->url . "' target='_blank' class=\"card-link\">" . $onerow->name . "</a>";
                $onerow->icon = $__mod->GetFileIcon($onerow->ext);
                $onerow->icon_link = "<a href='" . $onerow->url . "' target='_blank' class=\"card-link\">".$onerow->icon."</a>";
                $onerow->presigned_url = $__mod->CreateSignedLink($onerow->key);
                $onerow->presigned_link = "<a href='" . $onerow->presigned_url . "' class=\"card-link\">" . $onerow->name . "</a>";
                $onerow->presigned_icon_link = "<a href='" . $onerow->presigned_url . "' class=\"card-link\">" . $onerow->icon . "</a>";
                $entryarray[] = $onerow;
        
        }
        return $entryarray;
    }

    public function objectCount()
    {
        $result = $this->s3Client->listObjectsV2([
            'Bucket' => $this->Bucket,
            'Prefix' => $this->Prefix,
        ]);
        
        $objectCount = count($result['Contents']);
        return $objectCount;

    }

    /** internal */
    public function fill_from_array($row)
    {
        foreach( $row as $key => $val ) {
            if( array_key_exists($key,$this->_data) ) {
                $this->_data[$key] = $val;
            }
        }
    }



} // end of class
