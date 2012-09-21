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
        $this->assertEquals('queued', $responseObject->job->state);
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
        $this->assertEquals('in-progress', $responseObject->job->state);
        $this->assertTrue(count($responseObject->tasks) > 0);
        $this->assertNotNull($responseObject->job->time_period);
        $this->assertNotNull($responseObject->job->time_period->start_date_time);
        
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
    public function testGetPostCompleteTestStatus() {
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.self::$jobId.'/status/');        
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertEquals('completed', $responseObject->job->state);
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
    
    /**
     * @depends testGetPostCompleteTestStatus
     */
    public function testStartAndCancelTest() { 
        $startRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/start/');        
        $startResponse = $this->getHttpClient()->getResponse($startRequest);
        $startResponseObject = json_decode($startResponse->getBody());
        $job_id = $startResponseObject->id;
        
        $cancelRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/cancel/');
        $this->assertEquals(self::HTTP_STATUS_OK, $this->getHttpClient()->getResponse($cancelRequest)->getResponseCode());
        
        $postCancelStatusRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/status/');
        $postCancelStatusResponse = $this->getHttpClient()->getResponse($postCancelStatusRequest);
        $postCancelResponseObject = json_decode($postCancelStatusResponse->getBody());
        
        $this->assertEquals(self::HTTP_STATUS_OK, $postCancelStatusResponse->getResponseCode());
        $this->assertEquals('cancelled', $postCancelResponseObject->job->state);    
        $this->assertNotNull($postCancelResponseObject->time_period->start_date_time);
        $this->assertNotNull($postCancelResponseObject->time_period->end_date_time);  
    }
    
    
    public function testStartPrepareAndCancelTest() {
        // Start
        $startRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/start/');        
        $startResponse = $this->getHttpClient()->getResponse($startRequest);        
        $startResponseObject = json_decode($startResponse->getBody());
        $job_id = $startResponseObject->id;
        
        // Prepare
        $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . $job_id);
        
        // Cancel Job
        $cancelRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/cancel/');
        $this->assertEquals(self::HTTP_STATUS_OK, $this->getHttpClient()->getResponse($cancelRequest)->getResponseCode());
        
        // Verify
        $postCancelStatusRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/status/');
        $postCancelStatusResponse = $this->getHttpClient()->getResponse($postCancelStatusRequest);
        $postCancelResponseObject = json_decode($postCancelStatusResponse->getBody());        
        
        $this->assertEquals(self::HTTP_STATUS_OK, $postCancelStatusResponse->getResponseCode());
        $this->assertEquals('cancelled', $postCancelResponseObject->job->state);    
        $this->assertNotNull($postCancelResponseObject->time_period->start_date_time);
        $this->assertNotNull($postCancelResponseObject->time_period->end_date_time);         
        
        $this->assertTrue(count($postCancelResponseObject->tasks) > 0);        
        
        // Cancel tasks        
        foreach ($postCancelResponseObject->tasks as $task) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:task:cancel ' . $task->id);         
        }
        
        // Verify
        $postCancelStatusRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/tests/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/status/');
        $postCancelStatusResponse = $this->getHttpClient()->getResponse($postCancelStatusRequest);
        $postCancelResponseObject = json_decode($postCancelStatusResponse->getBody());          
        
        foreach ($postCancelResponseObject->tasks as $task) {
            $this->assertEquals('cancelled', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->time_period->end_date_time);
        }        
    }
      
}