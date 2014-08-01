<?php

require_once 'testrail-api/php/testrail.php';

/**
 * Operations to assist syncing TestRail cases between projects
 */
class TestRailSync extends TestRailAPIClient
{
	/**
	 * @var string $sourceProject
	 */
	private $sourceProject;

	/**
	 * @var string $destinationProject
	 */
	private $destinationProject;

	/**
	 * Set the source project
	 *
	 * @param int $sourceProject
	 */
	public function set_source($sourceProject)
	{
		$this->sourceProject = $sourceProject;
	}

	/**
	 * Set the destination project
	 *
	 * @param int $destinationProject
	 */
	public function set_destination($destinationProject)
	{
		$this->destinationProject = $destinationProject;
	}

	/**
	 * Sync $sourceProject's milestones to $destinationProject
	 */
	public function syncMilestones()
	{
		$sourceMilestones = $this->getMilestones($this->sourceProject);
		$destinationMilestones = $this->getMilestones($this->destinationProject);

		// If a milestone exists in Destination but not in Source, delete it from Destination
		foreach ($destinationMilestones as $destinationMilestone) {
			$found = FALSE;
			foreach ($sourceMilestones as $sourceMilestone)
			{
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteMilestone($destinationMilestone);
			}
		}

		// If a milestone is identical in Source and Destination, remove it from consideration
		// Outer loop must be destinationMilestones, in case there are duplicates in $sourceMilestones
		foreach ($destinationMilestones as $destinationKey => $destinationMilestone) {
			foreach ($sourceMilestones as $sourceKey => $sourceMilestone) {
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone)) {
					unset($sourceMilestones[$sourceKey]);
					unset($destinationMilestones[$destinationKey]);
				}
			}
		}

		// Copy remaining Source milestones to Destination
		foreach ($sourceMilestones as $sourceMilestone) {
			$this->addMilestone($this->destinationProject, $sourceMilestone);
		}
	}

	/**
	 * Sync $sourceProject's suites to $destinationProject
	 */
	public function syncSuites()
	{
		$sourceSuites = $this->getSuites($this->sourceProject);
		$destinationSuites = $this->getSuites($this->destinationProject);

		// If a suite exists in Destination but not in Source, delete it from Destination
		foreach ($destinationSuites as $destinationSuite) {
			$found = FALSE;
			foreach ($sourceSuites as $sourceSuite)
			{
				if ($this->equalSuites($sourceSuite, $destinationSuite))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteSuite($destinationSuite);
			}
		}

		// If a suite is identical in Source and Destination, remove it from consideration
		// Outer loop must be destinationSuites, in case there are duplicates in sourceSuites
		foreach ($destinationSuites as $destinationKey => $destinationSuite) {
			foreach ($sourceSuites as $sourceKey => $sourceSuite) {
				if ($this->equalSuites($sourceSuite, $destinationSuite)) {
					unset($sourceSuites[$sourceKey]);
					unset($destinationSuites[$destinationKey]);
				}
			}
		}

		// Copy remaining Source suites to Destination
		foreach ($sourceSuites as $sourceSuite) {
			$this->addSuite($this->destinationProject, $sourceSuite);
		}
	}

	/**
	 * Returns true if the milestones can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the milestones can be considered equal, else false
	 */
	private function equalMilestones($a, $b)
	{
		if ($a['name'] != $b['name']) {
			return false;
		}
		if ($a['description'] != $b['description']) {
			return false;
		}
		if ($a['due_on'] != $b['due_on']) {
			return false;
		}
		if ($a['is_completed'] != $b['is_completed']) {
			return false;
		}
		if ($a['completed_on'] != $b['completed_on']) {
			return false;
		}
		return true;
	}

	/**
	 * Returns true if the suites can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the suites can be considered equal, else false
	 */
	private function equalSuites($a, $b)
	{
		if ($a['name'] != $b['name']) {
			return false;
		}
		if ($a['description'] != $b['description']) {
			return false;
		}
		return true;
	}

	/**
	 * Add a new milestone to a project
	 *
	 * @param $projectId Project to add to
	 * @param $milestone Milestone to add
	 */
	private function addMilestone($projectId, $milestone)
	{
		$data = array(
			'name'          => $milestone['name'],
			'description'   => $milestone['description'],
			'due_on'        => $milestone['due_on'],
		);
		$this->send_post("add_milestone/{$projectId}", $data);
	}

	/**
	 * Add a new suite to a project
	 *
	 * @param $projectId Project to add to
	 * @param $suite Suite to add
	 */
	private function addSuite($projectId, $suite)
	{
		$data = array(
			'name'          => $suite['name'],
			'description'   => $suite['description'],
		);
		$this->send_post("add_suite/{$projectId}", $data);
	}

	/**
	 * Delete a milestone
	 *
	 * @param array $milestone
	 */
	private function deleteMilestone($milestone)
	{
		$this->send_post("delete_milestone/{$milestone['id']}", array());
	}

	/**
	 * Delete a suite
	 *
	 * @param array $suite
	 */
	private function deleteSuite($suite)
	{
		$this->send_post("delete_suite/{$suite['id']}", array());
	}

	/**
	 * Return an array of milestones for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getMilestones($projectId)
	{
		return $this->send_get("get_milestones/{$projectId}");
	}

	/**
	 * Return an array of suites for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getSuites($projectId)
	{
		return $this->send_get("get_suites/{$projectId}");
	}
}
