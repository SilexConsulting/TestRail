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
	 * Perform sync operation
	 */
	public function sync()
	{
		$this->sourceMilestones = $this->getMilestones($this->sourceProject);
		$this->destinationMilestones = $this->getMilestones($this->destinationProject);

		$this->deleteOrphanedMilestones();
		$this->matchMilestones();
		$this->copyMilestones();

		$this->sourceSuites = $this->getSuites($this->sourceProject);
		$this->destinationSuites = $this->getSuites($this->destinationProject);

		$this->deleteOrphanedSuites();
		$this->matchSuites();
		$this->copySuites();

		foreach ($this->sourceSuites as $sourceSuite)
		{
			$this->syncSection($sourceSuite);
		}
	}

	/**
	 * If a milestone exists in Destination but not in Source, delete it from Destination
	 */
	private function deleteOrphanedMilestones()
	{
		foreach ($this->destinationMilestones as $destinationKey => $destinationMilestone) {
			$found = FALSE;
			foreach ($this->sourceMilestones as $sourceMilestone)
			{
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteMilestone($destinationMilestone);
				unset($this->destinationMilestones[$destinationKey]);
			}
		}
	}

	/**
	 * If a suite exists in Destination but not in Source, delete it from Destination
	 */
	private function deleteOrphanedSuites()
	{
		foreach ($this->destinationSuites as $destinationKey => $destinationSuite) {
			$found = FALSE;
			foreach ($this->sourceSuites as $sourceSuite)
			{
				if ($this->equalSuites($sourceSuite, $destinationSuite))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteSuite($destinationSuite);
				unset($this->destinationSuites[$destinationKey]);
			}
		}
	}

	/**
	 * If a milestone is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceMilestone with it's matching destinationMilestone's ID
	 */
	private function matchMilestones()
	{
		foreach ($this->sourceMilestones as &$sourceMilestone) {
			foreach ($this->destinationMilestones as $destinationMilestone) {
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone)) {
					$sourceMilestone['destination_id'] = $destinationMilestone['id'];
				}
			}
		}
	}

	/**
	 * If a suite is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceSuite with it's matching destinationSuite's ID
	 */
	private function matchSuites()
	{
		foreach ($this->sourceSuites as &$sourceSuite) {
			foreach ($this->destinationSuites as $destinationSuite) {
				if ($this->equalSuites($sourceSuite, $destinationSuite)) {
					$sourceSuite['destination_id'] = $destinationSuite['id'];
				}
			}
		}
	}

	/**
	 * Sync $sourceProject's milestones to $destinationProject
	 */
	public function copyMilestones()
	{
		foreach ($this->sourceMilestones as &$sourceMilestone) {
			if (!isset($sourceMilestone['destination_id'])) {
				$destinationMilestone = $this->addMilestone($this->destinationProject, $sourceMilestone);
				$sourceMilestone['destination_id'] = $destinationMilestone['id'];
			}
		}
	}

	/**
	 * Sync $sourceProject's suites to $destinationProject
	 */
	public function copySuites()
	{
		foreach ($this->sourceSuites as &$sourceSuite) {
			if (!isset($sourceSuite['destination_id'])) {
				$destinationSuite = $this->addSuite($this->destinationProject, $sourceSuite);
				$sourceSuite['destination_id'] = $destinationSuite['id'];
			}
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
	 * @return array|mixed
	 */
	private function addMilestone($projectId, $milestone)
	{
		$data = array(
			'name'          => $milestone['name'],
			'description'   => $milestone['description'],
			'due_on'        => $milestone['due_on'],
		);
		return $this->send_post("add_milestone/{$projectId}", $data);
	}

	/**
	 * Add a new suite to a project
	 *
	 * @param $projectId Project to add to
	 * @param $suite Suite to add
	 * @return array|mixed
	 */
	private function addSuite($projectId, $suite)
	{
		$data = array(
			'name'          => $suite['name'],
			'description'   => $suite['description'],
		);
		return $this->send_post("add_suite/{$projectId}", $data);
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
