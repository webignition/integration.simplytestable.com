<?php

namespace SimplyTestable\Integration\Tests\Run;

use SimplyTestable\Integration\Tests\Run\BaseTestSequenceTest;

/**
 * Test that worker system state is restored when entering and then leaving
 * read-only mode.
 * 
 * - Start and prepare new job
 * - Assign first 10 tasks out to workers
 * - Enter worker read-only mode
 * - Try to perform task; see that tasks are not performed
 * - Leave read-only mode; see that tasks to be performed are re-queued
 * - Perform tasks; see that tasks are correctly performed
 * - Enter worker read-only mode
 * - Try to report completion for tasks; see that completion reporting does not happen
 * - Leave read-only mode; see that completion reporting jobs are requeued
 * - Report completion; see that results are reported correctly
 * 
 */
class EnterAndLeaveWorkerReadOnlyModeTest extends BaseTestSequenceTest {
    
    private static $taskIdsAssignedToWorkers = array();

    public function testPrepareSequence() {
        $this->startJob();
        $this->prepareJob();
        
        $preAssignmentTasks = $this->getTasks();
        $tasksToAssign = array_slice($preAssignmentTasks, 0, 10);

        foreach ($tasksToAssign as $task) {
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
            if ($task->id <= 10) {
                $this->assertEquals('in-progress', $task->state);
            } else {
                $this->assertEquals('queued', $task->state);
            }
        }
    }
    
//    
//    /**
//     * @depends testPrepareSequence
//     */
//    public function testWorkerEnterReadOnly() {
//        foreach (self::$workers as $worker) {
//            $adminMaintenanceEnterReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/enable-read-only/');
//            $response = $this->getHttpClient()->getResponse($adminMaintenanceEnterReadOnlyRequest);            
//            $this->assertEquals(200, $response->getResponseCode());
//            
//            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
//            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
//            $this->assertEquals(200, $statusResponse->getResponseCode());
//            
//            $workerStatus = json_decode($statusResponse->getBody());
//            $this->assertEquals('maintenance-read-only', $workerStatus->state);
//        }       
//    }
//    
//    
//    /**
//     * @depends testWorkerEnterReadOnly
//     */
//    public function testPerformTaskWhenWorkersAreReadOnly() {
//        $prePerformTasks = $this->getTasks();
//        foreach ($prePerformTasks as $task) {
//            if ($task->id <= 10) {
//                $result = $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
//                $this->assertEquals('Unable to perform task, worker application is in maintenance read-only mode', trim($result));
//            }
//        }         
//    }
//    
//    
//    /**
//     * @depends testPerformTaskWhenWorkersAreReadOnly
//     */    
//    public function testLeaveReadOnlyMode() {
//        self::clearRedis();
//        
//        foreach (self::$workers as $worker) {           
//            $adminMaintenanceLeaveReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/leave-read-only/');
//            $adminMaintenanceLeaveReadOnlyResponse = $this->getHttpClient()->getResponse($adminMaintenanceLeaveReadOnlyRequest);            
//            $this->assertEquals(200, $adminMaintenanceLeaveReadOnlyResponse->getResponseCode());
//            
//            var_dump($adminMaintenanceLeaveReadOnlyResponse->getBody());
//            
//            // string(251) "["10 queued tasks ready to be enqueued","Enqueuing task [1]","Enqueuing task [2]","Enqueuing task [3]","Enqueuing task [4]","Enqueuing task [5]","Enqueuing task [6]","Enqueuing task [7]","Enqueuing task [8]","Enqueuing task [9]","Enqueuing task [10]"]"
//            //string(39) "["0 queued tasks ready to be enqueued"]"
//            
//            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
//            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
//            $this->assertEquals(200, $statusResponse->getResponseCode());
//            
//            $workerStatus = json_decode($statusResponse->getBody());
//            $this->assertEquals('active', $workerStatus->state);
//        }
//        
//        
//    }
    
    
//    /**
//     * @depends testPrepare
//     */
//    public function testAssignTasksToWorkers() {
//        // Assign each task out to the workers
//        $preAssignmentTasks = $this->getTasks();
//        foreach ($preAssignmentTasks as $task) {
//            $this->runSymfonyCommand(self::$coreApplication, 'simplytestable:task:assign ' . $task->id);
//        }
//        
//        // Verify job state
//        $job = $this->getJob();
//        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
//        $this->assertEquals(self::$jobId, $job->id);
//        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
//        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
//        $this->assertEquals('in-progress', $job->state);
//        $this->assertGreaterThan(0, $job->url_count);
//        $this->assertGreaterThan(0, $job->task_count);       
//        $this->assertNotNull($job->time_period);
//        $this->assertNotNull($job->time_period->start_date_time);
//        $this->assertTrue(!isset($job->time_period->end_date_time));
//        
//        
//        // Verify state of all tasks
//        $postAssignmentTasks = $this->getTasks();       
//        
//        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
//
//        foreach ($postAssignmentTasks as $task) {
//            $this->assertGreaterThan(0, $task->id);
//            $this->assertNotNull($task->url);
//            $this->assertEquals('in-progress', $task->state);
//            $this->assertNotEquals('', $task->worker);
//            $this->assertNotEquals('', $task->type);
//            $this->assertNotNull($task->time_period);
//            $this->assertNotNull($task->time_period->start_date_time);
//            $this->assertTrue(!isset($task->time_period->end_date_time));
//        }        
//    }
//    
//    
//    /**
//     * @depends testAssignTasksToWorkers
//     */
//    public function testPerformTasks() { 
//        $prePerformTasks = $this->getTasks();
//        foreach ($prePerformTasks as $task) {
//            $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
//        }           
//    }
//    
//    
//    /**
//     * @depends testPerformTasks
//     */
//    public function testReportTaskCompletion() {
//        // Report completion for all tasks
//        $preReportCompletionTasks = $this->getTasks();        
//        foreach ($preReportCompletionTasks as $task) {
//            $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
//        }     
//        
//        // Verify job state
//        $job = $this->getJob();        
//        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
//        $this->assertEquals(self::$jobId, $job->id);
//        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
//        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
//        $this->assertEquals('completed', $job->state);
//        $this->assertGreaterThan(0, $job->url_count);
//        $this->assertGreaterThan(0, $job->task_count);       
//        $this->assertNotNull($job->time_period);
//        $this->assertNotNull($job->time_period->start_date_time);
//        $this->assertNotNull($job->time_period->end_date_time);
//        
//        // Verify state of all tasks
//        $tasks = $this->getTasks();    
//
//        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
//
//        foreach ($tasks as $task) {
//            $this->assertGreaterThan(0, $task->id);
//            $this->assertNotNull($task->url);
//            $this->assertEquals('completed', $task->state);
//            $this->assertEquals('', $task->worker);
//            $this->assertNotEquals('', $task->type);
//            $this->assertNotNull($task->time_period);
//            $this->assertNotNull($task->time_period->start_date_time);
//            $this->assertNotNull($task->time_period->end_date_time);
//        }         
//    }
      
}