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
		foreach ($sourceMilestones as $sourceKey => $sourceMilestone) {
			foreach ($destinationMilestones as $destinationKey => $destinationMilestone) {
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
	 * Delete a milestone
	 *
	 * @param array $destinationMilestone
	 */
	private function deleteMilestone($destinationMilestone)
	{
		$this->send_post("delete_milestone/{$destinationMilestone['id']}", array());
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
	 * Return an array of milestones for the given projectid
	 *
	 * @param int $project
	 * @return array|mixed
	 */
	private function getMilestones($project)
	{
		return $this->send_get("get_milestones/{$project}");
	}
}
