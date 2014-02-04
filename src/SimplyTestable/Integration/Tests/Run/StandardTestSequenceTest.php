<?php

namespace SimplyTestable\Integration\Tests\Run;

use SimplyTestable\Integration\Tests\Run\BaseTestSequenceTest;

/**
 * Run through a standard test sequence:
 * 
 * - start a new job
 * - prepare job
 * - assign tasks to workers individually
 * - perform job tasks
 * - report completion
 */
class StandardTestSequenceTest extends BaseTestSequenceTest {

    
    public function testStart() {
        // Request job to be started
        $request = $this->getAuthorisedHttpRequest('http://'.self::$coreApplication.'/job/'.self::TEST_CANONICAL_URL.'/start/?type=single+URL');        
        $this->getHttpClient()->redirectHandler()->enable();
        $response = $this->getHttpClient()->getResponse($request);
        
        $responseObject = json_decode($response->getBody()); 
        
        // Verify results
        $this->assertEquals(self::HTTP_STATUS_OK, $response->getResponseCode());
        $this->assertInternalType('integer', $responseObject->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $responseObject->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $responseObject->website);
        $this->assertEquals('queued', $responseObject->state);
        $this->assertEquals(1, $responseObject->url_count);
        $this->assertEquals(self::TASK_TYPE_COUNT, $responseObject->task_count);
        
        self::$jobId = $responseObject->id;
    } 
    
    
    /**
     * @depends testStart
     */
    public function testResolve() {
        $this->resolveJob();
        
        // Verify job state
        $job = $this->getJob();
        
        $this->assertEquals('resolved', $job->state);
    }    
    
    
    /**
     * @depends testResolve
     */
    public function testPrepare() {
        // Request job to be prepared
        $this->prepareJob();                
        
        // Verify job state
        $job = $this->getJob();
        
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        $this->assertEquals(self::$jobId, $job->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
        $this->assertEquals('queued', $job->state);
        $this->assertGreaterThan(0, $job->url_count);
        $this->assertGreaterThan(0, $job->task_count);
        $this->assertNotNull($job->time_period);
        $this->assertNotNull($job->time_period->start_date_time);
        $this->assertTrue(!isset($job->time_period->end_date_time));
        
        // Verify state of all tasks
        $tasks = $this->getTasks();

        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());

        foreach ($tasks as $task) {
            $this->assertGreaterThan(0, $task->id);
            $this->assertNotNull($task->url);
            $this->assertEquals('queued', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotEquals('', $task->type);
        }
    }
    
    
    /**
     * @depends testPrepare
     */
    public function testAssignTasksToWorkers() {
        // Assign each task out to the workers
        $preAssignmentTasks = $this->getTasks();
        foreach ($preAssignmentTasks as $task) {
            $this->runSymfonyCommand(self::$coreApplication, 'simplytestable:task:assign ' . $task->id);
        }
        
        // Verify job state
        $job = $this->getJob();
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        $this->assertEquals(self::$jobId, $job->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
        $this->assertEquals('in-progress', $job->state);
        $this->assertGreaterThan(0, $job->url_count);
        $this->assertGreaterThan(0, $job->task_count);       
        $this->assertNotNull($job->time_period);
        $this->assertNotNull($job->time_period->start_date_time);
        $this->assertTrue(!isset($job->time_period->end_date_time));
        
        
        // Verify state of all tasks
        $postAssignmentTasks = $this->getTasks();       
        
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());

        foreach ($postAssignmentTasks as $task) {
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
     * @depends testAssignTasksToWorkers
     */
    public function testPerformTasks() { 
        $prePerformTasks = $this->getTasks();
        foreach ($prePerformTasks as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
        }           
    }
    
    
    /**
     * @depends testPerformTasks
     */
    public function testReportTaskCompletion() {
        // Report completion for all tasks
        $preReportCompletionTasks = $this->getTasks();        
        foreach ($preReportCompletionTasks as $task) {
            $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
        }     
        
        // Verify job state
        $job = $this->getJob();        
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        $this->assertEquals(self::$jobId, $job->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
        $this->assertEquals('completed', $job->state);
        $this->assertGreaterThan(0, $job->url_count);
        $this->assertGreaterThan(0, $job->task_count);       
        $this->assertNotNull($job->time_period);
        $this->assertNotNull($job->time_period->start_date_time);
        $this->assertNotNull($job->time_period->end_date_time);
        
        // Verify state of all tasks
        $tasks = $this->getTasks();    

        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());

        foreach ($tasks as $task) {
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
      
}