<?php

use webignition\Http\Client\Client;


abstract class BaseTest extends \PHPUnit_Framework_TestCase {      
    
    const HTTP_STATUS_OK = 200;
    const PUBLIC_USER_USERNAME = 'public';
    const PUBLIC_USER_PASSWORD = 'password';
    const TEST_CANONICAL_URL = 'http://webignition.net/';
    
    /**
     * Map of environment name to enviroment local path
     * 
     * @var array
     */
    protected $environments = array(
        'ci.app.simplytestable.com' => '/www/ci.app.simplytestable.com',
        'hydrogen.ci.worker.simplytestable.com' => '/www/hydrogen.ci.worker.simplytestable.com'
    );
    
    
    /**
     * Names of environments that are workers
     * 
     * @var array
     */
    protected $workers = array(
        'hydrogen.ci.worker.simplytestable.com'
    );
    
    
    /**
     *
     * @var string
     */
    protected $coreApplication = 'ci.app.simplytestable.com';
    
    
    /**
     *
     * @var \webignition\Http\Client\Client
     */
    private $httpClient;
    
    
    /**
     *
     * @param string $environment
     * @param string $symfonyCommand 
     */
    protected function runSymfonyCommand($environment, $symfonyCommand) {
        $this->runCommand($environment, 'php app/console ' . $symfonyCommand);
    }
    
    
    /**
     *
     * @param string $environment
     * @param string $command 
     */
    protected function runCommand($environment, $command) {
        passthru('cd ' . $this->environments[$environment] . ' && ' . $command);
    }
    
    
    /**
     *
     * @return \HttpRequest 
     */
    protected function getAuthorisedHttpRequest($url = '', $request_method = HTTP_METH_GET, $options = array()) {
        $httpRequest = new \HttpRequest($url, $request_method, $options);
        $httpRequest->addHeaders(array(
            'Authorization' => 'Basic ' . base64_encode('public:public')
        ));
        
        return $httpRequest;
    }  
    
    
    /**
     *
     * @return \webignition\Http\Client\Client
     */
    protected function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
        }
        
        return $this->httpClient;
    }    
    
      
}