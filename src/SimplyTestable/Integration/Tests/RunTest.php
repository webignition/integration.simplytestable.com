<?php

namespace SimplyTestable\Integration\Tests;

use webignition\Http\Client\Client;


class RunTest extends BaseTest {
    
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
    public function testGetPreAssignmentTestStatus() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('queued', $responseObject->state);
        $this->assertTrue(count($responseObject->tasks) > 0);
        
        foreach ($responseObject->tasks as $task) {
            $this->assertEquals('queued', $task->state);
        }
        
        self::$tasks = $responseObject->tasks;
    }
    
    
    /**
     * @depends testGetPreAssignmentTestStatus
     */
    public function testAssignTasksToWorkers() {        
        foreach (self::$tasks as $task) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:task:assign ' . $task->id);
        }
    }
    
    
    /**
     * @depends testAssignTasksToWorkers
     */
    public function testGetPostAssignmentTestStatus() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('in-progress', $responseObject->state);
        $this->assertTrue(count($responseObject->tasks) > 0);
        $this->assertNotNull($responseObject->time_period);
        $this->assertNotNull($responseObject->time_period->start_date_time);
        
        foreach ($responseObject->tasks as $task) {
            $this->assertEquals('in-progress', $task->state);            
            $this->assertNotNull($task->worker);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->remote_id);
        }
        
        self::$tasks = $responseObject->tasks;        
    }
    
    /**
     * @depends testGetPostAssignmentTestStatus
     */
    public function testPerformTasks() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        foreach ($responseObject->tasks as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
        }        
    }
    

    /**
     * @depends testPerformTasks
     */
    public function testReportTaskCompletion() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        foreach ($responseObject->tasks as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
        }        
    }    
    
    
    /**
     * @depends testReportTaskCompletion
     */
    public function testMarkJobCompleted() {        
        $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:markcompleted ' . self::$jobId);
    }
    
    
    /**
     * @depends testMarkJobCompleted
     */
    public function testStartAndCancelTest() { 
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/start/');        
        $response = $this->getHttpClient()->getResponse($request);
        $responseObject = json_decode($response->getBody());
        $job_id = $responseObject->id;
        
        //$request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/start/');  
        
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/cancel/');
        $response = $this->getHttpClient()->getResponse($request);
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/status/');
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('cancelled', $responseObject->state);    
    }
      
    
    /**
     * @depends testMarkJobCompleted
     */    
    public function testGetPostCompleteTestStatus() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('completed', $responseObject->state);
        $this->assertTrue(count($responseObject->tasks) > 0);
        $this->assertNotNull($responseObject->time_period);
        $this->assertNotNull($responseObject->time_period->start_date_time);
        
        foreach ($responseObject->tasks as $task) {
            $this->assertEquals('completed', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->time_period->end_date_time);
        }
        
        self::$tasks = $responseObject->tasks;        
    }   
    
      
}