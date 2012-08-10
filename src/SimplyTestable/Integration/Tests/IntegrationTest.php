<?php

use webignition\Http\Client\Client;


class IntegrationTest extends BaseTest {  

    public function testPrepareEnvironment() {        
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->resetEnvironmentDatabases();
            $this->requestWorkerActivation();
            $this->verifyWorkerActivation();            
        }
    }    

    /**
     *
     * @depends testPrepareEnvironment 
     */
    public function testNewJobRequest() { 
        $request = $this->getAuthorisedHttpRequest('http://ci.app.simplytestable.com/tests/http://webignition.net/start/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
        $this->assertEquals('http://webignition.net/', $responseObject->website);
        $this->assertEquals('new', $responseObject->state);
        $this->assertEquals(0, count($responseObject->tasks));
        
        self::$jobId = $responseObject->id;
    }
    
    
    /**
     * @depends testNewJobRequest
     */
    public function testPrepareNewJob() {
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . self::$jobId);
        }
    }
    
    private function resetEnvironmentDatabases() {
        foreach ($this->environments as $environment => $path) {
            $this->runSymfonyCommand($environment, 'doctrine:database:drop --force');
            $this->runSymfonyCommand($environment, 'doctrine:database:create');
            $this->runSymfonyCommand($environment, 'doctrine:migrations:migrate --no-interaction --quiet');
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
}