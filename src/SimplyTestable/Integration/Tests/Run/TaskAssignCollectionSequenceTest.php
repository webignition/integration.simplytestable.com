<?php

namespace SimplyTestable\Integration\Tests\Run;

use SimplyTestable\Integration\Tests\Run\BaseTestSequenceTest;

/**
 * Test that tasks can be assigned by collection:
 * 
 * - start a new job
 * - prepare job
 * - assign tasks to workers as collection
 * 
 */
class TaskAssignCollectionSequenceTest extends BaseTestSequenceTest {

    
    public function testPrepareAndStartTest() {
        // Create, prepare then cancel job
        $this->startJob();
        $this->prepareJob();
    }
    
    
    /**
     * @depends testPrepareAndStartTest
     */
    public function testAssignTasksToWorkers() {
        // Assign each task out to the workers
        $preAssignmentTasks = $this->getTasks();
        $taskIds = array();
        
        foreach ($preAssignmentTasks as $task) {
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
      
}