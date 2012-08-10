<?php

use webignition\Http\Client\Client;


class IntegrationTest extends \PHPUnit_Framework_TestCase {      
    
    const HTTP_STATUS_OK = 200;
    const PUBLIC_USER_USERNAME = 'public';
    const PUBLIC_USER_PASSWORD = 'password';
    
    /**
     * Map of environment name to enviroment local path
     * 
     * @var array
     */
    private $environments = array(
        'ci.app.simplytestable.com' => '/www/ci.app.simplytestable.com',
        'hydrogen.ci.worker.simplytestable.com' => '/www/hydrogen.ci.worker.simplytestable.com'
    );
    
    
    /**
     * Names of environments that are workers
     * 
     * @var array
     */
    private $workers = array(
        'hydrogen.ci.worker.simplytestable.com'
    );
    
    
    /**
     *
     * @var string
     */
    private $coreApplication = 'ci.app.simplytestable.com';
    
    
    /**
     *
     * @var \webignition\Http\Client\Client
     */
    private $httpClient;
    
    
    /**
     *
     * @var int
     */
    private $jobId;
  

    public function testPrepareEnvironment() {
        var_dump(getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE'));
        
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->resetEnvironmentDatabases();
            //$this->requestWorkerActivation();
            //$this->verifyWorkerActivation();            
        }
    }    

//    /**
//     *
//     * @depends testPrepareEnvironment 
//     */
//    public function testNewJobRequest() { 
//        $request = $this->getAuthorisedHttpRequest('http://ci.app.simplytestable.com/tests/http://webignition.net/start/');        
//        $response = $this->getHttpClient()->getResponse($request);
//        
//        $responseObject = json_decode($response->getBody());
//        
//        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
//        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
//        $this->assertEquals('http://webignition.net/', $responseObject->website);
//        $this->assertEquals('new', $responseObject->state);
//        $this->assertEquals(0, count($responseObject->tasks));
//        
//        $this->jobId = $responseObject->id;
//    }
//    
//    
//    /**
//     * @depends testNewJobRequest
//     */
//    public function testPrepareNewJob() {
//        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
//            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . $this->jobId);
//        }
//    }     
    
    private function resetEnvironmentDatabases() {
        foreach ($this->environments as $environment => $path) {
            $this->runSymfonyCommand($environment, 'doctrine:database:drop --force');
            $this->runSymfonyCommand($environment, 'doctrine:database:create');
            $this->runSymfonyCommand($environment, 'doctrine:database:migrate --no-interaction');
        }
    }    
    
    
    private function requestWorkerActivation() {
        foreach ($this->workers as $worker) {
            $this->runSymfonyCommand($worker, 'simplytestable:worker:activate');
        }
    }
    
    
    private function verifyWorkerActivation() {
        foreach ($this->workers as $workerIndex => $worker) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:worker:activate:verify ' . ($workerIndex + 1));
        }        
    }
    
    
    /**
     *
     * @param string $environment
     * @param string $symfonyCommand 
     */
    private function runSymfonyCommand($environment, $symfonyCommand) {
        passthru('cd ' . $this->environments[$environment] . ' && php app/console ' . $symfonyCommand);
    }
    
    
    /**
     *
     * @return \HttpRequest 
     */
    private function getAuthorisedHttpRequest($url = '', $request_method = HTTP_METH_GET, $options = array()) {
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
    private function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
        }
        
        return $this->httpClient;
    }    
    
      
}

/**
 *

# Reset environment databases
cd /www/hydrogen.ci.worker.simplytestable.com && php app/console doctrine:database:drop --force && php app/console doctrine:database:create && php app/console doctrine:migrations:migrate --no-interaction --quiet
cd /www/ci.app.simplytestable.com && php app/console doctrine:database:drop --force && php app/console doctrine:database:create && php app/console doctrine:migrations:migrate --no-interaction --quiet

# Hydrogen request activation
cd /www/hydrogen.ci.worker.simplytestable.com && php app/console simplytestable:worker:activate

# Core application verifies activation
cd /www/ci.app.simplytestable.com && php app/console simplytestable:worker:activate:verify 1

# New job request for http://webignition.net
curl --user public:public http://ci.app.simplytestable.com/tests/http://webignition.net/start/

# Prepare new job, expanding job into tasks
cd /home/jon/www/ci.app.simplytestable.com && php app/console simplytestable:job:prepare 1

 *  
 */