<?php

use webignition\Http\Client\Client;


class IntegrationTest extends BaseTest {
    
    /**
     *
     * @var int
     */
    private static $jobId;
    
    
    /**
     *
     * @var stdClass
     */
    private static $tasks;   
    

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
    public function testStartTest() { 
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/start/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $responseObject->website);
        $this->assertEquals('new', $responseObject->state);
        $this->assertEquals(0, count($responseObject->tasks));
        
        self::$jobId = $responseObject->id;
    }
    
    
    /**
     * @depends testStartTest
     */
    public function testPrepareTest() {
        if (getenv('SIMPLYTESTABLE_INTEGRATION_PREPARE')) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . self::$jobId);
        }
    }
    
    /**
     * @depends testPrepareTest
     */
    public function testGetTestStatus() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('queued', $responseObject->state);
        $this->assertTrue(count($responseObject->tasks) > 0);
        
        self::$tasks = $responseObject->tasks;
    }
    
    
    /**
     * @depends testGetTestStatus
     */
    public function testAssignTasksToWorkers() {        
        foreach (self::$tasks as $task) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:task:assign ' . $task->id);
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