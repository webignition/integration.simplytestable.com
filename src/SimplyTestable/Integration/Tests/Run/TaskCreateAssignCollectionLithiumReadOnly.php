<?php

namespace SimplyTestable\Integration\Tests\Run;

use SimplyTestable\Integration\Tests\Run\BaseTestSequenceTest;

/**
 * Test that worker system state is restored when entering and then leaving
 * read-only mode.
 * 
 * - Start and prepare new job
 * - Put lithium in read-only mode 
 * - Assign first 10 tasks out to workers
 * - Verify tasks assigned to hydrogen
 */
class TaskCreateAssignSingleLithiumnReadOnly extends BaseTestSequenceTest {

    public function testPrepareSequence() {
        $this->startJob();
        $this->prepareJob();
    }
    
    
    /**
     * @depends testPrepareSequence
     */
    public function testLithiumWorkerEnterReadOnly() {
        $worker = 'lithium.ci.worker.simplytestable.com';        

        $adminMaintenanceEnterReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/enable-read-only/');
        $response = $this->getHttpClient()->getResponse($adminMaintenanceEnterReadOnlyRequest);            
        $this->assertEquals(200, $response->getResponseCode());

        $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
        $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
        $this->assertEquals(200, $statusResponse->getResponseCode());

        $workerStatus = json_decode($statusResponse->getBody());
        $this->assertEquals('maintenance-read-only', $workerStatus->state);
     
    }
    
    /**
     * @depends testLithiumWorkerEnterReadOnly
     */
    public function testAssignTasksToWorkers() {
        $preAssignmentTasks = $this->getTasks();
        $tasksToAssign = array_slice($preAssignmentTasks, 0, 10);
        
        $taskIds = array();        
        foreach ($tasksToAssign as $task) {
            $taskIds[] = $task->id;
        }        
        
        $this->runSymfonyCommand(self::$coreApplication, 'simplytestable:task:assigncollection ' . implode(',', $taskIds)); 
        
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
                $this->assertEquals('hydrogen.ci.worker.simplytestable.com', $task->worker);
            } else {
                $this->assertEquals('queued', $task->state);
            }
        }      
    }
      
}