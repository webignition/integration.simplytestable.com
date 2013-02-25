<?php

namespace SimplyTestable\Integration\Tests;

class StartPrepareAndCancelTestSequenceTest extends BaseTestSequenceTest {     

    public function testPrepareAndStartPrepareAndCancelTest() { 
        // Create, prepare then cancel job
        $this->startJob();
        $this->prepareJob();
        $this->cancelJob();

        // Verify cancellation response
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        
        // Verify job state
        $job = $this->getJob();
        $this->assertEquals(self::HTTP_STATUS_OK, self::$lastHttpResponse->getResponseCode());
        $this->assertEquals(self::$jobId, $job->id);
        $this->assertEquals(self::PUBLIC_USER_USERNAME, $job->user);
        $this->assertEquals(self::TEST_CANONICAL_URL, $job->website);
        $this->assertEquals('cancelled', $job->state);
        $this->assertGreaterThan(0, $job->url_count);
        $this->assertGreaterThan(0, $job->task_count);      
        $this->assertNotNull($job->time_period);
        $this->assertNotNull($job->time_period->start_date_time);
        $this->assertNotNull($job->time_period->end_date_time);

        // Cancel tasks 
        $preCancellationTasks = $this->getTasks();      
        foreach ($preCancellationTasks as $task) {
            self::runSymfonyCommand($this->coreApplication, 'simplytestable:task:cancel ' . $task->id);         
        }
        
        // Verify state of all tasks
        $postCancellationTasks = $this->getTasks();      
        
        foreach ($postCancellationTasks as $task) {
            $this->assertEquals('cancelled', $task->state);
            $this->assertEquals('', $task->worker);
            $this->assertNotNull($task->time_period);
            $this->assertNotNull($task->time_period->start_date_time);
            $this->assertNotNull($task->time_period->end_date_time);
       } 
     
    }
      
}