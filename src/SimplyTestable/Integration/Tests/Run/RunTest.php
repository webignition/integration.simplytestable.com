<?php

namespace SimplyTestable\Integration\Tests;

use SimplyTestable\Integration\Tests\BaseTest;


class RunTest extends BaseTest {
    
    /**
     *
     * @var int
     */
    private static $jobId;


    public function testStartTest() { 
        $request = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/start/');        
        $this->getHttpClient()->redirectHandler()->enable();
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody()); 
        
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertInternalType('integer', $responseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $responseObject->website);
        $this->assertEquals('new', $responseObject->state);
        $this->assertEquals(0, $responseObject->url_count);
        $this->assertEquals(0, $responseObject->task_count);
        
        self::$jobId = $responseObject->id;
    }
    
    
    /**
     * @depends testStartTest
     */
    public function testPrepareTest() {
        $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . self::$jobId);
    }
    
    /**
     * @depends testPrepareTest
     */
    public function testGetPreAssignmentTestStatus() {
        $jobRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/');        
        $jobResponse = $this->getHttpClient()->getResponse($jobRequest);
        
        $jobResponseObject = json_decode($jobResponse->getBody());

        $this->assertEquals(self::HTTP_STATUS_OK, $jobResponse->getResponseCode());
        $this->assertInternalType('integer', $jobResponseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $jobResponseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $jobResponseObject->website);
        $this->assertEquals('queued', $jobResponseObject->state);
        $this->assertGreaterThan(0, $jobResponseObject->url_count);
        $this->assertGreaterThan(0, $jobResponseObject->task_count);
        $this->assertNotNull($jobResponseObject->time_period);
        $this->assertNotNull($jobResponseObject->time_period->start_date_time);
        $this->assertTrue(!isset($jobResponseObject->time_period->end_date_time));

        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());        

        $this->assertEquals(self::HTTP_STATUS_OK, $tasksResponse->getResponseCode());

        foreach ($tasksResponseObject as $task) {
            $this->assertGreaterThan(0, $task->id);
            $this->assertNotNull($task->url);
            $this->assertEquals('queued', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotEquals('', $task->type);
        }
    }
    
    
    /**
     * @depends testGetPreAssignmentTestStatus
     */
    public function testAssignTasksToWorkers() {        
        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());  

        foreach ($tasksResponseObject as $task) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:task:assign ' . $task->id);
        }
    }
    
    
    /**
     * @depends testAssignTasksToWorkers
     */
    public function testGetPostAssignmentTestStatus() {
        $jobRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/');        
        $jobResponse = $this->getHttpClient()->getResponse($jobRequest);
        
        $jobResponseObject = json_decode($jobResponse->getBody());

        $this->assertEquals(self::HTTP_STATUS_OK, $jobResponse->getResponseCode());
        $this->assertInternalType('integer', $jobResponseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $jobResponseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $jobResponseObject->website);
        $this->assertEquals('in-progress', $jobResponseObject->state);
        $this->assertGreaterThan(0, $jobResponseObject->url_count);
        $this->assertGreaterThan(0, $jobResponseObject->task_count);       
        $this->assertNotNull($jobResponseObject->time_period);
        $this->assertNotNull($jobResponseObject->time_period->start_date_time);
        $this->assertTrue(!isset($jobResponseObject->time_period->end_date_time));

        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());        

        $this->assertEquals(self::HTTP_STATUS_OK, $tasksResponse->getResponseCode());

        foreach ($tasksResponseObject as $task) {
            $this->assertGreaterThan(0, $task->id);
            $this->assertNotNull($task->url);
            $this->assertEquals('in-progress', $task->state);
            $this->assertNotEquals('', $task->worker);
            $this->assertNotEquals('', $task->type);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertTrue(!isset($task->time_period->end_date_time));
        }
    }
    
    /**
     * @depends testGetPostAssignmentTestStatus
     */
    public function testPerformTasks() {        
        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());  

        foreach ($tasksResponseObject as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
        }    
    }
    

    /**
     * @depends testPerformTasks
     */
    public function testReportTaskCompletion() {
        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());
        
        foreach ($tasksResponseObject as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
        }     
    }
    
    
    /**
     * @depends testReportTaskCompletion
     */    
    public function testGetPostCompleteTestStatus() {
        $jobRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/');        
        $jobResponse = $this->getHttpClient()->getResponse($jobRequest);
        
        $jobResponseObject = json_decode($jobResponse->getBody());

        $this->assertEquals(self::HTTP_STATUS_OK, $jobResponse->getResponseCode());
        $this->assertInternalType('integer', $jobResponseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $jobResponseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $jobResponseObject->website);
        $this->assertEquals('completed', $jobResponseObject->state);
        $this->assertGreaterThan(0, $jobResponseObject->url_count);
        $this->assertGreaterThan(0, $jobResponseObject->task_count);       
        $this->assertNotNull($jobResponseObject->time_period);
        $this->assertNotNull($jobResponseObject->time_period->start_date_time);
        $this->assertNotNull($jobResponseObject->time_period->end_date_time);

        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.self::$jobId . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());        

        $this->assertEquals(self::HTTP_STATUS_OK, $tasksResponse->getResponseCode());

        foreach ($tasksResponseObject as $task) {
            $this->assertGreaterThan(0, $task->id);
            $this->assertNotNull($task->url);
            $this->assertEquals('completed', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotEquals('', $task->type);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->time_period->end_date_time);
        }       
    }     
    
    /**
     * @depends testGetPostCompleteTestStatus
     */
    public function testStartAndCancelTest() { 
        $startRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/start/');        
        $startResponse = $this->getHttpClient()->getResponse($startRequest);
        $startResponseObject = json_decode($startResponse->getBody());
        $job_id = $startResponseObject->id;

        $cancelRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/cancel/');

        $this->assertEquals(self::HTTP_STATUS_OK, $this->getHttpClient()->getResponse($cancelRequest)->getResponseCode());
        
        $jobRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'. $job_id . '/');        
        $jobResponse = $this->getHttpClient()->getResponse($jobRequest);
        
        $jobResponseObject = json_decode($jobResponse->getBody());

        $this->assertEquals(self::HTTP_STATUS_OK, $jobResponse->getResponseCode());
        $this->assertInternalType('integer', $jobResponseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $jobResponseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $jobResponseObject->website);
        $this->assertEquals('cancelled', $jobResponseObject->state);
        $this->assertEquals(0, $jobResponseObject->url_count);
        $this->assertEquals(0, $jobResponseObject->task_count);       
        $this->assertNotNull($jobResponseObject->time_period);
        $this->assertNotNull($jobResponseObject->time_period->start_date_time);
        $this->assertNotNull($jobResponseObject->time_period->end_date_time);
    }
    
    
    public function testStartPrepareAndCancelTest() {
        // Start
        $startRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/start/');        
        $startResponse = $this->getHttpClient()->getResponse($startRequest);
        $startResponseObject = json_decode($startResponse->getBody());
        $job_id = $startResponseObject->id;

        // Prepare
        $this->runSymfonyCommand($this->coreApplication, 'simplytestable:job:prepare ' . $job_id);

        // Cancel
        $cancelRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.$job_id.'/cancel/');
        $this->assertEquals(self::HTTP_STATUS_OK, $this->getHttpClient()->getResponse($cancelRequest)->getResponseCode());

        // Verify        
        $jobRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'. $job_id . '/');        
        $jobResponse = $this->getHttpClient()->getResponse($jobRequest);
        
        $jobResponseObject = json_decode($jobResponse->getBody());

        $this->assertEquals(self::HTTP_STATUS_OK, $jobResponse->getResponseCode());
        $this->assertInternalType('integer', $jobResponseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $jobResponseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $jobResponseObject->website);
        $this->assertEquals('cancelled', $jobResponseObject->state);
        $this->assertGreaterThan(0, $jobResponseObject->url_count);
        $this->assertGreaterThan(0, $jobResponseObject->task_count);     
        $this->assertNotNull($jobResponseObject->time_period);
        $this->assertNotNull($jobResponseObject->time_period->start_date_time);
        $this->assertNotNull($jobResponseObject->time_period->end_date_time);

        // Cancel tasks  
        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.$job_id . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());
      
        foreach ($tasksResponseObject as $task) {
            $this->runSymfonyCommand($this->coreApplication, 'simplytestable:task:cancel ' . $task->id);         
        }
        
        // Verify
        $tasksRequest = $this->getAuthorisedHttpRequest('http://'.$this->coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/'.$job_id . '/tasks/');        
        $tasksResponse = $this->getHttpClient()->getResponse($tasksRequest);
        
        $tasksResponseObject = json_decode($tasksResponse->getBody());        
        
        foreach ($tasksResponseObject as $task) {
            $this->assertEquals('cancelled', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->time_period->end_date_time);
       }     
    }
      
}