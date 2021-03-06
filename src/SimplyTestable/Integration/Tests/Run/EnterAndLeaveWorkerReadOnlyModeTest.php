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

    const TASK_COUNT = 2;
    
    private static $taskIdsAssignedToWorkers = array();

    public function testPrepareSequence() {
        $this->startJob();
        $this->resolveJob();
        $this->prepareJob();
        
        $preAssignmentTasks = $this->getTasks();
        $tasksToAssign = array_slice($preAssignmentTasks, 0, self::TASK_COUNT);

        foreach ($tasksToAssign as $task) {
            $this->runSymfonyCommand(self::$coreApplication, 'simplytestable:task:assigncollection ' . $task->id);
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
            if ($task->id <= self::TASK_COUNT) {
                $this->assertEquals('in-progress', $task->state);
            } else {
                $this->assertEquals('queued', $task->state);
            }
        }
    }
    
    
    /**
     * @depends testPrepareSequence
     */
    public function testWorkerEnterReadOnly() {
        foreach (self::$workers as $worker) {
            $adminMaintenanceEnterReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/enable-read-only/');
            $response = $this->getHttpClient()->getResponse($adminMaintenanceEnterReadOnlyRequest);            
            $this->assertEquals(200, $response->getResponseCode());
            
            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
            $this->assertEquals(200, $statusResponse->getResponseCode());
            
            $workerStatus = json_decode($statusResponse->getBody());
            $this->assertEquals('maintenance-read-only', $workerStatus->state);
        }       
    }
    
    
    /**
     * @depends testWorkerEnterReadOnly
     */
    public function testPerformTaskWhenWorkersAreReadOnly() {
        $prePerformTasks = $this->getTasks();
        foreach ($prePerformTasks as $task) {
            if ($task->id <= self::TASK_COUNT) {
                $result = $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
                $this->assertEquals('Unable to perform task, worker application is in maintenance read-only mode', trim($result));
            }
        }         
    }
    
    
    /**
     * @depends testPerformTaskWhenWorkersAreReadOnly
     */
    public function testLeaveReadOnlyModeAfterPerformingTasks() {
        self::clearRedis();

        foreach (self::$workers as $workerIndex => $worker) {
            $adminMaintenanceLeaveReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/leave-read-only/');
            $adminMaintenanceLeaveReadOnlyResponse = $this->getHttpClient()->getResponse($adminMaintenanceLeaveReadOnlyRequest);
            $this->assertEquals(200, $adminMaintenanceLeaveReadOnlyResponse->getResponseCode());

            if ($workerIndex === 0) {
                $this->assertEquals(
                        '["Set state to active","0 completed tasks ready to be enqueued","2 queued tasks ready to be enqueued","Enqueuing task [1]","Enqueuing task [2]"]',
                        $adminMaintenanceLeaveReadOnlyResponse->getBody()
                );
            } else {
                $this->assertEquals(
                        '["Set state to active","0 completed tasks ready to be enqueued","0 queued tasks ready to be enqueued"]',
                        $adminMaintenanceLeaveReadOnlyResponse->getBody()
                );
            }

            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
            $this->assertEquals(200, $statusResponse->getResponseCode());

            $workerStatus = json_decode($statusResponse->getBody());
            $this->assertEquals('active', $workerStatus->state);
        }
    }


    /**
     * @depends testLeaveReadOnlyModeAfterPerformingTasks
     */
    public function testPerformTasksAfterLeavingReadOnlyMode() {
        $tasks = $this->getTasks();
        foreach ($tasks as $task) {
            if ($task->id <= self::TASK_COUNT) {
                $taskPerformOutput = $this->runSymfonyCommand($task->worker, 'simplytestable:task:perform ' . $task->remote_id);
                $this->assertEquals('Performed ['.$task->remote_id.']', trim($taskPerformOutput));
            }
        }
    }


    /**
     * @depends testPerformTasksAfterLeavingReadOnlyMode
     */
    public function testWorkerEnterReadOnlyAfterPerformingTasks() {
        foreach (self::$workers as $worker) {
            $adminMaintenanceEnterReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/enable-read-only/');
            $response = $this->getHttpClient()->getResponse($adminMaintenanceEnterReadOnlyRequest);
            $this->assertEquals(200, $response->getResponseCode());

            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
            $this->assertEquals(200, $statusResponse->getResponseCode());

            $workerStatus = json_decode($statusResponse->getBody());
            $this->assertEquals('maintenance-read-only', $workerStatus->state);
        }
    }


    /**
     * @depends testWorkerEnterReadOnlyAfterPerformingTasks
     */
    public function testReportTaskCompletionWhenWorkersAreReadOnly() {
        $tasks = $this->getTasks();
        foreach ($tasks as $task) {
            if ($task->id <= self::TASK_COUNT) {
                $result = $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
                $this->assertEquals('Unable to report completion, worker application is in maintenance read-only mode', trim($result));
            }
        }
    }

    /**
     * @depends testReportTaskCompletionWhenWorkersAreReadOnly
     */
    public function testLeaveReadOnlyModeAfterReportingTaskCompletion() {
        self::clearRedis();

        foreach (self::$workers as $workerIndex => $worker) {
            $adminMaintenanceLeaveReadOnlyRequest = $this->getWorkerAdminHttpRequest('http://'.$worker.'/maintenance/leave-read-only/');
            $adminMaintenanceLeaveReadOnlyResponse = $this->getHttpClient()->getResponse($adminMaintenanceLeaveReadOnlyRequest);
            $this->assertEquals(200, $adminMaintenanceLeaveReadOnlyResponse->getResponseCode());

            if ($workerIndex === 0) {
                $this->assertEquals(
                        '["Set state to active","2 completed tasks ready to be enqueued","Enqueuing task [1]","Enqueuing task [2]","0 queued tasks ready to be enqueued"]',
                        $adminMaintenanceLeaveReadOnlyResponse->getBody()
                );
            } else {
                $this->assertEquals(
                        '["Set state to active","0 completed tasks ready to be enqueued","0 queued tasks ready to be enqueued"]',
                        $adminMaintenanceLeaveReadOnlyResponse->getBody()
                );
            }

            $workerStatusRequest = new \HttpRequest('http://'.$worker.'/status');
            $statusResponse = $this->getHttpClient()->getResponse($workerStatusRequest);
            $this->assertEquals(200, $statusResponse->getResponseCode());

            $workerStatus = json_decode($statusResponse->getBody());
            $this->assertEquals('active', $workerStatus->state);
        }
    }


    /**
     * @depends testLeaveReadOnlyModeAfterReportingTaskCompletion
     */
    public function testReportCompletionAfterLeavingReadOnlyMode() {
        $tasks = $this->getTasks();
        foreach ($tasks as $task) {
            if ($task->id <= self::TASK_COUNT) {
                $result = $this->runSymfonyCommand($task->worker, 'simplytestable:task:reportcompletion ' . $task->remote_id);
                $this->assertEquals('Reported task completion ['.$task->remote_id.']', trim($result));
            }
        }
    }
      
}